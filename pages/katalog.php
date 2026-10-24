<?php
// ============================================================
// FILE: pages/katalog.php
// FUNGSI: Katalog EV: filter sisi kiri, urutan, kartu bergambar, bandingkan
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pageTitle = 'Katalog Mobil Listrik Indonesia';
$opts      = getFilterOptions();
$conn      = db();

// ─── Filter dari GET ───
$f = [
    'q'      => trim($_GET['q'] ?? ''),
    'brand'  => trim($_GET['brand'] ?? ''),
    'bodi'   => trim($_GET['bodi'] ?? ''),
    'tipe'   => $_GET['tipe'] ?? '',
    'kursi'  => max(0, (int)($_GET['kursi'] ?? 0)),
    'hmin'   => max(0, (float)($_GET['hmin'] ?? 0)),
    'hmax'   => max(0, (float)($_GET['hmax'] ?? 0)),
    'range'  => max(0, (int)($_GET['range'] ?? 0)),
    'dc'     => max(0, (int)($_GET['dc'] ?? 0)),
];
$sortOpts = [
    'harga_asc'  => ['Harga terendah',     'harga_rp_juta ASC'],
    'harga_desc' => ['Harga tertinggi',    'harga_rp_juta DESC'],
    'range'      => ['Jangkauan terjauh',  'range_km DESC'],
    'baterai'    => ['Baterai terbesar',   'battery_capacity_kwh DESC'],
    'daya'       => ['Daya tertinggi',     'daya_kw DESC'],
    'dc'         => ['Pengisian tercepat', 'waktu_isi_menit ASC'],
    'nama'       => ['Nama A–Z',           'brand ASC, model ASC'],
];
$sort    = isset($sortOpts[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'harga_asc';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

$where  = ["status = 'aktif'"];
$params = [];
$types  = '';
$add = function ($sql, $val, $t) use (&$where, &$params, &$types) { $where[] = $sql; $params[] = $val; $types .= $t; };

if (in_array($f['tipe'], ['pribadi', 'kantor'], true)) $add('tipe_penggunaan = ?', $f['tipe'], 's');
if ($f['bodi'] !== '' && in_array($f['bodi'], $opts['bodi'], true)) $add('tipe_bodi = ?', $f['bodi'], 's');
if ($f['brand'] !== '') $add('brand = ?', $f['brand'], 's');
if ($f['kursi'] > 0)    $add('seats >= ?', $f['kursi'], 'i');
if ($f['hmin'] > 0)     $add('harga_rp_juta >= ?', $f['hmin'], 'd');
if ($f['hmax'] > 0)     $add('harga_rp_juta <= ?', $f['hmax'], 'd');
if ($f['range'] > 0)    $add('range_km >= ?', $f['range'], 'i');
if ($f['dc'] > 0)       $add('waktu_isi_menit <= ?', $f['dc'], 'i');
if ($f['q'] !== '') {
    $where[] = '(brand LIKE ? OR model LIKE ?)';
    $params[] = '%' . $f['q'] . '%'; $params[] = '%' . $f['q'] . '%'; $types .= 'ss';
}
$whereStr = 'WHERE ' . implode(' AND ', $where);

$stmt = $conn->prepare("SELECT COUNT(*) FROM kendaraan_ev $whereStr");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total     = (int)$stmt->get_result()->fetch_row()[0];
$totalPage = max(1, (int)ceil($total / $perPage));
$page      = min($page, $totalPage);
$offset    = ($page - 1) * $perPage;

$stmt = $conn->prepare("SELECT * FROM kendaraan_ev $whereStr ORDER BY {$sortOpts[$sort][1]} LIMIT ? OFFSET ?");
$stmt->bind_param($types . 'ii', ...array_merge($params, [$perPage, $offset]));
$stmt->execute();
$evList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$brands = fetchAll("SELECT brand, COUNT(*) c FROM kendaraan_ev WHERE status='aktif' GROUP BY brand ORDER BY brand");
$bodiCnt = [];
foreach (fetchAll("SELECT tipe_bodi, COUNT(*) c FROM kendaraan_ev WHERE status='aktif' AND tipe_bodi IS NOT NULL GROUP BY tipe_bodi") as $r) $bodiCnt[$r['tipe_bodi']] = $r['c'];

// Tautan pintas
$pintas = [
    'Termurah'            => ['sort' => 'harga_asc'],
    'Jangkauan terjauh'   => ['sort' => 'range'],
    'Pengisian tercepat'  => ['sort' => 'dc'],
    'SUV termurah'        => ['bodi' => 'SUV', 'sort' => 'harga_asc'],
    'Tujuh kursi ke atas' => ['kursi' => 7, 'sort' => 'harga_asc'],
    'Kendaraan kantor'    => ['tipe' => 'kantor'],
];
$aktif = array_filter($f, fn($v) => $v !== '' && $v !== 0 && $v !== 0.0);
$qs = fn(array $o) => '?' . http_build_query($o);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="cat-layout fade-up">

  <!-- FILTER SISI KIRI -->
  <aside class="cat-side">
    <details class="cat-filter" open>
      <summary>Filter kendaraan</summary>
      <form method="GET" action="" id="formFilter">
        <input type="hidden" name="sort" value="<?= clean($sort) ?>">

        <div class="fgroup">
          <label>Cari</label>
          <input class="form-control" type="text" name="q" placeholder="Merek atau model..." value="<?= clean($f['q']) ?>">
        </div>

        <div class="fgroup">
          <label>Merek</label>
          <select class="form-control" name="brand">
            <option value="">Semua merek</option>
            <?php foreach ($brands as $b): ?>
              <option value="<?= clean($b['brand']) ?>" <?= $f['brand'] === $b['brand'] ? 'selected' : '' ?>><?= clean($b['brand']) ?> (<?= (int)$b['c'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="fgroup">
          <label>Jenis mobil</label>
          <div class="opt-list">
            <label><input type="radio" name="bodi" value="" <?= $f['bodi'] === '' ? 'checked' : '' ?>> Semua</label>
            <?php foreach ($opts['bodi'] as $b): ?>
              <label><input type="radio" name="bodi" value="<?= clean($b) ?>" <?= $f['bodi'] === $b ? 'checked' : '' ?>> <?= clean($b) ?> <em><?= (int)($bodiCnt[$b] ?? 0) ?></em></label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="fgroup">
          <label>Kebutuhan</label>
          <select class="form-control" name="tipe">
            <option value="">Perorangan &amp; kantor</option>
            <?php foreach ($opts['tipe'] as $t): ?>
              <option value="<?= clean($t['value']) ?>" <?= $f['tipe'] === $t['value'] ? 'selected' : '' ?>><?= clean($t['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="fgroup">
          <label>Jumlah kursi (minimum)</label>
          <div class="chip-radio">
            <?php foreach ([0 => 'Semua', 2 => '2+', 4 => '4+', 5 => '5+', 6 => '6+', 7 => '7+'] as $v => $lbl): ?>
              <label><input type="radio" name="kursi" value="<?= $v ?>" <?= $f['kursi'] === $v ? 'checked' : '' ?>><span><?= $lbl ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="fgroup">
          <label>Harga (Rp juta)</label>
          <div style="display:flex;gap:6px;align-items:center">
            <input class="form-control" type="number" min="0" name="hmin" id="hmin" placeholder="Min" value="<?= $f['hmin'] > 0 ? clean($f['hmin']) : '' ?>">
            <span>–</span>
            <input class="form-control" type="number" min="0" name="hmax" id="hmax" placeholder="Maks" value="<?= $f['hmax'] > 0 ? clean($f['hmax']) : '' ?>">
          </div>
          <div class="quick-ang">
            <?php foreach ([300, 500, 750, 1000, 2000] as $q): ?>
              <button type="button" onclick="document.getElementById('hmin').value='';document.getElementById('hmax').value=<?= $q ?>">≤ <?= $q ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="fgroup">
          <label>Jangkauan minimum: <strong id="rangeOut"><?= $f['range'] > 0 ? $f['range'] . ' km' : 'semua' ?></strong></label>
          <input type="range" name="range" min="0" max="700" step="50" value="<?= (int)$f['range'] ?>"
                 oninput="document.getElementById('rangeOut').textContent = this.value > 0 ? this.value + ' km' : 'semua'">
        </div>

        <div class="fgroup">
          <label>Pengisian cepat DC (maksimum)</label>
          <select class="form-control" name="dc">
            <option value="0">Semua</option>
            <?php foreach ([30, 45, 60, 90] as $m): ?>
              <option value="<?= $m ?>" <?= $f['dc'] === $m ? 'selected' : '' ?>>≤ <?= $m ?> menit</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="display:flex;gap:8px;margin-top:6px">
          <button type="submit" class="btn btn-primary btn-sm" style="flex:1">Terapkan</button>
          <a href="<?= APP_URL ?>/pages/katalog.php" class="btn btn-outline btn-sm">Reset</a>
        </div>
      </form>
    </details>
  </aside>

  <!-- DAFTAR KENDARAAN -->
  <section class="cat-main">
    <div class="pintas">
      <?php foreach ($pintas as $lbl => $o): ?>
        <a href="<?= clean($qs($o)) ?>"><?= clean($lbl) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="cat-top">
      <div class="cat-count">
        <?php if ($total > 0): ?>
          Menampilkan <strong><?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?></strong> dari <strong><?= $total ?></strong> kendaraan
        <?php else: ?>Tidak ada kendaraan<?php endif; ?>
      </div>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <form method="GET" action="" style="display:flex;gap:6px;align-items:center">
          <?php foreach ($_GET as $k => $v): if ($k === 'sort' || $k === 'page' || is_array($v)) continue; ?>
            <input type="hidden" name="<?= clean($k) ?>" value="<?= clean($v) ?>">
          <?php endforeach; ?>
          <label for="sort" style="font-size:.8rem;color:var(--text-muted)">Urutkan</label>
          <select class="form-control" id="sort" name="sort" onchange="this.form.submit()" style="width:auto">
            <?php foreach ($sortOpts as $k => [$lbl]): ?>
              <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= clean($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <a href="<?= APP_URL ?>/pages/preferensi.php" class="btn btn-primary btn-sm">Mulai Analisis SPK</a>
      </div>
    </div>

    <?php if (empty($evList)): ?>
      <div class="card"><div class="card-body" style="text-align:center;padding:40px;color:var(--text-muted)">
        Tidak ada data yang cocok dengan filter Anda. <a href="<?= APP_URL ?>/pages/katalog.php">Reset filter</a>
      </div></div>
    <?php else: ?>
    <div class="ev-grid">
      <?php foreach ($evList as $ev):
        $url = APP_URL . '/pages/detail.php?id=' . (int)$ev['id'];
        $namaEv = $ev['brand'] . ' ' . $ev['model']; ?>
      <div class="ev-wrap">
        <a class="ev-card" href="<?= $url ?>">
          <div class="ev-photo">
            <?php if (!empty($ev['foto_url'])): ?>
              <img src="<?= clean($ev['foto_url']) ?>" alt="<?= clean($namaEv) ?>" data-foto loading="lazy" referrerpolicy="no-referrer">
            <?php else: ?><div class="ev-nophoto">Foto tidak tersedia</div><?php endif; ?>
            <span class="ev-tag"><?= $ev['tipe_penggunaan'] === 'kantor' ? 'Kantor' : 'Perorangan' ?></span>
          </div>
          <div class="ev-body">
            <div class="ev-brand"><?= clean($ev['brand']) ?></div>
            <div class="ev-name"><?= clean($ev['model']) ?></div>
            <div class="ev-meta"><?= clean($ev['tipe_bodi'] ?? '') ?><?= !empty($ev['tipe_bodi']) ? ' · ' : '' ?><?= (int)$ev['seats'] ?> kursi</div>
            <div class="ev-price"><?= formatRupiahJuta($ev['harga_rp_juta']) ?></div>
            <div class="ev-spec">
              <span><strong><?= angkaId($ev['range_km']) ?></strong> km</span>
              <span><strong><?= angkaId($ev['battery_capacity_kwh']) ?></strong> kWh</span>
              <span><strong><?= angkaId($ev['daya_kw']) ?></strong> kW</span>
              <span>DC <strong><?= angkaId($ev['waktu_isi_menit']) ?></strong> mnt</span>
            </div>
          </div>
        </a>
        <button type="button" class="cmp-btn" data-id="<?= (int)$ev['id'] ?>" data-nama="<?= clean($namaEv) ?>">+ Bandingkan</button>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($totalPage > 1): ?>
    <div style="padding:22px 0">
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-btn">← Prev</a>
        <?php endif; ?>
        <?php for ($p = max(1, $page - 2); $p <= min($totalPage, $page + 2); $p++): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPage): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-btn">Next →</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </section>
</div>

<!-- BAR BANDINGKAN -->
<div class="cmp-bar" id="cmpBar" hidden>
  <div><strong id="cmpN">0</strong> mobil dipilih <span id="cmpNames" style="color:var(--text-muted);font-size:.8rem"></span></div>
  <div style="display:flex;gap:8px">
    <button type="button" class="btn btn-outline btn-sm" id="cmpClear">Kosongkan</button>
    <a class="btn btn-primary btn-sm" id="cmpGo" href="#">Bandingkan</a>
  </div>
</div>

<script>
(function () {
  var KEY = 'spkev_bandingkan', base = '<?= APP_URL ?>/pages/bandingkan.php';
  function load() { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } }
  function save(a) { try { localStorage.setItem(KEY, JSON.stringify(a)); } catch (e) {} }
  var sel = load();
  var bar = document.getElementById('cmpBar');
  function render() {
    document.querySelectorAll('.cmp-btn').forEach(function (b) {
      var on = sel.some(function (s) { return s.id === b.dataset.id; });
      b.classList.toggle('on', on); b.textContent = on ? '✓ Dipilih' : '+ Bandingkan';
    });
    bar.hidden = sel.length === 0;
    document.getElementById('cmpN').textContent = sel.length;
    document.getElementById('cmpNames').textContent = '(' + sel.map(function (s) { return s.nama; }).join(', ') + ')';
    var go = document.getElementById('cmpGo');
    go.href = base + '?ids=' + sel.map(function (s) { return s.id; }).join(',');
    go.style.opacity = sel.length < 2 ? .5 : 1; go.style.pointerEvents = sel.length < 2 ? 'none' : 'auto';
  }
  document.querySelectorAll('.cmp-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = sel.findIndex(function (s) { return s.id === b.dataset.id; });
      if (i >= 0) sel.splice(i, 1);
      else if (sel.length >= 4) { alert('Maksimal 4 mobil untuk dibandingkan.'); return; }
      else sel.push({ id: b.dataset.id, nama: b.dataset.nama });
      save(sel); render();
    });
  });
  document.getElementById('cmpClear').addEventListener('click', function () { sel = []; save(sel); render(); });
  render();
  // Di layar kecil, panel filter tertutup dulu
  if (window.innerWidth < 900) { var d = document.querySelector('.cat-filter'); if (d) d.removeAttribute('open'); }
})();
</script>

<?php
$extraScript = '<style>
.pagination { display:flex; gap:6px; align-items:center; justify-content:center; }
.page-btn { padding:7px 13px; border-radius:8px; border:1.5px solid var(--border); background:#fff; font-size:0.85rem; cursor:pointer; transition:all 0.2s; color:var(--text); text-decoration:none; }
.page-btn.active { background:var(--primary); border-color:var(--primary); color:#fff; }
.page-btn:hover:not(.active) { border-color:var(--primary); color:var(--primary); }
</style>';

include __DIR__ . '/../includes/footer.php'; ?>
