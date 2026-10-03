<?php

require '../config/auth.php';
require '../config/database.php';

require_login();


/* =====================================================
   AMBIL DATA PENGAJUAN USER
   ===================================================== */

$userId = (int) ($_SESSION['user']['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        b.id,
        b.status,
        b.tanggal,
        b.jam_mulai,
        b.jam_selesai,
        b.keperluan,
        r.nama_ruangan,
        r.lokasi
    FROM bookings b
    INNER JOIN rooms r ON r.id = b.room_id
    WHERE b.user_id = ?
    ORDER BY b.id DESC
");

$stmt->execute([$userId]);

$bookings = $stmt->fetchAll();


/* =====================================================
   HITUNG NOTIFIKASI
   ===================================================== */

$totalNotifications = count($bookings);

$waitingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

foreach ($bookings as $booking) {

    if ($booking['status'] === 'menunggu') {
        $waitingCount++;
    }

    if ($booking['status'] === 'disetujui') {
        $approvedCount++;
    }

    if ($booking['status'] === 'ditolak') {
        $rejectedCount++;
    }
}


$title = 'Notifikasi';

require '../config/header.php';

?>


<style>

/* =====================================================
   HALAMAN NOTIFIKASI
   ===================================================== */

.notification-page {
    max-width: 900px;
    margin: 0 auto 40px;
}


/* =====================================================
   HEADER
   ===================================================== */

.notification-header {
    margin-bottom: 22px;
}

.notification-header h1 {
    margin: 3px 0 5px;
    font-size: 25px;
}

.notification-header p {
    margin: 0;
    color: var(--muted);
    font-size: 14px;
}


/* =====================================================
   RINGKASAN
   ===================================================== */

.notification-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 24px;
}

.notification-summary-card {
    display: flex;
    align-items: center;
    gap: 12px;

    padding: 15px 17px;

    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);

    box-shadow: var(--shadow);
}

.notification-summary-icon {
    width: 40px;
    height: 40px;

    flex: 0 0 40px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    font-size: 17px;
}

.notification-summary-icon-yellow {
    background: var(--yellow-light);
    color: var(--yellow);
}

.notification-summary-icon-green {
    background: var(--green-light);
    color: var(--green);
}

.notification-summary-icon-red {
    background: var(--red-light);
    color: var(--red);
}

.notification-summary-content span {
    display: block;

    color: var(--muted);

    font-size: 11px;

    margin-bottom: 2px;
}

.notification-summary-content strong {
    display: block;

    color: var(--text);

    font-size: 19px;
}


/* =====================================================
   LIST
   ===================================================== */

.notification-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}


/* =====================================================
   ITEM
   ===================================================== */

.notification-item {
    display: flex;

    align-items: flex-start;

    gap: 14px;

    padding: 17px 18px;

    background: var(--white);

    border: 1px solid var(--border);

    border-radius: var(--radius);

    box-shadow: var(--shadow);

    text-decoration: none;

    color: var(--text);

    transition:
        transform .2s ease,
        border-color .2s ease,
        box-shadow .2s ease;
}

.notification-item:hover {
    transform: translateY(-2px);

    border-color: #d3e4da;

    box-shadow: 0 8px 25px rgba(20, 40, 28, .07);

    color: var(--text);
}


/* =====================================================
   ICON
   ===================================================== */

.notification-item-icon {
    width: 43px;
    height: 43px;

    flex: 0 0 43px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 12px;

    font-size: 18px;
}

.notification-item-icon.waiting {
    background: var(--yellow-light);
    color: var(--yellow);
}

.notification-item-icon.approved {
    background: var(--green-light);
    color: var(--green);
}

.notification-item-icon.rejected {
    background: var(--red-light);
    color: var(--red);
}


/* =====================================================
   CONTENT
   ===================================================== */

.notification-item-content {
    min-width: 0;
    flex: 1;
}

.notification-item-title {
    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 15px;

    margin-bottom: 4px;
}

.notification-item-title h3 {
    margin: 0;

    font-size: 14.5px;

    font-weight: 700;
}

.notification-item-time {
    color: var(--muted);

    font-size: 10.5px;

    white-space: nowrap;
}

.notification-item-content p {
    margin: 0 0 8px;

    color: var(--text-soft);

    font-size: 12.5px;

    line-height: 1.5;
}

.notification-item-meta {
    display: flex;

    flex-wrap: wrap;

    gap: 7px;
}

.notification-item-meta span {
    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 5px 8px;

    background: var(--background);

    border-radius: 7px;

    color: var(--muted);

    font-size: 10.5px;
}

.notification-item-meta i {
    color: var(--green);
}


/* =====================================================
   ARROW
   ===================================================== */

.notification-item-arrow {
    width: 28px;
    height: 28px;

    flex: 0 0 28px;

    align-self: center;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background: var(--green-light);

    color: var(--green);

    font-size: 12px;
}


/* =====================================================
   EMPTY
   ===================================================== */

.notification-empty {
    padding: 50px 20px;

    text-align: center;

    background: var(--white);

    border: 1px solid var(--border);

    border-radius: var(--radius);

    box-shadow: var(--shadow);
}

.notification-empty-icon {
    width: 56px;
    height: 56px;

    margin: 0 auto 13px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 16px;

    background: var(--green-light);

    color: var(--green);

    font-size: 25px;
}

.notification-empty h3 {
    margin: 0 0 5px;

    font-size: 17px;
}

.notification-empty p {
    margin: 0;

    color: var(--muted);

    font-size: 13px;
}


/* =====================================================
   RESPONSIVE
   ===================================================== */

@media (max-width: 700px) {

    .notification-summary {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .notification-item {
        padding: 15px;
    }

    .notification-item-title {
        align-items: flex-start;

        flex-direction: column;

        gap: 3px;
    }

}

@media (max-width: 480px) {

    .notification-item-icon {
        width: 39px;
        height: 39px;

        flex-basis: 39px;

        font-size: 16px;
    }

    .notification-item {
        gap: 11px;
    }

    .notification-item-arrow {
        display: none;
    }

}

</style>


<div class="notification-page">


    <!-- =========================
         HEADER
         ========================= -->

    <div class="notification-header">

        <span class="heading-label">
            NOTIFIKASI
        </span>

        <h1>
            Pemberitahuan
        </h1>

        <p>
            Informasi terbaru mengenai pengajuan peminjaman ruanganmu.
        </p>

    </div>


    <!-- =========================
         RINGKASAN
         ========================= -->

    <div class="notification-summary">


        <div class="notification-summary-card">

            <div class="notification-summary-icon notification-summary-icon-yellow">

                <i class="bi bi-clock"></i>

            </div>

            <div class="notification-summary-content">

                <span>
                    Menunggu
                </span>

                <strong>
                    <?= $waitingCount ?>
                </strong>

            </div>

        </div>


        <div class="notification-summary-card">

            <div class="notification-summary-icon notification-summary-icon-green">

                <i class="bi bi-check-circle"></i>

            </div>

            <div class="notification-summary-content">

                <span>
                    Disetujui
                </span>

                <strong>
                    <?= $approvedCount ?>
                </strong>

            </div>

        </div>


        <div class="notification-summary-card">

            <div class="notification-summary-icon notification-summary-icon-red">

                <i class="bi bi-x-circle"></i>

            </div>

            <div class="notification-summary-content">

                <span>
                    Ditolak
                </span>

                <strong>
                    <?= $rejectedCount ?>
                </strong>

            </div>

        </div>


    </div>


    <!-- =========================
         DAFTAR NOTIFIKASI
         ========================= -->

    <?php if (empty($bookings)): ?>


        <div class="notification-empty">

            <div class="notification-empty-icon">

                <i class="bi bi-bell"></i>

            </div>

            <h3>
                Belum Ada Notifikasi
            </h3>

            <p>
                Belum ada informasi mengenai pengajuan peminjamanmu.
            </p>

        </div>


    <?php else: ?>


        <div class="notification-list">


            <?php foreach ($bookings as $booking): ?>


                <?php

                if ($booking['status'] === 'disetujui') {

                    $iconClass = 'approved';
                    $icon = 'bi-check-circle-fill';
                    $titleText = 'Pengajuan Disetujui';
                    $message = 'Pengajuan peminjaman ruangan kamu telah disetujui oleh administrator.';

                } elseif ($booking['status'] === 'ditolak') {

                    $iconClass = 'rejected';
                    $icon = 'bi-x-circle-fill';
                    $titleText = 'Pengajuan Ditolak';
                    $message = 'Pengajuan peminjaman ruangan kamu ditolak oleh administrator.';

                } else {

                    $iconClass = 'waiting';
                    $icon = 'bi-clock-fill';
                    $titleText = 'Pengajuan Menunggu';
                    $message = 'Pengajuan peminjaman ruangan kamu sedang menunggu pemeriksaan administrator.';

                }

                ?>


                <a
                    href="booking_detail.php?id=<?= (int) $booking['id'] ?>"
                    class="notification-item"
                >


                    <div class="notification-item-icon <?= $iconClass ?>">

                        <i class="bi <?= $icon ?>"></i>

                    </div>


                    <div class="notification-item-content">


                        <div class="notification-item-title">

                            <h3>
                                <?= $titleText ?>
                            </h3>

                            <span class="notification-item-time">

                                <?= date(
                                    'd-m-Y',
                                    strtotime($booking['tanggal'])
                                ) ?>

                            </span>

                        </div>


                        <p>
                            <?= $message ?>
                        </p>


                        <div class="notification-item-meta">


                            <span>

                                <i class="bi bi-building"></i>

                                <?= htmlspecialchars(
                                    $booking['nama_ruangan']
                                ) ?>

                            </span>


                            <span>

                                <i class="bi bi-calendar3"></i>

                                <?= date(
                                    'd-m-Y',
                                    strtotime($booking['tanggal'])
                                ) ?>

                            </span>


                            <span>

                                <i class="bi bi-clock"></i>

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

                            </span>


                        </div>


                    </div>


                    <div class="notification-item-arrow">

                        <i class="bi bi-arrow-right"></i>

                    </div>


                </a>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</div>


<?php require '../config/footer.php'; ?>