<?php
// guru/bank-soal.php
// Halaman utama Bank Soal (parent): daftar Kelompok Ujian beserta filter, cari,
// tambah/ubah/hapus, ubah status, dan jumlah soal tiap kelompok. Daftar soal
// per kelompok ada di bank-soal-detail.php.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$kelas_semua = kelas_guru();
if (!$kelas_semua) akhir_kelas();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $id   = (int) ($_POST['id'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';

    $st = db()->prepare('SELECT kelas_id FROM kelompok_ujian WHERE id = :id');
    $st->execute(['id' => $id]);
    $u_cek = $st->fetch();

    if (!$u_cek || !punya_kelas($kelas_semua, (int) $u_cek['kelas_id'])) {
        flash('danger', 'Kelompok ujian tidak termasuk kelas yang ditugaskan kepada Anda.');
    } elseif ($aksi === 'hapus') {
        // soal di dalam kelompok ikut terlepas, tetapi soal di bank_soal tidak terhapus
        db()->prepare('DELETE FROM kelompok_ujian WHERE id = :id')->execute(['id' => $id]);
        flash('success', 'Kelompok ujian berhasil dihapus. Soal di Bank Soal tetap tersimpan.');
    } elseif ($aksi === 'status') {
        db()->prepare("UPDATE kelompok_ujian SET status = IF(status = 'aktif', 'draft', 'aktif') WHERE id = :id")
            ->execute(['id' => $id]);
        flash('success', 'Status kelompok ujian diperbarui.');
    }
    redirect(url('guru/bank-soal.php'));
}

$cari        = trim($_GET['q'] ?? '');
$tahun       = trim($_GET['tahun'] ?? '');
$semester    = $_GET['semester'] ?? '';
$filter_kelas = (int) ($_GET['kelas'] ?? 0);
$status      = $_GET['status'] ?? '';

$where = ' WHERE 1=1';
$param = [];
if ($cari !== '') { $where .= ' AND k.nama_ujian LIKE :cari'; $param['cari'] = '%' . $cari . '%'; }
if ($tahun !== '') { $where .= ' AND k.tahun_ajaran = :tahun'; $param['tahun'] = $tahun; }
if (in_array($semester, ['ganjil', 'genap'], true)) { $where .= ' AND k.semester = :semester'; $param['semester'] = $semester; }
if ($filter_kelas > 0) { $where .= ' AND k.kelas_id = :kelas'; $param['kelas'] = $filter_kelas; }
if (in_array($status, ['draft', 'aktif'], true)) { $where .= ' AND k.status = :status'; $param['status'] = $status; }

// Guru hanya melihat kelompok dari kelas yang ditugaskan (seperti halaman Ujian)
if (user()['role'] !== 'admin') {
    $ids = array_map('intval', array_column($kelas_semua, 'id'));
    $where .= ' AND k.kelas_id IN (' . implode(',', $ids) . ')';
}

[$daftar, $halaman, $hal_akhir, $total] = paginasi(
    'SELECT k.*, kl.nama_kelas,
        (SELECT COUNT(*) FROM kelompok_ujian_soal ks WHERE ks.kelompok_id = k.id) AS jml_soal,
        (SELECT COALESCE(SUM(ks.bobot), 0) FROM kelompok_ujian_soal ks WHERE ks.kelompok_id = k.id) AS total_bobot
    FROM kelompok_ujian k JOIN kelas kl ON kl.id = k.kelas_id' . $where . '
    ORDER BY k.tahun_ajaran DESC, k.semester DESC, k.nama_ujian',
    'SELECT COUNT(*) FROM kelompok_ujian k' . $where, $param);

// Pilihan tahun ajaran untuk filter diambil dari data yang ada
$tahun_ajaran = db()->query('SELECT DISTINCT tahun_ajaran FROM kelompok_ujian ORDER BY tahun_ajaran DESC')->fetchAll(PDO::FETCH_COLUMN);

$judul = 'Bank Soal';
$aksi_header = '<a class="btn btn-primary" href="' . e(url('guru/kelompok-form.php')) . '">+ Kelompok Ujian</a>';
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <form method="get" class="toolbar">
        <div class="form-grup"><label for="q">Cari nama ujian</label>
            <input type="search" id="q" name="q" value="<?= e($cari) ?>"></div>
        <div class="form-grup"><label for="tahun">Tahun Ajaran</label>
            <select name="tahun" id="tahun"><option value="">Semua</option>
                <?php foreach ($tahun_ajaran as $t): ?>
                    <option value="<?= e($t) ?>" <?= $tahun === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-grup"><label for="semester">Semester</label>
            <select name="semester" id="semester"><option value="">Semua</option>
                <option value="ganjil" <?= $semester === 'ganjil' ? 'selected' : '' ?>>Ganjil</option>
                <option value="genap" <?= $semester === 'genap' ? 'selected' : '' ?>>Genap</option>
            </select></div>
        <div class="form-grup"><label for="kelas">Kelas</label>
            <select name="kelas" id="kelas"><option value="0">Semua</option>
                <?php foreach ($kelas_semua as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= $filter_kelas === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-grup"><label for="status">Status</label>
            <select name="status" id="status"><option value="">Semua</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
            </select></div>
        <button class="btn btn-primary" type="submit">Terapkan</button>
        <a class="btn btn-light" href="<?= e(url('guru/bank-soal.php')) ?>">Reset</a>
    </form>

    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>Nama Ujian</th><th>Tahun Ajaran</th><th>Semester</th><th>Kelas</th>
                <th class="angka">Soal</th><th class="angka">Total Bobot</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $k): ?>
                <tr>
                    <td><strong><?= e($k['nama_ujian']) ?></strong></td>
                    <td><?= e($k['tahun_ajaran']) ?></td>
                    <td><?= pill(ucfirst($k['semester']), $k['semester'] === 'ganjil' ? 'biru' : 'kuning') ?></td>
                    <td><?= e($k['nama_kelas']) ?></td>
                    <td class="angka"><strong><?= (int) $k['jml_soal'] ?></strong></td>
                    <td class="angka"><?= angka($k['total_bobot'], 2) ?></td>
                    <td><?= $k['status'] === 'aktif' ? pill('Aktif', 'hijau') : pill('Draft', 'abu') ?></td>
                    <td><div class="aksi-baris">
                        <a class="btn btn-primary btn-sm" href="<?= e(url('guru/bank-soal-detail.php?id=' . (int) $k['id'])) ?>">Buka</a>
                        <a class="btn btn-light btn-sm" href="<?= e(url('guru/kelompok-form.php?id=' . (int) $k['id'])) ?>">Ubah</a>
                        <form method="post" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="status"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                            <button class="btn btn-warning btn-sm" type="submit"><?= $k['status'] === 'aktif' ? 'Jadikan Draft' : 'Aktifkan' ?></button>
                        </form>
                        <form method="post" class="inline" data-konfirmasi="Hapus kelompok ujian ini? Soal di Bank Soal tidak ikut terhapus.">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$daftar): ?><tr><td colspan="8" class="kosong">Belum ada kelompok ujian.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= paginasi_tautan($halaman, $hal_akhir, ['q' => $cari, 'tahun' => $tahun, 'semester' => $semester, 'kelas' => $filter_kelas, 'status' => $status]) ?>
    <small>Total <?= count($daftar) ?> kelompok ujian pada halaman ini (total <?= (int) $total ?>).</small>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
