<?php
// siswa/badge.php
// Total poin, progress menuju badge berikutnya, dan koleksi badge.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['siswa']);
$siswa = siswa_login();
if (!$siswa) redirect(url('auth/logout.php'));

$poin = total_poin((int) $siswa['id']);
$badge = daftar_badge($poin);
$berikut = $badge['berikut'];

$judul = 'Poin & Badge';
require __DIR__ . '/../includes/header.php';
?>
<section class="card teks-tengah">
    <div class="stat-label">Total Poinmu</div>
    <div class="nilai-besar"><i class="bi bi-star-fill"></i> <?= $poin ?></div>
    <?php if ($berikut): ?>
        <p>Tinggal <strong><?= (int) $berikut['minimal_poin'] - $poin ?> poin</strong> lagi untuk badge
            <strong><?= ikon_badge($berikut['ikon']) ?> <?= e($berikut['nama_badge']) ?></strong></p>
        <div class="progress biru" style="max-width:420px;margin:0 auto"><span style="width:<?= min(100, round($poin / $berikut['minimal_poin'] * 100)) ?>%"></span></div>
    <?php else: ?>
        <p>Hebat! Kamu sudah mengumpulkan semua badge. <i class="bi bi-stars"></i></p>
    <?php endif; ?>
</section>

<h2>Koleksi Badge (<?= $badge['jumlah_diraih'] ?>/<?= count($badge['semua']) ?>)</h2>
<div class="badge-grid">
    <?php foreach ($badge['semua'] as $b): ?>
        <div class="badge-item <?= $b['diraih'] ? '' : 'terkunci' ?>">
            <span class="ikon"><?= ikon_badge($b['ikon']) ?></span>
            <strong><?= e($b['nama_badge']) ?></strong>
            <div><small><?= (int) $b['minimal_poin'] ?> poin</small></div>
            <div><small><?= $b['diraih'] ? 'Sudah diraih <i class="bi bi-check-lg"></i>' : 'Belum diraih' ?></small></div>
        </div>
    <?php endforeach; ?>
</div>
<p class="mt"><small>Poin didapat dari jurnal kebiasaan yang disetujui guru: 1 kebiasaan selesai = 1 poin.</small></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
