<?php

require '../config/auth.php';
require '../config/database.php';

require_login();


/* =====================================================
   FILTER TANGGAL
   ===================================================== */

$selectedDate = $_GET['tanggal'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}


/* =====================================================
   FILTER RUANGAN
   ===================================================== */

$selectedRoomId = (int) ($_GET['room_id'] ?? 0);


/* =====================================================
   DATA SEMUA RUANGAN
   ===================================================== */

$rooms = $pdo->query("
    SELECT *
    FROM rooms
    WHERE status = 'tersedia'
    ORDER BY nama_ruangan ASC
")->fetchAll();


/* =====================================================
   DATA JADWAL
   ===================================================== */

$sqlBookings = "
    SELECT
        b.*,
        r.nama_ruangan,
        r.lokasi,
        r.kapasitas
    FROM bookings b
    INNER JOIN rooms r ON r.id = b.room_id
    WHERE b.tanggal = ?
    AND b.status = 'disetujui'
";

$paramsBookings = [$selectedDate];

if ($selectedRoomId > 0) {
    $sqlBookings .= " AND b.room_id = ?";
    $paramsBookings[] = $selectedRoomId;
}

$sqlBookings .= "
    ORDER BY b.room_id ASC, b.jam_mulai ASC
";

$stmt = $pdo->prepare($sqlBookings);
$stmt->execute($paramsBookings);
$bookings = $stmt->fetchAll();


/* =====================================================
   KELOMPOKKAN JADWAL BERDASARKAN RUANGAN
   ===================================================== */

$schedulesByRoom = [];

foreach ($bookings as $booking) {

    $roomId = (int) $booking['room_id'];

    if (!isset($schedulesByRoom[$roomId])) {
        $schedulesByRoom[$roomId] = [];
    }

    $schedulesByRoom[$roomId][] = $booking;
}


/* =====================================================
   STATISTIK
   ===================================================== */

$totalRooms = count($rooms);
$usedRooms = 0;
$availableRooms = 0;
$totalSchedules = count($bookings);

foreach ($rooms as $room) {

    $roomId = (int) $room['id'];

    if (!empty($schedulesByRoom[$roomId])) {
        $usedRooms++;
    } else {
        $availableRooms++;
    }
}


/* =====================================================
   TANGGAL SEBELUM / SESUDAH
   ===================================================== */

$previousDate = date(
    'Y-m-d',
    strtotime($selectedDate . ' -1 day')
);

$nextDate = date(
    'Y-m-d',
    strtotime($selectedDate . ' +1 day')
);

$todayDate = date('Y-m-d');


/* =====================================================
   NAMA HARI
   ===================================================== */

$dayNames = [
    'Sunday'    => 'Minggu',
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat',
    'Saturday'  => 'Sabtu'
];

$monthNames = [
    1  => 'Januari',
    2  => 'Februari',
    3  => 'Maret',
    4  => 'April',
    5  => 'Mei',
    6  => 'Juni',
    7  => 'Juli',
    8  => 'Agustus',
    9  => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];

$timestamp = strtotime($selectedDate);

$selectedDayName = $dayNames[date('l', $timestamp)];
$selectedDay = date('d', $timestamp);
$selectedMonth = $monthNames[(int) date('n', $timestamp)];
$selectedYear = date('Y', $timestamp);

$title = 'Jadwal Ruangan';

require '../config/header.php';

?>

<style>

/* =====================================================
   INTERACTIVE SCHEDULE
   Tambahan khusus halaman jadwal
   ===================================================== */

.schedule-date-navigation {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    margin-top: 14px;
}

.schedule-date-navigation a,
.schedule-date-navigation button {

    min-height: 40px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    padding: 8px 13px;

    border-radius: 10px;

    border: 1px solid var(--border);

    background: var(--white);

    color: var(--text);

    text-decoration: none;

    font-size: 12px;

    font-weight: 600;

    cursor: pointer;

    transition: .2s ease;
}

.schedule-date-navigation a:hover,
.schedule-date-navigation button:hover {

    background: var(--green-light);

    color: var(--green);

    border-color: #d3e4da;
}

.schedule-date-navigation .schedule-today-btn {

    background: var(--green);

    color: white;

    border-color: var(--green);
}

.schedule-date-navigation .schedule-today-btn:hover {

    background: var(--green-dark);

    color: white;
}


/* =====================================================
   ROOM FILTER
   ===================================================== */

.schedule-room-filter {

    display: flex;

    align-items: flex-end;

    gap: 12px;

    margin-top: 16px;
}

.schedule-room-filter .form-group {

    flex: 1;

    margin: 0;
}

.schedule-room-filter label {

    display: block;

    margin-bottom: 7px;

    font-size: 12px;

    font-weight: 600;

    color: var(--text-soft);
}

.schedule-room-filter select {

    width: 100%;

    min-height: 44px;
}

.schedule-room-filter-action {

    display: flex;

    gap: 8px;
}

.schedule-room-filter-action .btn {

    min-height: 44px;

    display: inline-flex;

    align-items: center;

    justify-content: center;
}


/* =====================================================
   CURRENT DATE INDICATOR
   ===================================================== */

.schedule-current-label {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    margin-top: 5px;

    color: var(--green);

    font-size: 11px;

    font-weight: 700;
}


/* =====================================================
   EVENT CLICK
   ===================================================== */

.schedule-event {

    cursor: pointer;

    transition: transform .2s ease, box-shadow .2s ease;
}

.schedule-event:hover {

    transform: translateX(3px);
}

.schedule-event-content {

    min-width: 0;
}

.schedule-event-content strong {

    display: block;

    word-break: break-word;
}

.schedule-event-content span {

    display: block;
}


/* =====================================================
   MODAL DETAIL JADWAL
   ===================================================== */

.schedule-modal-overlay {

    position: fixed;

    inset: 0;

    z-index: 9999;

    display: none;

    align-items: center;

    justify-content: center;

    padding: 20px;

    background: rgba(20, 30, 24, .48);

    backdrop-filter: blur(3px);
}

.schedule-modal-overlay.active {

    display: flex;
}

.schedule-modal {

    width: 100%;

    max-width: 450px;

    background: var(--white);

    border-radius: 18px;

    box-shadow: 0 18px 60px rgba(20, 40, 28, .18);

    overflow: hidden;

    animation: scheduleModalShow .2s ease;
}

.schedule-modal-header {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 15px;

    padding: 20px;

    border-bottom: 1px solid var(--border);
}

.schedule-modal-heading {

    min-width: 0;
}

.schedule-modal-label {

    display: block;

    margin-bottom: 5px;

    color: var(--green);

    font-size: 10px;

    font-weight: 700;

    letter-spacing: .5px;
}

.schedule-modal-header h3 {

    margin: 0;

    font-size: 19px;
}

.schedule-modal-close {

    flex: 0 0 34px;

    width: 34px;

    height: 34px;

    border: 0;

    border-radius: 9px;

    background: var(--background);

    color: var(--muted);

    cursor: pointer;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 17px;
}

.schedule-modal-close:hover {

    background: var(--red-light);

    color: var(--red);
}

.schedule-modal-body {

    padding: 20px;
}

.schedule-modal-time {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 14px;

    margin-bottom: 17px;

    border-radius: 12px;

    background: var(--green-light);
}

.schedule-modal-time-icon {

    width: 40px;

    height: 40px;

    flex: 0 0 40px;

    border-radius: 10px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: var(--white);

    color: var(--green);

    font-size: 18px;
}

.schedule-modal-time strong {

    display: block;

    color: var(--green-dark);

    font-size: 17px;
}

.schedule-modal-time span {

    display: block;

    margin-top: 2px;

    color: var(--text-soft);

    font-size: 11px;
}

.schedule-modal-list {

    display: flex;

    flex-direction: column;

    gap: 13px;
}

.schedule-modal-item {

    display: flex;

    gap: 12px;
}

.schedule-modal-item-icon {

    width: 34px;

    height: 34px;

    flex: 0 0 34px;

    border-radius: 9px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: var(--background);

    color: var(--green);

    font-size: 14px;
}

.schedule-modal-item-content {

    min-width: 0;
}

.schedule-modal-item-content small {

    display: block;

    margin-bottom: 3px;

    color: var(--muted);

    font-size: 10px;
}

.schedule-modal-item-content strong,
.schedule-modal-item-content span {

    display: block;

    color: var(--text);

    font-size: 13px;

    line-height: 1.5;

    word-break: break-word;
}

.schedule-modal-footer {

    padding: 15px 20px 20px;

    display: flex;

    gap: 10px;
}

.schedule-modal-footer a {

    flex: 1;

    display: flex;

    align-items: center;

    justify-content: center;
}

@keyframes scheduleModalShow {

    from {

        opacity: 0;

        transform: translateY(10px) scale(.98);
    }

    to {

        opacity: 1;

        transform: translateY(0) scale(1);
    }
}


/* =====================================================
   FILTER ACTIVE
   ===================================================== */

.schedule-filter-active {

    margin-top: 12px;

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 7px 10px;

    border-radius: 8px;

    background: var(--green-light);

    color: var(--green-dark);

    font-size: 11px;
}


/* =====================================================
   RESPONSIVE
   ===================================================== */

@media (max-width: 700px) {

    .schedule-date-navigation {

        flex-wrap: wrap;
    }

    .schedule-date-navigation a,
    .schedule-date-navigation button {

        flex: 1;
    }

    .schedule-room-filter {

        flex-direction: column;

        align-items: stretch;
    }

    .schedule-room-filter-action {

        width: 100%;
    }

    .schedule-room-filter-action .btn {

        flex: 1;
    }
}

@media (max-width: 480px) {

    .schedule-date-navigation a,
    .schedule-date-navigation button {

        font-size: 11px;

        padding: 8px 9px;
    }

    .schedule-modal {

        border-radius: 15px;
    }

    .schedule-modal-footer {

        flex-direction: column;
    }
}

</style>


<!-- =====================================================
     HEADER
     ===================================================== -->

<div class="schedule-header">

    <div class="schedule-header-content">

        <span class="schedule-label">
            JADWAL RUANGAN
        </span>

        <h1>
            Jadwal Penggunaan Ruangan
        </h1>

        <p>
            Lihat ketersediaan dan penggunaan seluruh ruangan
            berdasarkan tanggal.
        </p>

    </div>


    <a href="dashboard.php" class="schedule-back">

        <i class="bi bi-arrow-left"></i>

        Kembali

    </a>

</div>


<!-- =====================================================
     DATE CONTROL
     ===================================================== -->

<div class="schedule-date-card">

    <div class="schedule-date-main">

        <div class="schedule-calendar-icon">

            <i class="bi bi-calendar3"></i>

        </div>


        <div>

            <span>
                TANGGAL YANG DIPILIH
            </span>

            <strong>

                <?= $selectedDayName ?>,
                <?= $selectedDay ?>
                <?= $selectedMonth ?>
                <?= $selectedYear ?>

            </strong>


            <?php if ($selectedDate === $todayDate): ?>

                <div class="schedule-current-label">

                    <i class="bi bi-circle-fill"></i>

                    Hari ini

                </div>

            <?php endif; ?>

        </div>

    </div>


    <form method="get" class="schedule-date-form">

        <?php if ($selectedRoomId > 0): ?>

            <input
                type="hidden"
                name="room_id"
                value="<?= $selectedRoomId ?>"
            >

        <?php endif; ?>


        <input
            type="date"
            name="tanggal"
            value="<?= htmlspecialchars($selectedDate) ?>"
            required
        >


        <button type="submit">

            <i class="bi bi-calendar-check"></i>

            Lihat Jadwal

        </button>

    </form>

</div>


<!-- =====================================================
     NAVIGASI TANGGAL
     ===================================================== -->

<div class="schedule-date-navigation">

    <a
        href="schedule.php?tanggal=<?= urlencode($previousDate) ?><?= $selectedRoomId > 0 ? '&room_id=' . $selectedRoomId : '' ?>"
    >

        <i class="bi bi-chevron-left"></i>

        Hari Sebelumnya

    </a>


    <!-- TANGGAL SEKARANG -->

    <a
        href="schedule.php?tanggal=<?= urlencode($todayDate) ?><?= $selectedRoomId > 0 ? '&room_id=' . $selectedRoomId : '' ?>"
        class="schedule-today-btn"
    >

        <i class="bi bi-calendar-event"></i>

        <?= date('d', strtotime($todayDate)) ?>
        <?= $monthNames[(int) date('n', strtotime($todayDate))] ?>
        <?= date('Y', strtotime($todayDate)) ?>

    </a>


    <a
        href="schedule.php?tanggal=<?= urlencode($nextDate) ?><?= $selectedRoomId > 0 ? '&room_id=' . $selectedRoomId : '' ?>"
    >

        Hari Berikutnya

        <i class="bi bi-chevron-right"></i>

    </a>

</div>


<!-- =====================================================
     FILTER RUANGAN
     ===================================================== -->

<form method="get" class="schedule-room-filter">

    <input
        type="hidden"
        name="tanggal"
        value="<?= htmlspecialchars($selectedDate) ?>"
    >


    <div class="form-group">

        <label for="scheduleRoom">

            <i class="bi bi-funnel me-1"></i>

            Filter Ruangan

        </label>


        <select
            name="room_id"
            id="scheduleRoom"
            class="form-select"
        >

            <option value="">
                Semua Ruangan
            </option>


            <?php foreach ($rooms as $room): ?>

                <option
                    value="<?= (int) $room['id'] ?>"
                    <?= $selectedRoomId === (int) $room['id'] ? 'selected' : '' ?>
                >

                    <?= htmlspecialchars($room['nama_ruangan']) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <div class="schedule-room-filter-action">

        <button type="submit" class="btn btn-primary">

            <i class="bi bi-funnel"></i>

            Terapkan

        </button>


        <a
            href="schedule.php?tanggal=<?= urlencode($selectedDate) ?>"
            class="btn btn-secondary"
        >

            Reset

        </a>

    </div>

</form>


<?php if ($selectedRoomId > 0): ?>

    <?php

    $selectedRoomName = '';

    foreach ($rooms as $room) {

        if ((int) $room['id'] === $selectedRoomId) {

            $selectedRoomName = $room['nama_ruangan'];

            break;
        }
    }

    ?>


    <?php if ($selectedRoomName !== ''): ?>

        <div class="schedule-filter-active">

            <i class="bi bi-funnel-fill"></i>

            Menampilkan jadwal:

            <strong>

                <?= htmlspecialchars($selectedRoomName) ?>

            </strong>

        </div>

    <?php endif; ?>

<?php endif; ?>


<!-- =====================================================
     SUMMARY
     ===================================================== -->

<div class="schedule-overview">

    <div class="schedule-overview-item">

        <div class="schedule-overview-icon green">

            <i class="bi bi-building"></i>

        </div>


        <div>

            <span>
                Total Ruangan
            </span>

            <strong>
                <?= $totalRooms ?>
            </strong>

        </div>

    </div>


    <div class="schedule-overview-item">

        <div class="schedule-overview-icon red">

            <i class="bi bi-calendar2-check"></i>

        </div>


        <div>

            <span>
                Terpakai
            </span>

            <strong>
                <?= $usedRooms ?>
            </strong>

        </div>

    </div>


    <div class="schedule-overview-item">

        <div class="schedule-overview-icon blue">

            <i class="bi bi-check2-circle"></i>

        </div>


        <div>

            <span>
                Tersedia
            </span>

            <strong>
                <?= $availableRooms ?>
            </strong>

        </div>

    </div>


    <div class="schedule-overview-item">

        <div class="schedule-overview-icon yellow">

            <i class="bi bi-clock"></i>

        </div>


        <div>

            <span>
                Total Kegiatan
            </span>

            <strong>
                <?= $totalSchedules ?>
            </strong>

        </div>

    </div>

</div>


<!-- =====================================================
     SECTION TITLE
     ===================================================== -->

<div class="schedule-list-heading">

    <div>

        <span>
            JADWAL RUANGAN
        </span>

        <h2>
            Penggunaan Ruangan
        </h2>

    </div>


    <div class="schedule-list-date">

        <i class="bi bi-calendar3"></i>

        <?= date('d/m/Y', strtotime($selectedDate)) ?>

    </div>

</div>


<!-- =====================================================
     ROOM SCHEDULE
     ===================================================== -->

<?php if (empty($rooms)): ?>

    <div class="schedule-no-room">

        <div class="schedule-no-room-icon">

            <i class="bi bi-building-x"></i>

        </div>


        <h3>
            Belum Ada Ruangan
        </h3>


        <p>
            Belum tersedia ruangan yang dapat digunakan.
        </p>

    </div>

<?php else: ?>

    <div class="schedule-list">

        <?php foreach ($rooms as $room): ?>

            <?php

            $roomId = (int) $room['id'];


            /*
             * Jika filter ruangan aktif,
             * hanya tampilkan ruangan yang dipilih.
             */

            if ($selectedRoomId > 0 && $roomId !== $selectedRoomId) {

                continue;
            }


            $roomSchedules = $schedulesByRoom[$roomId] ?? [];

            $isUsed = !empty($roomSchedules);

            ?>


            <div class="schedule-row">


                <!-- =================================================
                     ROOM INFORMATION
                     ================================================= -->

                <div class="schedule-room-info">

                    <div class="schedule-room-symbol">

                        <i class="bi bi-door-open"></i>

                    </div>


                    <div class="schedule-room-text">

                        <div class="schedule-room-name-row">

                            <h3>

                                <?= htmlspecialchars($room['nama_ruangan']) ?>

                            </h3>


                            <?php if ($isUsed): ?>

                                <span class="schedule-pill used">

                                    <span></span>

                                    Terpakai

                                </span>

                            <?php else: ?>

                                <span class="schedule-pill available">

                                    <span></span>

                                    Tersedia

                                </span>

                            <?php endif; ?>

                        </div>


                        <div class="schedule-room-details">

                            <span>

                                <i class="bi bi-geo-alt"></i>

                                <?= htmlspecialchars($room['lokasi']) ?>

                            </span>


                            <span>

                                <i class="bi bi-people"></i>

                                <?= htmlspecialchars($room['kapasitas']) ?>

                                orang

                            </span>

                        </div>


                        <?php if (!empty($room['fasilitas'])): ?>

                            <div class="schedule-room-facility">

                                <i class="bi bi-stars"></i>

                                <?= htmlspecialchars($room['fasilitas']) ?>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- =================================================
                     TIMELINE
                     ================================================= -->

                <div class="schedule-timeline">

                    <?php if ($isUsed): ?>

                        <?php foreach ($roomSchedules as $schedule): ?>

                            <div
                                class="schedule-event"
                                role="button"
                                tabindex="0"

                                data-room="<?= htmlspecialchars($room['nama_ruangan']) ?>"

                                data-location="<?= htmlspecialchars($room['lokasi']) ?>"

                                data-start="<?= htmlspecialchars(substr($schedule['jam_mulai'], 0, 5)) ?>"

                                data-end="<?= htmlspecialchars(substr($schedule['jam_selesai'], 0, 5)) ?>"

                                data-purpose="<?= htmlspecialchars($schedule['keperluan']) ?>"

                                data-date="<?= htmlspecialchars($selectedDate) ?>"
                            >

                                <div class="schedule-event-time">

                                    <strong>

                                        <?= substr($schedule['jam_mulai'], 0, 5) ?>

                                    </strong>

                                    <span>

                                        sampai

                                    </span>

                                    <strong>

                                        <?= substr($schedule['jam_selesai'], 0, 5) ?>

                                    </strong>

                                </div>


                                <div class="schedule-event-line">

                                    <span></span>

                                </div>


                                <div class="schedule-event-content">

                                    <strong>

                                        <?= htmlspecialchars($schedule['keperluan']) ?>

                                    </strong>

                                    <span>

                                        Klik untuk melihat detail

                                    </span>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="schedule-free">

                            <div class="schedule-free-icon">

                                <i class="bi bi-check-lg"></i>

                            </div>


                            <div>

                                <strong>

                                    Ruangan tersedia

                                </strong>

                                <span>

                                    Belum ada peminjaman yang disetujui
                                    pada tanggal ini.

                                </span>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- =================================================
                     ACTION
                     ================================================= -->

                <div class="schedule-row-action">

                    <a
                        href="booking.php?room_id=<?= $roomId ?>&tanggal=<?= urlencode($selectedDate) ?>"
                        class="<?= $isUsed ? 'schedule-action secondary' : 'schedule-action' ?>"
                    >

                        <i class="bi bi-plus-lg"></i>

                        Ajukan

                    </a>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     INFORMATION
     ===================================================== -->

<div class="schedule-information">

    <i class="bi bi-info-circle"></i>


    <div>

        <strong>
            Informasi
        </strong>


        <p>

            Jadwal yang ditampilkan hanya merupakan peminjaman
            yang telah disetujui admin. Klik jadwal untuk melihat
            detail waktu dan keperluan kegiatan.

        </p>

    </div>

</div>


<!-- =====================================================
     MODAL DETAIL JADWAL
     ===================================================== -->

<div
    class="schedule-modal-overlay"
    id="scheduleModal"
    aria-hidden="true"
>

    <div
        class="schedule-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="scheduleModalTitle"
    >

        <div class="schedule-modal-header">

            <div class="schedule-modal-heading">

                <span class="schedule-modal-label">

                    DETAIL JADWAL

                </span>


                <h3 id="scheduleModalTitle">

                    Ruangan

                </h3>

            </div>


            <button
                type="button"
                class="schedule-modal-close"
                id="scheduleModalClose"
                aria-label="Tutup"
            >

                <i class="bi bi-x-lg"></i>

            </button>

        </div>


        <div class="schedule-modal-body">


            <!-- WAKTU -->

            <div class="schedule-modal-time">

                <div class="schedule-modal-time-icon">

                    <i class="bi bi-clock"></i>

                </div>


                <div>

                    <strong id="scheduleModalTime">

                        00:00 - 00:00

                    </strong>


                    <span id="scheduleModalDate">

                        Tanggal

                    </span>

                </div>

            </div>


            <!-- DETAIL -->

            <div class="schedule-modal-list">


                <div class="schedule-modal-item">

                    <div class="schedule-modal-item-icon">

                        <i class="bi bi-building"></i>

                    </div>


                    <div class="schedule-modal-item-content">

                        <small>
                            Ruangan
                        </small>


                        <strong id="scheduleModalRoom">

                            -

                        </strong>

                    </div>

                </div>


                <div class="schedule-modal-item">

                    <div class="schedule-modal-item-icon">

                        <i class="bi bi-geo-alt"></i>

                    </div>


                    <div class="schedule-modal-item-content">

                        <small>
                            Lokasi
                        </small>


                        <span id="scheduleModalLocation">

                            -

                        </span>

                    </div>

                </div>


                <div class="schedule-modal-item">

                    <div class="schedule-modal-item-icon">

                        <i class="bi bi-card-text"></i>

                    </div>


                    <div class="schedule-modal-item-content">

                        <small>
                            Keperluan
                        </small>


                        <span id="scheduleModalPurpose">

                            -

                        </span>

                    </div>

                </div>

            </div>

        </div>


        <div class="schedule-modal-footer">

            <a
                href="#"
                id="scheduleModalBooking"
                class="btn btn-primary"
            >

                <i class="bi bi-calendar-plus me-1"></i>

                Ajukan Peminjaman

            </a>


            <button
                type="button"
                id="scheduleModalCloseBottom"
                class="btn btn-secondary"
            >

                Tutup

            </button>

        </div>

    </div>

</div>


<!-- =====================================================
     JAVASCRIPT INTERAKTIF
     ===================================================== -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const modal = document.getElementById('scheduleModal');

    const modalClose =
        document.getElementById('scheduleModalClose');

    const modalCloseBottom =
        document.getElementById('scheduleModalCloseBottom');

    const modalTitle =
        document.getElementById('scheduleModalTitle');

    const modalTime =
        document.getElementById('scheduleModalTime');

    const modalDate =
        document.getElementById('scheduleModalDate');

    const modalRoom =
        document.getElementById('scheduleModalRoom');

    const modalLocation =
        document.getElementById('scheduleModalLocation');

    const modalPurpose =
        document.getElementById('scheduleModalPurpose');

    const modalBooking =
        document.getElementById('scheduleModalBooking');


    if (!modal) {
        return;
    }


    /* =================================================
       FORMAT TANGGAL
       ================================================= */

    function formatDate(dateString) {

        const parts = dateString.split('-');

        if (parts.length !== 3) {
            return dateString;
        }

        const year = parts[0];

        const month = parseInt(parts[1], 10);

        const day = parts[2];


        const months = [
            '',
            'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember'
        ];


        return day + ' ' + months[month] + ' ' + year;
    }


    /* =================================================
       BUKA MODAL
       ================================================= */

    function openScheduleModal(eventElement) {

        const room =
            eventElement.dataset.room || '-';

        const location =
            eventElement.dataset.location || '-';

        const start =
            eventElement.dataset.start || '-';

        const end =
            eventElement.dataset.end || '-';

        const purpose =
            eventElement.dataset.purpose || '-';

        const date =
            eventElement.dataset.date || '';


        modalTitle.textContent = room;

        modalTime.textContent =
            start + ' - ' + end;

        modalDate.textContent =
            formatDate(date);

        modalRoom.textContent =
            room;

        modalLocation.textContent =
            location;

        modalPurpose.textContent =
            purpose;


        /*
         * Link langsung ke form peminjaman ruangan
         * dan membawa tanggal yang sedang dilihat.
         */

        const roomIdElement =
            eventElement
                .closest('.schedule-row')
                ?.querySelector('.schedule-row-action a');


        if (roomIdElement) {

            const bookingUrl = new URL(
                roomIdElement.href,
                window.location.origin
            );


            bookingUrl.searchParams.set(
                'tanggal',
                date
            );


            modalBooking.href =
                bookingUrl.toString();

        } else {

            modalBooking.href =
                'booking.php';

        }


        modal.classList.add('active');

        modal.setAttribute(
            'aria-hidden',
            'false'
        );


        document.body.style.overflow =
            'hidden';

    }


    /* =================================================
       TUTUP MODAL
       ================================================= */

    function closeScheduleModal() {

        modal.classList.remove('active');

        modal.setAttribute(
            'aria-hidden',
            'true'
        );


        document.body.style.overflow =
            '';

    }


    /* =================================================
       EVENT JADWAL
       ================================================= */

    const scheduleEvents =
        document.querySelectorAll('.schedule-event');


    scheduleEvents.forEach(function (eventElement) {

        eventElement.addEventListener(
            'click',
            function () {

                openScheduleModal(
                    eventElement
                );

            }
        );


        eventElement.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Enter' ||
                    event.key === ' '
                ) {

                    event.preventDefault();

                    openScheduleModal(
                        eventElement
                    );

                }

            }
        );

    });


    /* =================================================
       TOMBOL TUTUP
       ================================================= */

    if (modalClose) {

        modalClose.addEventListener(
            'click',
            closeScheduleModal
        );

    }


    if (modalCloseBottom) {

        modalCloseBottom.addEventListener(
            'click',
            closeScheduleModal
        );

    }


    /* =================================================
       KLIK AREA LUAR MODAL
       ================================================= */

    modal.addEventListener(
        'click',
        function (event) {

            if (event.target === modal) {

                closeScheduleModal();

            }

        }
    );


    /* =================================================
       ESCAPE
       ================================================= */

    document.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Escape' &&
                modal.classList.contains('active')
            ) {

                closeScheduleModal();

            }

        }
    );

});

</script>