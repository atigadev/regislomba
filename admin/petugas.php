<?php
/**
 * KELOLA PETUGAS (admin). Akun tidak dihapus, hanya dinonaktifkan - namanya
 * tetap tercatat di laporan sebagai yang menandai hadir.
 */
require_once dirname(__DIR__) . '/includes/app.php';
$akun = wajib_login(true);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        flash_set('error', 'Halaman kedaluwarsa. Silakan coba lagi.');
        redirect('admin/petugas.php');
    }
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $peran = ($_POST['peran'] ?? '') === 'admin' ? 'admin' : 'petugas';
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    $st = $pdo->prepare('SELECT COUNT(*) FROM petugas WHERE username = ? AND id <> ?');
    $st->execute([$username, $id]);

    if ($nama === '' || !preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
        flash_set('error', 'Nama wajib diisi; username 3–50 karakter (huruf, angka, titik, garis bawah, strip).');
    } elseif ((int) $st->fetchColumn() > 0) {
        flash_set('error', 'Username "' . $username . '" sudah dipakai. Pilih yang lain.');
    } elseif (!$id && strlen($password) < 6) {
        flash_set('error', 'Password akun baru minimal 6 karakter.');
    } elseif ($id && $password !== '' && strlen($password) < 6) {
        flash_set('error', 'Password minimal 6 karakter, atau kosongkan bila tidak diganti.');
    } elseif ($id === (int) $akun['id'] && ($peran !== 'admin' || !$aktif)) {
        flash_set('error', 'Anda tidak bisa menurunkan peran atau menonaktifkan akun sendiri.');
    } else {
        if ($id) {
            $pdo->prepare('UPDATE petugas SET nama = ?, username = ?, peran = ?, aktif = ? WHERE id = ?')->execute([$nama, $username, $peran, $aktif, $id]);
            if ($password !== '') {
                $pdo->prepare('UPDATE petugas SET password = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            }
        } else {
            $pdo->prepare('INSERT INTO petugas (nama, username, password, peran, aktif) VALUES (?, ?, ?, ?, ?)')
                ->execute([$nama, $username, password_hash($password, PASSWORD_DEFAULT), $peran, $aktif]);
        }
        flash_set('success', 'Akun ' . $nama . ' tersimpan.');
    }
    redirect('admin/petugas.php');
}

$rows = $pdo->query('SELECT p.*, (SELECT COUNT(*) FROM peserta x WHERE x.hadir_oleh = p.id) AS dicek FROM petugas p ORDER BY p.aktif DESC, p.nama')->fetchAll();

$pageTitle = 'Kelola Petugas';
$activeMenu = 'petugas';
require dirname(__DIR__) . '/includes/header.php';
?>

<section class="card">
    <header class="card-header">
        <div class="card-actions">
            <button type="button" class="btn btn-sm btn-primary" id="tombolTambah"><i class="fas fa-plus"></i> Tambah Petugas</button>
        </div>
        <h2 class="card-title">Akun Petugas</h2>
        <p class="card-subtitle"><?= count($rows) ?> akun. Petugas hanya bisa mengecek &amp; menandai hadir; Administrator juga mengelola sesi, akun, dan membatalkan kehadiran.</p>
    </header>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Nama</th><th>Username</th><th>Peran</th><th>Status</th><th class="text-end">Peserta Dicek</th><th class="text-end" style="width:100px;"></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><strong><?= h($r['nama']) ?></strong></td>
                            <td><?= h($r['username']) ?></td>
                            <td><?= $r['peran'] === 'admin' ? 'Administrator' : 'Petugas' ?></td>
                            <td><span class="badge <?= $r['aktif'] ? 'badge-hadir' : 'badge-terdaftar' ?>"><?= $r['aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                            <td class="text-end"><?= (int) $r['dicek'] ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-default tombol-ubah"
                                    data-akun='<?= h(json_encode(['id' => (int) $r['id'], 'nama' => $r['nama'], 'username' => $r['username'], 'peran' => $r['peran'], 'aktif' => (int) $r['aktif']])) ?>'>Ubah</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="modalAkun" tabindex="-1" aria-hidden="true" aria-labelledby="judulAkun">
    <div class="modal-dialog">
        <form method="post" class="modal-content" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="akunId" value="">
            <div class="modal-header">
                <h5 class="modal-title" id="judulAkun">Tambah Petugas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="akunNama">Nama <span class="wajib">*</span></label>
                    <input type="text" id="akunNama" name="nama" class="form-control" maxlength="100" required>
                </div>
                <div class="form-group">
                    <label for="akunUsername">Username <span class="wajib">*</span></label>
                    <input type="text" id="akunUsername" name="username" class="form-control" maxlength="50" required>
                </div>
                <div class="form-group">
                    <label for="akunPassword">Password <span class="wajib" id="tandaWajibPass">*</span></label>
                    <input type="password" id="akunPassword" name="password" class="form-control" autocomplete="new-password">
                    <span class="help-block" id="bantuPass">Minimal 6 karakter.</span>
                </div>
                <div class="form-group">
                    <label for="akunPeran">Peran</label>
                    <select id="akunPeran" name="peran" class="form-select">
                        <option value="petugas">Petugas</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="akunAktif" name="aktif" value="1" checked>
                    <label class="form-check-label" for="akunAktif">Aktif (bisa login)</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php ob_start(); ?>
<script>
(function () {
    var modal = new bootstrap.Modal(document.getElementById('modalAkun'));
    function isi(a) {
        document.getElementById('judulAkun').textContent = a ? 'Ubah Petugas' : 'Tambah Petugas';
        document.getElementById('akunId').value = a ? a.id : '';
        document.getElementById('akunNama').value = a ? a.nama : '';
        document.getElementById('akunUsername').value = a ? a.username : '';
        document.getElementById('akunPassword').value = '';
        document.getElementById('akunPeran').value = a ? a.peran : 'petugas';
        document.getElementById('akunAktif').checked = a ? !!a.aktif : true;
        document.getElementById('tandaWajibPass').style.display = a ? 'none' : '';
        document.getElementById('bantuPass').textContent = a ? 'Kosongkan bila tidak diganti.' : 'Minimal 6 karakter.';
        modal.show();
    }
    document.getElementById('tombolTambah').addEventListener('click', function () { isi(null); });
    document.querySelectorAll('.tombol-ubah').forEach(function (b) {
        b.addEventListener('click', function () { isi(JSON.parse(b.getAttribute('data-akun'))); });
    });
})();
</script>
<?php
$skripHalaman = ob_get_clean();
require dirname(__DIR__) . '/includes/footer.php';
