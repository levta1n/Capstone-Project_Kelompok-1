# Panduan Menerapkan dan Memeriksa RLS SIMPERU

Panduan ini untuk pemilik/admin project Supabase dan anggota tim yang membantu mengelola SIMPERU. Schema yang menjadi acuan adalah [`../supabase/schema.sql`](../supabase/schema.sql).

## Sebelum menjalankan SQL

`schema.sql` bukan hanya skrip RLS. Skrip ini juga membuat atau memperbarui tabel, role database, fungsi/RPC, trigger, constraint, data awal ruangan, serta konfigurasi Realtime. Karena itu:

1. Pastikan project yang dipilih di Dashboard adalah project yang benar.
2. Buat backup database atau gunakan project/branch staging terlebih dahulu. Jangan bereksperimen di database produksi tanpa backup yang bisa dipulihkan.
3. Pastikan perubahan file lokal yang ingin diterapkan sudah ditinjau tim.
4. Jangan membuka blok SQL opsional di bagian paling atas yang berisi `DROP TABLE ... CASCADE` dan `DROP TYPE`; blok tersebut dapat menghapus data dan objek.
5. Pastikan tidak ada eksekusi schema yang sedang berlangsung. Jika query menghasilkan error, berhenti dan tinjau error serta kondisi database sebelum mencoba menjalankan ulang.

Schema berisi beberapa operasi `DROP ... IF EXISTS` untuk fungsi/constraint lama sebelum membuat versi yang sesuai. Meskipun banyak bagian dirancang agar dapat dijalankan ulang, jangan menganggap seluruh skrip sebagai migrasi tanpa risiko atau menjalankannya berulang kali tanpa meninjau perubahan.

## Cara menerapkan lewat Supabase Dashboard

1. Masuk ke [Supabase Dashboard](https://supabase.com/dashboard) dan pilih project yang benar.
2. Buka **SQL Editor** dari sidebar.
3. Pilih **New query**.
4. Buka file [`../supabase/schema.sql`](../supabase/schema.sql) dari salinan proyek yang sudah ditinjau, lalu salin seluruh isinya ke editor.
5. Pastikan blok penghapusan opsional di awal tetap berupa komentar (`--`), dan jangan mengubah blok admin pertama yang juga sengaja dikomentari.
6. Klik **Run** dan tunggu query selesai.
7. Jika ada error, jangan lanjut ke pengujian aplikasi dengan anggapan semua perubahan sudah diterapkan. Catat bagian pesan error yang relevan tanpa menyalin kredensial, lalu minta admin database meninjau kondisi project.
8. Bila eksekusi berhasil, jalankan pemeriksaan di bagian berikut dan lakukan smoke test lewat aplikasi.

Menjalankan schema lengkap juga menerapkan fungsi dan aturan lain yang dipakai alur aplikasi. Jangan hanya menyalin bagian `CREATE POLICY`, karena policy bergantung pada helper function, grants, dan objek schema lain.

## Pemeriksaan RLS di SQL Editor

Jalankan query baca-saja berikut untuk memastikan RLS aktif pada tiga tabel aplikasi:

```sql
SELECT
  relation.relname AS table_name,
  relation.relrowsecurity AS rls_enabled,
  relation.relforcerowsecurity AS rls_forced
FROM pg_class AS relation
JOIN pg_namespace AS schema_name ON schema_name.oid = relation.relnamespace
WHERE schema_name.nspname = 'public'
  AND relation.relname IN ('profiles', 'rooms', 'reservation_requests')
ORDER BY relation.relname;
```

Nilai `rls_enabled` harus `true` untuk `profiles`, `rooms`, dan `reservation_requests`. `rls_forced` boleh `false`: aplikasi menggunakan role database `authenticated` yang tetap tunduk pada RLS; role pemilik database seperti SQL Editor dapat memiliki perilaku bypass.

Periksa juga policy yang terpasang:

```sql
SELECT
  tablename,
  policyname,
  cmd,
  roles,
  qual,
  with_check
FROM pg_policies
WHERE schemaname = 'public'
  AND tablename IN ('profiles', 'rooms', 'reservation_requests')
ORDER BY tablename, policyname;
```

Nama policy utama yang diharapkan:

- `profiles`: `Profiles are visible to owner and admin`, `Users can update their own profile`
- `rooms`: `Authenticated users can read active rooms`, `Admin can insert rooms`, `Admin can update rooms`, `Admin can delete rooms`
- `reservation_requests`: `Requesters and admin can read requests`, `Requesters can submit their own bookings`

Untuk permintaan peminjaman, tidak adanya policy `UPDATE`/`DELETE` langsung memang disengaja. Pembatalan dan keputusan admin harus lewat RPC yang memeriksa pengguna dan role.

**Penting:** query di SQL Editor berjalan dengan hak database tinggi, sehingga bukan cara untuk membuktikan pembatasan requester/admin. Jangan menyimpulkan RLS berfungsi hanya karena query SQL Editor berhasil.

## Jika Supabase Advisor menandai `room_availability`

View `public.room_availability` harus menggunakan `security_invoker = TRUE`, bukan `FALSE`. Versi schema saat ini membatasi view tersebut untuk admin aktif dan membuat hak akses/RLS tabel sumber mengikuti pemanggil. Requester tetap memeriksa ketersediaan jadwal lewat RPC `get_room_availability_for`.

Jika database sudah menjalankan versi schema lama yang memiliki `security_invoker = FALSE`, jalankan migrasi kecil berikut melalui **SQL Editor** setelah memastikan project yang dipilih benar:

```sql
ALTER VIEW public.room_availability
  SET (security_invoker = TRUE);

CREATE OR REPLACE VIEW public.room_availability AS
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
```

Jika query gagal, jangan menganggap peringatan sudah diperbaiki; tinjau pesan error dan definisi view yang aktif. Setelah berhasil, periksa propertinya:

```sql
SELECT
  relation.relname AS view_name,
  relation.reloptions
FROM pg_class AS relation
JOIN pg_namespace AS schema_name ON schema_name.oid = relation.relnamespace
WHERE schema_name.nspname = 'public'
  AND relation.relname = 'room_availability'
  AND relation.relkind = 'v';
```

Pastikan `reloptions` berisi `security_invoker=true`. Jalankan ulang Supabase Security Advisor setelah itu. View hanya boleh mengembalikan baris untuk admin aktif; requester memakai fungsi `get_room_availability_for` untuk pengecekan waktu.

## Smoke test melalui aplikasi

Gunakan akun uji requester dan admin pada project staging, atau lakukan pengujian terkontrol pada project yang disetujui tim. Hindari menggunakan akun/data mahasiswa nyata untuk test.

### Dengan akun requester

- Login dan lihat hanya profil serta permintaan milik akun tersebut.
- Lihat ruangan aktif dan ketersediaan jadwal.
- Ajukan peminjaman yang valid; status awal harus **Menunggu**.
- Coba batalkan permintaan sendiri yang belum dimulai.
- Pastikan UI tidak menyediakan keputusan admin atau pengelolaan akun/ruangan.

### Dengan akun admin

- Lihat permintaan yang perlu diproses dan data yang diperlukan untuk mengelolanya.
- Terima satu permintaan yang valid; status menjadi **Diterima**.
- Tolak permintaan uji lain dengan alasan; status menjadi **Ditolak** dan alasan tercatat.
- Pastikan bentrok jadwal tidak bisa diterima.
- Buka ekspor laporan dan periksa ZIP berisi PDF dan Excel untuk rentang yang menyertakan data uji.

Jika tersedia, gunakan pula akun nonaktif/suspend untuk memastikan akses tidak diberikan. Jangan mencoba menguji atau mengubah kredensial pengguna lain.

## Ringkasan aturan akses pada schema

- **Profil:** pengguna aktif dapat membaca profil sendiri; admin aktif dapat membaca profil. Pengguna hanya dapat mengubah nama lengkap dan unit miliknya melalui grant kolom yang dibatasi.
- **Ruangan:** pengguna aktif dapat membaca ruangan aktif; admin dapat mengelola ruangan.
- **Permintaan:** requester aktif dapat membaca permintaan sendiri dan membuat permintaan sendiri yang berstatus awal `pending`; admin dapat membaca permintaan untuk pengelolaan.
- **Perubahan status permintaan:** tidak tersedia sebagai update langsung dari client. Fungsi RPC memeriksa hak requester/admin dan menerapkan aturan pembatalan, keputusan, serta alasan yang diperlukan.
- **Laporan:** RPC laporan memeriksa bahwa pemanggil adalah admin aktif.
- **Akun nonaktif/diarsipkan:** helper akses membatasi operasi aplikasi berdasarkan `is_active` dan `deleted_at`.

RLS adalah lapisan kontrol database; UI yang menyembunyikan tombol bukan pengganti policy. Edge Function untuk manajemen akun juga harus tetap memverifikasi sesi dan role admin.

## Petunjuk untuk tim saat menggunakan AI

File [`AGENT_REFERENCE.md`](./AGENT_REFERENCE.md) berisi konteks domain aplikasi, dan [`mcp.json`](./mcp.json) adalah contoh MCP Supabase terbatas project serta baca-saja.

- Gunakan MCP baca-saja untuk memahami schema atau meminta agent meninjau policy. Jangan memberikan password, service-role key, access token, secret SMTP, atau data pribadi mahasiswa kepada agent.
- Berikan agent hanya struktur/kolom yang relevan. Hindari menyalin data produksi atau hasil query berisi NIM, email, tujuan peminjaman, maupun informasi pribadi.
- Minta agent menjelaskan dampak perubahan policy untuk requester, admin, dan pengguna nonaktif, serta menyiapkan patch/query dan langkah verifikasi untuk ditinjau.
- MCP baca-saja tidak untuk menerapkan schema, menjalankan migrasi, mengubah data, atau men-deploy function. Jangan meminta agent melewati batas ini dengan memakai key atau koneksi lain.
- Perubahan database harus ditinjau manusia, diuji pada project/branch staging dengan backup, lalu dijalankan oleh anggota tim yang berwenang melalui prosedur tim.
- Setelah perubahan, bandingkan policy di Supabase dengan schema lokal dan uji kedua role melalui aplikasi. Catat apa yang sudah dan belum diverifikasi.

Rujukan resmi: [Supabase Row Level Security](https://supabase.com/docs/guides/database/postgres/row-level-security) dan [Supabase MCP security risks](https://supabase.com/docs/guides/ai-tools/mcp#security-risks).
