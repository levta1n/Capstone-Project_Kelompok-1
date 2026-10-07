<script>
import { defineComponent } from 'vue'
import {
  Building2,
  ChartNoAxesColumnIncreasing,
  ClipboardList,
  LayoutDashboard,
  LogOut,
  Settings,
  Users,
} from '@lucide/vue'

export default defineComponent({
  name: 'DashboardSidebar',
  props: {
    activeNav: {
      type: String,
      required: true,
    },
    mobileOpen: {
      type: Boolean,
      default: false,
    },
    userName: {
      type: String,
      default: 'Pengguna',
    },
    userInitials: {
      type: String,
      default: 'P',
    },
    userRole: {
      type: String,
      default: 'Pengguna',
    },
  },
  components: {
    Building2,
    ChartNoAxesColumnIncreasing,
    ClipboardList,
    LayoutDashboard,
    LogOut,
    Settings,
    Users,
  },
  emits: ['navigate', 'close', 'logout'],
  methods: {
    navigate(section) {
      this.$emit('navigate', section)
      this.$emit('close')
    },
  },
})
</script>

<template>
  <div class="dashboard-sidebar-shell">
    <button
      v-if="mobileOpen"
      class="dashboard-sidebar-backdrop"
      type="button"
      aria-label="Tutup menu"
      @click="$emit('close')"
    ></button>
    <aside
      class="dashboard-sidebar bg-dark text-white overflow-auto p-3 p-lg-4 d-flex flex-column"
      :class="{ 'is-open': mobileOpen }"
    >
    <router-link class="navbar-brand d-flex align-items-center gap-2 fw-bold text-white mb-4" to="/">
      <span class="badge bg-primary rounded-3 fs-5 d-inline-grid justify-content-center align-items-center" >S</span>
      <span class="d-flex flex-column"><span>SIMPERU</span></span>
    </router-link>

    <nav class="nav flex-column gap-1" aria-label="Navigasi utama">
      <p class="text-white-50 small fw-bold text-uppercase px-3 mb-2">Menu utama</p>
      <button class="nav-link btn btn-link text-start text-white d-flex align-items-center gap-2 rounded-2" :class="{ 'bg-white bg-opacity-10': activeNav === 'beranda' }" @click="navigate('beranda')"><LayoutDashboard :size="18" aria-hidden="true" />Beranda</button>
      <button class="nav-link btn btn-link text-start text-white d-flex align-items-center gap-2 rounded-2" :class="{ 'bg-white bg-opacity-10': activeNav === 'permintaan' }" @click="navigate('permintaan')"><ClipboardList :size="18" aria-hidden="true" />Permintaan <span class="badge bg-light text-dark ms-auto">8</span></button>
      <button class="nav-link btn btn-link text-start text-white d-flex align-items-center gap-2 rounded-2" :class="{ 'bg-white bg-opacity-10': activeNav === 'ruangan' }" @click="navigate('ruangan')"><Building2 :size="18" aria-hidden="true" />Daftar ruangan</button>
      <button class="nav-link btn btn-link text-start text-white d-flex align-items-center gap-2 rounded-2" :class="{ 'bg-white bg-opacity-10': activeNav === 'laporan' }" @click="navigate('laporan')"><ChartNoAxesColumnIncreasing :size="18" aria-hidden="true" />Laporan</button>
      <p class="text-white-50 small fw-bold text-uppercase px-3 mt-4 mb-2">Manajemen</p>
      <button class="nav-link btn btn-link text-start text-white d-flex align-items-center gap-2 rounded-2" @click="navigate('pengguna')"><Users :size="18" aria-hidden="true" />Pengguna</button>
      <button class="nav-link btn btn-link text-start text-white d-flex align-items-center gap-2 rounded-2" @click="navigate('pengaturan')"><Settings :size="18" aria-hidden="true" />Pengaturan</button>
    </nav>

    <div class="d-flex align-items-center gap-2 mt-auto border-top border-white border-opacity-10 pt-3">
      <span class="dashboard-user-avatar badge bg-primary rounded-circle fs-6 d-inline-grid justify-content-center align-items-center">{{ userInitials }}</span>
      <div class="d-flex flex-column"><strong class="fs-6">{{ userName }}</strong><small class="text-white-50">{{ userRole }}</small></div>
      <button class="btn btn-link text-white-50 ms-auto p-0 d-md-none" aria-label="Keluar" @click="$emit('logout')"><LogOut :size="18" aria-hidden="true" /></button>
    </div>
    </aside>
  </div>
</template>

<style scoped>
.dashboard-sidebar-shell {
  display: contents;
}

.dashboard-user-avatar {
  inline-size: 32px;
  block-size: 32px;
  flex: 0 0 32px;
  padding: 0;
}

.dashboard-sidebar {
  position: fixed;
  z-index: 1045;
  top: 0;
  bottom: 0;
  left: 0;
  width: min(280px, 85vw);
  min-height: 100vh;
  transform: translateX(-105%);
  visibility: hidden;
  transition: transform 180ms ease, visibility 180ms ease;
}

.dashboard-sidebar.is-open {
  transform: translateX(0);
  visibility: visible;
}

.dashboard-sidebar-backdrop {
  position: fixed;
  z-index: 1040;
  inset: 0;
  border: 0;
  background: rgb(0 0 0 / 45%);
}

@media (min-width: 768px) {
  .dashboard-sidebar {
    position: sticky;
    top: 0;
    height: 100vh;
    width: 240px;
    min-height: 100vh;
    flex: 0 0 240px;
    transform: none;
    visibility: visible;
  }

  .dashboard-sidebar-backdrop {
    display: none;
  }
}
</style>
