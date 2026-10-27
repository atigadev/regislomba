<?php
/**
 * SETUP - pemasangan sekali jalan.
 *
 * Dalam satu langkah: membuat database & tabel, mengisi sesi bawaan, membuat
 * akun administrator, dan menulis config.php. Setelah berhasil, berkas
 * includes/setup.lock dibuat dan halaman ini menolak dijalankan lagi -
 * hapus berkas itu bila memang ingin memasang ulang.
 *
 * Sengaja TIDAK memuat includes/app.php: saat setup, config.php bisa saja
 * belum benar dan koneksinya pasti gagal.
 */
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/aturan.php';

$fileConfig = __DIR__ . '/config.php';
$fileKunci = __DIR__ . '/includes/setup.lock';
if (is_file($fileConfig)) {
    require_once $fileConfig;
}
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Jakarta');

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function tulis_config($file, array $v)
{
    $x = function ($s) { return var_export((string) $s, true); };
    $isi = <<<PHP
<?php
/**
 * Konfigurasi Registrasi Acara Lomba.
 * Ditulis oleh setup.php pada {$v['_waktu']}. Boleh diubah manual.
 */

define('DB_HOST', {$x($v['db_host'])});
define('DB_PORT', {$v['db_port']});
define('DB_NAME', {$x($v['db_name'])});
define('DB_USER', {$x($v['db_user'])});
define('DB_PASS', {$x($v['db_pass'])});

define('APP_NAME', {$x($v['app_nama'])});
// Ditampilkan di kepala formulir peserta.
define('ACARA_NAMA', {$x($v['acara_nama'])});
define('ACARA_LOKASI', {$x($v['acara_lokasi'])});

// Awalan kode registrasi yang ditunjukkan peserta ke petugas, mis. LMB-00012.
define('KODE_AWALAN', {$x($v['kode_awalan'])});

// Formulir publik bisa ditutup tanpa mematikan aplikasi petugas.
define('PENDAFTARAN_DIBUKA', true);

define('APP_TIMEZONE', 'Asia/Jakarta');

// Alamat aplikasi relatif terhadap root web, tanpa garis miring di akhir.
// Dibiarkan otomatis: '/regis-acara' di XAMPP, '' bila ditaruh di public_html.
define('BASE_URL', rtrim(str_replace('\\\\', '/', substr(str_replace('\\\\', '/', __DIR__), strlen(rtrim(str_replace('\\\\', '/', \$_SERVER['DOCUMENT_ROOT'] ?? ''), '/')))), '/'));

define('APP_DEBUG', false);

PHP;
    return file_put_contents($file, $isi) !== false;
}

$terkunci = is_file($fileKunci);
$galat = [];
$selesai = false;
$jumlah = [];

$v = [
    'db_host' => defined('DB_HOST') ? DB_HOST : 'localhost',
    'db_port' => defined('DB_PORT') ? DB_PORT : 3306,
    'db_name' => defined('DB_NAME') ? DB_NAME : 'regis_acara',
    'db_user' => defined('DB_USER') ? DB_USER : 'root',
    'db_pass' => '',
    'app_nama' => defined('APP_NAME') ? APP_NAME : 'Registrasi Lomba',
    'acara_nama' => defined('ACARA_NAMA') ? ACARA_NAMA : 'Lomba Game 2026',
    'acara_lokasi' => defined('ACARA_LOKASI') ? ACARA_LOKASI : '',
    'kode_awalan' => defined('KODE_AWALAN') ? KODE_AWALAN : 'LMB-',
    'admin_nama' => 'Administrator',
    'admin_user' => 'admin',
    'admin_pass' => '',
];

if (!$terkunci && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) {
        $v[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $v['db_pass'] = (string) ($_POST['db_pass'] ?? '');
    $v['admin_pass'] = (string) ($_POST['admin_pass'] ?? '');
    $v['db_port'] = (int) $v['db_port'] ?: 3306;

    if ($v['db_host'] === '' || $v['db_user'] === '') { $galat[] = 'Host dan user database wajib diisi.'; }
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $v['db_name'])) { $galat[] = 'Nama database hanya boleh huruf, angka, dan garis bawah.'; }
    if ($v['acara_nama'] === '' || $v['app_nama'] === '') { $galat[] = 'Nama aplikasi dan nama acara wajib diisi.'; }
    if (!preg_match('/^[A-Za-z0-9-]{0,10}$/', $v['kode_awalan'])) { $galat[] = 'Awalan kode maksimal 10 karakter (huruf, angka, strip).'; }
    if ($v['admin_nama'] === '' || !preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $v['admin_user'])) { $galat[] = 'Nama admin wajib diisi; username 3–50 karakter (huruf, angka, titik, garis bawah, strip).'; }
    if (password_lemah($v['admin_pass'])) { $galat[] = 'Password administrator minimal 8 karakter dan bukan password umum (admin123, password, dst.).'; }
    if ($v['admin_pass'] !== ($_POST['admin_pass2'] ?? '')) { $galat[] = 'Ulangi password administrator dengan isi yang sama.'; }

    if (!$galat) {
        try {
            $pdo = new PDO('mysql:host=' . $v['db_host'] . ';port=' . $v['db_port'] . ';charset=utf8mb4', $v['db_user'], $v['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $v['db_name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . $v['db_name'] . '`');
            foreach (schema_tabel() as $sql) {
                $pdo->exec($sql);
            }
            schema_migrasi($pdo);
            if ((int) $pdo->query('SELECT COUNT(*) FROM sesi')->fetchColumn() === 0) {
                $st = $pdo->prepare('INSERT INTO sesi (nama, jam_mulai, jam_selesai) VALUES (?, ?, ?)');
                foreach (schema_sesi_awal() as $s) {
                    $st->execute($s);
                }
            }
            // Username yang sudah ada (pemasangan ulang) diperbarui, bukan digandakan.
            $hash = password_hash($v['admin_pass'], PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO petugas (nama, username, password, peran, aktif) VALUES (?, ?, ?, 'admin', 1)
                           ON DUPLICATE KEY UPDATE nama = VALUES(nama), password = VALUES(password), peran = 'admin', aktif = 1")
                ->execute([$v['admin_nama'], $v['admin_user'], $hash]);

            $v['_waktu'] = date('Y-m-d H:i:s');
            if (!tulis_config($fileConfig, $v)) {
                $galat[] = 'Database siap, tetapi config.php tidak bisa ditulis. Beri izin tulis pada folder aplikasi lalu jalankan setup lagi.';
            } else {
                @file_put_contents($fileKunci, 'Setup selesai ' . $v['_waktu'] . ". Hapus berkas ini untuk menjalankan setup.php lagi.\n");
                $selesai = true;
                $jumlah = [];
                foreach (array_keys(schema_tabel()) as $t) {
                    $jumlah[$t] = (int) $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
                }
            }
        } catch (PDOException $ex) {
            $galat[] = 'Gagal terhubung/membuat database: ' . $ex->getMessage();
        }
    }
}

$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
?>
<!doctype html>
<html lang="id" class="fixed dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Setup - Registrasi Lomba</title>
<link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700,800&display=swap" rel="stylesheet" type="text/css">
<link rel="stylesheet" href="<?= e($base) ?>/assets/porto/vendor/bootstrap/css/bootstrap.css">
<link rel="stylesheet" href="<?= e($base) ?>/assets/porto/vendor/font-awesome/css/all.min.css">
<link rel="stylesheet" href="<?= e($base) ?>/assets/porto/css/theme.css">
<link rel="stylesheet" href="<?= e($base) ?>/assets/porto/css/skins/default.css">
<link rel="stylesheet" href="<?= e($base) ?>/assets/app.css">
<style>.login-card{max-width:640px}.login-card h3{font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--ui-muted);margin:22px 0 12px;padding-top:16px;border-top:1px solid var(--ui-line)}.login-card h3:first-of-type{border-top:0;padding-top:0;margin-top:0}</style>
</head>
<body class="login-page">
<div class="login-wrap">
  <section class="card card-featured-top card-featured-primary login-card">
    <div class="card-body">
      <div class="login-brand"><i class="fas fa-screwdriver-wrench"></i><span>Registrasi Lomba</span></div>
      <h1 class="login-title">Setup Aplikasi</h1>

      <?php if ($terkunci): ?>
        <div class="alert alert-warning">
          Aplikasi sudah terpasang. Untuk menjalankan setup lagi, hapus berkas
          <code>includes/setup.lock</code> di server.
        </div>
        <a href="<?= e($base) ?>/admin/login.php" class="btn btn-primary w-100"><i class="fas fa-sign-in-alt me-1"></i> Ke Login Petugas</a>

      <?php elseif ($selesai): ?>
        <div class="alert alert-success">
          <strong>Setup selesai.</strong> Database <code><?= e($v['db_name']) ?></code> siap dipakai:
          <?= (int) $jumlah['petugas'] ?> akun petugas, <?= (int) $jumlah['sesi'] ?> sesi, <?= (int) $jumlah['peserta'] ?> peserta.
          config.php sudah ditulis.
        </div>
        <p class="text-muted" style="font-size:13px">Login dengan username <strong><?= e($v['admin_user']) ?></strong> dan password yang tadi diisi.
          Unduh salinan database sekarang (menu <em>Backup Database</em> setelah login) bila ingin memindahkannya ke hosting.</p>
        <div class="d-grid gap-2">
          <a href="<?= e($base) ?>/admin/login.php?kembali=<?= rawurlencode($base . '/admin/backup.php') ?>" class="btn btn-primary"><i class="fas fa-download me-1"></i> Login &amp; Export Database</a>
          <a href="<?= e($base) ?>/admin/login.php" class="btn btn-default"><i class="fas fa-sign-in-alt me-1"></i> Ke Login Petugas</a>
          <a href="<?= e($base) ?>/index.php" target="_blank" rel="noopener" class="btn btn-default"><i class="fas fa-up-right-from-square me-1"></i> Buka Formulir Peserta</a>
        </div>

      <?php else: ?>
        <?php if ($galat): ?>
          <div class="alert alert-danger"><?= implode('<br>', array_map('e', $galat)) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
          <h3>Database</h3>
          <div class="row">
            <div class="col-sm-8 form-group mb-3">
              <label class="form-label" for="db_host">Host</label>
              <input class="form-control" id="db_host" name="db_host" value="<?= e($v['db_host']) ?>" required>
            </div>
            <div class="col-sm-4 form-group mb-3">
              <label class="form-label" for="db_port">Port</label>
              <input class="form-control" id="db_port" name="db_port" type="number" value="<?= e($v['db_port']) ?>">
            </div>
            <div class="col-sm-4 form-group mb-3">
              <label class="form-label" for="db_name">Nama Database</label>
              <input class="form-control" id="db_name" name="db_name" value="<?= e($v['db_name']) ?>" required>
            </div>
            <div class="col-sm-4 form-group mb-3">
              <label class="form-label" for="db_user">User</label>
              <input class="form-control" id="db_user" name="db_user" value="<?= e($v['db_user']) ?>" required>
            </div>
            <div class="col-sm-4 form-group mb-3">
              <label class="form-label" for="db_pass">Password</label>
              <input class="form-control" id="db_pass" name="db_pass" type="password" autocomplete="new-password">
            </div>
          </div>
          <span class="help-block" style="margin-top:-8px">Database dibuat otomatis bila belum ada. Di cPanel, buat dulu database &amp; user-nya lewat <em>MySQL Databases</em> (namanya berawalan nama akun).</span>

          <h3>Acara</h3>
          <div class="row">
            <div class="col-sm-6 form-group mb-3">
              <label class="form-label" for="acara_nama">Nama Acara</label>
              <input class="form-control" id="acara_nama" name="acara_nama" value="<?= e($v['acara_nama']) ?>" required maxlength="150">
            </div>
            <div class="col-sm-6 form-group mb-3">
              <label class="form-label" for="acara_lokasi">Lokasi <small>(opsional)</small></label>
              <input class="form-control" id="acara_lokasi" name="acara_lokasi" value="<?= e($v['acara_lokasi']) ?>" maxlength="150">
            </div>
            <div class="col-sm-6 form-group mb-3">
              <label class="form-label" for="app_nama">Nama Aplikasi</label>
              <input class="form-control" id="app_nama" name="app_nama" value="<?= e($v['app_nama']) ?>" required maxlength="60">
            </div>
            <div class="col-sm-6 form-group mb-3">
              <label class="form-label" for="kode_awalan">Awalan Kode Registrasi</label>
              <input class="form-control" id="kode_awalan" name="kode_awalan" value="<?= e($v['kode_awalan']) ?>" maxlength="10">
            </div>
          </div>

          <h3>Akun Administrator</h3>
          <div class="row">
            <div class="col-sm-6 form-group mb-3">
              <label class="form-label" for="admin_nama">Nama</label>
              <input class="form-control" id="admin_nama" name="admin_nama" value="<?= e($v['admin_nama']) ?>" required>
            </div>
            <div class="col-sm-6 form-group mb-3">
              <label class="form-label" for="admin_user">Username</label>
              <input class="form-control" id="admin_user" name="admin_user" value="<?= e($v['admin_user']) ?>" required>
            </div>
            <div class="col-sm-6 form-group mb-3">
              <label class="form-label" for="admin_pass">Password</label>
              <input class="form-control" id="admin_pass" name="admin_pass" type="password" minlength="8" required autocomplete="new-password">
            </div>
            <div class="col-sm-6 form-group mb-3">
              <label class="form-label" for="admin_pass2">Ulangi Password</label>
              <input class="form-control" id="admin_pass2" name="admin_pass2" type="password" minlength="8" required autocomplete="new-password">
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100 mt-2"><i class="fas fa-database me-1"></i> Pasang Sekarang</button>
        </form>
      <?php endif; ?>
    </div>
  </section>
</div>
</body>
</html>
