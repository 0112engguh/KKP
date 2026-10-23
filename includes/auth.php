<?php
// includes/auth.php
// Session, helper umum, pengecekan login/role, flash message, dan token CSRF.
// Semua halaman memanggil file ini paling atas.

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/* ---------- Helper umum ---------- */

// Escape output HTML (cegah XSS)
function e($nilai): string
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $tujuan): void
{
    header('Location: ' . $tujuan);
    exit;
}

/* ---------- Flash message ---------- */

// $tipe: success | danger | warning | info
function flash(string $tipe, string $pesan): void
{
    $_SESSION['flash'][] = ['tipe' => $tipe, 'pesan' => $pesan];
}

function ambil_flash(): array
{
    $daftar = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $daftar;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

// Panggil di setiap proses POST
function csrf_cek(): void
{
    $kirim = $_POST['csrf'] ?? '';
    if (!is_string($kirim) || !hash_equals(csrf_token(), $kirim)) {
        http_response_code(403);
        flash('danger', 'Akses ditolak. Sesi formulir tidak valid, silakan coba lagi.');
        redirect(url('index.php'));
    }
}

/* ---------- Login & role ---------- */

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function halaman_beranda(string $role): string
{
    switch ($role) {
        case 'siswa':    return url('siswa/dashboard.php');
        case 'orangtua': return url('orangtua/dashboard.php');
        default:         return url('guru/dashboard.php'); // admin & guru
    }
}

function wajib_login(): array
{
    $u = user();
    if (!$u) {
        flash('warning', 'Silakan login terlebih dahulu.');
        redirect(url('auth/login.php'));
    }
    return $u;
}

// Contoh: wajib_role(['admin','guru'])
function wajib_role(array $diizinkan): array
{
    $u = wajib_login();
    if (!in_array($u['role'], $diizinkan, true)) {
        flash('danger', 'Akses ditolak. Anda tidak berhak membuka halaman tersebut.');
        redirect(halaman_beranda($u['role']));
    }
    return $u;
}

function nama_role(string $role): string
{
    $label = ['admin' => 'Admin', 'guru' => 'Guru', 'siswa' => 'Siswa', 'orangtua' => 'Orang Tua'];
    return $label[$role] ?? $role;
}

// Untuk proses AJAX: kirim JSON lalu berhenti
function kirim_json(array $data, int $kode = 200): void
{
    http_response_code($kode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
