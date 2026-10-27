<?php
require_once dirname(__DIR__) . '/includes/app.php';

// Hanya POST bertoken: tautan/gambar di situs lain tidak bisa mengeluarkan
// petugas di tengah antrean registrasi.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    redirect(petugas_login() ? 'admin/index.php' : 'admin/login.php');
}

$_SESSION = [];
session_destroy();
redirect('admin/login.php');
