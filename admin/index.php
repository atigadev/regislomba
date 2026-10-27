<?php
require_once dirname(__DIR__) . '/includes/app.php';
wajib_login();

$hariIni = date('Y-m-d');
$pdo = db();

$total = $pdo->query("SELECT COUNT(*) AS semua, SUM(status = 'hadir') AS hadir FROM peserta")->fetch();
$st = $pdo->prepare('SELECT COUNT(*) FROM peserta WHERE DATE(dibuat_pada) = ?');
$st->execute([$hariIni]);
$daftarHariIni = (int) $st->fetchColumn();
$st = $pdo->prepare('SELECT COUNT(*) FROM peserta WHERE DATE(hadir_pada) = ?');
$st->execute([$hariIni]);
$hadirHariIni = (int) $st->fetchColumn();

// Rekap hari ini per sesi x jenis lomba, berdasarkan waktu hadir.
$st = $pdo->prepare("SELECT sesi_id, jenis_lomba, COUNT(*) AS n FROM peserta
                     WHERE status = 'hadir' AND DATE(hadir_pada) = ? GROUP BY sesi_id, jenis_lomba");
$st->execute([$hariIni]);
$rekap = [];
foreach ($st->fetchAll() as $r) {
    $rekap[(int) $r['sesi_id']][$r['jenis_lomba']] = (int) $r['n'];
}
$sesiList = sesi_semua();
$sesiKini = sesi_pada_jam();

$terbaru = $pdo->query('SELECT * FROM peserta ORDER BY id DESC LIMIT 8')->fetchAll();

$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
require dirname(__DIR__) . '/includes/header.php';
$semua = (int) $total['semua'];
$hadir = (int) $total['hadir'];
// Pemeriksaan server untuk admin: dibaca dari permintaan web ini, bukan CLI,
// karena php.ini, konfigurasi Apache, dan .htaccess bisa saling menimpa.
$progresUpload = filter_var(ini_get('session.upload_progress.enabled'), FILTER_VALIDATE_BOOLEAN);
?>

<?php if (is_admin() && (php_rentan_php2shell() || $progresUpload)): ?>
    <div class="alert alert-danger">
        <strong><i class="fas fa-triangle-exclamation"></i> Server: PHP <?= h(PHP_VERSION) ?></strong>
        <?php if (php_rentan_php2shell()): ?>
            rentan <strong>PHP2Shell</strong> (CVE-2026-109618 / CVE-2026-109619) — eksekusi kode jarak jauh tanpa login.
            Mitigasi di folder aplikasi ini sudah aktif, tetapi satu-satunya perbaikan tuntas adalah
            memperbarui PHP ke 8.2.35 / 8.3.36 / 8.4.27 / 8.5.12 atau lebih baru.
        <?php endif; ?>
        <?php if ($progresUpload): ?>
            <br><code>session.upload_progress.enabled</code> masih <strong>On</strong>: matikan di php.ini lalu restart Apache.
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="kotak-angka">
    <div class="kotak aksen">
        <div class="label">Total Pendaftar</div>
        <div class="nilai"><?= number_format($semua, 0, ',', '.') ?></div>
        <div class="sub"><?= number_format($daftarHariIni, 0, ',', '.') ?> mendaftar hari ini</div>
    </div>
    <div class="kotak ok">
        <div class="label">Sudah Hadir</div>
        <div class="nilai"><?= number_format($hadir, 0, ',', '.') ?></div>
        <div class="sub">dari <?= number_format($semua, 0, ',', '.') ?> pendaftar</div>
    </div>
    <div class="kotak waspada">
        <div class="label">Belum Dicek</div>
        <div class="nilai"><?= number_format($semua - $hadir, 0, ',', '.') ?></div>
        <div class="sub">menunggu konfirmasi petugas</div>
    </div>
    <div class="kotak">
        <div class="label">Hadir Hari Ini</div>
        <div class="nilai"><?= number_format($hadirHariIni, 0, ',', '.') ?></div>
        <div class="sub"><?= $sesiKini ? 'Sedang berjalan: ' . h(sesi_label($sesiKini)) : 'Tidak ada sesi berjalan' ?></div>
    </div>
</div>

<div class="row">
    <div class="col-lg-5 mb-4">
        <section class="card h-100">
            <header class="card-header">
                <h2 class="card-title">Kehadiran Hari Ini per Sesi</h2>
                <p class="card-subtitle"><?= h(tgl_indo($hariIni, false)) ?></p>
            </header>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Sesi</th>
                                <?php foreach (JENIS_LOMBA as $label): ?><th class="text-end"><?= h($label) ?></th><?php endforeach; ?>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$sesiList): ?>
                                <tr><td colspan="4" class="text-center text-muted">Belum ada sesi. Tambahkan di menu Sesi.</td></tr>
                            <?php endif; ?>
                            <?php
                            $baris = $sesiList;
                            if (isset($rekap[0])) { $baris[] = ['id' => 0, 'nama' => 'Tanpa sesi', 'jam_mulai' => '', 'jam_selesai' => '']; }
                            foreach ($baris as $s):
                                $sid = (int) $s['id'];
                                $sum = 0;
                            ?>
                                <tr>
                                    <td><?= h($s['nama']) ?></td>
                                    <?php foreach (JENIS_LOMBA as $k => $label): $n = $rekap[$sid][$k] ?? 0; $sum += $n; ?>
                                        <td class="text-end"><?= $n ?></td>
                                    <?php endforeach; ?>
                                    <td class="text-end"><strong><?= $sum ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <div class="col-lg-7 mb-4">
        <section class="card h-100">
            <header class="card-header">
                <div class="card-actions">
                    <a href="<?= url('admin/peserta.php') ?>" class="btn btn-sm btn-default">Lihat semua</a>
                </div>
                <h2 class="card-title">Pendaftar Terbaru</h2>
                <p class="card-subtitle"><?= count($terbaru) ?> pendaftaran terakhir dari <?= $semua ?></p>
            </header>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Kode</th><th>Nama</th><th>Lomba</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!$terbaru): ?>
                                <tr><td colspan="4" class="text-center text-muted">Belum ada pendaftar. Bagikan tautan formulir peserta untuk mulai menerima pendaftaran.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($terbaru as $p): ?>
                                <tr>
                                    <td><code><?= h($p['kode']) ?></code></td>
                                    <td><strong><?= h($p['nama']) ?></strong><br><small class="text-muted"><?= h(tgl_indo($p['dibuat_pada'])) ?></small></td>
                                    <td><span class="badge badge-<?= h($p['jenis_lomba']) ?>"><?= h(jenis_label($p['jenis_lomba'])) ?></span></td>
                                    <td><span class="badge badge-<?= h($p['status']) ?>"><?= $p['status'] === 'hadir' ? 'Hadir' : 'Terdaftar' ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
