<script>
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'RoomAvailability',
  props: {
    rooms: {
      type: Array,
      required: true,
    },
  },
})
</script>

<template>
  <section class="card shadow-sm border-0 rounded-3" id="ruangan">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-4">
      <div><h2 class="h5 fw-bold mb-1">Ketersediaan ruangan</h2><p class="text-muted small mb-0">Ringkasan kapasitas unit</p></div>
      <button class="btn btn-link text-primary p-0">Lihat daftar →</button>
    </div>
    <div class="list-group list-group-horizontal flex-wrap">
      <div v-for="room in rooms" :key="room.name" class="list-group-item flex-fill d-flex align-items-center gap-3 border-0">
        <span class="badge bg-primary-subtle text-primary rounded-2 fs-4 d-inline-grid justify-content-center align-items-center" >⌂</span>
        <div class="d-flex flex-column"><strong class="fs-6">{{ room.name }}</strong><small class="text-muted">{{ room.floor }} · {{ room.capacity }} tempat</small></div>
        <div class="d-flex flex-column ms-auto text-end"><strong class="text-primary fs-6">{{ room.available }}/{{ room.capacity }}</strong><small class="text-success">{{ room.status }}</small></div>
      </div>
    </div>
  </section>
</template>
