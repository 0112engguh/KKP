<?php
// laporan/tka.php
// Laporan hasil TKA per kelas dan periode (berdasarkan waktu selesai ujian).

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$kelas_semua = kelas_guru();
if (!$kelas_semua) akhir_kelas();
$kelas_id = (int) ($_GET['kelas_id'] ?? $kelas_semua[0]['id']);
$nama_kelas = $kelas_semua[0]['nama_kelas'];
$ditemukan = false;
foreach ($kelas_semua as $k) if ((int) $k['id'] === $kelas_id) { $nama_kelas = $k['nama_kelas']; $ditemukan = true; }
if (!$ditemukan) $kelas_id = (int) $kelas_semua[0]['id'];

$periode = ambil_periode($_GET + ['jenis' => 'semester']);
finalisasi_kadaluarsa();

$st = db()->prepare("SELECT s.nisn, s.nama, u.nama_ujian, m.nama_mapel, h.nilai, h.jumlah_benar, h.jumlah_salah, h.waktu_selesai
                     FROM hasil_ujian h JOIN siswa s ON s.id = h.siswa_id JOIN ujian u ON u.id = h.ujian_id
                     JOIN mata_pelajaran m ON m.id = u.mapel_id
                     WHERE s.kelas_id = :k AND h.status = 'selesai' AND DATE(h.waktu_selesai) BETWEEN :dari AND :sampai
                     ORDER BY u.nama_ujian, s.nama");
$st->execute(['k' => $kelas_id, 'dari' => $periode['dari'], 'sampai' => $periode['sampai']]);
$baris = $st->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    $out = [];
    foreach ($baris as $i => $b) $out[] = [$i + 1, $b['nisn'], $b['nama'], $b['nama_ujian'], $b['nama_mapel'], (float) $b['nilai'], (int) $b['jumlah_benar'], (int) $b['jumlah_salah']];
    unduh_csv('laporan-tka-' . $nama_kelas . '.csv', ['No', 'NISN', 'Nama Siswa', 'Ujian', 'Mata Pelajaran', 'Nilai', 'Benar', 'Salah'], $out);
}

$judul = 'Laporan TKA';
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
    <div class="kop-laporan"><h2><?= e(APP_SEKOLAH) ?></h2><strong>Laporan Hasil TKA</strong></div>
    <p>Kelas <strong><?= e($nama_kelas) ?></strong> &middot; Periode <strong><?= e($periode['label']) ?></strong></p>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>No</th><th>Nama Siswa</th><th>Ujian</th><th>Mapel</th><th class="angka">Nilai</th><th class="angka">Benar</th><th class="angka">Salah</th></tr></thead>
            <tbody>
            <?php foreach ($baris as $i => $b): ?>
                <tr><td><?= $i + 1 ?></td><td><?= e($b['nama']) ?></td><td><?= e($b['nama_ujian']) ?></td><td><?= e($b['nama_mapel']) ?></td>
                    <td class="angka"><strong><?= angka($b['nilai']) ?></strong></td><td class="angka"><?= (int) $b['jumlah_benar'] ?></td><td class="angka"><?= (int) $b['jumlah_salah'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$baris): ?><tr><td colspan="7" class="kosong">Tidak ada hasil ujian pada periode ini.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($baris): ?><p>Rata-rata nilai: <strong><?= angka(array_sum(array_column($baris, 'nilai')) / count($baris)) ?></strong></p><?php endif; ?>
    <p><small>Nilai = Jumlah Benar / Jumlah Soal x 100.</small></p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
