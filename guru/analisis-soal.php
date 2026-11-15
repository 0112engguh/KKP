<?php
// guru/analisis-soal.php
// Analisis butir soal: persentase siswa yang menjawab benar, tingkat kesukaran, dan sebaran jawaban A-D.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$kelas_semua = kelas_guru();
if (!$kelas_semua) akhir_kelas();

$ujian_id = (int) ($_GET['ujian_id'] ?? 0);
$where_kelas = '';
if (user()['role'] !== 'admin') {
    $ids = array_map('intval', array_column($kelas_semua, 'id'));
    $where_kelas = ' WHERE u.kelas_id IN (' . implode(',', $ids) . ')';
}
$daftar_ujian = db()->query("SELECT u.id, u.nama_ujian, k.nama_kelas FROM ujian u JOIN kelas k ON k.id = u.kelas_id $where_kelas ORDER BY u.waktu_mulai DESC")->fetchAll();
if ($ujian_id === 0 && $daftar_ujian) $ujian_id = (int) $daftar_ujian[0]['id'];

finalisasi_kadaluarsa($ujian_id ?: null);

$ujian = null;
$analisis = [];
$peserta = 0;
if ($ujian_id > 0) {
    $st = db()->prepare('SELECT u.*, m.nama_mapel, k.nama_kelas FROM ujian u JOIN mata_pelajaran m ON m.id = u.mapel_id JOIN kelas k ON k.id = u.kelas_id WHERE u.id = :id');
    $st->execute(['id' => $ujian_id]);
    $ujian = $st->fetch();
    if ($ujian && !punya_kelas($kelas_semua, (int) $ujian['kelas_id'])) {
        $ujian = null;
    }
}
if ($ujian) {
    $peserta = (int) skalar("SELECT COUNT(*) FROM hasil_ujian WHERE ujian_id = :u AND status = 'selesai'", ['u' => $ujian_id]);

    $st = db()->prepare('SELECT b.id, b.pertanyaan, b.kunci_jawaban, us.urutan FROM ujian_soal us JOIN bank_soal b ON b.id = us.soal_id WHERE us.ujian_id = :u ORDER BY us.urutan');
    $st->execute(['u' => $ujian_id]);
    $soal = $st->fetchAll();

    // Sebaran jawaban per soal dari siswa yang sudah selesai
    $st = db()->prepare("SELECT j.soal_id, COALESCE(j.jawaban, '-') AS jawaban, COUNT(*) AS jumlah
                         FROM jawaban_siswa j JOIN hasil_ujian h ON h.id = j.hasil_id
                         WHERE h.ujian_id = :u AND h.status = 'selesai' GROUP BY j.soal_id, jawaban");
    $st->execute(['u' => $ujian_id]);
    $sebaran = [];
    foreach ($st->fetchAll() as $r) $sebaran[$r['soal_id']][$r['jawaban']] = (int) $r['jumlah'];

    foreach ($soal as $s) {
        $d = $sebaran[$s['id']] ?? [];
        $benar = $d[$s['kunci_jawaban']] ?? 0;
        $persen_benar = persen($benar, $peserta);
        $analisis[] = $s + ['sebaran' => $d, 'benar' => $benar, 'persen' => $persen_benar,
            'kesukaran' => $persen_benar >= 70 ? 'Mudah' : ($persen_benar >= 40 ? 'Sedang' : 'Sulit')];
    }
}

$judul = 'Analisis Butir Soal';
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <form method="get" class="toolbar">
        <div class="form-grup"><label for="ujian_id">Ujian</label>
            <select name="ujian_id" id="ujian_id" onchange="this.form.submit()">
                <?php foreach ($daftar_ujian as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= $ujian_id === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['nama_ujian']) ?> (<?= e($u['nama_kelas']) ?>)</option>
                <?php endforeach; ?>
            </select></div>
        <noscript><button class="btn btn-primary" type="submit">Tampilkan</button></noscript>
    </form>
    <?php if ($ujian): ?><p>Peserta yang sudah selesai: <strong><?= (int) $peserta ?> siswa</strong>.
        Tingkat kesukaran: Mudah (benar &ge; 70%), Sedang (40-69%), Sulit (&lt; 40%).</p><?php endif; ?>
</section>

<?php if ($ujian && $peserta > 0): ?>
<section class="card">
    <h2>Persentase Siswa yang Menjawab Benar</h2>
    <div class="grafik" data-grafik="bar" data-maks="100" data-satuan="%"
         data-labels='<?= e(json_encode(array_map(fn($a) => 'No ' . $a['urutan'], $analisis))) ?>'
         data-values='<?= e(json_encode(array_column($analisis, 'persen'))) ?>' data-warna='["#2563eb"]'></div>
</section>
<section class="card">
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>No</th><th>Pertanyaan</th><th class="angka">Kunci</th><th class="angka">A</th><th class="angka">B</th><th class="angka">C</th><th class="angka">D</th><th class="angka">Kosong</th><th class="angka">% Benar</th><th>Kesukaran</th></tr></thead>
            <tbody>
            <?php foreach ($analisis as $a): ?>
                <tr>
                    <td><?= (int) $a['urutan'] ?></td>
                    <td><?= e(mb_strimwidth($a['pertanyaan'], 0, 70, '...')) ?></td>
                    <td class="angka"><strong><?= e($a['kunci_jawaban']) ?></strong></td>
                    <?php foreach (['A', 'B', 'C', 'D'] as $o): ?>
                        <td class="angka"><?= $o === $a['kunci_jawaban'] ? '<strong>' . ($a['sebaran'][$o] ?? 0) . '</strong>' : ($a['sebaran'][$o] ?? 0) ?></td>
                    <?php endforeach; ?>
                    <td class="angka"><?= $a['sebaran']['-'] ?? 0 ?></td>
                    <td class="angka"><?= angka($a['persen']) ?>%</td>
                    <td><?= pill($a['kesukaran'], ['Mudah' => 'hijau', 'Sedang' => 'kuning', 'Sulit' => 'merah'][$a['kesukaran']]) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php elseif ($ujian): ?>
    <div class="card kosong">Belum ada siswa yang menyelesaikan ujian ini.</div>
<?php else: ?>
    <div class="card kosong">Belum ada ujian.</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
