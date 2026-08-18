<?php
// ============================================================
// FILE: includes/functions.php
// FUNGSI: Kumpulan fungsi helper utama aplikasi
// ============================================================

session_start();
require_once __DIR__ . '/../config/database.php';

// ─────────────────────────────────────────
// AUTH HELPERS
// ─────────────────────────────────────────

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }
}

function requireGuest() {
    if (isLoggedIn()) {
        header('Location: ' . APP_URL . '/pages/dashboard.php');
        exit;
    }
}

function currentUser() {
    if (!isLoggedIn()) return null;
    $conn = db();
    $id   = (int)$_SESSION['user_id'];
    $res  = $conn->query("SELECT * FROM users WHERE id = $id");
    return $res ? $res->fetch_assoc() : null;
}

function hashPassword($pw) {
    return hash('sha256', $pw . 'spk_ev_salt_2024');
}

function verifyPassword($pw, $hash) {
    return hashPassword($pw) === $hash;
}

// ─────────────────────────────────────────
// SANITASI & VALIDASI
// ─────────────────────────────────────────

function clean($val) {
    return htmlspecialchars(trim((string)$val), ENT_QUOTES, 'UTF-8');
}

function safeFloat($val, $default = 0) {
    $v = filter_var($val, FILTER_VALIDATE_FLOAT);
    return ($v !== false) ? $v : $default;
}

function safeInt($val, $default = 0) {
    $v = filter_var($val, FILTER_VALIDATE_INT);
    return ($v !== false) ? (int)$v : $default;
}

// ─────────────────────────────────────────
// DATABASE HELPERS
// ─────────────────────────────────────────

function fetchAll($sql, $params = [], $types = '') {
    $conn = db();
    if (empty($params)) {
        $res = $conn->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchOne($sql, $params = [], $types = '') {
    $rows = fetchAll($sql, $params, $types);
    return $rows[0] ?? null;
}

function executeQuery($sql, $params = [], $types = '') {
    $conn = db();
    if (empty($params)) {
        return $conn->query($sql);
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param($types, ...$params);
    return $stmt->execute();
}

function lastInsertId() {
    return db()->insert_id;
}

// ─────────────────────────────────────────
// KRITERIA HELPERS
// ─────────────────────────────────────────

function getKriteria($aktifSaja = true) {
    $where = $aktifSaja ? 'WHERE aktif = 1' : '';
    return fetchAll("SELECT * FROM kriteria $where ORDER BY kode ASC");
}

function getKriteriaByKode($kode) {
    return fetchOne("SELECT * FROM kriteria WHERE kode = ?", [$kode], 's');
}

// ─────────────────────────────────────────
// KENDARAAN HELPERS
// ─────────────────────────────────────────

function getKendaraanFiltered($filter = [], $limit = 30) {
    $conn  = db();
    $where = ["k.status = 'aktif'",
              "k.range_km IS NOT NULL",
              "k.efficiency_wh_per_km IS NOT NULL",
              "k.acceleration_0_100_s IS NOT NULL",
              "k.battery_capacity_kwh IS NOT NULL",
              "k.fast_charging_power_kw IS NOT NULL"];
    $params = [];

    if (!empty($filter['segment'])) {
        $where[] = "k.segment = ?";
        $params[] = $filter['segment'];
    }
    if (!empty($filter['drivetrain'])) {
        $where[] = "k.drivetrain = ?";
        $params[] = $filter['drivetrain'];
    }
    if (!empty($filter['body_type'])) {
        $where[] = "k.car_body_type = ?";
        $params[] = $filter['body_type'];
    }
    if (!empty($filter['seats'])) {
        $where[] = "k.seats = ?";
        $params[] = (int)$filter['seats'];
    }

    $whereStr = implode(' AND ', $where);
    $types    = str_repeat('s', count($params));
    $sql = "SELECT * FROM kendaraan_ev k WHERE $whereStr ORDER BY k.range_km DESC LIMIT ?";
    $params[] = (int)$limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getFilterOptions() {
    return [
        'segments'   => fetchAll("SELECT DISTINCT segment FROM kendaraan_ev WHERE segment IS NOT NULL AND status='aktif' ORDER BY segment"),
        'drivetrains'=> fetchAll("SELECT DISTINCT drivetrain FROM kendaraan_ev WHERE drivetrain IS NOT NULL AND status='aktif' ORDER BY drivetrain"),
        'body_types' => fetchAll("SELECT DISTINCT car_body_type FROM kendaraan_ev WHERE car_body_type IS NOT NULL AND status='aktif' ORDER BY car_body_type"),
        'seats'      => fetchAll("SELECT DISTINCT seats FROM kendaraan_ev WHERE seats IS NOT NULL AND status='aktif' ORDER BY seats"),
    ];
}

// ─────────────────────────────────────────
// MATRIKS AHP HELPERS
// ─────────────────────────────────────────

function getMatriksAHP($userId) {
    return fetchAll(
        "SELECT * FROM matriks_ahp WHERE user_id = ?",
        [$userId], 'i'
    );
}

function saveMatriksAHP($userId, $ki, $kj, $nilai) {
    $conn = db();
    $stmt = $conn->prepare("
        INSERT INTO matriks_ahp (user_id, kriteria_i, kriteria_j, nilai)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)
    ");
    $stmt->bind_param('issd', $userId, $ki, $kj, $nilai);
    return $stmt->execute();
}

function getBobotAHP($userId) {
    return fetchAll(
        "SELECT ba.*, k.kode, k.nama, k.tipe, k.satuan
         FROM bobot_ahp ba
         JOIN kriteria k ON k.id = ba.kriteria_id
         WHERE ba.user_id = ?
         ORDER BY k.kode",
        [$userId], 'i'
    );
}

function saveBobotAHP($userId, $kriteriaId, $bobot, $lambdaMax, $ci, $cr, $konsisten) {
    $conn = db();
    $stmt = $conn->prepare("
        INSERT INTO bobot_ahp (user_id, kriteria_id, bobot, lambda_max, ci, cr, konsisten)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            bobot = VALUES(bobot),
            lambda_max = VALUES(lambda_max),
            ci = VALUES(ci), cr = VALUES(cr),
            konsisten = VALUES(konsisten)
    ");
    $stmt->bind_param('iiddddi',
        $userId, $kriteriaId, $bobot,
        $lambdaMax, $ci, $cr, $konsisten
    );
    return $stmt->execute();
}

// ─────────────────────────────────────────
// PREFERENSI USER
// ─────────────────────────────────────────

function getPreferensiUser($userId) {
    return fetchOne(
        "SELECT * FROM preferensi_user WHERE user_id = ?",
        [$userId], 'i'
    );
}

function savePreferensiUser($userId, $data) {
    $conn    = db();
    $segment = $data['segment'] ?? null;
    $drive   = $data['drivetrain'] ?? null;
    $body    = $data['body_type'] ?? null;
    $seats   = !empty($data['seats']) ? (int)$data['seats'] : null;
    $jml     = isset($data['jumlah_alt']) ? (int)$data['jumlah_alt'] : 20;

    $stmt = $conn->prepare("
        INSERT INTO preferensi_user (user_id, filter_segment, filter_drive, filter_body, filter_seats, jumlah_alt)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            filter_segment = VALUES(filter_segment),
            filter_drive   = VALUES(filter_drive),
            filter_body    = VALUES(filter_body),
            filter_seats   = VALUES(filter_seats),
            jumlah_alt     = VALUES(jumlah_alt)
    ");
    $stmt->bind_param('isssii', $userId, $segment, $drive, $body, $seats, $jml);
    return $stmt->execute();
}

// ─────────────────────────────────────────
// HISTORY PERHITUNGAN
// ─────────────────────────────────────────

function saveHistory($userId, $data) {
    $conn = db();
    $stmt = $conn->prepare("
        INSERT INTO history_perhitungan
            (user_id, nama_sesi, filter_json, bobot_json, ahp_json,
             topsis_json, ranking_json, cr_value, konsisten, jumlah_alt)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $cr       = isset($data['cr']) ? (float)$data['cr'] : 0;
    $konsiten = isset($data['konsisten']) ? (int)$data['konsisten'] : 0;
    $jml      = isset($data['jumlah_alt']) ? (int)$data['jumlah_alt'] : 0;
    $stmt->bind_param(
        'issssssidi',
        $userId,
        $data['nama_sesi'],
        $data['filter_json'],
        $data['bobot_json'],
        $data['ahp_json'],
        $data['topsis_json'],
        $data['ranking_json'],
        $cr, $konsiten, $jml
    );
    return $stmt->execute();
}

function getHistoryUser($userId, $limit = 10) {
    return fetchAll(
        "SELECT * FROM history_perhitungan WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
        [$userId, $limit], 'ii'
    );
}

function getHistoryDetail($histId, $userId) {
    return fetchOne(
        "SELECT * FROM history_perhitungan WHERE id = ? AND user_id = ?",
        [$histId, $userId], 'ii'
    );
}

// ─────────────────────────────────────────
// FLASH MESSAGE
// ─────────────────────────────────────────

function setFlash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function showFlash() {
    $f = getFlash();
    if (!$f) return;
    $icon = $f['type'] === 'success' ? '✅' : ($f['type'] === 'warning' ? '⚠️' : '❌');
    echo "<div class=\"alert alert-{$f['type']}\">$icon " . clean($f['msg']) . "</div>";
}

// ─────────────────────────────────────────
// FORMAT HELPERS
// ─────────────────────────────────────────

function formatAngka($val, $desimal = 2) {
    return number_format((float)$val, $desimal, '.', ',');
}

function formatPersen($val, $desimal = 2) {
    return number_format((float)$val * 100, $desimal) . '%';
}

function tglIndonesia($datetime) {
    $bulan = ['', 'Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $ts = strtotime($datetime);
    return date('d', $ts) . ' ' . $bulan[(int)date('m', $ts)] . ' ' . date('Y', $ts);
}

function badgeTipe($tipe) {
    if ($tipe === 'benefit') {
        return '<span class="badge badge-green">↑ Benefit</span>';
    }
    return '<span class="badge badge-red">↓ Cost</span>';
}

function badgeKonsisten($cr) {
    if ($cr <= 0.1) {
        return '<span class="badge badge-green">✅ Konsisten (CR=' . formatAngka($cr,4) . ')</span>';
    }
    return '<span class="badge badge-red">❌ Tidak Konsisten (CR=' . formatAngka($cr,4) . ')</span>';
}

// ─────────────────────────────────────────
// STATISTIK DASHBOARD
// ─────────────────────────────────────────

function getStatistikDB() {
    return [
        'total_ev'      => fetchOne("SELECT COUNT(*) c FROM kendaraan_ev WHERE status='aktif'")['c'] ?? 0,
        'total_brand'   => fetchOne("SELECT COUNT(DISTINCT brand) c FROM kendaraan_ev WHERE status='aktif'")['c'] ?? 0,
        'total_user'    => fetchOne("SELECT COUNT(*) c FROM users WHERE role='user'")['c'] ?? 0,
        'total_history' => fetchOne("SELECT COUNT(*) c FROM history_perhitungan")['c'] ?? 0,
        'max_range'     => fetchOne("SELECT MAX(range_km) c FROM kendaraan_ev WHERE status='aktif'")['c'] ?? 0,
        'avg_range'     => fetchOne("SELECT ROUND(AVG(range_km)) c FROM kendaraan_ev WHERE status='aktif'")['c'] ?? 0,
    ];
}
?>
