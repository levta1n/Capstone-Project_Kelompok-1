import { supabase } from '../utils/supabase'
import { getRoomAvailabilityFor } from './roomService'

const statusLabels = {
  pending: 'Menunggu',
  under_review: 'Proses',
  accepted: 'Diterima',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
}

const requestTypeLabels = {
  room_booking: 'Peminjaman',
  registration: 'Pendaftaran',
}

function formatRequestDate(value) {
  return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value))
}

export function mapReservation(request) {
  return {
    databaseId: request.id,
    id: request.request_code,
    requester: request.requester?.full_name || 'Pemohon',
    unit: request.requester?.unit_name || 'Unit belum diisi',
    room: request.room?.name || 'Ruangan dihapus',
    type: requestTypeLabels[request.request_type] || request.request_type,
    status: statusLabels[request.status] || request.status,
    date: formatRequestDate(request.starts_at),
    startsAt: request.starts_at,
    endsAt: request.ends_at,
    owner: request.room?.manager?.full_name || 'Pengelola ruangan',
    purpose: request.purpose,
    participantCount: request.participant_count,
    rejectionReason: request.rejection_reason,
  }
}

export async function listReservations(requesterId = null) {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')

  let query = supabase
    .from('reservation_requests')
    .select(`
      id, request_code, request_type, purpose, starts_at, ends_at,
      participant_count, status, rejection_reason, created_at,
      requester:profiles!reservation_requests_requester_id_fkey(full_name, unit_name),
      room:rooms!reservation_requests_room_id_fkey(
        name,
        manager:profiles!rooms_manager_id_fkey(full_name)
      )
    `)
    .order('created_at', { ascending: false })

  if (requesterId) query = query.eq('requester_id', requesterId)

  const { data, error } = await query
  if (error) throw error
  return data.map(mapReservation)
}

export async function createReservation(form, requesterId) {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')

  const startsAt = new Date(form.startsAt)
  const endsAt = new Date(form.endsAt)
  if (!Number.isFinite(startsAt.getTime()) || !Number.isFinite(endsAt.getTime()) || endsAt <= startsAt) {
    throw new Error('Waktu selesai harus setelah waktu mulai.')
  }

  const availability = await getRoomAvailabilityFor(startsAt, endsAt)
  const selectedRoom = availability.find((room) => room.id === form.roomId)
  if (!selectedRoom?.isAvailable) {
    throw new Error('Ruangan bentrok atau sedang diproses pada jadwal tersebut. Pilih ruangan atau waktu lain.')
  }
  if (Number(form.participantCount) > selectedRoom.capacity) {
    throw new Error(`Jumlah peserta melebihi kapasitas ruangan (${selectedRoom.capacity}).`)
  }

  const { error } = await supabase.from('reservation_requests').insert({
    requester_id: requesterId,
    room_id: form.roomId,
    request_type: form.requestType,
    purpose: form.purpose.trim(),
    starts_at: startsAt.toISOString(),
    ends_at: endsAt.toISOString(),
    participant_count: Number(form.participantCount),
  })

  if (error) throw error
}

export async function reviewReservation(requestId, reviewerId, status, rejectionReason = null) {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')
  if (!['accepted', 'rejected'].includes(status)) throw new Error('Status review tidak valid.')
  if (status === 'rejected' && !rejectionReason?.trim()) {
    throw new Error('Alasan penolakan wajib diisi.')
  }

  const { data, error } = await supabase
    .from('reservation_requests')
    .update({
      status,
      reviewed_by: reviewerId,
      reviewed_at: new Date().toISOString(),
      rejection_reason: status === 'rejected' ? rejectionReason.trim() : null,
    })
    .eq('id', requestId)
    .in('status', ['pending', 'under_review'])
    .select('id')
    .maybeSingle()

  if (error) throw error
  if (!data) throw new Error('Permintaan ini sudah diproses atau tidak lagi tersedia.')
}

export function subscribeToReservations(requesterId, onChange) {
  if (!supabase) return () => {}

  const filter = requesterId ? { filter: `requester_id=eq.${requesterId}` } : {}
  const channel = supabase
    .channel(`reservation-changes-${requesterId || 'management'}`)
    .on('postgres_changes', {
      event: '*',
      schema: 'public',
      table: 'reservation_requests',
      ...filter,
    }, onChange)
    .subscribe()

  return () => supabase.removeChannel(channel)
}