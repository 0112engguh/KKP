<?php
// guru/absensi-rekap.php
// Rekap absensi per kelas: harian, bulanan, atau semester, lengkap dengan persentase dan detail per siswa.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$kelas_semua = kelas_guru();
if (!$kelas_semua) akhir_kelas();
$kelas_id = (int) ($_GET['kelas_id'] ?? $kelas_semua[0]['id']);
if (!punya_kelas($kelas_semua, $kelas_id)) {
    flash('danger', 'Kelas tidak termasuk penugasan Anda.');
    redirect(url('guru/absensi-rekap.php'));
}
$periode = ambil_periode($_GET);
$rekap = rekap_absensi_kelas($kelas_id, $periode['dari'], $periode['sampai']);
// Tabel rekap dipaginasi; grafik tetap memakai seluruh siswa
[$rekap_tampil, $halaman, $hal_akhir] = paginasi_array($rekap);

$jumlah = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'total' => 0];
foreach ($rekap as $r) foreach ($jumlah as $k => $_) $jumlah[$k] += $r[$k];

// Detail satu siswa (bila dipilih). Siswa harus berasal dari kelas yang dipilih.
$detail = null;
$siswa_id = (int) ($_GET['siswa'] ?? 0);
foreach ($rekap as $r) {
    if ((int) $r['id'] === $siswa_id) {
        $detail = ['siswa' => $r, 'baris' => detail_absensi_siswa($siswa_id, $periode['dari'], $periode['sampai'])];
    }
}

$judul = 'Rekap Absensi';
require __DIR__ . '/../includes/header.php';
$qs_dasar = ['kelas_id' => $kelas_id, 'jenis' => $periode['jenis'], 'tanggal' => $periode['tanggal'], 'bulan' => $periode['bulan'],
             'semester' => $periode['semester'], 'tahun' => $periode['tahun']];
?>
<section class="card">
    <form method="get" class="toolbar">
        <div class="form-grup"><label for="kelas_id">Kelas</label>
            <select name="kelas_id" id="kelas_id">
                <?php foreach ($kelas_semua as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= $kelas_id === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <?= form_periode($periode) ?>
        <button class="btn btn-primary" type="submit">Tampilkan</button>
        <a class="btn btn-light" href="<?= e(url('laporan/absensi.php?' . http_build_query($qs_dasar))) ?>"><i class="bi bi-printer-fill"></i> Versi Laporan</a>
    </form>
</section>

<div class="grid-2">
    <section class="card">
        <h2>Rekap <?= e($periode['label']) ?></h2>
        <?php if ($jumlah['total'] > 0): ?>
            <div class="grafik" data-grafik="donat" data-labels='["Hadir","Izin","Sakit","Alpa"]'
                 data-values='[<?= (int) $jumlah['hadir'] ?>,<?= (int) $jumlah['izin'] ?>,<?= (int) $jumlah['sakit'] ?>,<?= (int) $jumlah['alpa'] ?>]'
                 data-warna='["#16a34a","#2563eb","#eab308","#dc2626"]'></div>
            <p class="teks-tengah">Persentase kehadiran kelas: <strong><?= angka(persen($jumlah['hadir'], $jumlah['total'])) ?>%</strong></p>
        <?php else: ?>
            <div class="kosong">Belum ada data absensi pada periode ini.</div>
        <?php endif; ?>
    </section>
    <section class="card">
        <h2>Persentase Kehadiran per Siswa</h2>
        <div class="grafik" data-grafik="bar" data-maks="100" data-satuan="%"
             data-labels='<?= e(json_encode(array_map(fn($r) => explode(' ', $r['nama'])[0], $rekap))) ?>'
             data-values='<?= e(json_encode(array_column($rekap, 'persen'))) ?>'
             data-warna='["#2563eb"]'></div>
    </section>
</div>

<section class="card">
    <h2>Tabel Rekap</h2>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>No</th><th>NISN</th><th>Nama</th><th class="angka">H</th><th class="angka">I</th><th class="angka">S</th><th class="angka">A</th><th class="angka">Total Hari</th><th class="angka">Persentase</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($rekap_tampil as $i => $r): ?>
                <tr>
                    <td><?= ($halaman - 1) * PER_HALAMAN + $i + 1 ?></td><td><?= e($r['nisn']) ?></td><td><strong><?= e($r['nama']) ?></strong></td>
                    <td class="angka"><?= (int) $r['hadir'] ?></td><td class="angka"><?= (int) $r['izin'] ?></td><td class="angka"><?= (int) $r['sakit'] ?></td><td class="angka"><?= (int) $r['alpa'] ?></td>
                    <td class="angka"><?= (int) $r['total'] ?></td>
                    <td class="angka"><?= $r['total'] ? pill(angka($r['persen']) . '%', $r['persen'] >= 90 ? 'hijau' : ($r['persen'] >= 75 ? 'kuning' : 'merah')) : '-' ?></td>
                    <td><a class="btn btn-light btn-sm" href="?<?= e(http_build_query($qs_dasar + ['siswa' => $r['id']])) ?>#detail">Lihat</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rekap): ?><tr><td colspan="10" class="kosong">Kelas belum memiliki siswa aktif.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= paginasi_tautan($halaman, $hal_akhir, $qs_dasar) ?>
</section>

<?php if ($detail): ?>
<section class="card" id="detail">
    <h2>Detail Kehadiran: <?= e($detail['siswa']['nama']) ?></h2>
    <p>Periode <?= e($periode['label']) ?> &middot; Hadir <?= (int) $detail['siswa']['hadir'] ?>, Izin <?= (int) $detail['siswa']['izin'] ?>,
        Sakit <?= (int) $detail['siswa']['sakit'] ?>, Alpa <?= (int) $detail['siswa']['alpa'] ?> (<?= angka($detail['siswa']['persen']) ?>%)</p>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>Tanggal</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($detail['baris'] as $b): ?>
                <tr><td><?= e(hari_tgl_indo($b['tanggal'])) ?></td><td><?= pill_absen($b['status']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$detail['baris']): ?><tr><td colspan="2" class="kosong">Tidak ada data.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
