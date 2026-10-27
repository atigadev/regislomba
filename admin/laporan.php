<?php
/**
 * LAPORAN KEHADIRAN berdasarkan tanggal & sesi.
 * Yang dihitung adalah peserta yang sudah ditandai HADIR, menurut tanggal
 * hadirnya. Bisa dicetak atau diunduh sebagai CSV (dibuka di Excel).
 */
require_once dirname(__DIR__) . '/includes/app.php';
wajib_login();

$valid = function ($t) { return is_string($t) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) ? $t : ''; };
$dari = $valid($_GET['dari'] ?? '') ?: date('Y-m-d');
$sampai = $valid($_GET['sampai'] ?? '') ?: $dari;
if ($sampai < $dari) { list($dari, $sampai) = [$sampai, $dari]; }
$fSesi = (int) ($_GET['sesi'] ?? 0);
$fJenis = $_GET['jenis'] ?? '';
if (!isset(JENIS_LOMBA[$fJenis])) { $fJenis = ''; }

$where = ["p.status = 'hadir'", 'p.hadir_pada >= ?', 'p.hadir_pada < ?'];
$param = [$dari . ' 00:00:00', date('Y-m-d', strtotime($sampai . ' +1 day')) . ' 00:00:00'];
if ($fSesi > 0) { $where[] = 'p.sesi_id = ?'; $param[] = $fSesi; }
if ($fJenis !== '') { $where[] = 'p.jenis_lomba = ?'; $param[] = $fJenis; }

$st = db()->prepare('SELECT p.*, DATE(p.hadir_pada) AS tgl, s.nama AS sesi_nama, s.jam_mulai, t.nama AS petugas_nama
    FROM peserta p
    LEFT JOIN sesi s ON s.id = p.sesi_id
    LEFT JOIN petugas t ON t.id = p.hadir_oleh
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY p.hadir_pada, p.id');
$st->execute($param);
$rows = $st->fetchAll();

$sesiList = sesi_semua();
$sesiNama = [];
foreach ($sesiList as $s) { $sesiNama[(int) $s['id']] = $s['nama']; }

$periode = $dari === $sampai ? tgl_indo($dari, false) : tgl_indo($dari, false) . ' – ' . tgl_indo($sampai, false);

/* ---------------- unduh CSV ---------------- */
if (($_GET['unduh'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-kehadiran-' . $dari . ($sampai !== $dari ? '_' . $sampai : '') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8
    fputcsv($out, ['No', 'Tanggal Hadir', 'Jam', 'Sesi', 'Kode', 'Nama', 'Umur', 'No. WA', 'Email', 'Domisili', 'Jenis Lomba', 'Tanggal Daftar', 'Petugas'], ';');
    foreach ($rows as $i => $p) {
        fputcsv($out, [
            $i + 1, $p['tgl'], substr($p['hadir_pada'], 11, 5), $p['sesi_nama'] ?: 'Tanpa sesi', $p['kode'], $p['nama'], $p['umur'],
            // Diawali petik supaya Excel tidak mengubah nomor jadi notasi ilmiah.
            "'" . format_wa($p['no_wa']), $p['email'], $p['domisili'], jenis_label($p['jenis_lomba']), $p['dibuat_pada'], $p['petugas_nama'],
        ], ';');
    }
    fclose($out);
    exit;
}

/* ---------------- rekap tanggal x sesi ---------------- */
$rekap = [];      // [tgl][sesi_id][jenis] = n
$perJenis = array_fill_keys(array_keys(JENIS_LOMBA), 0);
foreach ($rows as $p) {
    $sid = (int) $p['sesi_id'];
    $rekap[$p['tgl']][$sid][$p['jenis_lomba']] = ($rekap[$p['tgl']][$sid][$p['jenis_lomba']] ?? 0) + 1;
    $perJenis[$p['jenis_lomba']]++;
}
ksort($rekap);
$urutanSesi = array_keys($sesiNama);

$st = db()->prepare('SELECT COUNT(*) FROM peserta WHERE dibuat_pada >= ? AND dibuat_pada < ?');
$st->execute([$param[0], $param[1]]);
$daftarPeriode = (int) $st->fetchColumn();

$qs = http_build_query(['dari' => $dari, 'sampai' => $sampai, 'sesi' => $fSesi ?: '', 'jenis' => $fJenis]);

$pageTitle = 'Laporan';
$activeMenu = 'laporan';
require dirname(__DIR__) . '/includes/header.php';
?>

<section class="card mb-4 no-print">
    <header class="card-header">
        <h2 class="card-title">Periode Laporan</h2>
        <p class="card-subtitle">Menghitung peserta yang sudah ditandai hadir, menurut tanggal dan sesi kehadirannya.</p>
    </header>
    <div class="card-body">
        <form method="get" class="baris-filter">
            <div class="form-group">
                <label for="dari">Dari Tanggal</label>
                <input type="date" id="dari" name="dari" class="form-control" value="<?= h($dari) ?>">
            </div>
            <div class="form-group">
                <label for="sampai">Sampai Tanggal</label>
                <input type="date" id="sampai" name="sampai" class="form-control" value="<?= h($sampai) ?>">
            </div>
            <div class="form-group">
                <label for="sesi">Sesi</label>
                <select id="sesi" name="sesi" class="form-select">
                    <option value="">Semua sesi</option>
                    <?php foreach ($sesiList as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $fSesi === (int) $s['id'] ? 'selected' : '' ?>><?= h(sesi_label($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="jenis">Jenis Lomba</label>
                <select id="jenis" name="jenis" class="form-select">
                    <option value="">Semua lomba</option>
                    <?php foreach (JENIS_LOMBA as $k => $label): ?>
                        <option value="<?= h($k) ?>" <?= $fJenis === $k ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="tombol">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Tampilkan</button>
                <a href="?<?= h($qs) ?>&amp;unduh=csv" class="btn btn-default"><i class="fas fa-file-csv"></i> CSV</a>
                <button type="button" class="btn btn-default" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
            </div>
        </form>
    </div>
</section>

<div class="judul-cetak mb-3">
    <h3 class="mb-0"><?= h(ACARA_NAMA) ?> — Laporan Kehadiran</h3>
    <div>Periode: <?= h($periode) ?><?= $fSesi ? ' · ' . h($sesiNama[$fSesi] ?? '') : '' ?><?= $fJenis ? ' · ' . h(jenis_label($fJenis)) : '' ?></div>
    <small>Dicetak <?= h(tgl_indo(date('Y-m-d H:i:s'))) ?> oleh <?= h(petugas_login()['nama']) ?></small>
</div>

<div class="kotak-angka">
    <div class="kotak ok">
        <div class="label">Total Hadir</div>
        <div class="nilai"><?= count($rows) ?></div>
        <div class="sub"><?= h($periode) ?></div>
    </div>
    <?php foreach (JENIS_LOMBA as $k => $label): ?>
        <div class="kotak aksen">
            <div class="label"><?= h($label) ?></div>
            <div class="nilai"><?= $perJenis[$k] ?></div>
            <div class="sub">dari <?= count($rows) ?> peserta hadir</div>
        </div>
    <?php endforeach; ?>
    <div class="kotak">
        <div class="label">Mendaftar di Periode Ini</div>
        <div class="nilai"><?= $daftarPeriode ?></div>
        <div class="sub">semua status, menurut tanggal daftar</div>
    </div>
</div>

<section class="card mb-4">
    <header class="card-header">
        <h2 class="card-title">Rekap per Tanggal &amp; Sesi</h2>
        <p class="card-subtitle"><?= h($periode) ?><?= $fSesi || $fJenis ? ' — disaring, bukan seluruh sesi/lomba.' : '' ?></p>
    </header>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Sesi</th>
                        <?php foreach (JENIS_LOMBA as $label): ?><th class="text-end"><?= h($label) ?></th><?php endforeach; ?>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rekap): ?>
                        <tr><td colspan="<?= 3 + count(JENIS_LOMBA) ?>" class="text-center text-muted">Belum ada peserta yang hadir pada periode ini. Ubah tanggal di atas, atau tandai kehadiran di menu Cek Pendaftaran.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rekap as $tgl => $perSesi):
                        $ids = array_values(array_filter($urutanSesi, function ($id) use ($perSesi) { return isset($perSesi[$id]); }));
                        if (isset($perSesi[0])) { $ids[] = 0; }
                        $sub = array_fill_keys(array_keys(JENIS_LOMBA), 0);
                        foreach ($ids as $i => $sid):
                            $jml = 0;
                    ?>
                        <tr>
                            <?php if ($i === 0): ?>
                                <td rowspan="<?= count($ids) + 1 ?>"><strong><?= h(tgl_indo($tgl, false)) ?></strong></td>
                            <?php endif; ?>
                            <td><?= h($sid ? ($sesiNama[$sid] ?? 'Sesi #' . $sid) : 'Tanpa sesi') ?></td>
                            <?php foreach (JENIS_LOMBA as $k => $label): $n = $perSesi[$sid][$k] ?? 0; $jml += $n; $sub[$k] += $n; ?>
                                <td class="text-end"><?= $n ?></td>
                            <?php endforeach; ?>
                            <td class="text-end"><?= $jml ?></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr>
                            <td class="text-muted"><em>Subtotal</em></td>
                            <?php foreach ($sub as $n): ?><td class="text-end"><strong><?= $n ?></strong></td><?php endforeach; ?>
                            <td class="text-end"><strong><?= array_sum($sub) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($rekap): ?>
                    <tfoot>
                        <tr>
                            <th colspan="2">Total</th>
                            <?php foreach ($perJenis as $n): ?><th class="text-end"><?= $n ?></th><?php endforeach; ?>
                            <th class="text-end"><?= count($rows) ?></th>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</section>

<section class="card">
    <header class="card-header">
        <h2 class="card-title">Daftar Peserta Hadir</h2>
        <p class="card-subtitle"><?= count($rows) ?> peserta</p>
    </header>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="tabelLaporan">
                <thead>
                    <tr>
                        <th class="text-end">No</th>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Sesi</th>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th class="text-end">Umur</th>
                        <th>No. WA</th>
                        <th>Email</th>
                        <th>Domisili</th>
                        <th>Lomba</th>
                        <th>Petugas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $i => $p): ?>
                        <tr>
                            <td class="text-end"><?= $i + 1 ?></td>
                            <td data-order="<?= h($p['tgl']) ?>"><?= h(tgl_indo($p['tgl'], false)) ?></td>
                            <td><?= h(substr($p['hadir_pada'], 11, 5)) ?></td>
                            <td><?= h($p['sesi_nama'] ?: 'Tanpa sesi') ?></td>
                            <td><code><?= h($p['kode']) ?></code></td>
                            <td><strong><?= h($p['nama']) ?></strong></td>
                            <td class="text-end"><?= (int) $p['umur'] ?></td>
                            <td><?= h(format_wa($p['no_wa'])) ?></td>
                            <td><?= h($p['email']) ?></td>
                            <td><?= h($p['domisili']) ?></td>
                            <td><span class="badge badge-<?= h($p['jenis_lomba']) ?>"><?= h(jenis_label($p['jenis_lomba'])) ?></span></td>
                            <td><?= h($p['petugas_nama'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php
ob_start();
?>
<script>
(function () {
    var tabel = new DataTable('#tabelLaporan', {
        order: [],
        pageLength: 50,
        language: {
            search: 'Cari:',
            lengthMenu: '_MENU_ per halaman',
            info: '_START_–_END_ dari _TOTAL_ baris',
            infoEmpty: 'Tidak ada baris',
            infoFiltered: '(disaring dari _MAX_)',
            zeroRecords: 'Tidak ditemukan.',
            emptyTable: 'Belum ada peserta yang hadir pada periode ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });
    // Saat dicetak, seluruh baris ikut - bukan hanya halaman tabel yang sedang tampil.
    var panjangAsal = null;
    window.addEventListener('beforeprint', function () { panjangAsal = tabel.page.len(); tabel.page.len(-1).draw(); });
    window.addEventListener('afterprint', function () { if (panjangAsal) { tabel.page.len(panjangAsal).draw(); } });
})();
</script>
<?php
$skripHalaman = ob_get_clean();
require dirname(__DIR__) . '/includes/footer.php';
