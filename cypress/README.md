# Pengujian end-to-end Cypress

Spec pengujian dipisahkan berdasarkan alur pengguna di folder `cypress/e2e/`:

- `authentication.cy.js`: membuka halaman utama dan memeriksa pengalihan login berdasarkan peran.
- `requester/create-request.cy.js`: membuat permintaan peminjaman dan memeriksa status Menunggu.
- `requester/cancel-request.cy.js`: membuat lalu membatalkan permintaan.
- `admin/approve-request.cy.js`: pemohon membuat permintaan, admin menyetujuinya, lalu pemohon membatalkan peminjaman.
- `admin/reject-request.cy.js`: pemohon membuat permintaan, lalu admin menolaknya dengan alasan.
- `admin/room-management.cy.js`: admin menambah, mengubah, dan menonaktifkan ruangan.

Aplikasi tidak menyediakan fitur untuk menghapus ruangan secara permanen; spec manajemen ruangan menguji penonaktifan ruangan sehingga ruangan tidak lagi tersedia untuk peminjaman. Spec permintaan membatalkan permintaan setelah statusnya diverifikasi. Namun, data permintaan yang dibatalkan atau ditolak serta ruangan yang dibuat saat pengujian akan tetap tersimpan di Supabase. Gunakan akun pengujian dan proyek Supabase khusus testing, bukan data produksi.

## Kredensial dan konfigurasi

Letakkan kredensial pengujian di file `.env` pada root proyek (sejajar dengan `package.json`), atau berikan melalui environment variable shell. Konfigurasi Cypress memuat `.env` saat dijalankan. Jangan commit file tersebut.

Gunakan nama variabel berikut:

```sh
CYPRESS_ADMIN_EMAIL=...
CYPRESS_ADMIN_PASSWORD=...
CYPRESS_REQUESTER_EMAIL=...
CYPRESS_REQUESTER_PASSWORD=...
```

Kredensial login dibaca melalui Cypress `cy.env()` dan tidak ditampilkan di command log. Konfigurasi Supabase aplikasi juga harus tersedia di `.env`.

## Menjalankan pengujian

Untuk menjalankan seluruh spec dalam mode headless, gunakan:

```sh
npm run test:e2e
```

Perintah tersebut menjalankan server Vite pada port 5173, lalu menjalankan Cypress. Untuk membuka Cypress Test Runner, gunakan:

```sh
npm run test:e2e:dev
```

Jika server Vite sudah berjalan, jalankan Cypress secara langsung:

```sh
npx cypress run --e2e
```

Untuk menjalankan pengujian di Chrome atau Firefox, pastikan browser terkait telah terpasang, lalu tambahkan opsi `--browser chrome` atau `--browser firefox`. Contoh:

```sh
npx cypress run --e2e --browser chrome
```

```sh
npx cypress run --e2e --browser firefox
```

## Artefak hasil pengujian

Saat spec berjalan, Cypress menyimpan tangkapan layar setiap kasus pengujian. Video direkam untuk setiap spec saat menjalankan `cypress run`. Setelah `cypress run` selesai, ringkasan hasil dibuat dalam format HTML dan JSON. Semua artefak berada di `cypress/artifacts/` dan dikelompokkan berdasarkan fungsionalitas:

```text
cypress/artifacts/
├── media/
│   ├── screenshots/
│   │   ├── admin/<nama-spec>/<nama-kasus>.png
│   │   ├── requester/<nama-spec>/<nama-kasus>.png
│   │   └── authentication/<nama-spec>/<nama-kasus>.png
│   └── videos/
│       └── ... video per spec ...
└── reports/
    ├── admin/<nama-spec>.html
    ├── admin/<nama-spec>.json
    ├── requester/<nama-spec>.html
    ├── requester/<nama-spec>.json
    └── authentication/...
```

Video direkam saat menjalankan `cypress run`; mode Test Runner interaktif tidak menghasilkan video. Folder `cypress/artifacts/` diabaikan Git karena berisi hasil pengujian yang dibuat ulang setiap kali dijalankan.
