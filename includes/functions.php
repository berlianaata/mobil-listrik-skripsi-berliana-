<?php
// ============================================================
// FILE: includes/functions.php
// FUNGSI: Kumpulan fungsi helper utama aplikasi
// ============================================================

require_once __DIR__ . '/icons.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}
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
    return fetchOne("SELECT * FROM users WHERE id = ?", [(int)$_SESSION['user_id']], 'i');
}

function isAdmin() {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        setFlash('danger', 'Halaman ini hanya untuk administrator.');
        header('Location: ' . APP_URL . '/pages/dashboard.php');
        exit;
    }
}

// Password: bcrypt (password_hash). Hash lama SHA-256 tetap bisa login
// dan otomatis di-upgrade ke bcrypt (lihat auth/login.php).
function hashPassword($pw) {
    return password_hash($pw, PASSWORD_BCRYPT);
}

function isLegacyHash($hash) {
    return strpos($hash, '$2y$') !== 0;
}

function verifyPassword($pw, $hash) {
    if (isLegacyHash($hash)) {
        return hash_equals($hash, hash('sha256', $pw . 'spk_ev_salt_2024'));
    }
    return password_verify($pw, $hash);
}

// ─────────────────────────────────────────
// CSRF
// ─────────────────────────────────────────

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_verify($token = null) {
    $token = $token ?? ($_POST['csrf'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$token)) {
        http_response_code(419);
        die('Sesi formulir tidak valid atau kedaluwarsa. Silakan muat ulang halaman dan coba lagi.');
    }
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

function getKendaraanFiltered($filter = [], $limit = 50) {
    $conn  = db();
    $where = ["k.status = 'aktif'",
              "k.harga_rp_juta IS NOT NULL",
              "k.range_km IS NOT NULL",
              "k.battery_capacity_kwh IS NOT NULL",
              "k.waktu_isi_menit IS NOT NULL",
              "k.daya_hp IS NOT NULL",
              "k.seats IS NOT NULL"];
    $params = [];
    $types  = '';

    // Pilihan kebutuhan pengguna: 'pribadi' (perorangan) atau 'kantor' (niaga)
    if (!empty($filter['tipe'])) {
        $where[]  = "k.tipe_penggunaan = ?";
        $params[] = $filter['tipe'];
        $types   .= 's';
    }

    $whereStr = implode(' AND ', $where);
    $sql = "SELECT * FROM kendaraan_ev k WHERE $whereStr ORDER BY k.harga_rp_juta ASC LIMIT ?";
    $params[] = (int)$limit;
    $types   .= 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getFilterOptions() {
    return [
        'tipe' => [
            ['value' => 'pribadi', 'label' => 'Perorangan (penggunaan pribadi)'],
            ['value' => 'kantor',  'label' => 'Kantor / niaga'],
        ],
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
    $conn = db();
    $tipe = in_array($data['tipe'] ?? null, ['pribadi','kantor'], true) ? $data['tipe'] : null;
    $jml  = isset($data['jumlah_alt']) ? (int)$data['jumlah_alt'] : 50;

    $stmt = $conn->prepare("
        INSERT INTO preferensi_user (user_id, filter_tipe, jumlah_alt)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            filter_tipe = VALUES(filter_tipe),
            jumlah_alt  = VALUES(jumlah_alt)
    ");
    $stmt->bind_param('isi', $userId, $tipe, $jml);
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
// EKSPOR CSV (F-08)
// ─────────────────────────────────────────

function exportRankingCsv($filename, array $ranking, array $bobot, array $meta = []) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
    fputcsv($out, ['Hasil Perangkingan AHP-TOPSIS']);
    foreach ($meta as $k => $v) fputcsv($out, [$k, $v]);
    fputcsv($out, []);
    fputcsv($out, ['Bobot Kriteria (AHP)']);
    foreach ($bobot as $kode => $w) fputcsv($out, [$kode, round((float)$w, 6)]);
    fputcsv($out, []);
    $kodeKrit = !empty($ranking[0]['data_krit']) ? array_keys($ranking[0]['data_krit']) : [];
    fputcsv($out, array_merge(['Peringkat', 'Alternatif', 'Nilai Preferensi (Vi)', 'D+', 'D-'], $kodeKrit));
    foreach ($ranking as $r) {
        $row = [$r['rank'], $r['nama'], $r['CC'], $r['D_plus'] ?? '', $r['D_minus'] ?? ''];
        foreach ($kodeKrit as $k) $row[] = $r['data_krit'][$k] ?? '';
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
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
    $icon = $f['type'] === 'success' ? '' : ($f['type'] === 'warning' ? '' : '');
    echo "<div class=\"alert alert-{$f['type']}\">$icon " . clean($f['msg']) . "</div>";
}

// ─────────────────────────────────────────
// FORMAT HELPERS
// ─────────────────────────────────────────

function formatAngka($val, $desimal = 2) {
    return number_format((float)$val, $desimal, '.', ',');
}

// Format harga (nilai dalam Rp juta) menjadi teks rupiah yang mudah dibaca
function formatRupiahJuta($juta) {
    $juta = (float)$juta;
    if ($juta >= 1000) {
        return 'Rp ' . number_format($juta / 1000, 2, ',', '.') . ' miliar';
    }
    return 'Rp ' . number_format($juta, 2, ',', '.') . ' juta';
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
        return '<span class="badge badge-green">Konsisten (CR=' . formatAngka($cr,4) . ')</span>';
    }
    return '<span class="badge badge-red">Tidak Konsisten (CR=' . formatAngka($cr,4) . ')</span>';
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
        'total_pribadi' => fetchOne("SELECT COUNT(*) c FROM kendaraan_ev WHERE status='aktif' AND tipe_penggunaan='pribadi'")['c'] ?? 0,
        'total_kantor'  => fetchOne("SELECT COUNT(*) c FROM kendaraan_ev WHERE status='aktif' AND tipe_penggunaan='kantor'")['c'] ?? 0,
    ];
}
?>
