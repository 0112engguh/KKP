<?php
// orangtua/kebiasaan.php
// Grafik, poin, badge, dan riwayat jurnal 7 kebiasaan anak beserta catatan guru.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
[$anak, $semua_anak] = pilih_anak();

$judul = 'Kebiasaan Anak';
require __DIR__ . '/../includes/header.php';

if (!$anak) {
    echo '<div class="card kosong">Belum ada anak yang terhubung dengan akun Anda.</div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}
$sid = (int) $anak['id'];
$poin = total_poin($sid);
$badge = daftar_badge($poin);
$harian = kebiasaan_per_hari($sid, 14);
$riwayat = riwayat_jurnal($sid, 20);
echo html_pilih_anak($semua_anak, $anak);
?>
<div class="grid-2">
    <section class="card teks-tengah">
        <div class="stat-label">Total Poin <?= e($anak['nama']) ?></div>
        <div class="nilai-besar"><i class="bi bi-star-fill"></i> <?= (int) $poin ?></div>
        <p>
            <?php foreach ($badge['semua'] as $b): if ($b['diraih']): ?>
                <span class="pill pill-kuning" title="<?= e($b['deskripsi']) ?>"><?= ikon_badge($b['ikon']) ?> <?= e($b['nama_badge']) ?></span>
            <?php endif; endforeach; ?>
            <?php if (!$badge['jumlah_diraih']): ?><small>Belum ada badge.</small><?php endif; ?>
        </p>
        <?php if ($badge['berikut']): ?><small>Menuju <?= e($badge['berikut']['nama_badge']) ?>: <?= (int) $poin ?>/<?= (int) $badge['berikut']['minimal_poin'] ?> poin</small><?php endif; ?>
    </section>
    <section class="card">
        <h2>Grafik 14 Hari Terakhir</h2>
        <div class="grafik" data-grafik="bar" data-maks="7" data-warna='["#16a34a"]'
             data-labels='<?= e(json_encode(array_map(fn($r) => date('d/m', strtotime($r['tanggal'])), $harian))) ?>'
             data-values='<?= e(json_encode(array_map(fn($r) => (int) $r['jumlah'], $harian))) ?>'></div>
    </section>
</div>

<section class="card">
    <h2>Riwayat Jurnal</h2>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>Tanggal</th><th class="angka">Checklist</th><th>Status</th><th>Apresiasi / Catatan Guru</th></tr></thead>
            <tbody>
            <?php foreach ($riwayat as $r): ?>
                <tr><td><?= e(hari_tgl_indo($r['tanggal'])) ?></td><td class="angka"><?= (int) $r['jumlah'] ?>/7</td><td><?= pill_status_jurnal($r['status']) ?></td>
                    <td><?php if ($r['status'] !== 'menunggu'): ?><?= $r['apresiasi'] ? '<i class="bi bi-star-fill"></i> ' . e($r['apresiasi']) : '' ?><?= $r['catatan'] ? '<br><small><i class="bi bi-chat-fill"></i> ' . e($r['catatan']) . '</small>' : '' ?><?php endif; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$riwayat): ?><tr><td colspan="4" class="kosong">Belum ada jurnal.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
