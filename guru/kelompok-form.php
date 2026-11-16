<?php
// guru/kelompok-form.php
// Form tambah / ubah kelompok ujian (Parent Bank Soal): nama, tahun ajaran,
// semester, kelas, dan status. Soal diatur pada halaman detail kelompok.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$kelas_semua = kelas_guru();
if (!$kelas_semua) akhir_kelas();

// Default tahun ajaran: mulai Juli -> 2026/2027, sebelum Juli -> 2025/2026
$sekarang = (int) date('n') >= 7 ? (int) date('Y') : (int) date('Y') - 1;
$tahun_default = $sekarang . '/' . ($sekarang + 1);

$data = ['nama_ujian' => '', 'tahun_ajaran' => $tahun_default, 'semester' => 'ganjil',
         'kelas_id' => 0, 'status' => 'draft'];
$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    $st = db()->prepare('SELECT * FROM kelompok_ujian WHERE id = :id');
    $st->execute(['id' => $id]);
    $data = $st->fetch();
    if (!$data) {
        flash('danger', 'Data tidak ditemukan.');
        redirect(url('guru/bank-soal.php'));
    }
    if (!punya_kelas($kelas_semua, (int) $data['kelas_id'])) {
        flash('danger', 'Kelompok ujian tidak termasuk kelas yang ditugaskan kepada Anda.');
        redirect(url('guru/bank-soal.php'));
    }
}

$galat = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $data['nama_ujian']   = trim($_POST['nama_ujian'] ?? '');
    $data['tahun_ajaran'] = trim($_POST['tahun_ajaran'] ?? '');
    $data['semester']     = in_array($_POST['semester'] ?? '', ['ganjil', 'genap'], true) ? $_POST['semester'] : 'ganjil';
    $data['kelas_id']     = (int) ($_POST['kelas_id'] ?? 0);
    $data['status']       = ($_POST['status'] ?? 'draft') === 'aktif' ? 'aktif' : 'draft';

    if ($data['nama_ujian'] === '') $galat[] = 'Nama ujian wajib diisi.';
    if (!preg_match('/^(\d{4})\/(\d{4})$/', $data['tahun_ajaran'], $m) || (int) $m[2] !== (int) $m[1] + 1) {
        $galat[] = 'Tahun ajaran harus format 2026/2027.';
    }
    if (!skalar('SELECT COUNT(*) FROM kelas WHERE id = :k', ['k' => $data['kelas_id']])) $galat[] = 'Kelas wajib dipilih.';
    elseif (!punya_kelas($kelas_semua, $data['kelas_id'])) $galat[] = 'Kelas tidak termasuk penugasan Anda.';

    if (!$galat) {
        try {
            if ($id === 0) {
                db()->prepare('INSERT INTO kelompok_ujian (nama_ujian, tahun_ajaran, semester, kelas_id, status)
                               VALUES (:n, :t, :s, :k, :st)')
                    ->execute(['n' => $data['nama_ujian'], 't' => $data['tahun_ajaran'], 's' => $data['semester'],
                               'k' => $data['kelas_id'], 'st' => $data['status']]);
                $id_baru = (int) db()->lastInsertId();
                flash('success', 'Kelompok ujian dibuat. Silakan tambahkan soal.');
                redirect(url('guru/bank-soal-detail.php?id=' . $id_baru));
            } else {
                db()->prepare('UPDATE kelompok_ujian SET nama_ujian = :n, tahun_ajaran = :t, semester = :s,
                               kelas_id = :k, status = :st WHERE id = :id')
                    ->execute(['n' => $data['nama_ujian'], 't' => $data['tahun_ajaran'], 's' => $data['semester'],
                               'k' => $data['kelas_id'], 'st' => $data['status'], 'id' => $id]);
                flash('success', 'Data berhasil disimpan.');
                redirect(url('guru/bank-soal.php'));
            }
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $galat[] = 'Data gagal disimpan.';
        }
    }
}

$judul = $id ? 'Ubah Kelompok Ujian' : 'Tambah Kelompok Ujian';
$aksi_header = '<a class="btn btn-light" href="' . e(url('guru/bank-soal.php')) . '">&larr; Kembali</a>';
require __DIR__ . '/../includes/header.php';
?>
<?php foreach ($galat as $g): ?><div class="flash flash-danger"><?= e($g) ?></div><?php endforeach; ?>

<form method="post" class="card">
    <?= csrf_field() ?>
    <div class="form-grup"><label for="nama_ujian">Nama Ujian *</label>
        <input type="text" id="nama_ujian" name="nama_ujian" value="<?= e($data['nama_ujian']) ?>"
               maxlength="150" required placeholder="contoh: Paket Soal PAS Matematika"></div>
    <div class="form-baris">
        <div class="form-grup"><label for="tahun_ajaran">Tahun Ajaran *</label>
            <input type="text" id="tahun_ajaran" name="tahun_ajaran" value="<?= e($data['tahun_ajaran']) ?>"
                   maxlength="9" pattern="\d{4}/\d{4}" required placeholder="2026/2027"></div>
        <div class="form-grup"><label for="semester">Semester *</label>
            <select id="semester" name="semester">
                <option value="ganjil" <?= $data['semester'] === 'ganjil' ? 'selected' : '' ?>>Ganjil (Jul-Des)</option>
                <option value="genap" <?= $data['semester'] === 'genap' ? 'selected' : '' ?>>Genap (Jan-Jun)</option>
            </select></div>
        <div class="form-grup"><label for="kelas_id">Kelas *</label>
            <select id="kelas_id" name="kelas_id" required>
                <option value="">-- Pilih --</option>
                <?php foreach ($kelas_semua as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= (int) $data['kelas_id'] === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-grup"><label for="status">Status</label>
            <select id="status" name="status">
                <option value="draft" <?= $data['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="aktif" <?= $data['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
            </select></div>
    </div>

    <button type="submit" class="btn btn-primary">Simpan</button>
    <a class="btn btn-light" href="<?= e(url('guru/bank-soal.php')) ?>">Batal</a>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
