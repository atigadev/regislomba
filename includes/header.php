<?php
/**
 * Kerangka halaman petugas - tema Porto Admin (dark), sama dengan SatuRegis.
 * Wajib set $pageTitle dan $activeMenu sebelum include file ini.
 */
if (!isset($pageTitle)) { $pageTitle = APP_NAME; }
if (!isset($activeMenu)) { $activeMenu = ''; }
$flash = flash_get();
$akun = petugas_login();
$inisial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($akun['nama'], 0, 1)) : strtoupper(substr($akun['nama'], 0, 1));

$menu = [
    ['kunci' => 'dashboard', 'label' => 'Dashboard',        'ikon' => 'fa-gauge',          'href' => 'admin/index.php'],
    ['kunci' => 'peserta',   'label' => 'Cek Pendaftaran',  'ikon' => 'fa-user-check',     'href' => 'admin/peserta.php'],
    ['kunci' => 'qr',        'label' => 'QR Formulir',      'ikon' => 'fa-qrcode',         'href' => 'admin/qr-formulir.php'],
    ['kunci' => 'laporan',  'label' => 'Laporan',          'ikon' => 'fa-chart-column',   'href' => 'admin/laporan.php'],
    ['kunci' => 'sesi',      'label' => 'Sesi',             'ikon' => 'fa-clock',          'href' => 'admin/sesi.php',    'admin' => true],
    ['kunci' => 'petugas',   'label' => 'Kelola Petugas',   'ikon' => 'fa-users-gear',     'href' => 'admin/petugas.php', 'admin' => true],
    ['kunci' => 'backup',    'label' => 'Backup Database',  'ikon' => 'fa-database',       'href' => 'admin/backup.php',  'admin' => true],
    ['kunci' => 'password',  'label' => 'Ganti Password',   'ikon' => 'fa-key',            'href' => 'admin/password.php'],
];
?>
<!DOCTYPE html>
<html lang="id" class="fixed dark">
<head>
<script>
(function () {
    try {
        if (localStorage.getItem('porto-sidebar-ciut') === '1') {
            document.documentElement.classList.add('sidebar-left-collapsed');
        }
    } catch (e) { /* mode privat: pakai bawaan (terbentang) */ }
})();
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($pageTitle) ?> - <?= h(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700,800" rel="stylesheet" type="text/css">
<link rel="stylesheet" href="<?= url('assets/porto/vendor/bootstrap/css/bootstrap.css') ?>">
<link rel="stylesheet" href="<?= url('assets/porto/vendor/font-awesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= url('assets/porto/css/theme.css') ?>">
<link rel="stylesheet" href="<?= url('assets/porto/css/theme-admin-extension.css') ?>">
<link rel="stylesheet" href="<?= url('assets/porto/css/skins/default.css') ?>">
<link rel="stylesheet" href="<?= url('assets/porto/css/custom.css') ?>">
<link rel="stylesheet" href="<?= url('assets/vendor/datatables/dataTables.dataTables.min.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
<script src="<?= url('assets/porto/vendor/modernizr/modernizr.js') ?>"></script>
<script src="<?= url('assets/porto/vendor/jquery/jquery.js') ?>"></script>
<script src="<?= url('assets/vendor/datatables/dataTables.min.js') ?>"></script>
<script src="<?= url('assets/vendor/datatables/dataTables.dataTables.min.js') ?>"></script>
<script>window.CSRF = '<?= h(csrf_token()) ?>';</script>
</head>
<body>
<section class="body">
    <header class="header">
        <div class="logo-container">
            <a href="<?= url('admin/index.php') ?>" class="logo">
                <span class="porto-brand"><i class="fas fa-trophy"></i> <?= h(APP_NAME) ?></span>
            </a>
            <div class="d-md-none toggle-sidebar-left" data-toggle-class="sidebar-left-opened" data-target="html" data-fire-event="sidebar-left-opened">
                <i class="fas fa-bars" aria-label="Buka menu"></i>
            </div>
        </div>
        <div class="header-right">
            <a href="<?= url('index.php') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-default tombol-formulir d-none d-sm-inline-flex" title="Buka formulir pendaftaran peserta">
                <i class="fas fa-up-right-from-square"></i>&nbsp;Formulir Peserta
            </a>
            <span class="separator"></span>
            <div id="userbox" class="userbox">
                <a href="#" data-bs-toggle="dropdown">
                    <figure class="profile-picture">
                        <span class="avatar-circle"><?= h($inisial) ?></span>
                    </figure>
                    <div class="profile-info">
                        <span class="name"><?= h($akun['nama']) ?></span>
                        <span class="role"><?= $akun['peran'] === 'admin' ? 'Administrator' : 'Petugas' ?></span>
                    </div>
                    <i class="fa custom-caret"></i>
                </a>
                <div class="dropdown-menu">
                    <ul class="list-unstyled mb-2">
                        <li class="divider"></li>
                        <li><a role="menuitem" tabindex="-1" href="<?= url('index.php') ?>" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square"></i> Formulir Peserta</a></li>
                        <li><a role="menuitem" tabindex="-1" href="<?= url('admin/password.php') ?>"><i class="fas fa-key"></i> Ganti Password</a></li>
                        <li><a role="menuitem" tabindex="-1" href="<?= url('admin/logout.php') ?>"><i class="fas fa-power-off"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <div class="inner-wrapper">
        <aside id="sidebar-left" class="sidebar-left">
            <div class="sidebar-header">
                <div class="sidebar-title">Navigasi</div>
                <div class="sidebar-toggle d-none d-md-block" data-toggle-class="sidebar-left-collapsed" data-target="html" data-fire-event="sidebar-left-toggle">
                    <i class="fas fa-bars" aria-label="Ciutkan menu"></i>
                </div>
            </div>
            <div class="nano">
                <div class="nano-content">
                    <nav id="menu" class="nav-main" role="navigation">
                        <ul class="nav nav-main">
                            <?php foreach ($menu as $m): ?>
                                <?php if (!empty($m['admin']) && !is_admin()) { continue; } ?>
                                <li class="<?= $activeMenu === $m['kunci'] ? 'nav-active' : '' ?>">
                                    <a class="nav-link" href="<?= url($m['href']) ?>">
                                        <i class="fas <?= $m['ikon'] ?>" aria-hidden="true"></i>
                                        <span><?= h($m['label']) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <li>
                                <a class="nav-link" href="<?= url('admin/logout.php') ?>">
                                    <i class="fas fa-power-off" aria-hidden="true"></i>
                                    <span>Logout</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </aside>

        <section role="main" class="content-body">
            <header class="page-header">
                <h2><?= h($pageTitle) ?></h2>
                <div class="right-wrapper text-end">
                    <ol class="breadcrumbs">
                        <li><a href="<?= url('admin/index.php') ?>"><i class="fas fa-home"></i></a></li>
                        <li><span><?= h($pageTitle) ?></span></li>
                    </ol>
                </div>
            </header>
            <!-- start: page content -->
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'success' ? 'success' : 'info') ?>">
                    <?= h($flash['message']) ?>
                </div>
            <?php endif; ?>
