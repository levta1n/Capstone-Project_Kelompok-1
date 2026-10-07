<script>
import { defineComponent } from 'vue'
import { Download, Plus } from '@lucide/vue'
import { supabase } from '../utils/supabase'
import { createReservation, listReservations, reviewReservation, subscribeToReservations } from '../services/reservationService'
import { getRoomAvailabilityFor, listAvailableRooms, listManagedRooms, saveRoom, setRoomActive, subscribeToRoomChanges } from '../services/roomService'
import { getProfile, signOut } from '../services/profileService'
import DashboardHeader from '../components/dashboard/DashboardHeader.vue'
import DashboardSidebar from '../components/dashboard/DashboardSidebar.vue'
import RequestModal from '../components/dashboard/RequestModal.vue'
import RequestTable from '../components/dashboard/RequestTable.vue'
import RoomManagerModal from '../components/dashboard/RoomManagerModal.vue'
import RoomScheduleAvailability from '../components/requester/RoomScheduleAvailability.vue'
import StatCards from '../components/dashboard/StatCards.vue'

function getDefaultScheduleRange() {
  const startsAt = new Date()
  startsAt.setHours(startsAt.getHours() + 1, 0, 0, 0)
  const endsAt = new Date(startsAt.getTime() + 60 * 60 * 1000)
  const toLocalInput = (date) => new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 16)
  return { startsAt: toLocalInput(startsAt), endsAt: toLocalInput(endsAt) }
}

export default defineComponent({
  name: 'DashboardPage',
  components: {
    Download,
    Plus,
    DashboardHeader,
    DashboardSidebar,
    RequestModal,
    RequestTable,
    RoomManagerModal,
    RoomScheduleAvailability,
    StatCards,
  },
  data() {
    const schedule = getDefaultScheduleRange()
    return {
      activeNav: 'beranda',
      mobileSidebarOpen: false,
      searchTerm: '',
      profile: null,
      requests: [],
      rooms: [],
      scheduleRooms: [],
      scheduleStart: schedule.startsAt,
      scheduleEnd: schedule.endsAt,
      isCheckingSchedule: false,
      scheduleError: '',
      managedRooms: [],
      selectedRequest: null,
      reviewingRequestId: '',
      reviewError: '',
      isCreateRequestOpen: false,
      isSubmittingRequest: false,
      requestError: '',
      loadError: '',
      isLoading: true,
      isRoomManagerOpen: false,
      isSavingRoom: false,
      roomManagerError: '',
      stopRequestSubscription: null,
      stopRoomSubscription: null,
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
      const accepted = this.requests.filter((request) => request.status === 'Diterima').length
      const pending = this.requests.filter((request) => ['Menunggu', 'Proses'].includes(request.status)).length
      return [
        { icon: 'rooms', type: 'bg-primary-subtle text-primary', label: 'Total ruangan', value: this.rooms.length, description: 'Ruangan aktif' },
        { icon: 'accepted', type: 'bg-success-subtle text-success', label: 'Permintaan diterima', value: accepted, description: 'Sesuai data akun' },
        { icon: 'pending', type: 'bg-warning-subtle text-warning', label: 'Menunggu persetujuan', value: pending, description: 'Perlu ditindaklanjuti' },
      ]
    },
    userName() {
      return this.profile?.full_name || 'Pengguna'
    },
    userInitials() {
      return this.userName.split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase()
    },
    userRole() {
      return { admin: 'Administrator', manager: 'Manajer Operasi', requester: 'Pemohon' }[this.profile?.role] || 'Pengguna'
    },
    canManage() {
      return ['admin', 'manager'].includes(this.profile?.role)
    },
  },
  mounted() {
    window.addEventListener('scroll', this.handlePageScroll, { passive: true })
    this.loadDashboard()
  },
  beforeUnmount() {
    window.removeEventListener('scroll', this.handlePageScroll)
    this.stopRequestSubscription?.()
    this.stopRoomSubscription?.()
  },
  methods: {
    async loadDashboard() {
      if (!supabase) {
        this.loadError = 'Konfigurasi Supabase tidak tersedia.'
        this.isLoading = false
        return
      }

      try {
        const { data: { user }, error: authError } = await supabase.auth.getUser()
        if (authError || !user) {
          await this.$router.replace({ name: 'landing' })
          return
        }

        const [profile, rooms, managedRooms, requests] = await Promise.all([
          getProfile(user.id),
          listAvailableRooms(),
          listManagedRooms(),
          listReservations(),
        ])

        if (!profile) throw new Error('Profil pengguna belum dibuat di Supabase.')
        this.profile = profile
        this.rooms = rooms
        this.managedRooms = managedRooms
        this.requests = requests
        await this.checkSchedule({ startsAt: this.scheduleStart, endsAt: this.scheduleEnd })

        if (this.canManage && !this.stopRequestSubscription) {
          this.stopRequestSubscription = subscribeToReservations(null, () => this.loadDashboard())
          this.stopRoomSubscription = subscribeToRoomChanges(() => this.loadDashboard())
        }
      } catch (error) {
        this.loadError = `Gagal memuat data Supabase: ${error.message}. Pastikan schema.sql sudah dijalankan.`
      } finally {
        this.isLoading = false
      }
    },
    async checkSchedule(range = {}) {
      const startsAt = range.startsAt || this.scheduleStart
      const endsAt = range.endsAt || this.scheduleEnd
      this.scheduleStart = startsAt
      this.scheduleEnd = endsAt
      this.scheduleError = ''
      this.isCheckingSchedule = true

      try {
        this.scheduleRooms = await getRoomAvailabilityFor(startsAt, endsAt)
      } catch (error) {
        this.scheduleRooms = []
        this.scheduleError = error.code === 'PGRST202'
          ? 'Pemeriksaan jadwal belum aktif di Supabase. Jalankan supabase/schema.sql melalui SQL Editor.'
          : `Jadwal gagal dicek: ${error.message}`
      } finally {
        this.isCheckingSchedule = false
      }
    },
    showRequest(request) {
      this.selectedRequest = request
    },
    openCreateRequest() {
      this.requestError = ''
      this.isCreateRequestOpen = true
    },
    async decideRequest(request, status, reason = null) {
      if (!supabase || !this.canManage) return
      this.reviewingRequestId = request.databaseId
      this.reviewError = ''
      try {
        const { data: { user }, error } = await supabase.auth.getUser()
        if (error || !user) throw error || new Error('Sesi admin tidak ditemukan.')
        await reviewReservation(request.databaseId, user.id, status, reason)
        this.selectedRequest = null
        await this.loadDashboard()
      } catch (error) {
        this.reviewError = `Keputusan gagal disimpan: ${error.message}`
      } finally {
        this.reviewingRequestId = ''
      }
    },
    approveRequest(request) {
      return this.decideRequest(request, 'accepted')
    },
    rejectRequest({ request, reason }) {
      return this.decideRequest(request, 'rejected', reason)
    },
    async saveManagedRoom(room) {
      this.isSavingRoom = true
      this.roomManagerError = ''
      try {
        await saveRoom(room)
        await this.loadDashboard()
      } catch (error) {
        this.roomManagerError = `Ruangan gagal disimpan: ${error.message}`
      } finally {
        this.isSavingRoom = false
      }
    },
    async toggleRoomActive({ roomId, isActive }) {
      this.isSavingRoom = true
      this.roomManagerError = ''
      try {
        await setRoomActive(roomId, isActive)
        await this.loadDashboard()
      } catch (error) {
        this.roomManagerError = `Status ruangan gagal diperbarui: ${error.message}`
      } finally {
        this.isSavingRoom = false
      }
    },
    closeRequest() {
      this.selectedRequest = null
      this.isCreateRequestOpen = false
      this.requestError = ''
    },
    async handleLogout() {
      try {
        await signOut()
        await this.$router.replace({ name: 'landing' })
      } catch (error) {
        this.loadError = `Gagal keluar: ${error.message}`
      }
    },
    async createRequest(form) {
      if (!supabase) return
      this.isSubmittingRequest = true
      this.requestError = ''

      try {
        const { data: { user }, error: authError } = await supabase.auth.getUser()
        if (authError || !user) throw authError || new Error('Sesi login berakhir. Silakan masuk kembali.')

        await createReservation(form, user.id)
        this.isCreateRequestOpen = false
        await this.loadDashboard()
      } catch (error) {
        this.requestError = `Permintaan gagal disimpan: ${error.message}`
      } finally {
        this.isSubmittingRequest = false
      }
    },
    navigate(section) {
      this.activeNav = section
      document.getElementById(section)?.scrollIntoView({ behavior: 'smooth' })
    },
    toggleMobileSidebar() {
      this.mobileSidebarOpen = !this.mobileSidebarOpen
    },
    closeMobileSidebar() {
      this.mobileSidebarOpen = false
    },
    handlePageScroll() {
      if (this.mobileSidebarOpen && window.innerWidth < 768) {
        this.closeMobileSidebar()
      }
    },
  },
})
</script>

<template>
  <div class="container-fluid px-0">
    <div class="row g-0 align-items-start">
      <DashboardSidebar
        :active-nav="activeNav"
        :mobile-open="mobileSidebarOpen"
        :user-name="userName"
        :user-initials="userInitials"
        :user-role="userRole"
        @navigate="navigate"
        @close="closeMobileSidebar"
        @logout="handleLogout"
      />

      <main class="dashboard-content col px-3 px-lg-4 py-4">
        <DashboardHeader
          v-model="searchTerm"
          :user-name="userName"
          :user-initials="userInitials"
          :user-role="userRole"
          @toggle-menu="toggleMobileSidebar"
          @logout="handleLogout"
        />

        <p v-if="loadError" class="alert alert-warning mt-3" role="alert">{{ loadError }}</p>
        <p v-else-if="isLoading" class="text-muted mt-4" role="status">Memuat data dashboard...</p>

        <div v-else id="beranda" class="mt-4">
          <section class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
            <div>
              <p class="text-muted small fw-bold text-uppercase mb-1">Beranda / Overview</p>
              <h1 class="display-5 fw-black text-dark mb-1">Dashboard Manajemen</h1>
              <p class="text-muted small fw-semibold mb-0">MONITORING KINERJAAN PENGGUNAAN RUANGAN</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <button class="btn btn-outline-secondary rounded-2 d-inline-flex align-items-center gap-2"><Download :size="16" aria-hidden="true" />Ekspor laporan</button>
              <button class="btn btn-primary rounded-2 d-inline-flex align-items-center gap-2" @click="openCreateRequest"><Plus :size="16" aria-hidden="true" />Buat permintaan</button>
            </div>
          </section>

          <StatCards :items="stats" />
          <div class="mt-4">
            <RoomScheduleAvailability
              :rooms="scheduleRooms"
              :starts-at="scheduleStart"
              :ends-at="scheduleEnd"
              :is-checking="isCheckingSchedule"
              :error="scheduleError"
              :can-request="false"
              :can-manage="canManage"
              @check="checkSchedule"
              @manage-rooms="isRoomManagerOpen = true"
            />
          </div>
          <div class="mt-4"><RequestTable :requests="filteredRequests" :can-review="canManage" :reviewing-request-id="reviewingRequestId" @show-request="showRequest" @approve-request="approveRequest" /></div>
        </div>
      </main>
    </div>

    <RequestModal
      :request="selectedRequest"
      :create-open="isCreateRequestOpen"
      :rooms="rooms"
      :is-submitting="isSubmittingRequest"
      :error="requestError"
      :can-review="canManage"
      :is-reviewing="Boolean(reviewingRequestId)"
      :review-error="reviewError"
      @close="closeRequest"
      @create="createRequest"
      @approve="approveRequest"
      @reject="rejectRequest"
    />
    <RoomManagerModal
      :open="isRoomManagerOpen"
      :rooms="managedRooms"
      :is-saving="isSavingRoom"
      :error="roomManagerError"
      @close="isRoomManagerOpen = false"
      @save="saveManagedRoom"
      @toggle-active="toggleRoomActive"
    />
  </div>
</template>

<style scoped>
.dashboard-content {
  min-width: 0;
}
</style>
