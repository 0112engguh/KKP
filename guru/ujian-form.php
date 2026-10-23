<?php
// guru/ujian-form.php
// Form buat / ubah ujian TKA: nama, mapel, kelas, durasi, jadwal, status, dan pilihan soal dari bank soal.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$id = (int) ($_GET['id'] ?? 0);
$data = ['nama_ujian' => '', 'mapel_id' => 0, 'kelas_id' => 0, 'durasi_menit' => 30,
         'waktu_mulai' => date('Y-m-d H:i:s'), 'waktu_selesai' => date('Y-m-d H:i:s', strtotime('+7 days')), 'status' => 'draft'];
$soal_dipilih = [];
$sudah_ada_hasil = false;

if ($id > 0) {
    $st = db()->prepare('SELECT * FROM ujian WHERE id = :id');
    $st->execute(['id' => $id]);
    $data = $st->fetch();
    if (!$data) {
        flash('danger', 'Data tidak ditemukan.');
        redirect(url('guru/ujian.php'));
    }
    $st = db()->prepare('SELECT soal_id FROM ujian_soal WHERE ujian_id = :u');
    $st->execute(['u' => $id]);
    $soal_dipilih = array_map('intval', array_column($st->fetchAll(), 'soal_id'));
    $sudah_ada_hasil = skalar('SELECT COUNT(*) FROM hasil_ujian WHERE ujian_id = :u', ['u' => $id]) > 0;
}

function ke_datetime(string $input): ?string
{
    $t = str_replace('T', ' ', trim($input));
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $t)) $t .= ':00';
    return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $t) && strtotime($t) ? $t : null;
}

$galat = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $data['nama_ujian']   = trim($_POST['nama_ujian'] ?? '');
    $data['mapel_id']     = (int) ($_POST['mapel_id'] ?? 0);
    $data['kelas_id']     = (int) ($_POST['kelas_id'] ?? 0);
    $data['durasi_menit'] = (int) ($_POST['durasi_menit'] ?? 0);
    $data['status']       = ($_POST['status'] ?? 'draft') === 'aktif' ? 'aktif' : 'draft';
    $mulai   = ke_datetime($_POST['waktu_mulai'] ?? '');
    $selesai = ke_datetime($_POST['waktu_selesai'] ?? '');
    if ($mulai) $data['waktu_mulai'] = $mulai;
    if ($selesai) $data['waktu_selesai'] = $selesai;
    if (!$sudah_ada_hasil) $soal_dipilih = array_values(array_unique(array_map('intval', $_POST['soal'] ?? [])));

    if ($data['nama_ujian'] === '') $galat[] = 'Nama ujian wajib diisi.';
    if (!skalar('SELECT COUNT(*) FROM mata_pelajaran WHERE id = :m', ['m' => $data['mapel_id']])) $galat[] = 'Mata pelajaran wajib dipilih.';
    if (!skalar('SELECT COUNT(*) FROM kelas WHERE id = :k', ['k' => $data['kelas_id']])) $galat[] = 'Kelas wajib dipilih.';
    if ($data['durasi_menit'] < 1 || $data['durasi_menit'] > 300) $galat[] = 'Durasi harus antara 1 sampai 300 menit.';
    if (!$mulai || !$selesai) $galat[] = 'Jadwal mulai dan selesai wajib diisi.';
    elseif ($selesai <= $mulai) $galat[] = 'Jadwal selesai harus setelah jadwal mulai.';
    if (!$soal_dipilih) $galat[] = 'Pilih minimal satu soal.';

    // Semua soal terpilih harus dari mata pelajaran yang sama
    if (!$galat && !$sudah_ada_hasil) {
        $in = implode(',', array_fill(0, count($soal_dipilih), '?'));
        $st = db()->prepare("SELECT COUNT(*) FROM bank_soal WHERE mapel_id = ? AND id IN ($in)");
        $st->execute(array_merge([$data['mapel_id']], $soal_dipilih));
        if ((int) $st->fetchColumn() !== count($soal_dipilih)) $galat[] = 'Soal yang dipilih harus berasal dari mata pelajaran ujian.';
    }

    if (!$galat) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $param = ['n' => $data['nama_ujian'], 'm' => $data['mapel_id'], 'k' => $data['kelas_id'], 'd' => $data['durasi_menit'],
                      'mu' => $data['waktu_mulai'], 'se' => $data['waktu_selesai'], 's' => $data['status']];
            if ($id === 0) {
                $pdo->prepare('INSERT INTO ujian (nama_ujian, mapel_id, kelas_id, durasi_menit, waktu_mulai, waktu_selesai, status)
                               VALUES (:n, :m, :k, :d, :mu, :se, :s)')->execute($param);
                $uid = (int) $pdo->lastInsertId();
            } else {
                $pdo->prepare('UPDATE ujian SET nama_ujian = :n, mapel_id = :m, kelas_id = :k, durasi_menit = :d, waktu_mulai = :mu,
                               waktu_selesai = :se, status = :s WHERE id = :id')->execute($param + ['id' => $id]);
                $uid = $id;
            }
            if (!$sudah_ada_hasil) {
                $pdo->prepare('DELETE FROM ujian_soal WHERE ujian_id = :u')->execute(['u' => $uid]);
                $tambah = $pdo->prepare('INSERT INTO ujian_soal (ujian_id, soal_id, urutan) VALUES (:u, :s, :o)');
                foreach ($soal_dipilih as $n => $sid) $tambah->execute(['u' => $uid, 's' => $sid, 'o' => $n + 1]);
            }
            $pdo->commit();
            flash('success', 'Data berhasil disimpan.');
            redirect(url('guru/ujian.php'));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($e->getMessage());
            $galat[] = 'Data gagal disimpan.';
        }
    }
}

$semua_soal = db()->query('SELECT id, mapel_id, pertanyaan, tingkat FROM bank_soal ORDER BY mapel_id, id')->fetchAll();

$judul = $id ? 'Ubah Ujian' : 'Buat Ujian';
$aksi_header = '<a class="btn btn-light" href="' . e(url('guru/ujian.php')) . '">&larr; Kembali</a>';
require __DIR__ . '/../includes/header.php';
?>
<?php foreach ($galat as $g): ?><div class="flash flash-danger"><?= e($g) ?></div><?php endforeach; ?>

<form method="post" class="card">
    <?= csrf_field() ?>
    <div class="form-grup"><label for="nama_ujian">Nama Ujian *</label>
        <input type="text" id="nama_ujian" name="nama_ujian" value="<?= e($data['nama_ujian']) ?>" maxlength="150" required></div>
    <div class="form-baris">
        <div class="form-grup"><label for="mapel_id">Mata Pelajaran *</label>
            <select id="mapel_id" name="mapel_id" required>
                <option value="">-- Pilih --</option>
                <?php foreach (semua_mapel() as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= (int) $data['mapel_id'] === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['nama_mapel']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-grup"><label for="kelas_id">Kelas *</label>
            <select id="kelas_id" name="kelas_id" required>
                <option value="">-- Pilih --</option>
                <?php foreach (semua_kelas() as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= (int) $data['kelas_id'] === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-grup"><label for="durasi_menit">Durasi (menit) *</label>
            <input type="number" id="durasi_menit" name="durasi_menit" min="1" max="300" value="<?= (int) $data['durasi_menit'] ?>" required></div>
        <div class="form-grup"><label for="status">Status</label>
            <select id="status" name="status">
                <option value="draft" <?= $data['status'] === 'draft' ? 'selected' : '' ?>>Draft (belum tampil di siswa)</option>
                <option value="aktif" <?= $data['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
            </select></div>
        <div class="form-grup"><label for="waktu_mulai">Jadwal Mulai *</label>
            <input type="datetime-local" id="waktu_mulai" name="waktu_mulai" value="<?= e(date('Y-m-d\TH:i', strtotime($data['waktu_mulai']))) ?>" required></div>
        <div class="form-grup"><label for="waktu_selesai">Jadwal Selesai *</label>
            <input type="datetime-local" id="waktu_selesai" name="waktu_selesai" value="<?= e(date('Y-m-d\TH:i', strtotime($data['waktu_selesai']))) ?>" required></div>
    </div>

    <h2>Pilih Soal</h2>
    <?php if ($sudah_ada_hasil): ?>
        <div class="flash flash-warning">Ujian ini sudah dikerjakan siswa, sehingga daftar soalnya tidak dapat diubah. Terpilih <?= count($soal_dipilih) ?> soal.</div>
    <?php else: ?>
        <p><small>Daftar soal mengikuti mata pelajaran yang dipilih di atas.</small></p>
        <div class="pilih-soal" id="daftarSoal">
            <?php foreach ($semua_soal as $s): ?>
                <label class="cek-baris" data-mapel="<?= (int) $s['mapel_id'] ?>">
                    <input type="checkbox" name="soal[]" value="<?= (int) $s['id'] ?>" <?= in_array((int) $s['id'], $soal_dipilih, true) ? 'checked' : '' ?>>
                    <span>#<?= (int) $s['id'] ?> <?= e(mb_strimwidth($s['pertanyaan'], 0, 100, '...')) ?> <?= pill(ucfirst($s['tingkat']), ['mudah' => 'hijau', 'sedang' => 'kuning', 'sulit' => 'merah'][$s['tingkat']]) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <script>
            // Tampilkan hanya soal dari mata pelajaran yang dipilih
            (function () {
                var pilih = document.getElementById('mapel_id');
                function saring() {
                    document.querySelectorAll('#daftarSoal [data-mapel]').forEach(function (baris) {
                        var cocok = baris.getAttribute('data-mapel') === pilih.value;
                        baris.style.display = cocok ? '' : 'none';
                        if (!cocok) baris.querySelector('input').checked = false;
                    });
                }
                pilih.addEventListener('change', saring);
                saring();
            })();
        </script>
    <?php endif; ?>

    <p class="mt"><button type="submit" class="btn btn-primary">Simpan</button>
    <a class="btn btn-light" href="<?= e(url('guru/ujian.php')) ?>">Batal</a></p>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
