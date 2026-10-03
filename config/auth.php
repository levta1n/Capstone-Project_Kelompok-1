<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function require_login() {
    if (empty($_SESSION['user'])) {
        header('Location: /peminjaman_ruangan_php/index.php');
        exit;
    }
}

function require_admin() {
    require_login();

    if ($_SESSION['user']['role'] !== 'admin') {
        header('Location: /peminjaman_ruangan_php/user/dashboard.php');
        exit;
    }
}
?>