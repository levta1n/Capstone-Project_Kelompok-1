<?php

require '../config/auth.php';
require '../config/database.php';

require_admin();


/* =====================================================
   FILTER
   ===================================================== */

$tanggalMulai = $_GET['tanggal_mulai'] ?? '';
$tanggalSelesai = $_GET['tanggal_selesai'] ?? '';
$status = $_GET['status'] ?? '';
$roomId = (int) ($_GET['room_id'] ?? 0);
$search = trim($_GET['search'] ?? '');


/* =====================================================
   DATA RUANGAN
   ===================================================== */

$stmtRooms = $pdo->query("
    SELECT
        id,
        nama_ruangan
    FROM rooms
    ORDER BY nama_ruangan ASC
");

$rooms = $stmtRooms->fetchAll();


/* =====================================================
   QUERY LAPORAN
   ===================================================== */

$sql = "
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
    WHERE 1 = 1
";

$params = [];


/* =====================================================
   FILTER TANGGAL MULAI
   ===================================================== */

if ($tanggalMulai !== '') {

    $sql .= "
        AND b.tanggal >= ?
    ";

    $params[] = $tanggalMulai;
}


/* =====================================================
   FILTER TANGGAL SELESAI
   ===================================================== */

if ($tanggalSelesai !== '') {

    $sql .= "
        AND b.tanggal <= ?
    ";

    $params[] = $tanggalSelesai;
}


/* =====================================================
   FILTER STATUS
   ===================================================== */

$allowedStatuses = [
    'menunggu',
    'disetujui',
    'ditolak',
    'dibatalkan'
];

if (
    $status !== '' &&
    in_array($status, $allowedStatuses, true)
) {

    $sql .= "
        AND b.status = ?
    ";

    $params[] = $status;
}


/* =====================================================
   FILTER RUANGAN
   ===================================================== */

if ($roomId > 0) {

    $sql .= "
        AND b.room_id = ?
    ";

    $params[] = $roomId;
}


/* =====================================================
   PENCARIAN
   ===================================================== */

if ($search !== '') {

    $sql .= "
        AND (
            u.nama LIKE ?
            OR r.nama_ruangan LIKE ?
            OR b.keperluan LIKE ?
        )
    ";

    $searchParam = '%' . $search . '%';

    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}


/* =====================================================
   URUTAN DATA
   ===================================================== */

$sql .= "
    ORDER BY
        b.tanggal DESC,
        b.jam_mulai DESC,
        b.id DESC
";


/* =====================================================
   EKSEKUSI
   ===================================================== */

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$bookings = $stmt->fetchAll();


/* =====================================================
   RINGKASAN
   ===================================================== */

$totalData = count($bookings);

$totalMenunggu = 0;
$totalDisetujui = 0;
$totalDitolak = 0;
$totalDibatalkan = 0;

foreach ($bookings as $booking) {

    switch ($booking['status']) {

        case 'menunggu':
            $totalMenunggu++;
            break;

        case 'disetujui':
            $totalDisetujui++;
            break;

        case 'ditolak':
            $totalDitolak++;
            break;

        case 'dibatalkan':
            $totalDibatalkan++;
            break;
    }
}


/* =====================================================
   LABEL FILTER UNTUK CETAK
   ===================================================== */

$filterTanggal = 'Semua tanggal';

if ($tanggalMulai !== '' && $tanggalSelesai !== '') {

    $filterTanggal =
        date('d-m-Y', strtotime($tanggalMulai))
        . ' s.d. ' .
        date('d-m-Y', strtotime($tanggalSelesai));

} elseif ($tanggalMulai !== '') {

    $filterTanggal =
        'Mulai ' .
        date('d-m-Y', strtotime($tanggalMulai));

} elseif ($tanggalSelesai !== '') {

    $filterTanggal =
        'Sampai ' .
        date('d-m-Y', strtotime($tanggalSelesai));
}


$filterStatus = 'Semua Status';

$statusLabels = [
    'menunggu' => 'Menunggu',
    'disetujui' => 'Disetujui',
    'ditolak' => 'Ditolak',
    'dibatalkan' => 'Dibatalkan'
];

if (
    $status !== '' &&
    isset($statusLabels[$status])
) {

    $filterStatus = $statusLabels[$status];
}


$filterRuangan = 'Semua Ruangan';

foreach ($rooms as $room) {

    if ((int) $room['id'] === $roomId) {

        $filterRuangan = $room['nama_ruangan'];
        break;
    }
}


/* =====================================================
   TITLE
   ===================================================== */

$title = 'Laporan & Riwayat Peminjaman';

require '../config/header.php';

?>


<style>

/* =====================================================
   REPORT PAGE
   ===================================================== */

.report-page {
    margin-bottom: 40px;
}


/* =====================================================
   HEADER
   ===================================================== */

.report-header {
    position: relative;
    padding: 20px 22px;
    margin-bottom: 20px;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.report-header::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 5px;
    height: 100%;
    background: var(--green);
}

.report-header .heading-label {
    margin-bottom: 4px;
    color: var(--green);
}

.report-header h1 {
    margin: 0 0 5px;
}

.report-header p {
    margin: 0;
    color: var(--muted);
    font-size: 14px;
}


/* =====================================================
   SUMMARY
   ===================================================== */

.report-summary {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.report-summary-card {
    position: relative;
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
    padding: 14px;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 13px;
    box-shadow: var(--shadow);
    overflow: hidden;
}

.report-summary-card::after {
    content: '';
    position: absolute;
    width: 70px;
    height: 70px;
    right: -24px;
    top: -24px;
    border-radius: 50%;
    background: var(--green-light);
    opacity: .8;
}

.report-summary-icon {
    position: relative;
    z-index: 1;
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: var(--green-light);
    color: var(--green);
    font-size: 16px;
}

.report-summary-content {
    position: relative;
    z-index: 1;
    min-width: 0;
}

.report-summary-content span {
    display: block;
    color: var(--muted);
    font-size: 10.5px;
    margin-bottom: 2px;
    white-space: nowrap;
}

.report-summary-content strong {
    display: block;
    color: var(--green-dark);
    font-size: 20px;
    line-height: 1.1;
}


/* =====================================================
   FILTER CARD
   ===================================================== */

.report-filter-card {
    margin-bottom: 20px;
    padding: 18px;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.report-filter-heading {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 15px;
}

.report-filter-heading i {
    color: var(--green);
    font-size: 15px;
}

.report-filter-heading strong {
    color: var(--text);
    font-size: 14px;
}


/* =====================================================
   FILTER GRID
   ===================================================== */

.report-filter-grid {
    display: grid;
    grid-template-columns:
        1fr
        1fr
        1fr
        1fr;
    gap: 13px;
}

.report-filter-group {
    min-width: 0;
}

.report-filter-group label {
    display: block;
    margin-bottom: 6px;
    color: var(--text-soft);
    font-size: 11px;
    font-weight: 600;
}

.report-filter-group .form-control,
.report-filter-group select {
    width: 100%;
    height: 42px;
    box-sizing: border-box;
}


/* =====================================================
   SEARCH ROW
   ===================================================== */

.report-search-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 13px;
    margin-top: 13px;
}

.report-search {
    position: relative;
}

.report-search i {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--green);
    font-size: 14px;
    pointer-events: none;
}

.report-search input {
    width: 100%;
    height: 42px;
    box-sizing: border-box;
    padding-left: 38px;
}


/* =====================================================
   FILTER ACTION
   ===================================================== */

.report-filter-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.report-filter-actions .btn {
    height: 42px;
    white-space: nowrap;
}

.report-reset-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 42px;
    padding: 8px 14px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--white);
    color: var(--text-soft);
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    transition:
        background .2s ease,
        border-color .2s ease,
        color .2s ease;
}

.report-reset-button:hover {
    background: var(--green-light);
    border-color: #cfe4d7;
    color: var(--green-dark);
}


/* =====================================================
   REPORT TABLE CARD
   ===================================================== */

.report-table-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}


/* =====================================================
   TABLE HEADER
   ===================================================== */

.report-table-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 17px 18px;
    border-bottom: 1px solid var(--border);
}

.report-table-title {
    min-width: 0;
}

.report-table-title .heading-label {
    color: var(--green);
    margin-bottom: 3px;
}

.report-table-title h2 {
    margin: 0;
    color: var(--text);
    font-size: 18px;
}

.report-table-title p {
    margin: 3px 0 0;
    color: var(--muted);
    font-size: 11.5px;
}


/* =====================================================
   PRINT BUTTON
   ===================================================== */

.report-print-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    height: 40px;
    padding: 8px 15px;
    border: 1px solid #c8dfd1;
    border-radius: 9px;
    background: var(--green-light);
    color: var(--green-dark);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition:
        background .2s ease,
        border-color .2s ease,
        transform .2s ease;
}

.report-print-button:hover {
    background: var(--green-soft);
    border-color: #b4d4c0;
    transform: translateY(-1px);
}


/* =====================================================
   TABLE WRAPPER
   ===================================================== */

.report-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.report-table {
    width: 100%;
    min-width: 950px;
    border-collapse: collapse;
}

.report-table th {
    padding: 11px 13px;
    background: var(--green-light);
    border-bottom: 1px solid #dcebe2;
    color: var(--green-dark);
    font-size: 10.5px;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
}

.report-table td {
    padding: 12px 13px;
    border-bottom: 1px solid var(--border);
    color: var(--text-soft);
    font-size: 11.5px;
    vertical-align: top;
}

.report-table tbody tr:last-child td {
    border-bottom: 0;
}

.report-table tbody tr {
    transition: background .15s ease;
}

.report-table tbody tr:hover {
    background: #f8fcf9;
}


/* =====================================================
   TABLE CONTENT
   ===================================================== */

.report-number {
    width: 35px;
    color: var(--muted);
    text-align: center;
}

.report-user {
    min-width: 135px;
}

.report-user strong,
.report-room strong {
    display: block;
    color: var(--text);
    font-size: 12px;
    line-height: 1.4;
}

.report-room {
    min-width: 130px;
}

.report-room span {
    display: block;
    margin-top: 2px;
    color: var(--muted);
    font-size: 10px;
}

.report-date {
    white-space: nowrap;
    color: var(--text);
    font-weight: 600;
}

.report-time {
    white-space: nowrap;
    color: var(--text);
    font-weight: 600;
}

.report-purpose {
    min-width: 180px;
    max-width: 260px;
    line-height: 1.5;
    word-break: break-word;
}

.report-purpose.empty {
    color: var(--muted);
    font-style: italic;
}


/* =====================================================
   STATUS
   ===================================================== */

.report-status {
    white-space: nowrap;
}

.report-status .status-badge {
    display: inline-flex;
}


/* =====================================================
   DETAIL BUTTON
   ===================================================== */

.report-detail-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    min-width: 74px;
    height: 32px;
    padding: 6px 9px;
    border: 1px solid #cfe4d7;
    border-radius: 8px;
    background: var(--white);
    color: var(--green-dark);
    text-decoration: none;
    font-size: 10.5px;
    font-weight: 600;
    transition:
        background .2s ease,
        border-color .2s ease,
        transform .2s ease;
}

.report-detail-button:hover {
    background: var(--green-light);
    border-color: #b9dbc7;
    color: var(--green-dark);
    transform: translateY(-1px);
}


/* =====================================================
   EMPTY
   ===================================================== */

.report-empty {
    padding: 50px 20px;
    text-align: center;
}

.report-empty-icon {
    width: 56px;
    height: 56px;
    margin: 0 auto 13px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--green-light);
    color: var(--green);
    font-size: 24px;
}

.report-empty h3 {
    margin: 0 0 5px;
    color: var(--text);
    font-size: 16px;
}

.report-empty p {
    margin: 0;
    color: var(--muted);
    font-size: 12px;
}


/* =====================================================
   PRINT HEADER
   ===================================================== */

.report-print-header {
    display: none;
}


/* =====================================================
   RESPONSIVE
   ===================================================== */

@media (max-width: 1100px) {

    .report-summary {
        grid-template-columns: repeat(3, 1fr);
    }

    .report-filter-grid {
        grid-template-columns: repeat(2, 1fr);
    }

}


@media (max-width: 750px) {

    .report-summary {
        grid-template-columns: repeat(2, 1fr);
    }

    .report-search-row {
        grid-template-columns: 1fr;
    }

    .report-filter-actions {
        justify-content: flex-start;
    }

    .report-table-header {
        align-items: flex-start;
        flex-direction: column;
    }

}


@media (max-width: 500px) {

    .report-header {
        padding: 17px 18px;
    }

    .report-filter-card {
        padding: 15px;
    }

    .report-filter-grid {
        grid-template-columns: 1fr;
    }

    .report-summary {
        grid-template-columns: 1fr;
    }

    .report-summary-card {
        padding: 13px;
    }

    .report-filter-actions {
        width: 100%;
    }

    .report-filter-actions .btn,
    .report-reset-button {
        flex: 1;
    }

}


/* =====================================================
   PRINT STYLE
   ===================================================== */

@media print {

    @page {
        size: A4 portrait;
        margin: 15mm 13mm;
    }

    html,
    body {
        background: #ffffff !important;
    }

    body {
        color: #111111 !important;
        font-size: 10pt !important;
    }

    .navbar,
    header,
    footer,
    .site-footer,
    .report-header,
    .report-summary,
    .report-filter-card,
    .report-table-header,
    .report-detail-button,
    .report-actions,
    .btn,
    button {
        display: none !important;
    }

    main,
    .main-content,
    .container,
    .report-page {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .report-print-header {
        display: block !important;
        margin-bottom: 18px;
        text-align: center;
    }

    .report-print-header .print-brand {
        margin-bottom: 2px;
        color: #16834c;
        font-size: 18pt;
        font-weight: 700;
    }

    .report-print-header .print-title {
        margin-bottom: 8px;
        color: #111111;
        font-size: 15pt;
        font-weight: 700;
    }

    .report-print-header .print-subtitle {
        margin: 2px 0;
        color: #444444;
        font-size: 9.5pt;
    }

    .report-print-header .print-line {
        margin-top: 12px;
        border-bottom: 2px solid #16834c;
    }

    .report-table-card {
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        overflow: visible !important;
    }

    .report-table-wrapper {
        overflow: visible !important;
    }

    .report-table {
        width: 100% !important;
        min-width: 0 !important;
        border-collapse: collapse !important;
        font-size: 8.5pt !important;
    }

    .report-table th {
        padding: 7px 6px !important;
        background: #edf8f2 !important;
        color: #116b3d !important;
        border: 1px solid #bfcfc5 !important;
        font-size: 8pt !important;
    }

    .report-table td {
        padding: 6px !important;
        color: #222222 !important;
        border: 1px solid #d3d3d3 !important;
        font-size: 8pt !important;
        vertical-align: top !important;
    }

    .report-table tbody tr:hover {
        background: transparent !important;
    }

    .report-user strong,
    .report-room strong {
        color: #111111 !important;
        font-size: 8pt !important;
    }

    .report-room span {
        color: #555555 !important;
        font-size: 7pt !important;
    }

    .report-date,
    .report-time {
        color: #222222 !important;
        font-size: 8pt !important;
    }

    .report-purpose {
        max-width: none !important;
        min-width: 0 !important;
        font-size: 8pt !important;
    }

    .report-status .status-badge {
        padding: 3px 6px !important;
        border: 1px solid #aaaaaa !important;
        background: #ffffff !important;
        color: #222222 !important;
        font-size: 7.5pt !important;
    }

    .report-empty {
        padding: 30px 0 !important;
    }

}

</style>


<div class="report-page">


    <!-- =================================================
         HEADER
         ================================================= -->

    <section class="report-header">

        <span class="heading-label">
            LAPORAN
        </span>

        <h1>
            Laporan & Riwayat Peminjaman
        </h1>

        <p>
            Lihat, cari, dan cetak riwayat peminjaman ruangan berdasarkan data yang tersedia.
        </p>

    </section>


    <!-- =================================================
         RINGKASAN
         ================================================= -->

    <section class="report-summary">


        <!-- TOTAL -->

        <div class="report-summary-card">

            <div class="report-summary-icon">
                <i class="bi bi-list-check"></i>
            </div>

            <div class="report-summary-content">

                <span>
                    Total Data
                </span>

                <strong>
                    <?= $totalData ?>
                </strong>

            </div>

        </div>


        <!-- MENUNGGU -->

        <div class="report-summary-card">

            <div class="report-summary-icon">
                <i class="bi bi-clock"></i>
            </div>

            <div class="report-summary-content">

                <span>
                    Menunggu
                </span>

                <strong>
                    <?= $totalMenunggu ?>
                </strong>

            </div>

        </div>


        <!-- DISETUJUI -->

        <div class="report-summary-card">

            <div class="report-summary-icon">
                <i class="bi bi-check-circle"></i>
            </div>

            <div class="report-summary-content">

                <span>
                    Disetujui
                </span>

                <strong>
                    <?= $totalDisetujui ?>
                </strong>

            </div>

        </div>


        <!-- DITOLAK -->

        <div class="report-summary-card">

            <div class="report-summary-icon">
                <i class="bi bi-x-circle"></i>
            </div>

            <div class="report-summary-content">

                <span>
                    Ditolak
                </span>

                <strong>
                    <?= $totalDitolak ?>
                </strong>

            </div>

        </div>


        <!-- DIBATALKAN -->

        <div class="report-summary-card">

            <div class="report-summary-icon">
                <i class="bi bi-slash-circle"></i>
            </div>

            <div class="report-summary-content">

                <span>
                    Dibatalkan
                </span>

                <strong>
                    <?= $totalDibatalkan ?>
                </strong>

            </div>

        </div>


    </section>


    <!-- =================================================
         FILTER
         ================================================= -->

    <section class="report-filter-card">


        <div class="report-filter-heading">

            <i class="bi bi-funnel"></i>

            <strong>
                Filter Laporan
            </strong>

        </div>


        <form
            method="GET"
            action="reports.php"
        >


            <div class="report-filter-grid">


                <!-- TANGGAL MULAI -->

                <div class="report-filter-group">

                    <label for="tanggal_mulai">
                        Tanggal Mulai
                    </label>

                    <input
                        type="date"
                        name="tanggal_mulai"
                        id="tanggal_mulai"
                        class="form-control"
                        value="<?= htmlspecialchars(
                            $tanggalMulai
                        ) ?>"
                    >

                </div>


                <!-- TANGGAL SELESAI -->

                <div class="report-filter-group">

                    <label for="tanggal_selesai">
                        Tanggal Selesai
                    </label>

                    <input
                        type="date"
                        name="tanggal_selesai"
                        id="tanggal_selesai"
                        class="form-control"
                        value="<?= htmlspecialchars(
                            $tanggalSelesai
                        ) ?>"
                    >

                </div>


                <!-- STATUS -->

                <div class="report-filter-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        name="status"
                        id="status"
                        class="form-control"
                    >

                        <option value="">
                            Semua Status
                        </option>

                        <option
                            value="menunggu"
                            <?= $status === 'menunggu'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Menunggu
                        </option>

                        <option
                            value="disetujui"
                            <?= $status === 'disetujui'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Disetujui
                        </option>

                        <option
                            value="ditolak"
                            <?= $status === 'ditolak'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Ditolak
                        </option>

                        <option
                            value="dibatalkan"
                            <?= $status === 'dibatalkan'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Dibatalkan
                        </option>

                    </select>

                </div>


                <!-- RUANGAN -->

                <div class="report-filter-group">

                    <label for="room_id">
                        Ruangan
                    </label>

                    <select
                        name="room_id"
                        id="room_id"
                        class="form-control"
                    >

                        <option value="0">
                            Semua Ruangan
                        </option>

                        <?php foreach ($rooms as $room): ?>

                            <option
                                value="<?= (int) $room['id'] ?>"
                                <?= $roomId === (int) $room['id']
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $room['nama_ruangan']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


            </div>


            <!-- SEARCH + ACTION -->

            <div class="report-search-row">


                <div class="report-search">

                    <i class="bi bi-search"></i>

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Cari nama peminjam, ruangan, atau keperluan..."
                        value="<?= htmlspecialchars(
                            $search
                        ) ?>"
                    >

                </div>


                <div class="report-filter-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-search"></i>

                        Tampilkan

                    </button>


                    <a
                        href="reports.php"
                        class="report-reset-button"
                    >

                        <i class="bi bi-arrow-counterclockwise"></i>

                        Reset

                    </a>

                </div>


            </div>


        </form>


    </section>


    <!-- =================================================
         PRINT HEADER
         ================================================= -->

    <div class="report-print-header">

        <div class="print-brand">
            SIPERU
        </div>

        <div class="print-title">
            LAPORAN PEMINJAMAN RUANGAN
        </div>

        <div class="print-subtitle">
            Periode: <?= htmlspecialchars($filterTanggal) ?>
        </div>

        <div class="print-subtitle">
            Status: <?= htmlspecialchars($filterStatus) ?>
        </div>

        <div class="print-subtitle">
            Ruangan: <?= htmlspecialchars($filterRuangan) ?>
        </div>

        <?php if ($search !== ''): ?>

            <div class="print-subtitle">
                Pencarian:
                <?= htmlspecialchars($search) ?>
            </div>

        <?php endif; ?>

        <div class="print-subtitle">
            Dicetak:
            <?= date('d-m-Y H:i') ?>
        </div>

        <div class="print-line"></div>

    </div>


    <!-- =================================================
         TABLE
         ================================================= -->

    <section class="report-table-card">


        <div class="report-table-header">


            <div class="report-table-title">

                <span class="heading-label">
                    RIWAYAT
                </span>

                <h2>
                    Riwayat Peminjaman
                </h2>

                <p>
                    Menampilkan <?= $totalData ?> data peminjaman.
                </p>

            </div>


            <button
                type="button"
                class="report-print-button"
                onclick="window.print()"
            >

                <i class="bi bi-printer"></i>

                Cetak Laporan

            </button>


        </div>


        <?php if (!$bookings): ?>


            <div class="report-empty">

                <div class="report-empty-icon">

                    <i class="bi bi-file-earmark-text"></i>

                </div>

                <h3>
                    Data tidak ditemukan
                </h3>

                <p>
                    Tidak ada riwayat peminjaman yang sesuai dengan filter yang dipilih.
                </p>

            </div>


        <?php else: ?>


            <div class="report-table-wrapper">


                <table class="report-table">


                    <thead>

                        <tr>

                            <th class="report-number">
                                No
                            </th>

                            <th>
                                Peminjam
                            </th>

                            <th>
                                Ruangan
                            </th>

                            <th>
                                Tanggal
                            </th>

                            <th>
                                Waktu
                            </th>

                            <th>
                                Keperluan
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Detail
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach ($bookings as $index => $booking): ?>


                            <tr>


                                <!-- NO -->

                                <td class="report-number">

                                    <?= $index + 1 ?>

                                </td>


                                <!-- PEMINJAM -->

                                <td class="report-user">

                                    <strong>

                                        <?= htmlspecialchars(
                                            $booking['nama_peminjam']
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- RUANGAN -->

                                <td class="report-room">

                                    <strong>

                                        <?= htmlspecialchars(
                                            $booking['nama_ruangan']
                                        ) ?>

                                    </strong>

                                    <?php if (!empty($booking['lokasi'])): ?>

                                        <span>

                                            <i class="bi bi-geo-alt"></i>

                                            <?= htmlspecialchars(
                                                $booking['lokasi']
                                            ) ?>

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- TANGGAL -->

                                <td class="report-date">

                                    <?= date(
                                        'd-m-Y',
                                        strtotime(
                                            $booking['tanggal']
                                        )
                                    ) ?>

                                </td>


                                <!-- WAKTU -->

                                <td class="report-time">

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

                                </td>


                                <!-- KEPERLUAN -->

                                <td
                                    class="report-purpose
                                    <?= empty($booking['keperluan'])
                                        ? 'empty'
                                        : ''
                                    ?>"
                                >

                                    <?php if (!empty($booking['keperluan'])): ?>

                                        <?= nl2br(
                                            htmlspecialchars(
                                                $booking['keperluan']
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        Tidak ada keterangan

                                    <?php endif; ?>

                                </td>


                                <!-- STATUS -->

                                <td class="report-status">

                                    <?php

                                    switch ($booking['status']) {

                                        case 'disetujui':
                                            $statusClass = 'status-approved';
                                            $statusText = 'Disetujui';
                                            break;

                                        case 'ditolak':
                                            $statusClass = 'status-rejected';
                                            $statusText = 'Ditolak';
                                            break;

                                        case 'dibatalkan':
                                            $statusClass = 'status-cancelled';
                                            $statusText = 'Dibatalkan';
                                            break;

                                        default:
                                            $statusClass = 'status-waiting';
                                            $statusText = 'Menunggu';
                                            break;
                                    }

                                    ?>

                                    <span class="status-badge <?= $statusClass ?>">

                                        <?= $statusText ?>

                                    </span>

                                </td>


                                <!-- DETAIL -->

                                <td>

                                    <a
                                        href="booking_detail.php?id=<?= (int) $booking['id'] ?>"
                                        class="report-detail-button"
                                    >

                                        <i class="bi bi-eye"></i>

                                        Detail

                                    </a>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


        <?php endif; ?>


    </section>


</div>


<?php require '../config/footer.php'; ?>