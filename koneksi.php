<?php
$host = "mysql-2eefe333-rystsarz-156f.d.aivencloud.com";
$user = "avnadmin";
$pass = "AVNS_rQg_xWpm7g-xThkGz3G"; // Masukkan password akun Aiven kamu
$db   = "db_smsport"; // Atau defaultdb sesuai database tempat kamu import tadi
$port = "26686"; // Port koneksi Aiven

$koneksi = mysqli_connect($host, $user, $pass, $db, $port);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>