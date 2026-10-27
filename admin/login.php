<?php
require_once dirname(__DIR__) . '/includes/app.php';

// Hanya path di dalam aplikasi ini yang diterima sebagai tujuan kembali (cegah open redirect).
$kembali = (string) ($_GET['kembali'] ?? '');
if (strpos($kembali, BASE_URL . '/admin/') !== 0 || strpos($kembali, '//') !== false) {
    $kembali = url('admin/index.php');
}

if (petugas_login()) {
    redirect($kembali);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim((string) ($_POST['username'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    // Dua penghitung di database, 15 menit: per IP+username (5x) menghentikan
    // penebakan satu akun, per IP (20x) menghentikan penebakan banyak akun.
    // Disimpan di database, bukan di sesi: membuang cookie tidak mereset hitungan.
    $kunciAkun = 'login:' . ip_klien() . ':' . substr($username, 0, 50);
    $kunciIp = 'login-ip:' . ip_klien();

    if (pembatas_penuh($kunciAkun, 5, 900) || pembatas_penuh($kunciIp, 20, 900)) {
        $error = 'Terlalu banyak percobaan login gagal. Tunggu 15 menit lalu coba lagi.';
    } elseif (!csrf_valid()) {
        $error = 'Halaman kedaluwarsa. Silakan coba lagi.';
    } else {
        $st = db()->prepare('SELECT * FROM petugas WHERE username = ? AND aktif = 1');
        $st->execute([$username]);
        $p = $st->fetch();
        // password_verify tetap dijalankan walau akun tidak ada, supaya lamanya
        // jawaban tidak membocorkan username mana yang terdaftar.
        $cocok = password_verify($password, $p ? $p['password'] : password_hash(random_bytes(8), PASSWORD_DEFAULT));
        if ($p && $cocok) {
            session_regenerate_id(true);
            pembatas_hapus($kunciAkun);
            $_SESSION['petugas'] = ['id' => $p['id'], 'nama' => $p['nama'], 'username' => $p['username'], 'peran' => $p['peran']];
            $_SESSION['aktif_terakhir'] = time();
            if (password_lemah($password)) {
                $_SESSION['wajib_ganti_password'] = true;
                flash_set('error', 'Password akun ini terlalu lemah atau masih bawaan. Ganti dulu sebelum melanjutkan.');
                redirect('admin/password.php');
            }
            redirect($kembali);
        }
        pembatas_catat($kunciAkun);
        pembatas_catat($kunciIp);
        $error = 'Username atau password salah.';
    }
}
?>
<!doctype html>
<html lang="id" class="fixed dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Login Petugas - <?= h(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700,800&display=swap" rel="stylesheet" type="text/css">
<link rel="stylesheet" href="<?= url('assets/porto/vendor/bootstrap/css/bootstrap.css') ?>">
<link rel="stylesheet" href="<?= url('assets/porto/vendor/font-awesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= url('assets/porto/css/theme.css') ?>">
<link rel="stylesheet" href="<?= url('assets/porto/css/skins/default.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
</head>
<body class="login-page">
<div class="login-wrap">
  <section class="card card-featured-top card-featured-primary login-card">
    <div class="card-body">
      <div class="login-brand">
        <i class="fas fa-trophy"></i>
        <span><?= h(APP_NAME) ?></span>
      </div>
      <h1 class="login-title">Login Petugas</h1>

      <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><i class="fas fa-exclamation-circle me-1"></i> <?= h($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <?= csrf_field() ?>
        <label class="form-label" for="username">Username</label>
        <div class="input-group mb-3">
          <span class="input-group-text"><i class="fas fa-user"></i></span>
          <input type="text" id="username" name="username" class="form-control" required autofocus autocomplete="username">
        </div>

        <label class="form-label" for="password">Password</label>
        <div class="input-group mb-3">
          <span class="input-group-text"><i class="fas fa-lock"></i></span>
          <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-sign-in-alt me-1"></i> Masuk</button>
      </form>
    </div>
  </section>
</div>
</body>
</html>
