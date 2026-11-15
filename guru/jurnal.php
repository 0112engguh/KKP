<?php
// guru/jurnal.php
// Daftar jurnal 7 kebiasaan siswa. Filter status "menunggu" dipakai sebagai halaman Verifikasi.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$status   = $_GET['status'] ?? 'semua';
if (!in_array($status, ['semua', 'menunggu', 'disetujui', 'ditolak'], true)) $status = 'semua';
$kelas_id = (int) ($_GET['kelas'] ?? 0);
$tanggal  = trim($_GET['tanggal'] ?? '');

$kelas_semua = kelas_guru();
if (!$kelas_semua) akhir_kelas();
if ($kelas_id > 0 && !punya_kelas($kelas_semua, $kelas_id)) {
    flash('danger', 'Kelas tidak termasuk penugasan Anda.');
    redirect(url('guru/jurnal.php?status=' . urlencode($status)));
}

$where = ' WHERE 1=1';
$param = [];
if ($status !== 'semua') { $where .= ' AND j.status = :status'; $param['status'] = $status; }
if ($kelas_id > 0) {
    $where .= ' AND s.kelas_id = :kelas';
    $param['kelas'] = $kelas_id;
} elseif (user()['role'] !== 'admin') {
    $ids = array_map('intval', array_column($kelas_semua, 'id'));
    $where .= ' AND s.kelas_id IN (' . implode(',', $ids) . ')';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) { $where .= ' AND j.tanggal = :tgl'; $param['tgl'] = $tanggal; } else { $tanggal = ''; }

$st = db()->prepare('SELECT j.id, j.tanggal, j.status, j.foto_jurnal, s.nama, s.nisn, k.nama_kelas,
        (SELECT COALESCE(SUM(d.selesai), 0) FROM detail_jurnal_kebiasaan d WHERE d.jurnal_id = j.id) AS jumlah
    FROM jurnal_kebiasaan j JOIN siswa s ON s.id = j.siswa_id JOIN kelas k ON k.id = s.kelas_id' . $where
    . " ORDER BY FIELD(j.status, 'menunggu', 'ditolak', 'disetujui'), j.tanggal DESC, s.nama LIMIT 200");
$st->execute($param);
$daftar = $st->fetchAll();

$judul = $status === 'menunggu' ? 'Verifikasi Jurnal Kebiasaan' : 'Jurnal 7 Kebiasaan';
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <form method="get" class="toolbar">
        <div class="form-grup"><label for="status">Status</label>
            <select name="status" id="status">
                <?php foreach (['semua' => 'Semua', 'menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'] as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-grup"><label for="kelas">Kelas</label>
            <select name="kelas" id="kelas"><option value="0">Semua Kelas Saya</option>
                <?php foreach ($kelas_semua as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= $kelas_id === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-grup"><label for="tanggal">Tanggal</label>
            <input type="date" id="tanggal" name="tanggal" value="<?= e($tanggal) ?>"></div>
        <button class="btn btn-primary" type="submit">Tampilkan</button>
    </form>

    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>Tanggal</th><th>Siswa</th><th>Kelas</th><th class="angka">Checklist</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $j): ?>
                <tr>
                    <td><?= e(tgl_indo($j['tanggal'])) ?></td>
                    <td><strong><?= e($j['nama']) ?></strong><br><small><?= e($j['nisn']) ?></small></td>
                    <td><?= e($j['nama_kelas']) ?></td>
                    <td class="angka"><?= (int) $j['jumlah'] ?>/7 <?= $j['foto_jurnal'] ? '<i class="bi bi-camera-fill" title="Ada foto bukti"></i>' : '' ?></td>
                    <td><?= pill_status_jurnal($j['status']) ?></td>
                    <td><a class="btn <?= $j['status'] === 'menunggu' ? 'btn-primary' : 'btn-light' ?> btn-sm" href="<?= e(url('guru/jurnal-detail.php?id=' . (int) $j['id'])) ?>">
                        <?= $j['status'] === 'menunggu' ? 'Periksa' : 'Lihat' ?></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$daftar): ?><tr><td colspan="6" class="kosong">Data tidak ditemukan.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <small>Menampilkan maksimal 200 jurnal terbaru.</small>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
