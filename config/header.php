<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =====================================================
   NOTIFIKASI
   ===================================================== */

$pendingBookings = 0;
$userNotifications = 0;

$adminNotificationList = [];
$userNotificationList = [];


if (!empty($_SESSION['user'])) {

    require_once __DIR__ . '/database.php';


    /* =================================================
       NOTIFIKASI ADMIN
       ================================================= */

    if ($_SESSION['user']['role'] === 'admin') {

        /* JUMLAH PENGAJUAN MENUNGGU */

        $stmtPending = $pdo->query("
            SELECT COUNT(*)
            FROM bookings
            WHERE status = 'menunggu'
        ");

        $pendingBookings = (int) $stmtPending->fetchColumn();


        /* DAFTAR PENGAJUAN TERBARU */

        $stmtAdminNotifications = $pdo->query("
            SELECT
                b.id,
                b.tanggal,
                b.jam_mulai,
                b.jam_selesai,
                b.keperluan,
                r.nama_ruangan,
                r.lokasi
            FROM bookings b
            INNER JOIN rooms r
                ON r.id = b.room_id
            WHERE b.status = 'menunggu'
            ORDER BY
                b.tanggal ASC,
                b.jam_mulai ASC,
                b.id DESC
            LIMIT 5
        ");

        $adminNotificationList =
            $stmtAdminNotifications->fetchAll();
    }


    /* =================================================
       NOTIFIKASI USER
       ================================================= */

    if ($_SESSION['user']['role'] === 'user') {

        $userId = (int) ($_SESSION['user']['id'] ?? 0);


        if ($userId > 0) {

            /* JUMLAH NOTIFIKASI */

            $stmtUserNotification = $pdo->prepare("
                SELECT COUNT(*)
                FROM bookings
                WHERE user_id = ?
                AND status IN ('disetujui', 'ditolak')
            ");

            $stmtUserNotification->execute([
                $userId
            ]);

            $userNotifications =
                (int) $stmtUserNotification->fetchColumn();


            /* DAFTAR NOTIFIKASI TERBARU */

            $stmtUserNotificationList = $pdo->prepare("
                SELECT
                    b.id,
                    b.tanggal,
                    b.jam_mulai,
                    b.jam_selesai,
                    b.keperluan,
                    b.status,
                    r.nama_ruangan,
                    r.lokasi
                FROM bookings b
                INNER JOIN rooms r
                    ON r.id = b.room_id
                WHERE b.user_id = ?
                AND b.status IN ('disetujui', 'ditolak')
                ORDER BY b.id DESC
                LIMIT 5
            ");

            $stmtUserNotificationList->execute([
                $userId
            ]);

            $userNotificationList =
                $stmtUserNotificationList->fetchAll();
        }
    }
}

?>

<!doctype html>

<html lang="id">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= htmlspecialchars($title ?? 'SIPERU') ?>
    </title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- SIPERU CSS -->

    <link
        rel="stylesheet"
        href="/peminjaman_ruangan_php/assets/style.css?v=5"
    >


    <style>

        /* =================================================
           NOTIFIKASI NAVBAR
           ================================================= */

        .navbar-notification-wrapper {
            position: relative;
        }


        .navbar-notification {

            position: relative;

            width: 38px;
            height: 38px;

            flex: 0 0 38px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 10px;

            color: var(--text-soft);

            background: transparent;

            text-decoration: none;

            transition:
                background .2s ease,
                color .2s ease;

            border: 0;

            cursor: pointer;

            padding: 0;

        }


        .navbar-notification:hover,
        .navbar-notification.active {

            background: var(--green-light);

            color: var(--green);

        }


        .navbar-notification > i {

            font-size: 18px;

        }


        .navbar-notification-badge {

            position: absolute;

            top: 2px;
            right: 1px;

            min-width: 17px;
            height: 17px;

            padding: 0 4px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 20px;

            background: var(--red);

            color: var(--white);

            border: 2px solid var(--white);

            font-size: 9px;

            font-weight: 700;

            line-height: 1;

        }


        /* =================================================
           DROPDOWN NOTIFIKASI
           ================================================= */

        .notification-dropdown {

            position: absolute;

            top: calc(100% + 10px);

            right: 0;

            width: 350px;

            background: var(--white);

            border: 1px solid var(--border);

            border-radius: 15px;

            box-shadow:
                0 12px 35px rgba(20, 40, 28, .12);

            z-index: 9999;

            overflow: hidden;

            display: none;

        }


        .notification-dropdown.show {

            display: block;

            animation: notificationDropdownIn .16s ease;

        }


        @keyframes notificationDropdownIn {

            from {

                opacity: 0;

                transform: translateY(-5px);

            }

            to {

                opacity: 1;

                transform: translateY(0);

            }

        }


        /* =================================================
           HEADER DROPDOWN
           ================================================= */

        .notification-dropdown-header {

            display: flex;

            align-items: center;
            justify-content: space-between;

            gap: 10px;

            padding: 15px 16px;

            border-bottom: 1px solid var(--border);

        }


        .notification-dropdown-title {

            display: flex;

            align-items: center;

            gap: 9px;

            min-width: 0;

        }


        .notification-dropdown-title-icon {

            width: 34px;
            height: 34px;

            flex: 0 0 34px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 9px;

            background: var(--green-light);

            color: var(--green);

            font-size: 15px;

        }


        .notification-dropdown-title strong {

            display: block;

            margin-bottom: 1px;

            color: var(--text);

            font-size: 13px;

        }


        .notification-dropdown-title span {

            display: block;

            color: var(--muted);

            font-size: 10px;

        }


        .notification-dropdown-count {

            flex: 0 0 auto;

            color: var(--green);

            font-size: 10px;

            font-weight: 700;

        }


        /* =================================================
           LIST NOTIFIKASI
           ================================================= */

        .notification-list {

            max-height: 360px;

            overflow-y: auto;

        }


        .notification-item {

            display: flex;

            align-items: flex-start;

            gap: 11px;

            padding: 13px 15px;

            border-bottom: 1px solid #eef2ef;

            color: var(--text);

            text-decoration: none;

            transition: background .15s ease;

        }


        .notification-item:last-child {

            border-bottom: 0;

        }


        .notification-item:hover {

            background: #f8fbf9;

            color: var(--text);

        }


        .notification-item-icon {

            width: 36px;
            height: 36px;

            flex: 0 0 36px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 10px;

            font-size: 15px;

        }


        .notification-icon-pending {

            background: var(--yellow-light);

            color: var(--yellow);

        }


        .notification-icon-approved {

            background: var(--green-light);

            color: var(--green);

        }


        .notification-icon-rejected {

            background: var(--red-light);

            color: var(--red);

        }


        .notification-item-content {

            min-width: 0;

            flex: 1;

        }


        .notification-item-title {

            display: block;

            margin-bottom: 3px;

            color: var(--text);

            font-size: 12px;

            font-weight: 700;

            line-height: 1.4;

        }


        .notification-item-description {

            display: block;

            margin-bottom: 4px;

            color: var(--text-soft);

            font-size: 11px;

            line-height: 1.4;

        }


        .notification-item-meta {

            display: flex;

            align-items: center;

            gap: 7px;

            color: var(--muted);

            font-size: 9.5px;

        }


        .notification-item-arrow {

            align-self: center;

            color: #a5aea8;

            font-size: 11px;

        }


        /* =================================================
           NOTIFIKASI KOSONG
           ================================================= */

        .notification-empty {

            padding: 30px 20px;

            text-align: center;

        }


        .notification-empty-icon {

            width: 44px;
            height: 44px;

            margin: 0 auto 10px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 12px;

            background: #f4f7f5;

            color: var(--muted);

            font-size: 19px;

        }


        .notification-empty strong {

            display: block;

            margin-bottom: 3px;

            color: var(--text);

            font-size: 12px;

        }


        .notification-empty span {

            color: var(--muted);

            font-size: 10px;

        }


        /* =================================================
           FOOTER DROPDOWN
           ================================================= */

        .notification-dropdown-footer {

            padding: 10px 14px;

            border-top: 1px solid var(--border);

            background: #fafcfb;

        }


        .notification-view-all {

            width: 100%;

            display: flex;

            align-items: center;
            justify-content: center;

            gap: 6px;

            padding: 8px;

            border-radius: 8px;

            background: var(--green-light);

            color: var(--green);

            text-decoration: none;

            font-size: 11px;

            font-weight: 700;

            transition: .2s ease;

        }


        .notification-view-all:hover {

            background: var(--green);

            color: var(--white);

        }


        /* =================================================
           RESPONSIVE
           ================================================= */

        @media (max-width: 768px) {

            .navbar-notification {

                width: 35px;

                height: 35px;

                flex-basis: 35px;

            }


            .navbar-notification > i {

                font-size: 17px;

            }


            .notification-dropdown {

                position: fixed;

                top: 68px;

                right: 15px;

                left: 15px;

                width: auto;

                max-width: none;

            }

        }

    </style>

</head>


<body>


<!-- =========================
     NAVBAR
     ========================= -->

<nav class="siperu-navbar">

    <div class="container navbar-inner">


        <!-- LOGO -->

        <a
            href="/peminjaman_ruangan_php/index.php"
            class="siperu-logo"
        >

            <span class="logo-icon">

                <i class="bi bi-building"></i>

            </span>


            <span>

                <strong>
                    SIPERU
                </strong>

                <small>
                    Sistem Peminjaman Ruangan
                </small>

            </span>

        </a>


        <!-- MENU -->

        <?php if (!empty($_SESSION['user'])): ?>

            <div class="navbar-menu">


                <!-- DASHBOARD -->

                <a
                    href="<?= $_SESSION['user']['role'] === 'admin'
                        ? '/peminjaman_ruangan_php/admin/dashboard.php'
                        : '/peminjaman_ruangan_php/user/dashboard.php' ?>"
                    class="navbar-link"
                >

                    <i class="bi bi-grid"></i>

                    Dashboard

                </a>


                <!-- =========================
                     MENU ADMIN
                     ========================= -->

                <?php if ($_SESSION['user']['role'] === 'admin'): ?>


                    <!-- RUANGAN -->

                    <a
                        href="/peminjaman_ruangan_php/admin/rooms.php"
                        class="navbar-link"
                    >

                        <i class="bi bi-door-open"></i>

                        Ruangan

                    </a>


                    <!-- NOTIFIKASI ADMIN -->

                    <div class="navbar-notification-wrapper">

                        <button
                            type="button"
                            class="navbar-notification"
                            id="notificationButton"
                            title="Pengajuan Peminjaman"
                            aria-label="Pengajuan Peminjaman"
                            aria-expanded="false"
                        >

                            <i class="bi bi-bell"></i>


                            <?php if ($pendingBookings > 0): ?>

                                <span class="navbar-notification-badge">

                                    <?= $pendingBookings > 99
                                        ? '99+'
                                        : $pendingBookings ?>

                                </span>

                            <?php endif; ?>

                        </button>


                        <!-- DROPDOWN ADMIN -->

                        <div
                            class="notification-dropdown"
                            id="notificationDropdown"
                        >

                            <div class="notification-dropdown-header">

                                <div class="notification-dropdown-title">

                                    <div class="notification-dropdown-title-icon">

                                        <i class="bi bi-bell"></i>

                                    </div>


                                    <div>

                                        <strong>
                                            Pengajuan Peminjaman
                                        </strong>

                                        <span>
                                            Menunggu persetujuan admin
                                        </span>

                                    </div>

                                </div>


                                <?php if ($pendingBookings > 0): ?>

                                    <span class="notification-dropdown-count">

                                        <?= $pendingBookings ?>

                                        menunggu

                                    </span>

                                <?php endif; ?>

                            </div>


                            <div class="notification-list">


                                <?php if (!empty($adminNotificationList)): ?>


                                    <?php foreach ($adminNotificationList as $notification): ?>

                                        <a
                                            href="/peminjaman_ruangan_php/admin/booking_detail.php?id=<?= (int) $notification['id'] ?>"
                                            class="notification-item"
                                        >

                                            <div class="notification-item-icon notification-icon-pending">

                                                <i class="bi bi-calendar-event"></i>

                                            </div>


                                            <div class="notification-item-content">

                                                <span class="notification-item-title">

                                                    Pengajuan baru

                                                </span>


                                                <span class="notification-item-description">

                                                    <?= htmlspecialchars(
                                                        $notification['nama_ruangan']
                                                    ) ?>

                                                </span>


                                                <span class="notification-item-meta">

                                                    <span>

                                                        <i class="bi bi-calendar3"></i>

                                                        <?= date(
                                                            'd/m/Y',
                                                            strtotime(
                                                                $notification['tanggal']
                                                            )
                                                        ) ?>

                                                    </span>


                                                    <span>
                                                        •
                                                    </span>


                                                    <span>

                                                        <?= substr(
                                                            $notification['jam_mulai'],
                                                            0,
                                                            5
                                                        ) ?>

                                                        -

                                                        <?= substr(
                                                            $notification['jam_selesai'],
                                                            0,
                                                            5
                                                        ) ?>

                                                    </span>

                                                </span>

                                            </div>


                                            <div class="notification-item-arrow">

                                                <i class="bi bi-chevron-right"></i>

                                            </div>

                                        </a>

                                    <?php endforeach; ?>


                                <?php else: ?>


                                    <div class="notification-empty">

                                        <div class="notification-empty-icon">

                                            <i class="bi bi-check2-circle"></i>

                                        </div>


                                        <strong>
                                            Tidak ada pengajuan baru
                                        </strong>


                                        <span>
                                            Semua pengajuan sudah diproses.
                                        </span>

                                    </div>


                                <?php endif; ?>


                            </div>


                            <div class="notification-dropdown-footer">

                                <a
                                    href="/peminjaman_ruangan_php/admin/bookings.php"
                                    class="notification-view-all"
                                >

                                    Lihat Semua Pengajuan

                                    <i class="bi bi-arrow-right"></i>

                                </a>

                            </div>

                        </div>

                    </div>


                <?php endif; ?>


                <!-- =========================
                     NOTIFIKASI USER
                     ========================= -->

                <?php if ($_SESSION['user']['role'] === 'user'): ?>


                    <div class="navbar-notification-wrapper">

                        <button
                            type="button"
                            class="navbar-notification"
                            id="notificationButton"
                            title="Notifikasi"
                            aria-label="Notifikasi"
                            aria-expanded="false"
                        >

                            <i class="bi bi-bell"></i>


                            <?php if ($userNotifications > 0): ?>

                                <span class="navbar-notification-badge">

                                    <?= $userNotifications > 99
                                        ? '99+'
                                        : $userNotifications ?>

                                </span>

                            <?php endif; ?>

                        </button>


                        <!-- DROPDOWN USER -->

                        <div
                            class="notification-dropdown"
                            id="notificationDropdown"
                        >

                            <div class="notification-dropdown-header">

                                <div class="notification-dropdown-title">

                                    <div class="notification-dropdown-title-icon">

                                        <i class="bi bi-bell"></i>

                                    </div>


                                    <div>

                                        <strong>
                                            Notifikasi
                                        </strong>

                                        <span>
                                            Informasi peminjaman Anda
                                        </span>

                                    </div>

                                </div>


                                <?php if ($userNotifications > 0): ?>

                                    <span class="notification-dropdown-count">

                                        <?= $userNotifications ?>

                                        baru

                                    </span>

                                <?php endif; ?>

                            </div>


                            <div class="notification-list">


                                <?php if (!empty($userNotificationList)): ?>


                                    <?php foreach ($userNotificationList as $notification): ?>


                                        <?php

                                        $isApproved =
                                            $notification['status'] === 'disetujui';

                                        ?>


                                        <a
                                            href="/peminjaman_ruangan_php/user/booking_detail.php?id=<?= (int) $notification['id'] ?>"
                                            class="notification-item"
                                        >

                                            <div
                                                class="notification-item-icon <?= $isApproved
                                                    ? 'notification-icon-approved'
                                                    : 'notification-icon-rejected' ?>"
                                            >

                                                <i class="bi <?= $isApproved
                                                    ? 'bi-check-circle'
                                                    : 'bi-x-circle' ?>"></i>

                                            </div>


                                            <div class="notification-item-content">

                                                <span class="notification-item-title">

                                                    <?= $isApproved
                                                        ? 'Peminjaman disetujui'
                                                        : 'Peminjaman ditolak' ?>

                                                </span>


                                                <span class="notification-item-description">

                                                    <?= htmlspecialchars(
                                                        $notification['nama_ruangan']
                                                    ) ?>

                                                </span>


                                                <span class="notification-item-meta">

                                                    <span>

                                                        <i class="bi bi-calendar3"></i>

                                                        <?= date(
                                                            'd/m/Y',
                                                            strtotime(
                                                                $notification['tanggal']
                                                            )
                                                        ) ?>

                                                    </span>


                                                    <span>
                                                        •
                                                    </span>


                                                    <span>

                                                        <?= substr(
                                                            $notification['jam_mulai'],
                                                            0,
                                                            5
                                                        ) ?>

                                                        -

                                                        <?= substr(
                                                            $notification['jam_selesai'],
                                                            0,
                                                            5
                                                        ) ?>

                                                    </span>

                                                </span>

                                            </div>


                                            <div class="notification-item-arrow">

                                                <i class="bi bi-chevron-right"></i>

                                            </div>

                                        </a>


                                    <?php endforeach; ?>


                                <?php else: ?>


                                    <div class="notification-empty">

                                        <div class="notification-empty-icon">

                                            <i class="bi bi-bell-slash"></i>

                                        </div>


                                        <strong>
                                            Belum ada notifikasi
                                        </strong>


                                        <span>
                                            Informasi peminjaman akan muncul di sini.
                                        </span>

                                    </div>


                                <?php endif; ?>


                            </div>


                            <div class="notification-dropdown-footer">

                                <a
                                    href="/peminjaman_ruangan_php/user/notifications.php"
                                    class="notification-view-all"
                                >

                                    Lihat Semua Notifikasi

                                    <i class="bi bi-arrow-right"></i>

                                </a>

                            </div>

                        </div>

                    </div>


                <?php endif; ?>


                <!-- USER -->

                <div class="navbar-user">


                    <div class="user-avatar">

                        <?= strtoupper(
                            substr(
                                $_SESSION['user']['nama'],
                                0,
                                1
                            )
                        ) ?>

                    </div>


                    <div class="user-info">

                        <strong>

                            <?= htmlspecialchars(
                                $_SESSION['user']['nama']
                            ) ?>

                        </strong>


                        <small>

                            <?= $_SESSION['user']['role'] === 'admin'
                                ? 'Administrator'
                                : 'Pengguna' ?>

                        </small>

                    </div>

                </div>


                <!-- LOGOUT -->

                <a
                    href="/peminjaman_ruangan_php/logout.php"
                    class="logout-button"
                    title="Logout"
                >

                    <i class="bi bi-box-arrow-right"></i>

                </a>


            </div>

        <?php endif; ?>


    </div>

</nav>


<!-- =========================
     JAVASCRIPT NOTIFIKASI
     ========================= -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const notificationButton =
        document.getElementById('notificationButton');

    const notificationDropdown =
        document.getElementById('notificationDropdown');


    /* =================================================
       CEK ELEMEN
       ================================================= */

    if (!notificationButton || !notificationDropdown) {
        return;
    }


    /* =================================================
       KLIK TOMBOL NOTIFIKASI
       ================================================= */

    notificationButton.addEventListener('click', function (event) {

        event.preventDefault();

        event.stopPropagation();


        const isOpen =
            notificationDropdown.classList.contains('show');


        if (isOpen) {

            notificationDropdown.classList.remove('show');

            notificationButton.classList.remove('active');

            notificationButton.setAttribute(
                'aria-expanded',
                'false'
            );

        } else {

            notificationDropdown.classList.add('show');

            notificationButton.classList.add('active');

            notificationButton.setAttribute(
                'aria-expanded',
                'true'
            );

        }

    });


    /* =================================================
       KLIK ISI DROPDOWN
       ================================================= */

    notificationDropdown.addEventListener(
        'click',
        function (event) {

            event.stopPropagation();

        }
    );


    /* =================================================
       KLIK DI LUAR DROPDOWN
       ================================================= */

    document.addEventListener(
        'click',
        function (event) {

            if (
                !notificationDropdown.contains(event.target) &&
                !notificationButton.contains(event.target)
            ) {

                notificationDropdown.classList.remove('show');

                notificationButton.classList.remove('active');

                notificationButton.setAttribute(
                    'aria-expanded',
                    'false'
                );

            }

        }
    );


    /* =================================================
       TOMBOL ESC
       ================================================= */

    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key === 'Escape') {

                notificationDropdown.classList.remove('show');

                notificationButton.classList.remove('active');

                notificationButton.setAttribute(
                    'aria-expanded',
                    'false'
                );

            }

        }
    );

});

</script>


<!-- =========================
     MAIN
     ========================= -->

<main class="siperu-main">

    <div class="container">