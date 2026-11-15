<?php
// guru/siswa.php
// Data Master Siswa: daftar, pencarian, filter kelas, dan hapus.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);
$kelas_semua = kelas_guru();
if (!$kelas_semua) akhir_kelas();

// ----- Hapus siswa -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    csrf_cek();
    $id = (int) ($_POST['id'] ?? 0);
    $st = db()->prepare('SELECT user_id, orang_tua_id, kelas_id FROM siswa WHERE id = :id');
    $st->execute(['id' => $id]);
    $s = $st->fetch();

    if (!$s) {
        flash('danger', 'Data tidak ditemukan.');
    } elseif (!punya_kelas($kelas_semua, (int) $s['kelas_id'])) {
        flash('danger', 'Siswa tidak termasuk kelas yang ditugaskan kepada Anda.');
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM siswa WHERE id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM users WHERE id = :u')->execute(['u' => $s['user_id']]);
            // Akun orang tua ikut dihapus bila tidak punya anak lain
            if ($s['orang_tua_id']) {
                $sisa = skalar('SELECT COUNT(*) FROM siswa WHERE orang_tua_id = :o', ['o' => $s['orang_tua_id']]);
                if ((int) $sisa === 0) {
                    $uo = skalar('SELECT user_id FROM orang_tua WHERE id = :o', ['o' => $s['orang_tua_id']]);
                    $pdo->prepare('DELETE FROM orang_tua WHERE id = :o')->execute(['o' => $s['orang_tua_id']]);
                    if ($uo) $pdo->prepare('DELETE FROM users WHERE id = :u')->execute(['u' => $uo]);
                }
            }
            $pdo->commit();
            flash('success', 'Data siswa berhasil dihapus.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($e->getMessage());
            flash('danger', 'Data gagal dihapus.');
        }
    }
    redirect(url('guru/siswa.php'));
}

// ----- Daftar + pencarian -----
$cari     = trim($_GET['q'] ?? '');
$kelas_id = (int) ($_GET['kelas'] ?? 0);
$halaman  = max(1, (int) ($_GET['hal'] ?? 1));
$per_hal  = 20;

$where = ' WHERE 1=1';
$param = [];
if ($cari !== '') {
    $where .= ' AND (s.nama LIKE :cari1 OR s.nisn LIKE :cari2)';
    $param['cari1'] = $param['cari2'] = '%' . $cari . '%';
}
if ($kelas_id > 0 && !punya_kelas($kelas_semua, $kelas_id)) {
    flash('danger', 'Kelas tidak termasuk penugasan Anda.');
    redirect(url('guru/siswa.php'));
}
if ($kelas_id > 0) {
    $where .= ' AND s.kelas_id = :kelas';
    $param['kelas'] = $kelas_id;
} elseif (user()['role'] !== 'admin') {
    $ids = array_map('intval', array_column($kelas_semua, 'id'));
    $where .= ' AND s.kelas_id IN (' . implode(',', $ids) . ')';
}

$total = (int) skalar('SELECT COUNT(*) FROM siswa s' . $where, $param);
$jml_halaman = max(1, (int) ceil($total / $per_hal));
$halaman = min($halaman, $jml_halaman);

$st = db()->prepare('SELECT s.id, s.nisn, s.nama, s.jenis_kelamin, s.status, k.nama_kelas, o.nama AS nama_ortu
                     FROM siswa s JOIN kelas k ON k.id = s.kelas_id LEFT JOIN orang_tua o ON o.id = s.orang_tua_id'
    . $where . ' ORDER BY k.tingkat, k.nama_kelas, s.nama LIMIT :lim OFFSET :off');
foreach ($param as $k => $v) $st->bindValue($k, $v);
$st->bindValue('lim', $per_hal, PDO::PARAM_INT);
$st->bindValue('off', ($halaman - 1) * $per_hal, PDO::PARAM_INT);
$st->execute();
$daftar = $st->fetchAll();

$judul = 'Data Siswa';
$aksi_header = '<a class="btn btn-primary" href="' . e(url('guru/siswa-form.php')) . '">+ Tambah Siswa</a>';
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <form method="get" class="toolbar">
        <div class="form-grup">
            <label for="q">Cari (nama / NISN)</label>
            <input type="search" id="q" name="q" value="<?= e($cari) ?>" placeholder="Ketik nama atau NISN">
        </div>
        <div class="form-grup">
            <label for="kelas">Kelas</label>
            <select name="kelas" id="kelas">
                <option value="0">Semua kelas</option>
                <?php foreach ($kelas_semua as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= $kelas_id === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Cari</button>
        <a class="btn btn-light" href="<?= e(url('guru/siswa.php')) ?>">Reset</a>
    </form>

    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>No</th><th>NISN</th><th>Nama</th><th>L/P</th><th>Kelas</th><th>Orang Tua/Wali</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $i => $s): ?>
                <tr>
                    <td><?= ($halaman - 1) * $per_hal + $i + 1 ?></td>
                    <td><?= e($s['nisn']) ?></td>
                    <td><strong><?= e($s['nama']) ?></strong></td>
                    <td><?= e($s['jenis_kelamin']) ?></td>
                    <td><?= e($s['nama_kelas']) ?></td>
                    <td><?= e($s['nama_ortu'] ?? '-') ?></td>
                    <td><?= $s['status'] === 'aktif' ? pill('Aktif', 'hijau') : pill('Nonaktif', 'abu') ?></td>
                    <td>
                        <div class="aksi-baris">
                            <a class="btn btn-light btn-sm" href="<?= e(url('guru/siswa-form.php?id=' . (int) $s['id'])) ?>">Ubah</a>
                            <form method="post" class="inline" data-konfirmasi="Hapus siswa <?= e($s['nama']) ?>? Seluruh absensi, nilai, dan jurnalnya ikut terhapus.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$daftar): ?><tr><td colspan="8" class="kosong">Data tidak ditemukan.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($jml_halaman > 1): ?>
        <div class="toolbar mt">
            <?php for ($h = 1; $h <= $jml_halaman; $h++):
                $qs = http_build_query(['q' => $cari, 'kelas' => $kelas_id, 'hal' => $h]); ?>
                <a class="btn btn-sm <?= $h === $halaman ? 'btn-primary' : 'btn-light' ?>" href="?<?= e($qs) ?>"><?= (int) $h ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    <small>Total <?= (int) $total ?> siswa.</small>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
