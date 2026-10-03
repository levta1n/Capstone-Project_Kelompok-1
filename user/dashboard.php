<?php

require '../config/auth.php';
require '../config/database.php';

require_login();

$title = 'Dashboard';

require '../config/header.php';

?>

<!-- =====================================================
     DASHBOARD HERO
     ===================================================== -->

<section class="modern-hero">

    <div>

        <span class="heading-label">
            SISTEM PEMINJAMAN RUANGAN
        </span>

        <h1>
            Selamat datang kembali,
            <?= htmlspecialchars($_SESSION['user']['nama'] ?? 'Mahasiswa') ?> 👋
        </h1>

        <p>
            Kelola peminjaman ruangan, cek jadwal, dan pantau pengajuanmu dengan mudah.
        </p>

    </div>

</section>


<!-- =====================================================
     MENU UTAMA
     ===================================================== -->

<section class="dashboard-menu-section">

    <div class="dashboard-menu-heading">

        <div>
            <span class="heading-label">
                MENU UTAMA
            </span>

            <h2>
                Apa yang ingin kamu lakukan?
            </h2>

            <p>
                Pilih menu untuk melanjutkan.
            </p>
        </div>

    </div>


    <div class="dashboard-menu-grid">


        <!-- =================================================
             AJUKAN PEMINJAMAN
             ================================================= -->

        <a href="booking.php" class="dashboard-menu-card">

            <div class="dashboard-menu-icon dashboard-menu-icon-green">
                <i class="bi bi-building-add"></i>
            </div>

            <div class="dashboard-menu-content">

                <h3>
                    Ajukan Peminjaman
                </h3>

                <p>
                    Cari ruangan yang sesuai dan ajukan peminjaman untuk kegiatanmu.
                </p>

            </div>

            <div class="dashboard-menu-arrow">
                <i class="bi bi-arrow-right"></i>
            </div>

        </a>


        <!-- =================================================
             JADWAL RUANGAN
             ================================================= -->

        <a href="schedule.php" class="dashboard-menu-card">

            <div class="dashboard-menu-icon dashboard-menu-icon-blue">
                <i class="bi bi-calendar3"></i>
            </div>

            <div class="dashboard-menu-content">

                <h3>
                    Jadwal Ruangan
                </h3>

                <p>
                    Lihat penggunaan ruangan berdasarkan tanggal dan waktu.
                </p>

            </div>

            <div class="dashboard-menu-arrow">
                <i class="bi bi-arrow-right"></i>
            </div>

        </a>


        <!-- =================================================
             PENGAJUAN SAYA
             ================================================= -->

        <a href="my_bookings.php" class="dashboard-menu-card">

            <div class="dashboard-menu-icon dashboard-menu-icon-yellow">
                <i class="bi bi-file-earmark-text"></i>
            </div>

            <div class="dashboard-menu-content">

                <h3>
                    Pengajuan Saya
                </h3>

                <p>
                    Pantau status dan riwayat peminjaman ruangan yang kamu ajukan.
                </p>

            </div>

            <div class="dashboard-menu-arrow">
                <i class="bi bi-arrow-right"></i>
            </div>

        </a>


    </div>

</section>


<?php require '../config/footer.php'; ?>