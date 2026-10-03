<?php

require '../config/auth.php';
require '../config/database.php';

require_login();


/* =====================================================
   AMBIL ID RUANGAN
   ===================================================== */

$roomId = (int) ($_GET['id'] ?? 0);


if (!$roomId) {
    header('Location: dashboard.php');
    exit;
}


/* =====================================================
   DATA RUANGAN
   ===================================================== */

$stmt = $pdo->prepare("
    SELECT *
    FROM rooms
    WHERE id = ?
    AND status = 'tersedia'
");

$stmt->execute([
    $roomId
]);

$room = $stmt->fetch();


/* =====================================================
   JIKA RUANGAN TIDAK DITEMUKAN
   ===================================================== */

if (!$room) {
    header('Location: dashboard.php');
    exit;
}


/* =====================================================
   FOTO
   ===================================================== */

if (!empty($room['foto'])) {

    $photo =
        '/peminjaman_ruangan_php/assets/rooms/'
        . $room['foto'];

} else {

    $photo = null;

}


$title = 'Detail Ruangan';

require '../config/header.php';

?>


<!-- =====================================================
     PAGE HEADER
     ===================================================== -->

<div class="booking-page-header">

    <div>

        <span class="booking-label">
            INFORMASI RUANGAN
        </span>

        <h1>
            Detail Ruangan
        </h1>

        <p>
            Lihat informasi lengkap mengenai ruangan sebelum mengajukan peminjaman.
        </p>

    </div>


    <a
        href="dashboard.php"
        class="booking-back-button"
    >
        <i class="bi bi-arrow-left"></i>
        Kembali
    </a>

</div>



<!-- =====================================================
     DETAIL RUANGAN
     ===================================================== -->

<div class="booking-layout">


    <!-- =================================================
         FOTO + INFORMASI
         ================================================= -->

    <div class="booking-card">

        <!-- FOTO -->

        <div
            style="
                width:100%;
                height:360px;
                border-radius:16px;
                overflow:hidden;
                background:#edf8f2;
                margin-bottom:25px;
            "
        >

            <?php if ($photo): ?>

                <img
                    src="<?= htmlspecialchars($photo) ?>"
                    alt="<?= htmlspecialchars($room['nama_ruangan']) ?>"
                    style="
                        width:100%;
                        height:100%;
                        object-fit:cover;
                    "
                >

            <?php else: ?>

                <div
                    style="
                        width:100%;
                        height:100%;
                        display:flex;
                        flex-direction:column;
                        align-items:center;
                        justify-content:center;
                        color:#7a847e;
                    "
                >

                    <i
                        class="bi bi-building"
                        style="font-size:60px;"
                    ></i>

                    <span style="margin-top:10px;">
                        Foto Ruangan
                    </span>

                </div>

            <?php endif; ?>

        </div>



        <!-- NAMA -->

        <div class="booking-card-header">

            <div class="booking-icon">

                <i class="bi bi-building"></i>

            </div>

            <div>

                <h2>
                    <?= htmlspecialchars($room['nama_ruangan']) ?>
                </h2>

                <p>
                    Ruangan tersedia untuk digunakan.
                </p>

            </div>

        </div>



        <!-- INFORMASI -->

        <div style="margin-top:25px;">

            <div class="booking-field">

                <label class="booking-field-label">

                    <i class="bi bi-geo-alt"></i>

                    Lokasi

                </label>

                <div class="form-control booking-input">

                    <?= htmlspecialchars($room['lokasi']) ?>

                </div>

            </div>



            <div class="booking-field">

                <label class="booking-field-label">

                    <i class="bi bi-people"></i>

                    Kapasitas

                </label>

                <div class="form-control booking-input">

                    <?= htmlspecialchars($room['kapasitas']) ?>
                    orang

                </div>

            </div>



            <div class="booking-field">

                <label class="booking-field-label">

                    <i class="bi bi-grid"></i>

                    Fasilitas

                </label>

                <div class="form-control booking-input">

                    <?= htmlspecialchars(
                        $room['fasilitas']
                        ?: 'Fasilitas standar'
                    ) ?>

                </div>

            </div>



            <div class="booking-field">

                <label class="booking-field-label">

                    <i class="bi bi-check-circle"></i>

                    Status

                </label>

                <div
                    style="
                        display:inline-flex;
                        align-items:center;
                        gap:8px;
                        padding:10px 15px;
                        border-radius:10px;
                        background:#edf8f2;
                        color:#16834c;
                        font-weight:600;
                    "
                >

                    <span
                        style="
                            width:8px;
                            height:8px;
                            border-radius:50%;
                            background:#16834c;
                        "
                    ></span>

                    Tersedia

                </div>

            </div>

        </div>



        <!-- BUTTON -->

        <div class="booking-actions">

            <a
                href="dashboard.php"
                class="btn booking-cancel"
            >
                Kembali
            </a>


            <a
                href="booking.php?room_id=<?= $room['id'] ?>"
                class="btn btn-primary booking-submit"
            >

                <i class="bi bi-calendar-plus"></i>

                Ajukan Peminjaman

            </a>

        </div>

    </div>



    <!-- =================================================
         INFO
         ================================================= -->

    <div class="booking-info">


        <div class="booking-info-card">

            <div class="booking-info-icon">

                <i class="bi bi-info-circle"></i>

            </div>


            <h3>
                Tentang Ruangan
            </h3>


            <p>
                Gunakan informasi ruangan di sebelah kiri
                sebagai pertimbangan sebelum mengajukan
                peminjaman.
            </p>


            <div class="booking-steps">


                <div class="booking-step">

                    <span>
                        <i class="bi bi-building"></i>
                    </span>

                    <div>

                        <strong>
                            Pilih ruangan
                        </strong>

                        <small>
                            Pastikan ruangan sesuai dengan kebutuhan kegiatan.
                        </small>

                    </div>

                </div>


                <div class="booking-step">

                    <span>
                        <i class="bi bi-calendar-check"></i>
                    </span>

                    <div>

                        <strong>
                            Tentukan jadwal
                        </strong>

                        <small>
                            Pilih tanggal dan waktu penggunaan ruangan.
                        </small>

                    </div>

                </div>


                <div class="booking-step">

                    <span>
                        <i class="bi bi-send"></i>
                    </span>

                    <div>

                        <strong>
                            Ajukan peminjaman
                        </strong>

                        <small>
                            Pengajuan akan diperiksa oleh admin.
                        </small>

                    </div>

                </div>


            </div>

        </div>


        <div class="booking-warning-card">

            <i class="bi bi-exclamation-triangle"></i>

            <div>

                <strong>
                    Perhatikan jadwal
                </strong>

                <p>
                    Pastikan tanggal dan waktu yang dipilih
                    sesuai dengan kebutuhan kegiatan.
                </p>

            </div>

        </div>


    </div>


</div>


<?php

require '../config/footer.php';

?>