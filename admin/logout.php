<?php
require_once dirname(__DIR__) . '/includes/app.php';

$_SESSION = [];
session_destroy();
redirect('admin/login.php');
