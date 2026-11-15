<?php
// guru/jurnal-detail.php
// Verifikasi satu jurnal: setujui / tolak, beri catatan dan apresiasi.
// Jika disetujui, poin (1 kebiasaan selesai = 1 poin) dicatat. Jika ditolak/menunggu, poin dihapus.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
$pengguna_login = wajib_role(['admin', 'guru']);

$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare('SELECT j.*, s.nama, s.nisn, s.id AS sid, s.kelas_id, k.nama_kelas FROM jurnal_kebiasaan j
                     JOIN siswa s ON s.id = j.siswa_id JOIN kelas k ON k.id = s.kelas_id WHERE j.id = :id');
$st->execute(['id' => $id]);
$jurnal = $st->fetch();
if (!$jurnal) {
    flash('danger', 'Data tidak ditemukan.');
    redirect(url('guru/jurnal.php'));
}

$kelas_semua = kelas_guru();
if (!$kelas_semua || !punya_kelas($kelas_semua, (int) $jurnal['kelas_id'])) {
    flash('danger', 'Jurnal tidak termasuk kelas yang ditugaskan kepada Anda.');
    redirect(url('guru/jurnal.php'));
}

$st = db()->prepare('SELECT k.id, k.nama_kebiasaan, k.deskripsi, d.selesai FROM kebiasaan k
                     LEFT JOIN detail_jurnal_kebiasaan d ON d.kebiasaan_id = k.id AND d.jurnal_id = :j ORDER BY k.id');
$st->execute(['j' => $id]);
$detail = $st->fetchAll();
$jumlah_selesai = array_sum(array_map(fn($d) => (int) $d['selesai'], $detail));

$st = db()->prepare('SELECT * FROM verifikasi_kebiasaan WHERE jurnal_id = :j');
$st->execute(['j' => $id]);
$verif = $st->fetch() ?: ['catatan' => '', 'apresiasi' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $status    = $_POST['status'] ?? '';
    $catatan   = trim($_POST['catatan'] ?? '');
    $apresiasi = trim($_POST['apresiasi'] ?? '');

    if (!in_array($status, ['menunggu', 'disetujui', 'ditolak'], true)) {
        flash('danger', 'Pilih status verifikasi.');
    } elseif ($status === 'ditolak' && $catatan === '') {
        flash('danger', 'Isi catatan perbaikan untuk jurnal yang ditolak.');
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE jurnal_kebiasaan SET status = :s WHERE id = :id')->execute(['s' => $status, 'id' => $id]);
            $pdo->prepare('INSERT INTO verifikasi_kebiasaan (jurnal_id, guru_id, catatan, apresiasi, diverifikasi_pada)
                           VALUES (:j, :g, :c, :a, :w)
                           ON DUPLICATE KEY UPDATE guru_id = VALUES(guru_id), catatan = VALUES(catatan),
                                                   apresiasi = VALUES(apresiasi), diverifikasi_pada = VALUES(diverifikasi_pada)')
                ->execute(['j' => $id, 'g' => $pengguna_login['profil_id'], 'c' => $catatan !== '' ? $catatan : null,
                           'a' => $apresiasi !== '' ? $apresiasi : null, 'w' => date('Y-m-d H:i:s')]);

            if ($status === 'disetujui') {
                $pdo->prepare('INSERT INTO poin (siswa_id, jurnal_id, jumlah, tanggal) VALUES (:s, :j, :n, :t)
                               ON DUPLICATE KEY UPDATE jumlah = VALUES(jumlah)')
                    ->execute(['s' => $jurnal['sid'], 'j' => $id, 'n' => $jumlah_selesai, 't' => $jurnal['tanggal']]);
            } else {
                $pdo->prepare('DELETE FROM poin WHERE jurnal_id = :j')->execute(['j' => $id]);
            }
            $pdo->commit();
            flash('success', $status === 'disetujui' ? "Jurnal disetujui. Siswa mendapat $jumlah_selesai poin." : 'Verifikasi berhasil disimpan.');
            redirect(url('guru/jurnal.php?status=menunggu'));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($e->getMessage());
            flash('danger', 'Data gagal disimpan.');
        }
    }
    redirect(url('guru/jurnal-detail.php?id=' . $id));
}

$judul = 'Periksa Jurnal Kebiasaan';
$aksi_header = '<a class="btn btn-light" href="' . e(url('guru/jurnal.php?status=menunggu')) . '">&larr; Kembali</a>';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid-2">
    <section class="card">
        <h2><?= e($jurnal['nama']) ?> <small>(<?= e($jurnal['nama_kelas']) ?>)</small></h2>
        <p>Tanggal: <strong><?= e(hari_tgl_indo($jurnal['tanggal'])) ?></strong> &middot; Status: <?= pill_status_jurnal($jurnal['status']) ?></p>
        <?php foreach ($detail as $d): ?>
            <div class="cek-baris" style="padding:.4rem 0">
                <span><?= $d['selesai'] ? '<i class="bi bi-check-square-fill" style="color:var(--success)"></i>' : '<i class="bi bi-square"></i>' ?></span>
                <span><strong><?= e($d['nama_kebiasaan']) ?></strong> <small><?= e($d['deskripsi']) ?></small></span>
            </div>
        <?php endforeach; ?>
        <p class="mt"><strong><?= (int) $jumlah_selesai ?> dari 7</strong> kebiasaan diceklis (setara <?= (int) $jumlah_selesai ?> poin).</p>
        <?php if ($jurnal['catatan_siswa']): ?><p><em>Catatan siswa: &ldquo;<?= e($jurnal['catatan_siswa']) ?>&rdquo;</em></p><?php endif; ?>
        <?php if ($jurnal['foto_jurnal']): ?>
            <p class="mt"><strong>Foto bukti:</strong> <a href="<?= e(url('uploads/jurnal/' . $jurnal['foto_jurnal'])) ?>" target="_blank"><img class="soal-gambar" src="<?= e(url('uploads/jurnal/' . $jurnal['foto_jurnal'])) ?>" alt="Foto bukti jurnal" style="max-height:160px"></a></p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Verifikasi</h2>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-grup"><label>Keputusan</label>
                <?php foreach (['disetujui' => 'Setujui (poin diberikan)', 'ditolak' => 'Tolak (perlu perbaikan)', 'menunggu' => 'Kembalikan ke Menunggu'] as $k => $v): ?>
                    <label class="cek-baris"><input type="radio" name="status" value="<?= e($k) ?>" <?= $jurnal['status'] === $k || ($jurnal['status'] === 'menunggu' && $k === 'disetujui') ? 'checked' : '' ?>> <?= e($v) ?></label>
                <?php endforeach; ?>
            </div>
            <div class="form-grup"><label for="catatan">Catatan / Motivasi</label>
                <textarea id="catatan" name="catatan" maxlength="255"><?= e($verif['catatan']) ?></textarea></div>
            <div class="form-grup"><label for="apresiasi">Apresiasi</label>
                <input type="text" id="apresiasi" name="apresiasi" list="saranApresiasi" value="<?= e($verif['apresiasi']) ?>" maxlength="150" placeholder="contoh: Hebat, pertahankan ya!">
                <datalist id="saranApresiasi">
                    <option value="Hebat, pertahankan ya!"><option value="Luar biasa, kamu rajin sekali!">
                    <option value="Bagus sekali, terus semangat!"><option value="Keren, tingkatkan terus kebiasaan baikmu!">
                </datalist></div>
            <button type="submit" class="btn btn-success">Simpan Verifikasi</button>
        </form>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
