<?php

require '../config/auth.php';
require '../config/database.php';

require_login();


/* =====================================================
   AMBIL ID PENGAJUAN
   ===================================================== */

$bookingId = (int) ($_GET['id'] ?? $_POST['booking_id'] ?? 0);
$userId = (int) ($_SESSION['user']['id'] ?? 0);

if ($bookingId <= 0) {
    header('Location: my_bookings.php');
    exit;
}


/* =====================================================
   PROSES PEMBATALAN
   ===================================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'cancel'
) {

    $stmtCancel = $pdo->prepare("
        UPDATE bookings
        SET status = 'dibatalkan'
        WHERE id = ?
          AND user_id = ?
          AND status = 'menunggu'
    ");

    $stmtCancel->execute([
        $bookingId,
        $userId
    ]);

    header(
        'Location: booking_detail.php?id='
        . $bookingId
        . '&cancel=success'
    );

    exit;
}


/* =====================================================
   AMBIL DATA PENGAJUAN
   HANYA MILIK USER YANG LOGIN
   ===================================================== */

$stmt = $pdo->prepare("
    SELECT
        b.*,
        r.nama_ruangan,
        r.lokasi,
        r.kapasitas,
        r.fasilitas,
        r.foto
    FROM bookings b
    INNER JOIN rooms r ON r.id = b.room_id
    WHERE b.id = ?
      AND b.user_id = ?
    LIMIT 1
");

$stmt->execute([
    $bookingId,
    $userId
]);

$booking = $stmt->fetch();


/* =====================================================
   JIKA DATA TIDAK DITEMUKAN
   ===================================================== */

if (!$booking) {
    header('Location: my_bookings.php');
    exit;
}


/* =====================================================
   STATUS
   ===================================================== */

$statusClass = 'status-waiting';
$statusText = 'Menunggu';

if ($booking['status'] === 'disetujui') {

    $statusClass = 'status-approved';
    $statusText = 'Disetujui';

} elseif ($booking['status'] === 'ditolak') {

    $statusClass = 'status-rejected';
    $statusText = 'Ditolak';

} elseif ($booking['status'] === 'dibatalkan') {

    $statusClass = 'status-rejected';
    $statusText = 'Dibatalkan';
}


$title = 'Detail Pengajuan';

require '../config/header.php';

?>


<style>

/* =====================================================
   DETAIL PENGAJUAN USER
   ===================================================== */

.user-booking-detail-page {
    max-width: 900px;
    margin: 0 auto 40px;
}


/* =====================================================
   HEADER
   ===================================================== */

.user-booking-detail-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 20px;

    margin-bottom: 20px;
}


.user-booking-detail-header-left {
    display: flex;
    align-items: center;

    gap: 12px;
}


.user-booking-back {
    width: 38px;
    height: 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border: 1px solid var(--border);
    border-radius: 10px;

    background: var(--white);
    color: var(--text-soft);

    text-decoration: none;

    transition:
        background .2s ease,
        color .2s ease;
}


.user-booking-back:hover {
    background: var(--green-light);
    color: var(--green);
}


.user-booking-detail-title span {
    display: block;

    color: var(--muted);

    font-size: 11px;

    margin-bottom: 3px;
}


.user-booking-detail-title h1 {
    margin: 0;

    font-size: 23px;
}


/* =====================================================
   CARD UTAMA
   ===================================================== */

.user-booking-detail-card {
    background: var(--white);

    border: 1px solid var(--border);
    border-radius: var(--radius);

    box-shadow: var(--shadow);

    overflow: hidden;
}


/* =====================================================
   RUANGAN
   ===================================================== */

.user-booking-detail-room {
    display: grid;

    grid-template-columns: 200px 1fr;

    gap: 24px;

    padding: 22px;

    border-bottom: 1px solid var(--border);
}


.user-booking-detail-photo {
    width: 200px;
    height: 140px;

    border-radius: 13px;

    overflow: hidden;

    background: var(--green-light);
}


.user-booking-detail-photo img {
    width: 100%;
    height: 100%;

    object-fit: cover;
}


.user-booking-detail-photo-empty {
    width: 100%;
    height: 100%;

    display: flex;
    align-items: center;
    justify-content: center;

    color: var(--green);

    font-size: 34px;
}


.user-booking-detail-room-info {
    display: flex;

    flex-direction: column;

    justify-content: center;
}


.user-booking-detail-room-info .heading-label {
    margin-bottom: 5px;
}


.user-booking-detail-room-info h2 {
    margin: 0 0 5px;

    font-size: 20px;
}


.user-booking-detail-location {
    margin: 0 0 10px;

    color: var(--muted);

    font-size: 13px;
}


.user-booking-detail-location i {
    margin-right: 3px;
}


.user-booking-detail-room-meta {
    display: flex;

    flex-wrap: wrap;

    gap: 8px;
}


.user-booking-detail-meta-item {
    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding: 6px 9px;

    background: var(--green-light);

    color: var(--green);

    border-radius: 8px;

    font-size: 11.5px;

    font-weight: 600;
}


/* =====================================================
   SECTION
   ===================================================== */

.user-booking-detail-section {
    padding: 22px;

    border-bottom: 1px solid var(--border);
}


.user-booking-detail-section:last-child {
    border-bottom: 0;
}


.user-booking-detail-section-title {
    display: flex;

    align-items: center;

    gap: 9px;

    margin-bottom: 16px;
}


.user-booking-detail-section-title i {
    color: var(--green);

    font-size: 17px;
}


.user-booking-detail-section-title h3 {
    margin: 0;

    font-size: 16px;
}


/* =====================================================
   DETAIL GRID
   ===================================================== */

.user-booking-detail-grid {
    display: grid;

    grid-template-columns: repeat(2, 1fr);

    gap: 14px;
}


.user-booking-detail-item {
    padding: 13px 15px;

    background: var(--background);

    border-radius: 10px;
}


.user-booking-detail-item span {
    display: block;

    color: var(--muted);

    font-size: 11px;

    margin-bottom: 4px;
}


.user-booking-detail-item strong {
    display: block;

    color: var(--text);

    font-size: 13.5px;

    font-weight: 600;
}


/* =====================================================
   KEPERLUAN
   ===================================================== */

.user-booking-detail-purpose {
    padding: 14px 16px;

    background: var(--background);

    border-radius: 10px;

    color: var(--text-soft);

    font-size: 13.5px;

    line-height: 1.6;
}


/* =====================================================
   FASILITAS
   ===================================================== */

.user-booking-detail-facility {
    padding: 14px 16px;

    background: var(--background);

    border-radius: 10px;

    color: var(--text-soft);

    font-size: 13px;

    line-height: 1.6;
}


/* =====================================================
   STATUS
   ===================================================== */

.user-booking-detail-status {
    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 15px;
}


.user-booking-detail-status
.user-booking-detail-section-title {
    margin-bottom: 0;
}


/* =====================================================
   STATUS INFO
   ===================================================== */

.user-booking-status-info {
    margin-top: 16px;

    padding: 13px 15px;

    border-radius: 10px;

    font-size: 12.5px;

    line-height: 1.5;
}


.user-booking-status-info.waiting {
    background: var(--yellow-light);

    color: var(--yellow);
}


.user-booking-status-info.approved {
    background: var(--green-light);

    color: var(--green);
}


.user-booking-status-info.rejected {
    background: var(--red-light);

    color: var(--red);
}


.user-booking-status-info.cancelled {
    background: var(--red-light);

    color: var(--red);
}


.user-booking-status-info i {
    margin-right: 5px;
}


/* =====================================================
   NOTIFIKASI PEMBATALAN
   ===================================================== */

.user-booking-cancel-success {
    display: flex;

    align-items: center;

    gap: 9px;

    margin-bottom: 16px;

    padding: 12px 14px;

    background: var(--green-light);

    color: var(--green);

    border-radius: 10px;

    font-size: 12.5px;
}


.user-booking-cancel-success i {
    font-size: 16px;
}


/* =====================================================
   ACTION
   ===================================================== */

.user-booking-detail-actions {
    display: flex;

    justify-content: flex-end;

    gap: 9px;

    margin-top: 18px;
}


.user-booking-detail-actions a,
.user-booking-detail-actions button {
    min-width: 130px;
}


.user-booking-cancel-form {
    margin: 0;
}


.user-booking-cancel-button {
    border: 1px solid var(--red);

    background: var(--white);

    color: var(--red);

    cursor: pointer;

    border-radius: 8px;

    padding: 9px 14px;

    font-size: 13px;

    font-weight: 600;

    transition:
        background .2s ease,
        color .2s ease;
}


.user-booking-cancel-button:hover {
    background: var(--red);

    color: var(--white);
}


/* =====================================================
   RESPONSIVE
   ===================================================== */

@media (max-width: 700px) {

    .user-booking-detail-room {
        grid-template-columns: 1fr;
    }


    .user-booking-detail-photo {
        width: 100%;
        height: 190px;
    }


    .user-booking-detail-grid {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 600px) {

    .user-booking-detail-page {
        margin-top: 10px;
    }


    .user-booking-detail-header {
        align-items: flex-start;
    }


    .user-booking-detail-title h1 {
        font-size: 20px;
    }


    .user-booking-detail-room,
    .user-booking-detail-section {
        padding: 17px;
    }


    .user-booking-detail-status {
        align-items: flex-start;

        flex-direction: column;
    }


    .user-booking-detail-actions {
        flex-direction: column;
    }


    .user-booking-detail-actions a,
    .user-booking-detail-actions button {
        width: 100%;
    }

}

</style>


<div class="user-booking-detail-page">


    <!-- =========================
         HEADER
         ========================= -->

    <div class="user-booking-detail-header">

        <div class="user-booking-detail-header-left">

            <a
                href="my_bookings.php"
                class="user-booking-back"
                title="Kembali"
            >

                <i class="bi bi-arrow-left"></i>

            </a>


            <div class="user-booking-detail-title">

                <span>
                    DETAIL PENGAJUAN
                </span>

                <h1>
                    Pengajuan Peminjaman
                </h1>

            </div>

        </div>


        <span class="status-badge <?= $statusClass ?>">
            <?= $statusText ?>
        </span>

    </div>


    <!-- =========================
         CARD
         ========================= -->

    <article class="user-booking-detail-card">


        <!-- =========================
             RUANGAN
             ========================= -->

        <section class="user-booking-detail-room">


            <div class="user-booking-detail-photo">

                <?php if (!empty($booking['foto'])): ?>

                    <img
                        src="/peminjaman_ruangan_php/assets/rooms/<?= htmlspecialchars($booking['foto']) ?>"
                        alt="<?= htmlspecialchars($booking['nama_ruangan']) ?>"
                    >

                <?php else: ?>

                    <div class="user-booking-detail-photo-empty">

                        <i class="bi bi-building"></i>

                    </div>

                <?php endif; ?>

            </div>


            <div class="user-booking-detail-room-info">

                <span class="heading-label">
                    RUANGAN
                </span>


                <h2>
                    <?= htmlspecialchars($booking['nama_ruangan']) ?>
                </h2>


                <p class="user-booking-detail-location">

                    <i class="bi bi-geo-alt"></i>

                    <?= !empty($booking['lokasi'])
                        ? htmlspecialchars($booking['lokasi'])
                        : 'Lokasi tidak tersedia'
                    ?>

                </p>


                <div class="user-booking-detail-room-meta">


                    <span class="user-booking-detail-meta-item">

                        <i class="bi bi-people"></i>

                        Kapasitas
                        <?= (int) $booking['kapasitas'] ?> orang

                    </span>


                    <?php if (!empty($booking['fasilitas'])): ?>

                        <span class="user-booking-detail-meta-item">

                            <i class="bi bi-check2-circle"></i>

                            Fasilitas tersedia

                        </span>

                    <?php endif; ?>


                </div>

            </div>


        </section>


        <!-- =========================
             JADWAL
             ========================= -->

        <section class="user-booking-detail-section">


            <div class="user-booking-detail-section-title">

                <i class="bi bi-calendar3"></i>

                <h3>
                    Jadwal Peminjaman
                </h3>

            </div>


            <div class="user-booking-detail-grid">


                <div class="user-booking-detail-item">

                    <span>
                        Tanggal
                    </span>

                    <strong>

                        <?= date(
                            'd-m-Y',
                            strtotime($booking['tanggal'])
                        ) ?>

                    </strong>

                </div>


                <div class="user-booking-detail-item">

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


        </section>


        <!-- =========================
             KEPERLUAN
             ========================= -->

        <section class="user-booking-detail-section">


            <div class="user-booking-detail-section-title">

                <i class="bi bi-card-text"></i>

                <h3>
                    Keperluan Peminjaman
                </h3>

            </div>


            <div class="user-booking-detail-purpose">

                <?= !empty($booking['keperluan'])
                    ? nl2br(
                        htmlspecialchars(
                            $booking['keperluan']
                        )
                    )
                    : 'Tidak ada keperluan yang dicantumkan.'
                ?>

            </div>


        </section>


        <!-- =========================
             FASILITAS
             ========================= -->

        <?php if (!empty($booking['fasilitas'])): ?>

            <section class="user-booking-detail-section">


                <div class="user-booking-detail-section-title">

                    <i class="bi bi-grid"></i>

                    <h3>
                        Fasilitas Ruangan
                    </h3>

                </div>


                <div class="user-booking-detail-facility">

                    <?= nl2br(
                        htmlspecialchars(
                            $booking['fasilitas']
                        )
                    ) ?>

                </div>


            </section>

        <?php endif; ?>


        <!-- =========================
             STATUS
             ========================= -->

        <section class="user-booking-detail-section">


            <?php if (
                isset($_GET['cancel'])
                && $_GET['cancel'] === 'success'
            ): ?>

                <div class="user-booking-cancel-success">

                    <i class="bi bi-check-circle-fill"></i>

                    Pengajuan berhasil dibatalkan.

                </div>

            <?php endif; ?>


            <div class="user-booking-detail-status">


                <div class="user-booking-detail-section-title">

                    <i class="bi bi-info-circle"></i>

                    <h3>
                        Status Pengajuan
                    </h3>

                </div>


                <span class="status-badge <?= $statusClass ?>">
                    <?= $statusText ?>
                </span>


            </div>


            <?php if ($booking['status'] === 'menunggu'): ?>


                <div class="user-booking-status-info waiting">

                    <i class="bi bi-clock-fill"></i>

                    Pengajuan kamu sedang menunggu pemeriksaan
                    dan persetujuan dari administrator.

                </div>


            <?php elseif ($booking['status'] === 'disetujui'): ?>


                <div class="user-booking-status-info approved">

                    <i class="bi bi-check-circle-fill"></i>

                    Pengajuan peminjaman kamu telah disetujui.
                    Silakan gunakan ruangan sesuai jadwal yang telah diajukan.

                </div>


            <?php elseif ($booking['status'] === 'ditolak'): ?>


                <div class="user-booking-status-info rejected">

                    <i class="bi bi-x-circle-fill"></i>

                    Pengajuan peminjaman kamu ditolak oleh administrator.

                </div>


            <?php elseif ($booking['status'] === 'dibatalkan'): ?>


                <div class="user-booking-status-info cancelled">

                    <i class="bi bi-x-circle-fill"></i>

                    Pengajuan ini telah dibatalkan oleh kamu.

                </div>


            <?php endif; ?>


            <div class="user-booking-detail-actions">


                <a
                    href="my_bookings.php"
                    class="btn btn-secondary"
                >

                    <i class="bi bi-arrow-left"></i>

                    Kembali

                </a>


                <?php if ($booking['status'] === 'menunggu'): ?>


                    <form
                        method="POST"
                        class="user-booking-cancel-form"
                        onsubmit="return confirm('Apakah kamu yakin ingin membatalkan pengajuan ini?');"
                    >

                        <input
                            type="hidden"
                            name="booking_id"
                            value="<?= (int) $booking['id'] ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="cancel"
                        >


                        <button
                            type="submit"
                            class="user-booking-cancel-button"
                        >

                            <i class="bi bi-x-circle"></i>

                            Batalkan Pengajuan

                        </button>

                    </form>


                <?php endif; ?>


                <?php if ($booking['status'] !== 'menunggu'): ?>


                    <a
                        href="booking.php"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-plus-lg"></i>

                        Ajukan Lagi

                    </a>

                <?php endif; ?>


            </div>


        </section>


    </article>


</div>


<?php require '../config/footer.php'; ?>