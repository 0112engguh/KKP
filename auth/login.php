<?php
// auth/login.php
// Satu halaman login untuk tiga peran: Guru/Admin (NIP), Siswa (NISN), Orang Tua (NISN anak).

require_once __DIR__ . '/../includes/auth.php';

if (user()) {
    redirect(halaman_beranda(user()['role']));
}

$peran_lama = $_SESSION['login_peran'] ?? 'guru';
$user_lama  = $_SESSION['login_username'] ?? '';
unset($_SESSION['login_peran'], $_SESSION['login_username']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= e(APP_SEKOLAH) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/icons/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="halaman-login">
<main class="login-wrap">
    <section class="login-card">
        <img src="<?= e(url('assets/images/logo.svg')) ?>" alt="Logo sekolah" width="72" height="72">
        <h1><?= e(APP_SEKOLAH) ?></h1>
        <p class="login-sub">Sistem Informasi Akademik &amp; Monitoring 7 Kebiasaan Anak Indonesia Hebat</p>

        <?php foreach (ambil_flash() as $f): ?>
            <div class="flash flash-<?= e($f['tipe']) ?>" role="alert"><?= e($f['pesan']) ?></div>
        <?php endforeach; ?>

        <form method="post" action="<?= e(url('auth/proses-login.php')) ?>" autocomplete="off">
            <?= csrf_field() ?>

            <div class="pilih-peran" role="radiogroup" aria-label="Masuk sebagai">
                <?php foreach (['guru' => 'Guru / Admin', 'siswa' => 'Siswa', 'orangtua' => 'Orang Tua'] as $kode => $label): ?>
                    <label class="chip">
                        <input type="radio" name="peran" value="<?= $kode ?>" <?= $peran_lama === $kode ? 'checked' : '' ?>>
                        <span><?= $label ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="form-grup">
                <label for="username" id="labelUsername">NIP</label>
                <input type="text" id="username" name="username" value="<?= e($user_lama) ?>" inputmode="numeric" required autofocus>
                <small id="petunjukUsername" class="petunjuk">Guru/Admin masuk dengan NIP.</small>
            </div>
            <div class="form-grup">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-blok">Masuk</button>
        </form>
    </section>
</main>
<script>
    // Ubah label kolom sesuai peran yang dipilih
    var info = {
        guru:     ['NIP', 'Guru/Admin masuk dengan NIP.'],
        siswa:    ['NISN', 'Siswa masuk dengan NISN.'],
        orangtua: ['NISN Anak', 'Orang tua masuk dengan NISN anak dan password orang tua.']
    };
    function ubahLabel() {
        var dipilih = document.querySelector('input[name="peran"]:checked').value;
        document.getElementById('labelUsername').textContent = info[dipilih][0];
        document.getElementById('petunjukUsername').textContent = info[dipilih][1];
    }
    document.querySelectorAll('input[name="peran"]').forEach(function (r) { r.addEventListener('change', ubahLabel); });
    ubahLabel();
</script>
</body>
</html>
