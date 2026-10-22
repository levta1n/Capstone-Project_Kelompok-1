<script>
import { defineComponent } from 'vue'
import { Plus, RefreshCw, Users } from '@lucide/vue'

function emptyForm() {
  return { fullName: '', nim: '', dateOfBirth: '', unitName: '' }
}

export default defineComponent({
  name: 'UserManagementPanel',
  components: { Plus, RefreshCw, Users },
  props: {
    users: { type: Array, default: () => [] },
    isLoading: { type: Boolean, default: false },
    isSaving: { type: Boolean, default: false },
    updatingUserId: { type: String, default: '' },
    error: { type: String, default: '' },
    success: { type: String, default: '' },
  },
  emits: ['reload', 'create-user', 'manage-user'],
  data() {
    return { form: emptyForm() }
  },
  watch: {
    success(message) {
      if (message) this.form = emptyForm()
    },
  },
  methods: {
    submit() {
      this.$emit('create-user', { ...this.form })
    },
    formatCreatedAt(value) {
      return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value))
    },
    emailForNim(nim) {
      return nim ? `${nim}@ecampus.ut.ac.id` : ''
    },
    requestUserAction(user, action) {
      const actionText = action === 'delete'
        ? `Arsipkan akun ${user.full_name}? Akun tidak bisa login lagi, tetapi profil dan riwayat peminjaman tetap disimpan.`
        : `Suspend akun ${user.full_name}? Pengguna tidak bisa login sampai akun diaktifkan kembali.`
      if (action !== 'reactivate' && !window.confirm(actionText)) return
      this.$emit('manage-user', { userId: user.id, action })
    },
    statusLabel(user) {
      if (user.deleted_at) return 'Diarsipkan'
      return user.is_active ? 'Aktif' : 'Suspend'
    },
  },
})
</script>

<template>
  <section id="pengguna" class="user-management">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
      <div>
        <p class="text-primary small fw-bold text-uppercase mb-1">Administrasi</p>
        <h1 class="h3 fw-bold text-dark mb-1">Manajemen Pengguna</h1>
        <p class="text-muted mb-0">Buat akun requester, suspend akses, atau arsipkan akun tanpa menghapus riwayat peminjaman.</p>
      </div>
      <button class="btn btn-outline-secondary rounded-2 d-inline-flex align-items-center gap-2" type="button" :disabled="isLoading" @click="$emit('reload')"><RefreshCw :size="16" aria-hidden="true" />Muat ulang</button>
    </div>

    <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
    <p v-if="success" class="alert alert-success" role="status">{{ success }}</p>

    <section class="bg-white border rounded-3 p-3 p-md-4 mb-4" aria-labelledby="create-user-title">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge bg-primary-subtle text-primary rounded-2 p-2"><Plus :size="18" aria-hidden="true" /></span>
        <h2 id="create-user-title" class="h5 fw-bold mb-0">Buat akun baru</h2>
      </div>
      <form class="row g-3" @submit.prevent="submit">
        <div class="col-md-6"><label class="form-label" for="new-user-name">Nama lengkap</label><input id="new-user-name" v-model.trim="form.fullName" class="form-control" maxlength="120" autocomplete="name" required /></div>
        <div class="col-md-4"><label class="form-label" for="new-user-nim">NIM</label><input id="new-user-nim" v-model.trim="form.nim" class="form-control" inputmode="numeric" pattern="[0-9]+" maxlength="32" autocomplete="off" required /></div>
        <div class="col-md-4"><label class="form-label" for="new-user-dob">Tanggal lahir</label><input id="new-user-dob" v-model="form.dateOfBirth" class="form-control" type="date" :max="new Date().toISOString().slice(0, 10)" required /></div>
        <div class="col-md-4"><label class="form-label" for="new-user-unit">Unit / Fakultas</label><input id="new-user-unit" v-model.trim="form.unitName" class="form-control" maxlength="120" placeholder="Opsional" /></div>
        <div class="col-12">
          <p class="small text-muted mb-0">
            Email akun: <strong>{{ emailForNim(form.nim) || 'NIM@ecampus.ut.ac.id' }}</strong>.
            Password awal dibuat sebagai NIM + tanggal lahir (ddmmyyyy); pengguna wajib menggantinya saat login pertama.
          </p>
        </div>
        <div class="col-12"><button class="btn btn-primary rounded-2 d-inline-flex align-items-center gap-2" type="submit" :disabled="isSaving"><Plus :size="16" aria-hidden="true" />{{ isSaving ? 'Membuat akun...' : 'Buat akun' }}</button></div>
      </form>
    </section>

    <section class="bg-white border rounded-3" aria-labelledby="user-list-title">
      <div class="d-flex align-items-center justify-content-between gap-3 p-3 p-md-4 border-bottom">
        <div class="d-flex align-items-center gap-2"><Users class="text-primary" :size="19" aria-hidden="true" /><h2 id="user-list-title" class="h5 fw-bold mb-0">Akun terdaftar</h2></div>
        <span class="badge bg-light text-dark">{{ users.length }}</span>
      </div>
      <div v-if="isLoading" class="text-muted p-4" role="status">Memuat pengguna...</div>
      <div v-else-if="users.length" class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr><th>Nama</th><th>NIM</th><th>Email</th><th>Unit</th><th>Status</th><th>Dibuat</th><th>Aksi</th></tr></thead>
          <tbody>
            <tr v-for="user in users" :key="user.id">
              <td class="fw-semibold">{{ user.full_name }}</td>
              <td>{{ user.nim || '—' }}</td>
              <td>{{ user.email || '—' }}</td>
              <td>{{ user.unit_name || '—' }}</td>
              <td>
                <span
                  class="badge"
                  :class="user.deleted_at ? 'bg-secondary-subtle text-secondary' : user.is_active ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'"
                >{{ statusLabel(user) }}</span>
              </td>
              <td class="text-nowrap">{{ formatCreatedAt(user.created_at) }}</td>
              <td>
                <div v-if="user.role === 'requester' && !user.deleted_at" class="d-flex gap-2">
                  <button
                    v-if="user.is_active"
                    class="btn btn-sm btn-outline-warning text-nowrap"
                    type="button"
                    :disabled="updatingUserId === user.id"
                    @click="requestUserAction(user, 'suspend')"
                  >{{ updatingUserId === user.id ? 'Memproses...' : 'Suspend' }}</button>
                  <button
                    v-else
                    class="btn btn-sm btn-outline-success text-nowrap"
                    type="button"
                    :disabled="updatingUserId === user.id"
                    @click="requestUserAction(user, 'reactivate')"
                  >{{ updatingUserId === user.id ? 'Memproses...' : 'Aktifkan' }}</button>
                  <button
                    class="btn btn-sm btn-outline-danger text-nowrap"
                    type="button"
                    :disabled="updatingUserId === user.id"
                    @click="requestUserAction(user, 'delete')"
                  >Hapus (arsip)</button>
                </div>
                <span v-else-if="user.role === 'admin'" class="text-muted small">Akun admin</span>
                <span v-else class="text-muted small">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="text-muted text-center p-4 mb-0">Belum ada profil pengguna.</p>
    </section>
  </section>
</template>
