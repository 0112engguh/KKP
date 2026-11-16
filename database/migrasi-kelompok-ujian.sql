-- ============================================================
-- migrasi-kelompok-ujian.sql
-- Menambah fitur Bank Soal berbasis Parent-Child:
--   Parent : kelompok_ujian   (kelompok soal per ujian)
--   Child  : kelompok_ujian_soal (daftar soal dalam kelompok)
-- Satu soal bisa dipakai beberapa kelompok (many-to-many, tanpa duplikat).
-- Jalankan lewat phpMyAdmin (tab Import) atau CLI mysql.
-- ============================================================
USE kkp_sd;

CREATE TABLE IF NOT EXISTS kelompok_ujian (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_ujian    VARCHAR(150) NOT NULL,
    tahun_ajaran  VARCHAR(9) NOT NULL,          -- contoh: 2026/2027
    semester      ENUM('ganjil','genap') NOT NULL,
    kelas_id      INT UNSIGNED NOT NULL,
    status        ENUM('draft','aktif') NOT NULL DEFAULT 'draft',
    dibuat_pada   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_kelompok_tahun (tahun_ajaran),
    CONSTRAINT fk_kelompok_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kelompok_ujian_soal (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kelompok_id INT UNSIGNED NOT NULL,
    soal_id     INT UNSIGNED NOT NULL,
    urutan      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    bobot       DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    UNIQUE KEY uq_kelompok_soal (kelompok_id, soal_id),
    CONSTRAINT fk_ks_kelompok FOREIGN KEY (kelompok_id) REFERENCES kelompok_ujian(id) ON DELETE CASCADE,
    CONSTRAINT fk_ks_soal     FOREIGN KEY (soal_id)     REFERENCES bank_soal(id)
) ENGINE=InnoDB;
