<?php
// auth/proses-login.php
// Memproses form login: cek peran, username, password (password_verify), lalu buat session.

require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('auth/login.php'));
}
csrf_cek();

$peran    = $_POST['peran'] ?? '';
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Peran di form -> id role di tabel roles (1=admin, 2=guru, 3=siswa, 4=orangtua)
$peta_role = ['guru' => [1, 2], 'siswa' => [3], 'orangtua' => [4]];

function login_gagal(string $peran, string $username, string $pesan = 'Login gagal. Username atau password salah.'): void
{
    $_SESSION['login_peran'] = $peran;
    $_SESSION['login_username'] = $username;
    flash('danger', $pesan);
    redirect(url('auth/login.php'));
}

if (!isset($peta_role[$peran]) || $username === '' || $password === '') {
    login_gagal('guru', $username, 'Login gagal. Lengkapi peran, username, dan password.');
}

$id_role = implode(',', $peta_role[$peran]); // angka tetap dari array di atas, aman
$st = db()->prepare("SELECT u.id, u.password, r.nama_role
                     FROM users u JOIN roles r ON r.id = u.role_id
                     WHERE u.username = :username AND u.role_id IN ($id_role) LIMIT 1");
$st->execute(['username' => $username]);
$akun = $st->fetch();

if (!$akun || !password_verify($password, $akun['password'])) {
    login_gagal($peran, $username);
}

// Ambil profil sesuai role
$role = $akun['nama_role'];
if ($role === 'siswa') {
    $st = db()->prepare('SELECT id, nama, status FROM siswa WHERE user_id = :u');
} elseif ($role === 'orangtua') {
    $st = db()->prepare("SELECT id, nama, 'aktif' AS status FROM orang_tua WHERE user_id = :u");
} else {
    $st = db()->prepare("SELECT id, nama, 'aktif' AS status FROM guru WHERE user_id = :u");
}
$st->execute(['u' => $akun['id']]);
$profil = $st->fetch();

if (!$profil) {
    login_gagal($peran, $username, 'Data akun tidak ditemukan. Hubungi admin sekolah.');
}
if ($profil['status'] !== 'aktif') {
    login_gagal($peran, $username, 'Akun siswa tidak aktif. Hubungi wali kelas.');
}

session_regenerate_id(true); // cegah session fixation
$_SESSION['user'] = [
    'id'        => (int) $akun['id'],
    'role'      => $role,
    'username'  => $username,
    'nama'      => $profil['nama'],
    'profil_id' => (int) $profil['id'],
];

flash('success', 'Selamat datang, ' . $profil['nama'] . '!');
redirect(halaman_beranda($role));
