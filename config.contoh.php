<?php
/**
 * Konfigurasi Registrasi Acara Lomba.
 * CONTOH - salin ke config.php, atau jalankan setup.php yang menulisnya otomatis.
 */

define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'regis_acara');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'Registrasi Lomba');
// Ditampilkan di kepala formulir peserta.
define('ACARA_NAMA', 'Lomba Game 2026');
define('ACARA_LOKASI', '');

// Awalan kode registrasi yang ditunjukkan peserta ke petugas, mis. LMB-00012.
define('KODE_AWALAN', 'LMB-');

// Formulir publik bisa ditutup tanpa mematikan aplikasi petugas.
define('PENDAFTARAN_DIBUKA', true);

define('APP_TIMEZONE', 'Asia/Jakarta');

// Alamat aplikasi relatif terhadap root web, tanpa garis miring di akhir.
// Dibiarkan otomatis: '/regis-acara' di XAMPP, '' bila ditaruh di public_html.
define('BASE_URL', rtrim(str_replace('\\', '/', substr(str_replace('\\', '/', __DIR__), strlen(rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/')))), '/'));

define('APP_DEBUG', false);
