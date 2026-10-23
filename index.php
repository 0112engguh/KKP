<?php
// index.php
// Pintu masuk: arahkan ke dashboard sesuai role, atau ke halaman login.

require_once __DIR__ . '/includes/auth.php';

if (user()) {
    redirect(halaman_beranda(user()['role']));
}
redirect(url('auth/login.php'));
