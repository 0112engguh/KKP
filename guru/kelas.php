<?php
// guru/kelas.php
// Data Master Kelas: daftar + form tambah/ubah dalam satu halaman.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$id = (int) ($_GET['id'] ?? 0);
$data = ['nama_kelas' => '', 'tingkat' => 4, 'wali_kelas_id' => 0];
$galat = [];

if ($id > 0) {
    $st = db()->prepare('SELECT * FROM kelas WHERE id = :id');
    $st->execute(['id' => $id]);
    $data = $st->fetch();
    if (!$data) {
        flash('danger', 'Data tidak ditemukan.');
        redirect(url('guru/kelas.php'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $aksi = $_POST['aksi'] ?? 'simpan';

    if ($aksi === 'hapus') {
        $hapus_id = (int) ($_POST['id'] ?? 0);
        if (skalar('SELECT COUNT(*) FROM siswa WHERE kelas_id = :k', ['k' => $hapus_id]) > 0) {
            flash('danger', 'Kelas tidak dapat dihapus karena masih memiliki siswa.');
        } elseif (skalar('SELECT COUNT(*) FROM ujian WHERE kelas_id = :k', ['k' => $hapus_id]) > 0) {
            flash('danger', 'Kelas tidak dapat dihapus karena sudah dipakai pada ujian.');
        } else {
            db()->prepare('DELETE FROM kelas WHERE id = :id')->execute(['id' => $hapus_id]);
            flash('success', 'Data kelas berhasil dihapus.');
        }
        redirect(url('guru/kelas.php'));
    }

    $data['nama_kelas']    = trim($_POST['nama_kelas'] ?? '');
    $data['tingkat']       = (int) ($_POST['tingkat'] ?? 0);
    $data['wali_kelas_id'] = (int) ($_POST['wali_kelas_id'] ?? 0);

    if ($data['nama_kelas'] === '' || strlen($data['nama_kelas']) > 20) $galat[] = 'Nama kelas wajib diisi (maksimal 20 karakter).';
    if ($data['tingkat'] < 1 || $data['tingkat'] > 6) $galat[] = 'Tingkat harus antara 1 sampai 6.';
    if (!$galat && skalar('SELECT COUNT(*) FROM kelas WHERE nama_kelas = :n AND id <> :id', ['n' => $data['nama_kelas'], 'id' => $id])) {
        $galat[] = 'Nama kelas sudah ada.';
    }
    if (!$galat && $data['wali_kelas_id'] > 0 && !skalar('SELECT COUNT(*) FROM guru WHERE id = :g', ['g' => $data['wali_kelas_id']])) {
        $galat[] = 'Wali kelas tidak ditemukan.';
    }

    if (!$galat) {
        $wali = $data['wali_kelas_id'] > 0 ? $data['wali_kelas_id'] : null;
        try {
            if ($id === 0) {
                db()->prepare('INSERT INTO kelas (nama_kelas, tingkat, wali_kelas_id) VALUES (:n, :t, :w)')
                    ->execute(['n' => $data['nama_kelas'], 't' => $data['tingkat'], 'w' => $wali]);
            } else {
                db()->prepare('UPDATE kelas SET nama_kelas = :n, tingkat = :t, wali_kelas_id = :w WHERE id = :id')
                    ->execute(['n' => $data['nama_kelas'], 't' => $data['tingkat'], 'w' => $wali, 'id' => $id]);
            }
            flash('success', 'Data berhasil disimpan.');
            redirect(url('guru/kelas.php'));
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $galat[] = 'Data gagal disimpan.';
        }
    }
}

$daftar = db()->query("SELECT k.id, k.nama_kelas, k.tingkat, g.nama AS wali,
        (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id) AS jml_siswa
    FROM kelas k LEFT JOIN guru g ON g.id = k.wali_kelas_id ORDER BY k.tingkat, k.nama_kelas")->fetchAll();
$daftar_guru = db()->query('SELECT id, nama FROM guru ORDER BY nama')->fetchAll();

$judul = 'Data Kelas';
require __DIR__ . '/../includes/header.php';
?>
<?php foreach ($galat as $g): ?><div class="flash flash-danger"><?= e($g) ?></div><?php endforeach; ?>

<div class="grid-2">
    <section class="card">
        <h2>Daftar Kelas</h2>
        <div class="tabel-wrap">
            <table class="tabel">
                <thead><tr><th>Kelas</th><th>Tingkat</th><th>Wali Kelas</th><th class="angka">Siswa</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($daftar as $k): ?>
                    <tr>
                        <td><strong><?= e($k['nama_kelas']) ?></strong></td>
                        <td><?= (int) $k['tingkat'] ?></td>
                        <td><?= e($k['wali'] ?? '-') ?></td>
                        <td class="angka"><?= (int) $k['jml_siswa'] ?></td>
                        <td><div class="aksi-baris">
                            <a class="btn btn-light btn-sm" href="?id=<?= (int) $k['id'] ?>">Ubah</a>
                            <form method="post" class="inline" data-konfirmasi="Hapus kelas <?= e($k['nama_kelas']) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                            </form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$daftar): ?><tr><td colspan="5" class="kosong">Belum ada kelas.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2><?= $id ? 'Ubah Kelas' : 'Tambah Kelas' ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <div class="form-grup"><label for="nama_kelas">Nama Kelas *</label>
                <input type="text" id="nama_kelas" name="nama_kelas" value="<?= e($data['nama_kelas']) ?>" maxlength="20" placeholder="contoh: 4A" required></div>
            <div class="form-grup"><label for="tingkat">Tingkat (1-6) *</label>
                <input type="number" id="tingkat" name="tingkat" value="<?= (int) $data['tingkat'] ?>" min="1" max="6" required></div>
            <div class="form-grup"><label for="wali_kelas_id">Wali Kelas</label>
                <select id="wali_kelas_id" name="wali_kelas_id">
                    <option value="0">-- Belum ada --</option>
                    <?php foreach ($daftar_guru as $g): ?>
                        <option value="<?= (int) $g['id'] ?>" <?= (int) $data['wali_kelas_id'] === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['nama']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <button type="submit" class="btn btn-primary">Simpan</button>
            <?php if ($id): ?><a class="btn btn-light" href="<?= e(url('guru/kelas.php')) ?>">Batal</a><?php endif; ?>
        </form>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
