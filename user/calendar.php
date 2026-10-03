<?php

require '../config/auth.php';
require '../config/database.php';

require_login();


/* =====================================================
   FILTER RUANGAN
   ===================================================== */

$roomId = (int) ($_GET['room_id'] ?? 0);


/* =====================================================
   DATA RUANGAN
   ===================================================== */

$rooms = $pdo->query("
    SELECT
        id,
        nama_ruangan,
        lokasi
    FROM rooms
    WHERE status = 'tersedia'
    ORDER BY nama_ruangan
")->fetchAll();


/* =====================================================
   DATA PEMINJAMAN
   HANYA MENUNGGU & DISETUJUI
   ===================================================== */

$sql = "
    SELECT
        b.id,
        b.room_id,
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
    WHERE b.status IN ('menunggu', 'disetujui')
";

$params = [];


if ($roomId > 0) {

    $sql .= "
        AND b.room_id = ?
    ";

    $params[] = $roomId;
}


$sql .= "
    ORDER BY
        b.tanggal ASC,
        b.jam_mulai ASC
";


$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$bookings = $stmt->fetchAll();


/* =====================================================
   DATA UNTUK JAVASCRIPT
   ===================================================== */

$calendarBookings = [];

foreach ($bookings as $booking) {

    $calendarBookings[] = [
        'id' => (int) $booking['id'],
        'room_id' => (int) $booking['room_id'],
        'room' => $booking['nama_ruangan'],
        'location' => $booking['lokasi'],
        'date' => $booking['tanggal'],
        'start' => substr($booking['jam_mulai'], 0, 5),
        'end' => substr($booking['jam_selesai'], 0, 5),
        'purpose' => $booking['keperluan'],
        'status' => $booking['status']
    ];
}


$title = 'Kalender Peminjaman';

require '../config/header.php';

?>


<!-- =====================================================
     CALENDAR PAGE
     ===================================================== -->

<div class="calendar-page">


    <!-- =================================================
         HEADER
         ================================================= -->

    <div class="calendar-page-header">

        <div>

            <span class="heading-label">
                JADWAL RUANGAN
            </span>

            <h1>
                Kalender Peminjaman
            </h1>

            <p>
                Lihat jadwal penggunaan ruangan berdasarkan tanggal.
            </p>

        </div>


        <a
            href="booking.php"
            class="btn btn-primary calendar-book-button"
        >

            <i class="bi bi-calendar-plus"></i>

            Ajukan Peminjaman

        </a>

    </div>



    <!-- =================================================
         FILTER
         ================================================= -->

    <div class="calendar-filter-card">

        <div class="calendar-filter-left">

            <div class="calendar-filter-icon">

                <i class="bi bi-funnel"></i>

            </div>


            <div>

                <strong>
                    Filter Ruangan
                </strong>

                <span>
                    Tampilkan jadwal ruangan tertentu.
                </span>

            </div>

        </div>


        <form
            method="get"
            class="calendar-filter-form"
        >

            <select
                name="room_id"
                class="form-select calendar-room-select"
                onchange="this.form.submit()"
            >

                <option value="0">
                    Semua Ruangan
                </option>


                <?php foreach ($rooms as $room): ?>

                    <option
                        value="<?= (int) $room['id'] ?>"
                        <?= $roomId === (int) $room['id'] ? 'selected' : '' ?>
                    >

                        <?= htmlspecialchars($room['nama_ruangan']) ?>

                        <?php if (!empty($room['lokasi'])): ?>

                            -
                            <?= htmlspecialchars($room['lokasi']) ?>

                        <?php endif; ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <?php if ($roomId > 0): ?>

                <a
                    href="calendar.php"
                    class="calendar-reset"
                >

                    <i class="bi bi-x-circle"></i>

                    Reset

                </a>

            <?php endif; ?>

        </form>

    </div>



    <!-- =================================================
         CALENDAR CARD
         ================================================= -->

    <div class="calendar-card">


        <!-- =================================================
             CALENDAR TOOLBAR
             ================================================= -->

        <div class="calendar-toolbar">


            <div class="calendar-navigation">

                <button
                    type="button"
                    class="calendar-nav-button"
                    id="previousMonth"
                    title="Bulan sebelumnya"
                >

                    <i class="bi bi-chevron-left"></i>

                </button>


                <button
                    type="button"
                    class="calendar-today-button"
                    id="todayButton"
                >

                    Hari Ini

                </button>


                <button
                    type="button"
                    class="calendar-nav-button"
                    id="nextMonth"
                    title="Bulan berikutnya"
                >

                    <i class="bi bi-chevron-right"></i>

                </button>

            </div>


            <div
                class="calendar-month-title"
                id="calendarMonthTitle"
            >
                -
            </div>


            <div class="calendar-legend">


                <div class="calendar-legend-item">

                    <span class="calendar-dot calendar-dot-approved"></span>

                    Disetujui

                </div>


                <div class="calendar-legend-item">

                    <span class="calendar-dot calendar-dot-pending"></span>

                    Menunggu

                </div>


            </div>

        </div>



        <!-- =================================================
             CALENDAR
             ================================================= -->

        <div class="calendar-wrapper">

            <div class="calendar-weekdays">

                <div>Sen</div>
                <div>Sel</div>
                <div>Rab</div>
                <div>Kam</div>
                <div>Jum</div>
                <div>Sab</div>
                <div>Min</div>

            </div>


            <div
                class="calendar-grid"
                id="calendarGrid"
            ></div>

        </div>


    </div>



    <!-- =================================================
         INFO
         ================================================= -->

    <div class="calendar-info-card">

        <div class="calendar-info-icon">

            <i class="bi bi-info-circle"></i>

        </div>


        <div>

            <strong>
                Informasi Kalender
            </strong>

            <p>
                Klik jadwal pada kalender untuk melihat detail
                peminjaman. Jadwal berstatus disetujui merupakan
                peminjaman yang telah mendapatkan persetujuan admin.
            </p>

        </div>

    </div>


</div>



<!-- =====================================================
     DETAIL MODAL
     ===================================================== -->

<div
    class="calendar-modal"
    id="calendarModal"
    aria-hidden="true"
>

    <div class="calendar-modal-overlay"></div>


    <div
        class="calendar-modal-box"
        role="dialog"
        aria-modal="true"
        aria-labelledby="calendarModalTitle"
    >


        <!-- HEADER -->

        <div class="calendar-modal-header">

            <div class="calendar-modal-heading">

                <div class="calendar-modal-icon">

                    <i class="bi bi-calendar-event"></i>

                </div>


                <div>

                    <span>
                        DETAIL PEMINJAMAN
                    </span>

                    <h2 id="calendarModalTitle">
                        Detail Jadwal
                    </h2>

                </div>

            </div>


            <button
                type="button"
                class="calendar-modal-close"
                id="calendarModalClose"
                aria-label="Tutup"
            >

                <i class="bi bi-x-lg"></i>

            </button>

        </div>



        <!-- CONTENT -->

        <div class="calendar-modal-content">


            <div class="calendar-detail-row">

                <div class="calendar-detail-icon">

                    <i class="bi bi-building"></i>

                </div>


                <div>

                    <span>
                        Ruangan
                    </span>

                    <strong id="modalRoom">
                        -
                    </strong>

                </div>

            </div>



            <div class="calendar-detail-row">

                <div class="calendar-detail-icon">

                    <i class="bi bi-geo-alt"></i>

                </div>


                <div>

                    <span>
                        Lokasi
                    </span>

                    <strong id="modalLocation">
                        -
                    </strong>

                </div>

            </div>



            <div class="calendar-detail-row">

                <div class="calendar-detail-icon">

                    <i class="bi bi-calendar3"></i>

                </div>


                <div>

                    <span>
                        Tanggal
                    </span>

                    <strong id="modalDate">
                        -
                    </strong>

                </div>

            </div>



            <div class="calendar-detail-row">

                <div class="calendar-detail-icon">

                    <i class="bi bi-clock"></i>

                </div>


                <div>

                    <span>
                        Waktu
                    </span>

                    <strong id="modalTime">
                        -
                    </strong>

                </div>

            </div>



            <div class="calendar-detail-purpose">

                <span>

                    <i class="bi bi-card-text"></i>

                    Keperluan

                </span>


                <p id="modalPurpose">
                    -
                </p>

            </div>



            <div class="calendar-detail-status">

                <span>
                    Status
                </span>

                <span
                    class="calendar-status-badge"
                    id="modalStatus"
                >
                    -
                </span>

            </div>


        </div>



        <!-- FOOTER -->

        <div class="calendar-modal-footer">

            <button
                type="button"
                class="btn calendar-modal-button"
                id="calendarModalButton"
            >

                Tutup

            </button>

        </div>


    </div>

</div>



<!-- =====================================================
     STYLE KHUSUS CALENDAR
     Tidak mengubah style.css utama
     ===================================================== -->

<style>

/* =====================================================
   PAGE
   ===================================================== */

.calendar-page {
    margin-bottom: 40px;
}


/* =====================================================
   PAGE HEADER
   ===================================================== */

.calendar-page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 22px;
}

.calendar-page-header h1 {
    margin: 3px 0 5px;
    font-size: 30px;
    line-height: 1.2;
}

.calendar-page-header p {
    margin: 0;
    color: var(--muted);
    font-size: 14px;
}

.calendar-book-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    white-space: nowrap;
}


/* =====================================================
   FILTER
   ===================================================== */

.calendar-filter-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;

    padding: 15px 18px;

    margin-bottom: 18px;

    background: var(--white);

    border: 1px solid var(--border);

    border-radius: var(--radius);

    box-shadow: var(--shadow);
}

.calendar-filter-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.calendar-filter-icon {
    width: 40px;
    height: 40px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    background: var(--green-light);

    color: var(--green);

    font-size: 17px;
}

.calendar-filter-left strong {
    display: block;

    margin-bottom: 2px;

    font-size: 13px;
}

.calendar-filter-left span {
    display: block;

    color: var(--muted);

    font-size: 12px;
}

.calendar-filter-form {
    display: flex;
    align-items: center;
    gap: 10px;
}

.calendar-room-select {
    width: 260px;
    min-height: 42px;
}

.calendar-reset {
    display: inline-flex;
    align-items: center;
    gap: 5px;

    color: var(--muted);

    font-size: 12px;

    text-decoration: none;
}

.calendar-reset:hover {
    color: var(--red);
}


/* =====================================================
   CALENDAR CARD
   ===================================================== */

.calendar-card {
    background: var(--white);

    border: 1px solid var(--border);

    border-radius: var(--radius);

    box-shadow: var(--shadow);

    overflow: hidden;
}


/* =====================================================
   TOOLBAR
   ===================================================== */

.calendar-toolbar {
    display: grid;

    grid-template-columns: 1fr auto 1fr;

    align-items: center;

    gap: 15px;

    padding: 17px 20px;

    border-bottom: 1px solid var(--border);
}

.calendar-navigation {
    display: flex;
    align-items: center;
    gap: 7px;
}

.calendar-nav-button,
.calendar-today-button {
    height: 36px;

    border: 1px solid var(--border);

    background: var(--white);

    color: var(--text);

    border-radius: 9px;

    cursor: pointer;

    transition:
        background .2s ease,
        border-color .2s ease,
        color .2s ease;
}

.calendar-nav-button {
    width: 36px;

    display: flex;
    align-items: center;
    justify-content: center;
}

.calendar-today-button {
    padding: 0 13px;

    font-size: 12px;

    font-weight: 600;
}

.calendar-nav-button:hover,
.calendar-today-button:hover {
    border-color: #cfe0d5;

    background: var(--green-light);

    color: var(--green);
}

.calendar-month-title {
    text-align: center;

    font-size: 19px;

    font-weight: 700;

    color: var(--text);
}

.calendar-legend {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 14px;
}

.calendar-legend-item {
    display: flex;
    align-items: center;
    gap: 6px;

    color: var(--muted);

    font-size: 11px;
}

.calendar-dot {
    width: 8px;
    height: 8px;

    border-radius: 50%;
}

.calendar-dot-approved {
    background: var(--green);
}

.calendar-dot-pending {
    background: #d4a72c;
}


/* =====================================================
   CALENDAR GRID
   ===================================================== */

.calendar-wrapper {
    overflow-x: auto;
}

.calendar-weekdays {
    display: grid;

    grid-template-columns: repeat(7, minmax(0, 1fr));

    min-width: 700px;

    border-bottom: 1px solid var(--border);

    background: #fafcfb;
}

.calendar-weekdays div {
    padding: 11px 8px;

    text-align: center;

    color: var(--muted);

    font-size: 11px;

    font-weight: 700;

    text-transform: uppercase;
}

.calendar-grid {
    display: grid;

    grid-template-columns: repeat(7, minmax(0, 1fr));

    min-width: 700px;
}

.calendar-day {
    position: relative;

    min-height: 125px;

    padding: 8px;

    border-right: 1px solid var(--border);

    border-bottom: 1px solid var(--border);

    background: var(--white);

    transition: background .15s ease;
}

.calendar-day:nth-child(7n) {
    border-right: 0;
}

.calendar-day:hover {
    background: #fbfdfc;
}

.calendar-day.empty {
    background: #fafbfa;
}

.calendar-day.today {
    background: #f3faf6;
}

.calendar-day-number {
    width: 28px;
    height: 28px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin-bottom: 5px;

    border-radius: 50%;

    color: var(--text);

    font-size: 12px;

    font-weight: 600;
}

.calendar-day.today .calendar-day-number {
    background: var(--green);

    color: var(--white);
}

.calendar-day-events {
    display: flex;

    flex-direction: column;

    gap: 4px;
}

.calendar-event {
    width: 100%;

    padding: 6px 7px;

    border: 0;

    border-radius: 7px;

    text-align: left;

    cursor: pointer;

    overflow: hidden;

    transition:
        transform .15s ease,
        box-shadow .15s ease;
}

.calendar-event:hover {
    transform: translateY(-1px);

    box-shadow: 0 3px 9px rgba(20, 40, 28, .10);
}

.calendar-event-approved {
    background: var(--green-light);

    border-left: 3px solid var(--green);
}

.calendar-event-pending {
    background: var(--yellow-light);

    border-left: 3px solid #d4a72c;
}

.calendar-event-time {
    display: block;

    margin-bottom: 2px;

    color: var(--text);

    font-size: 10px;

    font-weight: 700;
}

.calendar-event-room {
    display: block;

    overflow: hidden;

    color: var(--text-soft);

    font-size: 10px;

    font-weight: 600;

    white-space: nowrap;

    text-overflow: ellipsis;
}

.calendar-more {
    padding: 2px 5px;

    color: var(--muted);

    font-size: 10px;

    font-weight: 600;
}


/* =====================================================
   INFO CARD
   ===================================================== */

.calendar-info-card {
    display: flex;

    align-items: flex-start;

    gap: 12px;

    margin-top: 16px;

    padding: 15px 17px;

    background: var(--green-light);

    border: 1px solid #d9eee2;

    border-radius: var(--radius);
}

.calendar-info-icon {
    width: 34px;
    height: 34px;

    flex: 0 0 34px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background: var(--white);

    color: var(--green);
}

.calendar-info-card strong {
    display: block;

    margin-bottom: 3px;

    font-size: 13px;
}

.calendar-info-card p {
    margin: 0;

    color: var(--text-soft);

    font-size: 12px;

    line-height: 1.5;
}


/* =====================================================
   MODAL
   ===================================================== */

.calendar-modal {
    position: fixed;

    inset: 0;

    z-index: 9999;

    display: none;

    align-items: center;
    justify-content: center;

    padding: 20px;
}

.calendar-modal.show {
    display: flex;
}

.calendar-modal-overlay {
    position: absolute;

    inset: 0;

    background: rgba(20, 30, 25, .42);

    backdrop-filter: blur(2px);
}

.calendar-modal-box {
    position: relative;

    z-index: 2;

    width: 100%;
    max-width: 480px;

    max-height: calc(100vh - 40px);

    overflow-y: auto;

    background: var(--white);

    border: 1px solid var(--border);

    border-radius: 18px;

    box-shadow: 0 20px 60px rgba(20, 40, 28, .18);

    animation: calendarModalIn .18s ease-out;
}

@keyframes calendarModalIn {

    from {
        opacity: 0;
        transform: translateY(8px) scale(.98);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }

}

.calendar-modal-header {
    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 15px;

    padding: 18px 20px;

    border-bottom: 1px solid var(--border);
}

.calendar-modal-heading {
    display: flex;

    align-items: center;

    gap: 12px;
}

.calendar-modal-icon {
    width: 42px;
    height: 42px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    background: var(--green-light);

    color: var(--green);

    font-size: 18px;
}

.calendar-modal-heading span {
    display: block;

    margin-bottom: 2px;

    color: var(--muted);

    font-size: 9px;

    font-weight: 700;

    letter-spacing: .5px;
}

.calendar-modal-heading h2 {
    margin: 0;

    color: var(--text);

    font-size: 17px;
}

.calendar-modal-close {
    width: 34px;
    height: 34px;

    display: flex;
    align-items: center;
    justify-content: center;

    border: 0;

    border-radius: 9px;

    background: #f4f6f5;

    color: var(--muted);

    cursor: pointer;

    transition: .2s ease;
}

.calendar-modal-close:hover {
    background: var(--red-light);

    color: var(--red);
}

.calendar-modal-content {
    padding: 18px 20px;
}

.calendar-detail-row {
    display: flex;

    align-items: center;

    gap: 12px;

    padding: 11px 0;

    border-bottom: 1px solid #eef2ef;
}

.calendar-detail-icon {
    width: 34px;
    height: 34px;

    flex: 0 0 34px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background: #f5f8f6;

    color: var(--green);

    font-size: 14px;
}

.calendar-detail-row span {
    display: block;

    margin-bottom: 2px;

    color: var(--muted);

    font-size: 10px;
}

.calendar-detail-row strong {
    display: block;

    color: var(--text);

    font-size: 13px;
}

.calendar-detail-purpose {
    margin-top: 15px;

    padding: 13px;

    border-radius: 11px;

    background: #fafbfa;

    border: 1px solid var(--border);
}

.calendar-detail-purpose > span {
    display: flex;

    align-items: center;

    gap: 6px;

    margin-bottom: 7px;

    color: var(--muted);

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;
}

.calendar-detail-purpose p {
    margin: 0;

    color: var(--text-soft);

    font-size: 12px;

    line-height: 1.55;
}

.calendar-detail-status {
    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 15px;

    margin-top: 14px;
}

.calendar-detail-status > span:first-child {
    color: var(--muted);

    font-size: 11px;

    font-weight: 600;
}

.calendar-status-badge {
    display: inline-flex;

    align-items: center;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 700;
}

.calendar-status-approved {
    background: var(--green-light);

    color: var(--green);
}

.calendar-status-pending {
    background: var(--yellow-light);

    color: var(--yellow);
}

.calendar-modal-footer {
    display: flex;

    justify-content: flex-end;

    padding: 14px 20px;

    border-top: 1px solid var(--border);
}

.calendar-modal-button {
    min-width: 80px;

    background: #f4f6f5;

    color: var(--text-soft);

    border: 1px solid var(--border);
}

.calendar-modal-button:hover {
    background: var(--green-light);

    color: var(--green);

    border-color: #d5e8dc;
}


/* =====================================================
   RESPONSIVE
   ===================================================== */

@media (max-width: 900px) {

    .calendar-page-header {
        align-items: flex-start;

        flex-direction: column;
    }

    .calendar-book-button {
        align-self: flex-start;
    }

    .calendar-toolbar {
        grid-template-columns: 1fr auto;

    }

    .calendar-legend {
        grid-column: 1 / -1;

        justify-content: flex-start;
    }

}


@media (max-width: 700px) {

    .calendar-filter-card {
        align-items: flex-start;

        flex-direction: column;
    }

    .calendar-filter-form {
        width: 100%;
    }

    .calendar-room-select {
        width: 100%;
    }

    .calendar-filter-form {
        flex-wrap: wrap;
    }

    .calendar-toolbar {
        display: flex;

        flex-direction: column;

        align-items: stretch;
    }

    .calendar-navigation {
        justify-content: space-between;
    }

    .calendar-month-title {
        order: -1;

        font-size: 17px;
    }

    .calendar-legend {
        justify-content: center;
    }

}


@media (max-width: 480px) {

    .calendar-page-header h1 {
        font-size: 25px;
    }

    .calendar-page-header p {
        font-size: 13px;
    }

    .calendar-filter-card {
        padding: 13px;
    }

    .calendar-toolbar {
        padding: 14px;
    }

    .calendar-day {
        min-height: 105px;

        padding: 6px;
    }

    .calendar-day-number {
        width: 25px;
        height: 25px;

        font-size: 11px;
    }

    .calendar-event {
        padding: 5px;
    }

    .calendar-event-time,
    .calendar-event-room {
        font-size: 9px;
    }

    .calendar-modal {
        padding: 12px;
    }

    .calendar-modal-header,
    .calendar-modal-content,
    .calendar-modal-footer {
        padding-left: 16px;
        padding-right: 16px;
    }

}

</style>



<!-- =====================================================
     JAVASCRIPT
     ===================================================== -->

<script>

document.addEventListener('DOMContentLoaded', function () {


    /* =================================================
       DATA
       ================================================= */

    const bookings = <?= json_encode(
        $calendarBookings,
        JSON_UNESCAPED_UNICODE |
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    ) ?>;


    /* =================================================
       ELEMENT
       ================================================= */

    const calendarGrid =
        document.getElementById('calendarGrid');

    const monthTitle =
        document.getElementById('calendarMonthTitle');

    const previousMonth =
        document.getElementById('previousMonth');

    const nextMonth =
        document.getElementById('nextMonth');

    const todayButton =
        document.getElementById('todayButton');


    /* =================================================
       MODAL ELEMENT
       ================================================= */

    const modal =
        document.getElementById('calendarModal');

    const modalClose =
        document.getElementById('calendarModalClose');

    const modalButton =
        document.getElementById('calendarModalButton');

    const modalOverlay =
        document.querySelector('.calendar-modal-overlay');

    const modalRoom =
        document.getElementById('modalRoom');

    const modalLocation =
        document.getElementById('modalLocation');

    const modalDate =
        document.getElementById('modalDate');

    const modalTime =
        document.getElementById('modalTime');

    const modalPurpose =
        document.getElementById('modalPurpose');

    const modalStatus =
        document.getElementById('modalStatus');

    const modalTitle =
        document.getElementById('calendarModalTitle');


    /* =================================================
       CURRENT DATE
       ================================================= */

    let currentDate = new Date();


    /* =================================================
       HELPER FORMAT DATE
       ================================================= */

    function pad(number) {

        return String(number).padStart(2, '0');

    }


    function getDateString(year, month, day) {

        return (
            year +
            '-' +
            pad(month + 1) +
            '-' +
            pad(day)
        );

    }


    /* =================================================
       FORMAT INDONESIAN DATE
       ================================================= */

    function formatLongDate(dateString) {

        const parts =
            dateString.split('-');

        const date =
            new Date(
                Number(parts[0]),
                Number(parts[1]) - 1,
                Number(parts[2])
            );

        return date.toLocaleDateString(
            'id-ID',
            {
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            }
        );

    }


    /* =================================================
       FORMAT MONTH
       ================================================= */

    function formatMonth(year, month) {

        const date =
            new Date(year, month, 1);

        return date.toLocaleDateString(
            'id-ID',
            {
                month: 'long',
                year: 'numeric'
            }
        );

    }


    /* =================================================
       CHECK TODAY
       ================================================= */

    function isToday(year, month, day) {

        const today =
            new Date();

        return (
            today.getFullYear() === year &&
            today.getMonth() === month &&
            today.getDate() === day
        );

    }


    /* =================================================
       GET BOOKINGS
       ================================================= */

    function getBookingsForDate(dateString) {

        return bookings.filter(function (booking) {

            return booking.date === dateString;

        });

    }


    /* =================================================
       OPEN MODAL
       ================================================= */

    function openModal(booking) {

        modalTitle.textContent =
            booking.room;

        modalRoom.textContent =
            booking.room;

        modalLocation.textContent =
            booking.location || '-';

        modalDate.textContent =
            formatLongDate(booking.date);

        modalTime.textContent =
            booking.start +
            ' - ' +
            booking.end;

        modalPurpose.textContent =
            booking.purpose || '-';


        modalStatus.className =
            'calendar-status-badge';


        if (booking.status === 'disetujui') {

            modalStatus.textContent =
                'Disetujui';

            modalStatus.classList.add(
                'calendar-status-approved'
            );

        } else {

            modalStatus.textContent =
                'Menunggu';

            modalStatus.classList.add(
                'calendar-status-pending'
            );

        }


        modal.classList.add('show');

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.style.overflow =
            'hidden';

    }


    /* =================================================
       CLOSE MODAL
       ================================================= */

    function closeModal() {

        modal.classList.remove('show');

        modal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.style.overflow =
            '';

    }


    /* =================================================
       RENDER CALENDAR
       ================================================= */

    function renderCalendar() {


        const year =
            currentDate.getFullYear();

        const month =
            currentDate.getMonth();


        monthTitle.textContent =
            formatMonth(year, month);


        calendarGrid.innerHTML =
            '';


        /*
         * JavaScript menggunakan:
         * Minggu = 0
         *
         * Kalender kita:
         * Senin = 0
         *
         * Jadi perlu dikurangi 1.
         */

        const firstDay =
            new Date(
                year,
                month,
                1
            ).getDay();


        let startingDay =
            firstDay === 0
                ? 6
                : firstDay - 1;


        const daysInMonth =
            new Date(
                year,
                month + 1,
                0
            ).getDate();


        const previousMonthDays =
            new Date(
                year,
                month,
                0
            ).getDate();


        /*
         * 42 kotak supaya tinggi
         * kalender tetap konsisten.
         */

        const totalCells = 42;


        for (
            let index = 0;
            index < totalCells;
            index++
        ) {


            const dayElement =
                document.createElement('div');


            dayElement.className =
                'calendar-day';


            let dayNumber;

            let cellDate;

            let isCurrentMonth =
                true;


            /* =========================================
               PREVIOUS MONTH
               ========================================= */

            if (index < startingDay) {

                dayNumber =
                    previousMonthDays -
                    startingDay +
                    index +
                    1;

                const previousMonth =
                    month === 0
                        ? 11
                        : month - 1;

                const previousYear =
                    month === 0
                        ? year - 1
                        : year;

                cellDate =
                    getDateString(
                        previousYear,
                        previousMonth,
                        dayNumber
                    );

                isCurrentMonth =
                    false;

                dayElement.classList.add(
                    'empty'
                );

            }


            /* =========================================
               CURRENT MONTH
               ========================================= */

            else if (
                index <
                startingDay +
                daysInMonth
            ) {

                dayNumber =
                    index -
                    startingDay +
                    1;

                cellDate =
                    getDateString(
                        year,
                        month,
                        dayNumber
                    );

            }


            /* =========================================
               NEXT MONTH
               ========================================= */

            else {

                dayNumber =
                    index -
                    startingDay -
                    daysInMonth +
                    1;

                const nextMonthValue =
                    month === 11
                        ? 0
                        : month + 1;

                const nextYear =
                    month === 11
                        ? year + 1
                        : year;

                cellDate =
                    getDateString(
                        nextYear,
                        nextMonthValue,
                        dayNumber
                    );

                isCurrentMonth =
                    false;

                dayElement.classList.add(
                    'empty'
                );

            }


            /* =========================================
               TODAY
               ========================================= */

            if (
                isCurrentMonth &&
                isToday(
                    year,
                    month,
                    dayNumber
                )
            ) {

                dayElement.classList.add(
                    'today'
                );

            }


            /* =========================================
               DAY NUMBER
               ========================================= */

            const numberElement =
                document.createElement('div');

            numberElement.className =
                'calendar-day-number';

            numberElement.textContent =
                dayNumber;


            dayElement.appendChild(
                numberElement
            );


            /* =========================================
               EVENTS
               ========================================= */

            if (isCurrentMonth) {

                const dayBookings =
                    getBookingsForDate(
                        cellDate
                    );


                const eventsContainer =
                    document.createElement('div');

                eventsContainer.className =
                    'calendar-day-events';


                const maxEvents = 3;


                dayBookings
                    .slice(0, maxEvents)
                    .forEach(function (booking) {


                        const event =
                            document.createElement(
                                'button'
                            );


                        event.type =
                            'button';


                        event.className =
                            'calendar-event ' +
                            (
                                booking.status ===
                                'disetujui'
                                    ? 'calendar-event-approved'
                                    : 'calendar-event-pending'
                            );


                        const time =
                            document.createElement(
                                'span'
                            );

                        time.className =
                            'calendar-event-time';

                        time.textContent =
                            booking.start +
                            ' - ' +
                            booking.end;


                        const room =
                            document.createElement(
                                'span'
                            );

                        room.className =
                            'calendar-event-room';

                        room.textContent =
                            booking.room;


                        event.appendChild(time);

                        event.appendChild(room);


                        event.addEventListener(
                            'click',
                            function () {

                                openModal(
                                    booking
                                );

                            }
                        );


                        eventsContainer.appendChild(
                            event
                        );

                    });


                /*
                 * Kalau lebih dari 3
                 * peminjaman pada hari tersebut.
                 */

                if (
                    dayBookings.length >
                    maxEvents
                ) {

                    const more =
                        document.createElement(
                            'div'
                        );

                    more.className =
                        'calendar-more';

                    more.textContent =
                        '+' +
                        (
                            dayBookings.length -
                            maxEvents
                        ) +
                        ' jadwal lainnya';


                    eventsContainer.appendChild(
                        more
                    );

                }


                dayElement.appendChild(
                    eventsContainer
                );

            }


            calendarGrid.appendChild(
                dayElement
            );

        }

    }


    /* =================================================
       PREVIOUS MONTH
       ================================================= */

    previousMonth.addEventListener(
        'click',
        function () {

            currentDate.setMonth(
                currentDate.getMonth() - 1
            );

            renderCalendar();

        }
    );


    /* =================================================
       NEXT MONTH
       ================================================= */

    nextMonth.addEventListener(
        'click',
        function () {

            currentDate.setMonth(
                currentDate.getMonth() + 1
            );

            renderCalendar();

        }
    );


    /* =================================================
       TODAY
       ================================================= */

    todayButton.addEventListener(
        'click',
        function () {

            currentDate =
                new Date();

            renderCalendar();

        }
    );


    /* =================================================
       MODAL EVENTS
       ================================================= */

    modalClose.addEventListener(
        'click',
        closeModal
    );


    modalButton.addEventListener(
        'click',
        closeModal
    );


    modalOverlay.addEventListener(
        'click',
        closeModal
    );


    document.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Escape' &&
                modal.classList.contains('show')
            ) {

                closeModal();

            }

        }
    );


    /* =================================================
       INITIAL RENDER
       ================================================= */

    renderCalendar();

});

</script>