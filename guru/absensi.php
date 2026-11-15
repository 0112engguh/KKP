<?php
// guru/absensi.php
// Input absensi harian: pilih kelas dan tanggal, isi status H/I/S/A tiap siswa, lalu simpan.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
$pengguna_login = wajib_role(['admin', 'guru']);

$kelas_semua = kelas_guru();
if (!$kelas_semua) akhir_kelas();

// Cek tanggal: format benar dan tidak boleh di masa depan
function tanggal_valid(string $t): bool
{
    return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) && $t <= date('Y-m-d');
}

$sumber   = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$kelas_id = (int) ($sumber['kelas_id'] ?? $kelas_semua[0]['id']);
if (!punya_kelas($kelas_semua, $kelas_id)) {
    flash('danger', 'Kelas tidak termasuk penugasan Anda.');
    redirect(url('guru/absensi.php'));
}
$tanggal  = (string) ($sumber['tanggal'] ?? date('Y-m-d'));
if (!tanggal_valid($tanggal)) $tanggal = date('Y-m-d');

$st = db()->prepare("SELECT id, nisn, nama FROM siswa WHERE kelas_id = :k AND status = 'aktif' ORDER BY nama");
$st->execute(['k' => $kelas_id]);
$daftar_siswa = $st->fetchAll();

// ----- Simpan -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $input_status = $_POST['status'] ?? [];
    $pdo = db();
    try {
        if (!skalar('SELECT COUNT(*) FROM kelas WHERE id = :k', ['k' => $kelas_id])) throw new RuntimeException('Kelas tidak ditemukan');
        if (!$daftar_siswa) throw new RuntimeException('Kelas belum memiliki siswa aktif');

        $pdo->beginTransaction();
        $absensi_id = skalar('SELECT id FROM absensi WHERE kelas_id = :k AND tanggal = :t', ['k' => $kelas_id, 't' => $tanggal]);
        if (!$absensi_id) {
            $pdo->prepare('INSERT INTO absensi (kelas_id, tanggal, guru_id) VALUES (:k, :t, :g)')
                ->execute(['k' => $kelas_id, 't' => $tanggal, 'g' => $pengguna_login['profil_id']]);
            $absensi_id = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE absensi SET guru_id = :g WHERE id = :id')->execute(['g' => $pengguna_login['profil_id'], 'id' => $absensi_id]);
        }

        $simpan = $pdo->prepare('INSERT INTO absensi_detail (absensi_id, siswa_id, status) VALUES (:a, :s, :st)
                                 ON DUPLICATE KEY UPDATE status = VALUES(status)');
        $hadir = 0;
        foreach ($daftar_siswa as $s) {
            $kode = $input_status[$s['id']] ?? 'H';
            if (!isset(STATUS_ABSEN[$kode])) $kode = 'H';
            if ($kode === 'H') $hadir++;
            $simpan->execute(['a' => $absensi_id, 's' => $s['id'], 'st' => $kode]);
        }
        $pdo->commit();

        flash('success', 'Data berhasil disimpan. Hadir ' . $hadir . ' dari ' . count($daftar_siswa) . ' siswa ('
            . angka(persen($hadir, count($daftar_siswa))) . '%).');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log($e->getMessage());
        flash('danger', 'Data gagal disimpan.');
    }
    redirect(url('guru/absensi.php?' . http_build_query(['kelas_id' => $kelas_id, 'tanggal' => $tanggal])));
}

// ----- Status yang sudah tersimpan untuk tanggal ini -----
$tersimpan = [];
$st = db()->prepare('SELECT d.siswa_id, d.status FROM absensi_detail d JOIN absensi a ON a.id = d.absensi_id WHERE a.kelas_id = :k AND a.tanggal = :t');
$st->execute(['k' => $kelas_id, 't' => $tanggal]);
foreach ($st->fetchAll() as $r) $tersimpan[$r['siswa_id']] = $r['status'];
$sudah_ada = (bool) $tersimpan;

$judul = 'Input Absensi Kelas';
$aksi_header = '<a class="btn btn-light" href="' . e(url('guru/absensi-rekap.php')) . '"><i class="bi bi-calendar-week-fill"></i> Lihat Rekap</a>';
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <form method="get" class="toolbar">
        <div class="form-grup"><label for="kelas_id">Kelas</label>
            <select name="kelas_id" id="kelas_id">
                <?php foreach ($kelas_semua as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= $kelas_id === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-grup"><label for="tanggal">Tanggal</label>
            <input type="date" name="tanggal" id="tanggal" value="<?= e($tanggal) ?>" max="<?= e(date('Y-m-d')) ?>"></div>
        <button class="btn btn-primary" type="submit">Tampilkan</button>
    </form>
    <?php if ($sudah_ada): ?>
        <div class="flash flash-info">Absensi tanggal <?= e(hari_tgl_indo($tanggal)) ?> sudah pernah diisi. Anda dapat mengubahnya lalu menyimpan ulang.</div>
    <?php endif; ?>
</section>

<?php if (!$daftar_siswa): ?>
    <div class="card kosong">Kelas ini belum memiliki siswa aktif.</div>
<?php else: ?>
<form method="post" class="card" data-absensi-form>
    <?= csrf_field() ?>
    <input type="hidden" name="kelas_id" value="<?= (int) $kelas_id ?>">
    <input type="hidden" name="tanggal" value="<?= e($tanggal) ?>">
    <div class="toolbar">
        <strong><?= e(hari_tgl_indo($tanggal)) ?></strong>
        <button type="button" class="btn btn-light btn-sm" data-set-semua="H">Tandai semua Hadir</button>
    </div>
    <div class="tabel-wrap">
        <table class="tabel">
            <thead><tr><th>No</th><th>NISN</th><th>Nama Siswa</th><th>Status Kehadiran</th></tr></thead>
            <tbody>
            <?php foreach ($daftar_siswa as $i => $s):
                $pilihan = $tersimpan[$s['id']] ?? 'H'; ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($s['nisn']) ?></td>
                    <td><strong><?= e($s['nama']) ?></strong></td>
                    <td>
                        <div class="pilih-absen">
                            <?php foreach (STATUS_ABSEN as $kode => $nama): ?>
                                <label title="<?= e($nama) ?>">
                                    <input type="radio" name="status[<?= (int) $s['id'] ?>]" value="<?= e($kode) ?>" <?= $pilihan === $kode ? 'checked' : '' ?>>
                                    <span class="<?= e(strtolower($kode)) ?>"><?= e($kode) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p><small>H = Hadir, I = Izin, S = Sakit, A = Alpa</small></p>
    <button type="submit" class="btn btn-success"><i class="bi bi-floppy-fill"></i> Simpan Absensi</button>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
