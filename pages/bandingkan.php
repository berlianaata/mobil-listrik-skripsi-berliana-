<?php
// ============================================================
// FILE: pages/bandingkan.php
// FUNGSI: Membandingkan 2–4 mobil berdampingan
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')))));
$ids = array_slice($ids, 0, 4);
if (count($ids) < 2) {
    setFlash('warning', 'Pilih minimal 2 mobil dari Katalog untuk dibandingkan.');
    header('Location: ' . APP_URL . '/pages/katalog.php');
    exit;
}
$ph   = implode(',', array_fill(0, count($ids), '?'));
$rows = fetchAll("SELECT * FROM kendaraan_ev WHERE id IN ($ph)", $ids, str_repeat('i', count($ids)));
$map  = [];
foreach ($rows as $r) $map[(int)$r['id']] = $r;
$cars = [];
foreach ($ids as $i) if (isset($map[$i])) $cars[] = $map[$i];
if (count($cars) < 2) {
    setFlash('warning', 'Mobil yang dipilih tidak ditemukan.');
    header('Location: ' . APP_URL . '/pages/katalog.php');
    exit;
}

$pageTitle = 'Bandingkan Mobil';

// [label, kolom, tipe (cost|benefit|null), satuan, format]
$baris = [
    ['Harga OTR',                'harga_rp_juta',        'cost',    '',      'rp'],
    ['Jangkauan',                'range_km',             'benefit', 'km',    'n'],
    ['Kapasitas baterai',        'battery_capacity_kwh', 'benefit', 'kWh',   'n'],
    ['Daya motor',               'daya_kw',              'benefit', 'kW',    'n'],
    ['Waktu isi cepat DC',       'waktu_isi_menit',      'cost',    'menit', 'n'],
    ['Jumlah kursi',             'seats',                null,      'kursi', 'n'],
    ['Jenis mobil',              'tipe_bodi',            null,      '',      't'],
    ['Kebutuhan',                'tipe_penggunaan',      null,      '',      'k'],
];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="mb-3"><a href="<?= APP_URL ?>/pages/katalog.php" class="btn btn-outline btn-sm">← Kembali ke Katalog</a></div>

<div class="card fade-up">
  <div class="card-body" style="overflow-x:auto">
    <table class="cmp-table">
      <thead>
        <tr>
          <th></th>
          <?php foreach ($cars as $c): ?>
          <th>
            <a href="<?= APP_URL ?>/pages/detail.php?id=<?= (int)$c['id'] ?>" style="text-decoration:none;color:inherit">
              <div class="ev-photo" style="border-radius:10px;overflow:hidden;margin-bottom:8px">
                <?php if (!empty($c['foto_url'])): ?>
                  <img src="<?= clean($c['foto_url']) ?>" alt="<?= clean($c['brand'].' '.$c['model']) ?>" data-foto referrerpolicy="no-referrer">
                <?php else: ?><div class="ev-nophoto">Foto tidak tersedia</div><?php endif; ?>
              </div>
              <div class="ev-brand"><?= clean($c['brand']) ?></div>
              <div class="ev-name"><?= clean($c['model']) ?></div>
            </a>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($baris as [$label, $kol, $tipe, $sat, $fmt]):
          $best = null;
          if ($tipe) {
              $vals = array_map(fn($c) => (float)$c[$kol], $cars);
              $best = $tipe === 'cost' ? min($vals) : max($vals);
          } ?>
        <tr>
          <td class="cmp-label"><?= clean($label) ?><?= $tipe ? ' <small>(' . ($tipe === 'cost' ? 'makin kecil makin baik' : 'makin besar makin baik') . ')</small>' : '' ?></td>
          <?php foreach ($cars as $c):
            $v = $c[$kol];
            $isBest = $tipe && (float)$v === $best && count(array_unique(array_map(fn($x) => (float)$x[$kol], $cars))) > 1;
            if ($fmt === 'rp') $txt = formatRupiahJuta($v);
            elseif ($fmt === 'n') $txt = angkaId($v) . ($sat ? ' ' . $sat : '');
            elseif ($fmt === 'k') $txt = $v === 'kantor' ? 'Kantor / niaga' : 'Perorangan';
            else $txt = $v ?: '-'; ?>
          <td class="<?= $isBest ? 'best' : '' ?>"><?= clean($txt) ?><?= $isBest ? ' ✓' : '' ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p style="font-size:.76rem;color:var(--text-muted);margin-top:12px">
      Tanda ✓ menandai nilai terbaik pada kriteria AHP-TOPSIS (C1–C5). Jumlah kursi, jenis, dan kebutuhan adalah filter, bukan kriteria.
      Data harga dari dataset Kaggle (April 2026).
    </p>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
