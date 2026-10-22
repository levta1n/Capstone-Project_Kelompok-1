import JSZip from 'jszip'
import { jsPDF } from 'jspdf'
import * as XLSX from 'xlsx'
import { supabase } from '../utils/supabase'

const statusLabels = {
  pending: 'Menunggu keputusan',
  accepted: 'Disetujui',
  rejected: 'Tidak disetujui',
  cancelled: 'Dibatalkan',
}

function toReportTimestamp(value, inclusiveEnd = false) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    throw new Error('Tanggal laporan tidak valid.')
  }

  const date = new Date(`${value}T00:00:00`)
  const parsedValue = [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
  ].join('-')
  if (!Number.isFinite(date.getTime()) || parsedValue !== value) {
    throw new Error('Tanggal laporan tidak valid.')
  }
  if (inclusiveEnd) date.setDate(date.getDate() + 1)
  return date.toISOString()
}

function formatDate(value) {
  return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value))
}

function formatDateTime(value) {
  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function formatDurationHours(value) {
  const totalMinutes = Math.round(Number(value || 0) * 60)
  const hours = Math.floor(totalMinutes / 60)
  const minutes = totalMinutes % 60
  const parts = []
  if (hours) parts.push(`${hours} jam`)
  if (minutes) parts.push(`${minutes} menit`)
  return parts.join(' ') || '0 menit'
}

function createWorkbook(detailRows, roomRows, from, to) {
  const workbook = XLSX.utils.book_new()
  workbook.Props = {
    Title: 'Laporan Peminjaman Ruangan SIMPERU',
    Subject: `Periode ${from} sampai ${to}`,
    Author: 'SIMPERU',
  }

  const detailData = [
    [
      'Nomor Permintaan',
      'Nama Pemohon',
      'NIM',
      'Unit',
      'Ruangan',
      'Gedung',
      'Lantai',
      'Tujuan',
      'Waktu Mulai',
      'Waktu Selesai',
      'Lama Penggunaan (jam)',
      'Jumlah Peserta',
      'Status',
      'Diproses Admin',
      'Tanggal Keputusan Admin',
      'Alasan Penolakan',
      'Pembatalan oleh',
      'Tanggal Pembatalan',
      'Alasan Pembatalan',
      'Dibuat Pada',
    ],
    ...detailRows.map((row) => [
      row.request_code,
      row.requester_name,
      row.requester_nim || '',
      row.requester_unit || '',
      row.room_name,
      row.building,
      row.floor_label,
      row.purpose,
      formatDateTime(row.starts_at),
      formatDateTime(row.ends_at),
      Number(row.duration_hours),
      Number(row.participant_count),
      statusLabels[row.status] || row.status,
      row.reviewed_by_name || '',
      row.reviewed_at ? formatDateTime(row.reviewed_at) : '',
      row.rejection_reason || '',
      row.cancelled_by_name || '',
      row.cancelled_at ? formatDateTime(row.cancelled_at) : '',
      row.cancellation_reason || '',
      formatDateTime(row.created_at),
    ]),
  ]
  const detailsSheet = XLSX.utils.aoa_to_sheet(detailData)
  detailsSheet['!cols'] = [20, 26, 16, 22, 20, 22, 16, 38, 24, 24, 16, 18, 16, 24, 24, 36, 24, 24, 36, 24]
  XLSX.utils.book_append_sheet(workbook, detailsSheet, 'Detail Peminjaman')

  const roomData = [
    [
      'Ruangan',
      'Gedung',
      'Lantai',
      'Jumlah Permintaan',
      'Jumlah Disetujui',
      'Menunggu Keputusan',
      'Tidak Disetujui',
      'Jumlah Dibatalkan',
      'Dibatalkan oleh Admin',
      'Lama Penggunaan Disetujui (jam)',
      'Peserta pada Peminjaman Disetujui',
    ],
    ...roomRows.map((row) => [
      row.room_name,
      row.building,
      row.floor_label,
      Number(row.total_bookings),
      Number(row.accepted_count),
      Number(row.pending_count),
      Number(row.rejected_count),
      Number(row.cancelled_count),
      Number(row.cancelled_by_admin_count),
      Number(row.booked_hours),
      Number(row.total_participants),
    ]),
  ]
  const roomSheet = XLSX.utils.aoa_to_sheet(roomData)
  roomSheet['!cols'] = [22, 24, 16, 20, 14, 14, 14, 16, 20, 26, 24]
  XLSX.utils.book_append_sheet(workbook, roomSheet, 'Statistik Ruangan')

  const guideData = [
    ['Panduan Membaca Laporan'],
    ['Periode laporan', 'Permintaan dihitung berdasarkan tanggal mulai peminjaman.'],
    ['Menunggu keputusan', 'Permintaan yang belum diputuskan oleh admin.'],
    ['Disetujui', 'Permintaan yang sudah diterima admin.'],
    ['Tidak disetujui', 'Permintaan yang ditolak admin.'],
    ['Lama Penggunaan (jam)', 'Durasi peminjaman yang disetujui. Nilai 1,5 berarti 1 jam 30 menit.'],
    ['Peserta', 'Jumlah peserta pada peminjaman yang disetujui.'],
  ]
  const guideSheet = XLSX.utils.aoa_to_sheet(guideData)
  guideSheet['!cols'] = [30, 90]
  XLSX.utils.book_append_sheet(workbook, guideSheet, 'Panduan')
  return XLSX.write(workbook, { type: 'array', bookType: 'xlsx' })
}

function createPdf(detailRows, roomRows, from, to, generatedAt) {
  const document = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' })
  const pageWidth = document.internal.pageSize.getWidth()
  const pageHeight = document.internal.pageSize.getHeight()
  const margin = 15
  const lineHeight = 6
  let y = 20

  const ensureSpace = (height) => {
    if (y + height > pageHeight - margin) {
      document.addPage()
      y = 20
    }
  }
  const addText = (text, options = {}) => {
    const lines = document.splitTextToSize(String(text), pageWidth - margin * 2)
    ensureSpace(lines.length * lineHeight + 2)
    document.setFont('helvetica', options.bold ? 'bold' : 'normal')
    document.setFontSize(options.size || 10)
    document.text(lines, margin, y)
    y += lines.length * lineHeight + (options.gap ?? 2)
  }
  const addSection = (title) => {
    ensureSpace(12)
    y += 2
    document.setFont('helvetica', 'bold')
    document.setFontSize(13)
    document.text(title, margin, y)
    y += 8
  }

  const statusCounts = detailRows.reduce((counts, row) => {
    counts[row.status] = (counts[row.status] || 0) + 1
    return counts
  }, {})
  const acceptedRows = detailRows.filter((row) => row.status === 'accepted')
  const acceptedHours = acceptedRows.reduce((total, row) => total + Number(row.duration_hours || 0), 0)
  const acceptedParticipants = acceptedRows.reduce((total, row) => total + Number(row.participant_count || 0), 0)

  document.setFont('helvetica', 'bold')
  document.setFontSize(19)
  document.text('Laporan Peminjaman Ruangan SIMPERU', margin, y)
  y += 10
  addText(`Periode laporan: ${formatDate(from)} - ${formatDate(to)}`, { size: 11, bold: true })
  addText(`Dibuat pada: ${formatDateTime(generatedAt)}`)
  addText('Laporan ini memuat peminjaman yang tanggal mulainya berada dalam periode terpilih.')
  addText('Lama penggunaan dihitung dari permintaan yang disetujui. Contoh: 1 jam 30 menit berarti ruangan digunakan selama satu setengah jam.')

  addSection('Ringkasan Peminjaman')
  const summary = [
    `Jumlah permintaan peminjaman: ${detailRows.length}`,
    `Sudah disetujui admin: ${statusCounts.accepted || 0}`,
    `Menunggu keputusan admin: ${statusCounts.pending || 0}`,
    `Tidak disetujui admin: ${statusCounts.rejected || 0}`,
    `Dibatalkan: ${statusCounts.cancelled || 0}`,
    `Lama penggunaan dari peminjaman yang disetujui: ${formatDurationHours(acceptedHours)}`,
    `Jumlah peserta pada peminjaman yang disetujui: ${acceptedParticipants} orang`,
  ]
  summary.forEach((item) => addText(`- ${item}`))

  addSection('Statistik Penggunaan Ruangan')
  if (roomRows.length === 0) {
    addText('Tidak ada data statistik ruangan untuk periode ini.')
  } else {
    const columnX = [margin, 61, 104, 132, 160, 190, 218, 250]
    const headers = ['Ruangan', 'Gedung', 'Jumlah', 'Disetujui', 'Menunggu', 'Tidak setuju', 'Dibatalkan', 'Lama (jam)']
    const drawRoomHeader = () => {
      ensureSpace(10)
      document.setFont('helvetica', 'bold')
      document.setFontSize(8)
      headers.forEach((header, index) => document.text(header, columnX[index], y))
      y += 4
      document.setLineWidth(0.3)
      document.line(margin, y, pageWidth - margin, y)
      y += 5
    }
    drawRoomHeader()
    roomRows.forEach((row) => {
      if (y + 8 > pageHeight - margin) {
        document.addPage()
        y = 20
        drawRoomHeader()
      }
      document.setFont('helvetica', 'normal')
      document.setFontSize(8)
      const values = [
        row.room_name,
        row.building,
        row.total_bookings,
        row.accepted_count,
        row.pending_count,
        row.rejected_count,
        row.cancelled_count,
        formatDurationHours(row.booked_hours),
      ]
      values.forEach((value, index) => {
        document.text(document.splitTextToSize(String(value ?? ''), index < 2 ? 44 : 27)[0], columnX[index], y)
      })
      y += 7
    })
  }

  const pageCount = document.getNumberOfPages()
  for (let page = 1; page <= pageCount; page += 1) {
    document.setPage(page)
    document.setFont('helvetica', 'normal')
    document.setFontSize(8)
    document.text(
      `SIMPERU - Laporan peminjaman | Halaman ${page}/${pageCount}`,
      pageWidth - margin,
      pageHeight - 7,
      { align: 'right' },
    )
  }
  return document.output('arraybuffer')
}

function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  document.body.append(anchor)
  anchor.click()
  anchor.remove()
  window.setTimeout(() => URL.revokeObjectURL(url), 1000)
}

export async function exportReservationReport({ from, to }) {
  if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')
  if (from > to) throw new Error('Tanggal awal harus sama dengan atau sebelum tanggal akhir.')

  const pFrom = toReportTimestamp(from)
  const pTo = toReportTimestamp(to, true)
  const [detailResult, roomResult] = await Promise.all([
    supabase.rpc('admin_booking_report', { p_from: pFrom, p_to: pTo }),
    supabase.rpc('admin_room_usage_report', { p_from: pFrom, p_to: pTo }),
  ])

  if (detailResult.error) throw new Error(`Gagal memuat detail laporan: ${detailResult.error.message}`)
  if (roomResult.error) throw new Error(`Gagal memuat statistik ruangan: ${roomResult.error.message}`)

  const detailRows = detailResult.data || []
  const roomRows = roomResult.data || []
  const generatedAt = new Date()
  const pdf = createPdf(detailRows, roomRows, from, to, generatedAt)
  const workbook = createWorkbook(detailRows, roomRows, from, to)
  const zip = new JSZip()
  zip.file('Laporan-Peminjaman.pdf', pdf)
  zip.file('Statistik-Peminjaman.xlsx', workbook)

  const archive = await zip.generateAsync({ type: 'blob', compression: 'DEFLATE' })
  downloadBlob(archive, `Laporan-SIMPERU-${from}-sampai-${to}.zip`)
  return { detailCount: detailRows.length, roomCount: roomRows.length }
}
