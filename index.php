<?php
/**
 * FORMULIR PENDAFTARAN PESERTA - dibuka peserta tanpa login.
 *
 * Temanya berdiri sendiri (assets/form-publik.css dari SatuRegis), tidak memuat
 * Porto: yang membukanya peserta di HP, dan satu formulir tidak perlu seluruh
 * tema admin.
 *
 * Setelah terkirim peserta diarahkan ke kartu.php: KODE REGISTRASI + QR code
 * bertoken, yang dipindai petugas di meja registrasi lalu ditandai hadir.
 */
require_once __DIR__ . '/includes/app.php';

$pesan = '';
$isi = ['nama' => '', 'umur' => '', 'no_wa' => '', 'email' => '', 'domisili' => '', 'jenis_lomba' => ''];

if (PENDAFTARAN_DIBUKA && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($isi as $k => $v) {
        $isi[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $wa = normalisasi_wa($isi['no_wa']);
    $umur = filter_var($isi['umur'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 120]]);

    $galat = [];
    // Penangkal bot & banjir kiriman. Batas per IP sengaja longgar: di lokasi
    // acara ratusan HP peserta bisa keluar lewat SATU IP WiFi yang sama.
    $kunciIp = 'daftar:' . ip_klien();
    $batasIp = defined('DAFTAR_BATAS_IP') ? (int) DAFTAR_BATAS_IP : 150;
    $mulai = (int) ($_SESSION['form_mulai'] ?? 0);
    if (pembatas_penuh($kunciIp, $batasIp, 600)) {
        $galat[] = 'Terlalu banyak pendaftaran dari jaringan ini dalam 10 menit terakhir. Coba lagi beberapa menit lagi, atau hubungi panitia.';
    } elseif (!csrf_valid()) {
        $galat[] = 'Halaman sudah terlalu lama dibuka. Silakan kirim ulang formulirnya.';
    } elseif (trim((string) ($_POST['situs'] ?? '')) !== '' || !$mulai || time() - $mulai < 3) {
        // Isian "situs" tersembunyi dari manusia; yang mengisinya, atau yang
        // mengirim kurang dari 3 detik setelah formulir tampil, hampir pasti bot.
        $galat[] = 'Kiriman tidak dapat diproses. Muat ulang halaman lalu isi formulirnya kembali.';
    }
    pembatas_catat($kunciIp);

    if (!$galat) {
        if ($isi['nama'] === '' || mb_strlen($isi['nama']) > 100) {
            $galat[] = 'Nama wajib diisi (maksimal 100 karakter).';
        }
        if ($umur === false) {
            $galat[] = 'Umur wajib diisi dengan angka 1–120.';
        }
        if (strlen($wa) < 10 || strlen($wa) > 15) {
            $galat[] = 'No. WhatsApp tidak valid. Contoh: 081234567890.';
        }
        if (!filter_var($isi['email'], FILTER_VALIDATE_EMAIL) || strlen($isi['email']) > 150) {
            $galat[] = 'Email tidak valid. Contoh: nama@email.com.';
        }
        if ($isi['domisili'] === '' || mb_strlen($isi['domisili']) > 100) {
            $galat[] = 'Domisili wajib diisi (kota/kabupaten).';
        }
        if (!isset(JENIS_LOMBA[$isi['jenis_lomba']])) {
            $galat[] = 'Pilih jenis lomba: Challenge atau Free Play.';
        }
    }

    // Kunci per (WA, lomba) selama cek-ganda + simpan: dua kiriman serentak
    // (dobel ketuk, sinyal lemot) tidak bisa sama-sama lolos cek lalu tersimpan dua kali.
    $kunciGanda = 'regis-daftar-' . md5($wa . '|' . $isi['jenis_lomba']);
    if (!$galat) {
        db()->query('SELECT GET_LOCK(' . db()->quote($kunciGanda) . ', 5)');
        // Satu nomor WA tidak bisa punya dua pendaftaran yang sama-sama belum
        // hadir untuk lomba yang sama - yang kedua hampir selalu kiriman ganda.
        $st = db()->prepare("SELECT kode FROM peserta WHERE no_wa = ? AND jenis_lomba = ? AND status = 'terdaftar' LIMIT 1");
        $st->execute([$wa, $isi['jenis_lomba']]);
        $ada = $st->fetchColumn();
        if ($ada) {
            $galat[] = 'Nomor WhatsApp ini sudah terdaftar untuk ' . jenis_label($isi['jenis_lomba'])
                . ' dengan kode ' . $ada . '. Tunjukkan kode tersebut ke petugas.';
        }
    }

    if ($galat) {
        db()->query('SELECT RELEASE_LOCK(' . db()->quote($kunciGanda) . ')');
        $pesan = implode(' ', $galat);
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        $token = token_baru();
        $st = $pdo->prepare('INSERT INTO peserta (token, nama, umur, no_wa, email, domisili, jenis_lomba) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $st->execute([$token, $isi['nama'], $umur, $wa, strtolower($isi['email']), $isi['domisili'], $isi['jenis_lomba']]);
        $id = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE peserta SET kode = ? WHERE id = ?')->execute([kode_peserta($id), $id]);
        $pdo->commit();
        $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote($kunciGanda) . ')');

        // Post/Redirect/Get ke kartu peserta: muat ulang tidak mengirim formulir
        // dua kali, dan alamatnya bisa disimpan untuk membuka QR-nya lagi nanti.
        redirect('kartu.php?t=' . $token . '&baru=1');
    }
}
// Waktu formulir tampil, untuk menolak kiriman yang terlalu cepat (bot).
$_SESSION['form_mulai'] = time();
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1b1e28">
    <title>Pendaftaran <?= h(ACARA_NAMA) ?></title>
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/form-publik.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/form-lomba.css') ?>">
</head>

<body>
    <div class="fp-bungkus">

        <?php if (!PENDAFTARAN_DIBUKA): ?>
            <div class="fp-kartu fp-pesan-besar">
                <h1>Pendaftaran sedang ditutup</h1>
                <p>Pendaftaran untuk <strong><?= h(ACARA_NAMA) ?></strong> belum dibuka atau sudah berakhir.
                    Silakan hubungi panitia.</p>
            </div>

        <?php else: ?>
            <div class="fp-kartu">
                <header class="fp-kepala">
                    <h1><?= h(ACARA_NAMA) ?></h1>
                    <?php if (ACARA_LOKASI !== ''): ?>
                        <p class="fp-sub"><?= h(ACARA_LOKASI) ?></p>
                    <?php endif; ?>
                    <p class="fp-sub">Formulir Pendaftaran Peserta Lomba</p>
                </header>

                <?php if ($pesan !== ''): ?>
                    <div class="fp-alert galat" role="alert"><?= h($pesan) ?></div>
                <?php endif; ?>

                <form method="post" class="fp-form" novalidate>
                    <?= csrf_field() ?>
                    <!-- Jebakan bot: tidak terlihat & tidak bisa difokus manusia. -->
                    <div class="fl-jebakan" aria-hidden="true">
                        <label for="f_situs">Situs web</label>
                        <input type="text" id="f_situs" name="situs" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="fp-grup">
                        <label for="f_nama">Nama Lengkap <span class="fp-wajib">*</span></label>
                        <input type="text" id="f_nama" name="nama" maxlength="100" required autocomplete="name"
                            value="<?= h($isi['nama']) ?>">
                    </div>

                    <div class="fp-grup">
                        <label for="f_umur">Umur <span class="fp-wajib">*</span></label>
                        <input type="number" id="f_umur" name="umur" min="1" max="120" inputmode="numeric" required
                            value="<?= h($isi['umur']) ?>">
                    </div>

                    <div class="fp-grup">
                        <label for="f_wa">No. WhatsApp <span class="fp-wajib">*</span></label>
                        <input type="tel" id="f_wa" name="no_wa" maxlength="20" inputmode="tel" required autocomplete="tel"
                            placeholder="081234567890" value="<?= h($isi['no_wa']) ?>">
                        <p class="fp-bantu">Nomor aktif WhatsApp, dipakai panitia untuk menghubungi Anda.</p>
                    </div>

                    <div class="fp-grup">
                        <label for="f_email">Email <span class="fp-wajib">*</span></label>
                        <input type="email" id="f_email" name="email" maxlength="150" required autocomplete="email"
                            placeholder="nama@email.com" value="<?= h($isi['email']) ?>">
                    </div>

                    <div class="fp-grup">
                        <label for="f_domisili">Domisili <span class="fp-wajib">*</span></label>
                        <input type="text" id="f_domisili" name="domisili" maxlength="100" required
                            placeholder="Kota / Kabupaten" value="<?= h($isi['domisili']) ?>">
                    </div>

                    <div class="fp-grup">
                        <span class="fl-label" id="l_jenis">Jenis Lomba <span class="fp-wajib">*</span></span>
                        <div class="fl-pilihan" role="radiogroup" aria-labelledby="l_jenis">
                            <?php foreach (JENIS_LOMBA as $nilai => $label): ?>
                                <label class="fl-opsi">
                                    <input type="radio" name="jenis_lomba" value="<?= h($nilai) ?>" required
                                        <?= $isi['jenis_lomba'] === $nilai ? 'checked' : '' ?>>
                                    <span><?= h($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" class="fp-tombol">Daftar Sekarang</button>
                </form>
            </div>
        <?php endif; ?>

    </div>
</body>

</html>
