-- Run this file in the Supabase SQL Editor.

do $$
begin
  create type public.app_role as enum ('requester', 'manager', 'admin');
exception
  when duplicate_object then null;
end;
$$;

create schema if not exists extensions;
create extension if not exists btree_gist with schema extensions;

create table if not exists public.profiles (
  id uuid primary key references auth.users (id) on delete cascade,
  full_name text not null,
  email text unique,
  unit_name text,
  role public.app_role not null default 'requester',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.rooms (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  building text not null default 'Gedung Utama',
  floor_label text not null,
  capacity integer not null check (capacity > 0),
  manager_id uuid references public.profiles (id) on delete set null,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (building, name)
);

create table if not exists public.reservation_requests (
  id uuid primary key default gen_random_uuid(),
  request_code text generated always as (
    'REQ-' || upper(substr(replace(id::text, '-', ''), 1, 8))
  ) stored unique,
  requester_id uuid not null references public.profiles (id) on delete restrict,
  room_id uuid not null references public.rooms (id) on delete restrict,
  request_type text not null check (request_type in ('room_booking', 'registration')),
  purpose text not null,
  starts_at timestamptz not null,
  ends_at timestamptz not null,
  participant_count integer not null default 1 check (participant_count > 0),
  status text not null default 'pending'
    check (status in ('pending', 'under_review', 'accepted', 'rejected', 'cancelled')),
  reviewed_by uuid references public.profiles (id) on delete set null,
  reviewed_at timestamptz,
  rejection_reason text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  check (ends_at > starts_at)
);

create index if not exists reservation_requests_requester_created_idx
  on public.reservation_requests (requester_id, created_at desc);
drop index if exists public.reservation_requests_room_schedule_idx;
drop index if exists public.reservation_requests_status_created_idx;
create index if not exists reservation_requests_created_idx
  on public.reservation_requests (created_at desc);

alter table public.reservation_requests
  drop constraint if exists reservation_requests_no_overlapping_active_requests;
alter table public.reservation_requests
  add constraint reservation_requests_no_overlapping_active_requests
  exclude using gist (
    room_id extensions.gist_uuid_ops with =,
    tstzrange(starts_at, ends_at, '[)') with &&
  ) where (status in ('pending', 'under_review', 'accepted'));

create or replace function public.set_updated_at()
returns trigger
language plpgsql
set search_path = ''
as $$
begin
  new.updated_at := now();
  return new;
end;
$$;

drop trigger if exists profiles_set_updated_at on public.profiles;
create trigger profiles_set_updated_at
  before update on public.profiles
  for each row execute function public.set_updated_at();

drop trigger if exists rooms_set_updated_at on public.rooms;
create trigger rooms_set_updated_at
  before update on public.rooms
  for each row execute function public.set_updated_at();

drop trigger if exists reservation_requests_set_updated_at on public.reservation_requests;
create trigger reservation_requests_set_updated_at
  before update on public.reservation_requests
  for each row execute function public.set_updated_at();

create or replace function public.validate_reservation_room_capacity()
returns trigger
language plpgsql
set search_path = ''
as $$
declare
  room_capacity integer;
  room_is_active boolean;
begin
  if new.status not in ('pending', 'under_review', 'accepted') then
    return new;
  end if;

  select room.capacity, room.is_active
    into room_capacity, room_is_active
  from public.rooms as room
  where room.id = new.room_id;

  if not found or not room_is_active then
    raise exception 'Ruangan tidak aktif atau tidak ditemukan.' using errcode = '23514';
  end if;

  if new.participant_count > room_capacity then
    raise exception 'Jumlah peserta melebihi kapasitas ruangan.' using errcode = '23514';
  end if;

  return new;
end;
$$;

drop trigger if exists reservation_requests_validate_room_capacity on public.reservation_requests;
create trigger reservation_requests_validate_room_capacity
  before insert or update of room_id, participant_count, status on public.reservation_requests
  for each row execute function public.validate_reservation_room_capacity();

create or replace function public.handle_new_auth_user()
returns trigger
language plpgsql
security definer
set search_path = ''
as $$
begin
  insert into public.profiles (id, full_name, email, unit_name)
  values (
    new.id,
    coalesce(
      nullif(trim(new.raw_user_meta_data ->> 'full_name'), ''),
      nullif(split_part(coalesce(new.email, new.phone, ''), '@', 1), ''),
      'Pengguna'
    ),
    new.email,
    nullif(trim(new.raw_user_meta_data ->> 'unit_name'), '')
  )
  on conflict (id) do update
    set email = excluded.email;

  return new;
end;
$$;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
  after insert on auth.users
  for each row execute function public.handle_new_auth_user();

create or replace function public.current_app_role()
returns public.app_role
language sql
stable
security definer
set search_path = ''
as $$
  select profile.role
  from public.profiles as profile
  where profile.id = (select auth.uid())
$$;

create or replace function public.is_manager_or_admin()
returns boolean
language sql
stable
security definer
set search_path = ''
as $$
  select coalesce(public.current_app_role() in ('manager', 'admin'), false)
$$;

grant execute on function public.current_app_role() to authenticated;
grant execute on function public.is_manager_or_admin() to authenticated;

alter table public.profiles enable row level security;
alter table public.rooms enable row level security;
alter table public.reservation_requests enable row level security;

drop policy if exists "Profiles are visible to owner and staff" on public.profiles;
create policy "Profiles are visible to owner and staff"
  on public.profiles for select to authenticated
  using (id = (select auth.uid()) or (select public.is_manager_or_admin()));

drop policy if exists "Users can update their own profile" on public.profiles;
create policy "Users can update their own profile"
  on public.profiles for update to authenticated
  using (id = (select auth.uid()) or (select public.is_manager_or_admin()))
  with check (
    (select public.is_manager_or_admin())
    or (id = (select auth.uid()) and role = (select public.current_app_role()))
  );

drop policy if exists "Authenticated users can read active rooms" on public.rooms;
create policy "Authenticated users can read active rooms"
  on public.rooms for select to authenticated
  using (is_active or (select public.is_manager_or_admin()));

drop policy if exists "Staff can insert rooms" on public.rooms;
create policy "Staff can insert rooms"
  on public.rooms for insert to authenticated
  with check ((select public.is_manager_or_admin()));

drop policy if exists "Staff can update rooms" on public.rooms;
create policy "Staff can update rooms"
  on public.rooms for update to authenticated
  using ((select public.is_manager_or_admin()))
  with check ((select public.is_manager_or_admin()));

drop policy if exists "Staff can delete rooms" on public.rooms;
create policy "Staff can delete rooms"
  on public.rooms for delete to authenticated
  using ((select public.is_manager_or_admin()));

drop policy if exists "Requesters and staff can read requests" on public.reservation_requests;
create policy "Requesters and staff can read requests"
  on public.reservation_requests for select to authenticated
  using (
    requester_id = (select auth.uid())
    or (select public.is_manager_or_admin())
  );

drop policy if exists "Users can submit their own requests" on public.reservation_requests;
create policy "Users can submit their own requests"
  on public.reservation_requests for insert to authenticated
  with check (
    requester_id = (select auth.uid())
    and status = 'pending'
    and reviewed_by is null
    and reviewed_at is null
    and rejection_reason is null
  );

drop policy if exists "Owners can update pending requests and staff can review" on public.reservation_requests;
create policy "Owners can update pending requests and staff can review"
  on public.reservation_requests for update to authenticated
  using (
    (requester_id = (select auth.uid()) and status = 'pending')
    or (select public.is_manager_or_admin())
  )
  with check (
    (requester_id = (select auth.uid()) and status in ('pending', 'cancelled'))
    or (select public.is_manager_or_admin())
  );

grant usage on schema public to authenticated;
grant select, update on public.profiles to authenticated;
grant select, insert, update, delete on public.rooms to authenticated;
grant select, insert, update on public.reservation_requests to authenticated;

-- This aggregate-only view intentionally bypasses request RLS so availability
-- includes bookings made by every requester without exposing request details.
create or replace view public.room_availability
with (security_invoker = false)
as
select
  room.id as room_id,
  room.name,
  room.building,
  room.floor_label,
  room.capacity,
  room.is_active,
  greatest(room.capacity - coalesce(sum(request.participant_count), 0), 0)::integer
    as available_capacity,
  case
    when not room.is_active then 'Tidak aktif'
    when greatest(room.capacity - coalesce(sum(request.participant_count), 0), 0) = 0 then 'Penuh'
    else 'Tersedia'
  end as availability_status
from public.rooms as room
left join public.reservation_requests as request
  on request.room_id = room.id
  and request.status = 'accepted'
  and request.starts_at <= now()
  and request.ends_at > now()
group by room.id;

grant select on public.room_availability to authenticated;

create or replace function public.get_room_availability_for(
  p_starts_at timestamptz,
  p_ends_at timestamptz
)
returns table (
  room_id uuid,
  name text,
  building text,
  floor_label text,
  capacity integer,
  conflict_count integer,
  availability_status text,
  is_available boolean
)
language plpgsql
stable
security definer
set search_path = ''
as $$
begin
  if p_starts_at is null or p_ends_at is null or p_ends_at <= p_starts_at then
    raise exception 'Rentang waktu tidak valid.' using errcode = '22023';
  end if;

  return query
  select
    room.id,
    room.name,
    room.building,
    room.floor_label,
    room.capacity,
    count(request.id)::integer,
    case when count(request.id) > 0 then 'Bentrok' else 'Tersedia' end,
    count(request.id) = 0
  from public.rooms as room
  left join public.reservation_requests as request
    on request.room_id = room.id
    and request.status in ('pending', 'under_review', 'accepted')
    and tstzrange(request.starts_at, request.ends_at, '[)')
      && tstzrange(p_starts_at, p_ends_at, '[)')
  where room.is_active
  group by room.id
  order by room.name;
end;
$$;

revoke all on function public.get_room_availability_for(timestamptz, timestamptz)
  from public, anon;
grant execute on function public.get_room_availability_for(timestamptz, timestamptz)
  to authenticated;

insert into public.rooms (name, building, floor_label, capacity)
values
  ('Ruang 104', 'Gedung Utama', 'Lantai 1', 24),
  ('Ruang 118', 'Gedung Utama', 'Lantai 1', 20),
  ('Ruang 212', 'Gedung Utama', 'Lantai 2', 16),
  ('Ruang 305', 'Gedung Utama', 'Lantai 3', 30)
on conflict (building, name) do nothing;

alter table public.reservation_requests replica identity default;
alter table public.rooms replica identity default;

do $$
begin
  if exists (select 1 from pg_publication where pubname = 'supabase_realtime') then
    if not exists (
      select 1 from pg_publication_tables
      where pubname = 'supabase_realtime'
        and schemaname = 'public'
        and tablename = 'reservation_requests'
    ) then
      execute 'alter publication supabase_realtime add table public.reservation_requests';
    end if;

    if not exists (
      select 1 from pg_publication_tables
      where pubname = 'supabase_realtime'
        and schemaname = 'public'
        and tablename = 'rooms'
    ) then
      execute 'alter publication supabase_realtime add table public.rooms';
    end if;
  end if;
end;
$$;

notify pgrst, 'reload schema';