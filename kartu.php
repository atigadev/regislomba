<?php
/**
 * KARTU PESERTA - kode registrasi + QR code, dibuka dengan token.
 *
 * Ini juga halaman sukses setelah mendaftar (?baru=1). Alamatnya bisa dibuka
 * lagi kapan saja, jadi peserta yang kehilangan screenshot tinggal membuka
 * tautannya. Tokennya acak 32 karakter: kode LMB-00001 berurutan dan bisa
 * ditebak, token tidak - jadi data peserta lain tidak bisa diintip.
 *
 * QR berisi alamat halaman cek petugas (admin/cek.php?t=...). Dipindai dari
 * aplikasi petugas atau kamera HP biasa, keduanya langsung membuka data
 * peserta ini - halaman itu sendiri tetap mewajibkan login petugas.
 */
require_once __DIR__ . '/includes/app.php';

$token = $_GET['t'] ?? '';
$p = null;
if (token_valid($token)) {
    $st = db()->prepare('SELECT p.*, s.nama AS sesi_nama FROM peserta p LEFT JOIN sesi s ON s.id = p.sesi_id WHERE p.token = ?');
    $st->execute([$token]);
    $p = $st->fetch() ?: null;
}
if (!$p) {
    http_response_code(404);
}
$baru = isset($_GET['baru']);
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1b1e28">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $p ? 'Kartu ' . h($p['kode']) : 'Kartu tidak ditemukan' ?> - <?= h(ACARA_NAMA) ?></title>
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/form-publik.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/form-lomba.css') ?>">
</head>

<body>
    <div class="fp-bungkus">
        <?php if (!$p): ?>
            <div class="fp-kartu fp-pesan-besar">
                <h1>Kartu tidak ditemukan</h1>
                <p>Tautan kartu tidak dikenali. Periksa kembali tautan yang Anda simpan, atau hubungi panitia
                    dengan menyebutkan nama dan nomor WhatsApp Anda.</p>
            </div>
        <?php else: ?>
            <div class="fp-kartu fp-sukses">
                <?php if ($baru): ?>
                    <div class="fp-ceklis">&#10003;</div>
                    <h1>Pendaftaran Berhasil</h1>
                <?php else: ?>
                    <h1><?= h(ACARA_NAMA) ?></h1>
                <?php endif; ?>
                <p>
                    <?= $baru ? 'Terima kasih, ' : '' ?><strong><?= h($p['nama']) ?></strong><?= $baru ? '. Anda terdaftar di lomba' : ' — lomba' ?>
                    <strong><?= h(jenis_label($p['jenis_lomba'])) ?></strong>.
                </p>

                <div class="fl-kode">
                    <span>Kode Registrasi</span>
                    <strong><?= h($p['kode']) ?></strong>
                    <div class="fl-qr">
                        <canvas id="qrPeserta" width="220" height="220" role="img"
                            aria-label="QR code kode registrasi <?= h($p['kode']) ?>"></canvas>
                    </div>
                    <?php if ($p['status'] === 'hadir'): ?>
                        <div class="fl-status hadir">&#10003; Sudah hadir · <?= h(tgl_indo($p['hadir_pada'])) ?><?= $p['sesi_nama'] ? ' · ' . h($p['sesi_nama']) : '' ?></div>
                    <?php else: ?>
                        <div class="fl-status">Belum dikonfirmasi petugas</div>
                    <?php endif; ?>
                </div>

                <p>Tunjukkan QR code ini ke petugas di meja registrasi untuk pengecekan dan konfirmasi kehadiran.</p>

                <div class="fl-aksi">
                    <button type="button" class="fp-tombol" id="tombolUnduh">Unduh Kartu (PNG)</button>
                    <button type="button" class="fl-tombol-2" id="tombolSalin">Salin Tautan Kartu</button>
                </div>
                <p class="fp-bantu">Simpan tautan kartu ini untuk membukanya lagi kapan saja.</p>

                <?php if ($baru): ?>
                    <p class="fp-catatan"><a class="fl-tautan" href="<?= url('index.php') ?>">Daftarkan peserta lain</a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($p): ?>
        <script src="<?= asset('assets/vendor/qrcode.min.js') ?>"></script>
        <script>
        (function () {
            var isiQr = <?= js(url_cek($p['token'])) ?>;
            var tautanKartu = <?= js(url_kartu($p['token'], true)) ?>;
            var kode = <?= js($p['kode']) ?>;
            var nama = <?= js($p['nama']) ?>;
            var lomba = <?= js(jenis_label($p['jenis_lomba'])) ?>;
            var acara = <?= js(ACARA_NAMA) ?>;

            // margin 2 + koreksi galat M: tetap terbaca dari layar HP yang retak atau silau.
            QRCode.toCanvas(document.getElementById('qrPeserta'), isiQr, { width: 220, margin: 2, errorCorrectionLevel: 'M' });

            // Kartu PNG digambar ulang di kanvas tersendiri: QR + kode + nama, supaya
            // gambar yang disimpan di galeri tetap bisa dibaca tanpa membuka halaman ini.
            document.getElementById('tombolUnduh').addEventListener('click', function () {
                var c = document.createElement('canvas');
                var W = 600, H = 820;
                c.width = W; c.height = H;
                var g = c.getContext('2d');
                g.fillStyle = '#ffffff'; g.fillRect(0, 0, W, H);
                g.fillStyle = '#1b1e28'; g.fillRect(0, 0, W, 110);
                g.textAlign = 'center';
                g.fillStyle = '#ffffff'; g.font = '600 28px Poppins, sans-serif';
                g.fillText(acara, W / 2, 66, W - 40);

                var qr = document.createElement('canvas');
                QRCode.toCanvas(qr, isiQr, { width: 400, margin: 2, errorCorrectionLevel: 'M' }, function () {
                    g.drawImage(qr, (W - 400) / 2, 140);
                    g.fillStyle = '#6b7280'; g.font = '500 18px Poppins, sans-serif';
                    g.fillText('KODE REGISTRASI', W / 2, 590);
                    g.fillStyle = '#111827'; g.font = '700 52px Poppins, sans-serif';
                    g.fillText(kode, W / 2, 650);
                    g.font = '600 26px Poppins, sans-serif';
                    g.fillText(nama, W / 2, 712, W - 40);
                    g.fillStyle = '#5c6bc0'; g.font = '600 22px Poppins, sans-serif';
                    g.fillText('Lomba ' + lomba, W / 2, 758);

                    var a = document.createElement('a');
                    a.download = 'kartu-' + kode + '.png';
                    a.href = c.toDataURL('image/png');
                    document.body.appendChild(a); a.click(); a.remove();
                });
            });

            var tombolSalin = document.getElementById('tombolSalin');
            tombolSalin.addEventListener('click', function () {
                function berhasil() {
                    tombolSalin.textContent = 'Tautan tersalin ✓';
                    setTimeout(function () { tombolSalin.textContent = 'Salin Tautan Kartu'; }, 2000);
                }
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(tautanKartu).then(berhasil, function () { prompt('Salin tautan ini:', tautanKartu); });
                } else {
                    prompt('Salin tautan ini:', tautanKartu);
                }
            });
        })();
        </script>
    <?php endif; ?>
</body>

</html>
