<?php
// ============================================================
// FILE: pages/katalog.php
// FUNGSI: Katalog semua kendaraan EV dengan filter dan pencarian
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pageTitle = 'Katalog Kendaraan EV';
$opts      = getFilterOptions();

// ─── Filter dari GET ───
$filterSegment  = $_GET['segment']    ?? '';
$filterDrive    = $_GET['drivetrain'] ?? '';
$filterBody     = $_GET['body_type']  ?? '';
$filterSeats    = $_GET['seats']      ?? '';
$search         = trim($_GET['q']     ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 15;

// ─── Query ───
$conn  = db();
$where = ["status = 'aktif'"];
$params= [];

if ($filterSegment)  { $where[] = "segment = ?";       $params[] = $filterSegment; }
if ($filterDrive)    { $where[] = "drivetrain = ?";     $params[] = $filterDrive; }
if ($filterBody)     { $where[] = "car_body_type = ?";  $params[] = $filterBody; }
if ($filterSeats)    { $where[] = "seats = ?";          $params[] = (int)$filterSeats; }
if ($search)         { $where[] = "(brand LIKE ? OR model LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$whereStr = 'WHERE ' . implode(' AND ', $where);

// Count total
$countSql  = "SELECT COUNT(*) FROM kendaraan_ev $whereStr";
$stmtCount = $conn->prepare($countSql);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$total    = $stmtCount->get_result()->fetch_row()[0];
$totalPage= ceil($total / $perPage);
$offset   = ($page - 1) * $perPage;

// Data
$dataSql  = "SELECT * FROM kendaraan_ev $whereStr ORDER BY range_km DESC LIMIT ? OFFSET ?";
$allParams= array_merge($params, [$perPage, $offset]);
$types2   = str_repeat('s', count($params)) . 'ii';
$stmtData = $conn->prepare($dataSql);
$stmtData->bind_param($types2, ...$allParams);
$stmtData->execute();
$evList   = $stmtData->get_result()->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- SEARCH & FILTER BAR -->
<form method="GET" action="">
<div class="filter-bar fade-up">
  <div class="filter-group" style="flex:1;min-width:200px">
    <label>🔍 Cari Kendaraan</label>
    <input class="form-control" type="text" name="q" placeholder="Nama brand atau model..."
           value="<?= clean($search) ?>">
  </div>
  <div class="filter-group">
    <label>Segmen</label>
    <select class="form-control" name="segment">
      <option value="">Semua Segmen</option>
      <?php foreach ($opts['segments'] as $s): ?>
        <option value="<?= clean($s['segment']) ?>" <?= $filterSegment===$s['segment']?'selected':''?>>
          <?= clean($s['segment']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-group">
    <label>Penggerak</label>
    <select class="form-control" name="drivetrain">
      <option value="">Semua</option>
      <?php foreach ($opts['drivetrains'] as $d): ?>
        <option value="<?= clean($d['drivetrain']) ?>" <?= $filterDrive===$d['drivetrain']?'selected':''?>>
          <?= clean($d['drivetrain']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-group">
    <label>Tipe Bodi</label>
    <select class="form-control" name="body_type">
      <option value="">Semua</option>
      <?php foreach ($opts['body_types'] as $bt): ?>
        <option value="<?= clean($bt['car_body_type']) ?>" <?= $filterBody===$bt['car_body_type']?'selected':''?>>
          <?= clean($bt['car_body_type']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-group">
    <label>Kursi</label>
    <select class="form-control" name="seats">
      <option value="">Semua</option>
      <?php foreach ($opts['seats'] as $st): ?>
        <option value="<?= (int)$st['seats'] ?>" <?= $filterSeats==(int)$st['seats']?'selected':''?>>
          <?= (int)$st['seats'] ?> Penumpang
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-group" style="justify-content:flex-end">
    <label>&nbsp;</label>
    <div style="display:flex;gap:8px">
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="<?= APP_URL ?>/pages/katalog.php" class="btn btn-outline btn-sm">Reset</a>
    </div>
  </div>
</div>
</form>

<!-- HASIL INFO -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px" class="fade-up">
  <div style="font-size:0.86rem;color:var(--text-muted)">
    Menampilkan <strong><?= $total ?></strong> kendaraan
    <?= $search ? "untuk pencarian \"<em>".clean($search)."</em>\"" : '' ?>
    (Halaman <?= $page ?> dari <?= max(1,$totalPage) ?>)
  </div>
  <a href="<?= APP_URL ?>/pages/preferensi.php" class="btn btn-green btn-sm">
    ⚡ Mulai Analisis SPK
  </a>
</div>

<!-- TABEL EV -->
<div class="card fade-up">
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Brand</th>
            <th>Model</th>
            <th>Segmen</th>
            <th>Bodi</th>
            <th>Penggerak</th>
            <th title="Range (km)">Range</th>
            <th title="Efisiensi (Wh/km)">Efisiensi</th>
            <th title="Daya Pengisian (kW)">Fast Charge</th>
            <th title="Akselerasi 0-100km/h">0-100</th>
            <th title="Kapasitas Baterai (kWh)">Baterai</th>
            <th>Kursi</th>
            <th>Port</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($evList)): ?>
          <tr>
            <td colspan="13" style="text-align:center;padding:32px;color:var(--text-muted)">
              🔍 Tidak ada data yang cocok dengan filter Anda.
            </td>
          </tr>
          <?php else: ?>
          <?php foreach ($evList as $i => $ev): ?>
          <tr>
            <td style="color:var(--text-muted)"><?= ($page-1)*$perPage + $i + 1 ?></td>
            <td>
              <strong style="color:var(--primary)"><?= clean($ev['brand']) ?></strong>
            </td>
            <td style="font-size:0.83rem;max-width:200px">
              <?= clean($ev['model']) ?>
            </td>
            <td>
              <span class="badge badge-gray" style="font-size:0.68rem">
                <?= clean($ev['segment'] ?? '-') ?>
              </span>
            </td>
            <td style="font-size:0.82rem"><?= clean($ev['car_body_type'] ?? '-') ?></td>
            <td>
              <?php
                $dr = $ev['drivetrain'] ?? '-';
                $drClass = $dr === 'AWD' ? 'badge-blue' : ($dr === 'RWD' ? 'badge-green' : 'badge-orange');
              ?>
              <span class="badge <?= $drClass ?>"><?= $dr ?></span>
            </td>
            <td>
              <strong style="color:var(--primary)"><?= $ev['range_km'] ?? '-' ?></strong>
              <span style="font-size:0.68rem;color:var(--text-muted)"> km</span>
            </td>
            <td>
              <?= $ev['efficiency_wh_per_km'] ?? '-' ?>
              <span style="font-size:0.68rem;color:var(--text-muted)"> Wh/km</span>
            </td>
            <td>
              <?php $fc = $ev['fast_charging_power_kw'] ?? 0; ?>
              <span style="color:<?= $fc >= 150 ? 'var(--success)' : ($fc >= 100 ? 'var(--warning)' : 'var(--text-muted)') ?>">
                <?= $fc ?: '-' ?>
              </span>
              <?php if ($fc): ?><span style="font-size:0.68rem;color:var(--text-muted)"> kW</span><?php endif; ?>
            </td>
            <td>
              <?= $ev['acceleration_0_100_s'] ?? '-' ?>
              <span style="font-size:0.68rem;color:var(--text-muted)"> s</span>
            </td>
            <td>
              <?= $ev['battery_capacity_kwh'] ?? '-' ?>
              <span style="font-size:0.68rem;color:var(--text-muted)"> kWh</span>
            </td>
            <td><?= $ev['seats'] ?? '-' ?></td>
            <td>
              <span class="badge badge-gray" style="font-size:0.68rem">
                <?= clean($ev['fast_charge_port'] ?? '-') ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPage > 1): ?>
    <div style="padding:16px 20px;border-top:1px solid var(--border)">
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>" class="page-btn">← Prev</a>
        <?php endif; ?>

        <?php for ($p = max(1,$page-2); $p <= min($totalPage,$page+2); $p++): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$p])) ?>"
             class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>

        <?php if ($page < $totalPage): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>" class="page-btn">Next →</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- LEGENDA KRITERIA -->
<div class="card fade-up mt-4">
  <div class="card-header"><h3>ℹ️ Keterangan Kriteria Analisis SPK</h3></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:14px">
      <?php foreach (getKriteria() as $k): ?>
      <div style="display:flex;gap:10px;align-items:flex-start">
        <span style="background:var(--primary-light);color:var(--primary);font-weight:700;font-size:0.72rem;padding:3px 10px;border-radius:20px;flex-shrink:0;margin-top:2px">
          <?= clean($k['kode']) ?>
        </span>
        <div>
          <div style="font-weight:600;font-size:0.86rem"><?= clean($k['nama']) ?></div>
          <div style="font-size:0.75rem;color:var(--text-muted)"><?= clean($k['deskripsi'] ?? $k['satuan'] ?? '') ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php

// Pagination helper CSS
$extraScript = '<style>
.pagination { display:flex; gap:6px; align-items:center; justify-content:center; }
.page-btn { padding:7px 13px; border-radius:8px; border:1.5px solid var(--border); background:#fff; font-size:0.85rem; cursor:pointer; transition:all 0.2s; color:var(--text); text-decoration:none; }
.page-btn.active { background:var(--primary); border-color:var(--primary); color:#fff; }
.page-btn:hover:not(.active) { border-color:var(--primary); color:var(--primary); }
</style>';

include __DIR__ . '/../includes/footer.php'; ?>