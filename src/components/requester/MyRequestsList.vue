<script>
import { defineComponent } from 'vue'
import { CalendarDays, ChevronLeft, ChevronRight, ClipboardList, Eye } from '@lucide/vue'

export default defineComponent({
  name: 'MyRequestsList',
  components: {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    Eye,
  },
  props: {
    requests: {
      type: Array,
      required: true,
    },
  },
  emits: ['create', 'show-request', 'see-all'],
  data() {
    return {
      currentPage: 1,
      pageSize: 4,
    }
  },
  computed: {
    pageCount() {
      return Math.ceil(this.requests.length / this.pageSize)
    },
    visibleRequests() {
      const start = (this.currentPage - 1) * this.pageSize
      return this.requests.slice(start, start + this.pageSize)
    },
    rangeStart() {
      return this.requests.length ? (this.currentPage - 1) * this.pageSize + 1 : 0
    },
    rangeEnd() {
      return Math.min(this.currentPage * this.pageSize, this.requests.length)
    },
  },
  watch: {
    requests() {
      this.currentPage = 1
    },
  },
  methods: {
    goToPage(page) {
      if (page >= 1 && page <= this.pageCount) this.currentPage = page
    },
    statusClass(status) {
      return {
        Diterima: 'bg-success-subtle text-success',
        Menunggu: 'bg-warning-subtle text-warning',
        Proses: 'bg-primary-subtle text-primary',
        Ditolak: 'bg-danger-subtle text-danger',
        Dibatalkan: 'bg-secondary-subtle text-secondary',
      }[status] || 'bg-secondary-subtle text-secondary'
    },
    formatSchedule(value) {
      return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
      }).format(new Date(value))
    },
  },
})
</script>

<template>
  <section id="requests-list" class="request-list-section bg-white border rounded-3">
    <div class="d-flex align-items-start justify-content-between gap-3 p-3 p-md-4 border-bottom">
      <div>
        <h2 class="h5 fw-bold mb-1">Permintaan saya</h2>
        <p class="text-muted small mb-0">Pantau status dan jadwal pengajuan ruangan.</p>
      </div>
      <button class="btn btn-link text-primary p-0 text-nowrap" type="button" @click="$emit('see-all')">Lihat semua</button>
    </div>

    <div v-if="requests.length" class="request-list">
      <article v-for="request in visibleRequests" :key="request.id" class="request-row d-flex flex-wrap align-items-center gap-3 px-3 px-md-4 py-3">
        <span class="request-icon badge bg-primary-subtle text-primary rounded-2 d-inline-grid justify-content-center align-items-center"><ClipboardList :size="19" aria-hidden="true" /></span>
        <div class="request-main">
          <strong class="d-block text-dark">{{ request.room }}</strong>
          <small class="text-muted">{{ request.id }} · {{ request.type }} · {{ request.participantCount }} peserta</small>
        </div>
        <div class="request-time d-flex align-items-center gap-1 text-muted small"><CalendarDays :size="15" aria-hidden="true" />{{ formatSchedule(request.startsAt) }}</div>
        <span :class="`badge rounded-pill ${statusClass(request.status)}`">{{ request.status }}</span>
        <button class="btn btn-light border rounded-2" type="button" :aria-label="`Detail ${request.id}`" @click="$emit('show-request', request)"><Eye :size="17" aria-hidden="true" /></button>
      </article>
    </div>

    <div v-else class="empty-requests text-center px-4 py-5">
      <span class="badge bg-primary-subtle text-primary rounded-circle d-inline-grid justify-content-center align-items-center mb-3"><ClipboardList :size="22" aria-hidden="true" /></span>
      <h3 class="h6 fw-bold">Belum ada permintaan</h3>
      <p class="text-muted small mb-3">Pilih ruangan yang tersedia untuk memulai pengajuan.</p>
      <button class="btn btn-primary rounded-2" type="button" @click="$emit('create')">Ajukan ruangan</button>
    </div>

    <div v-if="pageCount > 1" class="d-flex align-items-center justify-content-between gap-3 px-3 px-md-4 py-2 border-top">
      <small class="text-muted">{{ rangeStart }}–{{ rangeEnd }} dari {{ requests.length }}</small>
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-light border" type="button" aria-label="Halaman sebelumnya" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)"><ChevronLeft :size="16" /></button>
        <span class="small">{{ currentPage }} / {{ pageCount }}</span>
        <button class="btn btn-sm btn-light border" type="button" aria-label="Halaman berikutnya" :disabled="currentPage === pageCount" @click="goToPage(currentPage + 1)"><ChevronRight :size="16" /></button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.request-row + .request-row {
  border-top: 1px solid var(--bs-border-color);
}

.request-icon {
  inline-size: 40px;
  block-size: 40px;
  flex: 0 0 40px;
}

.request-main {
  min-width: 160px;
  flex: 1 1 auto;
}

.request-time {
  white-space: nowrap;
}

.empty-requests > .badge {
  inline-size: 44px;
  block-size: 44px;
}

@media (max-width: 575.98px) {
  .request-time {
    flex-basis: 100%;
    padding-left: 52px;
  }
}
</style>