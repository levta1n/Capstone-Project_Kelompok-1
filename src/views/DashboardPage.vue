<script>
import { defineComponent } from 'vue'
import DashboardHeader from '../components/dashboard/DashboardHeader.vue'
import DashboardSidebar from '../components/dashboard/DashboardSidebar.vue'
import RequestModal from '../components/dashboard/RequestModal.vue'
import RequestTable from '../components/dashboard/RequestTable.vue'
import RoomAvailability from '../components/dashboard/RoomAvailability.vue'
import StatCards from '../components/dashboard/StatCards.vue'

export default defineComponent({
  name: 'DashboardPage',
  components: {
    DashboardHeader,
    DashboardSidebar,
    RequestModal,
    RequestTable,
    RoomAvailability,
    StatCards,
  },
  data() {
    return {
      activeNav: 'beranda',
      searchTerm: '',
      requests: [
        { id: 'REQ-2401', requester: 'Siti Rahma', unit: 'Fakultas Teknik', room: 'Ruang 104', type: 'Peminjaman', status: 'Diterima', date: '18 Jun 2026', owner: 'Dr. Budi Santoso' },
        { id: 'REQ-2402', requester: 'Dewi Lestari', unit: 'Fakultas Kesehatan', room: 'Ruang 212', type: 'Peminjaman', status: 'Menunggu', date: '17 Jun 2026', owner: 'Dr. Rani Putri' },
        { id: 'REQ-2403', requester: 'Rizky Alam', unit: 'Fakultas Ekonomi', room: 'Ruang 305', type: 'Pendaftaran', status: 'Proses', date: '16 Jun 2026', owner: 'Dr. Nia Wulan' },
        { id: 'REQ-2404', requester: 'Anisa Putri', unit: 'Fakultas Informatika', room: 'Ruang 118', type: 'Peminjaman', status: 'Ditolak', date: '15 Jun 2026', owner: 'Dr. Arif Kurniawan' },
      ],
      rooms: [
        { name: 'Ruang 104', floor: 'Lantai 1', capacity: 24, available: 18, status: 'Tersedia' },
        { name: 'Ruang 212', floor: 'Lantai 2', capacity: 16, available: 9, status: 'Tersedia' },
        { name: 'Ruang 305', floor: 'Lantai 3', capacity: 30, available: 0, status: 'Penuh' },
      ],
      selectedRequest: null,
    }
  },
  computed: {
    filteredRequests() {
      const term = this.searchTerm.toLowerCase()
      if (!term) return this.requests
      return this.requests.filter((request) =>
        [request.id, request.requester, request.unit, request.room, request.status]
          .join(' ')
          .toLowerCase()
          .includes(term),
      )
    },
    stats() {
      return [
        { icon: '▦', type: 'bg-primary-subtle text-primary', label: 'Total ruangan', value: '48', description: 'Seluruh unit kampus' },
        { icon: '✓', type: 'bg-success-subtle text-success', label: 'Permintaan diterima', value: '126', description: 'Aktif dalam sistem' },
        { icon: '◷', type: 'bg-warning-subtle text-warning', label: 'Menunggu persetujuan', value: '8', description: 'Perlu tindakan hari ini' },
      ]
    },
  },
  methods: {
    showRequest(request) {
      this.selectedRequest = request
    },
    closeRequest() {
      this.selectedRequest = null
    },
    navigate(section) {
      this.activeNav = section
      document.getElementById(section)?.scrollIntoView({ behavior: 'smooth' })
    },
  },
})
</script>

<template>
  <div class="container-fluid">
    <div class="row g-0 align-items-start">
      <DashboardSidebar :active-nav="activeNav" @navigate="navigate" />

      <main class="col-12 col-lg-9 px-3 px-lg-4 py-4">
        <DashboardHeader v-model="searchTerm" />

        <div id="beranda" class="mt-4">
          <section class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
            <div>
              <p class="text-muted small fw-bold text-uppercase mb-1">Beranda / Overview</p>
              <h1 class="display-5 fw-black text-dark mb-1">Dashboard Manajemen</h1>
              <p class="text-muted small fw-semibold mb-0">MONITORING KINERJAAN PENGGUNAAN RUANGAN</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <button class="btn btn-outline-secondary rounded-2">↧ Ekspor laporan</button>
              <button class="btn btn-primary rounded-2">＋ Buat permintaan</button>
            </div>
          </section>

          <StatCards :items="stats" />
          <div class="mt-4"><RoomAvailability :rooms="rooms" /></div>
          <div class="mt-4"><RequestTable :requests="filteredRequests" @show-request="showRequest" /></div>
        </div>
      </main>
    </div>

    <RequestModal :request="selectedRequest" @close="closeRequest" />
  </div>
</template>
