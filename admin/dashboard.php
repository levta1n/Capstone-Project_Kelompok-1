<?php

require '../config/auth.php';
require '../config/database.php';

require_admin();


/* =====================================================
   HITUNG PENGAJUAN YANG MENUNGGU
   ===================================================== */

$stmtWaiting = $pdo->query("
    SELECT COUNT(*)
    FROM bookings
    WHERE status = 'menunggu'
");

$waitingBookings = (int) $stmtWaiting->fetchColumn();


$title = 'Dashboard Admin';

require '../config/header.php';
?>


<style>

/* =====================================================
   BADGE PENGAJUAN MENUNGGU
   ===================================================== */

.dashboard-menu-card {
    overflow: hidden;
}

.dashboard-menu-badge {
    position: absolute;
    top: 14px;
    right: 14px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 9px;
    border-radius: 20px;
    background: var(--yellow-light);
    color: var(--yellow);
    font-size: 10.5px;
    font-weight: 700;
    line-height: 1;
    white-space: nowrap;
}

.dashboard-menu-badge i {
    font-size: 10px;
}

.dashboard-menu-card .dashboard-menu-content {
    padding-right: 25px;
}

.dashboard-menu-card:has(.dashboard-menu-badge) .dashboard-menu-content {
    padding-right: 65px;
}

@media (max-width: 480px) {

    .dashboard-menu-badge {
        top: 11px;
        right: 11px;
        padding: 4px 8px;
        font-size: 10px;
    }

    .dashboard-menu-card:has(.dashboard-menu-badge) .dashboard-menu-content {
        padding-right: 55px;
    }

}

</style>


<section class="modern-hero">

    <div>

        <span class="heading-label">
            SISTEM PEMINJAMAN RUANGAN
        </span>

        <h1>
            Selamat datang kembali, <?= htmlspecialchars($_SESSION['user']['nama'] ?? 'Admin') ?> 👋
        </h1>

        <p>
            Kelola ruangan, proses persetujuan peminjaman, dan lihat laporan dengan mudah.
        </p>

    </div>

</section>


<section class="dashboard-menu-section">

    <div class="dashboard-menu-heading">

        <span class="heading-label">
            MENU ADMIN
        </span>

        <h2>
            Kelola Sistem
        </h2>

        <p>
            Pilih menu yang ingin kamu kelola.
        </p>

    </div>


    <div class="dashboard-menu-grid">


        <!-- =================================================
             EDIT RUANGAN
             ================================================= -->

        <a href="rooms.php" class="dashboard-menu-card">

            <div class="dashboard-menu-icon dashboard-menu-icon-green">
                <i class="bi bi-building"></i>
            </div>

            <div class="dashboard-menu-content">

                <h3>
                    Edit Ruangan
                </h3>

                <p>
                    Kelola data, kapasitas, fasilitas, lokasi, dan foto ruangan.
                </p>

            </div>

            <div class="dashboard-menu-arrow">
                <i class="bi bi-arrow-right"></i>
            </div>

        </a>


        <!-- =================================================
             APPROVAL PEMINJAMAN
             ================================================= -->

        <a href="bookings.php" class="dashboard-menu-card">

            <?php if ($waitingBookings > 0): ?>

                <div class="dashboard-menu-badge">
                    <i class="bi bi-clock"></i>
                    <?= $waitingBookings ?> Menunggu
                </div>

            <?php endif; ?>


            <div class="dashboard-menu-icon dashboard-menu-icon-blue">
                <i class="bi bi-check2-square"></i>
            </div>

            <div class="dashboard-menu-content">

                <h3>
                    Approval Peminjaman
                </h3>

                <p>
                    Periksa, setujui, atau tolak pengajuan peminjaman ruangan.
                </p>

            </div>

            <div class="dashboard-menu-arrow">
                <i class="bi bi-arrow-right"></i>
            </div>

        </a>


        <!-- =================================================
             LAPORAN
             ================================================= -->

        <a href="reports.php" class="dashboard-menu-card">

            <div class="dashboard-menu-icon dashboard-menu-icon-yellow">
                <i class="bi bi-bar-chart"></i>
            </div>

            <div class="dashboard-menu-content">

                <h3>
                    Laporan
                </h3>

                <p>
                    Lihat rekap dan riwayat penggunaan ruangan.
                </p>

            </div>

            <div class="dashboard-menu-arrow">
                <i class="bi bi-arrow-right"></i>
            </div>

        </a>


    </div>

</section>


<?php require '../config/footer.php'; ?>