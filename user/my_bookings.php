<?php

require '../config/auth.php';
require '../config/database.php';

require_login();


/* =====================================================
   DATA USER
   ===================================================== */

$userId = (int) ($_SESSION['user']['id'] ?? 0);


/* =====================================================
   NOTIFIKASI TOAST
   ===================================================== */

$showCancelToast = (
    ($_GET['cancel'] ?? '') === 'success'
);


/* =====================================================
   PROSES PEMBATALAN
   ===================================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'cancel'
) {

    $bookingId = (int) ($_POST['booking_id'] ?? 0);

    $cancelSuccess = false;


    if ($bookingId > 0) {

        /*
         * Hanya pengajuan milik user yang login
         * dan masih berstatus menunggu yang dapat dibatalkan.
         */

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


        /*
         * Pastikan memang ada data yang berubah.
         */

        if ($stmtCancel->rowCount() > 0) {
            $cancelSuccess = true;
        }
    }


    if ($cancelSuccess) {

        header('Location: my_bookings.php?cancel=success');
        exit;

    } else {

        header('Location: my_bookings.php?cancel=failed');
        exit;
    }
}


/* =====================================================
   STATUS FILTER
   ===================================================== */

$filterStatus = $_GET['status'] ?? '';

$allowedStatuses = [
    '',
    'menunggu',
    'disetujui',
    'ditolak',
    'dibatalkan'
];

if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = '';
}


/* =====================================================
   PENCARIAN
   ===================================================== */

$search = trim($_GET['search'] ?? '');


/* =====================================================
   FILTER TANGGAL
   ===================================================== */

$filterTanggal = $_GET['tanggal'] ?? '';

if (
    $filterTanggal !== ''
    && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterTanggal)
) {
    $filterTanggal = '';
}


/* =====================================================
   STATISTIK SEMUA PENGAJUAN
   ===================================================== */

$stmtStats = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'menunggu') AS menunggu,
        SUM(status = 'disetujui') AS disetujui,
        SUM(status = 'ditolak') AS ditolak,
        SUM(status = 'dibatalkan') AS dibatalkan
    FROM bookings
    WHERE user_id = ?
");

$stmtStats->execute([
    $userId
]);

$stats = $stmtStats->fetch();


$totalBookings = (int) ($stats['total'] ?? 0);
$totalWaiting = (int) ($stats['menunggu'] ?? 0);
$totalApproved = (int) ($stats['disetujui'] ?? 0);
$totalRejected = (int) ($stats['ditolak'] ?? 0);
$totalCancelled = (int) ($stats['dibatalkan'] ?? 0);


/* =====================================================
   QUERY PENGAJUAN
   ===================================================== */

$sql = "
    SELECT
        b.*,
        r.nama_ruangan,
        r.lokasi,
        r.kapasitas,
        r.foto
    FROM bookings b
    INNER JOIN rooms r
        ON r.id = b.room_id
    WHERE b.user_id = ?
";

$params = [
    $userId
];


/* =====================================================
   FILTER STATUS
   ===================================================== */

if ($filterStatus !== '') {

    $sql .= "
        AND b.status = ?
    ";

    $params[] = $filterStatus;
}


/* =====================================================
   FILTER PENCARIAN
   ===================================================== */

if ($search !== '') {

    $sql .= "
        AND (
            r.nama_ruangan LIKE ?
            OR r.lokasi LIKE ?
            OR b.keperluan LIKE ?
        )
    ";

    $searchLike = '%' . $search . '%';

    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}


/* =====================================================
   FILTER TANGGAL
   ===================================================== */

if ($filterTanggal !== '') {

    $sql .= "
        AND b.tanggal = ?
    ";

    $params[] = $filterTanggal;
}


/* =====================================================
   URUTAN
   ===================================================== */

$sql .= "
    ORDER BY
        b.tanggal DESC,
        b.jam_mulai DESC,
        b.id DESC
";


$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$bookings = $stmt->fetchAll();


$title = 'Pengajuan Saya';

require '../config/header.php';

?>

<style>

/* =====================================================
   TOAST NOTIFICATION
   ===================================================== */

.my-bookings-toast-container {

    position: fixed;

    top: 82px;

    right: 24px;

    z-index: 10000;

    width: min(380px, calc(100vw - 32px));

    pointer-events: none;
}


.my-bookings-toast {

    display: flex;

    align-items: flex-start;

    gap: 12px;

    padding: 15px 16px;

    background: var(--white);

    border: 1px solid var(--border);

    border-radius: 14px;

    box-shadow:
        0 12px 35px rgba(20, 40, 28, .14);

    pointer-events: auto;

    animation:
        myBookingsToastShow .3s ease forwards;
}


.my-bookings-toast.hide {

    animation:
        myBookingsToastHide .25s ease forwards;
}


.my-bookings-toast-icon {

    flex: 0 0 38px;

    width: 38px;

    height: 38px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 18px;
}


.my-bookings-toast-success
.my-bookings-toast-icon {

    background: var(--green-light);

    color: var(--green);
}


.my-bookings-toast-error
.my-bookings-toast-icon {

    background: var(--red-light);

    color: var(--red);
}


.my-bookings-toast-content {

    flex: 1;

    min-width: 0;
}


.my-bookings-toast-title {

    margin: 1px 0 4px;

    font-size: 14px;

    font-weight: 700;

    color: var(--text);
}


.my-bookings-toast-message {

    margin: 0;

    color: var(--text-soft);

    font-size: 12.5px;

    line-height: 1.5;
}


.my-bookings-toast-close {

    flex: 0 0 auto;

    border: 0;

    background: transparent;

    color: var(--muted);

    width: 28px;

    height: 28px;

    padding: 0;

    border-radius: 8px;

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;

    transition:
        background .2s ease,
        color .2s ease;
}


.my-bookings-toast-close:hover {

    background: var(--background);

    color: var(--text);
}


@keyframes myBookingsToastShow {

    from {

        opacity: 0;

        transform: translateX(25px);

    }

    to {

        opacity: 1;

        transform: translateX(0);

    }

}


@keyframes myBookingsToastHide {

    from {

        opacity: 1;

        transform: translateX(0);

    }

    to {

        opacity: 0;

        transform: translateX(25px);

    }

}


/* =====================================================
   HALAMAN PENGAJUAN SAYA
   ===================================================== */

.my-bookings-page {

    margin-bottom: 40px;

}


/* =====================================================
   HEADER
   ===================================================== */

.my-bookings-header {

    display: flex;

    align-items: flex-end;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 22px;

}


.my-bookings-header h1 {

    margin: 3px 0 5px;

}


.my-bookings-header p {

    margin: 0;

    color: var(--muted);

    font-size: 14px;

}


.my-bookings-header-action {

    flex-shrink: 0;

}


/* =====================================================
   STATISTIK
   ===================================================== */

.my-bookings-stats {

    display: grid;

    grid-template-columns:
        repeat(5, minmax(0, 1fr));

    gap: 12px;

    margin-bottom: 22px;

}


.my-bookings-stat {

    display: flex;

    align-items: center;

    gap: 11px;

    padding: 13px 14px;

    background: var(--white);

    border: 1px solid var(--border);

    border-radius: 12px;

    box-shadow: var(--shadow);

}


.my-bookings-stat-icon {

    width: 36px;

    height: 36px;

    flex: 0 0 36px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 10px;

    font-size: 16px;

}


.my-bookings-stat-icon.green {

    background: var(--green-light);

    color: var(--green);

}


.my-bookings-stat-icon.yellow {

    background: var(--yellow-light);

    color: var(--yellow);

}


.my-bookings-stat-icon.blue {

    background: var(--blue-light);

    color: var(--blue);

}


.my-bookings-stat-icon.red {

    background: var(--red-light);

    color: var(--red);

}


.my-bookings-stat-content {

    min-width: 0;

}


.my-bookings-stat-content strong {

    display: block;

    font-size: 18px;

    line-height: 1.1;

    margin-bottom: 3px;

}


.my-bookings-stat-content span {

    display: block;

    color: var(--muted);

    font-size: 10.5px;

}


/* =====================================================
   FILTER
   ===================================================== */

.my-bookings-filter {

    display: grid;

    grid-template-columns:
        minmax(0, 1.8fr)
        minmax(150px, .9fr)
        minmax(150px, .9fr)
        auto;

    align-items: end;

    gap: 11px;

    padding: 16px;

    margin-bottom: 20px;

    background: var(--white);

    border: 1px solid var(--border);

    border-radius: 14px;

    box-shadow: var(--shadow);

}


.my-bookings-filter-group {

    min-width: 0;

}


.my-bookings-filter-label {

    display: block;

    margin-bottom: 6px;

    color: var(--text-soft);

    font-size: 11px;

    font-weight: 600;

}


.my-bookings-filter-actions {

    display: flex;

    gap: 7px;

}


.my-bookings-filter-actions .btn {

    white-space: nowrap;

}


/* =====================================================
   HASIL FILTER
   ===================================================== */

.my-bookings-filter-result {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 13px;

}


.my-bookings-filter-result p {

    margin: 0;

    color: var(--muted);

    font-size: 12.5px;

}


.my-bookings-filter-result strong {

    color: var(--text);

}


/* =====================================================
   CARD PENGAJUAN
   ===================================================== */

.my-bookings-list {

    display: flex;

    flex-direction: column;

    gap: 13px;

}


.my-booking-card {

    display: grid;

    grid-template-columns:
        170px
        minmax(0, 1fr)
        auto;

    gap: 18px;

    padding: 16px;

    background: var(--white);

    border: 1px solid var(--border);

    border-radius: var(--radius);

    box-shadow: var(--shadow);

    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease;

}


.my-booking-card:hover {

    transform: translateY(-2px);

    border-color: #d7e5dc;

    box-shadow:
        0 8px 26px rgba(20, 40, 28, .07);

}


/* =====================================================
   FOTO
   ===================================================== */

.my-booking-photo {

    width: 170px;

    height: 125px;

    overflow: hidden;

    border-radius: 12px;

    background: var(--green-light);

}


.my-booking-photo img {

    width: 100%;

    height: 100%;

    object-fit: cover;

    display: block;

}


.my-booking-photo-empty {

    width: 100%;

    height: 100%;

    display: flex;

    align-items: center;

    justify-content: center;

    color: var(--green);

    font-size: 30px;

}


/* =====================================================
   CONTENT
   ===================================================== */

.my-booking-content {

    min-width: 0;

    display: flex;

    flex-direction: column;

    justify-content: center;

}


.my-booking-content-top {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 5px;

}


.my-booking-content h3 {

    margin: 0;

    font-size: 17px;

    line-height: 1.3;

}


.my-booking-location {

    margin: 0 0 8px;

    color: var(--muted);

    font-size: 12px;

}


.my-booking-location i {

    margin-right: 3px;

}


.my-booking-info {

    display: flex;

    flex-wrap: wrap;

    gap: 7px;

    margin-bottom: 7px;

}


.my-booking-info-item {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    color: var(--text-soft);

    font-size: 11.5px;

}


.my-booking-info-item i {

    color: var(--green);

}


.my-booking-purpose {

    margin: 0;

    color: var(--muted);

    font-size: 11.5px;

    line-height: 1.5;

    display: -webkit-box;

    -webkit-line-clamp: 2;

    -webkit-box-orient: vertical;

    overflow: hidden;

}


/* =====================================================
   ACTION
   ===================================================== */

.my-booking-actions {

    display: flex;

    flex-direction: column;

    justify-content: center;

    align-items: stretch;

    gap: 7px;

    min-width: 125px;

}


.my-booking-detail-button {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 6px;

    padding: 8px 12px;

    border-radius: 8px;

    background: var(--green-light);

    color: var(--green);

    border: 1px solid transparent;

    text-decoration: none;

    font-size: 11.5px;

    font-weight: 600;

    transition:
        background .2s ease,
        color .2s ease;

}


.my-booking-detail-button:hover {

    background: var(--green);

    color: var(--white);

}


.my-booking-cancel-form {

    margin: 0;

}


.my-booking-cancel-button {

    width: 100%;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 6px;

    padding: 8px 12px;

    border-radius: 8px;

    border: 1px solid #f0caca;

    background: var(--red-light);

    color: var(--red);

    font-size: 11.5px;

    font-weight: 600;

    cursor: pointer;

    transition:
        background .2s ease,
        color .2s ease,
        border-color .2s ease;

}


.my-booking-cancel-button:hover {

    background: var(--red);

    color: var(--white);

    border-color: var(--red);

}


/* =====================================================
   STATUS MESSAGE
   ===================================================== */

.my-booking-status-message {

    margin-top: 8px;

    padding: 8px 10px;

    border-radius: 8px;

    font-size: 10.5px;

    line-height: 1.4;

}


.my-booking-status-message.waiting {

    background: var(--yellow-light);

    color: var(--yellow);

}


.my-booking-status-message.approved {

    background: var(--green-light);

    color: var(--green);

}


.my-booking-status-message.rejected,
.my-booking-status-message.cancelled {

    background: var(--red-light);

    color: var(--red);

}


/* =====================================================
   EMPTY STATE
   ===================================================== */

.my-bookings-empty {

    padding: 55px 25px;

    text-align: center;

    background: var(--white);

    border: 1px solid var(--border);

    border-radius: var(--radius);

    box-shadow: var(--shadow);

}


.my-bookings-empty-icon {

    width: 58px;

    height: 58px;

    margin: 0 auto 13px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 15px;

    background: var(--green-light);

    color: var(--green);

    font-size: 25px;

}


.my-bookings-empty h3 {

    margin: 0 0 5px;

    font-size: 17px;

}


.my-bookings-empty p {

    max-width: 420px;

    margin: 0 auto 17px;

    color: var(--muted);

    font-size: 12.5px;

    line-height: 1.5;

}


/* =====================================================
   RESPONSIVE
   ===================================================== */

@media (max-width: 1050px) {

    .my-bookings-stats {

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

    }


    .my-bookings-filter {

        grid-template-columns:
            1fr 1fr;

    }


    .my-bookings-filter-actions {

        grid-column: 1 / -1;

    }

}


@media (max-width: 800px) {

    .my-booking-card {

        grid-template-columns:
            145px
            minmax(0, 1fr);

    }


    .my-booking-photo {

        width: 145px;

        height: 115px;

    }


    .my-booking-actions {

        grid-column: 1 / -1;

        flex-direction: row;

        min-width: 0;

    }


    .my-booking-actions > a,
    .my-booking-actions > form {

        flex: 1;

    }


    .my-booking-cancel-button {

        width: 100%;

    }

}


@media (max-width: 650px) {

    .my-bookings-header {

        align-items: flex-start;

        flex-direction: column;

    }


    .my-bookings-header-action {

        width: 100%;

    }


    .my-bookings-header-action .btn {

        width: 100%;

    }


    .my-bookings-stats {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

    }


    .my-bookings-filter {

        grid-template-columns: 1fr;

    }


    .my-bookings-filter-actions {

        grid-column: auto;

    }


    .my-bookings-filter-actions .btn {

        flex: 1;

    }


    .my-booking-card {

        grid-template-columns: 1fr;

    }


    .my-booking-photo {

        width: 100%;

        height: 180px;

    }


    .my-booking-actions {

        grid-column: auto;

    }

}


@media (max-width: 430px) {

    .my-bookings-stats {

        grid-template-columns: 1fr;

    }


    .my-booking-content-top {

        flex-direction: column;

    }


    .my-booking-actions {

        flex-direction: column;

    }


    .my-bookings-toast-container {

        top: 72px;

        right: 16px;

        width: calc(100vw - 32px);

    }

}

</style>


<!-- =====================================================
     TOAST PEMBATALAN BERHASIL
     ===================================================== -->

<?php if ($showCancelToast): ?>

<div class="my-bookings-toast-container">

    <div
        class="my-bookings-toast my-bookings-toast-success"
        id="cancelSuccessToast"
        role="alert"
    >

        <div class="my-bookings-toast-icon">

            <i class="bi bi-check-lg"></i>

        </div>


        <div class="my-bookings-toast-content">

            <div class="my-bookings-toast-title">
                Pengajuan Dibatalkan
            </div>

            <p class="my-bookings-toast-message">
                Pengajuan peminjaman berhasil dibatalkan.
            </p>

        </div>


        <button
            type="button"
            class="my-bookings-toast-close"
            aria-label="Tutup"
            onclick="closeMyBookingsToast()"
        >

            <i class="bi bi-x-lg"></i>

        </button>

    </div>

</div>

<?php endif; ?>


<!-- =====================================================
     TOAST PEMBATALAN GAGAL
     ===================================================== -->

<?php if (($_GET['cancel'] ?? '') === 'failed'): ?>

<div class="my-bookings-toast-container">

    <div
        class="my-bookings-toast my-bookings-toast-error"
        id="cancelErrorToast"
        role="alert"
    >

        <div class="my-bookings-toast-icon">

            <i class="bi bi-exclamation-circle"></i>

        </div>


        <div class="my-bookings-toast-content">

            <div class="my-bookings-toast-title">
                Pembatalan Tidak Berhasil
            </div>

            <p class="my-bookings-toast-message">
                Pengajuan tidak dapat dibatalkan. Pastikan pengajuan masih berstatus menunggu.
            </p>

        </div>


        <button
            type="button"
            class="my-bookings-toast-close"
            aria-label="Tutup"
            onclick="closeMyBookingsToast()"
        >

            <i class="bi bi-x-lg"></i>

        </button>

    </div>

</div>

<?php endif; ?>


<div class="my-bookings-page">


    <!-- =========================
         HEADER
         ========================= -->

    <div class="my-bookings-header">


        <div>

            <span class="heading-label">
                PENGAJUAN SAYA
            </span>

            <h1>
                Riwayat Peminjaman
            </h1>

            <p>
                Lihat status dan kelola pengajuan peminjaman ruangan kamu.
            </p>

        </div>


        <div class="my-bookings-header-action">

            <a
                href="booking.php"
                class="btn btn-primary"
            >

                <i class="bi bi-plus-lg"></i>

                Ajukan Peminjaman

            </a>

        </div>

    </div>


    <!-- =========================
         STATISTIK
         ========================= -->

    <div class="my-bookings-stats">


        <div class="my-bookings-stat">

            <div class="my-bookings-stat-icon green">

                <i class="bi bi-files"></i>

            </div>

            <div class="my-bookings-stat-content">

                <strong>
                    <?= $totalBookings ?>
                </strong>

                <span>
                    Total Pengajuan
                </span>

            </div>

        </div>


        <div class="my-bookings-stat">

            <div class="my-bookings-stat-icon yellow">

                <i class="bi bi-clock"></i>

            </div>

            <div class="my-bookings-stat-content">

                <strong>
                    <?= $totalWaiting ?>
                </strong>

                <span>
                    Menunggu
                </span>

            </div>

        </div>


        <div class="my-bookings-stat">

            <div class="my-bookings-stat-icon blue">

                <i class="bi bi-check-circle"></i>

            </div>

            <div class="my-bookings-stat-content">

                <strong>
                    <?= $totalApproved ?>
                </strong>

                <span>
                    Disetujui
                </span>

            </div>

        </div>


        <div class="my-bookings-stat">

            <div class="my-bookings-stat-icon red">

                <i class="bi bi-x-circle"></i>

            </div>

            <div class="my-bookings-stat-content">

                <strong>
                    <?= $totalRejected ?>
                </strong>

                <span>
                    Ditolak
                </span>

            </div>

        </div>


        <div class="my-bookings-stat">

            <div class="my-bookings-stat-icon red">

                <i class="bi bi-arrow-counterclockwise"></i>

            </div>

            <div class="my-bookings-stat-content">

                <strong>
                    <?= $totalCancelled ?>
                </strong>

                <span>
                    Dibatalkan
                </span>

            </div>

        </div>


    </div>


    <!-- =========================
         FILTER
         ========================= -->

    <form
        method="GET"
        class="my-bookings-filter"
    >


        <div class="my-bookings-filter-group">

            <label class="my-bookings-filter-label">
                Cari Pengajuan
            </label>

            <input
                type="text"
                name="search"
                class="form-control"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Cari ruangan, lokasi, atau keperluan..."
            >

        </div>


        <div class="my-bookings-filter-group">

            <label class="my-bookings-filter-label">
                Status
            </label>

            <select
                name="status"
                class="form-control"
            >

                <option
                    value=""
                    <?= $filterStatus === ''
                        ? 'selected'
                        : '' ?>
                >
                    Semua Status
                </option>

                <option
                    value="menunggu"
                    <?= $filterStatus === 'menunggu'
                        ? 'selected'
                        : '' ?>
                >
                    Menunggu
                </option>

                <option
                    value="disetujui"
                    <?= $filterStatus === 'disetujui'
                        ? 'selected'
                        : '' ?>
                >
                    Disetujui
                </option>

                <option
                    value="ditolak"
                    <?= $filterStatus === 'ditolak'
                        ? 'selected'
                        : '' ?>
                >
                    Ditolak
                </option>

                <option
                    value="dibatalkan"
                    <?= $filterStatus === 'dibatalkan'
                        ? 'selected'
                        : '' ?>
                >
                    Dibatalkan
                </option>

            </select>

        </div>


        <div class="my-bookings-filter-group">

            <label class="my-bookings-filter-label">
                Tanggal Peminjaman
            </label>

            <input
                type="date"
                name="tanggal"
                class="form-control"
                value="<?= htmlspecialchars($filterTanggal) ?>"
            >

        </div>


        <div class="my-bookings-filter-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >

                <i class="bi bi-search"></i>

                Filter

            </button>


            <a
                href="my_bookings.php"
                class="btn btn-secondary"
            >

                <i class="bi bi-arrow-counterclockwise"></i>

                Reset

            </a>

        </div>

    </form>


    <!-- =========================
         HASIL FILTER
         ========================= -->

    <div class="my-bookings-filter-result">

        <p>

            Menampilkan

            <strong>
                <?= count($bookings) ?>
            </strong>

            pengajuan

            <?php if ($filterStatus !== ''): ?>

                dengan status

                <strong>
                    <?= htmlspecialchars($filterStatus) ?>
                </strong>

            <?php endif; ?>

        </p>

    </div>


    <!-- =========================
         LIST
         ========================= -->

    <?php if (!empty($bookings)): ?>


        <div class="my-bookings-list">


            <?php foreach ($bookings as $booking): ?>

                <?php

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

                ?>


                <article class="my-booking-card">


                    <!-- FOTO -->

                    <div class="my-booking-photo">

                        <?php if (!empty($booking['foto'])): ?>

                            <img
                                src="/peminjaman_ruangan_php/assets/rooms/<?= htmlspecialchars($booking['foto']) ?>"
                                alt="<?= htmlspecialchars($booking['nama_ruangan']) ?>"
                            >

                        <?php else: ?>

                            <div class="my-booking-photo-empty">

                                <i class="bi bi-building"></i>

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- CONTENT -->

                    <div class="my-booking-content">


                        <div class="my-booking-content-top">

                            <h3>

                                <?= htmlspecialchars(
                                    $booking['nama_ruangan']
                                ) ?>

                            </h3>


                            <span class="status-badge <?= $statusClass ?>">

                                <?= $statusText ?>

                            </span>

                        </div>


                        <p class="my-booking-location">

                            <i class="bi bi-geo-alt"></i>

                            <?= !empty($booking['lokasi'])
                                ? htmlspecialchars(
                                    $booking['lokasi']
                                )
                                : 'Lokasi tidak tersedia'
                            ?>

                        </p>


                        <div class="my-booking-info">


                            <span class="my-booking-info-item">

                                <i class="bi bi-calendar3"></i>

                                <?= date(
                                    'd-m-Y',
                                    strtotime(
                                        $booking['tanggal']
                                    )
                                ) ?>

                            </span>


                            <span class="my-booking-info-item">

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


                            <span class="my-booking-info-item">

                                <i class="bi bi-people"></i>

                                <?= (int) $booking['kapasitas'] ?>

                                orang

                            </span>


                        </div>


                        <p class="my-booking-purpose">

                            <?= !empty($booking['keperluan'])
                                ? htmlspecialchars(
                                    $booking['keperluan']
                                )
                                : 'Tidak ada keperluan yang dicantumkan.'
                            ?>

                        </p>


                        <?php if ($booking['status'] === 'menunggu'): ?>

                            <div class="my-booking-status-message waiting">

                                <i class="bi bi-clock"></i>

                                Pengajuan sedang menunggu persetujuan administrator.

                            </div>


                        <?php elseif ($booking['status'] === 'disetujui'): ?>

                            <div class="my-booking-status-message approved">

                                <i class="bi bi-check-circle"></i>

                                Pengajuan telah disetujui.

                            </div>


                        <?php elseif ($booking['status'] === 'ditolak'): ?>

                            <div class="my-booking-status-message rejected">

                                <i class="bi bi-x-circle"></i>

                                Pengajuan ditolak oleh administrator.

                            </div>


                        <?php elseif ($booking['status'] === 'dibatalkan'): ?>

                            <div class="my-booking-status-message cancelled">

                                <i class="bi bi-arrow-counterclockwise"></i>

                                Pengajuan telah dibatalkan.

                            </div>

                        <?php endif; ?>


                    </div>


                    <!-- ACTION -->

                    <div class="my-booking-actions">


                        <a
                            href="booking_detail.php?id=<?= (int) $booking['id'] ?>"
                            class="my-booking-detail-button"
                        >

                            <i class="bi bi-eye"></i>

                            Lihat Detail

                        </a>


                        <?php if ($booking['status'] === 'menunggu'): ?>


                            <form
                                method="POST"
                                class="my-booking-cancel-form"
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
                                    class="my-booking-cancel-button"
                                >

                                    <i class="bi bi-x-circle"></i>

                                    Batalkan

                                </button>

                            </form>


                        <?php endif; ?>


                    </div>


                </article>


            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <div class="my-bookings-empty">


            <div class="my-bookings-empty-icon">

                <i class="bi bi-inbox"></i>

            </div>


            <?php if (
                $search !== ''
                || $filterStatus !== ''
                || $filterTanggal !== ''
            ): ?>


                <h3>
                    Pengajuan Tidak Ditemukan
                </h3>


                <p>
                    Tidak ada pengajuan yang sesuai dengan
                    filter yang kamu pilih.
                </p>


                <a
                    href="my_bookings.php"
                    class="btn btn-secondary"
                >

                    <i class="bi bi-arrow-counterclockwise"></i>

                    Reset Filter

                </a>


            <?php else: ?>


                <h3>
                    Belum Ada Pengajuan
                </h3>


                <p>
                    Kamu belum memiliki pengajuan peminjaman ruangan.
                    Silakan ajukan peminjaman untuk mulai menggunakan ruangan.
                </p>


                <a
                    href="booking.php"
                    class="btn btn-primary"
                >

                    <i class="bi bi-plus-lg"></i>

                    Ajukan Peminjaman

                </a>


            <?php endif; ?>


        </div>


    <?php endif; ?>


</div>


<script>

/* =====================================================
   TOAST PEMBATALAN
   ===================================================== */

function closeMyBookingsToast() {

    const toast =
        document.querySelector('.my-bookings-toast');

    if (!toast) {
        return;
    }


    toast.classList.add('hide');


    setTimeout(function () {

        const container =
            toast.closest(
                '.my-bookings-toast-container'
            );

        if (container) {
            container.remove();
        }

    }, 250);

}


document.addEventListener(
    'DOMContentLoaded',
    function () {

        const toast =
            document.querySelector(
                '.my-bookings-toast'
            );


        if (!toast) {
            return;
        }


        /*
         * Toast otomatis hilang setelah 4,5 detik.
         */

        setTimeout(function () {

            closeMyBookingsToast();

        }, 4500);

    }
);

</script>


<?php require '../config/footer.php'; ?>