<script>
import { defineComponent } from 'vue'
import { Download } from '@lucide/vue'
import { supabase } from '../utils/supabase'
import { listReservations, reviewReservation, subscribeToReservations } from '../services/reservationService'
import { getRoomAvailabilityFor, listAvailableRooms, listManagedRooms, saveRoom, setRoomActive, subscribeToRoomChanges } from '../services/roomService'
import { getProfile, shouldPromptPasswordChange, signOut } from '../services/profileService'
import { createManagedUser, listProfiles, manageUser } from '../services/userService'
import { exportReservationReport } from '../services/reportService'
import { formatAppDateTime, parseAppDateTime } from '../utils/dateTime'
import DashboardHeader from '../components/dashboard/DashboardHeader.vue'
import DashboardSidebar from '../components/dashboard/DashboardSidebar.vue'
import RequestModal from '../components/dashboard/RequestModal.vue'
import RequestTable from '../components/dashboard/RequestTable.vue'
import RoomManagerModal from '../components/dashboard/RoomManagerModal.vue'
import RoomScheduleAvailability from '../components/requester/RoomScheduleAvailability.vue'
import StatCards from '../components/dashboard/StatCards.vue'
import UserManagementPanel from '../components/dashboard/UserManagementPanel.vue'
import PasswordChangePrompt from '../components/dashboard/PasswordChangePrompt.vue'
import ReportExportModal from '../components/dashboard/ReportExportModal.vue'

function getDefaultScheduleRange() {
  const startsAt = parseAppDateTime(formatAppDateTime(new Date(Date.now() + 60 * 60 * 1000)))
  startsAt.setUTCMinutes(0, 0, 0)
  const endsAt = new Date(startsAt.getTime() + 60 * 60 * 1000)
  return { startsAt: formatAppDateTime(startsAt), endsAt: formatAppDateTime(endsAt) }
}

function formatDateInput(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function getDefaultReportRange() {
  const today = new Date()
  const firstDay = new Date(today.getFullYear(), today.getMonth(), 1)
  return { from: formatDateInput(firstDay), to: formatDateInput(today) }
}

export default defineComponent({
  name: 'DashboardPage',
  components: {
    Download,
    DashboardHeader,
    DashboardSidebar,
    RequestModal,
    RequestTable,
    RoomManagerModal,
    RoomScheduleAvailability,
    StatCards,
    UserManagementPanel,
    PasswordChangePrompt,
    ReportExportModal,
  },
  data() {
    const schedule = getDefaultScheduleRange()
    const reportRange = getDefaultReportRange()
    return {
      activeNav: 'beranda',
      mobileSidebarOpen: false,
      searchTerm: '',
      isReportExportOpen: false,
      isExportingReport: false,
      reportExportError: '',
      reportFrom: reportRange.from,
      reportTo: reportRange.to,
      profile: null,
      showPasswordChangePrompt: false,
      hasCheckedPasswordPrompt: false,
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
      reviewErrorTimeout: null,
      loadError: '',
      isLoading: true,
      isRoomManagerOpen: false,
      isSavingRoom: false,
      roomManagerError: '',
      users: [],
      isLoadingUsers: false,
      isCreatingUser: false,
      updatingUserId: '',
      userManagementError: '',
      userManagementSuccess: '',
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
      return [
        { icon: 'rooms', type: 'bg-primary-subtle text-primary', label: 'Total ruangan', value: this.rooms.length, description: 'Ruangan aktif' },
        { icon: 'accepted', type: 'bg-success-subtle text-success', label: 'Permintaan diterima', value: accepted, description: 'Sesuai data akun' },
        { icon: 'active', type: 'bg-warning-subtle text-warning', label: 'Peminjaman aktif', value: this.activeReservationCount, description: 'Belum selesai / dibatalkan' },
      ]
    },
    activeReservationCount() {
      return this.requests.filter((request) =>
        request.status === 'Diterima' && new Date(request.endsAt).getTime() > Date.now(),
      ).length
    },
    pendingApprovalCount() {
      return this.requests.filter((request) => request.status === 'Menunggu').length
    },
    userName() {
      return this.profile?.full_name || 'Pengguna'
    },
    userInitials() {
      return this.userName.split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase()
    },
    userRole() {
      return { admin: 'Administrator', requester: 'Pemohon' }[this.profile?.role] || 'Pengguna'
    },
    canManage() {
      return this.profile?.role === 'admin'
    },
    isAdmin() {
      return this.profile?.role === 'admin'
    },
  },
  mounted() {
    window.addEventListener('scroll', this.handlePageScroll, { passive: true })
    this.loadDashboard()
  },
  beforeUnmount() {
    window.removeEventListener('scroll', this.handlePageScroll)
    this.clearReviewErrorTimeout()
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
        if (!this.hasCheckedPasswordPrompt) {
          this.showPasswordChangePrompt = Boolean(
            profile.must_change_password && shouldPromptPasswordChange(user.id),
          )
          this.hasCheckedPasswordPrompt = true
        }
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
      this.clearReviewErrorTimeout()
      this.reviewError = ''
    },
    openReportExport() {
      if (!this.isAdmin) return
      this.reportExportError = ''
      this.isReportExportOpen = true
    },
    async exportReport() {
      if (!this.isAdmin) return
      this.isExportingReport = true
      this.reportExportError = ''
      try {
        await exportReservationReport({ from: this.reportFrom, to: this.reportTo })
        this.isReportExportOpen = false
      } catch (error) {
        this.reportExportError = `Laporan gagal dibuat: ${error.message}`
      } finally {
        this.isExportingReport = false
      }
    },
    async decideRequest(request, status, reason = null) {
      if (!this.isAdmin || !request) return
      this.reviewingRequestId = request.databaseId
      this.clearReviewErrorTimeout()
      this.reviewError = ''
      try {
        await reviewReservation(request.databaseId, status, reason)
        this.selectedRequest = null
        await this.loadDashboard()
      } catch (error) {
        const earlierRequest = status === 'accepted'
          && /lebih awal|bentrok|overlap/i.test(error.message)
          ? this.getEarlierConflictingRequest(request)
          : null
        this.reviewError = earlierRequest
          ? `Permintaan ${request.id} belum dapat disetujui: ${earlierRequest.id} lebih dahulu mengantre dan jadwalnya bentrok.`
          : `Keputusan permintaan ${request.id} gagal disimpan: ${error.message}`
        this.reviewErrorTimeout = window.setTimeout(() => {
          this.reviewError = ''
          this.reviewErrorTimeout = null
        }, 5000)
      } finally {
        this.reviewingRequestId = ''
      }
    },
    getEarlierConflictingRequest(request) {
      return this.requests
        .filter((candidate) =>
          candidate.status === 'Menunggu'
          && candidate.databaseId !== request.databaseId
          && candidate.roomId === request.roomId
          && new Date(candidate.createdAt).getTime() < new Date(request.createdAt).getTime()
          && new Date(candidate.startsAt).getTime() < new Date(request.endsAt).getTime()
          && new Date(candidate.endsAt).getTime() > new Date(request.startsAt).getTime(),
        )
        .sort((left, right) => new Date(left.createdAt) - new Date(right.createdAt))[0] || null
    },
    clearReviewErrorTimeout() {
      if (this.reviewErrorTimeout !== null) {
        window.clearTimeout(this.reviewErrorTimeout)
        this.reviewErrorTimeout = null
      }
    },
    approveRequest(request) {
      return this.decideRequest(request, 'accepted')
    },
    rejectRequest({ request, reason }) {
      return this.decideRequest(request, 'rejected', reason)
    },
    changePasswordNow() {
      this.showPasswordChangePrompt = false
      this.$router.push({ name: 'change-password' })
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
    async loadUsers() {
      if (!this.isAdmin) return
      this.isLoadingUsers = true
      this.userManagementError = ''
      try {
        this.users = await listProfiles()
      } catch (error) {
        this.userManagementError = `Gagal memuat pengguna: ${error.message}`
      } finally {
        this.isLoadingUsers = false
      }
    },
    async createUser(form) {
      if (!this.isAdmin) return
      this.isCreatingUser = true
      this.userManagementError = ''
      this.userManagementSuccess = ''
      try {
        const user = await createManagedUser(form)
        this.userManagementSuccess = `Akun ${user.email} berhasil dibuat. Password awal: ${user.initialPassword}. Sampaikan secara pribadi dan minta pengguna segera menggantinya.`
        await this.loadUsers()
      } catch (error) {
        this.userManagementError = `Gagal membuat akun: ${error.message}`
      } finally {
        this.isCreatingUser = false
      }
    },
    async handleUserAction({ userId, action }) {
      if (!this.isAdmin) return
      this.updatingUserId = userId
      this.userManagementError = ''
      this.userManagementSuccess = ''
      try {
        await manageUser(userId, action)
        const messages = {
          suspend: 'Akun berhasil di-suspend. Pengguna tidak dapat login.',
          reactivate: 'Akun berhasil diaktifkan kembali.',
          delete: 'Akun diarsipkan. Profil dan riwayat peminjaman tetap disimpan.',
        }
        this.userManagementSuccess = messages[action] || 'Aksi pengguna berhasil.'
        await this.loadUsers()
      } catch (error) {
        this.userManagementError = `Aksi pengguna gagal: ${error.message}`
      } finally {
        this.updatingUserId = ''
      }
    },
    closeRequest() {
      this.selectedRequest = null
    },
    async handleLogout() {
      try {
        await signOut()
        await this.$router.replace({ name: 'landing' })
      } catch (error) {
        this.loadError = `Gagal keluar: ${error.message}`
      }
    },
    navigate(section) {
      this.activeNav = section
      if (section === 'pengguna' && this.isAdmin) {
        this.loadUsers()
        this.$nextTick(() => document.getElementById('pengguna')?.scrollIntoView({ behavior: 'smooth' }))
        return
      }
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
        :pending-approval-count="pendingApprovalCount"
        :is-admin="isAdmin"
        :can-manage="canManage"
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
        <p v-if="isLoading" class="text-muted mt-4" role="status">Memuat data dashboard...</p>

        <div v-else-if="activeNav === 'pengguna' && isAdmin" class="mt-4">
          <UserManagementPanel
            :users="users"
            :is-loading="isLoadingUsers"
            :is-saving="isCreatingUser"
            :updating-user-id="updatingUserId"
            :error="userManagementError"
            :success="userManagementSuccess"
            @reload="loadUsers"
            @create-user="createUser"
            @manage-user="handleUserAction"
          />
        </div>

        <div v-else id="beranda" class="mt-4">
          <section class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
            <div>
              <p class="text-muted small fw-bold text-uppercase mb-1">Beranda / Overview</p>
              <h1 class="display-5 fw-black text-dark mb-1">Dashboard Manajemen</h1>
              <p class="text-muted small fw-semibold mb-0">MONITORING KINERJAAN PENGGUNAAN RUANGAN</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <button
                v-if="isAdmin"
                class="btn btn-outline-secondary rounded-2 d-inline-flex align-items-center gap-2"
                type="button"
                @click="openReportExport"
              ><Download :size="16" aria-hidden="true" />Ekspor laporan</button>
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
          <div class="mt-4">
            <RequestTable
              :requests="filteredRequests"
              :can-review="isAdmin"
              :reviewing-request-id="reviewingRequestId"
              @show-request="showRequest"
              @approve-request="approveRequest"
            />
          </div>
        </div>
      </main>
    </div>

    <RequestModal
      :request="selectedRequest"
      :can-review="isAdmin"
      :is-reviewing="Boolean(reviewingRequestId)"
      :review-error="reviewError"
      @close="closeRequest"
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
    <PasswordChangePrompt
      :open="showPasswordChangePrompt"
      @change-now="changePasswordNow"
      @later="showPasswordChangePrompt = false"
    />
    <ReportExportModal
      :open="isReportExportOpen"
      :from="reportFrom"
      :to="reportTo"
      :is-exporting="isExportingReport"
      :error="reportExportError"
      @close="isReportExportOpen = false"
      @export="exportReport"
      @update:from="reportFrom = $event"
      @update:to="reportTo = $event"
    />
    <div v-if="reviewError" class="alert alert-danger review-error-toast shadow" role="alert">
      {{ reviewError }}
    </div>
  </div>
</template>

<style scoped>
.dashboard-content {
  min-width: 0;
}

.review-error-toast {
  position: fixed;
  z-index: 1090;
  top: 1rem;
  right: 1rem;
  width: min(32rem, calc(100vw - 2rem));
}
</style>
