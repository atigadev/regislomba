<?php
/**
 * QR FORMULIR - tautan pendaftaran dijadikan QR code untuk dipasang di lokasi
 * acara atau dibagikan di media sosial. Bisa disalin, diunduh PNG, dan dicetak
 * sebagai poster A4.
 */
require_once dirname(__DIR__) . '/includes/app.php';
wajib_login();

$tautan = url_penuh('index.php');
$lokal = preg_match('#^https?://(localhost|127\.|\[::1\])#i', $tautan);

$pageTitle = 'QR Formulir';
$activeMenu = 'qr';
require dirname(__DIR__) . '/includes/header.php';
?>

<?php if ($lokal): ?>
    <div class="alert alert-warning no-print">
        Tautan ini memakai <strong>localhost</strong>, jadi hanya bisa dibuka dari komputer ini. Agar QR bisa dipindai
        dari HP peserta, buka halaman ini lewat alamat IP/domain yang bisa dijangkau HP (mis. <code>http://192.168.x.x/regis-acara/admin/qr-formulir.php</code>)
        — QR selalu mengikuti alamat yang sedang dibuka.
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6 mb-4 no-print">
        <section class="card h-100">
            <header class="card-header">
                <h2 class="card-title">Tautan Pendaftaran</h2>
                <p class="card-subtitle">Peserta yang memindai QR ini langsung membuka formulir pendaftaran.</p>
            </header>
            <div class="card-body">
                <div class="form-group">
                    <label for="tautanFormulir">Tautan</label>
                    <div class="input-group">
                        <input type="text" id="tautanFormulir" class="form-control" value="<?= h($tautan) ?>" readonly>
                        <button type="button" class="btn btn-default" id="tombolSalin"><i class="fas fa-copy"></i> Salin</button>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary" id="tombolUnduh"><i class="fas fa-download"></i> Unduh QR (PNG)</button>
                    <button type="button" class="btn btn-default" onclick="window.print()"><i class="fas fa-print"></i> Cetak Poster</button>
                    <a href="<?= h($tautan) ?>" target="_blank" rel="noopener" class="btn btn-default"><i class="fas fa-up-right-from-square"></i> Buka Formulir</a>
                </div>
                <span class="help-block mt-3">Setiap pendaftar otomatis mendapat QR pribadi bertoken di halaman kartunya. QR itulah yang dipindai petugas lewat tombol <em>Scan QR</em> di Cek Pendaftaran.</span>
            </div>
        </section>
    </div>

    <div class="col-lg-6 mb-4">
        <section class="card h-100 poster-qr">
            <div class="card-body text-center">
                <h3 class="poster-judul"><?= h(ACARA_NAMA) ?></h3>
                <?php if (ACARA_LOKASI !== ''): ?><p class="text-muted mb-2"><?= h(ACARA_LOKASI) ?></p><?php endif; ?>
                <p class="poster-ajak">Scan untuk mendaftar lomba<br><strong><?= h(implode(' / ', JENIS_LOMBA)) ?></strong></p>
                <div class="poster-kotak-qr"><canvas id="qrFormulir" width="300" height="300" role="img" aria-label="QR code tautan pendaftaran"></canvas></div>
                <p class="poster-tautan"><?= h($tautan) ?></p>
            </div>
        </section>
    </div>
</div>

<?php ob_start(); ?>
<script src="<?= asset('assets/vendor/qrcode.min.js') ?>"></script>
<script>
(function () {
    var tautan = <?= json_encode($tautan) ?>;
    var acara = <?= json_encode(ACARA_NAMA) ?>;
    QRCode.toCanvas(document.getElementById('qrFormulir'), tautan, { width: 300, margin: 2, errorCorrectionLevel: 'M' });

    document.getElementById('tombolUnduh').addEventListener('click', function () {
        var c = document.createElement('canvas');
        var W = 800, H = 980;
        c.width = W; c.height = H;
        var g = c.getContext('2d');
        g.fillStyle = '#fff'; g.fillRect(0, 0, W, H);
        g.textAlign = 'center';
        g.fillStyle = '#111827'; g.font = '700 40px Poppins, sans-serif';
        g.fillText(acara, W / 2, 80, W - 60);
        g.fillStyle = '#4b5563'; g.font = '500 26px Poppins, sans-serif';
        g.fillText('Scan untuk mendaftar lomba', W / 2, 130);
        var qr = document.createElement('canvas');
        QRCode.toCanvas(qr, tautan, { width: 640, margin: 2, errorCorrectionLevel: 'M' }, function () {
            g.drawImage(qr, (W - 640) / 2, 160);
            g.fillStyle = '#6b7280'; g.font = '400 20px Poppins, sans-serif';
            g.fillText(tautan, W / 2, 850, W - 60);
            var a = document.createElement('a');
            a.download = 'qr-pendaftaran.png';
            a.href = c.toDataURL('image/png');
            document.body.appendChild(a); a.click(); a.remove();
        });
    });

    var tombol = document.getElementById('tombolSalin');
    tombol.addEventListener('click', function () {
        var input = document.getElementById('tautanFormulir');
        function ok() { tombol.innerHTML = '<i class="fas fa-check"></i> Tersalin'; setTimeout(function () { tombol.innerHTML = '<i class="fas fa-copy"></i> Salin'; }, 2000); }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(tautan).then(ok);
        } else {
            input.select(); document.execCommand('copy'); ok();
        }
    });
})();
</script>
<?php
$skripHalaman = ob_get_clean();
require dirname(__DIR__) . '/includes/footer.php';
