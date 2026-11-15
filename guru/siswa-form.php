<?php
// guru/siswa-form.php
// Form tambah / ubah siswa. Saat siswa baru dibuat, akun login siswa (NISN) dibuat otomatis,
// dan akun orang tua (login memakai NISN anak) dapat dibuat sekaligus.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
wajib_role(['admin', 'guru']);

$id = (int) ($_GET['id'] ?? 0);
$data = ['nisn' => '', 'nama' => '', 'jenis_kelamin' => 'L', 'tanggal_lahir' => '', 'alamat' => '', 'kelas_id' => 0,
         'orang_tua_id' => 0, 'status' => 'aktif', 'user_id' => 0];

$galat = [];
$kelas_semua = kelas_guru();
if (!$kelas_semua) akhir_kelas();

if ($id > 0) {
    $st = db()->prepare('SELECT * FROM siswa WHERE id = :id');
    $st->execute(['id' => $id]);
    $data = $st->fetch();
    if (!$data) {
        flash('danger', 'Data tidak ditemukan.');
        redirect(url('guru/siswa.php'));
    }
    if (!punya_kelas($kelas_semua, (int) $data['kelas_id'])) {
        flash('danger', 'Siswa tidak termasuk kelas yang ditugaskan kepada Anda.');
        redirect(url('guru/siswa.php'));
    }
}

$nama_ortu_baru = $_POST['nama_ortu'] ?? '';
$hp_ortu = $_POST['no_hp'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $data['nisn']          = trim($_POST['nisn'] ?? '');
    $data['nama']          = trim($_POST['nama'] ?? '');
    $data['jenis_kelamin'] = ($_POST['jenis_kelamin'] ?? 'L') === 'P' ? 'P' : 'L';
    $data['tanggal_lahir'] = trim($_POST['tanggal_lahir'] ?? '');
    $data['alamat']        = trim($_POST['alamat'] ?? '');
    $data['kelas_id']      = (int) ($_POST['kelas_id'] ?? 0);
    $data['orang_tua_id']  = (int) ($_POST['orang_tua_id'] ?? 0);
    $data['status']        = ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
    $password              = $_POST['password'] ?? '';
    $password_ortu         = $_POST['password_ortu'] ?? '';
    $nama_ortu_baru        = trim($nama_ortu_baru);

    if (!preg_match('/^\d{10}$/', $data['nisn'])) $galat[] = 'NISN harus 10 digit angka.';
    if ($data['nama'] === '') $galat[] = 'Nama siswa wajib diisi.';
    if ($data['tanggal_lahir'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['tanggal_lahir'])) $galat[] = 'Tanggal lahir tidak valid.';
    if ($data['kelas_id'] <= 0 || !skalar('SELECT COUNT(*) FROM kelas WHERE id = :k', ['k' => $data['kelas_id']])) $galat[] = 'Kelas wajib dipilih.';
    if ($data['kelas_id'] > 0 && !punya_kelas($kelas_semua, $data['kelas_id'])) $galat[] = 'Kelas tidak termasuk penugasan Anda.';
    if ($password !== '' && strlen($password) < 6) $galat[] = 'Password minimal 6 karakter.';

    // NISN harus unik
    if (!$galat) {
        $ada = skalar('SELECT COUNT(*) FROM siswa WHERE nisn = :n AND id <> :id', ['n' => $data['nisn'], 'id' => $id]);
        if ($ada) $galat[] = 'NISN sudah dipakai siswa lain.';
    }

    // Orang tua baru: nama wajib, username (NISN anak) belum dipakai orang tua lain
    $buat_ortu = $data['orang_tua_id'] === 0 && $nama_ortu_baru !== '';
    if (!$galat && $buat_ortu) {
        if (skalar('SELECT COUNT(*) FROM users WHERE role_id = 4 AND username = :u', ['u' => $data['nisn']])) {
            $galat[] = 'Sudah ada akun orang tua dengan NISN tersebut. Pilih orang tua yang sudah ada.';
        }
        if ($password_ortu !== '' && strlen($password_ortu) < 6) $galat[] = 'Password orang tua minimal 6 karakter.';
    }
    if (!$galat && $data['orang_tua_id'] > 0 && !skalar('SELECT COUNT(*) FROM orang_tua WHERE id = :o', ['o' => $data['orang_tua_id']])) {
        $galat[] = 'Orang tua tidak ditemukan.';
    }

    if (!$galat) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $tgl = $data['tanggal_lahir'] !== '' ? $data['tanggal_lahir'] : null;

            if ($buat_ortu) {
                $pdo->prepare('INSERT INTO users (role_id, username, password) VALUES (4, :u, :p)')
                    ->execute(['u' => $data['nisn'], 'p' => password_hash($password_ortu !== '' ? $password_ortu : 'password', PASSWORD_DEFAULT)]);
                $uid_ortu = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO orang_tua (user_id, nama, no_hp) VALUES (:u, :n, :h)')
                    ->execute(['u' => $uid_ortu, 'n' => $nama_ortu_baru, 'h' => $hp_ortu !== '' ? $hp_ortu : null]);
                $data['orang_tua_id'] = (int) $pdo->lastInsertId();
            }
            $ortu = $data['orang_tua_id'] > 0 ? $data['orang_tua_id'] : null;

            if ($id === 0) {
                $pdo->prepare('INSERT INTO users (role_id, username, password) VALUES (3, :u, :p)')
                    ->execute(['u' => $data['nisn'], 'p' => password_hash($password !== '' ? $password : 'password', PASSWORD_DEFAULT)]);
                $uid = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO siswa (user_id, nisn, nama, jenis_kelamin, tanggal_lahir, alamat, kelas_id, orang_tua_id, status)
                               VALUES (:u, :nisn, :nama, :jk, :lahir, :alamat, :kelas, :ortu, :status)')
                    ->execute(['u' => $uid, 'nisn' => $data['nisn'], 'nama' => $data['nama'], 'jk' => $data['jenis_kelamin'],
                               'lahir' => $tgl, 'alamat' => $data['alamat'], 'kelas' => $data['kelas_id'], 'ortu' => $ortu, 'status' => $data['status']]);
            } else {
                $pdo->prepare('UPDATE siswa SET nisn = :nisn, nama = :nama, jenis_kelamin = :jk, tanggal_lahir = :lahir, alamat = :alamat,
                               kelas_id = :kelas, orang_tua_id = :ortu, status = :status WHERE id = :id')
                    ->execute(['nisn' => $data['nisn'], 'nama' => $data['nama'], 'jk' => $data['jenis_kelamin'], 'lahir' => $tgl,
                               'alamat' => $data['alamat'], 'kelas' => $data['kelas_id'], 'ortu' => $ortu, 'status' => $data['status'], 'id' => $id]);
                // username login siswa = NISN, selalu disamakan
                $pdo->prepare('UPDATE users SET username = :u WHERE id = :id')->execute(['u' => $data['nisn'], 'id' => $data['user_id']]);
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET password = :p WHERE id = :id')
                        ->execute(['p' => password_hash($password, PASSWORD_DEFAULT), 'id' => $data['user_id']]);
                }
            }
            $pdo->commit();
            flash('success', 'Data berhasil disimpan.');
            redirect(url('guru/siswa.php'));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($e->getMessage());
            $galat[] = 'Data gagal disimpan. Periksa kembali isian Anda.';
        }
    }
}

$daftar_ortu = db()->query('SELECT o.id, o.nama, u.username FROM orang_tua o JOIN users u ON u.id = o.user_id ORDER BY o.nama')->fetchAll();

$judul = $id ? 'Ubah Data Siswa' : 'Tambah Siswa';
$aksi_header = '<a class="btn btn-light" href="' . e(url('guru/siswa.php')) . '">&larr; Kembali</a>';
require __DIR__ . '/../includes/header.php';
?>
<?php foreach ($galat as $g): ?><div class="flash flash-danger"><?= e($g) ?></div><?php endforeach; ?>

<form method="post" class="card" autocomplete="off">
    <?= csrf_field() ?>
    <h2>Data Siswa</h2>
    <div class="form-baris">
        <div class="form-grup"><label for="nisn">NISN *</label>
            <input type="text" id="nisn" name="nisn" value="<?= e($data['nisn']) ?>" maxlength="10" inputmode="numeric" required>
            <small>10 digit. Dipakai sebagai username login siswa.</small></div>
        <div class="form-grup"><label for="nama">Nama Lengkap *</label>
            <input type="text" id="nama" name="nama" value="<?= e($data['nama']) ?>" maxlength="100" required></div>
        <div class="form-grup"><label for="jenis_kelamin">Jenis Kelamin *</label>
            <select id="jenis_kelamin" name="jenis_kelamin">
                <option value="L" <?= $data['jenis_kelamin'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                <option value="P" <?= $data['jenis_kelamin'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
            </select></div>
        <div class="form-grup"><label for="tanggal_lahir">Tanggal Lahir</label>
            <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?= e($data['tanggal_lahir']) ?>"></div>
        <div class="form-grup"><label for="kelas_id">Kelas *</label>
            <select id="kelas_id" name="kelas_id" required>
                <option value="">-- Pilih kelas --</option>
                <?php foreach ($kelas_semua as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= (int) $data['kelas_id'] === (int) $k['id'] ? 'selected' : '' ?>><?= e($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-grup"><label for="status">Status</label>
            <select id="status" name="status">
                <option value="aktif" <?= $data['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="nonaktif" <?= $data['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
            </select></div>
    </div>
    <div class="form-grup"><label for="alamat">Alamat</label>
        <input type="text" id="alamat" name="alamat" value="<?= e($data['alamat']) ?>" maxlength="255"></div>
    <div class="form-grup"><label for="password">Password Siswa</label>
        <input type="password" id="password" name="password" autocomplete="new-password">
        <small><?= $id ? 'Kosongkan bila tidak ingin mengubah password.' : 'Kosongkan untuk memakai password awal: password' ?></small></div>

    <h2 class="mt">Orang Tua / Wali</h2>
    <div class="form-grup"><label for="orang_tua_id">Pilih Orang Tua</label>
        <select id="orang_tua_id" name="orang_tua_id">
            <option value="0">-- Buat akun orang tua baru --</option>
            <?php foreach ($daftar_ortu as $o): ?>
                <option value="<?= (int) $o['id'] ?>" <?= (int) $data['orang_tua_id'] === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['nama']) ?> (login: <?= e($o['username']) ?>)</option>
            <?php endforeach; ?>
        </select>
        <small>Pilih orang tua yang sudah ada bila siswa punya saudara di sekolah ini.</small></div>
    <div class="form-baris">
        <div class="form-grup"><label for="nama_ortu">Nama Orang Tua Baru</label>
            <input type="text" id="nama_ortu" name="nama_ortu" value="<?= e($nama_ortu_baru) ?>" maxlength="100"></div>
        <div class="form-grup"><label for="no_hp">No. HP</label>
            <input type="text" id="no_hp" name="no_hp" value="<?= e($hp_ortu) ?>" maxlength="20"></div>
        <div class="form-grup"><label for="password_ortu">Password Orang Tua Baru</label>
            <input type="password" id="password_ortu" name="password_ortu" autocomplete="new-password">
            <small>Kosongkan untuk password awal: password. Login orang tua memakai NISN anak.</small></div>
    </div>

    <button type="submit" class="btn btn-primary">Simpan</button>
    <a class="btn btn-light" href="<?= e(url('guru/siswa.php')) ?>">Batal</a>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
