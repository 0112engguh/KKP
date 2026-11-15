<?php
// guru/ujian.php
// Daftar ujian TKA: ubah status (draft/aktif) dan hapus.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$kelas_semua = kelas_guru();
if (!$kelas_semua) akhir_kelas();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $id = (int) ($_POST['id'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';

    $st = db()->prepare('SELECT kelas_id FROM ujian WHERE id = :id');
    $st->execute(['id' => $id]);
    $u_cek = $st->fetch();

    if (!$u_cek || !punya_kelas($kelas_semua, (int) $u_cek['kelas_id'])) {
        flash('danger', 'Ujian tidak termasuk kelas yang ditugaskan kepada Anda.');
    } elseif ($aksi === 'hapus') {
        db()->prepare('DELETE FROM ujian WHERE id = :id')->execute(['id' => $id]);
        flash('success', 'Ujian berhasil dihapus.');
    } elseif ($aksi === 'status') {
        if (skalar('SELECT COUNT(*) FROM ujian_soal WHERE ujian_id = :u', ['u' => $id]) == 0) {
            flash('danger', 'Ujian belum memiliki soal, tidak dapat diaktifkan.');
        } else {
            db()->prepare("UPDATE ujian SET status = IF(status = 'aktif', 'draft', 'aktif') WHERE id = :id")->execute(['id' => $id]);
            flash('success', 'Status ujian diperbarui.');
        }
    }
    redirect(url('guru/ujian.php'));
}

$where_kelas = '';
if (user()['role'] !== 'admin') {
    $ids = array_map('intval', array_column($kelas_semua, 'id'));
    $where_kelas = ' WHERE u.kelas_id IN (' . implode(',', $ids) . ')';
}

$daftar = db()->query("SELECT u.*, m.nama_mapel, k.nama_kelas,
        (SELECT COUNT(*) FROM ujian_soal us WHERE us.ujian_id = u.id) AS jml_soal,
        (SELECT COUNT(*) FROM hasil_ujian h WHERE h.ujian_id = u.id) AS jml_peserta
    FROM ujian u JOIN mata_pelajaran m ON m.id = u.mapel_id JOIN kelas k ON k.id = u.kelas_id
    $where_kelas
    ORDER BY u.waktu_mulai DESC")->fetchAll();
$sekarang = date('Y-m-d H:i:s');

$judul = 'Daftar Ujian TKA';
$aksi_header = '<a class="btn btn-primary" href="' . e(url('guru/ujian-form.php')) . '">+ Buat Ujian</a>';
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>Nama Ujian</th><th>Mapel</th><th>Kelas</th><th class="angka">Durasi</th><th class="angka">Soal</th><th>Jadwal</th><th>Status</th><th class="angka">Peserta</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $u):
                if ($u['status'] === 'draft') $label = pill('Draft', 'abu');
                elseif ($sekarang < $u['waktu_mulai']) $label = pill('Terjadwal', 'biru');
                elseif ($sekarang > $u['waktu_selesai']) $label = pill('Berakhir', 'merah');
                else $label = pill('Aktif', 'hijau'); ?>
                <tr>
                    <td><strong><?= e($u['nama_ujian']) ?></strong></td>
                    <td><?= e($u['nama_mapel']) ?></td>
                    <td><?= e($u['nama_kelas']) ?></td>
                    <td class="angka"><?= (int) $u['durasi_menit'] ?> mnt</td>
                    <td class="angka"><?= (int) $u['jml_soal'] ?></td>
                    <td><small><?= e(waktu_indo($u['waktu_mulai'])) ?><br>s/d <?= e(waktu_indo($u['waktu_selesai'])) ?></small></td>
                    <td><?= $label ?></td>
                    <td class="angka"><?= (int) $u['jml_peserta'] ?></td>
                    <td><div class="aksi-baris">
                        <a class="btn btn-light btn-sm" href="<?= e(url('guru/ujian-form.php?id=' . (int) $u['id'])) ?>">Ubah</a>
                        <form method="post" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="status"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button class="btn btn-warning btn-sm" type="submit"><?= $u['status'] === 'aktif' ? 'Jadikan Draft' : 'Aktifkan' ?></button>
                        </form>
                        <form method="post" class="inline" data-konfirmasi="Hapus ujian ini beserta seluruh hasil siswa?">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$daftar): ?><tr><td colspan="9" class="kosong">Belum ada ujian.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
