<script>
import { defineComponent } from 'vue'
import { CalendarClock, Check, Clock3, DoorOpen, X } from '@lucide/vue'

export default defineComponent({
  name: 'RoomScheduleAvailability',
  components: { CalendarClock, Check, Clock3, DoorOpen, X },
  props: {
    rooms: { type: Array, default: () => [] },
    startsAt: { type: String, required: true },
    endsAt: { type: String, required: true },
    isChecking: { type: Boolean, default: false },
    canRequest: { type: Boolean, default: true },
    canManage: { type: Boolean, default: false },
    error: { type: String, default: '' },
  },
  emits: ['check', 'request-room', 'manage-rooms'],
  data() {
    return { draftStartsAt: this.startsAt, draftEndsAt: this.endsAt }
  },
  computed: {
    scheduleChanged() {
      return this.draftStartsAt !== this.startsAt || this.draftEndsAt !== this.endsAt
    },
  },
  watch: {
    startsAt(value) {
      this.draftStartsAt = value
    },
    endsAt(value) {
      this.draftEndsAt = value
    },
  },
  methods: {
    checkSchedule() {
      this.$emit('check', { startsAt: this.draftStartsAt, endsAt: this.draftEndsAt })
    },
  },
})
</script>

<template>
  <section class="schedule-checker bg-white border rounded-3" aria-labelledby="schedule-checker-title">
    <div class="p-3 p-md-4 border-bottom">
      <p class="text-primary small fw-bold text-uppercase mb-1">Cek jadwal</p>
      <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3">
        <div>
          <h2 id="schedule-checker-title" class="h5 fw-bold mb-1">Ketersediaan ruangan</h2>
          <p class="text-muted small mb-0">Pilih waktu untuk melihat ruangan yang tersedia dan jadwal bentrok.</p>
        </div>
        <div class="d-flex flex-column flex-sm-row align-items-sm-end gap-2">
          <form class="d-flex flex-column flex-sm-row align-items-sm-end gap-2" @submit.prevent="checkSchedule">
            <div><label class="form-label small mb-1" for="availability-start">Mulai</label><input id="availability-start" v-model="draftStartsAt" class="form-control form-control-sm" type="datetime-local" required /></div>
            <div><label class="form-label small mb-1" for="availability-end">Selesai</label><input id="availability-end" v-model="draftEndsAt" class="form-control form-control-sm" type="datetime-local" required /></div>
            <button class="btn btn-sm btn-primary text-nowrap d-inline-flex align-items-center gap-2" type="submit" :disabled="isChecking"><CalendarClock :size="16" aria-hidden="true" />{{ isChecking ? 'Mengecek...' : 'Cek jadwal' }}</button>
          </form>
          <button v-if="canManage" class="btn btn-sm btn-outline-primary text-nowrap" type="button" @click="$emit('manage-rooms')">Kelola ruangan</button>
        </div>
      </div>
    </div>

    <p v-if="error" class="alert alert-danger mx-3 mx-md-4 mt-3 mb-0" role="alert">{{ error }}</p>
    <p v-else-if="scheduleChanged" class="alert alert-warning mx-3 mx-md-4 mt-3 mb-0" role="status">Jadwal berubah. Cek ulang sebelum memilih ruangan.</p>
    <div v-if="isChecking" class="text-muted text-center py-5" role="status">Memeriksa jadwal ruangan...</div>
    <div v-else-if="rooms.length" class="schedule-room-list">
      <article v-for="room in rooms" :key="room.id" class="schedule-room d-flex flex-wrap align-items-center gap-3 px-3 px-md-4 py-3">
        <span class="schedule-room-icon badge rounded-2 d-inline-grid justify-content-center align-items-center" :class="room.isAvailable ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'"><DoorOpen :size="20" aria-hidden="true" /></span>
        <div class="schedule-room-main">
          <strong class="d-block">{{ room.name }}</strong>
          <small class="text-muted">{{ room.building }} · {{ room.floor }} · Kapasitas {{ room.capacity }}</small>
          <small v-if="room.conflictCount" class="d-block text-danger mt-1"><Clock3 :size="13" class="me-1" aria-hidden="true" />Bentrok dengan {{ room.conflictCount }} pengajuan pada rentang ini</small>
        </div>
        <span class="badge rounded-pill ms-auto" :class="room.isAvailable ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'"><Check v-if="room.isAvailable" :size="13" class="me-1" aria-hidden="true" /><X v-else :size="13" class="me-1" aria-hidden="true" />{{ room.status }}</span>
        <button v-if="canRequest" class="btn btn-sm btn-outline-primary text-nowrap" type="button" :disabled="!room.isAvailable || scheduleChanged || isChecking" @click="$emit('request-room', room)">Ajukan ruangan</button>
      </article>
    </div>
    <p v-else-if="!isChecking && !error && !scheduleChanged" class="text-muted text-center px-3 py-5 mb-0">Belum ada ruangan aktif.</p>
  </section>
</template>

<style scoped>
.schedule-room + .schedule-room {
  border-top: 1px solid var(--bs-border-color);
}

.schedule-room-icon {
  inline-size: 40px;
  block-size: 40px;
  flex: 0 0 40px;
}

.schedule-room-main {
  flex: 1 1 12rem;
  min-width: 10rem;
}

@media (max-width: 575.98px) {
  .schedule-room-main {
    min-width: 0;
  }
}
</style>
