<?php
// laporan/index.php
// Menu laporan untuk Admin/Guru.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$judul = 'Laporan';
require __DIR__ . '/../includes/header.php';
$menu = [
    ['Laporan Absensi', 'Rekap hadir, izin, sakit, alpa, dan persentase kehadiran tiap siswa.', 'laporan/absensi.php', '<i class="bi bi-calendar3"></i>'],
    ['Laporan TKA', 'Nilai, jumlah benar, dan jumlah salah setiap siswa pada tiap ujian.', 'laporan/tka.php', '<i class="bi bi-bar-chart-fill"></i>'],
    ['Laporan Kebiasaan', 'Total checklist, poin, progress, dan status verifikasi 7 kebiasaan.', 'laporan/kebiasaan.php', '<i class="bi bi-star-fill"></i>'],
    ['Laporan Terpadu', 'Gabungan absensi, perkembangan TKA, dan pencapaian kebiasaan untuk wali kelas, kepala sekolah, dan orang tua.', 'laporan/terpadu.php', '<i class="bi bi-file-earmark-text-fill"></i>'],
];
?>
<div class="grid-2">
    <?php foreach ($menu as $m): ?>
        <section class="card">
            <h2><?= $m[3] ?> <?= e($m[0]) ?></h2>
            <p><?= e($m[1]) ?></p>
            <a class="btn btn-primary" href="<?= e(url($m[2])) ?>">Buka</a>
        </section>
    <?php endforeach; ?>
</div>
<p><small>Setiap laporan dapat diekspor ke Excel (CSV) dan dicetak / disimpan sebagai PDF melalui tombol Cetak di browser.</small></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
