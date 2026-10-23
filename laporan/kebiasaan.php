<?php
// laporan/kebiasaan.php
// Laporan 7 kebiasaan per kelas dan periode: total checklist, poin, progress, dan status verifikasi.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$kelas_semua = semua_kelas();
if (!$kelas_semua) redirect(url('guru/kelas.php'));
$kelas_id = (int) ($_GET['kelas_id'] ?? $kelas_semua[0]['id']);
$nama_kelas = $kelas_semua[0]['nama_kelas'];
$ditemukan = false;
foreach ($kelas_semua as $k) if ((int) $k['id'] === $kelas_id) { $nama_kelas = $k['nama_kelas']; $ditemukan = true; }
if (!$ditemukan) $kelas_id = (int) $kelas_semua[0]['id'];

$periode = ambil_periode($_GET + ['jenis' => 'bulanan']);

$st = db()->prepare("SELECT id, nisn, nama FROM siswa WHERE kelas_id = :k AND status = 'aktif' ORDER BY nama");
$st->execute(['k' => $kelas_id]);
$siswa = $st->fetchAll();

// Hitung status verifikasi per siswa pada periode
$st = db()->prepare("SELECT j.siswa_id, j.status, COUNT(*) AS jumlah FROM jurnal_kebiasaan j JOIN siswa s ON s.id = j.siswa_id
                     WHERE s.kelas_id = :k AND j.tanggal BETWEEN :dari AND :sampai GROUP BY j.siswa_id, j.status");
$st->execute(['k' => $kelas_id, 'dari' => $periode['dari'], 'sampai' => $periode['sampai']]);
$status_jurnal = [];
foreach ($st->fetchAll() as $r) $status_jurnal[$r['siswa_id']][$r['status']] = (int) $r['jumlah'];

$baris = [];
foreach ($siswa as $s) {
    $p = progress_kebiasaan((int) $s['id'], $kelas_id, $periode['dari'], $periode['sampai']);
    $sj = $status_jurnal[$s['id']] ?? [];
    $baris[] = $s + $p + ['disetujui_j' => $sj['disetujui'] ?? 0, 'menunggu_j' => $sj['menunggu'] ?? 0, 'ditolak_j' => $sj['ditolak'] ?? 0];
}

if (($_GET['export'] ?? '') === 'csv') {
    $out = [];
    foreach ($baris as $i => $b) $out[] = [$i + 1, $b['nisn'], $b['nama'], $periode['label'], $b['checklist'], $b['poin'], $b['progress'], $b['disetujui_j'], $b['menunggu_j'], $b['ditolak_j']];
    unduh_csv('laporan-kebiasaan-' . $nama_kelas . '.csv', ['No', 'NISN', 'Nama', 'Periode', 'Total Checklist', 'Total Poin', 'Progress (%)', 'Jurnal Disetujui', 'Jurnal Menunggu', 'Jurnal Ditolak'], $out);
}

$judul = 'Laporan Kebiasaan';
$aksi_header = '<button type="button" class="btn btn-light" data-cetak><i class="bi bi-printer-fill"></i> Cetak / PDF</button>';
require __DIR__ . '/../includes/header.php';
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
    <div class="kop-laporan"><h2><?= e(APP_SEKOLAH) ?></h2><strong>Laporan Gerakan 7 Kebiasaan Anak Indonesia Hebat</strong></div>
    <p>Kelas <strong><?= e($nama_kelas) ?></strong> &middot; Periode <strong><?= e($periode['label']) ?></strong></p>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>No</th><th>Nama</th><th class="angka">Total Checklist</th><th class="angka">Total Poin</th><th class="angka">Progress</th><th class="angka">Disetujui</th><th class="angka">Menunggu</th><th class="angka">Ditolak</th></tr></thead>
            <tbody>
            <?php foreach ($baris as $i => $b): ?>
                <tr><td><?= $i + 1 ?></td><td><strong><?= e($b['nama']) ?></strong></td>
                    <td class="angka"><?= $b['checklist'] ?></td><td class="angka"><?= $b['poin'] ?></td>
                    <td class="angka"><div class="progress"><span style="width:<?= (float) $b['progress'] ?>%"></span></div><small><?= angka($b['progress']) ?>%</small></td>
                    <td class="angka"><?= $b['disetujui_j'] ?></td><td class="angka"><?= $b['menunggu_j'] ?></td><td class="angka"><?= $b['ditolak_j'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$baris): ?><tr><td colspan="8" class="kosong">Tidak ada data.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <p><small>Progress = kebiasaan disetujui / (7 x jumlah hari pengisian jurnal kelas) x 100. Poin dihitung dari jurnal yang disetujui guru.</small></p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
