<?php
// laporan/absensi.php
// Laporan absensi per kelas dan periode. Export Excel (CSV) dan cetak/PDF lewat browser.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$kelas_semua = kelas_guru();
if (!$kelas_semua) akhir_kelas();
$kelas_id = (int) ($_GET['kelas_id'] ?? $kelas_semua[0]['id']);
$nama_kelas = '';
foreach ($kelas_semua as $k) if ((int) $k['id'] === $kelas_id) $nama_kelas = $k['nama_kelas'];
if ($nama_kelas === '') { $kelas_id = (int) $kelas_semua[0]['id']; $nama_kelas = $kelas_semua[0]['nama_kelas']; }

$periode = ambil_periode($_GET);
$rekap = rekap_absensi_kelas($kelas_id, $periode['dari'], $periode['sampai']);

if (($_GET['export'] ?? '') === 'csv') {
    $baris = [];
    foreach ($rekap as $i => $r) $baris[] = [$i + 1, $r['nisn'], $r['nama'], $periode['label'], $r['hadir'], $r['izin'], $r['sakit'], $r['alpa'], $r['total'], $r['persen']];
    unduh_csv('laporan-absensi-' . $nama_kelas . '.csv', ['No', 'NISN', 'Nama', 'Periode', 'Hadir', 'Izin', 'Sakit', 'Alpa', 'Total Hari', 'Persentase Hadir (%)'], $baris);
}

$judul = 'Laporan Absensi';
$aksi_header = '<button type="button" class="btn btn-light" data-cetak><i class="bi bi-printer-fill"></i> Cetak / PDF</button>';
require __DIR__ . '/../includes/header.php';
$qs = http_build_query($_GET + ['kelas_id' => $kelas_id]);
?>
<section class="card tidak-dicetak">
    <form method="get" class="toolbar">
        <div class="form-grup"><label for="kelas_id">Kelas</label>
            <select name="kelas_id" id="kelas_id">
                <?php foreach ($kelas_semua as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= $kelas_id === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <?= form_periode($periode) ?>
        <button class="btn btn-primary" type="submit">Tampilkan</button>
        <a class="btn btn-success" href="?<?= e(http_build_query(['export' => 'csv', 'kelas_id' => $kelas_id] + $periode)) ?>"><i class="bi bi-download"></i> Export Excel (CSV)</a>
    </form>
</section>

<section class="card">
    <div class="kop-laporan"><h2><?= e(APP_SEKOLAH) ?></h2><strong>Laporan Absensi Siswa</strong></div>
    <p>Kelas <strong><?= e($nama_kelas) ?></strong> &middot; Periode <strong><?= e($periode['label']) ?></strong></p>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>No</th><th>NISN</th><th>Nama</th><th class="angka">Hadir</th><th class="angka">Izin</th><th class="angka">Sakit</th><th class="angka">Alpa</th><th class="angka">Total Hari</th><th class="angka">% Hadir</th></tr></thead>
            <tbody>
            <?php foreach ($rekap as $i => $r): ?>
                <tr><td><?= $i + 1 ?></td><td><?= e($r['nisn']) ?></td><td><?= e($r['nama']) ?></td>
                    <td class="angka"><?= (int) $r['hadir'] ?></td><td class="angka"><?= (int) $r['izin'] ?></td><td class="angka"><?= (int) $r['sakit'] ?></td><td class="angka"><?= (int) $r['alpa'] ?></td>
                    <td class="angka"><?= (int) $r['total'] ?></td><td class="angka"><?= angka($r['persen']) ?>%</td></tr>
            <?php endforeach; ?>
            <?php if (!$rekap): ?><tr><td colspan="9" class="kosong">Tidak ada data.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <p><small>Dicetak pada <?= e(waktu_indo(date('Y-m-d H:i:s'))) ?>. Persentase = Hadir / Total Hari x 100.</small></p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
