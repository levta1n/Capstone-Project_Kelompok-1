<?php

require '../config/auth.php';
require '../config/database.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: reports.php');
    exit;
}


/* =====================================================
   PROSES DATA
   ===================================================== */

$stmt = $pdo->prepare("
    SELECT 
        b.*,
        r.nama_ruangan,
        r.lokasi,
        r.kapasitas,
        r.fasilitas,
        r.foto,
        u.nama AS nama_user
    FROM bookings b
    JOIN rooms r ON b.room_id = r.id
    JOIN users u ON b.user_id = u.id
    WHERE b.id = ?
    LIMIT 1
");

$stmt->execute([$id]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: reports.php');
    exit;
}


/* =====================================================
   STATUS
   ===================================================== */

$status = $booking['status'];

$statusText = 'Menunggu';
$statusClass = 'waiting';

if ($status === 'disetujui') {
    $statusText = 'Disetujui';
    $statusClass = 'approved';
} elseif ($status === 'ditolak') {
    $statusText = 'Ditolak';
    $statusClass = 'rejected';
} elseif ($status === 'dibatalkan') {
    $statusText = 'Dibatalkan';
    $statusClass = 'cancelled';
}


/* =====================================================
   HEADER
   ===================================================== */

require '../config/header.php';

?>

<style>

/* =====================================================
   DETAIL PAGE
   ===================================================== */

.detail-page {
    max-width: 1000px;
    margin: 0 auto;
}

.detail-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 24px;
}

.detail-top-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.detail-back {
    width: 40px;
    height: 40px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--white);
    border: 1px solid var(--border);
    color: var(--text);
    text-decoration: none;
    transition: .2s ease;
}

.detail-back:hover {
    background: var(--green-light);
    color: var(--green);
    border-color: #d3e4da;
}

.detail-title h1 {
    margin: 0 0 4px;
    font-size: 24px;
}

.detail-title p {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
}


/* =====================================================
   STATUS
   ===================================================== */

.detail-status {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 13px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.detail-status.waiting {
    background: var(--yellow-light);
    color: var(--yellow);
}

.detail-status.approved {
    background: var(--green-light);
    color: var(--green);
}

.detail-status.rejected {
    background: var(--red-light);
    color: var(--red);
}

.detail-status.cancelled {
    background: var(--red-light);
    color: var(--red);
}


/* =====================================================
   MAIN GRID
   ===================================================== */

.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1.15fr;
    gap: 20px;
}


/* =====================================================
   CARD
   ===================================================== */

.detail-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.detail-card-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
}

.detail-card-header h2 {
    margin: 0;
    font-size: 16px;
}

.detail-card-body {
    padding: 20px;
}


/* =====================================================
   ROOM PHOTO
   ===================================================== */

.detail-room-photo {
    width: 100%;
    height: 230px;
    object-fit: cover;
    display: block;
    background: var(--green-light);
}

.detail-room-photo.empty {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--muted);
    font-size: 13px;
}


/* =====================================================
   ROOM INFO
   ===================================================== */

.detail-room-name {
    margin: 18px 20px 6px;
    font-size: 20px;
    font-weight: 700;
}

.detail-room-location {
    margin: 0 20px 18px;
    color: var(--muted);
    font-size: 13px;
}

.detail-room-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    padding: 0 20px 20px;
}

.detail-meta-box {
    background: var(--background);
    border-radius: 11px;
    padding: 12px;
}

.detail-meta-label {
    color: var(--muted);
    font-size: 11px;
    margin-bottom: 4px;
}

.detail-meta-value {
    font-size: 13px;
    font-weight: 600;
}


/* =====================================================
   INFO LIST
   ===================================================== */

.detail-info-list {
    display: flex;
    flex-direction: column;
    gap: 17px;
}

.detail-info-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
}

.detail-info-label {
    color: var(--muted);
    font-size: 12px;
    min-width: 125px;
}

.detail-info-value {
    text-align: right;
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    word-break: break-word;
}

.detail-purpose {
    line-height: 1.6;
    font-weight: 500;
}


/* =====================================================
   STATUS MESSAGE
   ===================================================== */

.detail-status-message {
    margin-top: 22px;
    padding: 14px 16px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.6;
}

.detail-status-message.waiting {
    background: var(--yellow-light);
    color: #765700;
}

.detail-status-message.approved {
    background: var(--green-light);
    color: var(--green-dark);
}

.detail-status-message.rejected {
    background: var(--red-light);
    color: var(--red);
}

.detail-status-message.cancelled {
    background: var(--red-light);
    color: var(--red);
}


/* =====================================================
   ACTION
   ===================================================== */

.detail-actions {
    display: flex;
    gap: 10px;
    margin-top: 22px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

.detail-actions form {
    margin: 0;
}

.detail-action-btn {
    border: none;
    border-radius: 10px;
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.detail-action-approve {
    background: var(--green);
    color: white;
}

.detail-action-approve:hover {
    background: var(--green-dark);
}

.detail-action-reject {
    background: var(--red-light);
    color: var(--red);
}

.detail-action-reject:hover {
    background: #ffe1e1;
}


/* =====================================================
   FACILITIES
   ===================================================== */

.detail-facilities {
    margin-top: 20px;
}

.detail-facilities-title {
    margin-bottom: 9px;
    font-size: 12px;
    color: var(--muted);
}

.detail-facilities-text {
    font-size: 13px;
    line-height: 1.6;
}


/* =====================================================
   RESPONSIVE
   ===================================================== */

@media (max-width: 800px) {

    .detail-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 600px) {

    .detail-top {
        align-items: flex-start;
        flex-direction: column;
    }

    .detail-room-meta {
        grid-template-columns: 1fr;
    }

    .detail-info-item {
        flex-direction: column;
        gap: 5px;
    }

    .detail-info-value {
        text-align: left;
    }

    .detail-actions {
        flex-direction: column;
    }

    .detail-actions form,
    .detail-action-btn {
        width: 100%;
    }

}

</style>


<div class="detail-page">

    <!-- =================================================
         HEADER
         ================================================= -->

    <div class="detail-top">

        <div class="detail-top-left">

            <a href="reports.php" class="detail-back" title="Kembali">
                <i class="bi bi-arrow-left"></i>
            </a>

            <div class="detail-title">

                <h1>
                    Detail Pengajuan
                </h1>

                <p>
                    Informasi lengkap pengajuan peminjaman ruangan.
                </p>

            </div>

        </div>


        <div class="detail-status <?= $statusClass ?>">

            <?php if ($status === 'menunggu'): ?>

                <i class="bi bi-clock"></i>

            <?php elseif ($status === 'disetujui'): ?>

                <i class="bi bi-check-circle"></i>

            <?php elseif ($status === 'ditolak'): ?>

                <i class="bi bi-x-circle"></i>

            <?php else: ?>

                <i class="bi bi-slash-circle"></i>

            <?php endif; ?>

            <?= htmlspecialchars($statusText) ?>

        </div>

    </div>


    <!-- =================================================
         DETAIL
         ================================================= -->

    <div class="detail-grid">


        <!-- =================================================
             RUANGAN
             ================================================= -->

        <div class="detail-card">

            <?php if (!empty($booking['foto'])): ?>

                <img
                    src="../assets/rooms/<?= htmlspecialchars($booking['foto']) ?>"
                    alt="<?= htmlspecialchars($booking['nama_ruangan']) ?>"
                    class="detail-room-photo"
                >

            <?php else: ?>

                <div class="detail-room-photo empty">

                    <i class="bi bi-image me-2"></i>

                    Foto ruangan belum tersedia

                </div>

            <?php endif; ?>


            <div class="detail-room-name">

                <?= htmlspecialchars($booking['nama_ruangan']) ?>

            </div>


            <div class="detail-room-location">

                <i class="bi bi-geo-alt me-1"></i>

                <?= htmlspecialchars($booking['lokasi']) ?>

            </div>


            <div class="detail-room-meta">

                <div class="detail-meta-box">

                    <div class="detail-meta-label">
                        Kapasitas
                    </div>

                    <div class="detail-meta-value">
                        <?= htmlspecialchars($booking['kapasitas']) ?> orang
                    </div>

                </div>


                <div class="detail-meta-box">

                    <div class="detail-meta-label">
                        ID Pengajuan
                    </div>

                    <div class="detail-meta-value">
                        #<?= htmlspecialchars($booking['id']) ?>
                    </div>

                </div>

            </div>


            <?php if (!empty($booking['fasilitas'])): ?>

                <div
                    class="detail-facilities"
                    style="padding: 0 20px 20px;"
                >

                    <div class="detail-facilities-title">
                        Fasilitas Ruangan
                    </div>

                    <div class="detail-facilities-text">

                        <?= nl2br(
                            htmlspecialchars($booking['fasilitas'])
                        ) ?>

                    </div>

                </div>

            <?php endif; ?>

        </div>


        <!-- =================================================
             INFORMASI PENGAJUAN
             ================================================= -->

        <div class="detail-card">

            <div class="detail-card-header">

                <h2>
                    Informasi Pengajuan
                </h2>

            </div>


            <div class="detail-card-body">

                <div class="detail-info-list">


                    <div class="detail-info-item">

                        <div class="detail-info-label">
                            Nama Pengguna
                        </div>

                        <div class="detail-info-value">

                            <?= htmlspecialchars(
                                $booking['nama_user']
                            ) ?>

                        </div>

                    </div>


                    <div class="detail-info-item">

                        <div class="detail-info-label">
                            Tanggal
                        </div>

                        <div class="detail-info-value">

                            <?= date(
                                'd/m/Y',
                                strtotime($booking['tanggal'])
                            ) ?>

                        </div>

                    </div>


                    <div class="detail-info-item">

                        <div class="detail-info-label">
                            Waktu
                        </div>

                        <div class="detail-info-value">

                            <?= date(
                                'H:i',
                                strtotime($booking['jam_mulai'])
                            ) ?>

                            -

                            <?= date(
                                'H:i',
                                strtotime($booking['jam_selesai'])
                            ) ?>

                        </div>

                    </div>


                    <div class="detail-info-item">

                        <div class="detail-info-label">
                            Keperluan
                        </div>

                        <div class="detail-info-value detail-purpose">

                            <?= nl2br(
                                htmlspecialchars(
                                    $booking['keperluan']
                                )
                            ) ?>

                        </div>

                    </div>


                    <div class="detail-info-item">

                        <div class="detail-info-label">
                            Status
                        </div>

                        <div class="detail-info-value">

                            <span class="detail-status <?= $statusClass ?>">

                                <?= htmlspecialchars(
                                    $statusText
                                ) ?>

                            </span>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     STATUS MESSAGE
                     ================================================= -->

                <?php if ($status === 'menunggu'): ?>

                    <div class="detail-status-message waiting">

                        <strong>
                            Menunggu persetujuan.
                        </strong>

                        <br>

                        Pengajuan ini masih menunggu pemeriksaan dan keputusan administrator.

                    </div>

                <?php elseif ($status === 'disetujui'): ?>

                    <div class="detail-status-message approved">

                        <strong>
                            Pengajuan disetujui.
                        </strong>

                        <br>

                        Peminjaman ruangan telah disetujui oleh administrator.

                    </div>

                <?php elseif ($status === 'ditolak'): ?>

                    <div class="detail-status-message rejected">

                        <strong>
                            Pengajuan ditolak.
                        </strong>

                        <br>

                        Pengajuan ini tidak dapat digunakan untuk peminjaman ruangan.

                    </div>

                <?php elseif ($status === 'dibatalkan'): ?>

                    <div class="detail-status-message cancelled">

                        <strong>
                            Pengajuan dibatalkan.
                        </strong>

                        <br>

                        Pengajuan ini telah dibatalkan oleh pengguna dan tidak memerlukan tindakan administrator.

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     ACTION ADMIN
                     ================================================= -->

                <?php if ($status === 'menunggu'): ?>

                    <div class="detail-actions">


                        <form
                            action="bookings.php"
                            method="POST"
                        >

                            <input
                                type="hidden"
                                name="booking_id"
                                value="<?= htmlspecialchars(
                                    $booking['id']
                                ) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="approve"
                            >

                            <button
                                type="submit"
                                class="detail-action-btn detail-action-approve"
                                onclick="return confirm('Apakah Anda yakin ingin menyetujui pengajuan ini?')"
                            >

                                <i class="bi bi-check-lg me-1"></i>

                                Setujui

                            </button>

                        </form>


                        <form
                            action="bookings.php"
                            method="POST"
                        >

                            <input
                                type="hidden"
                                name="booking_id"
                                value="<?= htmlspecialchars(
                                    $booking['id']
                                ) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="reject"
                            >

                            <button
                                type="submit"
                                class="detail-action-btn detail-action-reject"
                                onclick="return confirm('Apakah Anda yakin ingin menolak pengajuan ini?')"
                            >

                                <i class="bi bi-x-lg me-1"></i>

                                Tolak

                            </button>

                        </form>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>


<?php require '../config/footer.php'; ?>