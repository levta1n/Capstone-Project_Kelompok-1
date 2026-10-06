<script>
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'RequestModal',
  props: {
    request: {
      type: Object,
      default: null,
    },
  },
  emits: ['close'],
})
</script>

<template>
  <div v-if="request" class="modal fade show d-block" @click="$emit('close')">
    <div class="modal-dialog modal-dialog-centered" @click.stop>
      <div class="modal-content shadow-lg border-0 rounded-4">
        <div class="modal-header border-0">
          <div><span class="text-primary small fw-bold text-uppercase">Detail permintaan</span><h2 class="modal-title h5 fw-bold">{{ request.id }}</h2></div>
          <button type="button" class="btn-close" aria-label="Tutup detail" @click="$emit('close')"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6"><span class="text-muted small d-block">Pengguna</span><strong class="fs-6">{{ request.requester }}</strong></div>
            <div class="col-6"><span class="text-muted small d-block">Unit</span><strong class="fs-6">{{ request.unit }}</strong></div>
            <div class="col-6"><span class="text-muted small d-block">Ruangan</span><strong class="fs-6">{{ request.room }}</strong></div>
            <div class="col-6"><span class="text-muted small d-block">Pemilik</span><strong class="fs-6">{{ request.owner }}</strong></div>
            <div class="col-6"><span class="text-muted small d-block">Jenis</span><strong class="fs-6">{{ request.type }}</strong></div>
            <div class="col-6"><span class="text-muted small d-block">Status</span><strong class="fs-6">{{ request.status }}</strong></div>
          </div>
        </div>
        <div class="modal-footer border-0"><button class="btn btn-primary rounded-2" @click="$emit('close')">Tutup detail</button></div>
      </div>
    </div>
  </div>
</template>
