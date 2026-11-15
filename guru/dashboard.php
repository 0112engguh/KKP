<?php
// guru/dashboard.php
// Dashboard Admin/Guru: ringkasan siswa, kelas, kehadiran, ujian aktif, nilai TKA, dan jurnal kebiasaan.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$sekarang = date('Y-m-d H:i:s');
$bulan_ini = ambil_periode(['jenis' => 'bulanan']);

$is_admin = user()['role'] === 'admin';
$kelas_tugas = kelas_guru();
$tugas_ids = array_map('intval', array_column($kelas_tugas, 'id'));
$in_kelas = $tugas_ids ? implode(',', $tugas_ids) : '0';

$jml_siswa   = $is_admin
    ? (int) skalar("SELECT COUNT(*) FROM siswa WHERE status = 'aktif'")
    : (int) skalar("SELECT COUNT(*) FROM siswa WHERE status = 'aktif' AND kelas_id IN ($in_kelas)");
$jml_kelas   = $is_admin ? (int) skalar('SELECT COUNT(*) FROM kelas') : count($kelas_tugas);
$ujian_aktif = $is_admin
    ? (int) skalar("SELECT COUNT(*) FROM ujian WHERE status = 'aktif' AND :now BETWEEN waktu_mulai AND waktu_selesai", ['now' => $sekarang])
    : (int) skalar("SELECT COUNT(*) FROM ujian WHERE status = 'aktif' AND kelas_id IN ($in_kelas) AND :now BETWEEN waktu_mulai AND waktu_selesai", ['now' => $sekarang]);
$jml_soal    = (int) skalar('SELECT COUNT(*) FROM bank_soal');
$rata_nilai  = $is_admin
    ? (float) skalar("SELECT COALESCE(AVG(nilai), 0) FROM hasil_ujian WHERE status = 'selesai'")
    : (float) skalar("SELECT COALESCE(AVG(h.nilai), 0) FROM hasil_ujian h JOIN siswa s ON s.id = h.siswa_id WHERE h.status = 'selesai' AND s.kelas_id IN ($in_kelas)");
$jurnal_tunggu = $is_admin
    ? (int) skalar("SELECT COUNT(*) FROM jurnal_kebiasaan WHERE status = 'menunggu'")
    : (int) skalar("SELECT COUNT(*) FROM jurnal_kebiasaan j JOIN siswa s ON s.id = j.siswa_id WHERE j.status = 'menunggu' AND s.kelas_id IN ($in_kelas)");

// Ringkasan per kelas
$where_k = $is_admin ? '' : " WHERE k.id IN ($in_kelas)";
$kelas = db()->query("SELECT k.id, k.nama_kelas, g.nama AS wali,
        (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id AND s.status = 'aktif') AS jml_siswa,
        (SELECT AVG(h.nilai) FROM hasil_ujian h JOIN siswa s ON s.id = h.siswa_id WHERE s.kelas_id = k.id AND h.status = 'selesai') AS rata_nilai,
        (SELECT COUNT(*) FROM jurnal_kebiasaan j JOIN siswa s ON s.id = j.siswa_id WHERE s.kelas_id = k.id AND j.status = 'menunggu') AS menunggu
    FROM kelas k LEFT JOIN guru g ON g.id = k.wali_kelas_id $where_k ORDER BY k.tingkat, k.nama_kelas")->fetchAll();

$total = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'total' => 0];
foreach ($kelas as &$k) {
    $k['hadir_persen'] = 0;
    $sum = ['hadir' => 0, 'total' => 0];
    foreach (rekap_absensi_kelas((int) $k['id'], $bulan_ini['dari'], $bulan_ini['sampai']) as $r) {
        foreach (['hadir', 'izin', 'sakit', 'alpa', 'total'] as $f) $total[$f] += $r[$f];
        $sum['hadir'] += $r['hadir'];
        $sum['total'] += $r['total'];
    }
    $k['hadir_persen'] = persen($sum['hadir'], $sum['total']);
}
unset($k);

$judul = 'Dashboard Guru';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid-stat">
    <div class="stat"><div class="stat-label">Jumlah Siswa</div><div class="stat-nilai"><?= (int) $jml_siswa ?></div><div class="stat-catatan">siswa aktif</div></div>
    <div class="stat hijau"><div class="stat-label">Jumlah Kelas</div><div class="stat-nilai"><?= (int) $jml_kelas ?></div></div>
    <div class="stat kuning"><div class="stat-label">Ujian Aktif</div><div class="stat-nilai"><?= (int) $ujian_aktif ?></div><div class="stat-catatan">sedang dibuka</div></div>
    <div class="stat"><div class="stat-label">Jumlah Soal</div><div class="stat-nilai"><?= (int) $jml_soal ?></div><div class="stat-catatan">di bank soal</div></div>
    <div class="stat hijau"><div class="stat-label">Rata-rata Nilai TKA</div><div class="stat-nilai"><?= angka($rata_nilai) ?></div></div>
    <div class="stat <?= $jurnal_tunggu ? 'merah' : 'hijau' ?>"><div class="stat-label">Jurnal Menunggu</div><div class="stat-nilai"><?= (int) $jurnal_tunggu ?></div>
        <div class="stat-catatan"><a href="<?= e(url('guru/jurnal.php?status=menunggu')) ?>">Verifikasi sekarang</a></div></div>
</div>

<div class="grid-2">
    <section class="card">
        <h2>Rekap Kehadiran <?= e($bulan_ini['label']) ?></h2>
        <?php if ($total['total'] > 0): ?>
            <div class="grafik" data-grafik="donat"
                 data-labels='["Hadir","Izin","Sakit","Alpa"]'
                 data-values='[<?= (int) $total['hadir'] ?>,<?= (int) $total['izin'] ?>,<?= (int) $total['sakit'] ?>,<?= (int) $total['alpa'] ?>]'
                 data-warna='["#16a34a","#2563eb","#eab308","#dc2626"]'></div>
            <p class="teks-tengah">Persentase kehadiran <?= $is_admin ? 'seluruh kelas' : 'kelas binaan' ?>: <strong><?= angka(persen($total['hadir'], $total['total'])) ?>%</strong></p>
        <?php else: ?>
            <div class="kosong">Belum ada data absensi bulan ini.</div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Aksi Cepat</h2>
        <div class="aksi-baris">
            <a class="btn btn-primary" href="<?= e(url('guru/absensi.php')) ?>"><i class="bi bi-pen-fill"></i> Input Absensi Hari Ini</a>
            <a class="btn btn-success" href="<?= e(url('guru/jurnal.php?status=menunggu')) ?>"><i class="bi bi-check-circle-fill"></i> Verifikasi Jurnal</a>
            <a class="btn btn-light" href="<?= e(url('guru/ujian-form.php')) ?>"><i class="bi bi-pencil-square"></i> Buat Ujian</a>
            <a class="btn btn-light" href="<?= e(url('guru/bank-soal-form.php')) ?>"><i class="bi bi-puzzle-fill"></i> Tambah Soal</a>
            <a class="btn btn-light" href="<?= e(url('laporan/index.php')) ?>"><i class="bi bi-printer-fill"></i> Laporan</a>
        </div>
    </section>
</div>

<section class="card">
    <h2>Ringkasan per Kelas (<?= e($bulan_ini['label']) ?>)</h2>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>Kelas</th><th>Wali Kelas</th><th class="angka">Siswa</th><th class="angka">Kehadiran</th><th class="angka">Rata-rata TKA</th><th class="angka">Jurnal Menunggu</th></tr></thead>
            <tbody>
            <?php foreach ($kelas as $k): ?>
                <tr>
                    <td><strong><?= e($k['nama_kelas']) ?></strong></td>
                    <td><?= e($k['wali'] ?? '-') ?></td>
                    <td class="angka"><?= (int) $k['jml_siswa'] ?></td>
                    <td class="angka"><?= pill(angka($k['hadir_persen']) . '%', $k['hadir_persen'] >= 90 ? 'hijau' : ($k['hadir_persen'] >= 75 ? 'kuning' : 'merah')) ?></td>
                    <td class="angka"><?= $k['rata_nilai'] !== null ? angka($k['rata_nilai']) : '-' ?></td>
                    <td class="angka"><?= (int) $k['menunggu'] ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$kelas): ?><tr><td colspan="6" class="kosong">Belum ada kelas.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
