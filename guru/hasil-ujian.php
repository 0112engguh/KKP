<?php
// guru/hasil-ujian.php
// Rekap nilai ujian: daftar ujian beserta statistik, dan nilai tiap siswa per ujian.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$kelas_semua = kelas_guru();
if (!$kelas_semua) akhir_kelas();

$ujian_id = (int) ($_GET['ujian_id'] ?? 0);
finalisasi_kadaluarsa($ujian_id > 0 ? $ujian_id : null); // proses ujian yang waktunya habis

$ujian = null;
$baris = [];
if ($ujian_id > 0) {
    $st = db()->prepare('SELECT u.*, m.nama_mapel, k.nama_kelas FROM ujian u JOIN mata_pelajaran m ON m.id = u.mapel_id
                         JOIN kelas k ON k.id = u.kelas_id WHERE u.id = :id');
    $st->execute(['id' => $ujian_id]);
    $ujian = $st->fetch();
    if (!$ujian || !punya_kelas($kelas_semua, (int) $ujian['kelas_id'])) {
        flash('danger', 'Data tidak ditemukan atau di luar penugasan kelas Anda.');
        redirect(url('guru/hasil-ujian.php'));
    }
    $st = db()->prepare("SELECT s.nisn, s.nama, h.status, h.nilai, h.jumlah_benar, h.jumlah_salah, h.waktu_selesai
                         FROM siswa s LEFT JOIN hasil_ujian h ON h.siswa_id = s.id AND h.ujian_id = :u
                         WHERE s.kelas_id = :k AND s.status = 'aktif' ORDER BY s.nama");
    [$baris, $halaman, $hal_akhir, $total] = paginasi($st->queryString,
        'SELECT COUNT(*) FROM siswa s LEFT JOIN hasil_ujian h ON h.siswa_id = s.id AND h.ujian_id = :u
                         WHERE s.kelas_id = :k AND s.status = \'aktif\'',
        ['u' => $ujian_id, 'k' => $ujian['kelas_id']]);
    // Statistik dari seluruh peserta (tidak hanya halaman aktif)
    $st = db()->prepare("SELECT COALESCE(AVG(nilai), 0) AS rata, COALESCE(MAX(nilai), 0) AS ttinggi,
                                COALESCE(MIN(nilai), 0) AS trndh, COUNT(*) AS jumlah
                         FROM hasil_ujian WHERE ujian_id = :u AND status = 'selesai'");
    $st->execute(['u' => $ujian_id]);
    $stat = $st->fetch();
} else {
    $where_kelas = '';
    if (user()['role'] !== 'admin') {
        $ids = array_map('intval', array_column($kelas_semua, 'id'));
        $where_kelas = ' WHERE u.kelas_id IN (' . implode(',', $ids) . ')';
    }
    [$daftar, $halaman, $hal_akhir, $total] = paginasi(
        "SELECT u.id, u.nama_ujian, m.nama_mapel, k.nama_kelas,
            (SELECT COUNT(*) FROM hasil_ujian h WHERE h.ujian_id = u.id AND h.status = 'selesai') AS selesai,
            (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = u.kelas_id AND s.status = 'aktif') AS jml_siswa,
            (SELECT AVG(h.nilai) FROM hasil_ujian h WHERE h.ujian_id = u.id AND h.status = 'selesai') AS rata,
            (SELECT MAX(h.nilai) FROM hasil_ujian h WHERE h.ujian_id = u.id AND h.status = 'selesai') AS tertinggi,
            (SELECT MIN(h.nilai) FROM hasil_ujian h WHERE h.ujian_id = u.id AND h.status = 'selesai') AS terendah
        FROM ujian u JOIN mata_pelajaran m ON m.id = u.mapel_id JOIN kelas k ON k.id = u.kelas_id
        $where_kelas
        ORDER BY u.waktu_mulai DESC",
        'SELECT COUNT(*) FROM ujian u' . ($where_kelas ?: ''));
}

$judul = $ujian ? 'Hasil: ' . $ujian['nama_ujian'] : 'Hasil Ujian';
$aksi_header = $ujian ? '<a class="btn btn-light" href="' . e(url('guru/hasil-ujian.php')) . '">&larr; Semua Ujian</a>'
    . '<a class="btn btn-light" href="' . e(url('guru/analisis-soal.php?ujian_id=' . $ujian_id)) . '"><i class="bi bi-search"></i> Analisis Soal</a>' : '';
require __DIR__ . '/../includes/header.php';
?>
<?php if (!$ujian): ?>
<section class="card">
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>Ujian</th><th>Mapel</th><th>Kelas</th><th class="angka">Selesai</th><th class="angka">Rata-rata</th><th class="angka">Tertinggi</th><th class="angka">Terendah</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $u): ?>
                <tr>
                    <td><strong><?= e($u['nama_ujian']) ?></strong></td><td><?= e($u['nama_mapel']) ?></td><td><?= e($u['nama_kelas']) ?></td>
                    <td class="angka"><?= (int) $u['selesai'] ?>/<?= (int) $u['jml_siswa'] ?></td>
                    <td class="angka"><?= $u['rata'] !== null ? angka($u['rata']) : '-' ?></td>
                    <td class="angka"><?= $u['tertinggi'] !== null ? angka($u['tertinggi'], 0) : '-' ?></td>
                    <td class="angka"><?= $u['terendah'] !== null ? angka($u['terendah'], 0) : '-' ?></td>
                    <td><div class="aksi-baris">
                        <a class="btn btn-primary btn-sm" href="?ujian_id=<?= (int) $u['id'] ?>">Lihat Nilai</a>
                        <a class="btn btn-light btn-sm" href="<?= e(url('guru/analisis-soal.php?ujian_id=' . (int) $u['id'])) ?>">Analisis</a>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$daftar): ?><tr><td colspan="8" class="kosong">Belum ada ujian.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= paginasi_tautan($halaman, $hal_akhir) ?>
</section>
<?php else: ?>
<div class="grid-stat">
    <div class="stat"><div class="stat-label">Mapel / Kelas</div><div class="stat-nilai" style="font-size:1.2rem"><?= e($ujian['nama_mapel']) ?> - <?= e($ujian['nama_kelas']) ?></div></div>
    <div class="stat hijau"><div class="stat-label">Rata-rata</div><div class="stat-nilai"><?= (int) $stat['jumlah'] > 0 ? angka($stat['rata']) : '-' ?></div></div>
    <div class="stat"><div class="stat-label">Tertinggi</div><div class="stat-nilai"><?= (int) $stat['jumlah'] > 0 ? angka($stat['ttinggi'], 0) : '-' ?></div></div>
    <div class="stat merah"><div class="stat-label">Terendah</div><div class="stat-nilai"><?= (int) $stat['jumlah'] > 0 ? angka($stat['trndh'], 0) : '-' ?></div></div>
</div>
<section class="card">
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>No</th><th>NISN</th><th>Nama</th><th class="angka">Benar</th><th class="angka">Salah</th><th class="angka">Nilai</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($baris as $i => $b): ?>
                <tr>
                    <td><?= ($halaman - 1) * PER_HALAMAN + $i + 1 ?></td><td><?= e($b['nisn']) ?></td><td><strong><?= e($b['nama']) ?></strong></td>
                    <?php if ($b['status'] === 'selesai'): ?>
                        <td class="angka"><?= (int) $b['jumlah_benar'] ?></td><td class="angka"><?= (int) $b['jumlah_salah'] ?></td>
                        <td class="angka"><?= pill(angka($b['nilai']), warna_nilai((float) $b['nilai'])) ?></td><td><?= pill('Selesai', 'hijau') ?></td>
                    <?php else: ?>
                        <td class="angka">-</td><td class="angka">-</td><td class="angka">-</td>
                        <td><?= $b['status'] === 'berlangsung' ? pill('Sedang mengerjakan', 'kuning') : pill('Belum mengerjakan', 'abu') ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= paginasi_tautan($halaman, $hal_akhir, ['ujian_id' => $ujian_id]) ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
