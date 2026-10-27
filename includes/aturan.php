<?php
/**
 * Aturan yang dipakai bersama app.php dan setup.php (setup tidak memuat app.php
 * karena saat itu config.php bisa belum ada).
 */

/** Password yang terlalu lemah untuk akun petugas: ditolak, atau dipaksa ganti saat login. */
function password_lemah($pass)
{
    $umum = ['admin123', 'admin', 'password', '123456', '12345678', 'qwerty', 'petugas', 'admin1234', 'password1'];
    return strlen($pass) < 8 || in_array(strtolower($pass), $umum, true);
}
