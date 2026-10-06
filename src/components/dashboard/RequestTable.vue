<script>
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'RequestTable',
  props: {
    requests: {
      type: Array,
      required: true,
    },
  },
  emits: ['showRequest'],
  methods: {
    statusClass(status) {
      const classes = {
        Diterima: 'bg-success-subtle text-success',
        Menunggu: 'bg-warning-subtle text-warning',
        Proses: 'bg-primary-subtle text-primary',
        Ditolak: 'bg-danger-subtle text-danger',
      }
      return classes[status] || 'bg-secondary-subtle text-secondary'
    },
  },
})
</script>

<template>
  <section class="card shadow-sm border-0 rounded-3" id="permintaan">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pt-4">
      <div><h2 class="h5 fw-bold mb-1">Permintaan terbaru</h2><p class="text-muted small mb-0">Daftar permintaan yang sedang diproses</p></div>
      <button class="btn btn-link text-primary p-0">Lihat semua →</button>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 w-100">
        <thead class="table-light"><tr><th>Permintaan</th><th>Pengguna</th><th>Ruangan</th><th>Jenis</th><th>Status</th><th>Tanggal</th><th></th></tr></thead>
        <tbody>
          <tr v-for="request in requests" :key="request.id">
            <td><strong class="fs-6">{{ request.id }}</strong></td>
            <td><div class="d-flex align-items-center gap-2"><span class="badge bg-primary rounded-circle fs-6 justify-content-center align-items-center" >{{ request.requester.split(' ').map((part) => part[0]).join('').slice(0, 2) }}</span><div class="d-flex flex-column"><strong class="fs-6">{{ request.requester }}</strong><small class="text-muted">{{ request.unit }}</small></div></div></td>
            <td>{{ request.room }}</td>
            <td>{{ request.type }}</td>
            <td><span :class="`badge rounded-pill ${statusClass(request.status)}`">{{ request.status }}</span></td>
            <td>{{ request.date }}</td>
            <td><button class="btn btn-link text-muted p-0" aria-label="Lihat detail" @click="$emit('showRequest', request)">•••</button></td>
          </tr>
        </tbody>
      </table>
    </div>
    <p v-if="requests.length === 0" class="text-muted text-center mb-0 p-4">Tidak ada permintaan yang cocok dengan pencarian.</p>
  </section>
</template>
