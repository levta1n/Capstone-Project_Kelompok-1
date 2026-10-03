<?php
require 'config/auth.php';
require 'config/database.php';

if (!empty($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: user/dashboard.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE username = ?
        LIMIT 1
    ");

    $stmt->execute([$username]);

    $user = $stmt->fetch();

    if ($user && hash_equals($user['password'], md5($password))) {

        $_SESSION['user'] = $user;

        if ($user['role'] === 'admin') {
            header('Location: admin/dashboard.php');
        } else {
            header('Location: user/dashboard.php');
        }

        exit;
    }

    $error = 'Username atau password salah.';
}

$title = 'Login';
require 'config/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">

        <div class="hero mb-4">
            <h1 class="h3 fw-bold">SIPERU</h1>
            <p class="mb-0">
                Sistem Manajemen Peminjaman Ruangan
            </p>
        </div>

        <div class="card p-4">

            <h2 class="h5 mb-3">Login</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="post">

                <label class="form-label">Username</label>

                <input
                    type="text"
                    name="username"
                    class="form-control mb-3"
                    required
                >

                <label class="form-label">Password</label>

                <input
                    type="password"
                    name="password"
                    class="form-control mb-3"
                    required
                >

                <button type="submit" class="btn btn-primary w-100">
                    Masuk
                </button>

            </form>

            <div class="small text-muted mt-3">
                Demo: admin/admin123 atau user/user123
            </div>

        </div>

    </div>
</div>

<?php require 'config/footer.php'; ?>