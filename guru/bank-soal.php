<?php
// guru/bank-soal.php
// Bank soal: daftar, filter, dan hapus soal pilihan ganda.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    csrf_cek();
    $id = (int) ($_POST['id'] ?? 0);
    if (skalar('SELECT COUNT(*) FROM ujian_soal WHERE soal_id = :s', ['s' => $id]) > 0) {
        flash('danger', 'Soal tidak dapat dihapus karena sudah dipakai pada ujian.');
    } else {
        $gambar = skalar('SELECT gambar FROM bank_soal WHERE id = :s', ['s' => $id]);
        db()->prepare('DELETE FROM bank_soal WHERE id = :s')->execute(['s' => $id]);
        if ($gambar && is_file(UPLOAD_SOAL_DIR . basename($gambar))) @unlink(UPLOAD_SOAL_DIR . basename($gambar));
        flash('success', 'Soal berhasil dihapus.');
    }
    redirect(url('guru/bank-soal.php'));
}

$mapel_id = (int) ($_GET['mapel'] ?? 0);
$tingkat  = $_GET['tingkat'] ?? '';
$cari     = trim($_GET['q'] ?? '');

$where = ' WHERE 1=1';
$param = [];
if ($mapel_id > 0) { $where .= ' AND b.mapel_id = :mapel'; $param['mapel'] = $mapel_id; }
if (in_array($tingkat, ['mudah', 'sedang', 'sulit'], true)) { $where .= ' AND b.tingkat = :tingkat'; $param['tingkat'] = $tingkat; }
if ($cari !== '') { $where .= ' AND b.pertanyaan LIKE :cari'; $param['cari'] = '%' . $cari . '%'; }

$st = db()->prepare('SELECT b.id, b.pertanyaan, b.kategori, b.tingkat, b.kunci_jawaban, b.gambar, m.nama_mapel,
                            (SELECT COUNT(*) FROM ujian_soal us WHERE us.soal_id = b.id) AS dipakai
                     FROM bank_soal b JOIN mata_pelajaran m ON m.id = b.mapel_id' . $where . ' ORDER BY m.nama_mapel, b.id');
$st->execute($param);
$daftar = $st->fetchAll();

$judul = 'Bank Soal';
$aksi_header = '<a class="btn btn-primary" href="' . e(url('guru/bank-soal-form.php')) . '">+ Tambah Soal</a>';
require __DIR__ . '/../includes/header.php';
$warna_tingkat = ['mudah' => 'hijau', 'sedang' => 'kuning', 'sulit' => 'merah'];
?>
<section class="card">
    <form method="get" class="toolbar">
        <div class="form-grup"><label for="mapel">Mata Pelajaran</label>
            <select name="mapel" id="mapel"><option value="0">Semua</option>
                <?php foreach (semua_mapel() as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= $mapel_id === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['nama_mapel']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-grup"><label for="tingkat">Tingkat</label>
            <select name="tingkat" id="tingkat"><option value="">Semua</option>
                <?php foreach (['mudah', 'sedang', 'sulit'] as $t): ?>
                    <option value="<?= $t ?>" <?= $tingkat === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-grup"><label for="q">Cari pertanyaan</label>
            <input type="search" id="q" name="q" value="<?= e($cari) ?>"></div>
        <button class="btn btn-primary" type="submit">Cari</button>
    </form>

    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>No</th><th>Pertanyaan</th><th>Mapel</th><th>Kategori</th><th>Tingkat</th><th class="angka">Kunci</th><th class="angka">Dipakai</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $i => $s): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e(mb_strimwidth($s['pertanyaan'], 0, 90, '...')) ?><?= $s['gambar'] ? ' <i class="bi bi-image-fill"></i>' : '' ?></td>
                    <td><?= e($s['nama_mapel']) ?></td>
                    <td><?= e($s['kategori'] ?? '-') ?></td>
                    <td><?= pill(ucfirst($s['tingkat']), $warna_tingkat[$s['tingkat']]) ?></td>
                    <td class="angka"><strong><?= e($s['kunci_jawaban']) ?></strong></td>
                    <td class="angka"><?= (int) $s['dipakai'] ?>x</td>
                    <td><div class="aksi-baris">
                        <a class="btn btn-light btn-sm" href="<?= e(url('guru/bank-soal-form.php?id=' . (int) $s['id'])) ?>">Ubah</a>
                        <form method="post" class="inline" data-konfirmasi="Hapus soal ini?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$daftar): ?><tr><td colspan="8" class="kosong">Belum ada soal.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <small>Total <?= count($daftar) ?> soal.</small>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
