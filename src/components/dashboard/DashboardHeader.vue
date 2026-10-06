<script>
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'DashboardHeader',
  props: {
    modelValue: {
      type: String,
      default: '',
    },
  },
  emits: ['update:modelValue'],
})
</script>

<template>
  <header class="navbar navbar-expand-sm navbar-light bg-white border-bottom px-3 px-lg-4 py-3">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-link text-dark p-0 fs-4" aria-label="Buka menu">☰</button>
      <label class="input-group flex-grow-1 shadow-sm rounded-2">
        <span class="input-group-text bg-light border-0"><span aria-hidden="true">⌕</span></span>
        <input
          :value="modelValue"
          class="form-control border-0 bg-light"
          type="search"
          placeholder="Cari permintaan, pengguna, atau ruangan..."
          @input="$emit('update:modelValue', $event.target.value)"
        />
      </label>
      <div class="d-none d-md-flex align-items-center gap-3">
        <button class="btn btn-light border rounded-2 position-relative" aria-label="Notifikasi">◌<span class="badge bg-danger rounded-circle position-absolute top-0 end-0"></span></button>
        <div class="d-flex align-items-center gap-2"><span class="badge bg-primary rounded-circle fs-6 d-inline-grid justify-content-center align-items-center" >AS</span><div class="d-flex flex-column"><strong class="fs-6">Andi Setiawan</strong><small class="text-muted">Manajer Operasi</small></div></div>
      </div>
    </div>
  </header>
</template>
