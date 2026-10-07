import { supabase } from '../utils/supabase'

export async function listAvailableRooms() {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')

  const { data, error } = await supabase
    .from('room_availability')
    .select('room_id, name, floor_label, capacity, available_capacity, availability_status')
    .eq('is_active', true)
    .order('name')

  if (error) throw error

  return data.map((room) => ({
    id: room.room_id,
    name: room.name,
    floor: room.floor_label,
    capacity: room.capacity,
    available: room.available_capacity,
    status: room.availability_status,
  }))
}

export async function getRoomAvailabilityFor(startsAt, endsAt) {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')

  const start = new Date(startsAt)
  const end = new Date(endsAt)
  if (!Number.isFinite(start.getTime()) || !Number.isFinite(end.getTime()) || end <= start) {
    throw new Error('Pilih rentang waktu yang valid.')
  }

  const { data, error } = await supabase.rpc('get_room_availability_for', {
    p_starts_at: start.toISOString(),
    p_ends_at: end.toISOString(),
  })
  if (error) throw error

  return data.map((room) => ({
    id: room.room_id,
    name: room.name,
    building: room.building,
    floor: room.floor_label,
    capacity: room.capacity,
    available: room.is_available ? room.capacity : 0,
    isAvailable: room.is_available,
    status: room.availability_status,
    conflictCount: room.conflict_count,
  }))
}

export async function listManagedRooms() {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')

  const { data, error } = await supabase
    .from('rooms')
    .select('id, name, building, floor_label, capacity, is_active')
    .order('building')
    .order('name')

  if (error) throw error

  return data.map((room) => ({
    id: room.id,
    name: room.name,
    building: room.building,
    floor: room.floor_label,
    capacity: room.capacity,
    isActive: room.is_active,
  }))
}

export async function saveRoom(room) {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')

  const values = {
    name: room.name.trim(),
    building: room.building.trim(),
    floor_label: room.floor.trim(),
    capacity: Number(room.capacity),
  }
  const query = room.id
    ? supabase.from('rooms').update(values).eq('id', room.id)
    : supabase.from('rooms').insert(values)
  const { error } = await query
  if (error) throw error
}

export async function setRoomActive(roomId, isActive) {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')
  const { error } = await supabase.from('rooms').update({ is_active: isActive }).eq('id', roomId)
  if (error) throw error
}

export function subscribeToRoomChanges(onChange) {
  if (!supabase) return () => {}
  const channel = supabase
    .channel('room-availability-changes')
    .on('postgres_changes', { event: '*', schema: 'public', table: 'rooms' }, onChange)
    .subscribe()
  return () => supabase.removeChannel(channel)
}