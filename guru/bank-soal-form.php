<?php
// guru/bank-soal-form.php
// Form tambah / ubah soal pilihan ganda (4 opsi A-D), kunci jawaban, pembahasan, dan gambar pendukung.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$id = (int) ($_GET['id'] ?? 0);
$data = ['mapel_id' => 0, 'kategori' => '', 'tingkat' => 'sedang', 'pertanyaan' => '', 'gambar' => null, 'kunci_jawaban' => 'A', 'pembahasan' => ''];
$opsi = ['A' => '', 'B' => '', 'C' => '', 'D' => ''];

if ($id > 0) {
    $st = db()->prepare('SELECT * FROM bank_soal WHERE id = :id');
    $st->execute(['id' => $id]);
    $data = $st->fetch();
    if (!$data) {
        flash('danger', 'Data tidak ditemukan.');
        redirect(url('guru/bank-soal.php'));
    }
    $st = db()->prepare('SELECT label, teks FROM opsi_jawaban WHERE soal_id = :id');
    $st->execute(['id' => $id]);
    foreach ($st->fetchAll() as $o) $opsi[$o['label']] = $o['teks'];
}

// Validasi & simpan gambar upload dengan simpan_gambar() dari includes/fungsi.php.
function simpan_gambar_soal(array $berkas): ?string
{
    if ($berkas['error'] === UPLOAD_ERR_NO_FILE) return null;
    return simpan_gambar(UPLOAD_SOAL_DIR, $berkas);
}

$galat = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $data['mapel_id']      = (int) ($_POST['mapel_id'] ?? 0);
    $data['kategori']      = trim($_POST['kategori'] ?? '');
    $data['tingkat']       = in_array($_POST['tingkat'] ?? '', ['mudah', 'sedang', 'sulit'], true) ? $_POST['tingkat'] : 'sedang';
    $data['pertanyaan']    = trim($_POST['pertanyaan'] ?? '');
    $data['kunci_jawaban'] = $_POST['kunci_jawaban'] ?? 'A';
    $data['pembahasan']    = trim($_POST['pembahasan'] ?? '');
    foreach ($opsi as $label => $_) $opsi[$label] = trim($_POST['opsi'][$label] ?? '');

    if (!skalar('SELECT COUNT(*) FROM mata_pelajaran WHERE id = :m', ['m' => $data['mapel_id']])) $galat[] = 'Mata pelajaran wajib dipilih.';
    if ($data['pertanyaan'] === '') $galat[] = 'Pertanyaan wajib diisi.';
    foreach ($opsi as $label => $teks) if ($teks === '') $galat[] = "Opsi $label wajib diisi.";
    if (!isset($opsi[$data['kunci_jawaban']])) $galat[] = 'Kunci jawaban tidak valid.';

    $gambar_baru = null;
    if (!$galat) {
        try {
            $gambar_baru = simpan_gambar_soal($_FILES['gambar'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
        } catch (RuntimeException $ex) {
            $galat[] = $ex->getMessage();
        }
    }

    if (!$galat) {
        $pdo = db();
        $gambar_lama = $data['gambar'];
        $gambar_akhir = $gambar_lama;
        if ($gambar_baru !== null) $gambar_akhir = $gambar_baru;
        elseif (!empty($_POST['hapus_gambar'])) $gambar_akhir = null;

        try {
            $pdo->beginTransaction();
            $kat = $data['kategori'] !== '' ? $data['kategori'] : null;
            $pemb = $data['pembahasan'] !== '' ? $data['pembahasan'] : null;
            if ($id === 0) {
                $pdo->prepare('INSERT INTO bank_soal (mapel_id, kategori, tingkat, pertanyaan, gambar, kunci_jawaban, pembahasan)
                               VALUES (:m, :kat, :t, :p, :g, :k, :pb)')
                    ->execute(['m' => $data['mapel_id'], 'kat' => $kat, 't' => $data['tingkat'], 'p' => $data['pertanyaan'],
                               'g' => $gambar_akhir, 'k' => $data['kunci_jawaban'], 'pb' => $pemb]);
                $id_baru = (int) $pdo->lastInsertId();
            } else {
                $pdo->prepare('UPDATE bank_soal SET mapel_id = :m, kategori = :kat, tingkat = :t, pertanyaan = :p, gambar = :g,
                               kunci_jawaban = :k, pembahasan = :pb WHERE id = :id')
                    ->execute(['m' => $data['mapel_id'], 'kat' => $kat, 't' => $data['tingkat'], 'p' => $data['pertanyaan'],
                               'g' => $gambar_akhir, 'k' => $data['kunci_jawaban'], 'pb' => $pemb, 'id' => $id]);
                $id_baru = $id;
            }
            $upsert = $pdo->prepare('INSERT INTO opsi_jawaban (soal_id, label, teks) VALUES (:s, :l, :t)
                                     ON DUPLICATE KEY UPDATE teks = VALUES(teks)');
            foreach ($opsi as $label => $teks) $upsert->execute(['s' => $id_baru, 'l' => $label, 't' => $teks]);
            $pdo->commit();

            // Hapus file gambar lama bila diganti / dihapus
            if ($gambar_lama && $gambar_lama !== $gambar_akhir && is_file(UPLOAD_SOAL_DIR . basename($gambar_lama))) {
                @unlink(UPLOAD_SOAL_DIR . basename($gambar_lama));
            }
            flash('success', 'Data berhasil disimpan.');
            redirect(url('guru/bank-soal.php'));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($gambar_baru) @unlink(UPLOAD_SOAL_DIR . $gambar_baru);
            error_log($e->getMessage());
            $galat[] = 'Data gagal disimpan.';
        }
    }
}

$judul = $id ? 'Ubah Soal' : 'Tambah Soal';
$aksi_header = '<a class="btn btn-light" href="' . e(url('guru/bank-soal.php')) . '">&larr; Kembali</a>';
require __DIR__ . '/../includes/header.php';
?>
<?php foreach ($galat as $g): ?><div class="flash flash-danger"><?= e($g) ?></div><?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>
    <div class="form-baris">
        <div class="form-grup"><label for="mapel_id">Mata Pelajaran *</label>
            <select id="mapel_id" name="mapel_id" required>
                <option value="">-- Pilih --</option>
                <?php foreach (semua_mapel() as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= (int) $data['mapel_id'] === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['nama_mapel']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-grup"><label for="kategori">Kategori</label>
            <input type="text" id="kategori" name="kategori" value="<?= e($data['kategori']) ?>" maxlength="60" placeholder="contoh: Pecahan"></div>
        <div class="form-grup"><label for="tingkat">Tingkat Kesulitan</label>
            <select id="tingkat" name="tingkat">
                <?php foreach (['mudah', 'sedang', 'sulit'] as $t): ?>
                    <option value="<?= e($t) ?>" <?= $data['tingkat'] === $t ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option>
                <?php endforeach; ?>
            </select></div>
    </div>

    <div class="form-grup"><label for="pertanyaan">Pertanyaan *</label>
        <textarea id="pertanyaan" name="pertanyaan" required><?= e($data['pertanyaan']) ?></textarea></div>

    <div class="form-grup"><label for="gambar">Gambar Pendukung (opsional, maks 1 MB)</label>
        <?php if ($data['gambar']): ?>
            <img src="<?= e(url('uploads/soal/' . $data['gambar'])) ?>" alt="Gambar soal" class="soal-gambar" style="max-height:140px">
            <label class="cek-baris"><input type="checkbox" name="hapus_gambar" value="1"> Hapus gambar ini</label>
        <?php endif; ?>
        <input type="file" id="gambar" name="gambar" accept="image/jpeg,image/png,image/webp,image/gif"></div>

    <h2>Pilihan Jawaban</h2>
    <?php foreach ($opsi as $label => $teks): ?>
        <div class="form-grup"><label for="opsi<?= e($label) ?>">Opsi <?= e($label) ?> *</label>
            <input type="text" id="opsi<?= e($label) ?>" name="opsi[<?= e($label) ?>]" value="<?= e($teks) ?>" maxlength="500" required></div>
    <?php endforeach; ?>

    <div class="form-grup"><label for="kunci_jawaban">Kunci Jawaban *</label>
        <select id="kunci_jawaban" name="kunci_jawaban">
            <?php foreach (array_keys($opsi) as $label): ?>
                <option value="<?= e($label) ?>" <?= $data['kunci_jawaban'] === $label ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select></div>
    <div class="form-grup"><label for="pembahasan">Pembahasan</label>
        <textarea id="pembahasan" name="pembahasan"><?= e($data['pembahasan']) ?></textarea>
        <small>Ditampilkan kepada siswa setelah ujian selesai.</small></div>

    <button type="submit" class="btn btn-primary">Simpan</button>
    <a class="btn btn-light" href="<?= e(url('guru/bank-soal.php')) ?>">Batal</a>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
