<?php
// guru/guru-form.php
// Form tambah / ubah guru (khusus Admin). Username login = NIP.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/fungsi.php';
$pengguna_login = wajib_role(['admin']);

$id = (int) ($_GET['id'] ?? 0);
$data = ['nip' => '', 'nama' => '', 'email' => '', 'role_id' => 2, 'user_id' => 0];

if ($id > 0) {
    $st = db()->prepare('SELECT g.*, u.role_id FROM guru g JOIN users u ON u.id = g.user_id WHERE g.id = :id');
    $st->execute(['id' => $id]);
    $data = $st->fetch();
    if (!$data) {
        flash('danger', 'Data tidak ditemukan.');
        redirect(url('guru/guru.php'));
    }
}
$diri_sendiri = $id > 0 && (int) $data['user_id'] === (int) $pengguna_login['id'];

$galat = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_cek();
    $data['nip']   = trim($_POST['nip'] ?? '');
    $data['nama']  = trim($_POST['nama'] ?? '');
    $data['email'] = trim($_POST['email'] ?? '');
    $role_baru     = (int) ($_POST['role_id'] ?? 2);
    $password      = $_POST['password'] ?? '';
    if ($diri_sendiri) $role_baru = (int) $data['role_id']; // tidak boleh mengubah role sendiri
    $data['role_id'] = in_array($role_baru, [1, 2], true) ? $role_baru : 2;

    if (!preg_match('/^\d{8,20}$/', $data['nip'])) $galat[] = 'NIP harus berupa angka (8-20 digit).';
    if ($data['nama'] === '') $galat[] = 'Nama wajib diisi.';
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $galat[] = 'Format email tidak valid.';
    if ($id === 0 && strlen($password) < 6) $galat[] = 'Password wajib diisi (minimal 6 karakter).';
    if ($id > 0 && $password !== '' && strlen($password) < 6) $galat[] = 'Password minimal 6 karakter.';
    if (!$galat && skalar('SELECT COUNT(*) FROM guru WHERE nip = :n AND id <> :id', ['n' => $data['nip'], 'id' => $id])) {
        $galat[] = 'NIP sudah dipakai guru lain.';
    }

    if (!$galat) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $email = $data['email'] !== '' ? $data['email'] : null;
            if ($id === 0) {
                $pdo->prepare('INSERT INTO users (role_id, username, password) VALUES (:r, :u, :p)')
                    ->execute(['r' => $data['role_id'], 'u' => $data['nip'], 'p' => password_hash($password, PASSWORD_DEFAULT)]);
                $uid = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO guru (user_id, nip, nama, email) VALUES (:u, :nip, :nama, :email)')
                    ->execute(['u' => $uid, 'nip' => $data['nip'], 'nama' => $data['nama'], 'email' => $email]);
            } else {
                $pdo->prepare('UPDATE guru SET nip = :nip, nama = :nama, email = :email WHERE id = :id')
                    ->execute(['nip' => $data['nip'], 'nama' => $data['nama'], 'email' => $email, 'id' => $id]);
                $pdo->prepare('UPDATE users SET username = :u, role_id = :r WHERE id = :id')
                    ->execute(['u' => $data['nip'], 'r' => $data['role_id'], 'id' => $data['user_id']]);
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET password = :p WHERE id = :id')
                        ->execute(['p' => password_hash($password, PASSWORD_DEFAULT), 'id' => $data['user_id']]);
                }
            }
            $pdo->commit();
            flash('success', 'Data berhasil disimpan.');
            redirect(url('guru/guru.php'));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($e->getMessage());
            $galat[] = 'Data gagal disimpan. Periksa kembali isian Anda.';
        }
    }
}

$judul = $id ? 'Ubah Data Guru' : 'Tambah Guru';
$aksi_header = '<a class="btn btn-light" href="' . e(url('guru/guru.php')) . '">&larr; Kembali</a>';
require __DIR__ . '/../includes/header.php';
?>
<?php foreach ($galat as $g): ?><div class="flash flash-danger"><?= e($g) ?></div><?php endforeach; ?>

<form method="post" class="card" autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-baris">
        <div class="form-grup"><label for="nip">NIP *</label>
            <input type="text" id="nip" name="nip" value="<?= e($data['nip']) ?>" maxlength="20" inputmode="numeric" required>
            <small>Dipakai sebagai username login.</small></div>
        <div class="form-grup"><label for="nama">Nama Lengkap *</label>
            <input type="text" id="nama" name="nama" value="<?= e($data['nama']) ?>" maxlength="100" required></div>
        <div class="form-grup"><label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($data['email']) ?>" maxlength="100"></div>
        <div class="form-grup"><label for="role_id">Role</label>
            <select id="role_id" name="role_id" <?= $diri_sendiri ? 'disabled' : '' ?>>
                <option value="2" <?= (int) $data['role_id'] === 2 ? 'selected' : '' ?>>Guru</option>
                <option value="1" <?= (int) $data['role_id'] === 1 ? 'selected' : '' ?>>Admin</option>
            </select>
            <?php if ($diri_sendiri): ?><small>Role akun yang sedang dipakai tidak dapat diubah.</small><?php endif; ?></div>
    </div>
    <div class="form-grup"><label for="password">Password <?= $id ? '' : '*' ?></label>
        <input type="password" id="password" name="password" autocomplete="new-password" <?= $id ? '' : 'required' ?>>
        <?php if ($id): ?><small>Kosongkan bila tidak ingin mengubah password.</small><?php endif; ?></div>
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a class="btn btn-light" href="<?= e(url('guru/guru.php')) ?>">Batal</a>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
