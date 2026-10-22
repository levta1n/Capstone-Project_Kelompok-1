import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { insertReservation, getRoomAvailabilityFor, reservationRows, reservationQuery } = vi.hoisted(() => {
  const query = {
    select: vi.fn(),
    order: vi.fn(),
    eq: vi.fn(),
    then(resolve, reject) {
      return Promise.resolve({ data: reservationRows.value, error: null }).then(resolve, reject)
    },
  }
  query.select.mockReturnValue(query)
  query.order.mockReturnValue(query)
  query.eq.mockReturnValue(query)

  return {
    insertReservation: vi.fn(),
    getRoomAvailabilityFor: vi.fn(),
    reservationRows: { value: [] },
    reservationQuery: query,
  }
})

vi.mock('../utils/supabase', () => ({
  supabase: {
    from: vi.fn(() => ({
      insert: insertReservation,
      select: reservationQuery.select,
    })),
  },
}))

vi.mock('./roomService', () => ({
  getRoomAvailabilityFor,
}))

import { createReservation, listReservations } from './reservationService'

describe('createReservation', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-23T05:15:00.000Z'))
    insertReservation.mockReset()
    insertReservation.mockResolvedValue({ error: null })
    getRoomAvailabilityFor.mockReset()
    reservationRows.value = []
    getRoomAvailabilityFor.mockResolvedValue([
      { id: 'room-1', isAvailable: true, capacity: 10 },
    ])
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it.each([
    ['waktu yang sudah lewat', '2026-10-23T10:00', '2026-10-23T14:00'],
    ['waktu saat ini', '2026-10-23T12:15', '2026-10-23T14:00'],
    ['tanggal yang sudah lewat dengan jam yang lebih besar', '2026-10-22T23:59', '2026-10-23T00:30'],
    ['jadwal pukul 01.00–10.00 ketika sekarang pukul 12.15 WIB', '2026-10-23T01:00', '2026-10-23T10:00'],
  ])('menolak permintaan dengan waktu mulai %s sebelum cek jadwal', async (_description, startsAt, endsAt) => {
    await expect(createReservation({
      roomId: 'room-1',
      startsAt,
      endsAt,
      purpose: 'Kegiatan',
      participantCount: 5,
    }, 'requester-1')).rejects.toThrow('Waktu mulai harus di masa depan.')

    expect(getRoomAvailabilityFor).not.toHaveBeenCalled()
    expect(insertReservation).not.toHaveBeenCalled()
  })

  it('mengizinkan permintaan dengan waktu mulai mendatang', async () => {
    await createReservation({
      roomId: 'room-1',
      startsAt: '2026-10-23T13:00',
      endsAt: '2026-10-23T14:00',
      purpose: 'Kegiatan',
      participantCount: 5,
    }, 'requester-1')

    expect(getRoomAvailabilityFor).toHaveBeenCalledOnce()
    expect(insertReservation).toHaveBeenCalledOnce()
    expect(insertReservation.mock.calls[0][0].starts_at).toBe('2026-10-23T06:00:00.000Z')
  })

  it('menampilkan antrean menunggu paling awal di urutan teratas', async () => {
    reservationRows.value = [
      { id: 'accepted-id', request_code: 'REQ-ACCEPT', status: 'accepted', created_at: '2026-10-23T04:00:00Z', starts_at: '2026-10-24T01:00:00Z' },
      { id: 'later-id', request_code: 'REQ-LATER', status: 'pending', created_at: '2026-10-23T05:00:00Z', starts_at: '2026-10-24T01:00:00Z' },
      { id: 'first-id', request_code: 'REQ-FIRST', status: 'pending', created_at: '2026-10-23T04:30:00Z', starts_at: '2026-10-24T01:00:00Z' },
    ]

    const requests = await listReservations()

    expect(requests.map(({ id }) => id)).toEqual(['REQ-FIRST', 'REQ-LATER', 'REQ-ACCEPT'])
  })
})
