<script>
import { defineComponent } from 'vue'
import { Building2, Check, Info, LockKeyhole, Mail, TrendingUp, X } from '@lucide/vue'
import { supabase } from '../utils/supabase'

export default defineComponent({
  name: 'LandingPage',
  components: {
    Building2,
    Check,
    Info,
    LockKeyhole,
    Mail,
    TrendingUp,
    X,
  },
  data() {
    return {
      email: '',
      password: '',
      error: '',
      isSubmitting: false,
      isAccessNoticeOpen: false,
    }
  },
  methods: {
    openAccessNotice() {
      this.isAccessNoticeOpen = true
      this.$nextTick(() => this.$refs.accessNoticeClose?.focus())
    },
    closeAccessNotice() {
      this.isAccessNoticeOpen = false
    },
    async handleLogin(event) {
      event.preventDefault()
      this.error = ''

      if (!this.email.trim() || !this.password.trim()) {
        this.error = 'Masukkan email dan kata sandi untuk melanjutkan.'
        return
      }

      if (!supabase) {
        this.error = 'Koneksi Supabase belum dikonfigurasi. Periksa environment aplikasi.'
        return
      }

      this.isSubmitting = true
      try {
        const { error } = await supabase.auth.signInWithPassword({
          email: this.email.trim(),
          password: this.password,
        })

        if (error) {
          this.error = error.message === 'Invalid login credentials'
            ? 'Email atau kata sandi salah.'
            : error.message
          return
        }

        await this.$router.replace({ name: 'dashboard' })
      } catch {
        this.error = 'Tidak dapat terhubung ke Supabase. Coba lagi sebentar.'
      } finally {
        this.isSubmitting = false
      }
    },
  },
})
</script>

<template>
  <main class="landing-page bg-light d-flex flex-column">
    <nav class="navbar navbar-expand-md navbar-light bg-white border-bottom py-3" aria-label="Navigasi utama">
      <div class="container">
        <router-link class="navbar-brand d-flex align-items-center gap-2 fw-bold text-dark" to="/">
          <span class="badge bg-primary rounded-3 fs-5 d-inline-grid justify-content-center align-items-center" >S</span>
          <span class="d-flex flex-column"><span>SIMPERU</span></span>
        </router-link>
        <div class="d-flex align-items-center gap-3 ms-auto">
          <a class="nav-link text-dark" href="#tentang">Tentang</a>
          <a class="btn btn-dark rounded-2 px-3" href="#login">Masuk</a>
        </div>
      </div>
    </nav>

    <section id="beranda" class="container py-5 py-lg-6">
      <div class="row g-5 align-items-center">
        <div class="col-lg-7">
          <span class="hero-kicker badge rounded-pill bg-primary-subtle text-primary fs-6 fw-semibold mb-3">Sistem Informasi Manajemen Peminjaman Ruangan</span>
          <h1 class="display-5 fw-black text-dark mb-3">Pengelolaan <span class="text-primary">ruangan</span><br />lebih mudah, dan terintegrasi.</h1>
          <p class="lead text-muted mb-4">
            SIMPERU mempermudah pengelolaan seluruh proses tata kelola peminjaman ruangan pada lingkungan perguruan tinggi.
          </p>
          <div class="d-flex flex-column flex-md-row gap-3">
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-primary-subtle text-primary rounded-2 p-2"><Check /></span>
              <div><strong class="d-block fs-6">Proses cepat</strong><small class="text-muted">Permintaan tetap terstruktur</small></div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-primary-subtle text-primary rounded-2 p-2"><TrendingUp /></span>
              <div><strong class="d-block fs-6">Visibilitas nyata</strong><small class="text-muted">Status ruangan dapat dilacak</small></div>
            </div>
          </div>
        </div>

        <div id="login" class="col-lg-5">
          <div class="card shadow-lg border-0 rounded-4 p-4 p-lg-5">
            <div class="d-flex align-items-center gap-3 mb-4">
              <span class="badge bg-dark rounded-3 fs-4 d-inline-grid justify-content-center align-items-center" ><Building2 /></span>
              <div>
                <p class="text-primary small fw-bold text-uppercase mb-1">Selamat datang</p>
                <h2 class="h4 fw-bold text-dark mb-0">Masuk ke sistem</h2>
              </div>
            </div>

            <form id="login-form" @submit.prevent="handleLogin">
              <div class="mb-3">
                <label class="form-label fw-semibold" for="email">Email akun</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><Mail /></span>
                  <input id="email" v-model="email" class="form-control border-start-0" type="email" autocomplete="email" placeholder="nama@kampus.edu" required />
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold" for="password">Kata sandi</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><LockKeyhole /></span>
                  <input id="password" v-model="password" class="form-control border-start-0" type="password" autocomplete="current-password" placeholder="Masukkan kata sandi" required />
                </div>
              </div>
              <p v-if="error" class="alert alert-danger mb-3" role="alert">{{ error }}</p>
              <button class="btn btn-primary w-100 rounded-2 py-3 fw-bold" type="submit" :disabled="isSubmitting">
                {{ isSubmitting ? 'Memuat sistem...' : 'Masuk ke dashboard' }}
              </button>
            </form>

            <div class="text-center mt-4 text-muted small">
              <span>Belum memiliki akun? </span>
              <a href="#akses" class="fw-bold" @click.prevent="openAccessNotice">Minta akses</a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div v-if="isAccessNoticeOpen" class="access-notice-backdrop" @click.self="closeAccessNotice">
      <section
        class="access-notice bg-white border shadow-lg rounded-3 p-4 p-md-5"
        role="dialog"
        aria-modal="true"
        aria-labelledby="access-notice-title"
        aria-describedby="access-notice-message"
        @keydown.esc.stop.prevent="closeAccessNotice"
      >
        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
          <span class="badge bg-primary-subtle text-primary rounded-2 p-2"><Info :size="20" aria-hidden="true" /></span>
          <button ref="accessNoticeClose" class="btn btn-light border rounded-2 p-2" type="button" aria-label="Tutup informasi" @click="closeAccessNotice"><X :size="18" aria-hidden="true" /></button>
        </div>
        <p class="text-primary small fw-bold text-uppercase mb-1">Informasi akses akun</p>
        <h2 id="access-notice-title" class="h5 fw-bold text-dark mb-2">Registrasi melalui pihak terkait</h2>
        <p id="access-notice-message" class="text-muted mb-4">
          Untuk sementara, silakan hubungi dosen atau kaprodi, maupun bagian Tata Usaha (TU), untuk meminta dibuatkan akses akun.
        </p>
        <button class="btn btn-primary rounded-2 px-4" type="button" @click="closeAccessNotice">Mengerti</button>
      </section>
    </div>

    <section id="tentang" class="bg-dark text-white py-5 mt-auto">
      <div class="container">
        <div class="row g-4 align-items-center">
          <div class="col-lg-4">
            <p class="mb-0 text-white-50">Dirancang untuk mendukung proses yang lebih tertib di lingkungan akademik.</p>
          </div>
          <div class="col-6 col-lg-2"><strong class="d-block fs-6">01 Permintaan</strong><small class="text-white-50">Pengajuan & persetujuan</small></div>
          <div class="col-6 col-lg-2"><strong class="d-block fs-6">02 Manajemen</strong><small class="text-white-50">Daftar & status ruangan</small></div>
          <div class="col-6 col-lg-2"><strong class="d-block fs-6">03 Pelaporan</strong><small class="text-white-50">Transaksi & kebutuhan</small></div>
        </div>
      </div>
      <div class="text-center text-white-50 small mt-5">© 2026 SIMPERU/SIPERU. Sistem akademik.</div>
    </section>
  </main>
</template>

<style scoped>
.landing-page {
  min-height: 100vh;
}

.hero-kicker {
  max-width: 100%;
  white-space: normal;
  line-height: 1.35;
  text-align: left;
}

.access-notice-backdrop {
  position: fixed;
  z-index: 1080;
  inset: 0;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: rgb(17 24 39 / 55%);
}

.access-notice {
  width: min(100%, 440px);
}
</style>
