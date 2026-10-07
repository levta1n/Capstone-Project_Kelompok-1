<script>
import { defineComponent } from 'vue'
import { Bell, LogOut, Menu, Search } from '@lucide/vue'

export default defineComponent({
  name: 'DashboardHeader',
  components: {
    Bell,
    LogOut,
    Menu,
    Search,
  },
  props: {
    modelValue: {
      type: String,
      default: '',
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
  emits: ['update:modelValue', 'toggle-menu', 'logout'],
})
</script>

<template>
  <header class="navbar navbar-expand-sm navbar-light bg-white border-bottom py-3 position-sticky top-0 z-3">
    <div class="dashboard-header-content d-flex align-items-center gap-2 gap-md-3 w-100">
      <button class="dashboard-menu-button btn btn-link text-dark p-0 d-md-none" aria-label="Buka menu" @click="$emit('toggle-menu')"><Menu :size="20" /></button>
      <label class="dashboard-search input-group shadow-sm rounded-2">
        <span class="input-group-text bg-light border-0"><Search :size="18" aria-hidden="true" /></span>
        <input
          :value="modelValue"
          class="form-control border-0 bg-light"
          type="search"
          placeholder="Cari permintaan, pengguna, atau ruangan..."
          @input="$emit('update:modelValue', $event.target.value)"
        />
      </label>
      <div class="dashboard-header-actions d-flex align-items-center gap-2 gap-md-3">
        <button class="btn btn-light border rounded-2 position-relative d-none d-md-inline-flex" aria-label="Notifikasi"><Bell :size="18" aria-hidden="true" /><span class="badge bg-danger rounded-circle position-absolute top-0 end-0" aria-hidden="true"></span></button>
        <div class="dashboard-user-profile d-flex align-items-center gap-2"><span class="dashboard-user-avatar badge bg-primary rounded-circle fs-6 d-inline-grid justify-content-center align-items-center">{{ userInitials }}</span><div class="dashboard-user-info d-none d-lg-flex flex-column"><strong class="fs-6">{{ userName }}</strong><small class="text-muted">{{ userRole }}</small></div></div>
        <button class="dashboard-logout btn btn-light border rounded-2" aria-label="Keluar" title="Keluar" @click="$emit('logout')"><LogOut :size="18" aria-hidden="true" /></button>
      </div>
    </div>
  </header>
</template>

<style scoped>
.dashboard-header-content,
.dashboard-search,
.dashboard-search input {
  min-width: 0;
}

.dashboard-search {
  flex-grow: 1;
}

.dashboard-menu-button {
  flex-shrink: 0;
}

.dashboard-user-profile {
  flex-shrink: 0;
  white-space: nowrap;
}

.dashboard-header-actions,
.dashboard-logout {
  flex-shrink: 0;
}

.dashboard-user-avatar {
  inline-size: 32px;
  block-size: 32px;
  flex: 0 0 32px;
  padding: 0;
}
</style>
