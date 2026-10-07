<script>
import { defineComponent } from 'vue'
import { ArrowRight, ChevronLeft, ChevronRight, DoorOpen } from '@lucide/vue'

export default defineComponent({
  name: 'RoomAvailability',
  components: {
    ArrowRight,
    ChevronLeft,
    ChevronRight,
    DoorOpen,
  },
  props: {
    rooms: {
      type: Array,
      required: true,
    },
    canManage: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['manage-rooms'],
  data() {
    return {
      currentPage: 1,
      pageSize: 2,
    }
  },
  computed: {
    pageCount() {
      return Math.ceil(this.rooms.length / this.pageSize)
    },
    visibleRooms() {
      const start = (this.currentPage - 1) * this.pageSize
      return this.rooms.slice(start, start + this.pageSize)
    },
    rangeStart() {
      return this.rooms.length ? (this.currentPage - 1) * this.pageSize + 1 : 0
    },
    rangeEnd() {
      return Math.min(this.currentPage * this.pageSize, this.rooms.length)
    },
  },
  watch: {
    rooms() {
      this.currentPage = 1
    },
  },
  methods: {
    goToPage(page) {
      if (page >= 1 && page <= this.pageCount) {
        this.currentPage = page
      }
    },
  },
})
</script>

<template>
  <section class="card shadow-sm border-0 rounded-3" id="ruangan">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-4">
      <div><h2 class="h5 fw-bold mb-1">Ketersediaan ruangan</h2><p class="text-muted small mb-0">Ringkasan kapasitas unit</p></div>
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-link text-primary p-0 d-inline-flex align-items-center gap-1" type="button">Lihat daftar <ArrowRight :size="16" aria-hidden="true" /></button>
        <button v-if="canManage" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" type="button" @click="$emit('manage-rooms')">Kelola ruangan</button>
      </div>
    </div>
    <div v-if="rooms.length" class="list-group list-group-horizontal flex-wrap">
      <div v-for="room in visibleRooms" :key="room.name" class="list-group-item flex-fill d-flex align-items-center gap-3 border-0">
        <span class="badge bg-primary-subtle text-primary rounded-2 p-2 d-inline-grid justify-content-center align-items-center"><DoorOpen :size="20" aria-hidden="true" /></span>
        <div class="d-flex flex-column"><strong class="fs-6">{{ room.name }}</strong><small class="text-muted">{{ room.floor }} · {{ room.capacity }} tempat</small></div>
        <div class="d-flex flex-column ms-auto text-end"><strong class="text-primary fs-6">{{ room.available }}/{{ room.capacity }}</strong><small :class="room.available > 0 ? 'text-success' : 'text-danger'">{{ room.status }}</small></div>
      </div>
    </div>
    <p v-else class="text-muted text-center px-3 py-5 mb-0">Belum ada ruangan yang tersedia saat ini.</p>
    <nav v-if="pageCount > 1" class="d-flex align-items-center justify-content-between gap-3 border-top px-3 py-2" aria-label="Halaman daftar ruangan">
      <small class="text-muted">Ruangan {{ rangeStart }}–{{ rangeEnd }} dari {{ rooms.length }}</small>
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-light border" type="button" aria-label="Ruangan sebelumnya" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)"><ChevronLeft :size="16" aria-hidden="true" /></button>
        <span class="small text-nowrap">{{ currentPage }} / {{ pageCount }}</span>
        <button class="btn btn-sm btn-light border" type="button" aria-label="Ruangan berikutnya" :disabled="currentPage === pageCount" @click="goToPage(currentPage + 1)"><ChevronRight :size="16" aria-hidden="true" /></button>
      </div>
    </nav>
  </section>
</template>
