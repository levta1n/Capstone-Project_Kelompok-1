-- ============================================================================
-- Sistem Peminjaman Ruangan - Supabase schema
-- Role: requester (user biasa) dan admin
-- Jalankan di Supabase SQL Editor.
-- ============================================================================
--
-- (OPSIONAL) Jika sebelumnya sudah menjalankan schema lama dan mau mulai bersih,
-- buka komentar blok ini dulu. HATI-HATI: menghapus semua data booking/ruangan.
--
-- DROP VIEW IF EXISTS public.room_availability;
-- DROP FUNCTION IF EXISTS public.get_room_availability_for(TIMESTAMPTZ, TIMESTAMPTZ);
-- DROP TABLE IF EXISTS public.reservation_requests CASCADE;
-- DROP TABLE IF EXISTS public.rooms CASCADE;
-- DROP TABLE IF EXISTS public.profiles CASCADE;
-- DROP TYPE IF EXISTS public.app_role;
--
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Extension & tipe
-- ---------------------------------------------------------------------------
CREATE SCHEMA IF NOT EXISTS extensions;
CREATE EXTENSION IF NOT EXISTS btree_gist WITH SCHEMA extensions;

-- Jika schema lama masih memiliki role tambahan, migrasikan dengan hak minimum.
DO $$
DECLARE
  has_extra_role BOOLEAN;
BEGIN
  IF to_regtype('public.app_role') IS NOT NULL THEN
    SELECT EXISTS (
      SELECT 1
      FROM pg_enum AS enum_value
      JOIN pg_type AS enum_type ON enum_type.oid = enum_value.enumtypid
      JOIN pg_namespace AS enum_schema ON enum_schema.oid = enum_type.typnamespace
      WHERE enum_schema.nspname = 'public'
        AND enum_type.typname = 'app_role'
        AND enum_value.enumlabel NOT IN ('requester', 'admin')
    ) INTO has_extra_role;

    IF has_extra_role THEN
      DROP FUNCTION IF EXISTS public.current_app_role();
      DROP FUNCTION IF EXISTS public.admin_update_user(UUID, public.app_role, BOOLEAN);

      IF to_regclass('public.profiles') IS NOT NULL THEN
        ALTER TABLE public.profiles ALTER COLUMN role DROP DEFAULT;
        ALTER TABLE public.profiles
          ALTER COLUMN role TYPE TEXT
          USING CASE
            WHEN role::TEXT IN ('requester', 'admin') THEN role::TEXT
            ELSE 'requester'
          END;
      END IF;

      DROP TYPE public.app_role;
    END IF;
  END IF;
END;
$$;

DO $$
BEGIN
  CREATE TYPE public.app_role AS ENUM ('requester', 'admin');
EXCEPTION
  WHEN duplicate_object THEN NULL;
END;
$$;

DO $$
BEGIN
  IF to_regclass('public.profiles') IS NOT NULL
     AND EXISTS (
       SELECT 1
       FROM information_schema.columns
       WHERE table_schema = 'public'
         AND table_name = 'profiles'
         AND column_name = 'role'
         AND data_type = 'text'
     ) THEN
    ALTER TABLE public.profiles
      ALTER COLUMN role TYPE public.app_role
      USING role::public.app_role;
    ALTER TABLE public.profiles
      ALTER COLUMN role SET DEFAULT 'requester';
  END IF;
END;
$$;

-- ---------------------------------------------------------------------------
-- 2. Tabel
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.profiles (
  id UUID PRIMARY KEY REFERENCES auth.users (id) ON DELETE CASCADE,
  full_name TEXT NOT NULL,
  email TEXT UNIQUE,
  nim TEXT UNIQUE,
  unit_name TEXT,
  role public.app_role NOT NULL DEFAULT 'requester',
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  deleted_at TIMESTAMPTZ,
  must_change_password BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE public.profiles
  ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMPTZ;

CREATE TABLE IF NOT EXISTS public.rooms (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name TEXT NOT NULL,
  building TEXT NOT NULL DEFAULT 'Gedung Utama',
  floor_label TEXT NOT NULL,
  capacity INTEGER NOT NULL CHECK (capacity > 0),
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (building, name)
);

-- Requester mengajukan booking dengan status 'pending'. Admin menyetujui
-- atau menolak; hanya booking accepted yang mengunci jadwal.
CREATE TABLE IF NOT EXISTS public.reservation_requests (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  request_code TEXT GENERATED ALWAYS AS (
    'REQ-' || upper(substr(replace(id::TEXT, '-', ''), 1, 8))
  ) STORED UNIQUE,
  requester_id UUID NOT NULL REFERENCES public.profiles (id) ON DELETE RESTRICT,
  room_id UUID NOT NULL REFERENCES public.rooms (id) ON DELETE RESTRICT,
  purpose TEXT NOT NULL CHECK (length(trim(purpose)) > 0),
  starts_at TIMESTAMPTZ NOT NULL,
  ends_at TIMESTAMPTZ NOT NULL,
  participant_count INTEGER NOT NULL DEFAULT 1 CHECK (participant_count > 0),
  status TEXT NOT NULL DEFAULT 'pending',
  reviewed_by UUID REFERENCES public.profiles (id) ON DELETE SET NULL,
  reviewed_at TIMESTAMPTZ,
  rejection_reason TEXT,
  cancelled_by UUID REFERENCES public.profiles (id) ON DELETE SET NULL,
  cancelled_at TIMESTAMPTZ,
  cancellation_reason TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CHECK (ends_at > starts_at)
);

ALTER TABLE public.reservation_requests
  ADD COLUMN IF NOT EXISTS reviewed_by UUID REFERENCES public.profiles (id) ON DELETE SET NULL,
  ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS rejection_reason TEXT;
ALTER TABLE public.reservation_requests
  ALTER COLUMN status SET DEFAULT 'pending';
ALTER TABLE public.reservation_requests
  DROP CONSTRAINT IF EXISTS reservation_requests_status_check;
ALTER TABLE public.reservation_requests
  ADD CONSTRAINT reservation_requests_status_check
  CHECK (status IN ('pending', 'accepted', 'rejected', 'cancelled'));
ALTER TABLE public.reservation_requests
  DROP CONSTRAINT IF EXISTS reservation_requests_cancelled_at_check;
ALTER TABLE public.reservation_requests
  ADD CONSTRAINT reservation_requests_cancelled_at_check
  CHECK (status <> 'cancelled' OR cancelled_at IS NOT NULL);
UPDATE public.reservation_requests
SET reviewed_at = coalesce(reviewed_at, created_at)
WHERE status = 'accepted' AND reviewed_at IS NULL;
ALTER TABLE public.reservation_requests
  DROP CONSTRAINT IF EXISTS reservation_requests_reviewed_at_check;
ALTER TABLE public.reservation_requests
  ADD CONSTRAINT reservation_requests_reviewed_at_check
  CHECK (
    (status IN ('accepted', 'rejected') AND reviewed_at IS NOT NULL)
    OR (status = 'pending' AND reviewed_at IS NULL)
    OR status = 'cancelled'
  );

CREATE INDEX IF NOT EXISTS reservation_requests_requester_created_idx
  ON public.reservation_requests (requester_id, created_at DESC);
CREATE INDEX IF NOT EXISTS reservation_requests_starts_idx
  ON public.reservation_requests (starts_at);
CREATE INDEX IF NOT EXISTS reservation_requests_created_idx
  ON public.reservation_requests (created_at DESC);

-- Cegah double-booking di ruangan yang sama pada waktu yang beririsan.
ALTER TABLE public.reservation_requests
  DROP CONSTRAINT IF EXISTS reservation_requests_no_overlapping_active_requests;
ALTER TABLE public.reservation_requests
  ADD CONSTRAINT reservation_requests_no_overlapping_active_requests
  EXCLUDE USING gist (
    room_id extensions.gist_uuid_ops WITH =,
    tstzrange(starts_at, ends_at, '[)') WITH &&
  ) WHERE (status = 'accepted');

-- ---------------------------------------------------------------------------
-- 3. Trigger: updated_at
-- ---------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.set_updated_at()
RETURNS TRIGGER
LANGUAGE plpgsql
SET search_path = ''
AS $$
BEGIN
  new.updated_at := now();
  RETURN new;
END;
$$;

DROP TRIGGER IF EXISTS profiles_set_updated_at ON public.profiles;
CREATE TRIGGER profiles_set_updated_at
  BEFORE UPDATE ON public.profiles
  FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

DROP TRIGGER IF EXISTS rooms_set_updated_at ON public.rooms;
CREATE TRIGGER rooms_set_updated_at
  BEFORE UPDATE ON public.rooms
  FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

DROP TRIGGER IF EXISTS reservation_requests_set_updated_at ON public.reservation_requests;
CREATE TRIGGER reservation_requests_set_updated_at
  BEFORE UPDATE ON public.reservation_requests
  FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

-- ---------------------------------------------------------------------------
-- 4. Trigger: validasi booking (ruangan aktif, kapasitas, waktu di masa depan)
-- ---------------------------------------------------------------------------
DROP TRIGGER IF EXISTS reservation_requests_validate_room_capacity ON public.reservation_requests;
DROP FUNCTION IF EXISTS public.validate_reservation_room_capacity();

CREATE OR REPLACE FUNCTION public.validate_reservation()
RETURNS TRIGGER
LANGUAGE plpgsql
SET search_path = ''
AS $$
DECLARE
  room_capacity INTEGER;
  room_is_active BOOLEAN;
BEGIN
  IF tg_op = 'INSERT'
     AND (SELECT auth.uid()) IS NOT NULL
     AND new.starts_at <= now() THEN
    RAISE EXCEPTION 'Waktu mulai harus di masa depan.' USING ERRCODE = '23514';
  END IF;

  IF tg_op = 'INSERT'
     AND (SELECT auth.uid()) IS NOT NULL
     AND new.status = 'pending'
     AND EXISTS (
       SELECT 1
       FROM public.reservation_requests AS accepted
       WHERE accepted.room_id = new.room_id
         AND accepted.status = 'accepted'
         AND tstzrange(accepted.starts_at, accepted.ends_at, '[)')
           && tstzrange(new.starts_at, new.ends_at, '[)')
     ) THEN
    RAISE EXCEPTION 'Ruangan bentrok dengan peminjaman yang sudah disetujui.'
      USING ERRCODE = '23514';
  END IF;

  IF new.status <> 'accepted' THEN
    RETURN new;
  END IF;

  IF new.starts_at <= now() THEN
    RAISE EXCEPTION 'Waktu mulai harus di masa depan.' USING ERRCODE = '23514';
  END IF;

  SELECT room.capacity, room.is_active
    INTO room_capacity, room_is_active
  FROM public.rooms AS room
  WHERE room.id = new.room_id;

  IF NOT found OR NOT room_is_active THEN
    RAISE EXCEPTION 'Ruangan tidak aktif atau tidak ditemukan.' USING ERRCODE = '23514';
  END IF;

  IF new.participant_count > room_capacity THEN
    RAISE EXCEPTION 'Jumlah peserta melebihi kapasitas ruangan.' USING ERRCODE = '23514';
  END IF;

  RETURN new;
END;
$$;

DROP TRIGGER IF EXISTS reservation_requests_validate ON public.reservation_requests;
CREATE TRIGGER reservation_requests_validate
  BEFORE INSERT OR UPDATE OF room_id, participant_count, status, starts_at
  ON public.reservation_requests
  FOR EACH ROW EXECUTE FUNCTION public.validate_reservation();

-- ---------------------------------------------------------------------------
-- 5. Auto-buat profil saat akun auth dibuat
--    Role SELALU 'requester' di sini. Role admin hanya diubah lewat
--    admin_update_user() / service role, tidak pernah dari metadata.
--    Nonaktifkan sign-up publik di pengaturan Supabase Auth; akun dibuat
--    melalui Edge Function admin-create-user.
-- ---------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.handle_new_auth_user()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $$
DECLARE
  user_nim TEXT;
BEGIN
  user_nim := nullif(trim(new.raw_user_meta_data ->> 'nim'), '');

  IF user_nim IS NOT NULL
     AND (
       user_nim !~ '^[0-9]{1,32}$'
       OR lower(coalesce(new.email, '')) <> (user_nim || '@campus.univ.ac.id')
     ) THEN
    RAISE EXCEPTION 'Akun harus menggunakan NIM sebagai email @campus.univ.ac.id.'
      USING ERRCODE = '23514';
  END IF;

  INSERT INTO public.profiles (id, full_name, email, nim, unit_name)
  VALUES (
    new.id,
    coalesce(
      nullif(trim(new.raw_user_meta_data ->> 'full_name'), ''),
      nullif(split_part(coalesce(new.email, new.phone, ''), '@', 1), ''),
      'Pengguna'
    ),
    new.email,
    user_nim,
    nullif(trim(new.raw_user_meta_data ->> 'unit_name'), '')
  )
  ON CONFLICT (id) DO UPDATE
    SET email = excluded.email;

  RETURN new;
END;
$$;

REVOKE ALL ON FUNCTION public.handle_new_auth_user() FROM PUBLIC, anon, authenticated;

DROP TRIGGER IF EXISTS on_auth_user_created ON auth.users;
CREATE TRIGGER on_auth_user_created
  AFTER INSERT ON auth.users
  FOR EACH ROW EXECUTE FUNCTION public.handle_new_auth_user();

-- Backfill profil untuk akun auth yang sudah ada sebelum schema ini dijalankan.
INSERT INTO public.profiles (id, full_name, email)
SELECT
  u.id,
  coalesce(
    nullif(trim(u.raw_user_meta_data ->> 'full_name'), ''),
    nullif(split_part(coalesce(u.email, u.phone, ''), '@', 1), ''),
    'Pengguna'
  ),
  u.email
FROM auth.users AS u
ON CONFLICT (id) DO NOTHING;

-- ---------------------------------------------------------------------------
-- 6. Helper role
-- ---------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.current_app_role()
RETURNS public.app_role
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = ''
AS $$
  SELECT profile.role
  FROM public.profiles AS profile
  WHERE profile.id = (SELECT auth.uid())
$$;

CREATE OR REPLACE FUNCTION public.is_admin()
RETURNS BOOLEAN
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = ''
AS $$
  SELECT coalesce(
    (SELECT profile.role = 'admin' AND profile.is_active AND profile.deleted_at IS NULL
     FROM public.profiles AS profile
     WHERE profile.id = (SELECT auth.uid())),
    FALSE
  )
$$;

CREATE OR REPLACE FUNCTION public.is_active_user()
RETURNS BOOLEAN
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = ''
AS $$
  SELECT coalesce(
    (SELECT profile.is_active AND profile.deleted_at IS NULL
     FROM public.profiles AS profile
     WHERE profile.id = (SELECT auth.uid())),
    FALSE
  )
$$;

-- Hanya requester aktif yang boleh membuat booking (admin tidak boleh).
CREATE OR REPLACE FUNCTION public.can_book()
RETURNS BOOLEAN
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = ''
AS $$
  SELECT coalesce(
    (SELECT profile.role = 'requester' AND profile.is_active AND profile.deleted_at IS NULL
     FROM public.profiles AS profile
     WHERE profile.id = (SELECT auth.uid())),
    FALSE
  )
$$;

REVOKE ALL ON FUNCTION public.current_app_role() FROM PUBLIC, anon;
REVOKE ALL ON FUNCTION public.is_admin() FROM PUBLIC, anon;
REVOKE ALL ON FUNCTION public.is_active_user() FROM PUBLIC, anon;
REVOKE ALL ON FUNCTION public.can_book() FROM PUBLIC, anon;
GRANT EXECUTE ON FUNCTION public.current_app_role() TO authenticated;
GRANT EXECUTE ON FUNCTION public.is_admin() TO authenticated;
GRANT EXECUTE ON FUNCTION public.is_active_user() TO authenticated;
GRANT EXECUTE ON FUNCTION public.can_book() TO authenticated;

-- ---------------------------------------------------------------------------
-- 7. Privilege tabel (dikunci eksplisit; perubahan sensitif lewat RPC)
-- ---------------------------------------------------------------------------
REVOKE ALL ON TABLE public.profiles, public.rooms, public.reservation_requests
  FROM anon, authenticated;

GRANT USAGE ON SCHEMA public TO authenticated;

-- User hanya boleh ubah nama & unit miliknya sendiri. Kolom role, is_active,
-- nim, dll. TIDAK bisa diubah langsung dari client.
GRANT SELECT ON public.profiles TO authenticated;
GRANT UPDATE (full_name, unit_name) ON public.profiles TO authenticated;

GRANT SELECT, INSERT, UPDATE, DELETE ON public.rooms TO authenticated;

-- Booking: client hanya boleh membuat dan membaca. Pembatalan lewat RPC.
GRANT SELECT, INSERT ON public.reservation_requests TO authenticated;

-- ---------------------------------------------------------------------------
-- 8. Row Level Security
-- ---------------------------------------------------------------------------
ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.rooms ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.reservation_requests ENABLE ROW LEVEL SECURITY;

-- profiles
DROP POLICY IF EXISTS "Profiles are visible to owner and staff" ON public.profiles;
DROP POLICY IF EXISTS "Profiles are visible to owner and admin" ON public.profiles;
CREATE POLICY "Profiles are visible to owner and admin"
  ON public.profiles FOR SELECT TO authenticated
  USING (
    (id = (SELECT auth.uid()) AND (SELECT public.is_active_user()))
    OR (SELECT public.is_admin())
  );

DROP POLICY IF EXISTS "Users can update their own profile" ON public.profiles;
CREATE POLICY "Users can update their own profile"
  ON public.profiles FOR UPDATE TO authenticated
  USING (id = (SELECT auth.uid()) AND (SELECT public.is_active_user()))
  WITH CHECK (id = (SELECT auth.uid()) AND (SELECT public.is_active_user()));

-- rooms
DROP POLICY IF EXISTS "Authenticated users can read active rooms" ON public.rooms;
CREATE POLICY "Authenticated users can read active rooms"
  ON public.rooms FOR SELECT TO authenticated
  USING (
    ((SELECT public.is_active_user()) AND is_active)
    OR (SELECT public.is_admin())
  );

DROP POLICY IF EXISTS "Staff can insert rooms" ON public.rooms;
DROP POLICY IF EXISTS "Admin can insert rooms" ON public.rooms;
CREATE POLICY "Admin can insert rooms"
  ON public.rooms FOR INSERT TO authenticated
  WITH CHECK ((SELECT public.is_admin()));

DROP POLICY IF EXISTS "Staff can update rooms" ON public.rooms;
DROP POLICY IF EXISTS "Admin can update rooms" ON public.rooms;
CREATE POLICY "Admin can update rooms"
  ON public.rooms FOR UPDATE TO authenticated
  USING ((SELECT public.is_admin()))
  WITH CHECK ((SELECT public.is_admin()));

DROP POLICY IF EXISTS "Staff can delete rooms" ON public.rooms;
DROP POLICY IF EXISTS "Admin can delete rooms" ON public.rooms;
CREATE POLICY "Admin can delete rooms"
  ON public.rooms FOR DELETE TO authenticated
  USING ((SELECT public.is_admin()));

-- reservation_requests
DROP POLICY IF EXISTS "Requesters and staff can read requests" ON public.reservation_requests;
DROP POLICY IF EXISTS "Requesters and admin can read requests" ON public.reservation_requests;
CREATE POLICY "Requesters and admin can read requests"
  ON public.reservation_requests FOR SELECT TO authenticated
  USING (
    (requester_id = (SELECT auth.uid()) AND (SELECT public.is_active_user()))
    OR (SELECT public.is_admin())
  );

DROP POLICY IF EXISTS "Users can submit their own requests" ON public.reservation_requests;
DROP POLICY IF EXISTS "Requesters can submit their own bookings" ON public.reservation_requests;
CREATE POLICY "Requesters can submit their own bookings"
  ON public.reservation_requests FOR INSERT TO authenticated
  WITH CHECK (
    requester_id = (SELECT auth.uid())
    AND (SELECT public.can_book())
    AND status = 'pending'
    AND reviewed_by IS NULL
    AND reviewed_at IS NULL
    AND rejection_reason IS NULL
    AND cancelled_by IS NULL
    AND cancelled_at IS NULL
    AND cancellation_reason IS NULL
  );

-- Tidak ada policy UPDATE/DELETE untuk booking: semua lewat RPC di bawah.
DROP POLICY IF EXISTS "Owners can update pending requests and staff can review" ON public.reservation_requests;
DROP POLICY IF EXISTS "Only staff can update requests" ON public.reservation_requests;

-- ---------------------------------------------------------------------------
-- 9. RPC: pembatalan booking
-- ---------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.expire_stale_reservation_requests()
RETURNS INTEGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $$
DECLARE
  expired_count INTEGER;
BEGIN
  IF (SELECT auth.uid()) IS NOT NULL
     AND NOT (SELECT public.is_active_user()) THEN
    RAISE EXCEPTION 'Akun tidak aktif.' USING ERRCODE = '42501';
  END IF;

  UPDATE public.reservation_requests AS request
  SET status = 'rejected',
      reviewed_by = NULL,
      reviewed_at = now(),
      rejection_reason = 'Permintaan otomatis ditolak karena waktu mulai telah lewat tanpa persetujuan admin.'
  WHERE request.status = 'pending'
    AND request.starts_at <= now();

  GET DIAGNOSTICS expired_count = ROW_COUNT;
  RETURN expired_count;
END;
$$;

-- Permintaan yang otomatis ditolak oleh antrean dibuka kembali saat booking
-- yang menghalangi jadwal tersebut dibatalkan.
CREATE OR REPLACE FUNCTION public.restore_conflicting_reservation_queue(
  p_room_id UUID,
  p_starts_at TIMESTAMPTZ,
  p_ends_at TIMESTAMPTZ
)
RETURNS INTEGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $$
DECLARE
  restored_count INTEGER;
BEGIN
  UPDATE public.reservation_requests AS request
  SET status = 'pending',
      reviewed_by = NULL,
      reviewed_at = NULL,
      rejection_reason = NULL
  WHERE request.room_id = p_room_id
    AND request.status = 'rejected'
    AND request.reviewed_by IS NULL
    AND request.rejection_reason = 'Dibatalkan otomatis karena jadwal ruangan bentrok dengan peminjaman lain.'
    AND request.starts_at > now()
    AND tstzrange(request.starts_at, request.ends_at, '[)')
      && tstzrange(p_starts_at, p_ends_at, '[)');

  GET DIAGNOSTICS restored_count = ROW_COUNT;
  RETURN restored_count;
END;
$$;

-- Requester membatalkan booking miliknya sendiri (sebelum dimulai).
CREATE OR REPLACE FUNCTION public.cancel_reservation(p_request_id UUID)
RETURNS BOOLEAN
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $$
DECLARE
  cancelled_id UUID;
  cancelled_room_id UUID;
  cancelled_starts_at TIMESTAMPTZ;
  cancelled_ends_at TIMESTAMPTZ;
BEGIN
  IF NOT (SELECT public.is_active_user()) THEN
    RAISE EXCEPTION 'Akun tidak aktif.' USING ERRCODE = '42501';
  END IF;

  SELECT request.room_id
    INTO cancelled_room_id
  FROM public.reservation_requests AS request
  WHERE request.id = p_request_id
    AND request.requester_id = (SELECT auth.uid())
    AND request.status IN ('pending', 'accepted')
    AND request.starts_at > now();

  IF cancelled_room_id IS NULL THEN
    RETURN FALSE;
  END IF;

  PERFORM 1
  FROM public.rooms AS room
  WHERE room.id = cancelled_room_id
  FOR UPDATE;

  UPDATE public.reservation_requests AS request
  SET status = 'cancelled',
      cancelled_by = (SELECT auth.uid()),
      cancelled_at = now(),
      cancellation_reason = 'Dibatalkan oleh pemesan.'
  WHERE request.id = p_request_id
    AND request.requester_id = (SELECT auth.uid())
    AND request.status IN ('pending', 'accepted')
    AND request.starts_at > now()
  RETURNING request.id, request.room_id, request.starts_at, request.ends_at
    INTO cancelled_id, cancelled_room_id, cancelled_starts_at, cancelled_ends_at;

  IF cancelled_id IS NOT NULL THEN
    PERFORM public.restore_conflicting_reservation_queue(
      cancelled_room_id,
      cancelled_starts_at,
      cancelled_ends_at
    );
  END IF;

  RETURN cancelled_id IS NOT NULL;
END;
$$;

-- Admin memutuskan permintaan yang masih menunggu. Exclusion constraint
-- menjadi pemeriksaan terakhir untuk mencegah bentrok saat menyetujui.
CREATE OR REPLACE FUNCTION public.admin_review_reservation(
  p_request_id UUID,
  p_status TEXT,
  p_rejection_reason TEXT DEFAULT NULL
)
RETURNS BOOLEAN
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $$
DECLARE
  reviewed_id UUID;
  request_room_id UUID;
  request_starts_at TIMESTAMPTZ;
  request_ends_at TIMESTAMPTZ;
  request_created_at TIMESTAMPTZ;
  request_request_code TEXT;
  earlier_request_code TEXT;
BEGIN
  IF NOT (SELECT public.is_admin()) THEN
    RAISE EXCEPTION 'Hanya admin yang boleh melakukan ini.' USING ERRCODE = '42501';
  END IF;

  PERFORM public.expire_stale_reservation_requests();

  IF p_status IS NULL OR p_status NOT IN ('accepted', 'rejected') THEN
    RAISE EXCEPTION 'Keputusan harus diterima atau ditolak.' USING ERRCODE = '22023';
  END IF;

  IF p_status = 'rejected'
     AND (p_rejection_reason IS NULL OR length(trim(p_rejection_reason)) = 0) THEN
    RAISE EXCEPTION 'Alasan penolakan wajib diisi.' USING ERRCODE = '22023';
  END IF;

  SELECT request.room_id
    INTO request_room_id
  FROM public.reservation_requests AS request
  WHERE request.id = p_request_id
    AND request.status = 'pending';

  IF request_room_id IS NULL THEN
    RETURN FALSE;
  END IF;

  PERFORM 1
  FROM public.rooms AS room
  WHERE room.id = request_room_id
  FOR UPDATE;

  SELECT request.room_id, request.starts_at, request.ends_at, request.created_at, request.request_code
    INTO request_room_id, request_starts_at, request_ends_at, request_created_at, request_request_code
  FROM public.reservation_requests AS request
  WHERE request.id = p_request_id
    AND request.status = 'pending'
  FOR UPDATE;

  IF NOT found THEN
    RETURN FALSE;
  END IF;

  IF p_status = 'accepted' THEN
    SELECT earlier.request_code
      INTO earlier_request_code
    FROM public.reservation_requests AS earlier
    WHERE earlier.room_id = request_room_id
      AND earlier.status = 'pending'
      AND earlier.id <> p_request_id
      AND (
        earlier.created_at < request_created_at
        OR (earlier.created_at = request_created_at AND earlier.id < p_request_id)
      )
      AND tstzrange(earlier.starts_at, earlier.ends_at, '[)')
        && tstzrange(request_starts_at, request_ends_at, '[)')
    ORDER BY earlier.created_at, earlier.id
    LIMIT 1;

    IF earlier_request_code IS NOT NULL THEN
      RAISE EXCEPTION
        'Permintaan % belum dapat disetujui: permintaan lebih awal % masih menunggu dan jadwalnya bentrok. Proses permintaan yang lebih awal terlebih dahulu.',
        request_request_code,
        earlier_request_code
        USING ERRCODE = '23514';
    END IF;
  END IF;

  IF p_status = 'accepted' AND EXISTS (
    SELECT 1
    FROM public.reservation_requests AS accepted
    WHERE accepted.room_id = request_room_id
      AND accepted.status = 'accepted'
      AND accepted.id <> p_request_id
      AND tstzrange(accepted.starts_at, accepted.ends_at, '[)')
        && tstzrange(request_starts_at, request_ends_at, '[)')
  ) THEN
    UPDATE public.reservation_requests AS request
    SET status = 'rejected',
        reviewed_by = NULL,
        reviewed_at = now(),
        rejection_reason = 'Dibatalkan otomatis karena jadwal ruangan bentrok dengan peminjaman lain.'
    WHERE request.id = p_request_id
      AND request.status = 'pending'
    RETURNING request.id INTO reviewed_id;

    RETURN reviewed_id IS NOT NULL;
  END IF;

  UPDATE public.reservation_requests AS request
  SET status = p_status,
      reviewed_by = (SELECT auth.uid()),
      reviewed_at = now(),
      rejection_reason = CASE
        WHEN p_status = 'rejected' THEN trim(p_rejection_reason)
        ELSE NULL
      END
  WHERE request.id = p_request_id
    AND request.status = 'pending'
  RETURNING request.id INTO reviewed_id;

  IF reviewed_id IS NOT NULL AND p_status = 'accepted' THEN
    UPDATE public.reservation_requests AS request
    SET status = 'rejected',
        reviewed_by = NULL,
        reviewed_at = now(),
        rejection_reason = 'Dibatalkan otomatis karena jadwal ruangan bentrok dengan peminjaman lain.'
    WHERE request.room_id = request_room_id
      AND request.status = 'pending'
      AND request.id <> p_request_id
      AND tstzrange(request.starts_at, request.ends_at, '[)')
        && tstzrange(request_starts_at, request_ends_at, '[)');
  END IF;

  RETURN reviewed_id IS NOT NULL;
END;
$$;

-- Admin membatalkan booking siapa pun (failsafe: bentrok / kendala mendadak).
-- Alasan wajib diisi supaya tercatat di laporan.
CREATE OR REPLACE FUNCTION public.admin_cancel_reservation(
  p_request_id UUID,
  p_reason TEXT
)
RETURNS BOOLEAN
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $$
DECLARE
  cancelled_id UUID;
  cancelled_room_id UUID;
  cancelled_starts_at TIMESTAMPTZ;
  cancelled_ends_at TIMESTAMPTZ;
BEGIN
  IF NOT (SELECT public.is_admin()) THEN
    RAISE EXCEPTION 'Hanya admin yang boleh melakukan ini.' USING ERRCODE = '42501';
  END IF;

  IF p_reason IS NULL OR length(trim(p_reason)) = 0 THEN
    RAISE EXCEPTION 'Alasan pembatalan wajib diisi.' USING ERRCODE = '22023';
  END IF;

  SELECT request.room_id
    INTO cancelled_room_id
  FROM public.reservation_requests AS request
  WHERE request.id = p_request_id
    AND request.status = 'accepted'
    AND request.ends_at > now();

  IF cancelled_room_id IS NULL THEN
    RETURN FALSE;
  END IF;

  PERFORM 1
  FROM public.rooms AS room
  WHERE room.id = cancelled_room_id
  FOR UPDATE;

  UPDATE public.reservation_requests AS request
  SET status = 'cancelled',
      cancelled_by = (SELECT auth.uid()),
      cancelled_at = now(),
      cancellation_reason = trim(p_reason)
  WHERE request.id = p_request_id
    AND request.status = 'accepted'
    AND request.ends_at > now()
  RETURNING request.id, request.room_id, request.starts_at, request.ends_at
    INTO cancelled_id, cancelled_room_id, cancelled_starts_at, cancelled_ends_at;

  IF cancelled_id IS NOT NULL THEN
    PERFORM public.restore_conflicting_reservation_queue(
      cancelled_room_id,
      cancelled_starts_at,
      cancelled_ends_at
    );
  END IF;

  RETURN cancelled_id IS NOT NULL;
END;
$$;

-- ---------------------------------------------------------------------------
-- 10. RPC: manajemen ruangan & akun (admin)
-- ---------------------------------------------------------------------------
-- Suspend ruangan; opsional sekaligus membatalkan booking yang belum selesai.
-- Mengembalikan jumlah booking yang dibatalkan.
-- (Mengaktifkan kembali: UPDATE rooms SET is_active = TRUE, boleh langsung.)
CREATE OR REPLACE FUNCTION public.admin_suspend_room(
  p_room_id UUID,
  p_reason TEXT DEFAULT NULL,
  p_cancel_future BOOLEAN DEFAULT TRUE
)
RETURNS INTEGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $$
DECLARE
  cancelled_count INTEGER := 0;
  cancelled_request RECORD;
  cancelled_room_ids UUID[] := ARRAY[]::UUID[];
  cancelled_starts_at TIMESTAMPTZ[] := ARRAY[]::TIMESTAMPTZ[];
  cancelled_ends_at TIMESTAMPTZ[] := ARRAY[]::TIMESTAMPTZ[];
  cancelled_index INTEGER;
BEGIN
  IF NOT (SELECT public.is_admin()) THEN
    RAISE EXCEPTION 'Hanya admin yang boleh melakukan ini.' USING ERRCODE = '42501';
  END IF;

  UPDATE public.rooms AS room
  SET is_active = FALSE
  WHERE room.id = p_room_id;

  IF NOT found THEN
    RAISE EXCEPTION 'Ruangan tidak ditemukan.' USING ERRCODE = 'P0002';
  END IF;

  IF p_cancel_future THEN
    FOR cancelled_request IN
      UPDATE public.reservation_requests AS request
      SET status = 'cancelled',
          cancelled_by = (SELECT auth.uid()),
          cancelled_at = now(),
          cancellation_reason = coalesce(
            nullif(trim(p_reason), ''),
            'Ruangan ditangguhkan oleh admin.'
          )
      WHERE request.room_id = p_room_id
        AND request.status = 'accepted'
        AND request.ends_at > now()
      RETURNING request.room_id, request.starts_at, request.ends_at
    LOOP
      cancelled_count := cancelled_count + 1;
      cancelled_room_ids := array_append(cancelled_room_ids, cancelled_request.room_id);
      cancelled_starts_at := array_append(cancelled_starts_at, cancelled_request.starts_at);
      cancelled_ends_at := array_append(cancelled_ends_at, cancelled_request.ends_at);
    END LOOP;

    FOR cancelled_index IN 1..cancelled_count
    LOOP
      PERFORM public.restore_conflicting_reservation_queue(
        cancelled_room_ids[cancelled_index],
        cancelled_starts_at[cancelled_index],
        cancelled_ends_at[cancelled_index]
      );
    END LOOP;
  END IF;

  RETURN cancelled_count;
END;
$$;

-- Ubah role / nonaktifkan akun. Admin tidak boleh menurunkan atau
-- menonaktifkan dirinya sendiri.
CREATE OR REPLACE FUNCTION public.admin_update_user(
  p_user_id UUID,
  p_role public.app_role DEFAULT NULL,
  p_is_active BOOLEAN DEFAULT NULL
)
RETURNS BOOLEAN
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $$
BEGIN
  IF NOT (SELECT public.is_admin()) THEN
    RAISE EXCEPTION 'Hanya admin yang boleh melakukan ini.' USING ERRCODE = '42501';
  END IF;

  IF p_user_id = (SELECT auth.uid())
     AND ((p_role IS NOT NULL AND p_role <> 'admin')
          OR p_is_active IS NOT DISTINCT FROM FALSE) THEN
    RAISE EXCEPTION 'Admin tidak boleh menurunkan/menonaktifkan akunnya sendiri.'
      USING ERRCODE = '42501';
  END IF;

  UPDATE public.profiles AS profile
  SET role = coalesce(p_role, profile.role),
      is_active = coalesce(p_is_active, profile.is_active)
  WHERE profile.id = p_user_id;

  RETURN found;
END;
$$;

-- Dipanggil setelah user berhasil mengganti password default.
CREATE OR REPLACE FUNCTION public.mark_password_changed()
RETURNS VOID
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = ''
AS $$
BEGIN
  IF NOT (SELECT public.is_active_user()) THEN
    RAISE EXCEPTION 'Akun tidak aktif.' USING ERRCODE = '42501';
  END IF;

  UPDATE public.profiles AS profile
  SET must_change_password = FALSE
  WHERE profile.id = (SELECT auth.uid());
END;
$$;

-- ---------------------------------------------------------------------------
-- 11. RPC: laporan (admin)
-- ---------------------------------------------------------------------------
-- Detail booking per rentang waktu (berdasarkan waktu mulai), opsional per ruangan.
DROP FUNCTION IF EXISTS public.admin_booking_report(TIMESTAMPTZ, TIMESTAMPTZ, UUID);
CREATE OR REPLACE FUNCTION public.admin_booking_report(
  p_from TIMESTAMPTZ,
  p_to TIMESTAMPTZ,
  p_room_id UUID DEFAULT NULL
)
RETURNS TABLE (
  request_code TEXT,
  requester_name TEXT,
  requester_nim TEXT,
  requester_unit TEXT,
  room_name TEXT,
  building TEXT,
  floor_label TEXT,
  purpose TEXT,
  starts_at TIMESTAMPTZ,
  ends_at TIMESTAMPTZ,
  duration_hours NUMERIC,
  participant_count INTEGER,
  status TEXT,
  reviewed_by_name TEXT,
  reviewed_at TIMESTAMPTZ,
  rejection_reason TEXT,
  cancelled_by_name TEXT,
  cancelled_at TIMESTAMPTZ,
  cancellation_reason TEXT,
  created_at TIMESTAMPTZ
)
LANGUAGE plpgsql
STABLE
SECURITY DEFINER
SET search_path = ''
AS $$
BEGIN
  IF NOT (SELECT public.is_admin()) THEN
    RAISE EXCEPTION 'Hanya admin yang boleh melakukan ini.' USING ERRCODE = '42501';
  END IF;

  IF p_from IS NULL OR p_to IS NULL OR p_to <= p_from THEN
    RAISE EXCEPTION 'Rentang waktu tidak valid.' USING ERRCODE = '22023';
  END IF;

  RETURN QUERY
  SELECT
    rr.request_code,
    pr.full_name,
    pr.nim,
    pr.unit_name,
    rm.name,
    rm.building,
    rm.floor_label,
    rr.purpose,
    rr.starts_at,
    rr.ends_at,
    round(extract(EPOCH FROM (rr.ends_at - rr.starts_at)) / 3600.0, 2),
    rr.participant_count,
    rr.status,
    reviewer.full_name,
    rr.reviewed_at,
    rr.rejection_reason,
    cb.full_name,
    rr.cancelled_at,
    rr.cancellation_reason,
    rr.created_at
  FROM public.reservation_requests AS rr
  JOIN public.profiles AS pr ON pr.id = rr.requester_id
  JOIN public.rooms AS rm ON rm.id = rr.room_id
  LEFT JOIN public.profiles AS reviewer ON reviewer.id = rr.reviewed_by
  LEFT JOIN public.profiles AS cb ON cb.id = rr.cancelled_by
  WHERE rr.starts_at >= p_from
    AND rr.starts_at < p_to
    AND (p_room_id IS NULL OR rr.room_id = p_room_id)
  ORDER BY rr.starts_at, rm.name;
END;
$$;

-- Ringkasan per ruangan pada rentang waktu.
DROP FUNCTION IF EXISTS public.admin_room_usage_report(TIMESTAMPTZ, TIMESTAMPTZ);
CREATE OR REPLACE FUNCTION public.admin_room_usage_report(
  p_from TIMESTAMPTZ,
  p_to TIMESTAMPTZ
)
RETURNS TABLE (
  room_id UUID,
  room_name TEXT,
  building TEXT,
  floor_label TEXT,
  total_bookings INTEGER,
  accepted_count INTEGER,
  pending_count INTEGER,
  rejected_count INTEGER,
  cancelled_count INTEGER,
  cancelled_by_admin_count INTEGER,
  booked_hours NUMERIC,
  total_participants INTEGER
)
LANGUAGE plpgsql
STABLE
SECURITY DEFINER
SET search_path = ''
AS $$
BEGIN
  IF NOT (SELECT public.is_admin()) THEN
    RAISE EXCEPTION 'Hanya admin yang boleh melakukan ini.' USING ERRCODE = '42501';
  END IF;

  IF p_from IS NULL OR p_to IS NULL OR p_to <= p_from THEN
    RAISE EXCEPTION 'Rentang waktu tidak valid.' USING ERRCODE = '22023';
  END IF;

  RETURN QUERY
  SELECT
    rm.id,
    rm.name,
    rm.building,
    rm.floor_label,
    count(rr.id)::INTEGER,
    (count(rr.id) FILTER (WHERE rr.status = 'accepted'))::INTEGER,
    (count(rr.id) FILTER (WHERE rr.status = 'pending'))::INTEGER,
    (count(rr.id) FILTER (WHERE rr.status = 'rejected'))::INTEGER,
    (count(rr.id) FILTER (WHERE rr.status = 'cancelled'))::INTEGER,
    (count(rr.id) FILTER (
      WHERE rr.status = 'cancelled'
        AND rr.cancelled_by IS DISTINCT FROM rr.requester_id
    ))::INTEGER,
    coalesce(
      round(
        sum(extract(EPOCH FROM (rr.ends_at - rr.starts_at)) / 3600.0)
          FILTER (WHERE rr.status = 'accepted'),
        2
      ),
      0
    ),
    coalesce(
      sum(rr.participant_count) FILTER (WHERE rr.status = 'accepted'),
      0
    )::INTEGER
  FROM public.rooms AS rm
  LEFT JOIN public.reservation_requests AS rr
    ON rr.room_id = rm.id
    AND rr.starts_at >= p_from
    AND rr.starts_at < p_to
  GROUP BY rm.id
  ORDER BY rm.building, rm.floor_label, rm.name;
END;
$$;

-- ---------------------------------------------------------------------------
-- 12. Ketersediaan ruangan
-- ---------------------------------------------------------------------------
-- Status "sekarang". Satu ruangan hanya bisa dipakai satu booking pada satu
-- waktu (dijamin exclusion constraint), jadi statusnya Terpakai / Tersedia.
-- View ini hanya dipakai dashboard admin. SECURITY INVOKER memastikan hak
-- akses dan RLS tabel sumber mengikuti pemanggil.
DROP VIEW IF EXISTS public.room_availability;
CREATE VIEW public.room_availability
WITH (security_invoker = TRUE)
AS
SELECT
  room.id AS room_id,
  room.name,
  room.building,
  room.floor_label,
  room.capacity,
  room.is_active,
  occ.occupied AS is_occupied_now,
  CASE
    WHEN NOT room.is_active THEN 'Tidak aktif'
    WHEN occ.occupied THEN 'Terpakai'
    ELSE 'Tersedia'
  END AS availability_status
FROM public.rooms AS room
CROSS JOIN LATERAL (
  SELECT EXISTS (
    SELECT 1
    FROM public.reservation_requests AS rr
    WHERE rr.room_id = room.id
      AND rr.status = 'accepted'
      AND rr.starts_at <= now()
      AND rr.ends_at > now()
  ) AS occupied
) AS occ
WHERE room.is_active
  AND (SELECT public.is_admin());

REVOKE ALL ON public.room_availability FROM anon, authenticated;
GRANT SELECT ON public.room_availability TO authenticated;

-- Ketersediaan pada rentang waktu tertentu, bisa difilter berdasarkan
-- gedung, lantai, dan kapasitas minimum. Hanya ruangan aktif.
DROP FUNCTION IF EXISTS public.get_room_availability_for(TIMESTAMPTZ, TIMESTAMPTZ);

CREATE OR REPLACE FUNCTION public.get_room_availability_for(
  p_starts_at TIMESTAMPTZ,
  p_ends_at TIMESTAMPTZ,
  p_building TEXT DEFAULT NULL,
  p_floor_label TEXT DEFAULT NULL,
  p_min_capacity INTEGER DEFAULT NULL
)
RETURNS TABLE (
  room_id UUID,
  name TEXT,
  building TEXT,
  floor_label TEXT,
  capacity INTEGER,
  conflict_count INTEGER,
  availability_status TEXT,
  is_available BOOLEAN
)
LANGUAGE plpgsql
STABLE
SECURITY DEFINER
SET search_path = ''
AS $$
BEGIN
  IF NOT (SELECT public.is_active_user()) THEN
    RAISE EXCEPTION 'Sesi login diperlukan atau akun tidak aktif.' USING ERRCODE = '28000';
  END IF;

  IF p_starts_at IS NULL OR p_ends_at IS NULL OR p_ends_at <= p_starts_at THEN
    RAISE EXCEPTION 'Rentang waktu tidak valid.' USING ERRCODE = '22023';
  END IF;

  RETURN QUERY
  SELECT
    room.id,
    room.name,
    room.building,
    room.floor_label,
    room.capacity,
    count(rr.id)::INTEGER,
    CASE WHEN count(rr.id) > 0 THEN 'Bentrok' ELSE 'Tersedia' END,
    count(rr.id) = 0
  FROM public.rooms AS room
  LEFT JOIN public.reservation_requests AS rr
    ON rr.room_id = room.id
    AND rr.status = 'accepted'
    AND tstzrange(rr.starts_at, rr.ends_at, '[)')
      && tstzrange(p_starts_at, p_ends_at, '[)')
  WHERE room.is_active
    AND (p_building IS NULL OR room.building = p_building)
    AND (p_floor_label IS NULL OR room.floor_label = p_floor_label)
    AND (p_min_capacity IS NULL OR room.capacity >= p_min_capacity)
  GROUP BY room.id
  ORDER BY room.building, room.floor_label, room.name;
END;
$$;

-- ---------------------------------------------------------------------------
-- 13. Hak eksekusi fungsi RPC
-- ---------------------------------------------------------------------------
REVOKE ALL ON FUNCTION public.restore_conflicting_reservation_queue(UUID, TIMESTAMPTZ, TIMESTAMPTZ) FROM PUBLIC, anon, authenticated;
REVOKE ALL ON FUNCTION public.expire_stale_reservation_requests() FROM PUBLIC, anon;
REVOKE ALL ON FUNCTION public.cancel_reservation(UUID) FROM PUBLIC, anon;
REVOKE ALL ON FUNCTION public.admin_review_reservation(UUID, TEXT, TEXT) FROM PUBLIC, anon;
REVOKE ALL ON FUNCTION public.admin_cancel_reservation(UUID, TEXT) FROM PUBLIC, anon;
REVOKE ALL ON FUNCTION public.admin_suspend_room(UUID, TEXT, BOOLEAN) FROM PUBLIC, anon;
REVOKE ALL ON FUNCTION public.admin_update_user(UUID, public.app_role, BOOLEAN) FROM PUBLIC, anon;
REVOKE ALL ON FUNCTION public.mark_password_changed() FROM PUBLIC, anon;
REVOKE ALL ON FUNCTION public.admin_booking_report(TIMESTAMPTZ, TIMESTAMPTZ, UUID) FROM PUBLIC, anon;
REVOKE ALL ON FUNCTION public.admin_room_usage_report(TIMESTAMPTZ, TIMESTAMPTZ) FROM PUBLIC, anon;
REVOKE ALL ON FUNCTION public.get_room_availability_for(TIMESTAMPTZ, TIMESTAMPTZ, TEXT, TEXT, INTEGER) FROM PUBLIC, anon;

GRANT EXECUTE ON FUNCTION public.cancel_reservation(UUID) TO authenticated;
GRANT EXECUTE ON FUNCTION public.expire_stale_reservation_requests() TO authenticated;
GRANT EXECUTE ON FUNCTION public.admin_review_reservation(UUID, TEXT, TEXT) TO authenticated;
GRANT EXECUTE ON FUNCTION public.admin_cancel_reservation(UUID, TEXT) TO authenticated;
GRANT EXECUTE ON FUNCTION public.admin_suspend_room(UUID, TEXT, BOOLEAN) TO authenticated;
GRANT EXECUTE ON FUNCTION public.admin_update_user(UUID, public.app_role, BOOLEAN) TO authenticated;
GRANT EXECUTE ON FUNCTION public.mark_password_changed() TO authenticated;
GRANT EXECUTE ON FUNCTION public.admin_booking_report(TIMESTAMPTZ, TIMESTAMPTZ, UUID) TO authenticated;
GRANT EXECUTE ON FUNCTION public.admin_room_usage_report(TIMESTAMPTZ, TIMESTAMPTZ) TO authenticated;
GRANT EXECUTE ON FUNCTION public.get_room_availability_for(TIMESTAMPTZ, TIMESTAMPTZ, TEXT, TEXT, INTEGER) TO authenticated;

-- Expire pending requests automatically even when nobody is using the app.
CREATE EXTENSION IF NOT EXISTS pg_cron;
SELECT cron.schedule(
  'expire-stale-reservation-requests',
  '* * * * *',
  'SELECT public.expire_stale_reservation_requests();'
);

-- ---------------------------------------------------------------------------
-- 14. Data awal ruangan
-- ---------------------------------------------------------------------------
INSERT INTO public.rooms (name, building, floor_label, capacity)
VALUES
  ('Ruang 104', 'Gedung Utama', 'Lantai 1', 24),
  ('Ruang 118', 'Gedung Utama', 'Lantai 1', 20),
  ('Ruang 212', 'Gedung Utama', 'Lantai 2', 16),
  ('Ruang 305', 'Gedung Utama', 'Lantai 3', 30)
ON CONFLICT (building, name) DO NOTHING;

-- ---------------------------------------------------------------------------
-- 15. Realtime
-- ---------------------------------------------------------------------------
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_publication WHERE pubname = 'supabase_realtime') THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_publication_tables
      WHERE pubname = 'supabase_realtime'
        AND schemaname = 'public'
        AND tablename = 'reservation_requests'
    ) THEN
      EXECUTE 'ALTER PUBLICATION supabase_realtime ADD TABLE public.reservation_requests';
    END IF;

    IF NOT EXISTS (
      SELECT 1 FROM pg_publication_tables
      WHERE pubname = 'supabase_realtime'
        AND schemaname = 'public'
        AND tablename = 'rooms'
    ) THEN
      EXECUTE 'ALTER PUBLICATION supabase_realtime ADD TABLE public.rooms';
    END IF;
  END IF;
END;
$$;

-- ---------------------------------------------------------------------------
-- 16. Admin pertama (jalankan SEKALI setelah akun admin dibuat di Auth)
--     Ganti emailnya, lalu buka komentar.
-- ---------------------------------------------------------------------------
-- UPDATE public.profiles
-- SET role = 'admin', must_change_password = FALSE
-- WHERE email = 'admin@contoh.ac.id';

NOTIFY pgrst, 'reload schema';