<?php
// siswa/kebiasaan-history.php
// Riwayat jurnal 7 kebiasaan, status verifikasi, catatan guru, dan grafik perkembangan.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['siswa']);
$siswa = siswa_login();
if (!$siswa) redirect(url('auth/logout.php'));

$riwayat = riwayat_jurnal((int) $siswa['id'], 30);
$harian = kebiasaan_per_hari((int) $siswa['id'], 14);

$judul = 'History 7 Kebiasaan';
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <h2>Perkembangan 14 Hari Terakhir</h2>
    <div class="grafik" data-grafik="bar" data-maks="7" data-warna='["#16a34a"]'
         data-labels='<?= e(json_encode(array_map(fn($r) => date('d/m', strtotime($r['tanggal'])), $harian))) ?>'
         data-values='<?= e(json_encode(array_map(fn($r) => (int) $r['jumlah'], $harian))) ?>'></div>
</section>
<section class="card">
    <h2>Riwayat Jurnal</h2>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>Tanggal</th><th class="angka">Checklist</th><th>Status</th><th>Apresiasi / Catatan Guru</th></tr></thead>
            <tbody>
            <?php foreach ($riwayat as $r): ?>
                <tr>
                    <td><?= e(hari_tgl_indo($r['tanggal'])) ?></td>
                    <td class="angka"><?= (int) $r['jumlah'] ?>/7</td>
                    <td><?= pill_status_jurnal($r['status']) ?></td>
                    <td><?php if ($r['status'] !== 'menunggu'): ?>
                        <?= $r['apresiasi'] ? '<i class="bi bi-star-fill"></i> ' . e($r['apresiasi']) : '' ?><?= $r['catatan'] ? '<br><small><i class="bi bi-chat-fill"></i> ' . e($r['catatan']) . '</small>' : '' ?>
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$riwayat): ?><tr><td colspan="4" class="kosong">Belum ada jurnal.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
