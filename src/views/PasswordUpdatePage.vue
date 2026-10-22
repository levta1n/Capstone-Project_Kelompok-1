<script>
import { defineComponent } from 'vue'
import { supabase } from '../utils/supabase'

export default defineComponent({
  name: 'PasswordUpdatePage',
  data() {
    return {
      password: '',
      passwordConfirmation: '',
      error: '',
      isSaving: false,
      isReady: false,
    }
  },
  computed: {
    isRecovery() {
      return this.$route.name === 'reset-password'
    },
  },
  async mounted() {
    if (!supabase) {
      this.error = 'Koneksi Supabase belum dikonfigurasi.'
      return
    }
    const { data, error } = await supabase.auth.getSession()
    if (error) {
      this.error = `Sesi pemulihan tidak dapat dibaca: ${error.message}`
      return
    }
    if (!data.session) {
      this.error = 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru dari halaman masuk.'
      return
    }
    this.isReady = true
  },
  methods: {
    async updatePassword() {
      this.error = ''
      if (this.password.length < 8) {
        this.error = 'Kata sandi baru minimal 8 karakter.'
        return
      }
      if (this.password !== this.passwordConfirmation) {
        this.error = 'Konfirmasi kata sandi tidak sama.'
        return
      }
      if (!supabase) {
        this.error = 'Koneksi Supabase belum dikonfigurasi.'
        return
      }

      this.isSaving = true
      try {
        const { error } = await supabase.auth.updateUser({ password: this.password })
        if (error) throw error
        const { error: profileError } = await supabase.rpc('mark_password_changed')
        if (profileError) throw profileError
        await this.$router.replace({ name: 'dashboard' })
      } catch (error) {
        this.error = `Kata sandi gagal diperbarui: ${error.message}`
      } finally {
        this.isSaving = false
      }
    },
  },
})
</script>

<template>
  <main class="min-vh-100 bg-light d-grid align-items-center justify-content-center p-3">
    <section class="card border-0 shadow-sm rounded-4 p-4 p-md-5 w-100" style="max-width: 480px">
      <p class="text-primary small fw-bold text-uppercase mb-1">Keamanan akun</p>
      <h1 class="h4 fw-bold mb-2">{{ isRecovery ? 'Atur ulang kata sandi' : 'Ganti kata sandi awal' }}</h1>
      <p class="text-muted mb-4">
        {{ isRecovery ? 'Buat kata sandi baru untuk melanjutkan.' : 'Untuk keamanan, kata sandi awal wajib diganti sebelum menggunakan sistem.' }}
      </p>
      <form @submit.prevent="updatePassword">
        <div class="mb-3">
          <label class="form-label" for="new-password">Kata sandi baru</label>
          <input id="new-password" v-model="password" class="form-control" type="password" minlength="8" autocomplete="new-password" required />
        </div>
        <div class="mb-3">
          <label class="form-label" for="confirm-password">Konfirmasi kata sandi baru</label>
          <input id="confirm-password" v-model="passwordConfirmation" class="form-control" type="password" minlength="8" autocomplete="new-password" required />
        </div>
        <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
        <button class="btn btn-primary w-100" type="submit" :disabled="isSaving || !isReady">
          {{ isSaving ? 'Menyimpan...' : 'Simpan kata sandi' }}
        </button>
      </form>
      <router-link v-if="isRecovery && !isReady" class="btn btn-link mt-2" :to="{ name: 'landing' }">Kembali ke halaman masuk</router-link>
    </section>
  </main>
</template>
