-- =========================================================
--  Registrasi Acara Lomba - skema database
--  Import sekali:  mysql -uroot < database.sql
--  Akun awal: admin / admin123  (SEGERA ganti lewat menu Ganti Password)
-- =========================================================

CREATE DATABASE IF NOT EXISTS regis_acara CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE regis_acara;

-- Petugas acara. Peran 'admin' boleh mengelola sesi, petugas, dan membatalkan
-- kehadiran; 'petugas' hanya memeriksa pendaftaran dan menandai hadir.
CREATE TABLE IF NOT EXISTS petugas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    peran ENUM('admin','petugas') NOT NULL DEFAULT 'petugas',
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Sesi berlaku untuk setiap hari acara, ditentukan dari jam. Saat petugas
-- menekan Hadir, sesi yang sedang berjalan dipilih otomatis (bisa diganti).
CREATE TABLE IF NOT EXISTS sesi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(50) NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    aktif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS peserta (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(20) NULL UNIQUE,
    -- Token acak di QR peserta; kode berurutan bisa ditebak, token tidak.
    token CHAR(32) NULL UNIQUE,
    nama VARCHAR(100) NOT NULL,
    umur TINYINT UNSIGNED NOT NULL,
    no_wa VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL,
    domisili VARCHAR(100) NOT NULL,
    jenis_lomba ENUM('challenge','free_play') NOT NULL,
    status ENUM('terdaftar','hadir') NOT NULL DEFAULT 'terdaftar',
    dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    hadir_pada DATETIME NULL,
    sesi_id INT UNSIGNED NULL,
    hadir_oleh INT UNSIGNED NULL,
    INDEX idx_status (status),
    INDEX idx_no_wa (no_wa),
    INDEX idx_hadir_pada (hadir_pada),
    INDEX idx_dibuat_pada (dibuat_pada),
    CONSTRAINT fk_peserta_sesi FOREIGN KEY (sesi_id) REFERENCES sesi(id) ON DELETE SET NULL,
    CONSTRAINT fk_peserta_petugas FOREIGN KEY (hadir_oleh) REFERENCES petugas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO petugas (id, nama, username, password, peran) VALUES
(1, 'Administrator', 'admin', '$2y$10$0EIssV9cYT7t0vSgSx3eMuxQCvXu18dNGAdmcENQ5Y0P4tuMC.USO', 'admin');

INSERT INTO sesi (nama, jam_mulai, jam_selesai)
SELECT * FROM (SELECT 'Sesi 1' AS n, '08:00:00' AS m, '12:00:00' AS s UNION ALL
               SELECT 'Sesi 2', '12:00:00', '16:00:00' UNION ALL
               SELECT 'Sesi 3', '16:00:00', '21:00:00') x
WHERE NOT EXISTS (SELECT 1 FROM sesi);
