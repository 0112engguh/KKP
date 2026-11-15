<?php
// orangtua/absensi.php
// Laporan absensi bulanan (atau harian/semester) anak, dengan grafik dan tombol cetak.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
[$anak, $semua_anak] = pilih_anak();

$periode = ambil_periode($_GET + ['jenis' => 'bulanan']);
$judul = 'Absensi Anak';
$aksi_header = '<button type="button" class="btn btn-light" data-cetak><i class="bi bi-printer-fill"></i> Cetak</button>';
require __DIR__ . '/../includes/header.php';

if (!$anak) {
    echo '<div class="card kosong">Belum ada anak yang terhubung dengan akun Anda.</div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}
$rekap = rekap_absensi_siswa((int) $anak['id'], $periode['dari'], $periode['sampai']);
$detail = detail_absensi_siswa((int) $anak['id'], $periode['dari'], $periode['sampai']);
echo html_pilih_anak($semua_anak, $anak);
?>
<section class="card">
    <form method="get" class="toolbar">
        <input type="hidden" name="anak" value="<?= (int) $anak['id'] ?>">
        <?= form_periode($periode) ?>
        <button class="btn btn-primary" type="submit">Tampilkan</button>
    </form>
</section>

<div class="grid-2">
    <section class="card">
        <div class="kop-laporan"><h2><?= e(APP_SEKOLAH) ?></h2><strong>Laporan Absensi: <?= e($anak['nama']) ?> (<?= e($anak['nama_kelas']) ?>)</strong></div>
        <h2><?= e($anak['nama']) ?> &middot; <?= e($periode['label']) ?></h2>
        <?php if ($rekap['total'] > 0): ?>
            <div class="grafik" data-grafik="donat" data-labels='["Hadir","Izin","Sakit","Alpa"]'
                 data-values='[<?= (int) $rekap['hadir'] ?>,<?= (int) $rekap['izin'] ?>,<?= (int) $rekap['sakit'] ?>,<?= (int) $rekap['alpa'] ?>]'
                 data-warna='["#16a34a","#2563eb","#eab308","#dc2626"]'></div>
            <p class="teks-tengah">Persentase hadir: <strong><?= angka($rekap['persen']) ?>%</strong> (<?= (int) $rekap['hadir'] ?> dari <?= (int) $rekap['total'] ?> hari)</p>
        <?php else: ?>
            <div class="kosong">Belum ada data absensi pada periode ini.</div>
        <?php endif; ?>
    </section>
    <section class="card">
        <h2>Rincian Tanggal</h2>
        <div class="tabel-wrap" style="max-height:380px;overflow-y:auto">
            <table class="tabel">
                <thead><tr><th>Tanggal</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($detail as $d): ?>
                    <tr><td><?= e(hari_tgl_indo($d['tanggal'])) ?></td><td><?= pill_absen($d['status']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$detail): ?><tr><td colspan="2" class="kosong">Tidak ada data.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
