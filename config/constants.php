<?php
// config/constants.php
// Pengaturan umum aplikasi. Ubah DB_USER / DB_PASS bila MySQL Anda memakai password.

date_default_timezone_set('Asia/Jakarta');

define('APP_NAME', 'SIAKAD SD & Monitoring 7 Kebiasaan');
define('APP_SEKOLAH', 'SD Negeri Kalibaru 6');

// Pengaturan database (default XAMPP: user root, password kosong)
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'kkp_sd');
define('DB_USER', 'root');
define('DB_PASS', '');

// Folder upload gambar soal
define('UPLOAD_SOAL_DIR', dirname(__DIR__) . '/uploads/soal/');
define('UPLOAD_JURNAL_DIR', dirname(__DIR__) . '/uploads/jurnal/');
define('UPLOAD_MAKS_BYTE', 1024 * 1024); // 1 MB

// Alamat dasar aplikasi, dihitung otomatis dari lokasi folder project.
// Contoh: project di C:\xampp\htdocs\kkp-sd  ->  BASE_URL = /kkp-sd
$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
$proyek  = realpath(dirname(__DIR__)) ?: '';
$base    = '';
if ($docRoot !== '' && strpos($proyek, $docRoot) === 0) {
    $base = str_replace('\\', '/', substr($proyek, strlen($docRoot)));
}
define('BASE_URL', rtrim($base, '/'));

// Jangan tampilkan error PHP mentah ke pengguna, cukup dicatat di log.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
