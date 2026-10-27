<?php
/**
 * Bootstrap bersama: config, koneksi database, sesi, dan fungsi bantu.
 * Dimuat oleh SEMUA halaman - publik maupun petugas.
 */
// config.php tidak ikut di git; tanpa itu arahkan ke setup, bukan halaman galat.
if (!is_file(dirname(__DIR__) . '/config.php')) {
    $akar = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    header('Location: ' . (substr($akar, -6) === '/admin' ? substr($akar, 0, -6) : $akar) . '/setup.php');
    exit;
}
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/aturan.php';

date_default_timezone_set(APP_TIMEZONE);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

function koneksi_https()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

if (session_status() === PHP_SESSION_NONE) {
    // Strict mode: ID sesi buatan penyerang yang belum pernah diterbitkan server ditolak.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('REGIS_ACARA');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => koneksi_https(), 'path' => BASE_URL . '/']);
    session_start();
}

// Naikkan bila schema_migrasi() mendapat langkah baru.
const SKEMA_VERSI = 2;

const JENIS_LOMBA = [
    'challenge' => 'Challenge',
    'free_play' => 'Free Play',
];

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            // Jam MySQL disamakan dengan jam PHP supaya NOW() dan date() tidak berselisih.
            $pdo->exec("SET time_zone = '" . date('P') . "'");

            // Perubahan skema sesudah setup (tabel pembatas, kolom token) dipasang
            // sekali; penandanya berkas di includes/ yang tidak ikut git.
            $penanda = __DIR__ . '/.skema-' . SKEMA_VERSI;
            if (!is_file($penanda)) {
                require_once __DIR__ . '/schema.php';
                schema_migrasi($pdo);
                @file_put_contents($penanda, date('c'));
            }
        } catch (PDOException $e) {
            http_response_code(503);
            exit('Database belum siap. Jalankan setup.php untuk memasang database, atau periksa kredensial di config.php.'
                . (APP_DEBUG ? ' (' . $e->getMessage() . ')' : ''));
        }
    }
    return $pdo;
}

function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Nilai PHP -> literal JavaScript yang aman ditaruh di dalam <script>.
 * JSON_HEX_TAG mengubah < dan > jadi </>, sehingga isian peserta
 * seperti "<!--<script>" tidak bisa mengubah cara browser membaca blok script.
 */
function js($v)
{
    return json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

/**
 * Sel CSV yang aman dibuka di Excel/Sheets: isian yang diawali = + - @ (atau
 * tab/CR) dianggap rumus oleh spreadsheet - nama "=HYPERLINK(...)" akan
 * menjadi tautan aktif di komputer panitia. Diberi petik di depan supaya
 * terbaca sebagai teks.
 */
function csv_aman($v)
{
    $v = (string) $v;
    return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
}

function ip_klien()
{
    // Sengaja HANYA REMOTE_ADDR: X-Forwarded-For bisa diisi sembarang oleh pengirim.
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/**
 * Pembatas percobaan berbasis database (bukan sesi - sesi bisa dibuang
 * penyerang dengan menghapus cookie). Mengembalikan true bila $kunci sudah
 * mencapai $batas kejadian dalam $detik terakhir.
 */
function pembatas_penuh($kunci, $batas, $detik)
{
    $st = db()->prepare('SELECT COUNT(*) FROM pembatas WHERE kunci = ? AND waktu > (NOW() - INTERVAL ? SECOND)');
    $st->execute([$kunci, (int) $detik]);
    return (int) $st->fetchColumn() >= $batas;
}

function pembatas_catat($kunci)
{
    db()->prepare('INSERT INTO pembatas (kunci, waktu) VALUES (?, NOW())')->execute([$kunci]);
    // Bersih-bersih sesekali supaya tabelnya tidak membengkak.
    if (mt_rand(1, 50) === 1) {
        db()->exec('DELETE FROM pembatas WHERE waktu < (NOW() - INTERVAL 1 DAY)');
    }
}

function pembatas_hapus($kunci)
{
    db()->prepare('DELETE FROM pembatas WHERE kunci = ?')->execute([$kunci]);
}

/** Versi PHP yang masih rentan PHP2Shell (CVE-2026-109618/109619). */
function php_rentan_php2shell()
{
    $aman = ['8.2' => '8.2.35', '8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'];
    $cabang = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    return !isset($aman[$cabang]) || version_compare(PHP_VERSION, $aman[$cabang], '<');
}

function url($path = '')
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Aset diberi ?v=<waktu ubah> supaya browser tidak memakai salinan lama. */
function asset($path)
{
    $file = dirname(__DIR__) . '/' . ltrim($path, '/');
    return url($path) . '?v=' . (int) @filemtime($file);
}

/**
 * Alamat LENGKAP (https://host/...) - dipakai di isi QR code, yang dibuka dari
 * HP lain dan karena itu tidak bisa memakai path relatif.
 */
function url_penuh($path = '')
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (koneksi_https() ? 'https' : 'http') . '://' . $host . url($path);
}

function token_baru()
{
    return bin2hex(random_bytes(16));
}

function token_valid($t)
{
    return is_string($t) && preg_match('/^[a-f0-9]{32}$/', $t);
}

/** Halaman kartu peserta (publik, kuncinya token). */
function url_kartu($token, $penuh = false)
{
    $p = 'kartu.php?t=' . $token;
    return $penuh ? url_penuh($p) : url($p);
}

/** Isi QR di kartu peserta: langsung membuka halaman cek petugas. */
function url_cek($token)
{
    return url_penuh('admin/cek.php?t=' . $token);
}

function redirect($path)
{
    header('Location: ' . (preg_match('#^https?://|^/#', $path) ? $path : url($path)));
    exit;
}

function flash_set($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get()
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_valid()
{
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && $t !== '' && hash_equals(csrf_token(), $t);
}

/** Nomor WA disimpan seragam berawalan 62, supaya pencarian & cek ganda tidak meleset. */
function normalisasi_wa($wa)
{
    $d = preg_replace('/\D+/', '', (string) $wa);
    if (strpos($d, '0') === 0) {
        $d = '62' . substr($d, 1);
    } elseif (strpos($d, '8') === 0) {
        $d = '62' . $d;
    }
    return $d;
}

function format_wa($wa)
{
    return strpos($wa, '62') === 0 ? '0' . substr($wa, 2) : $wa;
}

function kode_peserta($id)
{
    return KODE_AWALAN . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

function jenis_label($jenis)
{
    return JENIS_LOMBA[$jenis] ?? $jenis;
}

function tgl_indo($datetime, $denganJam = true)
{
    if (!$datetime) {
        return '-';
    }
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($datetime);
    return date('j', $t) . ' ' . $bulan[(int) date('n', $t)] . ' ' . date('Y', $t) . ($denganJam ? ', ' . date('H:i', $t) : '');
}

function sesi_semua($hanyaAktif = false)
{
    $sql = 'SELECT * FROM sesi' . ($hanyaAktif ? ' WHERE aktif = 1' : '') . ' ORDER BY jam_mulai, id';
    return db()->query($sql)->fetchAll();
}

/** Sesi yang sedang berjalan pada jam tertentu (bawaan: sekarang), atau null. */
function sesi_pada_jam($jam = null)
{
    $jam = $jam ?: date('H:i:s');
    foreach (sesi_semua(true) as $s) {
        if ($jam >= $s['jam_mulai'] && $jam < $s['jam_selesai']) {
            return $s;
        }
    }
    return null;
}

function sesi_label($s)
{
    return $s['nama'] . ' (' . substr($s['jam_mulai'], 0, 5) . '–' . substr($s['jam_selesai'], 0, 5) . ')';
}

/**
 * Tandai peserta hadir. SATU jalur untuk tombol Hadir (ajax.php) maupun hasil
 * scan QR (admin/cek.php). Mengembalikan ['ok' => bool, 'pesan' => ..., 'keterangan' => ...].
 */
function peserta_tandai_hadir($id, $sesiId, array $akun)
{
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM sesi WHERE id = ? AND aktif = 1');
    $st->execute([(int) $sesiId]);
    $sesi = $st->fetch();
    if (!$sesi) {
        return ['ok' => false, 'pesan' => 'Sesi tidak valid. Pilih sesi yang tersedia.'];
    }

    // Syarat status='terdaftar' di WHERE: dua petugas yang menekan Hadir bersamaan
    // tidak bisa mencatat peserta yang sama dua kali.
    $st = $pdo->prepare("UPDATE peserta SET status = 'hadir', hadir_pada = NOW(), sesi_id = ?, hadir_oleh = ?
                         WHERE id = ? AND status = 'terdaftar'");
    $st->execute([(int) $sesiId, $akun['id'], (int) $id]);
    if ($st->rowCount() === 0) {
        $st = $pdo->prepare('SELECT p.hadir_pada, t.nama AS petugas_nama FROM peserta p LEFT JOIN petugas t ON t.id = p.hadir_oleh WHERE p.id = ?');
        $st->execute([(int) $id]);
        $p = $st->fetch();
        if (!$p) {
            return ['ok' => false, 'pesan' => 'Peserta tidak ditemukan — mungkin sudah dihapus.'];
        }
        return ['ok' => false, 'pesan' => 'Peserta ini sudah ditandai hadir pada ' . tgl_indo($p['hadir_pada'])
            . ' oleh ' . ($p['petugas_nama'] ?: 'petugas lain') . '.'];
    }
    return ['ok' => true, 'keterangan' => tgl_indo(date('Y-m-d H:i:s')) . ' · ' . $sesi['nama'] . ' · ' . $akun['nama']];
}

/* ---------------- login petugas ---------------- */

function petugas_login()
{
    return $_SESSION['petugas'] ?? null;
}

function is_admin()
{
    $p = petugas_login();
    return $p && $p['peran'] === 'admin';
}

/** Wajib login. Akun yang dinonaktifkan di tengah sesi langsung dikeluarkan. */
function wajib_login($hanyaAdmin = false)
{
    $p = petugas_login();
    // Sesi yang menganggur lebih dari 8 jam (lebih panjang dari satu hari acara) berakhir.
    if ($p && time() - (int) ($_SESSION['aktif_terakhir'] ?? 0) > 8 * 3600) {
        $p = null;
    }
    if ($p) {
        $_SESSION['aktif_terakhir'] = time();
        $st = db()->prepare('SELECT id, nama, username, peran FROM petugas WHERE id = ? AND aktif = 1');
        $st->execute([$p['id']]);
        $p = $st->fetch() ?: null;
        $_SESSION['petugas'] = $p;
    }
    if (!$p) {
        unset($_SESSION['petugas']);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            json_keluar(['ok' => false, 'pesan' => 'Sesi berakhir. Muat ulang halaman lalu login kembali.'], 401);
        }
        redirect('admin/login.php?kembali=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    // Akun dengan password lemah/bawaan hanya boleh membuka halaman Ganti Password.
    if (!empty($_SESSION['wajib_ganti_password']) && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'password.php') {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            json_keluar(['ok' => false, 'pesan' => 'Ganti password Anda dulu di menu Ganti Password.'], 403);
        }
        redirect('admin/password.php');
    }
    if ($hanyaAdmin && $p['peran'] !== 'admin') {
        http_response_code(403);
        flash_set('error', 'Halaman itu hanya untuk administrator.');
        redirect('admin/index.php');
    }
    return $p;
}

function json_keluar($data, $kode = 200)
{
    http_response_code($kode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
