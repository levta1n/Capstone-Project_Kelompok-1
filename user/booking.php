<?php

require '../config/auth.php';
require '../config/database.php';

require_login();

date_default_timezone_set('Asia/Jakarta');

/* =====================================================
   VARIABEL
   ===================================================== */

$error = '';
$success = '';
$showSuccessPopup = false;

$selectedRoomId = (int) ($_GET['room_id'] ?? $_POST['room_id'] ?? 0);


/* =====================================================
   CEK KETERSEDIAAN VIA AJAX
   Dipanggil saat user memilih tanggal/jam
   ===================================================== */

if (
    isset($_GET['check_availability']) &&
    $_GET['check_availability'] === '1'
) {
    header('Content-Type: application/json; charset=utf-8');

    $roomId = (int) ($_GET['room_id'] ?? 0);
    $tanggal = trim($_GET['tanggal'] ?? '');
    $jamMulai = trim($_GET['jam_mulai'] ?? '');
    $jamSelesai = trim($_GET['jam_selesai'] ?? '');

    if (
        $roomId <= 0 ||
        $tanggal === '' ||
        $jamMulai === '' ||
        $jamSelesai === ''
    ) {
        echo json_encode([
            'success' => false,
            'available' => false,
            'message' => 'Lengkapi tanggal, jam mulai, dan jam selesai.'
        ]);
        exit;
    }

    if ($jamMulai >= $jamSelesai) {
        echo json_encode([
            'success' => false,
            'available' => false,
            'message' => 'Jam selesai harus lebih besar dari jam mulai.'
        ]);
        exit;
    }

    /* ===============================
       CEK RUANGAN
       =============================== */

    $stmtRoomCheck = $pdo->prepare("
        SELECT id, nama_ruangan, status
        FROM rooms
        WHERE id = ?
        LIMIT 1
    ");

    $stmtRoomCheck->execute([$roomId]);
    $roomCheck = $stmtRoomCheck->fetch();

    if (!$roomCheck) {
        echo json_encode([
            'success' => false,
            'available' => false,
            'message' => 'Ruangan tidak ditemukan.'
        ]);
        exit;
    }

    if ($roomCheck['status'] !== 'tersedia') {
        echo json_encode([
            'success' => false,
            'available' => false,
            'message' => 'Ruangan sedang tidak tersedia untuk dipinjam.'
        ]);
        exit;
    }

    /* ===============================
       CEK BENTROK
       ===============================

       Contoh:
       Booking lama : 09.00 - 11.00
       Pengajuan    : 10.00 - 12.00
       -> BENTROK

       Booking lama : 09.00 - 11.00
       Pengajuan    : 11.00 - 13.00
       -> TIDAK BENTROK
    */

    $stmtConflict = $pdo->prepare("
        SELECT
            id,
            jam_mulai,
            jam_selesai,
            keperluan
        FROM bookings
        WHERE room_id = ?
        AND tanggal = ?
        AND status = 'disetujui'
        AND jam_mulai < ?
        AND jam_selesai > ?
        ORDER BY jam_mulai ASC
        LIMIT 5
    ");

    $stmtConflict->execute([
        $roomId,
        $tanggal,
        $jamSelesai,
        $jamMulai
    ]);

    $conflicts = $stmtConflict->fetchAll();

    if (!empty($conflicts)) {

        $conflictData = [];

        foreach ($conflicts as $conflict) {
            $conflictData[] = [
                'jam_mulai' => date(
                    'H:i',
                    strtotime($conflict['jam_mulai'])
                ),
                'jam_selesai' => date(
                    'H:i',
                    strtotime($conflict['jam_selesai'])
                ),
                'keperluan' => $conflict['keperluan']
            ];
        }

        $firstConflict = $conflictData[0];

        echo json_encode([
            'success' => true,
            'available' => false,
            'message' => 'Jadwal bentrok dengan peminjaman yang sudah disetujui.',
            'conflicts' => $conflictData,
            'first_conflict' => $firstConflict
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'available' => true,
        'message' => 'Ruangan tersedia untuk tanggal dan waktu yang dipilih.',
        'conflicts' => []
    ]);
    exit;
}


/* =====================================================
   PROSES PEMINJAMAN
   ===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $roomId = (int) ($_POST['room_id'] ?? 0);
    $tanggal = trim($_POST['tanggal'] ?? '');
    $jamMulai = trim($_POST['jam_mulai'] ?? '');
    $jamSelesai = trim($_POST['jam_selesai'] ?? '');
    $keperluan = trim($_POST['keperluan'] ?? '');

    $selectedRoomId = $roomId;

    /* ===============================
       VALIDASI INPUT
       =============================== */

    if (
        $roomId <= 0 ||
        empty($tanggal) ||
        empty($jamMulai) ||
        empty($jamSelesai) ||
        empty($keperluan)
    ) {

        $error = 'Semua data peminjaman wajib diisi.';

    } elseif ($jamMulai >= $jamSelesai) {

        $error = 'Jam selesai harus lebih besar dari jam mulai.';

    } elseif ($tanggal < date('Y-m-d')) {

        $error = 'Tanggal peminjaman tidak boleh sebelum hari ini.';

    } else {

        /* ===============================
           CEK RUANGAN
           =============================== */

        $stmtRoom = $pdo->prepare("
            SELECT *
            FROM rooms
            WHERE id = ?
            AND status = 'tersedia'
            LIMIT 1
        ");

        $stmtRoom->execute([$roomId]);
        $room = $stmtRoom->fetch();

        if (!$room) {

            $error = 'Ruangan yang dipilih tidak tersedia.';

        } else {

            /* ===============================
               CEK BENTROK JADWAL
               =============================== */

            $stmtConflict = $pdo->prepare("
                SELECT
                    id,
                    jam_mulai,
                    jam_selesai,
                    keperluan
                FROM bookings
                WHERE room_id = ?
                AND tanggal = ?
                AND status = 'disetujui'
                AND jam_mulai < ?
                AND jam_selesai > ?
                ORDER BY jam_mulai ASC
                LIMIT 1
            ");

            $stmtConflict->execute([
                $roomId,
                $tanggal,
                $jamSelesai,
                $jamMulai
            ]);

            $conflict = $stmtConflict->fetch();

            if ($conflict) {

                $conflictStart = date(
                    'H:i',
                    strtotime($conflict['jam_mulai'])
                );

                $conflictEnd = date(
                    'H:i',
                    strtotime($conflict['jam_selesai'])
                );

                $error =
                    'Jadwal bentrok. Ruangan sudah digunakan pada pukul ' .
                    $conflictStart .
                    '–' .
                    $conflictEnd .
                    '. Silakan pilih waktu lain.';

            } else {

                /* ===============================
                   SIMPAN PENGAJUAN
                   =============================== */

                $stmtInsert = $pdo->prepare("
                    INSERT INTO bookings (
                        user_id,
                        room_id,
                        tanggal,
                        jam_mulai,
                        jam_selesai,
                        keperluan,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, 'menunggu')
                ");

                $stmtInsert->execute([
                    $_SESSION['user']['id'],
                    $roomId,
                    $tanggal,
                    $jamMulai,
                    $jamSelesai,
                    $keperluan
                ]);

                $success =
                    'Pengajuan peminjaman berhasil dikirim dan sedang menunggu persetujuan.';

                $showSuccessPopup = true;

                /* Kosongkan form setelah berhasil */

                $tanggal = '';
                $jamMulai = '';
                $jamSelesai = '';
                $keperluan = '';
            }
        }
    }
}


/* =====================================================
   DATA STATUS RUANGAN HARI INI
   ===================================================== */

$today = date('Y-m-d');
$currentTime = date('H:i:s');

$roomAvailability = [];


/*
 * Ambil booking yang sudah disetujui hari ini.
 * Booking menunggu/ditolak/dibatalkan tidak dihitung.
 */

$stmtAvailability = $pdo->prepare("
    SELECT
        id,
        room_id,
        jam_mulai,
        jam_selesai,
        keperluan
    FROM bookings
    WHERE tanggal = ?
    AND status = 'disetujui'
    ORDER BY room_id ASC, jam_mulai ASC
");

$stmtAvailability->execute([$today]);

$todayBookings = $stmtAvailability->fetchAll();


foreach ($todayBookings as $booking) {

    $roomId = (int) $booking['room_id'];

    if (!isset($roomAvailability[$roomId])) {
        $roomAvailability[$roomId] = [];
    }

    $roomAvailability[$roomId][] = $booking;
}


/*
 * Fungsi menentukan status ruangan hari ini.
 */

function getRoomAvailabilityStatus(
    $roomId,
    $roomAvailability,
    $currentTime
) {

    $result = [
        'type' => 'empty',
        'label' => 'TIDAK ADA JADWAL HARI INI',
        'description' => 'Belum ada peminjaman yang disetujui hari ini.',
        'icon' => 'bi-calendar-x',
        'current' => null,
        'next' => null
    ];


    /*
     * Tidak ada booking hari ini.
     */

    if (empty($roomAvailability[$roomId])) {
        return $result;
    }


    foreach ($roomAvailability[$roomId] as $booking) {

        $start = $booking['jam_mulai'];
        $end = $booking['jam_selesai'];


        /*
         * Sedang digunakan.
         *
         * Jam mulai <= sekarang < jam selesai
         */

        if (
            $start <= $currentTime &&
            $currentTime < $end
        ) {

            $result['type'] = 'busy';

            $result['label'] = 'SEDANG DIGUNAKAN';

            $result['description'] =
                'Digunakan sampai pukul ' .
                date('H:i', strtotime($end));

            $result['icon'] = 'bi-door-closed';

            $result['current'] = $booking;

            return $result;
        }


        /*
         * Cari jadwal berikutnya.
         */

        if (
            $start > $currentTime &&
            $result['next'] === null
        ) {

            $result['next'] = $booking;
        }
    }


    /*
     * Ada jadwal berikutnya hari ini.
     */

    if ($result['next'] !== null) {

        $next = $result['next'];

        $result['type'] = 'upcoming';

        $result['label'] = 'TERSEDIA SEKARANG';

        $result['description'] =
            'Jadwal berikutnya pukul ' .
            date('H:i', strtotime($next['jam_mulai'])) .
            '–' .
            date('H:i', strtotime($next['jam_selesai']));

        $result['icon'] = 'bi-door-open';

        return $result;
    }


    /*
     * Semua jadwal hari ini sudah selesai.
     */

    $result['type'] = 'available';

    $result['label'] = 'TERSEDIA SEKARANG';

    $result['description'] =
        'Tidak ada penggunaan lagi untuk hari ini.';

    $result['icon'] = 'bi-door-open';

    return $result;
}


/* =====================================================
   JIKA BELUM MEMILIH RUANGAN
   TAMPILKAN DAFTAR RUANGAN
   ===================================================== */

$search = trim($_GET['search'] ?? '');
$kapasitas = $_GET['kapasitas'] ?? '';
$lokasi = trim($_GET['lokasi'] ?? '');

$rooms = [];


if ($selectedRoomId <= 0) {

    $sql = "
        SELECT *
        FROM rooms
        WHERE status = 'tersedia'
    ";

    $params = [];


    /* ===============================
       SEARCH
       =============================== */

    if ($search !== '') {

        $sql .= "
            AND (
                nama_ruangan LIKE ?
                OR lokasi LIKE ?
                OR fasilitas LIKE ?
            )
        ";

        $searchValue = '%' . $search . '%';

        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
    }


    /* ===============================
       FILTER KAPASITAS
       =============================== */

    if ($kapasitas === 'kecil') {

        $sql .= " AND kapasitas <= 20";

    } elseif ($kapasitas === 'sedang') {

        $sql .= " AND kapasitas BETWEEN 21 AND 50";

    } elseif ($kapasitas === 'besar') {

        $sql .= " AND kapasitas > 50";
    }


    /* ===============================
       FILTER LOKASI
       =============================== */

    if ($lokasi !== '') {

        $sql .= " AND lokasi LIKE ?";

        $params[] = '%' . $lokasi . '%';
    }


    $sql .= " ORDER BY nama_ruangan ASC";

    $stmtRooms = $pdo->prepare($sql);

    $stmtRooms->execute($params);

    $rooms = $stmtRooms->fetchAll();
}


/* =====================================================
   JIKA SUDAH MEMILIH RUANGAN
   AMBIL DATA RUANGAN
   ===================================================== */

$selectedRoom = null;


if ($selectedRoomId > 0) {

    $stmtSelectedRoom = $pdo->prepare("
        SELECT *
        FROM rooms
        WHERE id = ?
        AND status = 'tersedia'
        LIMIT 1
    ");

    $stmtSelectedRoom->execute([
        $selectedRoomId
    ]);

    $selectedRoom = $stmtSelectedRoom->fetch();


    if (!$selectedRoom) {

        $error = 'Ruangan yang dipilih tidak tersedia.';

        $selectedRoomId = 0;
    }
}


$title = 'Ajukan Peminjaman';

require '../config/header.php';

?>

<style>

/* =====================================================
   TOAST NOTIFICATION
   ===================================================== */

.booking-toast-container {
    position: fixed;
    top: 82px;
    right: 24px;
    z-index: 10000;
    width: min(380px, calc(100vw - 32px));
    pointer-events: none;
}

.booking-toast {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 15px 16px;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: 0 12px 35px rgba(20, 40, 28, .14);
    pointer-events: auto;
    animation: bookingToastShow .3s ease forwards;
}

.booking-toast.hide {
    animation: bookingToastHide .25s ease forwards;
}

.booking-toast-icon {
    flex: 0 0 38px;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.booking-toast-success .booking-toast-icon {
    background: var(--green-light);
    color: var(--green);
}

.booking-toast-error .booking-toast-icon {
    background: var(--red-light);
    color: var(--red);
}

.booking-toast-content {
    flex: 1;
    min-width: 0;
}

.booking-toast-title {
    margin: 1px 0 4px;
    font-size: 14px;
    font-weight: 700;
    color: var(--text);
}

.booking-toast-message {
    margin: 0;
    color: var(--text-soft);
    font-size: 12.5px;
    line-height: 1.5;
}

.booking-toast-close {
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
    transition: background .2s ease, color .2s ease;
}

.booking-toast-close:hover {
    background: var(--background);
    color: var(--text);
}

@keyframes bookingToastShow {
    from {
        opacity: 0;
        transform: translateX(25px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes bookingToastHide {
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
   POPUP PENGAJUAN BERHASIL
   ===================================================== */

.booking-success-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(20, 30, 24, .48);
    backdrop-filter: blur(3px);
    animation: bookingPopupFade .2s ease;
}

.booking-success-modal {
    width: 100%;
    max-width: 430px;
    background: var(--white);
    border-radius: 20px;
    padding: 32px 30px 28px;
    text-align: center;
    box-shadow: 0 18px 60px rgba(20, 40, 28, .18);
    animation: bookingPopupShow .25s ease;
}

.booking-success-icon {
    width: 68px;
    height: 68px;
    margin: 0 auto 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--green-light);
    color: var(--green);
    font-size: 32px;
}

.booking-success-modal h2 {
    margin: 0 0 8px;
    font-size: 21px;
    color: var(--text);
}

.booking-success-modal p {
    margin: 0 auto;
    max-width: 340px;
    color: var(--text-soft);
    font-size: 13.5px;
    line-height: 1.6;
}

.booking-success-actions {
    display: flex;
    gap: 10px;
    margin-top: 24px;
}

.booking-success-actions a {
    flex: 1;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}

.booking-success-actions .btn {
    margin: 0;
}

@keyframes bookingPopupFade {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

@keyframes bookingPopupShow {
    from {
        opacity: 0;
        transform: translateY(12px) scale(.97);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}


/* =====================================================
   INDIKATOR KETERSEDIAAN RUANGAN
   ===================================================== */

.booking-room-availability {
    position: absolute;
    top: 12px;
    left: 12px;
    right: 12px;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 8px;
    width: fit-content;
    max-width: calc(100% - 24px);
    padding: 8px 11px;
    border-radius: 10px;
    background: rgba(255, 255, 255, .96);
    box-shadow: 0 5px 18px rgba(20, 40, 28, .14);
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: .25px;
}

.booking-room-availability-dot {
    width: 9px;
    height: 9px;
    flex: 0 0 9px;
    border-radius: 50%;
}

.booking-room-availability-text {
    line-height: 1.2;
}


/* TERSEDIA */

.booking-room-availability-available {
    color: var(--green);
}

.booking-room-availability-available
.booking-room-availability-dot {
    background: #16834c;
}


/* SEDANG DIGUNAKAN */

.booking-room-availability-busy {
    color: var(--red);
}

.booking-room-availability-busy
.booking-room-availability-dot {
    background: #c64747;
}


/* TIDAK ADA JADWAL */

.booking-room-availability-empty {
    color: #68726c;
}

.booking-room-availability-empty
.booking-room-availability-dot {
    background: #8a938d;
}


/* =====================================================
   DETAIL STATUS DI BAWAH INFORMASI RUANGAN
   ===================================================== */

.booking-room-status-box {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-top: 13px;
    margin-bottom: 14px;
    padding: 11px 12px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: #fafcfb;
}

.booking-room-status-icon {
    width: 30px;
    height: 30px;
    flex: 0 0 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 14px;
}

.booking-room-status-content {
    min-width: 0;
}

.booking-room-status-title {
    display: block;
    margin-bottom: 2px;
    font-size: 11px;
    font-weight: 800;
}

.booking-room-status-description {
    margin: 0;
    color: var(--text-soft);
    font-size: 10.5px;
    line-height: 1.4;
}


/* STATUS TERSEDIA */

.booking-room-status-available {
    background: var(--green-light);
    border-color: #d8eadf;
}

.booking-room-status-available
.booking-room-status-icon {
    background: #d8f0e2;
    color: var(--green);
}

.booking-room-status-available
.booking-room-status-title {
    color: var(--green);
}


/* STATUS SEDANG DIGUNAKAN */

.booking-room-status-busy {
    background: var(--red-light);
    border-color: #f0dada;
}

.booking-room-status-busy
.booking-room-status-icon {
    background: #f8dede;
    color: var(--red);
}

.booking-room-status-busy
.booking-room-status-title {
    color: var(--red);
}


/* STATUS TIDAK ADA JADWAL */

.booking-room-status-empty {
    background: #f6f7f6;
    border-color: #e5e8e6;
}

.booking-room-status-empty
.booking-room-status-icon {
    background: #e9ebe9;
    color: #707a73;
}

.booking-room-status-empty
.booking-room-status-title {
    color: #667069;
}


/* =====================================================
   JADWAL YANG SEDANG BERJALAN
   ===================================================== */

.booking-room-current {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed #e2caca;
}

.booking-room-current-label {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 3px;
    color: var(--red);
    font-size: 10px;
    font-weight: 700;
}

.booking-room-current-time {
    color: var(--text);
    font-size: 13px;
    font-weight: 800;
}


/* =====================================================
   JADWAL BERIKUTNYA
   ===================================================== */

.booking-room-next {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed #e4d9bc;
}

.booking-room-next-label {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 3px;
    color: #896700;
    font-size: 10px;
    font-weight: 700;
}

.booking-room-next-time {
    color: var(--text);
    font-size: 12px;
    font-weight: 800;
}

.booking-room-next-purpose {
    margin-top: 3px;
    color: var(--muted);
    font-size: 10px;
    line-height: 1.4;
}


/* =====================================================
   POSISI FOTO
   ===================================================== */

.booking-room-photo {
    position: relative;
}


/* =====================================================
   CEK BENTROK JADWAL
   ===================================================== */

.booking-conflict-check {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    margin-top: 14px;
    padding: 13px 14px;
    border-radius: 11px;
    border: 1px solid var(--border);
    background: #f8faf9;
    transition:
        background .2s ease,
        border-color .2s ease;
}

.booking-conflict-check-icon {
    width: 32px;
    height: 32px;
    flex: 0 0 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    background: #eef1ef;
    color: var(--muted);
    font-size: 15px;
}

.booking-conflict-check-content {
    flex: 1;
    min-width: 0;
}

.booking-conflict-check-title {
    margin: 0 0 3px;
    font-size: 12px;
    font-weight: 800;
    color: var(--text);
}

.booking-conflict-check-message {
    margin: 0;
    color: var(--text-soft);
    font-size: 11.5px;
    line-height: 1.5;
}


/* PROSES CEK */

.booking-conflict-check.checking {
    background: #f7f9f8;
    border-color: #dfe6e2;
}

.booking-conflict-check.checking
.booking-conflict-check-icon {
    background: #edf1ef;
    color: var(--muted);
}


/* TERSEDIA */

.booking-conflict-check.available {
    background: var(--green-light);
    border-color: #cfe6d8;
}

.booking-conflict-check.available
.booking-conflict-check-icon {
    background: #d8f0e2;
    color: var(--green);
}

.booking-conflict-check.available
.booking-conflict-check-title {
    color: var(--green);
}


/* BENTROK */

.booking-conflict-check.conflict {
    background: var(--red-light);
    border-color: #efcccc;
}

.booking-conflict-check.conflict
.booking-conflict-check-icon {
    background: #f8dede;
    color: var(--red);
}

.booking-conflict-check.conflict
.booking-conflict-check-title {
    color: var(--red);
}


/* ERROR */

.booking-conflict-check.error {
    background: #fff8e8;
    border-color: #eadbb8;
}

.booking-conflict-check.error
.booking-conflict-check-icon {
    background: #fff0c9;
    color: #947000;
}

.booking-conflict-list {
    margin: 9px 0 0;
    padding: 0;
    list-style: none;
}

.booking-conflict-item {
    display: flex;
    align-items: flex-start;
    gap: 7px;
    margin-top: 5px;
    padding: 8px 9px;
    border-radius: 8px;
    background: rgba(255, 255, 255, .65);
    border: 1px solid rgba(198, 71, 71, .12);
}

.booking-conflict-item i {
    margin-top: 1px;
    color: var(--red);
    font-size: 11px;
}

.booking-conflict-item-content {
    min-width: 0;
}

.booking-conflict-item-time {
    display: block;
    color: var(--text);
    font-size: 11.5px;
    font-weight: 800;
}

.booking-conflict-item-purpose {
    display: block;
    margin-top: 2px;
    color: var(--muted);
    font-size: 10.5px;
    line-height: 1.4;
}


/* TOMBOL DISABLED */

.booking-form-actions .btn-primary:disabled {
    opacity: .55;
    cursor: not-allowed;
    pointer-events: none;
}


/* =====================================================
   RESPONSIVE
   ===================================================== */

@media (max-width: 480px) {

    .booking-toast-container {
        top: 72px;
        right: 16px;
        width: calc(100vw - 32px);
    }

    .booking-success-modal {
        padding: 28px 20px 22px;
    }

    .booking-success-actions {
        flex-direction: column;
    }

    .booking-room-availability {
        top: 9px;
        left: 9px;
        padding: 7px 9px;
        font-size: 9.5px;
    }
}

</style>


<?php if ($selectedRoomId <= 0): ?>

<!-- =====================================================
     HALAMAN PILIH RUANGAN
     ===================================================== -->

<section class="page-header">

    <div>

        <span class="heading-label">
            AJUKAN PEMINJAMAN
        </span>

        <h1>
            Pilih Ruangan
        </h1>

        <p>
            Pilih ruangan yang sesuai dengan kebutuhan kegiatanmu.
        </p>

    </div>

</section>


<!-- =====================================================
     FILTER RUANGAN
     ===================================================== -->

<form method="GET" class="booking-room-filter">

    <div class="form-group">

        <label>
            Cari Ruangan
        </label>

        <input
            type="text"
            name="search"
            value="<?= htmlspecialchars($search) ?>"
            placeholder="Cari nama ruangan, lokasi, atau fasilitas..."
            class="form-control"
        >

    </div>


    <div class="form-group">

        <label>
            Kapasitas
        </label>

        <select name="kapasitas" class="form-select">

            <option value="">
                Semua Kapasitas
            </option>

            <option
                value="kecil"
                <?= $kapasitas === 'kecil' ? 'selected' : '' ?>
            >
                ≤ 20 orang
            </option>

            <option
                value="sedang"
                <?= $kapasitas === 'sedang' ? 'selected' : '' ?>
            >
                21–50 orang
            </option>

            <option
                value="besar"
                <?= $kapasitas === 'besar' ? 'selected' : '' ?>
                > 50 orang
            </option>

        </select>

    </div>


    <div class="form-group">

        <label>
            Lokasi
        </label>

        <input
            type="text"
            name="lokasi"
            value="<?= htmlspecialchars($lokasi) ?>"
            placeholder="Contoh: Gedung A"
            class="form-control"
        >

    </div>


    <div class="booking-filter-action">

        <button
            type="submit"
            class="btn btn-primary"
        >

            <i class="bi bi-search"></i>

            Cari

        </button>

        <a
            href="booking.php"
            class="btn btn-secondary"
        >
            Reset
        </a>

    </div>

</form>


<?php if ($rooms): ?>

<!-- =====================================================
     DAFTAR RUANGAN
     ===================================================== -->

<section class="content-section">

    <div class="content-heading">

        <div>

            <span class="heading-label">
                RUANGAN TERSEDIA
            </span>

            <h2>
                Pilih Ruangan
            </h2>

            <p style="margin: 0; color: var(--muted); font-size: 13px;">
                Status di bawah menunjukkan kondisi ruangan berdasarkan jadwal yang telah disetujui hari ini.
            </p>

        </div>

    </div>


    <div class="booking-room-grid">

        <?php foreach ($rooms as $room): ?>

            <?php

            $roomId = (int) $room['id'];

            $availability = getRoomAvailabilityStatus(
                $roomId,
                $roomAvailability,
                $currentTime
            );

            ?>


            <div class="booking-room-card">


                <!-- FOTO -->

                <div class="booking-room-photo">

                    <?php if (!empty($room['foto'])): ?>

                        <img
                            src="../assets/rooms/<?= htmlspecialchars($room['foto']) ?>"
                            alt="<?= htmlspecialchars($room['nama_ruangan']) ?>"
                        >

                    <?php else: ?>

                        <div class="booking-room-photo-empty">

                            <i class="bi bi-building"></i>

                        </div>

                    <?php endif; ?>


                    <!-- INDIKATOR UTAMA -->

                    <div
                        class="
                            booking-room-availability
                            booking-room-availability-<?= htmlspecialchars($availability['type']) ?>
                        "
                    >

                        <span class="booking-room-availability-dot"></span>

                        <span class="booking-room-availability-text">

                            <?= htmlspecialchars($availability['label']) ?>

                        </span>

                    </div>

                </div>


                <!-- INFORMASI -->

                <div class="booking-room-body">

                    <h3>

                        <?= htmlspecialchars($room['nama_ruangan']) ?>

                    </h3>


                    <?php if (!empty($room['lokasi'])): ?>

                        <div class="booking-room-info">

                            <i class="bi bi-geo-alt"></i>

                            <?= htmlspecialchars($room['lokasi']) ?>

                        </div>

                    <?php endif; ?>


                    <div class="booking-room-info">

                        <i class="bi bi-people"></i>

                        Kapasitas

                        <?= (int) $room['kapasitas'] ?>

                        orang

                    </div>


                    <?php if (!empty($room['fasilitas'])): ?>

                        <div class="booking-room-facilities">

                            <?= htmlspecialchars($room['fasilitas']) ?>

                        </div>

                    <?php endif; ?>


                    <!-- STATUS DETAIL -->

                    <?php

                    $statusClass = '';

                    if ($availability['type'] === 'busy') {

                        $statusClass = 'booking-room-status-busy';

                    } elseif ($availability['type'] === 'empty') {

                        $statusClass = 'booking-room-status-empty';

                    } else {

                        $statusClass = 'booking-room-status-available';
                    }

                    ?>


                    <div
                        class="
                            booking-room-status-box
                            <?= $statusClass ?>
                        "
                    >

                        <div class="booking-room-status-icon">

                            <i class="bi <?= htmlspecialchars($availability['icon']) ?>"></i>

                        </div>


                        <div class="booking-room-status-content">

                            <span class="booking-room-status-title">

                                <?= htmlspecialchars($availability['label']) ?>

                            </span>


                            <p class="booking-room-status-description">

                                <?php if ($availability['type'] === 'busy'): ?>

                                    Ruangan sedang digunakan sampai pukul

                                    <strong>

                                        <?= date(
                                            'H:i',
                                            strtotime($availability['current']['jam_selesai'])
                                        ) ?>

                                    </strong>.

                                    <?php if (!empty($availability['current']['keperluan'])): ?>

                                        <br>

                                        Keperluan:

                                        <?= htmlspecialchars(
                                            $availability['current']['keperluan']
                                        ) ?>

                                    <?php endif; ?>


                                <?php elseif ($availability['type'] === 'upcoming'): ?>

                                    Ruangan kosong sekarang dan akan digunakan pada jadwal berikutnya.


                                <?php elseif ($availability['type'] === 'available'): ?>

                                    Tidak ada penggunaan lagi untuk ruangan ini hari ini.


                                <?php else: ?>

                                    Belum ada peminjaman yang disetujui untuk ruangan ini hari ini.

                                <?php endif; ?>

                            </p>


                            <!-- JADWAL SEDANG BERJALAN -->

                            <?php if (
                                $availability['type'] === 'busy' &&
                                $availability['current'] !== null
                            ): ?>

                                <div class="booking-room-current">

                                    <div class="booking-room-current-label">

                                        <i class="bi bi-clock"></i>

                                        Sedang digunakan

                                    </div>

                                    <div class="booking-room-current-time">

                                        <?= date(
                                            'H:i',
                                            strtotime($availability['current']['jam_mulai'])
                                        ) ?>

                                        –

                                        <?= date(
                                            'H:i',
                                            strtotime($availability['current']['jam_selesai'])
                                        ) ?>

                                    </div>

                                </div>

                            <?php endif; ?>


                            <!-- JADWAL BERIKUTNYA -->

                            <?php if (
                                $availability['next'] !== null
                            ): ?>

                                <div class="booking-room-next">

                                    <div class="booking-room-next-label">

                                        <i class="bi bi-arrow-right-circle"></i>

                                        Jadwal berikutnya

                                    </div>

                                    <div class="booking-room-next-time">

                                        <?= date(
                                            'H:i',
                                            strtotime($availability['next']['jam_mulai'])
                                        ) ?>

                                        –

                                        <?= date(
                                            'H:i',
                                            strtotime($availability['next']['jam_selesai'])
                                        ) ?>

                                    </div>


                                    <?php if (
                                        !empty($availability['next']['keperluan'])
                                    ): ?>

                                        <div class="booking-room-next-purpose">

                                            <?= htmlspecialchars(
                                                $availability['next']['keperluan']
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>


                    <!-- TOMBOL -->

                    <a
                        href="booking.php?room_id=<?= $roomId ?>"
                        class="btn btn-primary booking-room-button"
                    >

                        <i class="bi bi-calendar-plus"></i>

                        Ajukan Peminjaman

                    </a>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

</section>


<?php else: ?>

<!-- =====================================================
     TIDAK ADA RUANGAN
     ===================================================== -->

<div class="empty-state">

    <div class="empty-state-icon">

        <i class="bi bi-building-x"></i>

    </div>

    <h3>
        Ruangan tidak ditemukan
    </h3>

    <p>
        Tidak ada ruangan yang sesuai dengan pencarianmu.
    </p>

    <a
        href="booking.php"
        class="btn btn-primary"
    >

        Tampilkan Semua Ruangan

    </a>

</div>

<?php endif; ?>


<?php else: ?>


<!-- =====================================================
     FORM PEMINJAMAN
     ===================================================== -->

<section class="page-header">

    <div>

        <span class="heading-label">
            FORM PEMINJAMAN
        </span>

        <h1>
            Ajukan Peminjaman
        </h1>

        <p>
            Lengkapi data peminjaman ruangan berikut.
        </p>

    </div>


    <a
        href="booking.php"
        class="btn btn-secondary"
    >

        <i class="bi bi-arrow-left"></i>

        Pilih Ruangan Lain

    </a>

</section>


<?php if ($error): ?>

<div class="alert alert-danger">

    <i class="bi bi-exclamation-circle"></i>

    <?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>


<?php if ($success): ?>

<div class="alert alert-success">

    <i class="bi bi-check-circle"></i>

    <?= htmlspecialchars($success) ?>

</div>

<?php endif; ?>


<section class="booking-form-section">

    <div class="booking-form-layout">


        <!-- =================================================
             RUANGAN YANG DIPILIH
             ================================================= -->

        <div class="booking-selected-room">

            <div class="booking-selected-room-photo">

                <?php if (!empty($selectedRoom['foto'])): ?>

                    <img
                        src="../assets/rooms/<?= htmlspecialchars($selectedRoom['foto']) ?>"
                        alt="<?= htmlspecialchars($selectedRoom['nama_ruangan']) ?>"
                    >

                <?php else: ?>

                    <div class="booking-room-photo-empty">

                        <i class="bi bi-building"></i>

                    </div>

                <?php endif; ?>

            </div>


            <div class="booking-selected-room-content">

                <span class="heading-label">
                    RUANGAN DIPILIH
                </span>

                <h2>
                    <?= htmlspecialchars($selectedRoom['nama_ruangan']) ?>
                </h2>


                <?php if (!empty($selectedRoom['lokasi'])): ?>

                    <p>

                        <i class="bi bi-geo-alt"></i>

                        <?= htmlspecialchars($selectedRoom['lokasi']) ?>

                    </p>

                <?php endif; ?>


                <p>

                    <i class="bi bi-people"></i>

                    Kapasitas

                    <?= (int) $selectedRoom['kapasitas'] ?>

                    orang

                </p>


                <?php if (!empty($selectedRoom['fasilitas'])): ?>

                    <p>

                        <i class="bi bi-check2-circle"></i>

                        <?= htmlspecialchars($selectedRoom['fasilitas']) ?>

                    </p>

                <?php endif; ?>


                <a
                    href="booking.php"
                    class="btn btn-secondary"
                >

                    <i class="bi bi-arrow-left"></i>

                    Ganti Ruangan

                </a>

            </div>

        </div>


        <!-- =================================================
             FORM
             ================================================= -->

        <div class="booking-form">

            <div class="booking-form-heading">

                <span class="heading-label">
                    DATA PEMINJAMAN
                </span>

                <h2>
                    Lengkapi Formulir
                </h2>

            </div>


            <form
                method="POST"
                id="bookingForm"
                novalidate
            >

                <input
                    type="hidden"
                    name="room_id"
                    id="booking_room_id"
                    value="<?= (int) $selectedRoom['id'] ?>"
                >


                <!-- TANGGAL -->

                <div class="form-group">

                    <label for="tanggal">
                        Tanggal Peminjaman
                    </label>

                    <input
                        type="date"
                        id="tanggal"
                        name="tanggal"
                        class="form-control"
                        value="<?= htmlspecialchars($tanggal ?? '') ?>"
                        min="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>


                <!-- JAM -->

                <div class="booking-time-grid">

                    <div class="form-group">

                        <label for="jam_mulai">
                            Jam Mulai
                        </label>

                        <input
                            type="time"
                            id="jam_mulai"
                            name="jam_mulai"
                            class="form-control"
                            value="<?= htmlspecialchars($jamMulai ?? '') ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="jam_selesai">
                            Jam Selesai
                        </label>

                        <input
                            type="time"
                            id="jam_selesai"
                            name="jam_selesai"
                            class="form-control"
                            value="<?= htmlspecialchars($jamSelesai ?? '') ?>"
                            required
                        >

                    </div>

                </div>


                <!-- =================================================
                     HASIL CEK BENTROK
                     ================================================= -->

                <div
                    class="booking-conflict-check"
                    id="bookingConflictCheck"
                    style="display: none;"
                >

                    <div class="booking-conflict-check-icon">

                        <i
                            class="bi bi-calendar-check"
                            id="bookingConflictIcon"
                        ></i>

                    </div>


                    <div class="booking-conflict-check-content">

                        <div
                            class="booking-conflict-check-title"
                            id="bookingConflictTitle"
                        >
                            Memeriksa jadwal...
                        </div>

                        <p
                            class="booking-conflict-check-message"
                            id="bookingConflictMessage"
                        >
                            Sistem sedang memeriksa ketersediaan ruangan.
                        </p>


                        <div
                            id="bookingConflictList"
                        ></div>

                    </div>

                </div>


                <!-- KEPERLUAN -->

                <div class="form-group">

                    <label for="keperluan">
                        Keperluan Peminjaman
                    </label>

                    <textarea
                        id="keperluan"
                        name="keperluan"
                        class="form-control"
                        rows="5"
                        placeholder="Jelaskan kegiatan yang akan dilaksanakan..."
                        required
                    ><?= htmlspecialchars($keperluan ?? '') ?></textarea>

                </div>


                <!-- INFORMASI -->

                <div class="booking-info-box">

                    <i class="bi bi-info-circle"></i>

                    <div>

                        <strong>
                            Informasi
                        </strong>

                        <p>

                            Pengajuan akan berstatus

                            <strong>Menunggu</strong>

                            sampai disetujui oleh admin.

                        </p>

                    </div>

                </div>


                <!-- TOMBOL -->

                <div class="booking-form-actions">

                    <a
                        href="booking.php"
                        class="btn btn-secondary"
                    >
                        Batal
                    </a>


                    <button
                        type="submit"
                        class="btn btn-primary"
                        id="submitBookingButton"
                    >

                        <i class="bi bi-send"></i>

                        Ajukan Peminjaman

                    </button>

                </div>

            </form>

        </div>

    </div>

</section>


<?php endif; ?>


<!-- =====================================================
     TOAST ERROR
     ===================================================== -->

<?php if ($error): ?>

<div class="booking-toast-container">

    <div
        class="booking-toast booking-toast-error"
        id="bookingErrorToast"
        role="alert"
    >

        <div class="booking-toast-icon">

            <i class="bi bi-exclamation-circle"></i>

        </div>


        <div class="booking-toast-content">

            <div class="booking-toast-title">
                Pengajuan Belum Berhasil
            </div>

            <p class="booking-toast-message">

                <?= htmlspecialchars($error) ?>

            </p>

        </div>


        <button
            type="button"
            class="booking-toast-close"
            aria-label="Tutup"
            onclick="closeBookingToast('bookingErrorToast')"
        >

            <i class="bi bi-x-lg"></i>

        </button>

    </div>

</div>

<?php endif; ?>


<!-- =====================================================
     TOAST BERHASIL
     ===================================================== -->

<?php if ($success && !$showSuccessPopup): ?>

<div class="booking-toast-container">

    <div
        class="booking-toast booking-toast-success"
        id="bookingSuccessToast"
        role="alert"
    >

        <div class="booking-toast-icon">

            <i class="bi bi-check-lg"></i>

        </div>


        <div class="booking-toast-content">

            <div class="booking-toast-title">
                Berhasil
            </div>

            <p class="booking-toast-message">

                <?= htmlspecialchars($success) ?>

            </p>

        </div>


        <button
            type="button"
            class="booking-toast-close"
            aria-label="Tutup"
            onclick="closeBookingToast('bookingSuccessToast')"
        >

            <i class="bi bi-x-lg"></i>

        </button>

    </div>

</div>

<?php endif; ?>


<!-- =====================================================
     POPUP BERHASIL
     ===================================================== -->

<?php if ($showSuccessPopup): ?>

<div
    class="booking-success-overlay"
    id="bookingSuccessPopup"
>

    <div
        class="booking-success-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="bookingSuccessTitle"
    >

        <div class="booking-success-icon">

            <i class="bi bi-check-lg"></i>

        </div>


        <h2 id="bookingSuccessTitle">
            Pengajuan Berhasil
        </h2>


        <p>

            Pengajuan peminjaman ruangan berhasil dikirim
            dan sedang menunggu persetujuan admin.

        </p>


        <div class="booking-success-actions">

            <a
                href="my_bookings.php"
                class="btn btn-primary"
            >

                <i class="bi bi-list-check me-1"></i>

                Lihat Pengajuan

            </a>


            <a
                href="dashboard.php"
                class="btn btn-secondary"
            >

                Kembali ke Dashboard

            </a>

        </div>

    </div>

</div>

<?php endif; ?>


<!-- =====================================================
     JAVASCRIPT
     ===================================================== -->

<script>

/* =====================================================
   TOAST
   ===================================================== */

function closeBookingToast(id) {

    const toast = document.getElementById(id);

    if (!toast) {
        return;
    }

    toast.classList.add('hide');

    setTimeout(function () {

        if (toast.parentElement) {
            toast.parentElement.remove();
        }

    }, 250);
}


function showBookingToast(message, type = 'error') {

    const oldContainer =
        document.querySelector('.booking-toast-container');

    if (oldContainer) {
        oldContainer.remove();
    }


    const container =
        document.createElement('div');

    container.className =
        'booking-toast-container';


    const toast =
        document.createElement('div');

    toast.className =
        'booking-toast ' +
        (
            type === 'success'
                ? 'booking-toast-success'
                : 'booking-toast-error'
        );

    toast.setAttribute(
        'role',
        'alert'
    );


    const icon =
        type === 'success'
            ? 'bi-check-lg'
            : 'bi-exclamation-circle';


    const title =
        type === 'success'
            ? 'Berhasil'
            : 'Perhatian';


    toast.innerHTML = `
        <div class="booking-toast-icon">
            <i class="bi ${icon}"></i>
        </div>

        <div class="booking-toast-content">

            <div class="booking-toast-title">
                ${title}
            </div>

            <p class="booking-toast-message">
                ${message}
            </p>

        </div>

        <button
            type="button"
            class="booking-toast-close"
            aria-label="Tutup"
        >
            <i class="bi bi-x-lg"></i>
        </button>
    `;


    container.appendChild(toast);

    document.body.appendChild(container);


    const closeButton =
        toast.querySelector(
            '.booking-toast-close'
        );


    closeButton.addEventListener(
        'click',
        function () {

            closeBookingToastElement(toast);

        }
    );


    setTimeout(
        function () {

            closeBookingToastElement(toast);

        },
        4500
    );
}


function closeBookingToastElement(toast) {

    if (!toast) {
        return;
    }

    toast.classList.add('hide');

    setTimeout(function () {

        const container =
            toast.closest(
                '.booking-toast-container'
            );

        if (container) {
            container.remove();
        }

    }, 250);
}


/* =====================================================
   CEK BENTROK JADWAL
   ===================================================== */

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const bookingForm =
            document.getElementById('bookingForm');

        const roomIdInput =
            document.getElementById('booking_room_id');

        const tanggal =
            document.getElementById('tanggal');

        const jamMulai =
            document.getElementById('jam_mulai');

        const jamSelesai =
            document.getElementById('jam_selesai');

        const submitButton =
            document.getElementById('submitBookingButton');

        const conflictBox =
            document.getElementById('bookingConflictCheck');

        const conflictIcon =
            document.getElementById('bookingConflictIcon');

        const conflictTitle =
            document.getElementById('bookingConflictTitle');

        const conflictMessage =
            document.getElementById('bookingConflictMessage');

        const conflictList =
            document.getElementById('bookingConflictList');


        if (
            !bookingForm ||
            !roomIdInput ||
            !tanggal ||
            !jamMulai ||
            !jamSelesai ||
            !submitButton ||
            !conflictBox
        ) {
            return;
        }


        let availabilityRequest = null;

        let availabilityTimer = null;

        let lastCheckedKey = '';

        let currentAvailability = null;


        /* =================================================
           FUNGSI RESET STATUS
           ================================================= */

        function resetConflictCheck() {

            conflictBox.style.display = 'none';

            conflictBox.classList.remove(
                'checking',
                'available',
                'conflict',
                'error'
            );

            conflictList.innerHTML = '';

            currentAvailability = null;

            submitButton.disabled = false;
        }


        /* =================================================
           TAMPILKAN STATUS CEK
           ================================================= */

        function showChecking() {

            conflictBox.style.display = 'flex';

            conflictBox.classList.remove(
                'available',
                'conflict',
                'error'
            );

            conflictBox.classList.add(
                'checking'
            );


            conflictIcon.className =
                'bi bi-arrow-repeat';


            conflictTitle.textContent =
                'Memeriksa ketersediaan...';


            conflictMessage.textContent =
                'Sistem sedang mengecek jadwal ruangan yang dipilih.';


            conflictList.innerHTML = '';

            submitButton.disabled = true;
        }


        /* =================================================
           STATUS TERSEDIA
           ================================================= */

        function showAvailable(message) {

            conflictBox.style.display = 'flex';

            conflictBox.classList.remove(
                'checking',
                'conflict',
                'error'
            );

            conflictBox.classList.add(
                'available'
            );


            conflictIcon.className =
                'bi bi-check-circle-fill';


            conflictTitle.textContent =
                'Jadwal tersedia';


            conflictMessage.textContent =
                message ||
                'Tidak ada peminjaman lain pada waktu tersebut.';


            conflictList.innerHTML = '';

            submitButton.disabled = false;

            currentAvailability = true;
        }


        /* =================================================
           STATUS BENTROK
           ================================================= */

        function showConflict(data) {

            conflictBox.style.display = 'flex';

            conflictBox.classList.remove(
                'checking',
                'available',
                'error'
            );

            conflictBox.classList.add(
                'conflict'
            );


            conflictIcon.className =
                'bi bi-calendar-x-fill';


            conflictTitle.textContent =
                'Jadwal bentrok';


            conflictMessage.textContent =
                data.message ||
                'Ruangan sudah digunakan pada waktu yang dipilih.';


            conflictList.innerHTML = '';


            if (
                data.conflicts &&
                data.conflicts.length
            ) {

                const list =
                    document.createElement('ul');

                list.className =
                    'booking-conflict-list';


                data.conflicts.forEach(
                    function (item) {

                        const li =
                            document.createElement('li');

                        li.className =
                            'booking-conflict-item';


                        const icon =
                            document.createElement('i');

                        icon.className =
                            'bi bi-clock-fill';


                        const content =
                            document.createElement('div');

                        content.className =
                            'booking-conflict-item-content';


                        const time =
                            document.createElement('span');

                        time.className =
                            'booking-conflict-item-time';

                        time.textContent =
                            item.jam_mulai +
                            ' – ' +
                            item.jam_selesai;


                        content.appendChild(time);


                        if (item.keperluan) {

                            const purpose =
                                document.createElement('span');

                            purpose.className =
                                'booking-conflict-item-purpose';

                            purpose.textContent =
                                'Keperluan: ' +
                                item.keperluan;

                            content.appendChild(
                                purpose
                            );
                        }


                        li.appendChild(icon);

                        li.appendChild(content);

                        list.appendChild(li);

                    }
                );


                conflictList.appendChild(list);
            }


            submitButton.disabled = true;

            currentAvailability = false;
        }


        /* =================================================
           STATUS ERROR
           ================================================= */

        function showAvailabilityError(message) {

            conflictBox.style.display = 'flex';

            conflictBox.classList.remove(
                'checking',
                'available',
                'conflict'
            );

            conflictBox.classList.add(
                'error'
            );


            conflictIcon.className =
                'bi bi-exclamation-triangle-fill';


            conflictTitle.textContent =
                'Belum dapat memeriksa jadwal';


            conflictMessage.textContent =
                message ||
                'Terjadi masalah saat memeriksa ketersediaan.';


            conflictList.innerHTML = '';

            submitButton.disabled = true;

            currentAvailability = null;
        }


        /* =================================================
           CEK KE SERVER
           ================================================= */

        async function checkAvailability() {

            const roomId =
                roomIdInput.value;

            const date =
                tanggal.value;

            const start =
                jamMulai.value;

            const end =
                jamSelesai.value;


            if (
                !roomId ||
                !date ||
                !start ||
                !end
            ) {

                resetConflictCheck();

                return;
            }


            if (start >= end) {

                showAvailabilityError(
                    'Jam selesai harus lebih besar dari jam mulai.'
                );

                return;
            }


            const checkKey =
                roomId +
                '|' +
                date +
                '|' +
                start +
                '|' +
                end;


            if (checkKey === lastCheckedKey) {
                return;
            }


            lastCheckedKey = checkKey;


            if (availabilityRequest) {

                availabilityRequest.abort();

                availabilityRequest = null;
            }


            showChecking();


            const controller =
                new AbortController();

            availabilityRequest =
                controller;


            try {

                const params =
                    new URLSearchParams({

                        check_availability: '1',

                        room_id: roomId,

                        tanggal: date,

                        jam_mulai: start,

                        jam_selesai: end

                    });


                const response =
                    await fetch(
                        'booking.php?' +
                        params.toString(),
                        {
                            method: 'GET',
                            headers: {
                                'X-Requested-With':
                                    'XMLHttpRequest'
                            },
                            signal:
                                controller.signal,
                            cache: 'no-store'
                        }
                    );


                if (!response.ok) {

                    throw new Error(
                        'Gagal menghubungi server.'
                    );
                }


                const data =
                    await response.json();


                if (!data.success) {

                    showAvailabilityError(
                        data.message
                    );

                    return;
                }


                if (data.available) {

                    showAvailable(
                        data.message
                    );

                } else {

                    showConflict(data);
                }


            } catch (error) {

                if (
                    error.name ===
                    'AbortError'
                ) {
                    return;
                }


                showAvailabilityError(
                    'Tidak dapat memeriksa ketersediaan ruangan. Silakan coba lagi.'
                );

            } finally {

                if (
                    availabilityRequest ===
                    controller
                ) {

                    availabilityRequest =
                        null;
                }
            }
        }


        /* =================================================
           JADWALKAN PENGECEKAN
           ================================================= */

        function scheduleAvailabilityCheck() {

            clearTimeout(
                availabilityTimer
            );


            lastCheckedKey = '';

            currentAvailability = null;


            const date =
                tanggal.value;

            const start =
                jamMulai.value;

            const end =
                jamSelesai.value;


            if (
                !date ||
                !start ||
                !end
            ) {

                resetConflictCheck();

                return;
            }


            if (start >= end) {

                showAvailabilityError(
                    'Jam selesai harus lebih besar dari jam mulai.'
                );

                return;
            }


            availabilityTimer =
                setTimeout(
                    function () {

                        checkAvailability();

                    },
                    250
                );
        }


        /* =================================================
           UPDATE MIN JAM SELESAI
           ================================================= */

        function updateJamSelesai() {

            if (jamMulai.value) {

                jamSelesai.min =
                    jamMulai.value;


                if (
                    jamSelesai.value &&
                    jamSelesai.value <=
                    jamMulai.value
                ) {

                    jamSelesai.value = '';

                    lastCheckedKey = '';

                    resetConflictCheck();
                }

            } else {

                jamSelesai.removeAttribute(
                    'min'
                );
            }
        }


        /* =================================================
           EVENT TANGGAL
           ================================================= */

        tanggal.addEventListener(
            'change',
            function () {

                lastCheckedKey = '';

                scheduleAvailabilityCheck();

            }
        );


        /* =================================================
           EVENT JAM MULAI
           ================================================= */

        jamMulai.addEventListener(
            'change',
            function () {

                updateJamSelesai();

                scheduleAvailabilityCheck();

            }
        );


        jamMulai.addEventListener(
            'input',
            function () {

                updateJamSelesai();

            }
        );


        /* =================================================
           EVENT JAM SELESAI
           ================================================= */

        jamSelesai.addEventListener(
            'change',
            function () {

                if (
                    jamMulai.value &&
                    jamSelesai.value &&
                    jamSelesai.value <=
                    jamMulai.value
                ) {

                    showBookingToast(
                        'Jam selesai harus lebih besar dari jam mulai.',
                        'error'
                    );


                    jamSelesai.value = '';

                    lastCheckedKey = '';

                    resetConflictCheck();

                    return;
                }


                scheduleAvailabilityCheck();

            }
        );


        /* =================================================
           BLOK SUBMIT JIKA MASIH BENTROK
           ================================================= */

        bookingForm.addEventListener(
            'submit',
            function (event) {

                const date =
                    tanggal.value;

                const start =
                    jamMulai.value;

                const end =
                    jamSelesai.value;


                if (
                    !date ||
                    !start ||
                    !end
                ) {

                    return;
                }


                if (start >= end) {

                    event.preventDefault();

                    showBookingToast(
                        'Jam selesai harus lebih besar dari jam mulai.',
                        'error'
                    );

                    return;
                }


                /*
                 * Kalau hasil terakhir menunjukkan bentrok,
                 * jangan izinkan submit.
                 */

                if (
                    currentAvailability === false
                ) {

                    event.preventDefault();

                    showBookingToast(
                        'Jadwal peminjaman bentrok. Silakan pilih waktu lain.',
                        'error'
                    );

                    return;
                }


                /*
                 * Jika sedang dalam proses pengecekan,
                 * jangan izinkan submit.
                 */

                if (
                    currentAvailability === null
                ) {

                    event.preventDefault();

                    showBookingToast(
                        'Tunggu sampai pengecekan ketersediaan ruangan selesai.',
                        'error'
                    );

                    checkAvailability();

                    return;
                }

            }
        );


        /* =================================================
           INISIALISASI
           ================================================= */

        updateJamSelesai();


        /*
         * Kalau form sudah memiliki tanggal dan jam
         * setelah terjadi error server, cek kembali.
         */

        if (
            tanggal.value &&
            jamMulai.value &&
            jamSelesai.value
        ) {

            scheduleAvailabilityCheck();

        }


        /* =================================================
           OTOMATIS TUTUP TOAST SERVER
           ================================================= */

        const serverToast =
            document.querySelector(
                '.booking-toast'
            );


        if (serverToast) {

            setTimeout(
                function () {

                    closeBookingToastElement(
                        serverToast
                    );

                },
                4500
            );
        }

    }
);

</script>


<?php require '../config/footer.php'; ?>