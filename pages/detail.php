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
    if ($min === null || $max === null) return clean((float)$fallback) . ' ' . $satuan;
    if ((float)$min === (float)$max) return clean((float)$min) . ' ' . $satuan;
    return clean((float)$min) . ' – ' . clean((float)$max) . ' ' . $satuan;
}
$hargaTxt = ($ev['harga_min'] !== null && $ev['harga_maks'] !== null && (float)$ev['harga_min'] !== (float)$ev['harga_maks'])
    ? formatRupiahJuta($ev['harga_min']) . ' – ' . formatRupiahJuta($ev['harga_maks'])
    : formatRupiahJuta($ev['harga_rp_juta']);
$spek = [
    ['Harga OTR (dataset)', $hargaTxt, 'C1 · cost'],
    ['Jangkauan', rentang($ev['range_min'], $ev['range_maks'], 'km', $ev['range_km']), 'C2 · benefit'],
    ['Kapasitas baterai', rentang($ev['baterai_min'], $ev['baterai_maks'], 'kWh', $ev['battery_capacity_kwh']), 'C3 · benefit'],
    ['Daya motor', rentang($ev['daya_min_kw'], $ev['daya_maks_kw'], 'kW', $ev['daya_kw']), 'C4 · benefit'],
    ['Waktu pengisian cepat DC', clean((float)$ev['waktu_isi_menit']) . ' menit', 'C5 · cost'],
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
<div class="mb-3"><a href="<?= clean($back) ?>" class="btn btn-outline btn-sm">← Kembali</a></div>

<div class="card fade-up">
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;align-items:start">
      <div>
        <div class="gal-tabs" id="galTabs"></div>
        <div class="gal-main">
          <img id="galImg" src="<?= clean($ev['foto_url'] ?? '') ?>" alt="<?= clean($nama) ?>" referrerpolicy="no-referrer">
          <button type="button" class="gal-nav prev" id="galPrev" aria-label="Sebelumnya">‹</button>
          <button type="button" class="gal-nav next" id="galNext" aria-label="Berikutnya">›</button>
          <span class="gal-cap" id="galCap" style="display:none"></span>
          <span class="gal-count" id="galCount"></span>
        </div>
        <div class="gal-thumbs" id="galThumbs"></div>
      </div>
      <div>
        <h2 style="font-size:1.6rem;font-weight:700;margin-bottom:4px"><?= clean($nama) ?></h2>
        <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px">
          <?= clean($ev['tipe_bodi'] ?? '') ?><?= !empty($ev['tipe_bodi']) ? ' · ' : '' ?><?= (int)$ev['seats'] ?> kursi
        </p>
        <table class="table" style="width:100%">
          <tbody>
          <?php foreach ($spek as [$label, $nilai, $ket]): ?>
            <tr>
              <td style="color:var(--text-muted)"><?= $label ?></td>
              <td><strong><?= $nilai ?></strong></td>
              <td style="font-size:0.72rem;color:var(--text-muted)"><?= $ket ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php if ($varian): ?>
          <h4 style="margin:18px 0 6px;font-size:0.95rem">Varian dan harga di Oto.com</h4>
          <table class="table" style="width:100%">
            <tbody>
            <?php foreach ($varian as $v): ?>
              <tr>
                <td><?= clean($v['nama']) ?></td>
                <td><strong><?= isset($v['harga']) && $v['harga'] !== null ? formatRupiahJuta($v['harga']) : 'harga belum tercantum' ?></strong></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
        <?php if ($warna): ?>
          <h4 style="margin:18px 0 6px;font-size:0.95rem">Pilihan warna <span style="font-weight:400;font-size:0.74rem;color:var(--text-muted)">(klik untuk melihat fotonya)</span></h4>
          <div class="warna-chips" id="warnaChips">
            <?php foreach ($warna as $w): ?>
              <button type="button" data-warna="<?= clean($w) ?>"><?= clean($w) ?></button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($ev['source_url'])): ?>
          <p style="margin-top:14px;font-size:0.82rem">
            Sumber data: <a href="<?= clean($ev['source_url']) ?>" target="_blank" rel="noopener noreferrer">halaman Oto.com</a>
          </p>
        <?php endif; ?>
        <p style="margin-top:8px;font-size:0.74rem;color:var(--text-muted)">
          Rentang harga, jangkauan, baterai, dan daya berasal dari dataset Kaggle (harga OTR April 2026); perhitungan AHP-TOPSIS memakai
          rata-rata nilai minimum dan maksimum. Daya motor dikonversi dari hp ke kW. Jenis mobil, foto, varian, harga varian, dan warna
          diambil dari halaman Oto.com pada Oktober 2026, sehingga harga varian dapat berbeda dari dataset.
        </p>
      </div>
    </div>
  </div>
</div>

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
