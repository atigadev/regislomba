<?php
/**
 * Skema database - SATU sumber untuk setup.php. database.sql adalah salinan
 * untuk import manual lewat phpMyAdmin; ubah keduanya bersamaan.
 */

function schema_tabel()
{
    return [
        'petugas' => "CREATE TABLE IF NOT EXISTS petugas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(100) NOT NULL,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            peran ENUM('admin','petugas') NOT NULL DEFAULT 'petugas',
            aktif TINYINT(1) NOT NULL DEFAULT 1,
            dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'sesi' => "CREATE TABLE IF NOT EXISTS sesi (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(50) NOT NULL,
            jam_mulai TIME NOT NULL,
            jam_selesai TIME NOT NULL,
            aktif TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'peserta' => "CREATE TABLE IF NOT EXISTS peserta (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kode VARCHAR(20) NULL UNIQUE,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Satu baris per kejadian (login gagal, kiriman formulir); dihitung per jendela waktu.
        'pembatas' => "CREATE TABLE IF NOT EXISTS pembatas (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kunci VARCHAR(120) NOT NULL,
            waktu DATETIME NOT NULL,
            INDEX idx_kunci_waktu (kunci, waktu)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
}

/**
 * Perubahan skema untuk database yang dipasang sebelum kolomnya ada.
 * Aman dijalankan berulang: setiap langkah memeriksa dulu.
 */
function schema_migrasi(PDO $pdo)
{
    // Token QR per pendaftaran (ditambahkan setelah versi pertama).
    if (!$pdo->query("SHOW COLUMNS FROM peserta LIKE 'token'")->fetch()) {
        $pdo->exec('ALTER TABLE peserta ADD COLUMN token CHAR(32) NULL UNIQUE AFTER kode');
    }
    // Pembatas percobaan login & kiriman formulir (per IP), versi 2.
    $pdo->exec(schema_tabel()['pembatas']);
    $st = $pdo->prepare('UPDATE peserta SET token = ? WHERE id = ?');
    foreach ($pdo->query('SELECT id FROM peserta WHERE token IS NULL')->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $st->execute([bin2hex(random_bytes(16)), $id]);
    }
}

/** Sesi bawaan, hanya diisi kalau tabel sesi masih kosong. */
function schema_sesi_awal()
{
    return [
        ['Sesi 1', '08:00:00', '12:00:00'],
        ['Sesi 2', '12:00:00', '16:00:00'],
        ['Sesi 3', '16:00:00', '21:00:00'],
    ];
}

/**
 * Ekspor seluruh tabel aplikasi sebagai teks SQL (struktur + data), ditulis
 * langsung ke $out supaya data besar tidak ditampung di memori.
 */
function schema_ekspor(PDO $pdo, $out, $namaDb)
{
    fwrite($out, "-- Backup database `{$namaDb}` - " . date('Y-m-d H:i:s') . "\n");
    fwrite($out, "-- Import: buat/pilih database, lalu jalankan berkas ini (phpMyAdmin > Import).\n\n");
    fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

    // Tabel pembatas hanya catatan sementara (login gagal, kiriman), tidak perlu di-backup.
    foreach (array_diff(array_keys(schema_tabel()), ['pembatas']) as $tabel) {
        $buat = $pdo->query("SHOW CREATE TABLE `{$tabel}`")->fetch(PDO::FETCH_NUM);
        fwrite($out, "DROP TABLE IF EXISTS `{$tabel}`;\n" . $buat[1] . ";\n\n");

        $st = $pdo->query("SELECT * FROM `{$tabel}`");
        $kolom = null;
        $batch = [];
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            if ($kolom === null) {
                $kolom = '`' . implode('`, `', array_keys($r)) . '`';
            }
            $nilai = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote((string) $v);
            }, array_values($r));
            $batch[] = '(' . implode(', ', $nilai) . ')';
            if (count($batch) === 200) {
                fwrite($out, "INSERT INTO `{$tabel}` ({$kolom}) VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch) {
            fwrite($out, "INSERT INTO `{$tabel}` ({$kolom}) VALUES\n" . implode(",\n", $batch) . ";\n");
        }
        fwrite($out, "\n");
    }
    fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
}
