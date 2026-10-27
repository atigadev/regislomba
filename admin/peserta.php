<?php
/**
 * CEK PENDAFTARAN - petugas mencari peserta (kode / nama / no. WA), memeriksa
 * datanya, lalu menekan Hadir. Sesi diisi otomatis sesuai jam, bisa diganti.
 */
require_once dirname(__DIR__) . '/includes/app.php';
wajib_login();

$fStatus = $_GET['status'] ?? 'terdaftar';
$fJenis = $_GET['jenis'] ?? '';
$fTanggal = $_GET['tanggal'] ?? '';
if (!in_array($fStatus, ['', 'terdaftar', 'hadir'], true)) { $fStatus = 'terdaftar'; }
if (!isset(JENIS_LOMBA[$fJenis])) { $fJenis = ''; }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fTanggal)) { $fTanggal = ''; }

$where = [];
$param = [];
if ($fStatus !== '') { $where[] = 'p.status = ?'; $param[] = $fStatus; }
if ($fJenis !== '') { $where[] = 'p.jenis_lomba = ?'; $param[] = $fJenis; }
if ($fTanggal !== '') { $where[] = 'DATE(p.dibuat_pada) = ?'; $param[] = $fTanggal; }

$sql = 'SELECT p.*, s.nama AS sesi_nama, t.nama AS petugas_nama
        FROM peserta p
        LEFT JOIN sesi s ON s.id = p.sesi_id
        LEFT JOIN petugas t ON t.id = p.hadir_oleh'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY p.id DESC';
$st = db()->prepare($sql);
$st->execute($param);
$rows = $st->fetchAll();
$totalSemua = (int) db()->query('SELECT COUNT(*) FROM peserta')->fetchColumn();
// Saringan yang aktif disebut dengan kata-kata: status "Belum hadir" adalah
// bawaan halaman ini, jadi tanpa keterangan petugas mengira tidak ada saringan
// padahal sebagian data tersembunyi.
$ketFilter = [];
if ($fStatus !== '') { $ketFilter[] = $fStatus === 'hadir' ? 'sudah hadir' : 'belum hadir'; }
if ($fJenis !== '') { $ketFilter[] = 'lomba ' . jenis_label($fJenis); }
if ($fTanggal !== '') { $ketFilter[] = 'daftar ' . tgl_indo($fTanggal, false); }

$sesiAktif = sesi_semua(true);
$sesiKini = sesi_pada_jam();

$pageTitle = 'Cek Pendaftaran';
$activeMenu = 'peserta';
require dirname(__DIR__) . '/includes/header.php';
?>

<section class="card mb-4">
    <header class="card-header">
        <div class="card-actions">
            <button type="button" class="btn btn-primary" id="tombolScan"><i class="fas fa-qrcode"></i> Scan QR</button>
        </div>
        <h2 class="card-title">Saring Data</h2>
        <p class="card-subtitle">Bawaan: hanya peserta yang belum hadir. Pilih Status "Semua status" untuk melihat yang sudah hadir juga.</p>
    </header>
    <div class="card-body">
        <form method="get" class="baris-filter">
            <div class="form-group">
                <label for="fStatus">Status</label>
                <select id="fStatus" name="status" class="form-select">
                    <option value="terdaftar" <?= $fStatus === 'terdaftar' ? 'selected' : '' ?>>Belum hadir</option>
                    <option value="hadir" <?= $fStatus === 'hadir' ? 'selected' : '' ?>>Sudah hadir</option>
                    <option value="" <?= $fStatus === '' ? 'selected' : '' ?>>Semua status</option>
                </select>
            </div>
            <div class="form-group">
                <label for="fJenis">Jenis Lomba</label>
                <select id="fJenis" name="jenis" class="form-select">
                    <option value="">Semua lomba</option>
                    <?php foreach (JENIS_LOMBA as $k => $label): ?>
                        <option value="<?= h($k) ?>" <?= $fJenis === $k ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="fTanggal">Tanggal Daftar</label>
                <input type="date" id="fTanggal" name="tanggal" class="form-control" value="<?= h($fTanggal) ?>">
            </div>
            <div class="tombol">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Terapkan</button>
                <a href="<?= url('admin/peserta.php?status=') ?>" class="btn btn-default">Semua data</a>
            </div>
        </form>
    </div>
</section>

<section class="card">
    <header class="card-header">
        <h2 class="card-title">Data Pendaftar</h2>
        <p class="card-subtitle">
            <?php if ($ketFilter): ?>
                Menampilkan <?= count($rows) ?> dari <?= $totalSemua ?> pendaftar — hanya yang <?= h(implode(', ', $ketFilter)) ?>.
                <a href="<?= url('admin/peserta.php?status=') ?>">Tampilkan semua</a>
            <?php else: ?>
                Menampilkan semua <?= $totalSemua ?> pendaftar.
            <?php endif; ?>
        </p>
    </header>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="tabelPeserta">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th class="text-end">Umur</th>
                        <th>No. WA</th>
                        <th>Email</th>
                        <th>Domisili</th>
                        <th>Lomba</th>
                        <th>Daftar</th>
                        <th>Status</th>
                        <th class="text-end" style="width:190px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $p):
                        $data = [
                            'id' => (int) $p['id'], 'kode' => $p['kode'], 'nama' => $p['nama'], 'umur' => (int) $p['umur'],
                            'wa' => format_wa($p['no_wa']), 'email' => $p['email'], 'domisili' => $p['domisili'],
                            'jenis' => jenis_label($p['jenis_lomba']), 'daftar' => tgl_indo($p['dibuat_pada']),
                        ];
                    ?>
                        <tr data-id="<?= (int) $p['id'] ?>">
                            <td data-search="<?= h($p['kode'] . ' ' . $p['no_wa']) ?>"><code><?= h($p['kode']) ?></code></td>
                            <td><strong><?= h($p['nama']) ?></strong></td>
                            <td class="text-end"><?= (int) $p['umur'] ?></td>
                            <td><a href="https://wa.me/<?= h($p['no_wa']) ?>" target="_blank" rel="noopener"><?= h(format_wa($p['no_wa'])) ?></a></td>
                            <td><?= h($p['email']) ?></td>
                            <td><?= h($p['domisili']) ?></td>
                            <td><span class="badge badge-<?= h($p['jenis_lomba']) ?>"><?= h(jenis_label($p['jenis_lomba'])) ?></span></td>
                            <td data-order="<?= h($p['dibuat_pada']) ?>"><small><?= h(tgl_indo($p['dibuat_pada'])) ?></small></td>
                            <td class="sel-status">
                                <?php if ($p['status'] === 'hadir'): ?>
                                    <span class="badge badge-hadir">Hadir</span>
                                    <br><small class="text-muted"><?= h(tgl_indo($p['hadir_pada'])) ?> · <?= h($p['sesi_nama'] ?: 'Tanpa sesi') ?> · <?= h($p['petugas_nama'] ?: '-') ?></small>
                                <?php else: ?>
                                    <span class="badge badge-terdaftar">Belum hadir</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end sel-aksi">
                                <a href="<?= h(url_kartu($p['token'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-default" title="Buka kartu QR peserta" aria-label="Buka kartu QR peserta"><i class="fas fa-qrcode"></i></a>
                                <?php if ($p['status'] === 'terdaftar'): ?>
                                    <button type="button" class="btn btn-sm btn-success tombol-hadir" data-peserta='<?= h(json_encode($data)) ?>'>
                                        <i class="fas fa-check"></i> Hadir
                                    </button>
                                    <?php if (is_admin()): ?>
                                        <button type="button" class="btn btn-sm btn-danger tombol-hapus" data-id="<?= (int) $p['id'] ?>" data-nama="<?= h($p['nama']) ?>" title="Hapus pendaftaran" aria-label="Hapus pendaftaran">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php elseif (is_admin()): ?>
                                    <button type="button" class="btn btn-sm btn-default tombol-batal" data-id="<?= (int) $p['id'] ?>" data-nama="<?= h($p['nama']) ?>">
                                        <i class="fas fa-rotate-left"></i> Batal hadir
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Modal konfirmasi hadir: petugas melihat seluruh data sekali lagi sebelum menekan Hadir. -->
<div class="modal fade" id="modalHadir" tabindex="-1" aria-hidden="true" aria-labelledby="judulModalHadir">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="judulModalHadir">Konfirmasi Kehadiran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="pesanModal"></div>
                <table class="table table-sm mb-3">
                    <tbody id="detailPeserta"></tbody>
                </table>
                <div class="form-group mb-0">
                    <label for="pilihSesi">Sesi <span class="wajib">*</span></label>
                    <select id="pilihSesi" class="form-select">
                        <?php if (!$sesiAktif): ?>
                            <option value="">Belum ada sesi aktif</option>
                        <?php endif; ?>
                        <?php foreach ($sesiAktif as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= $sesiKini && (int) $sesiKini['id'] === (int) $s['id'] ? 'selected' : '' ?>><?= h(sesi_label($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="help-block">Dipilih otomatis sesuai jam sekarang. Ganti bila peserta masuk sesi lain.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" id="tombolSimpanHadir"><i class="fas fa-check"></i> Tandai Hadir</button>
            </div>
        </div>
    </div>
</div>

<!-- Pemindai QR kamera. Kamera hanya bisa dibuka di https:// atau localhost. -->
<div class="modal fade" id="modalScan" tabindex="-1" aria-hidden="true" aria-labelledby="judulScan">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="judulScan">Scan QR Peserta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="pesanScan"></div>
                <div id="pembaca" class="pembaca-qr"></div>
                <div class="form-group mt-3 mb-0">
                    <label for="pilihKamera">Kamera</label>
                    <select id="pilihKamera" class="form-select"></select>
                </div>
                <form class="d-flex gap-2 mt-3" id="formKodeManual">
                    <input type="text" id="kodeManual" class="form-control" placeholder="Atau ketik kode, mis. <?= h(KODE_AWALAN) ?>00001" aria-label="Kode registrasi">
                    <button type="submit" class="btn btn-default">Cari</button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php
$urlAjax = url('admin/ajax.php');
ob_start();
?>
<script src="<?= asset('assets/vendor/html5-qrcode.min.js') ?>"></script>
<script>
// ---- Scan QR ----
// Hasil pindaian bisa berupa: tautan kartu (…cek.php?t=<token>), token saja,
// atau kode registrasi. Semuanya diantar ke halaman cek peserta.
(function () {
    var URL_CEK = '<?= h(url('admin/cek.php')) ?>';
    var POLA_KODE = /^<?= preg_quote(KODE_AWALAN, '/') ?>\d+$/i;

    window.tujuanScan = function (teks) {
        teks = String(teks || '').trim();
        var m = teks.match(/[?&]t=([a-f0-9]{32})/i) || teks.match(/^([a-f0-9]{32})$/i);
        if (m) { return URL_CEK + '?t=' + m[1].toLowerCase(); }
        if (POLA_KODE.test(teks)) { return URL_CEK + '?kode=' + encodeURIComponent(teks.toUpperCase()); }
        return null;
    };

    var modalEl = document.getElementById('modalScan');
    var modal = new bootstrap.Modal(modalEl);
    var pesan = document.getElementById('pesanScan');
    var pilihKamera = document.getElementById('pilihKamera');
    var pemindai = null, berjalan = false, selesai = false;
    var KUNCI_KAMERA = 'regis-acara-kamera';

    function tampilPesan(teks) { pesan.innerHTML = teks ? '<div class="alert alert-danger">' + teks + '</div>' : ''; }

    function berhenti() {
        if (pemindai && berjalan) {
            berjalan = false;
            return pemindai.stop().catch(function () {});
        }
        return Promise.resolve();
    }

    function mulai(idKamera) {
        berhenti().then(function () {
            pemindai = pemindai || new Html5Qrcode('pembaca');
            var sumber = idKamera || { facingMode: 'environment' };
            pemindai.start(sumber, {
                fps: 10,
                qrbox: function (w, h) { var s = Math.floor(Math.min(w, h) * 0.7); return { width: s, height: s }; }
            }, function (teks) {
                if (selesai) { return; }
                var tujuan = window.tujuanScan(teks);
                if (!tujuan) { tampilPesan('QR ini bukan kartu peserta acara ini.'); return; }
                selesai = true;
                berhenti().then(function () { window.location.href = tujuan; });
            }).then(function () {
                berjalan = true;
                try { if (idKamera) { localStorage.setItem(KUNCI_KAMERA, idKamera); } } catch (e) {}
            }).catch(function (err) {
                tampilPesan('Kamera tidak bisa dibuka: ' + err + '. Pastikan izin kamera diberikan dan halaman dibuka lewat https:// atau localhost. Bisa juga ketik kodenya di bawah.');
            });
        });
    }

    modalEl.addEventListener('shown.bs.modal', function () {
        tampilPesan('');
        selesai = false;
        if (typeof Html5Qrcode === 'undefined') { tampilPesan('Pustaka pemindai gagal dimuat. Ketik kodenya di bawah.'); return; }
        Html5Qrcode.getCameras().then(function (kamera) {
            pilihKamera.innerHTML = '';
            var simpan = null;
            try { simpan = localStorage.getItem(KUNCI_KAMERA); } catch (e) {}
            var dipilih = null;
            kamera.forEach(function (k, i) {
                var o = document.createElement('option');
                o.value = k.id;
                o.textContent = k.label || ('Kamera ' + (i + 1));
                pilihKamera.appendChild(o);
                // Bawaan: kamera belakang, kecuali petugas pernah memilih yang lain.
                if (k.id === simpan || (!simpan && /back|rear|belakang|environment/i.test(k.label))) { dipilih = k.id; }
            });
            if (dipilih) { pilihKamera.value = dipilih; }
            mulai(pilihKamera.value || null);
        }).catch(function () { mulai(null); });
    });
    modalEl.addEventListener('hidden.bs.modal', berhenti);
    pilihKamera.addEventListener('change', function () { mulai(this.value); });

    document.getElementById('tombolScan').addEventListener('click', function () { modal.show(); });
    document.getElementById('formKodeManual').addEventListener('submit', function (e) {
        e.preventDefault();
        var v = document.getElementById('kodeManual').value.trim();
        if (!v) { return; }
        window.location.href = window.tujuanScan(v) || (URL_CEK + '?kode=' + encodeURIComponent(v.toUpperCase()));
    });

    if (/[?&]scan=1/.test(window.location.search)) { modal.show(); }
})();
</script>
<script>
(function () {
    var tabel = new DataTable('#tabelPeserta', {
        order: [],
        pageLength: 25,
        columnDefs: [{ targets: -1, orderable: false, searchable: false }],
        language: {
            search: 'Cari:',
            searchPlaceholder: 'Kode, nama, atau no. WA',
            lengthMenu: '_MENU_ per halaman',
            info: '_START_–_END_ dari _TOTAL_ baris',
            infoEmpty: 'Tidak ada baris',
            infoFiltered: '(disaring dari _MAX_)',
            zeroRecords: 'Tidak ditemukan. Periksa ejaan, atau pilih "Semua data" untuk mencari di seluruh status.',
            emptyTable: 'Tidak ada pendaftar pada saringan ini.',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        }
    });
    // Kotak cari langsung siap diketik begitu halaman terbuka.
    var cari = document.querySelector('#tabelPeserta_wrapper .dt-search input, .dt-search input');
    if (cari) {
        cari.classList.add('cari-cepat');
        if (!/[?&]scan=1/.test(window.location.search)) { cari.focus(); }
        // Pemindai USB/Bluetooth mengetik isi QR lalu menekan Enter di kotak cari:
        // tautan kartu atau kode yang lengkap langsung membuka halaman cek peserta.
        cari.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') { return; }
            var tujuan = window.tujuanScan(cari.value);
            if (tujuan) { e.preventDefault(); window.location.href = tujuan; }
        });
    }

    var modalEl = document.getElementById('modalHadir');
    var modal = new bootstrap.Modal(modalEl);
    var pesertaAktif = null;
    var tombolSimpan = document.getElementById('tombolSimpanHadir');

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function kirim(aksi, data) {
        var body = new URLSearchParams(data);
        body.append('aksi', aksi);
        body.append('csrf', window.CSRF);
        return fetch('<?= h($urlAjax) ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).then(function (r) { return r.json(); });
    }

    function baris(id) { return document.querySelector('#tabelPeserta tr[data-id="' + id + '"]'); }

    document.getElementById('tabelPeserta').addEventListener('click', function (e) {
        var t = e.target.closest('button');
        if (!t) { return; }

        if (t.classList.contains('tombol-hadir')) {
            pesertaAktif = JSON.parse(t.getAttribute('data-peserta'));
            var p = pesertaAktif;
            document.getElementById('pesanModal').innerHTML = '';
            document.getElementById('detailPeserta').innerHTML =
                '<tr><th style="width:110px">Kode</th><td><code>' + esc(p.kode) + '</code></td></tr>' +
                '<tr><th>Nama</th><td><strong>' + esc(p.nama) + '</strong></td></tr>' +
                '<tr><th>Umur</th><td>' + esc(p.umur) + ' tahun</td></tr>' +
                '<tr><th>No. WA</th><td>' + esc(p.wa) + '</td></tr>' +
                '<tr><th>Email</th><td>' + esc(p.email) + '</td></tr>' +
                '<tr><th>Domisili</th><td>' + esc(p.domisili) + '</td></tr>' +
                '<tr><th>Lomba</th><td>' + esc(p.jenis) + '</td></tr>' +
                '<tr><th>Mendaftar</th><td>' + esc(p.daftar) + '</td></tr>';
            tombolSimpan.disabled = false;
            modal.show();
            return;
        }

        if (t.classList.contains('tombol-batal')) {
            if (!confirm('Batalkan kehadiran ' + t.dataset.nama + '? Status kembali menjadi "Belum hadir" dan sesinya dikosongkan.')) { return; }
            t.disabled = true;
            kirim('batal', { id: t.dataset.id }).then(function (res) {
                if (!res.ok) { alert(res.pesan); t.disabled = false; return; }
                window.location.reload();
            }).catch(function () { alert('Jaringan terputus. Coba lagi.'); t.disabled = false; });
            return;
        }

        if (t.classList.contains('tombol-hapus')) {
            if (!confirm('Hapus 1 pendaftaran atas nama ' + t.dataset.nama + '? Data ini tidak bisa dikembalikan.')) { return; }
            t.disabled = true;
            kirim('hapus', { id: t.dataset.id }).then(function (res) {
                if (!res.ok) { alert(res.pesan); t.disabled = false; return; }
                tabel.row(baris(t.dataset.id)).remove().draw(false);
            }).catch(function () { alert('Jaringan terputus. Coba lagi.'); t.disabled = false; });
        }
    });

    tombolSimpan.addEventListener('click', function () {
        if (!pesertaAktif) { return; }
        var sesi = document.getElementById('pilihSesi').value;
        if (!sesi) {
            document.getElementById('pesanModal').innerHTML = '<div class="alert alert-danger">Belum ada sesi aktif. Minta administrator menambahkan sesi di menu Sesi.</div>';
            return;
        }
        tombolSimpan.disabled = true;
        kirim('hadir', { id: pesertaAktif.id, sesi_id: sesi }).then(function (res) {
            if (!res.ok) {
                document.getElementById('pesanModal').innerHTML = '<div class="alert alert-danger">' + esc(res.pesan) + '</div>';
                tombolSimpan.disabled = false;
                return;
            }
            var tr = baris(pesertaAktif.id);
            tr.querySelector('.sel-status').innerHTML = '<span class="badge badge-hadir">Hadir</span><br><small class="text-muted">' + esc(res.keterangan) + '</small>';
            tr.querySelector('.sel-aksi').innerHTML = '<span class="text-success"><i class="fas fa-circle-check"></i> Tercatat</span>';
            modal.hide();
            var cari = document.querySelector('.dt-search input');
            if (cari) { cari.select(); }
        }).catch(function () {
            document.getElementById('pesanModal').innerHTML = '<div class="alert alert-danger">Jaringan terputus. Coba tekan Tandai Hadir lagi.</div>';
            tombolSimpan.disabled = false;
        });
    });
})();
</script>
<?php
$skripHalaman = ob_get_clean();
require dirname(__DIR__) . '/includes/footer.php';
