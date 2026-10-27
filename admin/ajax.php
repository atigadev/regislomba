<?php
/**
 * Aksi petugas lewat AJAX: hadir, batal (admin), hapus (admin).
 * Selalu menjawab JSON {ok, pesan, ...}.
 */
require_once dirname(__DIR__) . '/includes/app.php';
$akun = wajib_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    json_keluar(['ok' => false, 'pesan' => 'Permintaan ditolak. Muat ulang halaman lalu coba lagi.'], 400);
}

$aksi = $_POST['aksi'] ?? '';
$id = (int) ($_POST['id'] ?? 0);
$pdo = db();

$st = $pdo->prepare('SELECT p.*, t.nama AS petugas_nama FROM peserta p LEFT JOIN petugas t ON t.id = p.hadir_oleh WHERE p.id = ?');
$st->execute([$id]);
$p = $st->fetch();
if (!$p) {
    json_keluar(['ok' => false, 'pesan' => 'Peserta tidak ditemukan — mungkin sudah dihapus. Muat ulang halaman.'], 404);
}

if ($aksi === 'hadir') {
    json_keluar(peserta_tandai_hadir($id, (int) ($_POST['sesi_id'] ?? 0), $akun));
}

if ($aksi === 'batal' || $aksi === 'hapus') {
    if (!is_admin()) {
        json_keluar(['ok' => false, 'pesan' => 'Hanya administrator yang boleh melakukan ini.'], 403);
    }
    if ($aksi === 'batal') {
        $pdo->prepare("UPDATE peserta SET status = 'terdaftar', hadir_pada = NULL, sesi_id = NULL, hadir_oleh = NULL WHERE id = ?")->execute([$id]);
    } else {
        if ($p['status'] === 'hadir') {
            json_keluar(['ok' => false, 'pesan' => 'Peserta yang sudah hadir tidak bisa dihapus. Batalkan kehadirannya dulu.']);
        }
        $pdo->prepare('DELETE FROM peserta WHERE id = ?')->execute([$id]);
    }
    json_keluar(['ok' => true]);
}

json_keluar(['ok' => false, 'pesan' => 'Aksi tidak dikenal.'], 400);
