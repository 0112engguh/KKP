<?php
// guru/mapel.php
// Data Master Mata Pelajaran: daftar + form tambah/ubah dalam satu halaman.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$id = (int) ($_GET['id'] ?? 0);
$data = ['nama_mapel' => '', 'keterangan' => ''];
$galat = [];

if ($id > 0) {
    $st = db()->prepare('SELECT * FROM mata_pelajaran WHERE id = :id');
    $st->execute(['id' => $id]);
    $data = $st->fetch();
    if (!$data) {
        flash('danger', 'Data tidak ditemukan.');
        redirect(url('guru/mapel.php'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();

    if (($_POST['aksi'] ?? '') === 'hapus') {
        $hapus_id = (int) ($_POST['id'] ?? 0);
        $dipakai = skalar('SELECT (SELECT COUNT(*) FROM bank_soal WHERE mapel_id = :a) + (SELECT COUNT(*) FROM ujian WHERE mapel_id = :b)',
                          ['a' => $hapus_id, 'b' => $hapus_id]);
        if ($dipakai > 0) {
            flash('danger', 'Mata pelajaran tidak dapat dihapus karena sudah dipakai pada soal atau ujian.');
        } else {
            db()->prepare('DELETE FROM mata_pelajaran WHERE id = :id')->execute(['id' => $hapus_id]);
            flash('success', 'Data mata pelajaran berhasil dihapus.');
        }
        redirect(url('guru/mapel.php'));
    }

    $data['nama_mapel'] = trim($_POST['nama_mapel'] ?? '');
    $data['keterangan'] = trim($_POST['keterangan'] ?? '');
    if ($data['nama_mapel'] === '' || strlen($data['nama_mapel']) > 80) $galat[] = 'Nama mata pelajaran wajib diisi (maksimal 80 karakter).';
    if (!$galat && skalar('SELECT COUNT(*) FROM mata_pelajaran WHERE nama_mapel = :n AND id <> :id', ['n' => $data['nama_mapel'], 'id' => $id])) {
        $galat[] = 'Nama mata pelajaran sudah ada.';
    }

    if (!$galat) {
        try {
            $ket = $data['keterangan'] !== '' ? $data['keterangan'] : null;
            if ($id === 0) {
                db()->prepare('INSERT INTO mata_pelajaran (nama_mapel, keterangan) VALUES (:n, :k)')->execute(['n' => $data['nama_mapel'], 'k' => $ket]);
            } else {
                db()->prepare('UPDATE mata_pelajaran SET nama_mapel = :n, keterangan = :k WHERE id = :id')->execute(['n' => $data['nama_mapel'], 'k' => $ket, 'id' => $id]);
            }
            flash('success', 'Data berhasil disimpan.');
            redirect(url('guru/mapel.php'));
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $galat[] = 'Data gagal disimpan.';
        }
    }
}

$daftar = db()->query('SELECT m.id, m.nama_mapel, m.keterangan, (SELECT COUNT(*) FROM bank_soal b WHERE b.mapel_id = m.id) AS jml_soal
                       FROM mata_pelajaran m ORDER BY m.nama_mapel')->fetchAll();

$judul = 'Mata Pelajaran';
require __DIR__ . '/../includes/header.php';
?>
<?php foreach ($galat as $g): ?><div class="flash flash-danger"><?= e($g) ?></div><?php endforeach; ?>

<div class="grid-2">
    <section class="card">
        <h2>Daftar Mata Pelajaran</h2>
        <div class="tabel-wrap">
            <table class="tabel">
                <thead><tr><th>Mata Pelajaran</th><th>Keterangan</th><th class="angka">Soal</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($daftar as $m): ?>
                    <tr>
                        <td><strong><?= e($m['nama_mapel']) ?></strong></td>
                        <td><?= e($m['keterangan'] ?? '-') ?></td>
                        <td class="angka"><?= (int) $m['jml_soal'] ?></td>
                        <td><div class="aksi-baris">
                            <a class="btn btn-light btn-sm" href="?id=<?= (int) $m['id'] ?>">Ubah</a>
                            <form method="post" class="inline" data-konfirmasi="Hapus mata pelajaran <?= e($m['nama_mapel']) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                            </form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$daftar): ?><tr><td colspan="4" class="kosong">Belum ada mata pelajaran.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2><?= $id ? 'Ubah Mata Pelajaran' : 'Tambah Mata Pelajaran' ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <div class="form-grup"><label for="nama_mapel">Nama Mata Pelajaran *</label>
                <input type="text" id="nama_mapel" name="nama_mapel" value="<?= e($data['nama_mapel']) ?>" maxlength="80" required></div>
            <div class="form-grup"><label for="keterangan">Keterangan</label>
                <textarea id="keterangan" name="keterangan" maxlength="255"><?= e($data['keterangan']) ?></textarea></div>
            <button type="submit" class="btn btn-primary">Simpan</button>
            <?php if ($id): ?><a class="btn btn-light" href="<?= e(url('guru/mapel.php')) ?>">Batal</a><?php endif; ?>
        </form>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
