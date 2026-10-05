<?php
// ============================================================
// FILE: pages/admin_kendaraan.php
// FUNGSI: CRUD data kendaraan listrik (khusus Admin) — F-01, F-07
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pageTitle = 'Kelola Kendaraan';
$errors    = [];
$edit      = null;

// Kolom kriteria + metadata yang dapat diisi lewat form
function validasiKendaraan(array $in, array &$errors): array {
    $d = [];
    $d['brand'] = trim($in['brand'] ?? '');
    $d['model'] = trim($in['model'] ?? '');
    if ($d['brand'] === '' || mb_strlen($d['brand']) > 20) $errors[] = 'Merek wajib diisi (maks. 20 karakter).';
    if ($d['model'] === '' || mb_strlen($d['model']) > 60) $errors[] = 'Model wajib diisi (maks. 60 karakter).';

    $aturan = [  // kolom => [label, min, max, desimal?]
        'range_km'               => ['Jangkauan (km)', 20, 2000, false],
        'efficiency_wh_per_km'   => ['Efisiensi (Wh/km)', 50, 600, false],
        'acceleration_0_100_s'   => ['Akselerasi 0-100 (detik)', 1, 60, true],
        'battery_capacity_kwh'   => ['Kapasitas baterai (kWh)', 5, 300, true],
        'fast_charging_power_kw' => ['Daya fast charging (kW)', 1, 1000, false],
        'top_speed_kmh'          => ['Kecepatan maks. (km/h)', 30, 500, false],
        'seats'                  => ['Jumlah kursi', 1, 12, false],
    ];
    foreach ($aturan as $kol => [$label, $min, $max, $desimal]) {
        $raw = $in[$kol] ?? '';
        $v   = filter_var($raw, FILTER_VALIDATE_FLOAT);
        if ($v === false || $v < $min || $v > $max) {
            $errors[] = "$label harus berupa angka $min – $max.";
            $d[$kol] = 0;
        } else {
            $d[$kol] = $desimal ? round($v, 1) : (int)round($v);
        }
    }
    $d['drivetrain']    = in_array($in['drivetrain'] ?? '', ['FWD','RWD','AWD'], true) ? $in['drivetrain'] : '';
    if ($d['drivetrain'] === '') $errors[] = 'Drivetrain harus FWD, RWD, atau AWD.';
    $d['segment']       = trim($in['segment'] ?? '');
    $d['car_body_type'] = trim($in['car_body_type'] ?? '');
    if ($d['segment'] === '' || mb_strlen($d['segment']) > 20)             $errors[] = 'Segmen wajib diisi (maks. 20 karakter).';
    if ($d['car_body_type'] === '' || mb_strlen($d['car_body_type']) > 25) $errors[] = 'Body type wajib diisi (maks. 25 karakter).';
    $d['status'] = ($in['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
    return $d;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = $_POST['aksi'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);

    if ($aksi === 'simpan') {
        $d = validasiKendaraan($_POST, $errors);
        if (!$errors) {
            // cek duplikat merek+model
            $dup = fetchOne("SELECT id FROM kendaraan_ev WHERE brand = ? AND model = ? AND id <> ?",
                            [$d['brand'], $d['model'], $id], 'ssi');
            if ($dup) $errors[] = 'Kendaraan dengan merek dan model yang sama sudah ada.';
        }
        if (!$errors) {
            if ($id > 0) {
                executeQuery("UPDATE kendaraan_ev SET brand=?, model=?, range_km=?, efficiency_wh_per_km=?,
                    acceleration_0_100_s=?, battery_capacity_kwh=?, fast_charging_power_kw=?, top_speed_kmh=?,
                    seats=?, drivetrain=?, segment=?, car_body_type=?, status=? WHERE id=?",
                    [$d['brand'],$d['model'],$d['range_km'],$d['efficiency_wh_per_km'],$d['acceleration_0_100_s'],
                     $d['battery_capacity_kwh'],$d['fast_charging_power_kw'],$d['top_speed_kmh'],$d['seats'],
                     $d['drivetrain'],$d['segment'],$d['car_body_type'],$d['status'],$id], 'ssiiddiiissssi');
                setFlash('success', 'Data kendaraan berhasil diperbarui.');
            } else {
                executeQuery("INSERT INTO kendaraan_ev (brand, model, range_km, efficiency_wh_per_km,
                    acceleration_0_100_s, battery_capacity_kwh, fast_charging_power_kw, top_speed_kmh, seats,
                    drivetrain, segment, car_body_type, status, battery_type, length_mm, width_mm, height_mm, source_url)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, 'Li-ion', 0, 0, 0, 'input-admin')",
                    [$d['brand'],$d['model'],$d['range_km'],$d['efficiency_wh_per_km'],$d['acceleration_0_100_s'],
                     $d['battery_capacity_kwh'],$d['fast_charging_power_kw'],$d['top_speed_kmh'],$d['seats'],
                     $d['drivetrain'],$d['segment'],$d['car_body_type'],$d['status']], 'ssiiddiiissss');
                setFlash('success', 'Kendaraan baru berhasil ditambahkan.');
            }
            header('Location: ' . APP_URL . '/pages/admin_kendaraan.php');
            exit;
        }
        $edit = $_POST;           // tampilkan kembali input + error
        $edit['id'] = $id;
    } elseif ($aksi === 'toggle' && $id > 0) {
        executeQuery("UPDATE kendaraan_ev SET status = IF(status='aktif','nonaktif','aktif') WHERE id = ?", [$id], 'i');
        setFlash('success', 'Status kendaraan diubah.');
        header('Location: ' . APP_URL . '/pages/admin_kendaraan.php'); exit;
    } elseif ($aksi === 'hapus' && $id > 0) {
        executeQuery("DELETE FROM kendaraan_ev WHERE id = ?", [$id], 'i');
        setFlash('success', 'Kendaraan dihapus permanen.');
        header('Location: ' . APP_URL . '/pages/admin_kendaraan.php'); exit;
    }
}

if ($edit === null && !empty($_GET['edit'])) {
    $edit = fetchOne("SELECT * FROM kendaraan_ev WHERE id = ?", [(int)$_GET['edit']], 'i');
}

// ─── Daftar + pencarian + pagination ───
$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$where   = $q !== '' ? "WHERE brand LIKE ? OR model LIKE ?" : '';
$params  = $q !== '' ? ["%$q%", "%$q%"] : [];
$total   = (int)(fetchOne("SELECT COUNT(*) c FROM kendaraan_ev $where", $params, str_repeat('s', count($params)))['c'] ?? 0);
$pages   = max(1, (int)ceil($total / $perPage));
$page    = min($page, $pages);
$rows    = fetchAll("SELECT * FROM kendaraan_ev $where ORDER BY brand, model LIMIT ? OFFSET ?",
                    array_merge($params, [$perPage, ($page - 1) * $perPage]),
                    str_repeat('s', count($params)) . 'ii');

$f = fn($k, $def = '') => clean($edit[$k] ?? $def);
$opts = getFilterOptions();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
showFlash();
?>

<div class="card fade-up mb-4">
  <div class="card-header"><h3><?= !empty($edit['id']) ? '✏️ Edit Kendaraan' : '➕ Tambah Kendaraan' ?></h3></div>
  <div class="card-body">
    <?php foreach ($errors as $e): ?><div class="alert alert-danger">❌ <?= clean($e) ?></div><?php endforeach; ?>
    <form method="POST" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="grid-2" style="gap:14px">
        <div class="form-group"><label>Merek</label><input class="form-control" name="brand" maxlength="20" value="<?= $f('brand') ?>" required></div>
        <div class="form-group"><label>Model</label><input class="form-control" name="model" maxlength="60" value="<?= $f('model') ?>" required></div>
        <div class="form-group"><label>C1 Jangkauan (km) · benefit</label><input class="form-control" type="number" step="1" name="range_km" value="<?= $f('range_km') ?>" required></div>
        <div class="form-group"><label>C2 Efisiensi (Wh/km) · cost</label><input class="form-control" type="number" step="1" name="efficiency_wh_per_km" value="<?= $f('efficiency_wh_per_km') ?>" required></div>
        <div class="form-group"><label>Akselerasi 0–100 km/h (detik) · cost</label><input class="form-control" type="number" step="0.1" name="acceleration_0_100_s" value="<?= $f('acceleration_0_100_s') ?>" required></div>
        <div class="form-group"><label>Kapasitas baterai (kWh) · benefit</label><input class="form-control" type="number" step="0.1" name="battery_capacity_kwh" value="<?= $f('battery_capacity_kwh') ?>" required></div>
        <div class="form-group"><label>Daya fast charging DC (kW) · benefit</label><input class="form-control" type="number" step="1" name="fast_charging_power_kw" value="<?= $f('fast_charging_power_kw') ?>" required></div>
        <div class="form-group"><label>Kecepatan maks. (km/h)</label><input class="form-control" type="number" step="1" name="top_speed_kmh" value="<?= $f('top_speed_kmh') ?>" required></div>
        <div class="form-group"><label>Jumlah kursi</label><input class="form-control" type="number" step="1" name="seats" value="<?= $f('seats', '5') ?>" required></div>
        <div class="form-group"><label>Drivetrain</label>
          <select class="form-control" name="drivetrain" required>
            <option value="">— pilih —</option>
            <?php foreach (['FWD','RWD','AWD'] as $dt): ?>
            <option value="<?= $dt ?>" <?= ($edit['drivetrain'] ?? '') === $dt ? 'selected' : '' ?>><?= $dt ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="form-group"><label>Segmen</label>
          <input class="form-control" name="segment" maxlength="20" list="dl-seg" value="<?= $f('segment') ?>" required>
          <datalist id="dl-seg"><?php foreach ($opts['segments'] as $o): ?><option value="<?= clean($o['segment']) ?>"><?php endforeach; ?></datalist></div>
        <div class="form-group"><label>Body type</label>
          <input class="form-control" name="car_body_type" maxlength="25" list="dl-body" value="<?= $f('car_body_type') ?>" required>
          <datalist id="dl-body"><?php foreach ($opts['body_types'] as $o): ?><option value="<?= clean($o['car_body_type']) ?>"><?php endforeach; ?></datalist></div>
        <div class="form-group"><label>Status</label>
          <select class="form-control" name="status">
            <option value="aktif" <?= ($edit['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif (ikut perhitungan)</option>
            <option value="nonaktif" <?= ($edit['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
          </select></div>
      </div>
      <button type="submit" class="btn btn-primary">💾 Simpan</button>
      <?php if (!empty($edit['id'])): ?><a href="admin_kendaraan.php" class="btn btn-outline">Batal</a><?php endif; ?>
    </form>
  </div>
</div>

<div class="card fade-up">
  <div class="card-header">
    <h3>🚗 Data Kendaraan (<?= $total ?>)</h3>
    <form method="GET" style="display:flex;gap:8px">
      <input class="form-control" type="text" name="q" placeholder="Cari merek / model" value="<?= clean($q) ?>">
      <button class="btn btn-outline btn-sm" type="submit">Cari</button>
    </form>
  </div>
  <div class="card-body">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Merek</th><th>Model</th><th>Range</th><th>Wh/km</th><th>0-100</th><th>kWh</th><th>Fast kW</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= clean($r['brand']) ?></td><td><?= clean($r['model']) ?></td>
            <td><?= (int)$r['range_km'] ?></td><td><?= (int)$r['efficiency_wh_per_km'] ?></td>
            <td><?= clean($r['acceleration_0_100_s']) ?></td><td><?= clean($r['battery_capacity_kwh']) ?></td>
            <td><?= clean($r['fast_charging_power_kw'] ?? '-') ?></td>
            <td><span class="badge <?= $r['status'] === 'aktif' ? 'badge-green' : 'badge-orange' ?>"><?= clean($r['status']) ?></span></td>
            <td style="white-space:nowrap">
              <a class="btn btn-outline btn-sm" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
              <form method="POST" style="display:inline"><?= csrf_field() ?>
                <input type="hidden" name="aksi" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-outline btn-sm" type="submit"><?= $r['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
              <form method="POST" style="display:inline" onsubmit="return confirm('Hapus permanen kendaraan ini?')"><?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">Hapus</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="9" style="text-align:center;color:var(--text-muted)">Tidak ada data.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pages > 1): ?>
    <div style="display:flex;gap:6px;margin-top:14px;flex-wrap:wrap">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>"
           href="?<?= http_build_query(['q' => $q, 'page' => $p]) ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
