<?php
require_once dirname(__DIR__) . '/includes/app.php';
$akun = wajib_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare('SELECT password FROM petugas WHERE id = ?');
    $st->execute([$akun['id']]);
    $hash = (string) $st->fetchColumn();
    $baru = (string) ($_POST['baru'] ?? '');

    if (!csrf_valid()) {
        flash_set('error', 'Halaman kedaluwarsa. Silakan coba lagi.');
    } elseif (!password_verify((string) ($_POST['lama'] ?? ''), $hash)) {
        flash_set('error', 'Password lama salah.');
    } elseif (password_lemah($baru)) {
        flash_set('error', 'Password baru minimal 8 karakter dan bukan password umum (admin123, password, 12345678, dst.).');
    } elseif ($baru === (string) ($_POST['lama'] ?? '')) {
        flash_set('error', 'Password baru harus berbeda dari password lama.');
    } elseif ($baru !== ($_POST['ulang'] ?? '')) {
        flash_set('error', 'Ulangi password baru dengan isi yang sama.');
    } else {
        db()->prepare('UPDATE petugas SET password = ? WHERE id = ?')->execute([password_hash($baru, PASSWORD_DEFAULT), $akun['id']]);
        session_regenerate_id(true);
        unset($_SESSION['wajib_ganti_password']);
        flash_set('success', 'Password berhasil diganti.');
        redirect('admin/index.php');
    }
    redirect('admin/password.php');
}

$pageTitle = 'Ganti Password';
$activeMenu = 'password';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-lg-6">
        <form method="post" class="card">
            <?= csrf_field() ?>
            <header class="card-header">
                <h2 class="card-title">Ganti Password</h2>
                <p class="card-subtitle">Akun: <?= h($akun['username']) ?></p>
            </header>
            <div class="card-body">
                <div class="form-group">
                    <label for="lama">Password Lama <span class="wajib">*</span></label>
                    <input type="password" id="lama" name="lama" class="form-control" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label for="baru">Password Baru <span class="wajib">*</span></label>
                    <input type="password" id="baru" name="baru" class="form-control" required minlength="8" autocomplete="new-password">
                    <span class="help-block">Minimal 8 karakter, bukan password umum.</span>
                </div>
                <div class="form-group">
                    <label for="ulang">Ulangi Password Baru <span class="wajib">*</span></label>
                    <input type="password" id="ulang" name="ulang" class="form-control" required minlength="8" autocomplete="new-password">
                </div>
            </div>
            <footer class="card-footer">
                <div class="row">
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Simpan</button>
                    </div>
                </div>
            </footer>
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
