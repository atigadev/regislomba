<?php
/**
 * KELOLA SESI (admin). Sesi berlaku untuk setiap hari acara, ditentukan jam.
 * Sesi yang sudah dipakai tidak dihapus, hanya dinonaktifkan - supaya laporan
 * hari-hari sebelumnya tetap menyebut nama sesinya.
 */
require_once dirname(__DIR__) . '/includes/app.php';
wajib_login(true);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        flash_set('error', 'Halaman kedaluwarsa. Silakan coba lagi.');
        redirect('admin/sesi.php');
    }
    $aksi = $_POST['aksi'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($aksi === 'simpan') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $mulai = (string) ($_POST['jam_mulai'] ?? '');
        $selesai = (string) ($_POST['jam_selesai'] ?? '');
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        if ($nama === '' || !preg_match('/^\d{2}:\d{2}$/', $mulai) || !preg_match('/^\d{2}:\d{2}$/', $selesai)) {
            flash_set('error', 'Nama sesi, jam mulai, dan jam selesai wajib diisi.');
        } elseif ($selesai <= $mulai) {
            flash_set('error', 'Jam selesai harus setelah jam mulai.');
        } else {
            if ($id) {
                $pdo->prepare('UPDATE sesi SET nama = ?, jam_mulai = ?, jam_selesai = ?, aktif = ? WHERE id = ?')->execute([$nama, $mulai, $selesai, $aktif, $id]);
            } else {
                $pdo->prepare('INSERT INTO sesi (nama, jam_mulai, jam_selesai, aktif) VALUES (?, ?, ?, ?)')->execute([$nama, $mulai, $selesai, $aktif]);
            }
            flash_set('success', 'Sesi "' . $nama . '" tersimpan.');
        }
    } elseif ($aksi === 'hapus') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM peserta WHERE sesi_id = ?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            flash_set('error', 'Sesi ini sudah dipakai peserta yang hadir. Nonaktifkan saja supaya laporan lama tetap utuh.');
        } else {
            $pdo->prepare('DELETE FROM sesi WHERE id = ?')->execute([$id]);
            flash_set('success', 'Sesi dihapus.');
        }
    }
    redirect('admin/sesi.php');
}

$rows = $pdo->query('SELECT s.*, (SELECT COUNT(*) FROM peserta p WHERE p.sesi_id = s.id) AS dipakai FROM sesi s ORDER BY s.jam_mulai, s.id')->fetchAll();

$pageTitle = 'Sesi';
$activeMenu = 'sesi';
require dirname(__DIR__) . '/includes/header.php';
?>

<section class="card">
    <header class="card-header">
        <div class="card-actions">
            <button type="button" class="btn btn-sm btn-primary" id="tombolTambah"><i class="fas fa-plus"></i> Tambah Sesi</button>
        </div>
        <h2 class="card-title">Daftar Sesi</h2>
        <p class="card-subtitle">Berlaku setiap hari acara. Saat petugas menekan Hadir, sesi yang sedang berjalan dipilih otomatis.</p>
    </header>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <!-- Di HP kolom Jam disembunyikan; jamnya ditulis di bawah nama. -->
                        <th class="d-none d-sm-table-cell">Jam</th>
                        <th>Status</th>
                        <th class="text-end">Hadir</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="5" class="text-center text-muted">Belum ada sesi. Tekan Tambah Sesi untuk membuat yang pertama.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $s):
                        $jam = substr($s['jam_mulai'], 0, 5) . '–' . substr($s['jam_selesai'], 0, 5);
                    ?>
                        <tr>
                            <td>
                                <strong><?= h($s['nama']) ?></strong>
                                <small class="d-block d-sm-none text-muted text-nowrap"><?= h($jam) ?></small>
                            </td>
                            <td class="d-none d-sm-table-cell text-nowrap"><?= h($jam) ?></td>
                            <td><span class="badge <?= $s['aktif'] ? 'badge-hadir' : 'badge-terdaftar' ?>"><?= $s['aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                            <td class="text-end"><?= (int) $s['dipakai'] ?></td>
                            <td class="text-end">
                                <div class="aksi-baris">
                                    <button type="button" class="btn btn-sm btn-default tombol-ubah"
                                        data-sesi='<?= h(json_encode(['id' => (int) $s['id'], 'nama' => $s['nama'], 'mulai' => substr($s['jam_mulai'], 0, 5), 'selesai' => substr($s['jam_selesai'], 0, 5), 'aktif' => (int) $s['aktif']])) ?>'>Ubah</button>
                                    <?php if (!(int) $s['dipakai']): ?>
                                        <form method="post" onsubmit="return confirm('Hapus sesi <?= h(addslashes($s['nama'])) ?>?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="aksi" value="hapus">
                                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Hapus sesi" aria-label="Hapus sesi"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="modalSesi" tabindex="-1" aria-hidden="true" aria-labelledby="judulSesi">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" id="sesiId" value="">
            <div class="modal-header">
                <h5 class="modal-title" id="judulSesi">Tambah Sesi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="sesiNama">Nama Sesi <span class="wajib">*</span></label>
                    <input type="text" id="sesiNama" name="nama" class="form-control" maxlength="50" required placeholder="Sesi 1">
                </div>
                <div class="row">
                    <div class="col-6 form-group">
                        <label for="sesiMulai">Jam Mulai <span class="wajib">*</span></label>
                        <input type="time" id="sesiMulai" name="jam_mulai" class="form-control" required>
                    </div>
                    <div class="col-6 form-group">
                        <label for="sesiSelesai">Jam Selesai <span class="wajib">*</span></label>
                        <input type="time" id="sesiSelesai" name="jam_selesai" class="form-control" required>
                    </div>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="sesiAktif" name="aktif" value="1" checked>
                    <label class="form-check-label" for="sesiAktif">Aktif (bisa dipilih saat menandai hadir)</label>
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
    var modal = new bootstrap.Modal(document.getElementById('modalSesi'));
    function isi(s) {
        document.getElementById('judulSesi').textContent = s ? 'Ubah Sesi' : 'Tambah Sesi';
        document.getElementById('sesiId').value = s ? s.id : '';
        document.getElementById('sesiNama').value = s ? s.nama : '';
        document.getElementById('sesiMulai').value = s ? s.mulai : '';
        document.getElementById('sesiSelesai').value = s ? s.selesai : '';
        document.getElementById('sesiAktif').checked = s ? !!s.aktif : true;
        modal.show();
    }
    document.getElementById('tombolTambah').addEventListener('click', function () { isi(null); });
    document.querySelectorAll('.tombol-ubah').forEach(function (b) {
        b.addEventListener('click', function () { isi(JSON.parse(b.getAttribute('data-sesi'))); });
    });
})();
</script>
<?php
$skripHalaman = ob_get_clean();
require dirname(__DIR__) . '/includes/footer.php';
