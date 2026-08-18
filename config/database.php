<?php
// ============================================================
// FILE: config/database.php
// PERBAIKAN: APP_URL disesuaikan dengan nama folder di htdocs
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Sesuaikan username MySQL
define('DB_PASS', '');            // Sesuaikan password MySQL
define('DB_NAME', 'spk_ev_db');
define('APP_NAME', 'SPK-EV');
define('APP_URL',  'http://localhost/spk_ev'); // ← DIPERBAIKI: sesuai nama folder di htdocs

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("❌ Koneksi database gagal: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Singleton connection
function db() {
    static $conn = null;
    if ($conn === null) {
        $conn = getDB();
    }
    return $conn;
}
?>
