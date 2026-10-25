<?php
// ============================================================
// FILE: pages/detail.php
// FUNGSI: Detail satu mobil listrik (foto, jenis, spesifikasi, sumber)
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$ev = $id > 0 ? fetchOne("SELECT * FROM kendaraan_ev WHERE id = ?", [$id], 'i') : null;
if (!$ev) {
    setFlash('warning', 'Kendaraan tidak ditemukan.');
    header('Location: ' . APP_URL . '/pages/katalog.php');
    exit;
}

$nama      = $ev['brand'] . ' ' . $ev['model'];
$pageTitle = $nama;
$back      = $_SERVER['HTTP_REFERER'] ?? (APP_URL . '/pages/katalog.php');
if (strpos($back, APP_URL) !== 0) $back = APP_URL . '/pages/katalog.php';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';

// Rentang min–maks dari dataset (bila ada); selain itu tampilkan nilai yang dipakai di perhitungan
function rentang($min, $max, $satuan, $fallback) {
    if ($min === null || $max === null) return angkaId($fallback) . ' ' . $satuan;
    if ((float)$min === (float)$max) return angkaId($min) . ' ' . $satuan;
    return angkaId($min) . ' – ' . angkaId($max) . ' ' . $satuan;
}
$hargaTxt = ($ev['harga_min'] !== null && $ev['harga_maks'] !== null && (float)$ev['harga_min'] !== (float)$ev['harga_maks'])
    ? formatRupiahJuta($ev['harga_min']) . ' – ' . formatRupiahJuta($ev['harga_maks'])
    : formatRupiahJuta($ev['harga_rp_juta']);
$spek = [
    ['Harga OTR (dataset)', $hargaTxt, 'C1 · cost'],
    ['Jangkauan', rentang($ev['range_min'], $ev['range_maks'], 'km', $ev['range_km']), 'C2 · benefit'],
    ['Kapasitas baterai', rentang($ev['baterai_min'], $ev['baterai_maks'], 'kWh', $ev['battery_capacity_kwh']), 'C3 · benefit'],
    ['Daya motor', rentang($ev['daya_min_kw'], $ev['daya_maks_kw'], 'kW', $ev['daya_kw']), 'C4 · benefit'],
    ['Waktu pengisian cepat DC', angkaId($ev['waktu_isi_menit']) . ' menit', 'C5 · cost'],
    ['Jumlah kursi', (int)$ev['seats'] . ' kursi', 'filter'],
    ['Jenis mobil', clean($ev['tipe_bodi'] ?? '-'), 'filter'],
    ['Kebutuhan', $ev['tipe_penggunaan'] === 'kantor' ? 'Kantor / niaga' : 'Perorangan', 'filter'],
];
$varian = !empty($ev['varian_json']) ? (json_decode($ev['varian_json'], true) ?: []) : [];
$warna  = array_filter(array_map('trim', explode(',', (string)($ev['warna'] ?? ''))));
$galeri = !empty($ev['galeri_json']) ? (json_decode($ev['galeri_json'], true) ?: []) : [];
$galData = [
    'Eksterior' => array_map(fn($u) => ['url' => $u], $galeri['eksterior'] ?? []),
    'Interior'  => array_map(fn($u) => ['url' => $u], $galeri['interior'] ?? []),
    'Warna'     => array_map(fn($w) => ['url' => $w['url'], 'cap' => $w['nama']], $galeri['warna'] ?? []),
];
if (!$galData['Eksterior'] && !empty($ev['foto_url'])) $galData['Eksterior'][] = ['url' => $ev['foto_url']];
$galData = array_filter($galData);
?>
<?php
$mm = fetchOne("SELECT MIN(harga_rp_juta) h0, MAX(harga_rp_juta) h1, MIN(range_km) r0, MAX(range_km) r1,
    MIN(battery_capacity_kwh) b0, MAX(battery_capacity_kwh) b1, MIN(daya_kw) d0, MAX(daya_kw) d1,
    MIN(waktu_isi_menit) w0, MAX(waktu_isi_menit) w1 FROM kendaraan_ev WHERE status='aktif'");
function posisi($v, $min, $max) { return $max > $min ? max(4, min(100, round(($v - $min) / ($max - $min) * 100))) : 100; }
$bar = [
    ['C1', 'Harga',             formatRupiahJuta($ev['harga_rp_juta']),          posisi($ev['harga_rp_juta'], $mm['h0'], $mm['h1']),      'cost',    'makin pendek makin murah'],
    ['C2', 'Jangkauan',         angkaId($ev['range_km']) . ' km',                posisi($ev['range_km'], $mm['r0'], $mm['r1']),           'benefit', 'makin panjang makin baik'],
    ['C3', 'Kapasitas baterai', angkaId($ev['battery_capacity_kwh']) . ' kWh',   posisi($ev['battery_capacity_kwh'], $mm['b0'], $mm['b1']), 'benefit', 'makin panjang makin baik'],
    ['C4', 'Daya motor',        angkaId($ev['daya_kw']) . ' kW',                 posisi($ev['daya_kw'], $mm['d0'], $mm['d1']),            'benefit', 'makin panjang makin baik'],
    ['C5', 'Waktu isi cepat DC', angkaId($ev['waktu_isi_menit']) . ' menit',     posisi($ev['waktu_isi_menit'], $mm['w0'], $mm['w1']),    'cost',    'makin pendek makin cepat'],
];
?>
<div class="mb-3"><a href="<?= clean($back) ?>" class="btn btn-outline btn-sm">← Kembali</a></div>

<div class="dt-head fade-up">
  <div>
    <div class="dt-brand"><?= clean($ev['brand']) ?></div>
    <h2 class="dt-title"><?= clean($ev['model']) ?></h2>
    <div class="dt-tags">
      <?php if (!empty($ev['tipe_bodi'])): ?><span><?= clean($ev['tipe_bodi']) ?></span><?php endif; ?>
      <span><?= (int)$ev['seats'] ?> kursi</span>
      <span><?= $ev['tipe_penggunaan'] === 'kantor' ? 'Kantor / niaga' : 'Perorangan' ?></span>
    </div>
  </div>
  <div class="dt-price">
    <small>Harga OTR</small>
    <strong><?= $hargaTxt ?></strong>
    <?php if (!empty($ev['source_url'])): ?>
      <a class="btn btn-outline btn-sm" href="<?= clean($ev['source_url']) ?>" target="_blank" rel="noopener noreferrer">Lihat di Oto.com ↗</a>
    <?php endif; ?>
  </div>
</div>

<div class="dt-layout fade-up">
  <div class="dt-gallery card">
    <div class="card-body">
      <div class="gal-tabs" id="galTabs"></div>
      <div class="gal-main">
        <img id="galImg" data-foto src="<?= clean($ev['foto_url'] ?? '') ?>" alt="<?= clean($nama) ?>" referrerpolicy="no-referrer">
        <button type="button" class="gal-nav prev" id="galPrev" aria-label="Sebelumnya">‹</button>
        <button type="button" class="gal-nav next" id="galNext" aria-label="Berikutnya">›</button>
        <span class="gal-cap" id="galCap" style="display:none"></span>
        <span class="gal-count" id="galCount"></span>
      </div>
      <div class="gal-thumbs" id="galThumbs"></div>
      <?php if ($warna): ?>
        <div class="dt-sub">Pilihan warna <span>(klik untuk melihat fotonya)</span></div>
        <div class="warna-chips" id="warnaChips">
          <?php foreach ($warna as $w): ?>
            <button type="button" data-warna="<?= clean($w) ?>"><?= clean($w) ?></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="dt-side">
    <div class="dt-tiles">
      <div><strong><?= angkaId($ev['range_km']) ?></strong><span>km jangkauan</span></div>
      <div><strong><?= angkaId($ev['battery_capacity_kwh']) ?></strong><span>kWh baterai</span></div>
      <div><strong><?= angkaId($ev['daya_kw']) ?></strong><span>kW daya motor</span></div>
      <div><strong><?= angkaId($ev['waktu_isi_menit']) ?></strong><span>menit isi cepat DC</span></div>
    </div>

    <div class="card">
      <div class="card-header"><h3>Posisi di antara <?= (int)fetchOne("SELECT COUNT(*) c FROM kendaraan_ev WHERE status='aktif'")['c'] ?> mobil</h3></div>
      <div class="card-body">
        <?php foreach ($bar as [$kode, $lbl, $nilai, $pos, $jenis, $ket]): ?>
        <div class="dt-bar">
          <div class="dt-bar-top"><span><b><?= $kode ?></b> <?= $lbl ?> <em>· <?= $jenis ?></em></span><strong><?= $nilai ?></strong></div>
          <div class="dt-bar-track"><div class="dt-bar-fill <?= $jenis ?>" style="width:<?= $pos ?>%"></div></div>
        </div>
        <?php endforeach; ?>
        <p class="dt-note">Panjang batang menunjukkan posisi nilai antara terendah dan tertinggi di seluruh data. Untuk kriteria cost (harga dan waktu isi), nilai kecil lebih baik.</p>
      </div>
    </div>
  </div>
</div>

<div class="dt-bottom fade-up">
  <div class="card">
    <div class="card-header"><h3>Spesifikasi lengkap</h3></div>
    <div class="card-body" style="padding-top:6px;padding-bottom:6px">
      <table class="table dt-table"><tbody>
      <?php foreach ($spek as [$label, $nilai, $ket]): ?>
        <tr><td><?= $label ?></td><td><strong><?= $nilai ?></strong></td><td class="k"><?= $ket ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
  </div>
  <div class="dt-stack">
    <div class="card">
      <div class="card-header"><h3>Varian dan harga</h3><span class="dt-src">Oto.com · <?= count($varian) ?> varian</span></div>
      <div class="card-body" style="padding-top:6px;padding-bottom:10px">
      <?php if ($varian):
        $hv = array_filter(array_column($varian, 'harga'), fn($x) => $x !== null);
        $hmin = $hv ? min($hv) : null; ?>
        <table class="table dt-table"><tbody>
        <?php foreach ($varian as $v): $h = $v['harga'] ?? null; ?>
          <tr><td><?= clean($v['nama']) ?></td>
              <td style="text-align:right"><strong><?= $h !== null ? formatRupiahJuta($h) : 'harga belum tercantum' ?></strong>
                <?php if ($h !== null && $hmin !== null && $h > $hmin): ?><div class="k">+<?= formatRupiahJuta($h - $hmin) ?> dari varian termurah</div><?php endif; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php if (count($varian) === 1): ?>
          <p class="dt-note">Oto.com hanya mencantumkan satu varian untuk model ini.</p>
        <?php elseif ($hv): ?>
          <p class="dt-note">Selisih harga varian termurah dan termahal: <strong><?= formatRupiahJuta(max($hv) - $hmin) ?></strong>.</p>
        <?php endif; ?>
      <?php else: ?><p class="dt-note" style="padding:14px 0">Data varian belum tersedia.</p><?php endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h3>Sumber dan catatan data</h3></div>
      <div class="card-body">
        <?php if (!empty($ev['source_url'])): ?>
          <p style="font-size:.85rem;margin-bottom:8px">Halaman asli: <a href="<?= clean($ev['source_url']) ?>" target="_blank" rel="noopener noreferrer">Oto.com ↗</a></p>
        <?php endif; ?>
        <p class="dt-note">Rentang harga, jangkauan, baterai, dan daya berasal dari dataset Kaggle (harga OTR April 2026); perhitungan AHP-TOPSIS memakai
        rata-rata nilai minimum dan maksimum. Daya motor dikonversi dari hp ke kW. Jenis mobil, foto, varian, harga varian, dan warna
        diambil dari halaman Oto.com pada Oktober 2026, sehingga harga varian dapat berbeda dari dataset.</p>
      </div>
    </div>
  </div>
</div>

<?php
$mirip = fetchAll("SELECT * FROM kendaraan_ev WHERE status='aktif' AND id <> ? AND foto_url IS NOT NULL AND foto_url <> ''
    ORDER BY (tipe_bodi = ?) DESC, ABS(harga_rp_juta - ?) ASC LIMIT 3", [$ev['id'], (string)$ev['tipe_bodi'], $ev['harga_rp_juta']], 'isd');
if ($mirip): ?>
<div class="dash-sec-head" style="margin-top:26px"><h3>Mobil serupa</h3><a href="<?= APP_URL ?>/pages/katalog.php">Lihat katalog →</a></div>
<div class="ev-grid">
  <?php foreach ($mirip as $m): $nm = $m['brand'].' '.$m['model']; ?>
  <a class="ev-card" href="<?= APP_URL ?>/pages/detail.php?id=<?= (int)$m['id'] ?>">
    <div class="ev-photo"><img src="<?= clean($m['foto_url']) ?>" alt="<?= clean($nm) ?>" data-foto loading="lazy" referrerpolicy="no-referrer">
      <span class="ev-tag"><?= clean($m['tipe_bodi'] ?? '') ?></span></div>
    <div class="ev-body">
      <div class="ev-brand"><?= clean($m['brand']) ?></div><div class="ev-name"><?= clean($m['model']) ?></div>
      <div class="ev-price"><?= formatRupiahJuta($m['harga_rp_juta']) ?></div>
      <div class="ev-spec"><span><strong><?= angkaId($m['range_km']) ?></strong> km</span><span><strong><?= angkaId($m['battery_capacity_kwh']) ?></strong> kWh</span><span>DC <strong><?= angkaId($m['waktu_isi_menit']) ?></strong> mnt</span></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
(function () {
  var data = <?= json_encode($galData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var tabs = Object.keys(data), cur = tabs[0], idx = 0;
  var img = document.getElementById('galImg'), cap = document.getElementById('galCap'),
      cnt = document.getElementById('galCount'), thumbs = document.getElementById('galThumbs'),
      tabBox = document.getElementById('galTabs');
  if (!tabs.length) { document.querySelector('.gal-main').innerHTML = '<div style="padding:60px 20px;text-align:center;color:var(--text-muted)">Foto belum tersedia</div>'; return; }
  function show() {
    var list = data[cur]; if (idx < 0) idx = list.length - 1; if (idx >= list.length) idx = 0;
    img.src = list[idx].url;
    cap.style.display = list[idx].cap ? '' : 'none'; cap.textContent = list[idx].cap || '';
    cnt.textContent = (idx + 1) + ' / ' + list.length;
    Array.prototype.forEach.call(thumbs.children, function (t, i) { t.className = i === idx ? 'active' : ''; });
    var chips = document.querySelectorAll('#warnaChips button');
    Array.prototype.forEach.call(chips, function (c) { c.className = (cur === 'Warna' && list[idx].cap === c.getAttribute('data-warna')) ? 'active' : ''; });
  }
  function build() {
    tabBox.innerHTML = '';
    tabs.forEach(function (t) {
      var b = document.createElement('button'); b.type = 'button'; b.textContent = t + ' (' + data[t].length + ')';
      b.className = t === cur ? 'active' : ''; b.onclick = function () { cur = t; idx = 0; build(); }; tabBox.appendChild(b);
    });
    thumbs.innerHTML = '';
    data[cur].forEach(function (p, i) {
      var t = document.createElement('img'); t.src = p.url; t.loading = 'lazy'; t.referrerPolicy = 'no-referrer';
      t.onclick = function () { idx = i; show(); }; thumbs.appendChild(t);
    });
    show();
  }
  document.getElementById('galPrev').onclick = function () { idx--; show(); };
  document.getElementById('galNext').onclick = function () { idx++; show(); };
  Array.prototype.forEach.call(document.querySelectorAll('#warnaChips button'), function (c) {
    var nama = c.getAttribute('data-warna');
    var i = (data['Warna'] || []).findIndex(function (w) { return w.cap === nama; });
    if (i < 0) { c.disabled = true; c.title = 'Foto warna ini belum tersedia'; c.style.opacity = .55; c.style.cursor = 'default'; return; }
    c.onclick = function () { cur = 'Warna'; idx = i; build(); };
  });
  build();
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
