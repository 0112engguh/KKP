<?php
// siswa/kebiasaan.php
// Checklist harian 7 kebiasaan. Setelah disimpan, jurnal berstatus "menunggu" sampai diverifikasi guru.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['siswa']);
$siswa = siswa_login();
if (!$siswa) redirect(url('auth/logout.php'));
$sid = (int) $siswa['id'];
$hari_ini = date('Y-m-d');

$st = db()->prepare('SELECT * FROM jurnal_kebiasaan WHERE siswa_id = :s AND tanggal = :t');
$st->execute(['s' => $sid, 't' => $hari_ini]);
$jurnal = $st->fetch() ?: null;
$terkunci = $jurnal && $jurnal['status'] === 'disetujui';

$daftar_kebiasaan = db()->query('SELECT id, nama_kebiasaan, deskripsi FROM kebiasaan ORDER BY id')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    if ($terkunci) {
        flash('danger', 'Jurnal hari ini sudah disetujui guru dan tidak dapat diubah.');
        redirect(url('siswa/kebiasaan.php'));
    }
    $dipilih = array_map('intval', $_POST['kebiasaan'] ?? []);
    $catatan = trim($_POST['catatan'] ?? '');
    $valid = array_column($daftar_kebiasaan, 'id');
    $dipilih = array_values(array_intersect($dipilih, array_map('intval', $valid)));

    // Foto bukti (opsional): hapus foto lama bila diganti.
    $foto = $jurnal['foto_jurnal'] ?? null;
    try {
        if (isset($_FILES['foto_jurnal']) && $_FILES['foto_jurnal']['error'] !== UPLOAD_ERR_NO_FILE) {
            $nama_foto = simpan_gambar(UPLOAD_JURNAL_DIR, $_FILES['foto_jurnal']);
            if ($foto) @unlink(UPLOAD_JURNAL_DIR . $foto);
            $foto = $nama_foto;
        }
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
        redirect(url('siswa/kebiasaan.php'));
    }

    if (!$dipilih) {
        flash('warning', 'Centang minimal satu kebiasaan yang kamu lakukan hari ini.');
        redirect(url('siswa/kebiasaan.php'));
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        if ($jurnal) {
            $jurnal_id = (int) $jurnal['id'];
            $pdo->prepare("UPDATE jurnal_kebiasaan SET catatan_siswa = :c, foto_jurnal = :f, status = 'menunggu' WHERE id = :id")
                ->execute(['c' => $catatan !== '' ? mb_substr($catatan, 0, 255) : null, 'f' => $foto, 'id' => $jurnal_id]);
        } else {
            $pdo->prepare('INSERT INTO jurnal_kebiasaan (siswa_id, tanggal, catatan_siswa, foto_jurnal) VALUES (:s, :t, :c, :f)')
                ->execute(['s' => $sid, 't' => $hari_ini, 'c' => $catatan !== '' ? mb_substr($catatan, 0, 255) : null, 'f' => $foto]);
            $jurnal_id = (int) $pdo->lastInsertId();
        }
        $simpan = $pdo->prepare('INSERT INTO detail_jurnal_kebiasaan (jurnal_id, kebiasaan_id, selesai) VALUES (:j, :k, :s)
                                 ON DUPLICATE KEY UPDATE selesai = VALUES(selesai)');
        foreach ($valid as $kid) $simpan->execute(['j' => $jurnal_id, 'k' => $kid, 's' => in_array((int) $kid, $dipilih, true) ? 1 : 0]);
        $pdo->commit();
        flash('success', 'Data berhasil disimpan. Jurnalmu menunggu verifikasi guru.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log($e->getMessage());
        flash('danger', 'Data gagal disimpan.');
    }
    redirect(url('siswa/kebiasaan.php'));
}

$centang = [];
$verif = null;
if ($jurnal) {
    $st = db()->prepare('SELECT kebiasaan_id FROM detail_jurnal_kebiasaan WHERE jurnal_id = :j AND selesai = 1');
    $st->execute(['j' => $jurnal['id']]);
    $centang = array_map('intval', array_column($st->fetchAll(), 'kebiasaan_id'));
    $st = db()->prepare('SELECT catatan, apresiasi FROM verifikasi_kebiasaan WHERE jurnal_id = :j');
    $st->execute(['j' => $jurnal['id']]);
    $verif = $st->fetch() ?: null;
}

$judul = 'Checklist 7 Kebiasaan';
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <h2><?= e(hari_tgl_indo($hari_ini)) ?></h2>
    <?php if ($jurnal): ?>
        <p>Status jurnal hari ini: <?= pill_status_jurnal($jurnal['status']) ?></p>
        <?php if ($verif && in_array($jurnal['status'], ['disetujui', 'ditolak'], true)): ?>
            <?php if ($verif['apresiasi']): ?><div class="apresiasi"><i class="bi bi-star-fill"></i> <?= e($verif['apresiasi']) ?></div><?php endif; ?>
            <?php if ($verif['catatan']): ?><div class="apresiasi"><i class="bi bi-chat-fill"></i> Catatan guru: <?= e($verif['catatan']) ?></div><?php endif; ?>
        <?php endif; ?>
        <?php if ($jurnal['status'] === 'ditolak'): ?><p class="mt"><small>Perbaiki checklist sesuai catatan guru, lalu kirim ulang.</small></p><?php endif; ?>
    <?php else: ?>
        <p>Centang kebiasaan baik yang sudah kamu lakukan hari ini. Setiap kebiasaan yang disetujui guru bernilai 1 poin!</p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="habit-grid mt">
            <?php foreach ($daftar_kebiasaan as $k): ?>
                <label class="habit">
                    <input type="checkbox" name="kebiasaan[]" value="<?= (int) $k['id'] ?>" <?= in_array((int) $k['id'], $centang, true) ? 'checked' : '' ?> <?= $terkunci ? 'disabled' : '' ?>>
                    <span class="ikon"><?= ikon_kebiasaan((int) $k['id']) ?></span>
                    <span><strong><?= e($k['nama_kebiasaan']) ?></strong><small><?= e($k['deskripsi']) ?></small></span>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="form-grup"><label for="catatan">Catatan (opsional)</label>
            <input type="text" id="catatan" name="catatan" maxlength="255" value="<?= e($jurnal['catatan_siswa'] ?? '') ?>" <?= $terkunci ? 'disabled' : '' ?> placeholder="Ceritakan harimu..."></div>
        <div class="form-grup"><label for="foto_jurnal">Foto Bukti (opsional, maks 1 MB)</label>
            <input type="file" id="foto_jurnal" name="foto_jurnal" accept="image/jpeg,image/png,image/webp,image/gif" <?= $terkunci ? 'disabled' : '' ?>>
            <small>Unggah foto kamu mengerjakan kebiasaan (misal menyusun kasur, beresin istirahat) supaya guru lebih mudah memeriksa.</small></div>
        <?php if (!empty($jurnal['foto_jurnal'])): ?>
            <p class="mt"><a href="<?= e(url('uploads/jurnal/' . $jurnal['foto_jurnal'])) ?>" target="_blank"><img class="soal-gambar" src="<?= e(url('uploads/jurnal/' . $jurnal['foto_jurnal'])) ?>" alt="Foto bukti jurnal" style="max-height:140px"></a></p>
        <?php endif; ?>
        <?php if (!$terkunci): ?>
            <button type="submit" class="btn btn-success"><?= $jurnal ? '<i class="bi bi-floppy-fill"></i> Perbarui Checklist' : '<i class="bi bi-send-fill"></i> Kirim Checklist' ?></button>
        <?php else: ?>
            <div class="flash flash-success">Jurnal hari ini sudah disetujui guru. Terima kasih! <i class="bi bi-stars"></i></div>
        <?php endif; ?>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
