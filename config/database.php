<?php
// ============================================================
// FILE: config/database.php
// PERBAIKAN: APP_URL disesuaikan dengan nama folder di htdocs
// ============================================================

define('DB_HOST', getenv('SPKEV_DB_HOST') !== false ? getenv('SPKEV_DB_HOST') : 'localhost');
define('DB_USER', getenv('SPKEV_DB_USER') !== false ? getenv('SPKEV_DB_USER') : 'root');        // Sesuaikan username MySQL
define('DB_PASS', getenv('SPKEV_DB_PASS') !== false ? getenv('SPKEV_DB_PASS') : '');            // Sesuaikan password MySQL
define('DB_NAME', getenv('SPKEV_DB_NAME') !== false ? getenv('SPKEV_DB_NAME') : 'spk_ev_db');
define('APP_NAME', 'SPK-EV');
define('APP_URL',  getenv('SPKEV_APP_URL') ?: 'http://localhost/spk_ev'); // sesuaikan dengan folder di htdocs / domain hosting

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log("Koneksi database gagal: " . $conn->connect_error);
        http_response_code(500);
        die("Layanan sedang tidak tersedia. Periksa konfigurasi database.");
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
