<?php
/**
 * CEK PESERTA HASIL SCAN - tujuan QR di kartu peserta (?t=<token>), juga bisa
 * dibuka dengan ?kode=LMB-00001 untuk pencarian manual.
 *
 * QR yang dipindai kamera HP biasa juga mendarat di sini; petugas yang belum
 * login diarahkan ke login lalu dikembalikan ke peserta yang sama.
 */
require_once dirname(__DIR__) . '/includes/app.php';
$akun = wajib_login();

$token = (string) ($_GET['t'] ?? '');
$kode = strtoupper(trim((string) ($_GET['kode'] ?? '')));

$p = null;
if (token_valid($token)) {
    $st = db()->prepare('SELECT * FROM peserta WHERE token = ?');
    $st->execute([$token]);
    $p = $st->fetch() ?: null;
} elseif ($kode !== '') {
    $st = db()->prepare('SELECT * FROM peserta WHERE kode = ?');
    $st->execute([$kode]);
    $p = $st->fetch() ?: null;
}

$hasil = null;
if ($p && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $hasil = csrf_valid()
        ? peserta_tandai_hadir($p['id'], (int) ($_POST['sesi_id'] ?? 0), $akun)
        : ['ok' => false, 'pesan' => 'Halaman kedaluwarsa. Tekan Tandai Hadir sekali lagi.'];
}

if ($p) {
    // Dibaca ulang supaya status yang tampil adalah yang terbaru (mis. baru saja hadir).
    $st = db()->prepare('SELECT p.*, s.nama AS sesi_nama, t.nama AS petugas_nama FROM peserta p
                         LEFT JOIN sesi s ON s.id = p.sesi_id LEFT JOIN petugas t ON t.id = p.hadir_oleh WHERE p.id = ?');
    $st->execute([$p['id']]);
    $p = $st->fetch();
}

$sesiAktif = sesi_semua(true);
$sesiKini = sesi_pada_jam();

$pageTitle = 'Cek Peserta';
$activeMenu = 'peserta';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">

        <?php if ($hasil && $hasil['ok']): ?>
            <div class="alert alert-success"><i class="fas fa-circle-check"></i> <strong><?= h($p['nama']) ?></strong> tercatat hadir · <?= h($hasil['keterangan']) ?></div>
        <?php elseif ($hasil): ?>
            <div class="alert alert-danger"><?= h($hasil['pesan']) ?></div>
        <?php endif; ?>

        <?php if (!$p): ?>
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title">Peserta tidak ditemukan</h2>
                </header>
                <div class="card-body">
                    <p class="mb-3">QR atau kode yang dipindai tidak cocok dengan pendaftaran mana pun. Mungkin pendaftarannya
                        sudah dihapus, atau QR berasal dari acara lain. Cari manual dengan kode registrasinya:</p>
                    <form method="get" class="d-flex gap-2">
                        <input type="text" name="kode" class="form-control" placeholder="<?= h(KODE_AWALAN) ?>00001" value="<?= h($kode) ?>" aria-label="Kode registrasi">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass"></i> Cari</button>
                    </form>
                </div>
                <footer class="card-footer text-end">
                    <a href="<?= url('admin/peserta.php?scan=1') ?>" class="btn btn-default"><i class="fas fa-qrcode"></i> Scan lagi</a>
                </footer>
            </section>

        <?php else: ?>
            <section class="card">
                <header class="card-header">
                    <h2 class="card-title"><?= h($p['nama']) ?></h2>
                    <p class="card-subtitle"><code><?= h($p['kode']) ?></code> ·
                        <span class="badge badge-<?= h($p['jenis_lomba']) ?>"><?= h(jenis_label($p['jenis_lomba'])) ?></span>
                        <span class="badge badge-<?= h($p['status']) ?>"><?= $p['status'] === 'hadir' ? 'Hadir' : 'Belum hadir' ?></span>
                    </p>
                </header>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <tbody>
                                <tr><th style="width:130px">Umur</th><td><?= (int) $p['umur'] ?> tahun</td></tr>
                                <tr><th>No. WA</th><td><a href="https://wa.me/<?= h($p['no_wa']) ?>" target="_blank" rel="noopener"><?= h(format_wa($p['no_wa'])) ?></a></td></tr>
                                <tr><th>Email</th><td><?= h($p['email']) ?></td></tr>
                                <tr><th>Domisili</th><td><?= h($p['domisili']) ?></td></tr>
                                <tr><th>Mendaftar</th><td><?= h(tgl_indo($p['dibuat_pada'])) ?></td></tr>
                                <?php if ($p['status'] === 'hadir'): ?>
                                    <tr><th>Hadir</th><td><?= h(tgl_indo($p['hadir_pada'])) ?> · <?= h($p['sesi_nama'] ?: 'Tanpa sesi') ?> · oleh <?= h($p['petugas_nama'] ?: '-') ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($p['status'] === 'terdaftar'): ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="card-body pt-0">
                            <div class="form-group mb-0">
                                <label for="pilihSesi">Sesi <span class="wajib">*</span></label>
                                <select id="pilihSesi" name="sesi_id" class="form-select" required>
                                    <!-- Pilihan kosong terpilih bila tidak ada sesi yang berjalan: tanpa
                                         itu browser diam-diam menunjuk sesi pertama. -->
                                    <option value="" <?= $sesiKini ? '' : 'selected' ?>><?= $sesiAktif ? '— Pilih sesi —' : 'Belum ada sesi aktif' ?></option>
                                    <?php foreach ($sesiAktif as $s): ?>
                                        <option value="<?= (int) $s['id'] ?>" <?= $sesiKini && (int) $sesiKini['id'] === (int) $s['id'] ? 'selected' : '' ?>><?= h(sesi_label($s)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($sesiKini): ?>
                                    <span class="help-block">Dipilih otomatis sesuai jam sekarang. Periksa data di atas sebelum menandai hadir.</span>
                                <?php else: ?>
                                    <span class="help-block text-warning"><i class="fas fa-triangle-exclamation"></i> Sekarang (<?= date('H:i') ?>) di luar jam sesi mana pun — pilih sesinya secara manual.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <footer class="card-footer">
                            <div class="d-flex flex-wrap gap-2 justify-content-end">
                                <a href="<?= url('admin/peserta.php?scan=1') ?>" class="btn btn-default">Batal</a>
                                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Tandai Hadir</button>
                            </div>
                        </footer>
                    </form>
                <?php else: ?>
                    <footer class="card-footer">
                        <div class="d-flex flex-wrap gap-2 justify-content-end">
                            <a href="<?= url('admin/peserta.php') ?>" class="btn btn-default">Daftar Pendaftar</a>
                            <a href="<?= url('admin/peserta.php?scan=1') ?>" class="btn btn-primary"><i class="fas fa-qrcode"></i> Scan Berikutnya</a>
                        </div>
                    </footer>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
