<?php
// guru/guru.php
// Data Master Guru (khusus role Admin): daftar dan hapus.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
$pengguna_login = wajib_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    csrf_cek();
    $id = (int) ($_POST['id'] ?? 0);
    $st = db()->prepare('SELECT user_id FROM guru WHERE id = :id');
    $st->execute(['id' => $id]);
    $g = $st->fetch();

    if (!$g) {
        flash('danger', 'Data tidak ditemukan.');
    } elseif ((int) $g['user_id'] === (int) $pengguna_login['id']) {
        flash('danger', 'Akun yang sedang Anda pakai tidak dapat dihapus.');
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM guru WHERE id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM users WHERE id = :u')->execute(['u' => $g['user_id']]);
            $pdo->commit();
            flash('success', 'Data guru berhasil dihapus.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($e->getMessage());
            flash('danger', 'Data gagal dihapus.');
        }
    }
    redirect(url('guru/guru.php'));
}

$daftar = db()->query("SELECT g.id, g.nip, g.nama, g.email, r.nama_role,
        (SELECT GROUP_CONCAT(k.nama_kelas SEPARATOR ', ') FROM kelas k WHERE k.wali_kelas_id = g.id) AS wali_kelas,
        (SELECT GROUP_CONCAT(kk.nama_kelas ORDER BY kk.tingkat, kk.nama_kelas SEPARATOR ', ')
             FROM guru_kelas gk JOIN kelas kk ON kk.id = gk.kelas_id WHERE gk.guru_id = g.id) AS kelas_tugas
    FROM guru g JOIN users u ON u.id = g.user_id JOIN roles r ON r.id = u.role_id ORDER BY r.id, g.nama")->fetchAll();
[$daftar, $halaman, $hal_akhir] = paginasi_array($daftar);

$judul = 'Data Guru';
$aksi_header = '<a class="btn btn-primary" href="' . e(url('guru/guru-form.php')) . '">+ Tambah Guru</a>';
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>No</th><th>NIP</th><th>Nama</th><th>Email</th><th>Role</th><th>Wali Kelas</th><th>Kelas Ditugaskan</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $i => $g): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($g['nip']) ?></td>
                    <td><strong><?= e($g['nama']) ?></strong></td>
                    <td><?= e($g['email'] ?? '-') ?></td>
                    <td><?= pill(nama_role($g['nama_role']), $g['nama_role'] === 'admin' ? 'biru' : 'hijau') ?></td>
                    <td><?= e($g['wali_kelas'] ?? '-') ?></td>
                    <td><small><?= e($g['kelas_tugas'] ?? '-') ?></small></td>
                    <td>
                        <div class="aksi-baris">
                            <a class="btn btn-light btn-sm" href="<?= e(url('guru/guru-form.php?id=' . (int) $g['id'])) ?>">Ubah</a>
                            <form method="post" class="inline" data-konfirmasi="Hapus akun <?= e($g['nama']) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= paginasi_tautan($halaman, $hal_akhir) ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
