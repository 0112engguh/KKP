<?php
// auth/logout.php
// Menghapus session lalu kembali ke halaman login.

require_once __DIR__ . '/../includes/auth.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

session_start();
flash('info', 'Anda sudah keluar. Sampai jumpa!');
redirect(url('auth/login.php'));
