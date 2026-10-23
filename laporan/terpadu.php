<?php
// laporan/terpadu.php
// Laporan terpadu per siswa: kehadiran + nilai TKA + pencapaian kebiasaan (untuk guru, wali kelas, kepala sekolah, orang tua).

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

$periode = ambil_periode($_GET + ['jenis' => 'semester']);
finalisasi_kadaluarsa();

$absen = [];
foreach (rekap_absensi_kelas($kelas_id, $periode['dari'], $periode['sampai']) as $r) $absen[$r['id']] = $r;

$st = db()->prepare("SELECT h.siswa_id, AVG(h.nilai) AS rata, COUNT(*) AS jumlah FROM hasil_ujian h JOIN siswa s ON s.id = h.siswa_id
                     WHERE s.kelas_id = :k AND h.status = 'selesai' AND DATE(h.waktu_selesai) BETWEEN :dari AND :sampai GROUP BY h.siswa_id");
$st->execute(['k' => $kelas_id, 'dari' => $periode['dari'], 'sampai' => $periode['sampai']]);
$tka = [];
foreach ($st->fetchAll() as $r) $tka[$r['siswa_id']] = $r;

$baris = [];
foreach ($absen as $sid => $a) {
    $p = progress_kebiasaan((int) $sid, $kelas_id, $periode['dari'], $periode['sampai']);
    $badge = daftar_badge(total_poin((int) $sid));
    $diraih = array_values(array_filter($badge['semua'], fn($b) => $b['diraih']));
    $baris[] = [
        'nisn' => $a['nisn'], 'nama' => $a['nama'], 'hadir' => $a['persen'],
        'nilai' => isset($tka[$sid]) ? round((float) $tka[$sid]['rata'], 1) : null, 'ujian' => (int) ($tka[$sid]['jumlah'] ?? 0),
        'poin' => $p['poin'], 'progress' => $p['progress'],
        'badge' => $diraih ? end($diraih)['nama_badge'] : '-',
    ];
}

if (($_GET['export'] ?? '') === 'csv') {
    $out = [];
    foreach ($baris as $i => $b) $out[] = [$i + 1, $b['nisn'], $b['nama'], $periode['label'], $b['hadir'], $b['nilai'] ?? '', $b['ujian'], $b['poin'], $b['progress'], $b['badge']];
    unduh_csv('laporan-terpadu-' . $nama_kelas . '.csv', ['No', 'NISN', 'Nama', 'Periode', 'Kehadiran (%)', 'Rata-rata TKA', 'Jumlah Ujian', 'Poin Kebiasaan', 'Progress Kebiasaan (%)', 'Badge'], $out);
}

$judul = 'Laporan Terpadu';
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
    <div class="kop-laporan"><h2><?= e(APP_SEKOLAH) ?></h2><strong>Laporan Terpadu Akademik dan Karakter Siswa</strong></div>
    <p>Kelas <strong><?= e($nama_kelas) ?></strong> &middot; Periode <strong><?= e($periode['label']) ?></strong></p>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>No</th><th>Nama</th><th class="angka">Kehadiran</th><th class="angka">Rata-rata TKA</th><th class="angka">Poin Kebiasaan</th><th class="angka">Progress Kebiasaan</th><th>Badge</th></tr></thead>
            <tbody>
            <?php foreach ($baris as $i => $b): ?>
                <tr><td><?= $i + 1 ?></td><td><strong><?= e($b['nama']) ?></strong></td>
                    <td class="angka"><?= angka($b['hadir']) ?>%</td>
                    <td class="angka"><?= $b['nilai'] !== null ? angka($b['nilai']) . ' <small>(' . $b['ujian'] . ' ujian)</small>' : '-' ?></td>
                    <td class="angka"><?= $b['poin'] ?></td><td class="angka"><?= angka($b['progress']) ?>%</td><td><?= e($b['badge']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$baris): ?><tr><td colspan="7" class="kosong">Tidak ada data.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <p><small>Dicetak pada <?= e(waktu_indo(date('Y-m-d H:i:s'))) ?>.</small></p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
