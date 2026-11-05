<template>
  <main class="min-vh-100 bg-light d-grid align-items-center justify-content-center p-3">
    <section class="card border-0 shadow-sm rounded-4 p-4 p-md-5 text-center w-100" style="max-width: 520px">
      <p class="text-primary fw-bold text-uppercase mb-2">Halaman tidak ditemukan</p>
      <h1 class="display-1 fw-bold text-primary mb-2">404</h1>
      <p class="text-muted mb-4">
        Alamat yang kamu buka tidak tersedia atau sudah dipindahkan.
      </p>
      <div>
        <router-link class="btn btn-primary" :to="{ name: 'landing' }">
          Kembali ke halaman utama
        </router-link>
      </div>
    </section>
  </main>
</template>
