# Sistem Manajemen Peminjaman Ruangan

Aplikasi web untuk mengelola permintaan peminjaman ruangan, mulai dari pengajuan oleh pemohon hingga peninjauan oleh admin.

## Teknologi

- Vue 3 dan Vite
- Supabase
- Cypress untuk pengujian end-to-end
- Vitest untuk pengujian unit

## Persiapan

Pastikan Node.js dan npm sudah terpasang, lalu jalankan:

```sh
npm install
```

Siapkan konfigurasi Supabase di file `.env` pada root proyek. Jangan commit file `.env` atau membagikan nilai rahasianya.

## Menjalankan aplikasi

Jalankan server pengembangan dengan hot reload:

```sh
npm run dev
```

## Pengujian

### Pengujian unit

```sh
npm run test:unit
```

### Pengujian end-to-end dengan Cypress

Untuk membuka Cypress Test Runner:

```sh
npm run test:e2e:dev
```

Untuk menjalankan seluruh spec dalam mode headless:

```sh
npm run test:e2e
```

Pengujian end-to-end memakai akun dan konfigurasi Supabase dari `.env`. Beberapa spec membuat atau mengubah data, jadi jalankan pengujian menggunakan database khusus testing.

#### Menjalankan pengujian di Chrome atau Firefox

Chrome atau Firefox disarankan untuk menjalankan pengujian browser. Pastikan browser yang dipilih sudah terpasang.

Jika server Vite sudah berjalan di `http://localhost:5173`, jalankan:

```sh
npx cypress run --e2e --browser chrome
```

Untuk Firefox:

```sh
npx cypress run --e2e --browser firefox
```

Untuk membuka Test Runner di browser tertentu, gunakan `open` sebagai pengganti `run`, misalnya:

```sh
npx cypress open --e2e --browser chrome
```

```sh
npx cypress open --e2e --browser firefox
```

Detail daftar spec, kredensial pengujian, dan dampak data dapat dilihat di [panduan Cypress](./cypress/README.md).

### Build produksi

```sh
npm run build
```

### Lint

```sh
npm run lint
```
