<?php
// ============================================================
// FILE: auth/logout.php
// FUNGSI: Proses logout / akhiri sesi pengguna
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
session_destroy();
header('Location: ' . APP_URL . '/auth/login.php');
exit;
?>
