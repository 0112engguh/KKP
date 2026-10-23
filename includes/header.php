<?php
// includes/header.php
// Pembuka halaman: <head>, navbar, sidebar, dan judul halaman.
// Variabel yang bisa diisi sebelum require:
//   $judul          -> judul halaman
//   $aksi_header    -> HTML tombol di sebelah kanan judul (opsional)
//   $tanpa_sidebar  -> true untuk menyembunyikan sidebar (dipakai saat ujian)
//   $sembunyikan_judul -> true untuk tidak menampilkan judul halaman (dipakai saat ujian)
//   $css_tambahan   -> array nama file CSS tambahan (opsional)

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/fungsi.php';

$pengguna = wajib_login();
$judul = $judul ?? 'Dashboard';
$aksi_header = $aksi_header ?? '';
$tanpa_sidebar = $tanpa_sidebar ?? false;
$sembunyikan_judul = $sembunyikan_judul ?? false;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> - <?= e(APP_SEKOLAH) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/icons/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="<?= $tanpa_sidebar ? 'tanpa-sidebar' : '' ?>">
<?php require __DIR__ . '/navbar.php'; ?>
<div class="layout">
    <?php if (!$tanpa_sidebar) require __DIR__ . '/sidebar.php'; ?>
    <main class="konten" id="konten">
        <?php foreach (ambil_flash() as $f): ?>
            <div class="flash flash-<?= e($f['tipe']) ?>" role="alert"><?= e($f['pesan']) ?></div>
        <?php endforeach; ?>
        <?php if (!$sembunyikan_judul): ?>
        <div class="page-head">
            <h1><?= e($judul) ?></h1>
            <?php if ($aksi_header): ?><div class="page-aksi"><?= $aksi_header ?></div><?php endif; ?>
        </div>
        <?php endif; ?>
