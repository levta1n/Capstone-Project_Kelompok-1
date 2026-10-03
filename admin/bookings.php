<?php

require '../config/auth.php';
require '../config/database.php';

require_admin();

$error = '';
$success = '';

/* =====================================================
   PROSES APPROVAL
   ===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($bookingId <= 0) {

        $error = 'Data pengajuan tidak valid.';

    } elseif (!in_array($action, ['approve', 'reject'], true)) {

        $error = 'Tindakan tidak valid.';

    } else {

        /* =============================================
           AMBIL DATA PENGAJUAN
           ============================================= */

        $stmt = $pdo->prepare("
            SELECT
                b.*,
                r.nama_ruangan,
                r.lokasi,
                r.kapasitas,
                u.nama AS nama_peminjam
            FROM bookings b
            INNER JOIN rooms r
                ON r.id = b.room_id
            INNER JOIN users u
                ON u.id = b.user_id
            WHERE b.id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $bookingId
        ]);

        $booking = $stmt->fetch();

        if (!$booking) {

            $error = 'Pengajuan tidak ditemukan.';

        } elseif ($booking['status'] !== 'menunggu') {

            if ($booking['status'] === 'dibatalkan') {

                $error = 'Pengajuan ini telah dibatalkan oleh pengguna.';

            } else {

                $error = 'Pengajuan ini sudah diproses sebelumnya.';
            }

        } elseif ($action === 'approve') {

            /* =========================================
               CEK BENTROK JADWAL
               ========================================= */

            $stmtConflict = $pdo->prepare("
                SELECT COUNT(*)
                FROM bookings
                WHERE room_id = ?
                AND tanggal = ?
                AND status = 'disetujui'
                AND id != ?
                AND jam_mulai < ?
                AND jam_selesai > ?
            ");

            $stmtConflict->execute([
                $booking['room_id'],
                $booking['tanggal'],
                $booking['id'],
                $booking['jam_selesai'],
                $booking['jam_mulai']
            ]);

            $conflict = (int) $stmtConflict->fetchColumn();

            if ($conflict > 0) {

                $error = 'Pengajuan tidak dapat disetujui karena jadwal ruangan sudah digunakan pada waktu tersebut.';

            } else {

                $stmtUpdate = $pdo->prepare("
                    UPDATE bookings
                    SET status = 'disetujui'
                    WHERE id = ?
                    AND status = 'menunggu'
                ");

                $stmtUpdate->execute([
                    $bookingId
                ]);

                if ($stmtUpdate->rowCount() > 0) {

                    $success = 'Pengajuan berhasil disetujui.';

                } else {

                    $error = 'Pengajuan tidak dapat diproses.';
                }
            }

        } else {

            /* =========================================
               TOLAK PENGAJUAN
               ========================================= */

            $stmtUpdate = $pdo->prepare("
                UPDATE bookings
                SET status = 'ditolak'
                WHERE id = ?
                AND status = 'menunggu'
            ");

            $stmtUpdate->execute([
                $bookingId
            ]);

            if ($stmtUpdate->rowCount() > 0) {

                $success = 'Pengajuan berhasil ditolak.';

            } else {

                $error = 'Pengajuan tidak dapat diproses.';
            }
        }
    }
}


/* =====================================================
   AMBIL PENGAJUAN YANG BELUM DIRESPON
   ===================================================== */

$stmtBookings = $pdo->query("
    SELECT
        b.*,
        r.nama_ruangan,
        r.lokasi,
        r.kapasitas,
        u.nama AS nama_peminjam
    FROM bookings b
    INNER JOIN rooms r
        ON r.id = b.room_id
    INNER JOIN users u
        ON u.id = b.user_id
    WHERE b.status = 'menunggu'
    ORDER BY
        b.tanggal ASC,
        b.jam_mulai ASC,
        b.id ASC
");

$bookings = $stmtBookings->fetchAll();


/* =====================================================
   HITUNG PENGAJUAN MENUNGGU
   ===================================================== */

$total = count($bookings);
$waiting = $total;


/* =====================================================
   TITLE
   ===================================================== */

$title = 'Approval Peminjaman';

require '../config/header.php';

?>

<style>

/* =====================================================
   APPROVAL PAGE
   ===================================================== */

.approval-page {
    margin-bottom: 40px;
}


/* =====================================================
   HEADER AKSEN HIJAU
   ===================================================== */

.approval-page-header {
    position: relative;
    margin-bottom: 24px;
    padding: 20px 22px;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.approval-page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: var(--green);
}

.approval-page-header .heading-label {
    margin-bottom: 4px;
    color: var(--green);
}

.approval-page-header h1 {
    margin: 0 0 5px;
}

.approval-page-header p {
    margin: 0;
    color: var(--muted);
    font-size: 14px;
}


/* =====================================================
   STATISTIK
   ===================================================== */

.approval-stats {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    margin-bottom: 28px;
}

.approval-stat {
    position: relative;
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 18px 20px;
    background: var(--white);
    border: 1px solid var(--border);
    border-left: 4px solid var(--green);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.approval-stat::after {
    content: '';
    position: absolute;
    right: -25px;
    top: -35px;
    width: 110px;
    height: 110px;
    border-radius: 50%;
    background: var(--green-light);
    opacity: .7;
}

.approval-stat-icon {
    position: relative;
    z-index: 1;
    width: 46px;
    height: 46px;
    flex: 0 0 46px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--green-light);
    color: var(--green);
    font-size: 19px;
}

.approval-stat-text {
    position: relative;
    z-index: 1;
}

.approval-stat-text span {
    display: block;
    color: var(--muted);
    font-size: 12px;
    margin-bottom: 3px;
}

.approval-stat-text strong {
    display: block;
    color: var(--green-dark);
    font-size: 24px;
    line-height: 1;
}


/* =====================================================
   SECTION HEADING
   ===================================================== */

.approval-section-heading {
    margin-bottom: 16px;
}

.approval-section-heading .heading-label {
    margin-bottom: 4px;
    color: var(--green);
}

.approval-section-heading h2 {
    margin: 0;
    color: var(--text);
    font-size: 21px;
}


/* =====================================================
   LIST
   ===================================================== */

.approval-list {
    display: flex;
    flex-direction: column;
    gap: 17px;
}


/* =====================================================
   CARD UTAMA
   ===================================================== */

.approval-card {
    position: relative;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 20px;
    overflow: hidden;
}

.approval-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 4px;
    height: 100%;
    background: var(--green);
}


/* =====================================================
   HEADER CARD
   ===================================================== */

.approval-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 17px;
}


/* =====================================================
   INFORMASI PEMOHON & RUANGAN
   ===================================================== */

.approval-main-info {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
    gap: 12px;
    width: 100%;
    min-width: 0;
}

.approval-person,
.approval-room {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    min-width: 0;
    padding: 13px 14px;
    border-radius: 12px;
}

.approval-person {
    background: var(--green-light);
    border: 1px solid #d9eee2;
}

.approval-room {
    background: #f8fbf9;
    border: 1px solid var(--border);
}

.approval-person-icon,
.approval-room-icon {
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.approval-person-icon {
    background: var(--white);
    color: var(--green);
}

.approval-room-icon {
    background: var(--green-light);
    color: var(--green);
}

.approval-person > div:last-child,
.approval-room > div:last-child {
    min-width: 0;
}

.approval-info-label {
    display: block;
    margin-bottom: 4px;
    color: var(--green);
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.approval-person h3,
.approval-room h3 {
    margin: 0 0 4px;
    color: var(--text);
    font-size: 17px;
    font-weight: 700;
    line-height: 1.3;
    word-break: break-word;
}

.approval-person p,
.approval-room p {
    margin: 0;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.4;
}

.approval-person p i,
.approval-room p i {
    margin-right: 3px;
}


/* =====================================================
   STATUS MENUNGGU
   ===================================================== */

.approval-card-top > .status-badge {
    flex: 0 0 auto;
}


/* =====================================================
   DETAIL JADWAL
   ===================================================== */

.approval-details {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0;
    padding: 14px 0;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}

.approval-detail {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    min-width: 0;
    padding: 2px 14px;
    border-right: 1px solid var(--border);
}

.approval-detail:first-child {
    padding-left: 0;
}

.approval-detail:last-child {
    padding-right: 0;
    border-right: 0;
}

.approval-detail i {
    color: var(--green);
    font-size: 15px;
    margin-top: 2px;
    flex: 0 0 auto;
}

.approval-detail > div {
    min-width: 0;
}

.approval-detail span {
    display: block;
    color: var(--muted);
    font-size: 10.5px;
    margin-bottom: 3px;
}

.approval-detail strong {
    display: block;
    color: var(--text);
    font-size: 13px;
    font-weight: 600;
    line-height: 1.4;
    word-break: break-word;
}


/* =====================================================
   DETAIL TAMBAHAN
   ===================================================== */

.approval-details-extra {
    margin-top: 0;
    border-top: 0;
}


/* =====================================================
   KEPERLUAN PEMINJAMAN
   ===================================================== */

.approval-purpose {
    position: relative;
    margin-top: 15px;
    padding: 15px 17px 15px 19px;
    background: var(--green-light);
    border: 1px solid #d7ecdf;
    border-radius: 12px;
    overflow: hidden;
}

.approval-purpose::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 4px;
    height: 100%;
    background: var(--green);
}

.approval-purpose-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 7px;
}

.approval-purpose-header i {
    color: var(--green);
    font-size: 15px;
}

.approval-purpose-label {
    color: var(--green-dark);
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.approval-purpose p {
    margin: 0;
    color: var(--text);
    font-size: 13.5px;
    font-weight: 500;
    line-height: 1.6;
    word-break: break-word;
}


/* =====================================================
   ACTION
   ===================================================== */

.approval-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 9px;
    margin-top: 16px;
}

.approval-actions form {
    margin: 0;
}

.approval-actions .btn {
    min-width: 105px;
}


/* =====================================================
   DETAIL BUTTON
   ===================================================== */

.approval-detail-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-width: 120px;
    height: 38px;
    padding: 8px 13px;
    border: 1px solid #cfe4d7;
    border-radius: 9px;
    background: var(--white);
    color: var(--green-dark);
    font-size: 12.5px;
    font-weight: 600;
    text-decoration: none;
    transition:
        background .2s ease,
        border-color .2s ease,
        color .2s ease,
        transform .2s ease;
}

.approval-detail-button:hover {
    background: var(--green-light);
    border-color: #b9dbc7;
    color: var(--green-dark);
    transform: translateY(-1px);
}


/* =====================================================
   TOAST
   ===================================================== */

.approval-toast {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: 12px;
    width: min(380px, calc(100vw - 32px));
    padding: 14px 15px;
    background: var(--white);
    border: 1px solid var(--border);
    border-left: 4px solid var(--green);
    border-radius: 13px;
    box-shadow: 0 12px 35px rgba(20, 40, 28, .14);
    opacity: 0;
    visibility: hidden;
    transform: translateY(18px);
    transition:
        opacity .25s ease,
        transform .25s ease,
        visibility .25s ease;
}

.approval-toast.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.approval-toast.error {
    border-left-color: var(--red);
}

.approval-toast-icon {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-size: 17px;
}

.approval-toast.success .approval-toast-icon {
    background: var(--green-light);
    color: var(--green);
}

.approval-toast.error .approval-toast-icon {
    background: var(--red-light);
    color: var(--red);
}

.approval-toast-content {
    min-width: 0;
    flex: 1;
}

.approval-toast-title {
    margin: 0 0 2px;
    color: var(--text);
    font-size: 13px;
    font-weight: 700;
}

.approval-toast-message {
    margin: 0;
    color: var(--muted);
    font-size: 11.5px;
    line-height: 1.45;
}

.approval-toast-close {
    width: 28px;
    height: 28px;
    flex: 0 0 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 0;
    border-radius: 7px;
    background: transparent;
    color: var(--muted);
    font-size: 16px;
    cursor: pointer;
    transition:
        background .2s ease,
        color .2s ease;
}

.approval-toast-close:hover {
    background: var(--green-light);
    color: var(--green);
}


/* =====================================================
   MODAL KONFIRMASI
   ===================================================== */

.approval-confirm-overlay {
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(23, 33, 28, .48);
    opacity: 0;
    visibility: hidden;
    transition:
        opacity .2s ease,
        visibility .2s ease;
}

.approval-confirm-overlay.show {
    opacity: 1;
    visibility: visible;
}

.approval-confirm-modal {
    width: min(430px, 100%);
    background: var(--white);
    border: 1px solid var(--border);
    border-top: 4px solid var(--green);
    border-radius: 18px;
    box-shadow: 0 20px 60px rgba(20, 40, 28, .18);
    padding: 24px;
    transform: translateY(12px) scale(.98);
    transition: transform .2s ease;
}

.approval-confirm-overlay.show .approval-confirm-modal {
    transform: translateY(0) scale(1);
}

.approval-confirm-icon {
    width: 48px;
    height: 48px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
    font-size: 21px;
}

.approval-confirm-icon.approve {
    background: var(--green-light);
    color: var(--green);
}

.approval-confirm-icon.reject {
    background: var(--red-light);
    color: var(--red);
}

.approval-confirm-title {
    margin: 0 0 6px;
    color: var(--text);
    font-size: 18px;
    font-weight: 700;
}

.approval-confirm-description {
    margin: 0 0 18px;
    color: var(--text-soft);
    font-size: 13px;
    line-height: 1.55;
}

.approval-confirm-info {
    display: grid;
    gap: 9px;
    padding: 13px 14px;
    margin-bottom: 20px;
    background: var(--green-light);
    border: 1px solid #d7ecdf;
    border-radius: 11px;
}

.approval-confirm-info-row {
    display: flex;
    align-items: flex-start;
    gap: 9px;
}

.approval-confirm-info-row i {
    width: 16px;
    color: var(--green);
    font-size: 14px;
    margin-top: 2px;
}

.approval-confirm-info-row div {
    min-width: 0;
}

.approval-confirm-info-label {
    display: block;
    color: var(--muted);
    font-size: 10.5px;
    margin-bottom: 1px;
}

.approval-confirm-info-value {
    display: block;
    color: var(--text);
    font-size: 12.5px;
    font-weight: 600;
    word-break: break-word;
}

.approval-confirm-actions {
    display: flex;
    justify-content: flex-end;
    gap: 9px;
}

.approval-confirm-actions button {
    min-width: 105px;
    height: 40px;
}

.approval-confirm-cancel {
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--white);
    color: var(--text-soft);
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    transition:
        background .2s ease,
        border-color .2s ease,
        color .2s ease;
}

.approval-confirm-cancel:hover {
    background: var(--green-light);
    border-color: #cfe4d7;
    color: var(--green-dark);
}

.approval-confirm-submit {
    border: 0;
    border-radius: 9px;
    color: var(--white);
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    transition:
        transform .2s ease,
        opacity .2s ease;
}

.approval-confirm-submit:hover {
    transform: translateY(-1px);
    opacity: .92;
}

.approval-confirm-submit.approve {
    background: var(--green);
}

.approval-confirm-submit.reject {
    background: var(--red);
}


/* =====================================================
   EMPTY STATE
   ===================================================== */

.approval-empty {
    text-align: center;
    padding: 50px 25px;
    background: var(--white);
    border: 1px solid var(--border);
    border-top: 4px solid var(--green);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.approval-empty-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 14px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--green-light);
    color: var(--green);
    font-size: 25px;
}

.approval-empty h3 {
    margin: 0 0 5px;
    color: var(--text);
    font-size: 17px;
}

.approval-empty p {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
}


/* =====================================================
   RESPONSIVE
   ===================================================== */

@media (max-width: 900px) {

    .approval-main-info {
        gap: 10px;
    }

    .approval-details {
        grid-template-columns: 1fr 1fr;
        row-gap: 12px;
    }

    .approval-detail:nth-child(2) {
        border-right: 0;
    }

    .approval-detail:nth-child(3) {
        padding-left: 0;
        border-right: 0;
    }

}

@media (max-width: 800px) {

    .approval-card-top {
        flex-direction: column;
    }

    .approval-main-info {
        grid-template-columns: 1fr;
    }

    .approval-card-top > .status-badge {
        align-self: flex-start;
    }

}

@media (max-width: 650px) {

    .approval-details {
        grid-template-columns: 1fr;
    }

    .approval-detail,
    .approval-detail:first-child,
    .approval-detail:last-child {
        padding: 0;
        border-right: 0;
    }

    .approval-detail + .approval-detail {
        padding-top: 11px;
    }

}

@media (max-width: 600px) {

    .approval-page-header {
        padding: 17px 18px;
    }

    .approval-card {
        padding: 16px;
    }

    .approval-main-info {
        width: 100%;
    }

    .approval-person,
    .approval-room {
        width: 100%;
    }

    .approval-purpose {
        padding: 14px 15px 14px 17px;
    }

    .approval-actions {
        justify-content: stretch;
        flex-wrap: wrap;
    }

    .approval-detail-button {
        width: 100%;
        order: 1;
    }

    .approval-actions form {
        flex: 1;
    }

    .approval-actions .btn {
        width: 100%;
    }

    .approval-toast {
        right: 16px;
        bottom: 16px;
        width: calc(100vw - 32px);
    }

    .approval-confirm-modal {
        padding: 20px;
    }

    .approval-confirm-actions {
        flex-direction: column-reverse;
    }

    .approval-confirm-actions button {
        width: 100%;
    }

}

</style>


<!-- =====================================================
     HEADER
     ===================================================== -->

<section class="approval-page-header">

    <span class="heading-label">
        APPROVAL PEMINJAMAN
    </span>

    <h1>
        Pengajuan Peminjaman
    </h1>

    <p>
        Periksa dan kelola pengajuan peminjaman ruangan yang belum direspons.
    </p>

</section>


<div class="approval-page">


    <!-- =================================================
         RINGKASAN STATUS
         ================================================= -->

    <section class="approval-stats">

        <div class="approval-stat">

            <div class="approval-stat-icon">
                <i class="bi bi-clock"></i>
            </div>

            <div class="approval-stat-text">

                <span>
                    Menunggu Persetujuan
                </span>

                <strong>
                    <?= $waiting ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- =================================================
         DAFTAR PENGAJUAN
         ================================================= -->

    <section>

        <div class="approval-section-heading">

            <span class="heading-label">
                PENGAJUAN BELUM DIRESPON
            </span>

            <h2>
                Pengajuan Menunggu Persetujuan
            </h2>

        </div>


        <?php if (!$bookings): ?>

            <div class="approval-empty">

                <div class="approval-empty-icon">
                    <i class="bi bi-check2-circle"></i>
                </div>

                <h3>
                    Semua pengajuan sudah diproses
                </h3>

                <p>
                    Saat ini tidak ada pengajuan peminjaman yang menunggu persetujuan.
                </p>

            </div>

        <?php else: ?>


            <div class="approval-list">


                <?php foreach ($bookings as $booking): ?>

                    <?php

                    $jamMulai = strtotime($booking['jam_mulai']);
                    $jamSelesai = strtotime($booking['jam_selesai']);

                    $durasiMenit = max(
                        0,
                        ($jamSelesai - $jamMulai) / 60
                    );

                    $durasiJam = floor($durasiMenit / 60);
                    $durasiSisaMenit = $durasiMenit % 60;

                    if ($durasiJam > 0 && $durasiSisaMenit > 0) {

                        $durasi =
                            $durasiJam .
                            ' jam ' .
                            $durasiSisaMenit .
                            ' menit';

                    } elseif ($durasiJam > 0) {

                        $durasi =
                            $durasiJam .
                            ' jam';

                    } else {

                        $durasi =
                            $durasiSisaMenit .
                            ' menit';
                    }

                    ?>


                    <article class="approval-card">


                        <!-- =================================
                             PEMOHON + RUANGAN
                             ================================= -->

                        <div class="approval-card-top">


                            <div class="approval-main-info">


                                <!-- PEMOHON -->

                                <div class="approval-person">

                                    <div class="approval-person-icon">

                                        <i class="bi bi-person-fill"></i>

                                    </div>

                                    <div>

                                        <span class="approval-info-label">
                                            Pemohon
                                        </span>

                                        <h3>
                                            <?= htmlspecialchars(
                                                $booking['nama_peminjam']
                                            ) ?>
                                        </h3>

                                        <p>
                                            Pengguna yang mengajukan peminjaman
                                        </p>

                                    </div>

                                </div>


                                <!-- RUANGAN -->

                                <div class="approval-room">

                                    <div class="approval-room-icon">

                                        <i class="bi bi-building"></i>

                                    </div>

                                    <div>

                                        <span class="approval-info-label">
                                            Ruangan
                                        </span>

                                        <h3>
                                            <?= htmlspecialchars(
                                                $booking['nama_ruangan']
                                            ) ?>
                                        </h3>

                                        <p>

                                            <i class="bi bi-geo-alt"></i>

                                            <?= !empty($booking['lokasi'])
                                                ? htmlspecialchars(
                                                    $booking['lokasi']
                                                )
                                                : 'Lokasi belum diisi'
                                            ?>

                                        </p>

                                    </div>

                                </div>


                            </div>


                            <span class="status-badge status-waiting">
                                Menunggu
                            </span>


                        </div>


                        <!-- =================================
                             JADWAL
                             ================================= -->

                        <div class="approval-details">


                            <!-- TANGGAL -->

                            <div class="approval-detail">

                                <i class="bi bi-calendar3"></i>

                                <div>

                                    <span>
                                        Tanggal
                                    </span>

                                    <strong>

                                        <?= date(
                                            'd-m-Y',
                                            strtotime(
                                                $booking['tanggal']
                                            )
                                        ) ?>

                                    </strong>

                                </div>

                            </div>


                            <!-- WAKTU -->

                            <div class="approval-detail">

                                <i class="bi bi-clock"></i>

                                <div>

                                    <span>
                                        Waktu
                                    </span>

                                    <strong>

                                        <?= substr(
                                            $booking['jam_mulai'],
                                            0,
                                            5
                                        ) ?>

                                        -

                                        <?= substr(
                                            $booking['jam_selesai'],
                                            0,
                                            5
                                        ) ?>

                                    </strong>

                                </div>

                            </div>


                            <!-- DURASI -->

                            <div class="approval-detail">

                                <i class="bi bi-hourglass-split"></i>

                                <div>

                                    <span>
                                        Durasi
                                    </span>

                                    <strong>
                                        <?= htmlspecialchars($durasi) ?>
                                    </strong>

                                </div>

                            </div>


                        </div>


                        <!-- =================================
                             INFORMASI RUANGAN
                             ================================= -->

                        <div class="approval-details approval-details-extra">


                            <!-- LOKASI -->

                            <div class="approval-detail">

                                <i class="bi bi-geo-alt"></i>

                                <div>

                                    <span>
                                        Lokasi
                                    </span>

                                    <strong>

                                        <?= !empty($booking['lokasi'])
                                            ? htmlspecialchars(
                                                $booking['lokasi']
                                            )
                                            : '-'
                                        ?>

                                    </strong>

                                </div>

                            </div>


                            <!-- KAPASITAS -->

                            <div class="approval-detail">

                                <i class="bi bi-people"></i>

                                <div>

                                    <span>
                                        Kapasitas Ruangan
                                    </span>

                                    <strong>

                                        <?= !empty($booking['kapasitas'])
                                            ? htmlspecialchars(
                                                $booking['kapasitas']
                                            ) . ' orang'
                                            : '-'
                                        ?>

                                    </strong>

                                </div>

                            </div>


                            <!-- STATUS -->

                            <div class="approval-detail">

                                <i class="bi bi-hourglass-split"></i>

                                <div>

                                    <span>
                                        Status
                                    </span>

                                    <strong>
                                        Menunggu Persetujuan
                                    </strong>

                                </div>

                            </div>


                        </div>


                        <!-- =================================
                             KEPERLUAN PEMINJAMAN
                             ================================= -->

                        <?php if (!empty($booking['keperluan'])): ?>

                            <div class="approval-purpose">

                                <div class="approval-purpose-header">

                                    <i class="bi bi-card-text"></i>

                                    <div class="approval-purpose-label">
                                        Keperluan Peminjaman
                                    </div>

                                </div>

                                <p>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $booking['keperluan']
                                        )
                                    ) ?>

                                </p>

                            </div>

                        <?php endif; ?>


                        <!-- =================================
                             ACTION
                             ================================= -->

                        <div class="approval-actions">


                            <!-- LIHAT DETAIL -->

                            <a
                                href="booking_detail.php?id=<?= (int) $booking['id'] ?>"
                                class="approval-detail-button"
                            >

                                <i class="bi bi-eye"></i>

                                Lihat Detail

                            </a>


                            <!-- TOLAK -->

                            <form
                                method="POST"
                                class="approval-action-form"
                                data-action="reject"

                                data-booking-name="<?= htmlspecialchars(
                                    $booking['nama_peminjam'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"

                                data-room-name="<?= htmlspecialchars(
                                    $booking['nama_ruangan'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"

                                data-booking-date="<?= htmlspecialchars(
                                    date(
                                        'd-m-Y',
                                        strtotime($booking['tanggal'])
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"

                                data-booking-time="<?= htmlspecialchars(
                                    substr(
                                        $booking['jam_mulai'],
                                        0,
                                        5
                                    )
                                    . ' - ' .
                                    substr(
                                        $booking['jam_selesai'],
                                        0,
                                        5
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                                <input
                                    type="hidden"
                                    name="booking_id"
                                    value="<?= (int) $booking['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="reject"
                                >

                                <button
                                    type="button"
                                    class="btn btn-secondary approval-confirm-trigger"
                                >

                                    <i class="bi bi-x-lg"></i>

                                    Tolak

                                </button>

                            </form>


                            <!-- SETUJUI -->

                            <form
                                method="POST"
                                class="approval-action-form"
                                data-action="approve"

                                data-booking-name="<?= htmlspecialchars(
                                    $booking['nama_peminjam'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"

                                data-room-name="<?= htmlspecialchars(
                                    $booking['nama_ruangan'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"

                                data-booking-date="<?= htmlspecialchars(
                                    date(
                                        'd-m-Y',
                                        strtotime($booking['tanggal'])
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"

                                data-booking-time="<?= htmlspecialchars(
                                    substr(
                                        $booking['jam_mulai'],
                                        0,
                                        5
                                    )
                                    . ' - ' .
                                    substr(
                                        $booking['jam_selesai'],
                                        0,
                                        5
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                                <input
                                    type="hidden"
                                    name="booking_id"
                                    value="<?= (int) $booking['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="approve"
                                >

                                <button
                                    type="button"
                                    class="btn btn-primary approval-confirm-trigger"
                                >

                                    <i class="bi bi-check-lg"></i>

                                    Setujui

                                </button>

                            </form>


                        </div>


                    </article>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>


    </section>


</div>


<!-- =====================================================
     MODAL KONFIRMASI
     ===================================================== -->

<div
    class="approval-confirm-overlay"
    id="approvalConfirmOverlay"
    aria-hidden="true"
>

    <div
        class="approval-confirm-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="approvalConfirmTitle"
    >


        <div
            class="approval-confirm-icon"
            id="approvalConfirmIcon"
        >

            <i
                class="bi bi-question-lg"
                id="approvalConfirmIconSymbol"
            ></i>

        </div>


        <h3
            class="approval-confirm-title"
            id="approvalConfirmTitle"
        >
            Konfirmasi Pengajuan?
        </h3>


        <p
            class="approval-confirm-description"
            id="approvalConfirmDescription"
        >
            Apakah Anda yakin ingin memproses pengajuan ini?
        </p>


        <div class="approval-confirm-info">


            <div class="approval-confirm-info-row">

                <i class="bi bi-person"></i>

                <div>

                    <span class="approval-confirm-info-label">
                        Pemohon
                    </span>

                    <strong
                        class="approval-confirm-info-value"
                        id="confirmBookingName"
                    >
                        -
                    </strong>

                </div>

            </div>


            <div class="approval-confirm-info-row">

                <i class="bi bi-building"></i>

                <div>

                    <span class="approval-confirm-info-label">
                        Ruangan
                    </span>

                    <strong
                        class="approval-confirm-info-value"
                        id="confirmRoomName"
                    >
                        -
                    </strong>

                </div>

            </div>


            <div class="approval-confirm-info-row">

                <i class="bi bi-calendar3"></i>

                <div>

                    <span class="approval-confirm-info-label">
                        Tanggal
                    </span>

                    <strong
                        class="approval-confirm-info-value"
                        id="confirmBookingDate"
                    >
                        -
                    </strong>

                </div>

            </div>


            <div class="approval-confirm-info-row">

                <i class="bi bi-clock"></i>

                <div>

                    <span class="approval-confirm-info-label">
                        Waktu
                    </span>

                    <strong
                        class="approval-confirm-info-value"
                        id="confirmBookingTime"
                    >
                        -
                    </strong>

                </div>

            </div>


        </div>


        <div class="approval-confirm-actions">

            <button
                type="button"
                class="approval-confirm-cancel"
                id="approvalConfirmCancel"
            >
                Batal
            </button>

            <button
                type="button"
                class="approval-confirm-submit"
                id="approvalConfirmSubmit"
            >
                Konfirmasi
            </button>

        </div>


    </div>

</div>


<!-- =====================================================
     TOAST NOTIFICATION
     ===================================================== -->

<?php if ($success || $error): ?>

    <div
        id="approvalToast"
        class="approval-toast <?= $success ? 'success' : 'error' ?>"
        role="alert"
        aria-live="polite"
    >

        <div class="approval-toast-icon">

            <?php if ($success): ?>

                <i class="bi bi-check-lg"></i>

            <?php else: ?>

                <i class="bi bi-exclamation-lg"></i>

            <?php endif; ?>

        </div>


        <div class="approval-toast-content">

            <p class="approval-toast-title">

                <?php if ($success): ?>

                    Berhasil

                <?php else: ?>

                    Terjadi Kesalahan

                <?php endif; ?>

            </p>


            <p class="approval-toast-message">

                <?= htmlspecialchars(
                    $success ?: $error
                ) ?>

            </p>

        </div>


        <button
            type="button"
            class="approval-toast-close"
            id="approvalToastClose"
            aria-label="Tutup notifikasi"
        >

            <i class="bi bi-x-lg"></i>

        </button>

    </div>

<?php endif; ?>


<script>

/* =====================================================
   TOAST
   ===================================================== */

document.addEventListener('DOMContentLoaded', function () {

    const toast = document.getElementById(
        'approvalToast'
    );

    const closeButton = document.getElementById(
        'approvalToastClose'
    );


    if (toast) {

        let hideTimer;


        function showToast() {

            requestAnimationFrame(function () {

                toast.classList.add('show');

            });


            hideTimer = setTimeout(function () {

                hideToast();

            }, 4000);

        }


        function hideToast() {

            toast.classList.remove('show');


            if (hideTimer) {

                clearTimeout(hideTimer);

            }

        }


        if (closeButton) {

            closeButton.addEventListener(
                'click',
                function () {

                    hideToast();

                }
            );

        }


        showToast();

    }


    /* =================================================
       MODAL KONFIRMASI
       ================================================= */

    const overlay = document.getElementById(
        'approvalConfirmOverlay'
    );

    const cancelButton = document.getElementById(
        'approvalConfirmCancel'
    );

    const submitButton = document.getElementById(
        'approvalConfirmSubmit'
    );

    const title = document.getElementById(
        'approvalConfirmTitle'
    );

    const description = document.getElementById(
        'approvalConfirmDescription'
    );

    const icon = document.getElementById(
        'approvalConfirmIcon'
    );

    const iconSymbol = document.getElementById(
        'approvalConfirmIconSymbol'
    );

    const bookingName = document.getElementById(
        'confirmBookingName'
    );

    const roomName = document.getElementById(
        'confirmRoomName'
    );

    const bookingDate = document.getElementById(
        'confirmBookingDate'
    );

    const bookingTime = document.getElementById(
        'confirmBookingTime'
    );


    let selectedForm = null;


    if (!overlay || !submitButton) {
        return;
    }


    /* ================================================
       BUKA MODAL
       ================================================ */

    function openConfirmation(form) {

        selectedForm = form;


        const action = form.dataset.action;


        bookingName.textContent =
            form.dataset.bookingName || '-';


        roomName.textContent =
            form.dataset.roomName || '-';


        bookingDate.textContent =
            form.dataset.bookingDate || '-';


        bookingTime.textContent =
            form.dataset.bookingTime || '-';


        if (action === 'approve') {

            title.textContent =
                'Setujui Pengajuan?';


            description.textContent =
                'Apakah Anda yakin ingin menyetujui pengajuan peminjaman ruangan ini?';


            icon.classList.remove('reject');

            icon.classList.add('approve');


            iconSymbol.className =
                'bi bi-check-lg';


            submitButton.textContent =
                'Ya, Setujui';


            submitButton.classList.remove('reject');

            submitButton.classList.add('approve');

        } else {

            title.textContent =
                'Tolak Pengajuan?';


            description.textContent =
                'Apakah Anda yakin ingin menolak pengajuan peminjaman ruangan ini?';


            icon.classList.remove('approve');

            icon.classList.add('reject');


            iconSymbol.className =
                'bi bi-x-lg';


            submitButton.textContent =
                'Ya, Tolak';


            submitButton.classList.remove('approve');

            submitButton.classList.add('reject');

        }


        overlay.classList.add('show');


        overlay.setAttribute(
            'aria-hidden',
            'false'
        );


        document.body.style.overflow = 'hidden';


        setTimeout(function () {

            submitButton.focus();

        }, 100);

    }


    /* ================================================
       TUTUP MODAL
       ================================================ */

    function closeConfirmation() {

        overlay.classList.remove('show');


        overlay.setAttribute(
            'aria-hidden',
            'true'
        );


        document.body.style.overflow = '';


        selectedForm = null;

    }


    /* ================================================
       EVENT TOMBOL SETUJUI / TOLAK
       ================================================ */

    const triggerButtons = document.querySelectorAll(
        '.approval-confirm-trigger'
    );


    triggerButtons.forEach(function (button) {

        button.addEventListener(
            'click',
            function () {

                const form = button.closest(
                    '.approval-action-form'
                );


                if (!form) {
                    return;
                }


                openConfirmation(form);

            }
        );

    });


    /* ================================================
       KONFIRMASI
       ================================================ */

    submitButton.addEventListener(
        'click',
        function () {

            if (!selectedForm) {
                return;
            }


            selectedForm.submit();

        }
    );


    /* ================================================
       TOMBOL BATAL
       ================================================ */

    cancelButton.addEventListener(
        'click',
        function () {

            closeConfirmation();

        }
    );


    /* ================================================
       KLIK AREA LUAR MODAL
       ================================================ */

    overlay.addEventListener(
        'click',
        function (event) {

            if (event.target === overlay) {

                closeConfirmation();

            }

        }
    );


    /* ================================================
       TOMBOL ESCAPE
       ================================================ */

    document.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Escape' &&
                overlay.classList.contains('show')
            ) {

                closeConfirmation();

            }

        }
    );

});

</script>


<?php require '../config/footer.php'; ?>