# Catatan Integrasi Supabase

Untuk menghubungkan sistem dengan Supabase, ganti data statis pada dashboard dengan pemanggilan API di method `loadDashboardData()` dan gunakan autentikasi Supabase pada `handleLogin()`.

Contoh alur:

1. Simpan credential Supabase di environment (`VITE_SUPABASE_URL`, `VITE_SUPABASE_ANON_KEY`).
2. Buat klien Supabase dan autentikasi email serta kata sandi.
3. Ambil data permintaan, ruangan, dan statistik dari tabel Supabase.
4. Ganti status `data` dengan hasil API dan gunakan `onMounted()` untuk memuat awal.

Data pada halaman ini hanya contoh untuk tampilan awal dan dapat diganti tanpa mengubah struktur komponen.
