<?php

require '../config/auth.php';
require '../config/database.php';

require_admin();


/* =====================================================
   FOLDER UPLOAD
   ===================================================== */

$upload_dir = '../assets/rooms/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}


/* =====================================================
   PESAN
   ===================================================== */

$success = '';
$error = '';


/* =====================================================
   HAPUS RUANGAN
   ===================================================== */

if (isset($_GET['delete'])) {

    $id = (int) $_GET['delete'];

    $stmt = $pdo->prepare("
        SELECT foto
        FROM rooms
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    $room = $stmt->fetch();


    if ($room) {

        /*
         * Cek apakah ruangan sudah pernah
         * digunakan dalam peminjaman.
         */

        $check = $pdo->prepare("
            SELECT COUNT(*)
            FROM bookings
            WHERE room_id = ?
        ");

        $check->execute([$id]);

        $booking_count = $check->fetchColumn();


        if ($booking_count > 0) {

            $error =
                'Ruangan tidak dapat dihapus karena sudah memiliki data peminjaman.';

        } else {

            /*
             * Hapus foto jika ada.
             */

            if (!empty($room['foto'])) {

                $photo_path =
                    $upload_dir . $room['foto'];

                if (file_exists($photo_path)) {
                    unlink($photo_path);
                }
            }


            $stmt = $pdo->prepare("
                DELETE FROM rooms
                WHERE id = ?
            ");

            $stmt->execute([$id]);

            $success =
                'Ruangan berhasil dihapus.';
        }

    }

}


/* =====================================================
   EDIT DATA
   ===================================================== */

$edit_room = null;

if (isset($_GET['edit'])) {

    $id = (int) $_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM rooms
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    $edit_room = $stmt->fetch();

}


/* =====================================================
   TAMBAH / UPDATE
   ===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = (int) ($_POST['id'] ?? 0);

    $nama_ruangan =
        trim($_POST['nama_ruangan'] ?? '');

    $lokasi =
        trim($_POST['lokasi'] ?? '');

    $kapasitas =
        (int) ($_POST['kapasitas'] ?? 0);

    $fasilitas =
        trim($_POST['fasilitas'] ?? '');

    $status =
        $_POST['status'] ?? 'tersedia';


    /* -------------------------------------------------
       VALIDASI
       ------------------------------------------------- */

    if (
        $nama_ruangan === '' ||
        $lokasi === '' ||
        $kapasitas <= 0
    ) {

        $error =
            'Nama ruangan, lokasi, dan kapasitas wajib diisi.';

    } else {


        /* ---------------------------------------------
           UPLOAD FOTO
           --------------------------------------------- */

        $new_photo = null;


        if (
            isset($_FILES['foto']) &&
            $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            if (
                $_FILES['foto']['error']
                !== UPLOAD_ERR_OK
            ) {

                $error =
                    'Foto gagal diupload.';

            } else {

                $file_tmp =
                    $_FILES['foto']['tmp_name'];

                $file_name =
                    $_FILES['foto']['name'];

                $file_size =
                    $_FILES['foto']['size'];


                /* Ekstensi */

                $extension =
                    strtolower(
                        pathinfo(
                            $file_name,
                            PATHINFO_EXTENSION
                        )
                    );


                $allowed_extensions = [
                    'jpg',
                    'jpeg',
                    'png',
                    'webp'
                ];


                if (
                    !in_array(
                        $extension,
                        $allowed_extensions
                    )
                ) {

                    $error =
                        'Format foto harus JPG, JPEG, PNG, atau WEBP.';

                }


                /* Ukuran maksimal 5 MB */

                if ($file_size > 5 * 1024 * 1024) {

                    $error =
                        'Ukuran foto maksimal 5 MB.';

                }


                /* Pastikan benar-benar gambar */

                if (
                    !$error &&
                    getimagesize($file_tmp) === false
                ) {

                    $error =
                        'File yang dipilih bukan gambar yang valid.';

                }


                if (!$error) {

                    /*
                     * Buat nama file baru agar
                     * tidak bentrok dengan file lain.
                     */

                    $new_photo =
                        'room_' .
                        time() .
                        '_' .
                        bin2hex(
                            random_bytes(4)
                        ) .
                        '.' .
                        $extension;


                    $destination =
                        $upload_dir . $new_photo;


                    if (
                        !move_uploaded_file(
                            $file_tmp,
                            $destination
                        )
                    ) {

                        $error =
                            'Foto gagal disimpan.';
                    }

                }

            }

        }


        /* ---------------------------------------------
           SIMPAN DATABASE
           --------------------------------------------- */

        if (!$error) {


            /* =========================================
               UPDATE
               ========================================= */

            if ($id > 0) {

                $stmt = $pdo->prepare("
                    SELECT foto
                    FROM rooms
                    WHERE id = ?
                ");

                $stmt->execute([$id]);

                $old_room =
                    $stmt->fetch();


                if (!$old_room) {

                    $error =
                        'Data ruangan tidak ditemukan.';

                } else {


                    if ($new_photo) {

                        /*
                         * Hapus foto lama.
                         */

                        if (
                            !empty(
                                $old_room['foto']
                            )
                        ) {

                            $old_photo =
                                $upload_dir .
                                $old_room['foto'];

                            if (
                                file_exists(
                                    $old_photo
                                )
                            ) {

                                unlink(
                                    $old_photo
                                );
                            }

                        }


                        $stmt = $pdo->prepare("
                            UPDATE rooms
                            SET
                                nama_ruangan = ?,
                                lokasi = ?,
                                kapasitas = ?,
                                fasilitas = ?,
                                status = ?,
                                foto = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $nama_ruangan,
                            $lokasi,
                            $kapasitas,
                            $fasilitas,
                            $status,
                            $new_photo,
                            $id
                        ]);

                    } else {

                        $stmt = $pdo->prepare("
                            UPDATE rooms
                            SET
                                nama_ruangan = ?,
                                lokasi = ?,
                                kapasitas = ?,
                                fasilitas = ?,
                                status = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $nama_ruangan,
                            $lokasi,
                            $kapasitas,
                            $fasilitas,
                            $status,
                            $id
                        ]);

                    }


                    $success =
                        'Data ruangan berhasil diperbarui.';


                    $edit_room = null;

                }

            }


            /* =========================================
               TAMBAH
               ========================================= */

            else {

                $stmt = $pdo->prepare("
                    INSERT INTO rooms
                    (
                        nama_ruangan,
                        lokasi,
                        kapasitas,
                        fasilitas,
                        status,
                        foto
                    )
                    VALUES
                    (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $nama_ruangan,
                    $lokasi,
                    $kapasitas,
                    $fasilitas,
                    $status,
                    $new_photo
                ]);


                $success =
                    'Ruangan berhasil ditambahkan.';
            }

        }

    }

}


/* =====================================================
   DATA RUANGAN
   ===================================================== */

$rooms = $pdo->query("
    SELECT *
    FROM rooms
    ORDER BY nama_ruangan
")->fetchAll();


$title = 'Kelola Ruangan';

require '../config/header.php';

?>


<!-- =====================================================
     HEADER
     ===================================================== -->

<div class="rooms-page-header">

    <div>

        <span class="rooms-page-label">
            ADMINISTRATOR
        </span>

        <h1>
            Kelola Ruangan
        </h1>

        <p>
            Tambahkan dan kelola informasi ruangan yang
            tersedia untuk peminjaman.
        </p>

    </div>


    <a
        href="rooms.php"
        class="btn btn-primary"
    >
        <i class="bi bi-plus-lg"></i>
        Tambah Ruangan
    </a>

</div>



<!-- =====================================================
     ALERT
     ===================================================== -->

<?php if ($success): ?>

    <div class="alert alert-success">

        <i class="bi bi-check-circle me-2"></i>

        <?= htmlspecialchars($success) ?>

    </div>

<?php endif; ?>


<?php if ($error): ?>

    <div class="alert alert-danger">

        <i class="bi bi-exclamation-circle me-2"></i>

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>



<!-- =====================================================
     FORM
     ===================================================== -->

<div class="rooms-admin-grid">


    <!-- FORM -->

    <div class="rooms-form-card">

        <div class="rooms-card-header">

            <div>

                <span>
                    <?= $edit_room
                        ? 'EDIT RUANGAN'
                        : 'RUANGAN BARU' ?>
                </span>

                <h2>
                    <?= $edit_room
                        ? 'Perbarui ruangan'
                        : 'Tambah ruangan' ?>
                </h2>

            </div>

            <div class="rooms-header-icon">

                <i class="bi bi-building"></i>

            </div>

        </div>


        <form
            method="post"
            enctype="multipart/form-data"
        >


            <?php if ($edit_room): ?>

                <input
                    type="hidden"
                    name="id"
                    value="<?= $edit_room['id'] ?>"
                >

            <?php endif; ?>


            <!-- NAMA -->

            <div class="mb-3">

                <label class="form-label">
                    Nama Ruangan
                </label>

                <input
                    type="text"
                    name="nama_ruangan"
                    class="form-control"
                    placeholder="Contoh: Ruang 101"
                    value="<?= htmlspecialchars(
                        $edit_room['nama_ruangan'] ?? ''
                    ) ?>"
                    required
                >

            </div>


            <!-- LOKASI -->

            <div class="mb-3">

                <label class="form-label">
                    Lokasi
                </label>

                <input
                    type="text"
                    name="lokasi"
                    class="form-control"
                    placeholder="Contoh: Gedung A Lantai 1"
                    value="<?= htmlspecialchars(
                        $edit_room['lokasi'] ?? ''
                    ) ?>"
                    required
                >

            </div>


            <!-- KAPASITAS -->

            <div class="mb-3">

                <label class="form-label">
                    Kapasitas
                </label>

                <div class="input-group">

                    <input
                        type="number"
                        name="kapasitas"
                        class="form-control"
                        min="1"
                        placeholder="Contoh: 40"
                        value="<?= htmlspecialchars(
                            $edit_room['kapasitas'] ?? ''
                        ) ?>"
                        required
                    >

                    <span class="input-group-text">
                        orang
                    </span>

                </div>

            </div>


            <!-- FASILITAS -->

            <div class="mb-3">

                <label class="form-label">
                    Fasilitas
                </label>

                <textarea
                    name="fasilitas"
                    class="form-control"
                    rows="3"
                    placeholder="Contoh: AC, Proyektor, WiFi"
                ><?= htmlspecialchars(
                    $edit_room['fasilitas'] ?? ''
                ) ?></textarea>

            </div>


            <!-- STATUS -->

            <div class="mb-3">

                <label class="form-label">
                    Status Ruangan
                </label>

                <select
                    name="status"
                    class="form-select"
                >

                    <option
                        value="tersedia"
                        <?= (
                            ($edit_room['status'] ?? '')
                            === 'tersedia'
                        )
                            ? 'selected'
                            : '' ?>
                    >
                        Tersedia
                    </option>

                    <option
                        value="tidak tersedia"
                        <?= (
                            ($edit_room['status'] ?? '')
                            === 'tidak tersedia'
                        )
                            ? 'selected'
                            : '' ?>
                    >
                        Tidak Tersedia
                    </option>

                </select>

            </div>


            <!-- FOTO -->

            <div class="mb-3">

                <label class="form-label">
                    Foto Ruangan
                </label>


                <div class="photo-upload-box">

                    <i class="bi bi-image"></i>

                    <strong>
                        Pilih foto ruangan
                    </strong>

                    <span>
                        JPG, JPEG, PNG, atau WEBP • Maks. 5 MB
                    </span>


                    <input
                        type="file"
                        name="foto"
                        class="form-control"
                        accept=".jpg,.jpeg,.png,.webp,image/*"
                    >

                </div>


                <?php if (
                    $edit_room &&
                    !empty($edit_room['foto'])
                ): ?>


                    <div class="current-photo">

                        <img
                            src="../assets/rooms/<?= htmlspecialchars(
                                $edit_room['foto']
                            ) ?>"
                            alt="Foto ruangan"
                        >

                        <div>

                            <small>
                                Foto saat ini
                            </small>

                            <strong>
                                <?= htmlspecialchars(
                                    $edit_room['foto']
                                ) ?>
                            </strong>

                        </div>

                    </div>


                <?php endif; ?>

            </div>


            <!-- BUTTON -->

            <div class="rooms-form-actions">

                <?php if ($edit_room): ?>

                    <a
                        href="rooms.php"
                        class="btn btn-light"
                    >
                        Batal
                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-check-lg"></i>
                        Simpan Perubahan
                    </button>

                <?php else: ?>

                    <button
                        type="reset"
                        class="btn btn-light"
                    >
                        Reset
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-plus-lg"></i>
                        Tambah Ruangan
                    </button>

                <?php endif; ?>

            </div>


        </form>

    </div>



    <!-- DAFTAR RUANGAN -->

    <div class="rooms-list-card">

        <div class="rooms-card-header">

            <div>

                <span>
                    DATA RUANGAN
                </span>

                <h2>
                    Ruangan terdaftar
                </h2>

            </div>

            <div class="rooms-count">

                <?= count($rooms) ?>

            </div>

        </div>


        <div class="admin-room-list">


            <?php if (!$rooms): ?>


                <div class="admin-room-empty">

                    <i class="bi bi-building"></i>

                    <strong>
                        Belum ada ruangan
                    </strong>

                    <span>
                        Tambahkan ruangan menggunakan form.
                    </span>

                </div>


            <?php endif; ?>


            <?php foreach ($rooms as $room): ?>


                <div class="admin-room-item">


                    <!-- FOTO -->

                    <div class="admin-room-image">

                        <?php if (
                            !empty($room['foto'])
                        ): ?>

                            <img
                                src="../assets/rooms/<?= htmlspecialchars(
                                    $room['foto']
                                ) ?>"
                                alt="<?= htmlspecialchars(
                                    $room['nama_ruangan']
                                ) ?>"
                            >

                        <?php else: ?>

                            <div>

                                <i class="bi bi-building"></i>

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- INFO -->

                    <div class="admin-room-info">

                        <strong>

                            <?= htmlspecialchars(
                                $room['nama_ruangan']
                            ) ?>

                        </strong>


                        <span>

                            <i class="bi bi-geo-alt"></i>

                            <?= htmlspecialchars(
                                $room['lokasi']
                            ) ?>

                        </span>


                        <span>

                            <i class="bi bi-people"></i>

                            <?= $room['kapasitas'] ?>
                            orang

                        </span>


                    </div>


                    <!-- STATUS -->

                    <div>

                        <?php if (
                            $room['status']
                            === 'tersedia'
                        ): ?>

                            <span class="admin-room-status available">

                                Tersedia

                            </span>

                        <?php else: ?>

                            <span class="admin-room-status unavailable">

                                Tidak tersedia

                            </span>

                        <?php endif; ?>

                    </div>


                    <!-- ACTION -->

                    <div class="admin-room-actions">

                        <a
                            href="rooms.php?edit=<?= $room['id'] ?>"
                            class="room-action edit"
                            title="Edit"
                        >

                            <i class="bi bi-pencil"></i>

                        </a>


                        <a
                            href="rooms.php?delete=<?= $room['id'] ?>"
                            class="room-action delete"
                            title="Hapus"
                            onclick="return confirm('Yakin ingin menghapus ruangan ini?');"
                        >

                            <i class="bi bi-trash"></i>

                        </a>

                    </div>


                </div>


            <?php endforeach; ?>


        </div>

    </div>


</div>


<?php

require '../config/footer.php';

?>