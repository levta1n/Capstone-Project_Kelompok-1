<script>
import { defineComponent } from 'vue'
import { Building2, LogOut } from '@lucide/vue'

export default defineComponent({
  name: 'RequesterHeader',
  components: {
    Building2,
    LogOut,
  },
  props: {
    profile: {
      type: Object,
      default: null,
    },
  },
  emits: ['logout'],
  computed: {
    initials() {
      const name = this.profile?.full_name || 'Pengguna'
      return name.split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase()
    },
  },
})
</script>

<template>
  <header class="requester-header bg-white border-bottom sticky-top">
    <nav class="container d-flex align-items-center justify-content-between gap-3 py-3" aria-label="Navigasi pemohon">
      <router-link class="navbar-brand d-flex align-items-center gap-2 fw-bold text-dark mb-0" to="/my-requests">
        <span class="badge bg-primary rounded-3 d-inline-grid justify-content-center align-items-center requester-brand-mark"><Building2 :size="18" aria-hidden="true" /></span>
        <span>SIMPERU</span>
      </router-link>
      <div class="d-flex align-items-center gap-3">
        <nav class="d-none d-md-flex align-items-center gap-3" aria-label="Menu pemohon">
          <a class="link-secondary text-decoration-none" href="#ruangan-tersedia">Ruangan tersedia</a>
          <a class="link-secondary text-decoration-none" href="#requests-list">Permintaan saya</a>
        </nav>
        <div class="d-flex align-items-center gap-2 border-start ps-3">
          <span class="requester-avatar badge bg-primary rounded-circle d-inline-grid justify-content-center align-items-center">{{ initials }}</span>
          <span class="d-none d-sm-flex flex-column"><strong class="small text-dark">{{ profile?.full_name || 'Pengguna' }}</strong><small class="text-muted">{{ profile?.unit_name || 'Pemohon' }}</small></span>
          <button class="btn btn-light border rounded-2 ms-1" type="button" aria-label="Keluar" title="Keluar" @click="$emit('logout')"><LogOut :size="17" aria-hidden="true" /></button>
        </div>
      </div>
    </nav>
  </header>
</template>

<style scoped>
.requester-brand-mark {
  inline-size: 34px;
  block-size: 34px;
}

.requester-avatar {
  inline-size: 34px;
  block-size: 34px;
  flex: 0 0 34px;
  padding: 0;
}
</style>