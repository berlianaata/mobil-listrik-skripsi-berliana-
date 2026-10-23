<?php
// ============================================================
// FILE: pages/katalog.php
// FUNGSI: Katalog semua kendaraan EV dengan filter dan pencarian
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pageTitle = 'Katalog Mobil Listrik Indonesia';
$opts      = getFilterOptions();

// ─── Filter dari GET ───
$filterTipe     = $_GET['tipe']       ?? '';
$filterBodi     = $_GET['bodi']       ?? '';
$search         = trim($_GET['q']     ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 15;

// ─── Query ───
$conn  = db();
$where = ["status = 'aktif'"];
$params= [];

if (in_array($filterTipe, ['pribadi','kantor'], true)) { $where[] = "tipe_penggunaan = ?"; $params[] = $filterTipe; }
if (in_array($filterBodi, $opts['bodi'], true)) { $where[] = "tipe_bodi = ?"; $params[] = $filterBodi; }
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
$dataSql  = "SELECT * FROM kendaraan_ev $whereStr ORDER BY harga_rp_juta ASC LIMIT ? OFFSET ?";
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
    <label>Cari Kendaraan</label>
    <input class="form-control" type="text" name="q" placeholder="Nama brand atau model..."
           value="<?= clean($search) ?>">
  </div>
  <div class="filter-group">
    <label>Kebutuhan</label>
    <select class="form-control" name="tipe">
      <option value="">Semua</option>
      <?php foreach ($opts['tipe'] as $t): ?>
        <option value="<?= clean($t['value']) ?>" <?= $filterTipe===$t['value']?'selected':''?>>
          <?= clean($t['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="filter-group">
    <label>Jenis Mobil</label>
    <select class="form-control" name="bodi">
      <option value="">Semua</option>
      <?php foreach ($opts['bodi'] as $b): ?>
        <option value="<?= clean($b) ?>" <?= $filterBodi===$b?'selected':''?>><?= clean($b) ?></option>
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
  <a href="<?= APP_URL ?>/pages/preferensi.php" class="btn btn-primary btn-sm">
    Mulai Analisis SPK
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
            <th>Foto</th>
            <th>Brand</th>
            <th>Model</th>
            <th>Jenis</th>
            <th>Kebutuhan</th>
            <th title="Harga OTR (Rp juta)">Harga</th>
            <th title="Jangkauan (km)">Range</th>
            <th title="Kapasitas Baterai (kWh)">Baterai</th>
            <th title="Waktu pengisian cepat DC (menit)">Isi DC</th>
            <th title="Daya motor (kW)">Daya</th>
            <th>Kursi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($evList)): ?>
          <tr>
            <td colspan="12" style="text-align:center;padding:32px;color:var(--text-muted)">
              Tidak ada data yang cocok dengan filter Anda.
            </td>
          </tr>
          <?php else: ?>
          <?php foreach ($evList as $i => $ev): ?>
          <tr>
            <td style="color:var(--text-muted)"><?= ($page-1)*$perPage + $i + 1 ?></td>
            <td>
              <?php if (!empty($ev['foto_url'])): ?>
              <a href="<?= APP_URL ?>/pages/detail.php?id=<?= (int)$ev['id'] ?>"><img src="<?= clean($ev['foto_url']) ?>" alt="<?= clean($ev['brand'].' '.$ev['model']) ?>" loading="lazy" referrerpolicy="no-referrer"
                   style="width:84px;height:56px;object-fit:cover;border-radius:6px;display:block"></a>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?= APP_URL ?>/pages/detail.php?id=<?= (int)$ev['id'] ?>" style="text-decoration:none"><strong style="color:var(--primary)"><?= clean($ev['brand']) ?></strong></a>
            </td>
            <td style="font-size:0.83rem;max-width:200px">
              <a href="<?= APP_URL ?>/pages/detail.php?id=<?= (int)$ev['id'] ?>"><?= clean($ev['model']) ?></a>
            </td>
            <td style="font-size:0.8rem"><?= clean($ev['tipe_bodi'] ?? '-') ?></td>
            <td>
              <span class="badge <?= $ev['tipe_penggunaan']==='kantor' ? 'badge-orange' : 'badge-green' ?>" style="font-size:0.68rem">
                <?= $ev['tipe_penggunaan']==='kantor' ? 'Kantor' : 'Perorangan' ?>
              </span>
            </td>
            <td><?= formatRupiahJuta($ev['harga_rp_juta']) ?></td>
            <td>
              <strong style="color:var(--primary)"><?= clean((float)$ev['range_km']) ?></strong>
              <span style="font-size:0.68rem;color:var(--text-muted)"> km</span>
            </td>
            <td>
              <?= clean((float)$ev['battery_capacity_kwh']) ?>
              <span style="font-size:0.68rem;color:var(--text-muted)"> kWh</span>
            </td>
            <td>
              <?= clean((float)$ev['waktu_isi_menit']) ?>
              <span style="font-size:0.68rem;color:var(--text-muted)"> mnt</span>
            </td>
            <td>
              <?= clean((float)$ev['daya_kw']) ?>
              <span style="font-size:0.68rem;color:var(--text-muted)"> kW</span>
            </td>
            <td><?= (int)$ev['seats'] ?></td>
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
  <div class="card-header"><h3>Keterangan Kriteria Analisis SPK</h3></div>
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