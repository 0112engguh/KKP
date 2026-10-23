<?php
// config/database.php
// Koneksi database memakai PDO. Panggil db() di mana saja untuk mendapatkan koneksi.

require_once __DIR__ . '/constants.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false, // prepared statement asli
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00'",
                ]
            );
        } catch (PDOException $e) {
            error_log('Koneksi database gagal: ' . $e->getMessage());
            http_response_code(500);
            exit('Koneksi database gagal. Pastikan MySQL di XAMPP sudah Start dan database kkp_sd sudah diimpor.');
        }
    }

    return $pdo;
}
