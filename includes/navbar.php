<?php
// includes/navbar.php
// Bar atas: tombol menu (mobile), nama aplikasi, nama pengguna, dan tombol keluar.
?>
<header class="navbar">
    <?php if (!$tanpa_sidebar): ?>
        <button type="button" class="navbar-menu" id="tombolMenu" aria-label="Buka menu">&#9776;</button>
    <?php endif; ?>
    <a class="navbar-brand" href="<?= e(halaman_beranda($pengguna['role'])) ?>">
        <img src="<?= e(url('assets/images/logo.svg')) ?>" alt="" width="32" height="32">
        <span><?= e(APP_SEKOLAH) ?></span>
    </a>
    <div class="navbar-user">
        <span class="navbar-nama"><?= e($pengguna['nama']) ?></span>
        <span class="pill pill-biru"><?= e(nama_role($pengguna['role'])) ?></span>
        <a class="btn btn-light btn-sm" href="<?= e(url('auth/logout.php')) ?>">Keluar</a>
    </div>
</header>
