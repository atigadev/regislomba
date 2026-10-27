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

## Catatan

- Isi QR mengikuti alamat yang sedang dibuka. Buka aplikasi lewat IP/domain yang bisa dijangkau HP, bukan `localhost`.
- Kamera di dalam aplikasi hanya jalan di `https://` atau localhost. Di jaringan `http://` biasa, pindai pakai kamera bawaan HP (langsung membuka halaman cek) atau scanner USB.
- Backup database: menu **Backup Database** (admin) mengunduh `.sql` struktur + data.
