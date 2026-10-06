<script>
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'DashboardSidebar',
  props: {
    activeNav: {
      type: String,
      required: true,
    },
  },
  emits: ['navigate'],
  methods: {
    navigate(section) {
      this.$emit('navigate', section)
    },
  },
})
</script>

<template>
  <aside class="col-12 col-lg-3 bg-dark text-white position-sticky top-0 vh-100 overflow-auto p-3 p-lg-4 d-flex flex-column">
    <router-link class="navbar-brand d-flex align-items-center gap-2 fw-bold text-white mb-4" to="/">
      <span class="badge bg-primary rounded-3 fs-5 d-inline-grid justify-content-center align-items-center" >S</span>
      <span class="d-flex flex-column"><span>SIMPERU</span><small class="text-primary fs-6 fw-bold letter-spacing-1">SIPERU</small></span>
    </router-link>

    <nav class="nav flex-column gap-1" aria-label="Navigasi utama">
      <p class="text-white-50 small fw-bold text-uppercase px-3 mb-2">Menu utama</p>
      <button class="nav-link btn btn-link text-start text-white d-flex align-items-center gap-2 rounded-2" :class="{ 'bg-white bg-opacity-10': activeNav === 'beranda' }" @click="navigate('beranda')">Beranda</button>
      <button class="nav-link btn btn-link text-start text-white d-flex align-items-center gap-2 rounded-2" :class="{ 'bg-white bg-opacity-10': activeNav === 'permintaan' }" @click="navigate('permintaan')">Permintaan <span class="badge bg-light text-dark ms-auto">8</span></button>
      <button class="nav-link btn btn-link text-start text-white d-flex align-items-center gap-2 rounded-2" :class="{ 'bg-white bg-opacity-10': activeNav === 'ruangan' }" @click="navigate('ruangan')">Daftar ruangan</button>
      <button class="nav-link btn btn-link text-start text-white d-flex align-items-center gap-2 rounded-2" :class="{ 'bg-white bg-opacity-10': activeNav === 'laporan' }" @click="navigate('laporan')">Laporan</button>
      <p class="text-white-50 small fw-bold text-uppercase px-3 mt-4 mb-2">Manajemen</p>
      <button class="nav-link btn btn-link text-start text-white d-flex align-items-center gap-2 rounded-2" @click="navigate('pengguna')">Pengguna</button>
      <button class="nav-link btn btn-link text-start text-white d-flex align-items-center gap-2 rounded-2" @click="navigate('pengaturan')">Pengaturan</button>
    </nav>

    <div class="d-flex align-items-center gap-2 mt-auto border-top border-white border-opacity-10 pt-3">
      <span class="badge bg-primary rounded-circle fs-6 d-inline-grid justify-content-center align-items-center" >AS</span>
      <div class="d-flex flex-column"><strong class="fs-6">Andi Setiawan</strong><small class="text-white-50">Manajer Operasi</small></div>
      <button class="btn btn-link text-white-50 ms-auto p-0" aria-label="Keluar">↗</button>
    </div>
  </aside>
</template>
