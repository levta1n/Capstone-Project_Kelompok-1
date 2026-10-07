<script>
import { defineComponent } from 'vue'
import { ArrowRight, Check, ChevronLeft, ChevronRight, MoreHorizontal, X } from '@lucide/vue'

export default defineComponent({
  name: 'RequestTable',
  components: {
    ArrowRight,
    Check,
    ChevronLeft,
    ChevronRight,
    MoreHorizontal,
    X,
  },
  props: {
    requests: {
      type: Array,
      required: true,
    },
    canReview: {
      type: Boolean,
      default: false,
    },
    reviewingRequestId: {
      type: String,
      default: '',
    },
  },
  emits: ['showRequest', 'approveRequest'],
  data() {
    return {
      currentPage: 1,
      pageSize: 3,
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
      if (page >= 1 && page <= this.pageCount) {
        this.currentPage = page
      }
    },
    statusClass(status) {
      const classes = {
        Diterima: 'bg-success-subtle text-success',
        Menunggu: 'bg-warning-subtle text-warning',
        Proses: 'bg-primary-subtle text-primary',
        Ditolak: 'bg-danger-subtle text-danger',
      }
      return classes[status] || 'bg-secondary-subtle text-secondary'
    },
    canDecide(request) {
      return this.canReview && ['Menunggu', 'Proses'].includes(request.status)
    },
  },
})
</script>

<template>
  <section class="card shadow-sm border-0 rounded-3" id="permintaan">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-4">
      <div><h2 class="h5 fw-bold mb-1">Permintaan terbaru</h2><p class="text-muted small mb-0">Daftar permintaan yang sedang diproses</p></div>
      <button class="btn btn-link text-primary p-0 d-inline-flex align-items-center gap-1">Lihat semua <ArrowRight :size="16" aria-hidden="true" /></button>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 w-100">
        <thead class="table-light"><tr><th>Permintaan</th><th>Pengguna</th><th>Ruangan</th><th>Jenis</th><th>Status</th><th>Tanggal</th><th></th></tr></thead>
        <tbody>
          <tr v-for="request in visibleRequests" :key="request.id">
            <td><strong class="fs-6">{{ request.id }}</strong></td>
            <td><div class="d-flex align-items-center gap-2"><span class="requester-avatar badge bg-primary rounded-circle fs-6 d-inline-grid justify-content-center align-items-center">{{ request.requester.split(' ').map((part) => part[0]).join('').slice(0, 2) }}</span><div class="d-flex flex-column"><strong class="fs-6">{{ request.requester }}</strong><small class="text-muted">{{ request.unit }}</small></div></div></td>
            <td>{{ request.room }}</td>
            <td>{{ request.type }}</td>
            <td><span :class="`badge rounded-pill ${statusClass(request.status)}`">{{ request.status }}</span></td>
            <td>{{ request.date }}</td>
            <td>
              <div class="d-flex align-items-center gap-1">
                <button
                  v-if="canDecide(request)"
                  class="btn btn-sm btn-outline-success p-1"
                  type="button"
                  :aria-label="`Setujui ${request.id}`"
                  title="Setujui"
                  :disabled="reviewingRequestId === request.databaseId"
                  @click="$emit('approveRequest', request)"
                ><Check :size="16" aria-hidden="true" /></button>
                <button
                  v-if="canDecide(request)"
                  class="btn btn-sm btn-outline-danger p-1"
                  type="button"
                  :aria-label="`Tolak ${request.id}`"
                  title="Tolak dengan alasan"
                  :disabled="reviewingRequestId === request.databaseId"
                  @click="$emit('showRequest', request)"
                ><X :size="16" aria-hidden="true" /></button>
                <button class="btn btn-link text-muted p-0" type="button" aria-label="Lihat detail" @click="$emit('showRequest', request)"><MoreHorizontal :size="18" aria-hidden="true" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <nav v-if="pageCount > 1" class="d-flex align-items-center justify-content-between gap-3 border-top px-3 py-2" aria-label="Halaman permintaan">
      <small class="text-muted">Menampilkan {{ rangeStart }}–{{ rangeEnd }} dari {{ requests.length }} permintaan</small>
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-light border" type="button" aria-label="Halaman sebelumnya" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)"><ChevronLeft :size="16" aria-hidden="true" /></button>
        <span class="small text-nowrap">{{ currentPage }} / {{ pageCount }}</span>
        <button class="btn btn-sm btn-light border" type="button" aria-label="Halaman berikutnya" :disabled="currentPage === pageCount" @click="goToPage(currentPage + 1)"><ChevronRight :size="16" aria-hidden="true" /></button>
      </div>
    </nav>
    <p v-if="requests.length === 0" class="text-muted text-center mb-0 p-4">Tidak ada permintaan yang cocok dengan pencarian.</p>
  </section>
</template>

<style scoped>
.requester-avatar {
  inline-size: 32px;
  block-size: 32px;
  flex: 0 0 32px;
  padding: 0;
}
</style>
