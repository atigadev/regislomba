# Registrasi Lomba

Pendaftaran peserta lomba (Challenge / Free Play) dengan QR code bertoken, pengecekan dan konfirmasi hadir oleh petugas, dan laporan kehadiran per tanggal dan sesi. PHP 7.4+ / MySQL (MariaDB), tema Porto Admin seperti SatuRegis.

| Halaman | Isi |
|---|---|
| `index.php` | Formulir peserta (publik) |
| `kartu.php?t=<token>` | Kartu peserta: kode registrasi + QR, unduh PNG |
| `admin/peserta.php` | Cek Pendaftaran: cari, Scan QR (kamera / scanner USB), tandai Hadir |
| `admin/cek.php?t=<token>` | Tujuan QR peserta: detail + tombol Tandai Hadir |
| `admin/laporan.php` | Rekap tanggal × sesi, daftar hadir, CSV, cetak |
| `admin/qr-formulir.php` | QR link pendaftaran, unduh PNG, cetak poster |
| `admin/sesi.php`, `admin/petugas.php`, `admin/backup.php` | Khusus admin |

## Pasang

1. Taruh folder di `htdocs` (XAMPP) atau `public_html` (cPanel).
2. Buka `http://<host>/<folder>/setup.php`, isi database, data acara, dan akun admin, lalu tekan **Pasang Sekarang**.
   Database, tabel, sesi bawaan, akun admin, dan `config.php` dibuat otomatis.
3. Login di `admin/login.php`.

Setup terkunci setelah berhasil. Hapus `includes/setup.lock` untuk menjalankannya lagi.
Untuk import manual, pakai `database.sql` (akun awal `admin` / `admin123`, segera ganti).

`config.php` **tidak dilacak git** karena berisi kredensial. Contohnya ada di `config.contoh.php`.

## Keamanan

- **PHP2Shell (CVE-2026-109618 / 109619)**: celah di runtime PHP, bukan di aplikasi. Perbaikan tuntas hanya
  dengan PHP **8.2.35 / 8.3.36 / 8.4.27 / 8.5.12** atau lebih baru (PHP 7.4–8.1 tidak mendapat rilis perbaikan).
  Sampai server diperbarui, `.htaccess` aplikasi ini menolak multipart/upload, kiriman chunked, badan > 64 KB, dan
  metode selain GET/POST/HEAD sebelum sampai ke PHP, serta menyetel `session.upload_progress.enabled=Off` dan
  `memory_limit=-1` untuk folder ini. Matikan juga `session.upload_progress.enabled` di `php.ini` server, karena
  aplikasi lain di server yang sama tetap bisa menjadi pintu masuk. Dashboard admin menampilkan peringatan bila
  versi PHP masih rentan.
- Login dibatasi per IP+username (5x/15 menit) dan per IP (20x/15 menit), disimpan di database.
- Formulir publik: jebakan bot, minimal 3 detik pengisian, dan batas per IP (`DAFTAR_BATAS_IP`, bawaan 150 per
  10 menit — naikkan di `config.php` bila ratusan peserta mendaftar lewat satu WiFi acara).
- Password petugas minimal 8 karakter dan bukan password umum; akun dengan password lemah dipaksa ganti saat login.
- Wajib HTTPS di hosting: cookie sesi otomatis diberi flag `Secure`.

## Catatan

- Isi QR mengikuti alamat yang sedang dibuka. Buka aplikasi lewat IP/domain yang bisa dijangkau HP, bukan `localhost`.
- Kamera di dalam aplikasi hanya jalan di `https://` atau localhost. Di jaringan `http://` biasa, pindai pakai kamera bawaan HP (langsung membuka halaman cek) atau scanner USB.
- Backup database: menu **Backup Database** (admin) mengunduh `.sql` struktur + data.
