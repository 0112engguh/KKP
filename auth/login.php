<?php
// auth/login.php
// Satu halaman login untuk tiga peran: Guru/Admin (NIP), Siswa (NISN), Orang Tua (NISN anak).

require_once __DIR__ . '/../includes/auth.php';

if (user()) {
    redirect(halaman_beranda(user()['role']));
}

$user_lama  = $_SESSION['login_username'] ?? '';
unset($_SESSION['login_peran'], $_SESSION['login_username']);

// Mode: ?peran=... (default siswa). Form guru ada di login-guru.php.
$peran_tetap = $_GET['peran'] ?? 'siswa';
if (!in_array($peran_tetap, ['guru', 'siswa', 'orangtua'], true)) $peran_tetap = 'siswa';
$info_username = ['guru'     => ['NIP', 'Guru/Admin masuk dengan NIP.'],
                  'siswa'    => ['NISN', 'Siswa masuk dengan NISN.'],
                  'orangtua' => ['NISN Anak', 'Orang tua masuk dengan NISN anak dan password orang tua.']];
$judul_halaman = ['guru' => 'Login Guru / Admin', 'siswa' => 'Login Siswa', 'orangtua' => 'Login Orang Tua'];
$labelUser = $info_username[$peran_tetap][0];
$petunjuk  = $info_username[$peran_tetap][1];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $peran_tetap ? e($judul_halaman[$peran_tetap]) . ' - ' : '' ?><?= e(APP_SEKOLAH) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/icons/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="halaman-login <?= $peran_tetap ? 'halaman-login-' . e($peran_tetap) : '' ?>">
<main class="login-wrap">
    <section class="login-card">
        <img src="<?= e(url('assets/images/logo.svg')) ?>" alt="Logo sekolah" width="72" height="72">
        <h1><?= e(APP_SEKOLAH) ?></h1>
        <?php if ($peran_tetap): ?><p class="login-role"><?= e($judul_halaman[$peran_tetap]) ?></p><?php endif; ?>
        <p class="login-sub">Sistem Informasi Akademik &amp; Monitoring 7 Kebiasaan Anak Indonesia Hebat</p>

        <?php foreach (ambil_flash() as $f): ?>
            <div class="flash flash-<?= e($f['tipe']) ?>" role="alert"><?= e($f['pesan']) ?></div>
        <?php endforeach; ?>

        <form method="post" action="<?= e(url('auth/proses-login.php')) ?>" autocomplete="off">
            <?= csrf_field() ?>

            <input type="hidden" name="peran" value="<?= e($peran_tetap) ?>">

            <div class="form-grup">
                <label for="username" id="labelUsername"><?= e($labelUser) ?></label>
                <input type="text" id="username" name="username" value="<?= e($user_lama) ?>" inputmode="numeric" required autofocus>
                <small id="petunjukUsername" class="petunjuk"><?= e($petunjuk) ?></small>
            </div>
            <div class="form-grup">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-blok">Masuk</button>
        </form>

        <nav class="login-pindah" aria-label="Pilihan login lain">
            <?php if ($peran_tetap === 'siswa'): ?>
                <a href="<?= e(url('auth/login.php?peran=guru')) ?>">Masuk sebagai Guru / Admin &rarr;</a>
                <a href="<?= e(url('auth/login.php?peran=orangtua')) ?>">Masuk sebagai Orang Tua &rarr;</a>
            <?php elseif ($peran_tetap === 'guru'): ?>
                <a href="<?= e(url('auth/login.php?peran=siswa')) ?>">&larr; Masuk sebagai Siswa</a>
                <a href="<?= e(url('auth/login.php?peran=orangtua')) ?>">Masuk sebagai Orang Tua &rarr;</a>
            <?php else: ?>
                <a href="<?= e(url('auth/login.php?peran=siswa')) ?>">&larr; Masuk sebagai Siswa</a>
                <a href="<?= e(url('auth/login.php?peran=guru')) ?>">Masuk sebagai Guru / Admin &rarr;</a>
            <?php endif; ?>
        </nav>
    </section>
</main>
</body>
</html>
