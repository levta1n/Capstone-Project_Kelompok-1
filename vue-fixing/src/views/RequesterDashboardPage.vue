<script>
import { defineComponent } from 'vue'
import { ArrowDown, Check, Clock3, Plus } from '@lucide/vue'
import MyRequestsList from '../components/requester/MyRequestsList.vue'
import RequesterHeader from '../components/requester/RequesterHeader.vue'
import RoomScheduleAvailability from '../components/requester/RoomScheduleAvailability.vue'
import RequestModal from '../components/dashboard/RequestModal.vue'
import { createReservation, listReservations, subscribeToReservations } from '../services/reservationService'
import { getRoomAvailabilityFor, subscribeToRoomChanges } from '../services/roomService'
import { getProfile, signOut } from '../services/profileService'
import { supabase } from '../utils/supabase'

function getDefaultScheduleRange() {
  const startsAt = new Date()
  startsAt.setHours(startsAt.getHours() + 1, 0, 0, 0)
  const endsAt = new Date(startsAt.getTime() + 60 * 60 * 1000)
  const toLocalInput = (date) => new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 16)
  return { startsAt: toLocalInput(startsAt), endsAt: toLocalInput(endsAt) }
}

export default defineComponent({
  name: 'RequesterDashboardPage',
  components: {
    ArrowDown,
    Check,
    Clock3,
    MyRequestsList,
    Plus,
    RequesterHeader,
    RequestModal,
    RoomScheduleAvailability,
  },
  data() {
    const schedule = getDefaultScheduleRange()
    return {
      profile: null,
      requests: [],
      rooms: [],
      scheduleStart: schedule.startsAt,
      scheduleEnd: schedule.endsAt,
      isCheckingSchedule: false,
      scheduleError: '',
      selectedRoomId: '',
      isLoading: true,
      loadError: '',
      isCreateRequestOpen: false,
      isSubmittingRequest: false,
      requestError: '',
      selectedRequest: null,
      stopRequestSubscription: null,
      stopRoomSubscription: null,
    }
  },
  computed: {
    activeRequests() {
      return this.requests.filter((request) => ['Menunggu', 'Proses'].includes(request.status)).length
    },
    acceptedRequests() {
      return this.requests.filter((request) => request.status === 'Diterima').length
    },
    availableRooms() {
      return this.rooms.filter((room) => room.isAvailable)
    },
  },
  mounted() {
    this.loadDashboard()
  },
  beforeUnmount() {
    this.stopRequestSubscription?.()
    this.stopRoomSubscription?.()
  },
  methods: {
    async loadDashboard() {
      this.loadError = ''
      try {
        if (!supabase) throw new Error('Konfigurasi Supabase tidak tersedia.')
        const { data: { user }, error } = await supabase.auth.getUser()
        if (error || !user) {
          await this.$router.replace({ name: 'landing' })
          return
        }

        const [profile, requests] = await Promise.all([
          getProfile(user.id),
          listReservations(user.id),
        ])
        if (!profile) throw new Error('Profil pengguna belum dibuat di Supabase.')
        if (profile.role !== 'requester') {
          await this.$router.replace({ name: 'dashboard' })
          return
        }

        this.profile = profile
        this.requests = requests
        await this.checkSchedule({ startsAt: this.scheduleStart, endsAt: this.scheduleEnd })

        if (!this.stopRequestSubscription) {
          this.stopRequestSubscription = subscribeToReservations(user.id, () => this.loadDashboard())
          this.stopRoomSubscription = subscribeToRoomChanges(() => this.checkSchedule())
        }
      } catch (error) {
        this.loadError = `Gagal memuat data: ${error.message}`
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
        this.rooms = await getRoomAvailabilityFor(startsAt, endsAt)
      } catch (error) {
        this.rooms = []
        this.scheduleError = error.code === 'PGRST202'
          ? 'Fitur cek jadwal belum aktif di Supabase. Jalankan ulang supabase/schema.sql melalui SQL Editor, lalu muat ulang halaman.'
          : `Jadwal gagal dicek: ${error.message}`
      } finally {
        this.isCheckingSchedule = false
      }
    },
    openRequestForm(room = null) {
      this.requestError = ''
      this.selectedRoomId = room?.id || this.availableRooms[0]?.id || ''
      this.isCreateRequestOpen = true
    },
    closeModal() {
      this.isCreateRequestOpen = false
      this.selectedRequest = null
      this.selectedRoomId = ''
      this.requestError = ''
    },
    async submitRequest(form) {
      if (!this.profile) return
      this.isSubmittingRequest = true
      this.requestError = ''
      try {
        await createReservation(form, this.profile.id)
        this.closeModal()
        await this.loadDashboard()
      } catch (error) {
        this.requestError = `Permintaan gagal disimpan: ${error.message}`
      } finally {
        this.isSubmittingRequest = false
      }
    },
    async handleLogout() {
      try {
        await signOut()
        await this.$router.replace({ name: 'landing' })
      } catch (error) {
        this.loadError = `Gagal keluar: ${error.message}`
      }
    },
    scrollToRequests() {
      document.getElementById('requests-list')?.scrollIntoView({ behavior: 'smooth' })
    },
  },
})
</script>

<template>
  <div class="requester-page min-vh-100 bg-light">
    <RequesterHeader :profile="profile" @logout="handleLogout" />
    <main class="container py-4 py-lg-5">
      <div v-if="loadError" class="alert alert-warning" role="alert">
        {{ loadError }}
        <button class="btn btn-sm btn-outline-secondary ms-2" type="button" @click="loadDashboard">Coba lagi</button>
      </div>
      <div v-else-if="isLoading" class="text-muted py-5" role="status">Memuat halaman pemohon...</div>
      <template v-else>
        <section class="requester-intro d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4 mb-4">
          <div>
            <p class="text-primary small text-uppercase fw-bold mb-2">Portal peminjaman ruangan</p>
            <h1 class="h2 fw-bold text-dark mb-2">Halo, {{ profile.full_name }}</h1>
            <p class="text-muted mb-0">{{ profile.unit_name || 'Ajukan dan pantau penggunaan ruangan Anda.' }}</p>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary rounded-2 d-inline-flex align-items-center gap-2" href="#ruangan-tersedia"><ArrowDown :size="16" aria-hidden="true" />Lihat ruangan</a>
            <button class="btn btn-primary rounded-2 d-inline-flex align-items-center gap-2" type="button" :disabled="availableRooms.length === 0" @click="openRequestForm()"><Plus :size="16" aria-hidden="true" />Ajukan ruangan</button>
          </div>
        </section>

        <section class="requester-summary d-flex flex-wrap align-items-center gap-4 px-3 px-md-4 py-3 mb-4 bg-white border rounded-3" aria-label="Ringkasan permintaan">
          <div class="d-flex align-items-center gap-2"><Clock3 class="text-warning" :size="18" aria-hidden="true" /><span class="small text-muted">Menunggu</span><strong>{{ activeRequests }}</strong></div>
          <div class="d-flex align-items-center gap-2"><Check class="text-success" :size="18" aria-hidden="true" /><span class="small text-muted">Diterima</span><strong>{{ acceptedRequests }}</strong></div>
          <div class="d-flex align-items-center gap-2"><span class="small text-muted">Ruangan tersedia untuk jadwal</span><strong>{{ availableRooms.length }}</strong></div>
        </section>

        <div class="d-grid gap-4">
          <RoomScheduleAvailability
            :rooms="rooms"
            :starts-at="scheduleStart"
            :ends-at="scheduleEnd"
            :is-checking="isCheckingSchedule"
            :error="scheduleError"
            @check="checkSchedule"
            @request-room="openRequestForm"
          />
          <MyRequestsList :requests="requests" @create="openRequestForm" @show-request="selectedRequest = $event" @see-all="scrollToRequests" />
        </div>
      </template>
    </main>

    <RequestModal
      :request="selectedRequest"
      :create-open="isCreateRequestOpen"
      :rooms="availableRooms"
      :is-submitting="isSubmittingRequest"
      :error="requestError"
      :initial-room-id="selectedRoomId"
      :initial-starts-at="scheduleStart"
      :initial-ends-at="scheduleEnd"
      schedule-read-only
      @close="closeModal"
      @create="submitRequest"
    />
  </div>
</template>

<style scoped>
.requester-page {
  min-height: 100vh;
}

.requester-intro {
  padding-block: 0.5rem;
}

.requester-summary > div {
  min-width: max-content;
}
</style>
