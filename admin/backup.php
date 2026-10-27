<?php
/**
 * BACKUP DATABASE (admin) - mengunduh seluruh tabel aplikasi sebagai .sql
 * (struktur + data), siap di-import lewat phpMyAdmin di server lain.
 */
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/schema.php';
wajib_login(true);

if (($_GET['unduh'] ?? '') === '1') {
    @set_time_limit(0);
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="backup-' . DB_NAME . '-' . date('Ymd-His') . '.sql"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    schema_ekspor(db(), $out, DB_NAME);
    fclose($out);
    exit;
}

$jumlah = [];
foreach (array_keys(schema_tabel()) as $t) {
    $jumlah[$t] = (int) db()->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
}

$pageTitle = 'Backup Database';
$activeMenu = 'backup';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-lg-7">
        <section class="card">
            <header class="card-header">
                <h2 class="card-title">Export Database</h2>
                <p class="card-subtitle">Database <code><?= h(DB_NAME) ?></code> — struktur dan seluruh isinya dalam satu berkas .sql.</p>
            </header>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Tabel</th><th class="text-end">Baris</th></tr></thead>
                        <tbody>
                            <?php foreach ($jumlah as $t => $n): ?>
                                <tr><td><?= h($t) ?></td><td class="text-end"><?= number_format($n, 0, ',', '.') ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="help-block mt-3 mb-0">Berkas ini berisi data pribadi peserta (no. WA, email). Simpan di tempat aman.
                    Untuk memulihkan: phpMyAdmin → pilih database → Import → pilih berkas .sql. Tabel yang sama di tujuan akan ditimpa.</p>
            </div>
            <footer class="card-footer">
                <div class="row">
                    <div class="col-md-12 text-end">
                        <a href="?unduh=1" class="btn btn-primary"><i class="fas fa-download"></i> Unduh Backup .sql</a>
                    </div>
                </div>
            </footer>
        </section>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
