import { supabase } from '../utils/supabase'
import { APPLICATION_TIME_ZONE, parseAppDateTime } from '../utils/dateTime'
import { getRoomAvailabilityFor } from './roomService'

const statusLabels = {
  pending: 'Menunggu',
  accepted: 'Diterima',
  rejected: 'Ditolak',
  cancelled: 'Dibatalkan',
}

function formatRequestDate(value) {
  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeZone: APPLICATION_TIME_ZONE,
  }).format(new Date(value))
}

export function mapReservation(request) {
  return {
    databaseId: request.id,
    id: request.request_code,
    roomId: request.room_id,
    createdAt: request.created_at,
    requester: request.requester?.full_name || 'Pemohon',
    unit: request.requester?.unit_name || '',
    room: request.room?.name || 'Ruangan dihapus',
    type: 'Peminjaman',
    status: statusLabels[request.status] || request.status,
    date: formatRequestDate(request.starts_at),
    startsAt: request.starts_at,
    endsAt: request.ends_at,
    purpose: request.purpose,
    participantCount: request.participant_count,
    cancellationReason: request.cancellation_reason,
    rejectionReason: request.rejection_reason,
    reviewedAt: request.reviewed_at,
  }
}

export async function listReservations(requesterId = null) {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')

  const { error: expiryError } = await supabase.rpc('expire_stale_reservation_requests')
  if (expiryError) throw expiryError

  let query = supabase
    .from('reservation_requests')
    .select(`
      id, request_code, room_id, purpose, starts_at, ends_at, participant_count,
      status, cancellation_reason, rejection_reason, reviewed_at, created_at,
      requester:profiles!reservation_requests_requester_id_fkey(full_name, unit_name),
      room:rooms!reservation_requests_room_id_fkey(name)
    `)
    .order('created_at', { ascending: false })

  if (requesterId) query = query.eq('requester_id', requesterId)

  const { data, error } = await query
  if (error) throw error
  return data
    .sort((left, right) => {
      if (left.status === 'pending' && right.status !== 'pending') return -1
      if (left.status !== 'pending' && right.status === 'pending') return 1
      if (left.status === 'pending') {
        return new Date(left.created_at) - new Date(right.created_at)
          || (left.id < right.id ? -1 : left.id > right.id ? 1 : 0)
      }
      return new Date(right.created_at) - new Date(left.created_at)
    })
    .map(mapReservation)
}

export async function createReservation(form, requesterId) {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')

  const startsAt = parseAppDateTime(form.startsAt)
  const endsAt = parseAppDateTime(form.endsAt)
  if (!Number.isFinite(startsAt.getTime()) || !Number.isFinite(endsAt.getTime()) || endsAt <= startsAt) {
    throw new Error('Waktu selesai harus setelah waktu mulai.')
  }
  if (startsAt <= new Date()) {
    throw new Error('Waktu mulai harus di masa depan.')
  }

  const availability = await getRoomAvailabilityFor(startsAt, endsAt)
  const selectedRoom = availability.find((room) => room.id === form.roomId)
  if (!selectedRoom?.isAvailable) {
    throw new Error('Ruangan bentrok pada jadwal tersebut. Pilih ruangan atau waktu lain.')
  }
  if (Number(form.participantCount) > selectedRoom.capacity) {
    throw new Error(`Jumlah peserta melebihi kapasitas ruangan (${selectedRoom.capacity}).`)
  }

  const { error } = await supabase.from('reservation_requests').insert({
    requester_id: requesterId,
    room_id: form.roomId,
    purpose: form.purpose.trim(),
    starts_at: startsAt.toISOString(),
    ends_at: endsAt.toISOString(),
    participant_count: Number(form.participantCount),
  })

  if (error) throw error
}

export async function cancelReservation(requestId) {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')

  const { data, error } = await supabase.rpc('cancel_reservation', {
    p_request_id: requestId,
  })

  if (error) throw error
  if (!data) throw new Error('Permintaan hanya bisa dibatalkan sebelum waktu mulai.')
}

export async function reviewReservation(requestId, status, rejectionReason = null) {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')
  if (!['accepted', 'rejected'].includes(status)) throw new Error('Keputusan permintaan tidak valid.')
  if (status === 'rejected' && !rejectionReason?.trim()) {
    throw new Error('Alasan penolakan wajib diisi.')
  }

  const { data, error } = await supabase.rpc('admin_review_reservation', {
    p_request_id: requestId,
    p_status: status,
    p_rejection_reason: status === 'rejected' ? rejectionReason.trim() : null,
  })

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