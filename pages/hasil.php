<?php
// ============================================================
// FILE: pages/hasil.php
// FUNGSI: Tampilan hasil rekomendasi EV terbaik berdasarkan AHP-TOPSIS
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/TOPSIS.php';
requireLogin();

$pageTitle = 'Hasil & Rekomendasi';
$userId    = $_SESSION['user_id'];

// ─── Ambil bobot AHP ───
$bobotDB = getBobotAHP($userId);
if (empty($bobotDB)) {
    setFlash('warning', 'Belum ada bobot AHP. Silakan lakukan penilaian AHP terlebih dahulu.');
    header('Location: ' . APP_URL . '/pages/ahp.php');
    exit;
}

$kriteria  = getKriteria();
$bobotArr  = []; $tipeArr = []; $namaKrit = []; $kolom = [];
foreach ($kriteria as $k) {
    foreach ($bobotDB as $b) {
        if ($b['kode'] === $k['kode']) {
            $bobotArr[] = (float)$b['bobot'];
            $tipeArr[]  = $k['tipe'];
            $namaKrit[] = $k['kode'];
            $kolom[]    = $k['kolom_db'] ?? 'range_km';
            break;
        }
    }
}

// ─── Ambil kendaraan ───
$pref   = getPreferensiUser($userId);
$filter = [
    'segment'    => $pref['filter_segment'] ?? null,
    'drivetrain' => $pref['filter_drive']   ?? null,
    'body_type'  => $pref['filter_body']    ?? null,
    'seats'      => $pref['filter_seats']   ?? null,
];
$limit     = $pref['jumlah_alt'] ?? 20;
$kendaraan = getKendaraanFiltered($filter, $limit);

if (count($kendaraan) < 2) {
    setFlash('warning', 'Data kendaraan tidak cukup untuk analisis.');
    header('Location: ' . APP_URL . '/pages/preferensi.php');
    exit;
}

// ─── Hitung ulang TOPSIS ───
$dataMatrix = []; $namaAlt = [];
foreach ($kendaraan as $ev) {
    $baris = [];
    foreach ($kolom as $col) $baris[] = (float)($ev[$col] ?? 0);
    $dataMatrix[] = $baris;
    $namaAlt[]    = $ev['brand'] . ' ' . $ev['model'];
}

$topsis = new TOPSIS($dataMatrix, $bobotArr, $tipeArr, $namaAlt, $namaKrit);
$hasil  = $topsis->hitung();
$ranking = $hasil['ranking'];
$top3   = array_slice($ranking, 0, 3);
$top1   = $ranking[0];

// Ambil detail EV dari DB untuk top ranking
$evMap = [];
foreach ($kendaraan as $ev) {
    $evMap[$ev['brand'] . ' ' . $ev['model']] = $ev;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- STEP NAV -->
<div class="step-nav fade-up mb-4">
  <div class="step-item done"><div class="step-num">✓</div><div class="step-label">Preferensi</div></div>
  <div class="step-item done"><div class="step-num">✓</div><div class="step-label">AHP</div></div>
  <div class="step-item done"><div class="step-num">✓</div><div class="step-label">TOPSIS</div></div>
  <div class="step-item active"><div class="step-num">4</div><div class="step-label">Hasil</div></div>
</div>

<!-- HERO REKOMENDASI TERBAIK -->
<?php
  $ev1   = $evMap[$top1['nama']] ?? null;
  $parts = explode(' ', $top1['nama'], 2);
?>
<div class="card fade-up mb-4" style="background:linear-gradient(135deg,#0F1728,#1E3A5F);padding:32px;border-radius:var(--radius)">
  <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
    <div style="font-size:4.5rem;filter:drop-shadow(0 4px 8px rgba(0,0,0,0.3))">🏆</div>
    <div style="flex:1">
      <div style="font-size:0.78rem;color:rgba(255,255,255,0.5);margin-bottom:4px;font-weight:700;letter-spacing:1px;text-transform:uppercase">
        🥇 Rekomendasi Terbaik — Rank #1
      </div>
      <h2 style="font-family:'Space Grotesk',sans-serif;font-size:2rem;font-weight:800;color:#fff;margin-bottom:6px;line-height:1.15">
        <?= clean($top1['nama']) ?>
      </h2>
      <?php if ($ev1): ?>
      <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px">
        <span style="color:rgba(255,255,255,0.7);font-size:0.85rem">🔋 <?= $ev1['battery_capacity_kwh'] ?> kWh</span>
        <span style="color:rgba(255,255,255,0.7);font-size:0.85rem">📏 <?= $ev1['range_km'] ?> km</span>
        <span style="color:rgba(255,255,255,0.7);font-size:0.85rem">⚡ <?= $ev1['fast_charging_power_kw'] ?> kW Charging</span>
        <span style="color:rgba(255,255,255,0.7);font-size:0.85rem">🏎️ 0-100 dalam <?= $ev1['acceleration_0_100_s'] ?>s</span>
        <span style="color:rgba(255,255,255,0.7);font-size:0.85rem">👥 <?= $ev1['seats'] ?> kursi</span>
      </div>
      <?php endif; ?>
      <p style="color:rgba(255,255,255,0.55);font-size:0.85rem">
        Dipilih sebagai EV terbaik berdasarkan analisis AHP & TOPSIS
        dari <?= count($kendaraan) ?> alternatif kendaraan listrik.
      </p>
    </div>
    <div style="text-align:right;flex-shrink:0">
      <div style="background:rgba(0,200,150,0.15);border:2px solid rgba(0,200,150,0.4);border-radius:14px;padding:20px 24px">
        <div style="font-size:0.72rem;color:rgba(255,255,255,0.5);margin-bottom:4px">Skor TOPSIS (CC)</div>
        <div style="font-size:2.8rem;font-weight:800;color:var(--primary);line-height:1"><?= formatAngka($top1['CC'], 4) ?></div>
        <div style="font-size:0.78rem;color:rgba(255,255,255,0.4);margin-top:4px"><?= round($top1['CC']*100, 1) ?>% mendekati ideal</div>
        <div class="cc-bar" style="width:100%;margin-top:8px;height:6px">
          <div class="cc-bar-fill" style="width:<?= round($top1['CC']*100,1) ?>%"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- PODIUM TOP 3 -->
<div class="card fade-up mb-4">
  <div class="card-header">
    <h3>🥇🥈🥉 Peringkat 3 Besar</h3>
    <span class="badge badge-green"><?= count($ranking) ?> alternatif dianalisis</span>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px">
      <?php foreach ($top3 as $r):
        $evX   = $evMap[$r['nama']] ?? null;
        $parts2= explode(' ', $r['nama'], 2);
        $medals = [1=>'medal-1',2=>'medal-2',3=>'medal-3'];
        $icons  = [1=>'🥇',2=>'🥈',3=>'🥉'];
        $colors = [1=>'#F59E0B',2=>'#94A3B8',3=>'#CD7F32'];
      ?>
      <div class="card" style="border:2px solid <?= $colors[$r['rank']] ?>;border-radius:var(--radius)">
        <div style="padding:20px">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
            <div class="rank-medal <?= $medals[$r['rank']] ?>" style="width:44px;height:44px;font-size:1.3rem">
              <?= $icons[$r['rank']] ?>
            </div>
            <div>
              <div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase">Peringkat <?= $r['rank'] ?></div>
              <div style="font-weight:700;font-size:0.95rem;color:var(--secondary)"><?= clean($r['nama']) ?></div>
            </div>
          </div>

          <?php if ($evX): ?>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px">
            <div style="background:#F8FAFC;border-radius:8px;padding:10px;text-align:center">
              <div style="font-size:1.1rem;font-weight:800;color:var(--primary)"><?= $evX['range_km'] ?></div>
              <div style="font-size:0.68rem;color:var(--text-muted)">km Jangkauan</div>
            </div>
            <div style="background:#F8FAFC;border-radius:8px;padding:10px;text-align:center">
              <div style="font-size:1.1rem;font-weight:800;color:var(--info)"><?= $evX['fast_charging_power_kw'] ?></div>
              <div style="font-size:0.68rem;color:var(--text-muted)">kW Charging</div>
            </div>
            <div style="background:#F8FAFC;border-radius:8px;padding:10px;text-align:center">
              <div style="font-size:1.1rem;font-weight:800;color:var(--warning)"><?= $evX['acceleration_0_100_s'] ?>s</div>
              <div style="font-size:0.68rem;color:var(--text-muted)">0-100 km/h</div>
            </div>
            <div style="background:#F8FAFC;border-radius:8px;padding:10px;text-align:center">
              <div style="font-size:1.1rem;font-weight:800;color:var(--success)"><?= $evX['efficiency_wh_per_km'] ?></div>
              <div style="font-size:0.68rem;color:var(--text-muted)">Wh/km</div>
            </div>
          </div>
          <?php endif; ?>

          <div style="background:var(--primary-light);border-radius:10px;padding:12px;text-align:center">
            <div style="font-size:0.7rem;color:var(--primary-dark);font-weight:700;margin-bottom:2px">Skor TOPSIS (CC)</div>
            <div style="font-size:1.6rem;font-weight:800;color:var(--primary)"><?= formatAngka($r['CC'],4) ?></div>
            <div class="cc-bar" style="margin:6px auto 0;width:80%">
              <div class="cc-bar-fill" style="width:<?= round($r['CC']*100,1) ?>%"></div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- TABEL RANKING LENGKAP -->
<div class="card fade-up mb-4">
  <div class="card-header">
    <h3>📋 Ranking Lengkap Semua Alternatif</h3>
    <a href="<?= APP_URL ?>/pages/topsis.php" class="btn btn-outline btn-sm">Detail Perhitungan</a>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Rank</th>
            <th>Brand</th>
            <th>Model</th>
            <?php foreach ($kriteria as $k): ?>
              <th title="<?= clean($k['nama']) ?>"><?= clean($k['kode']) ?><br>
                <span style="font-size:0.6rem;font-weight:400">(<?= $k['tipe']==='benefit'?'↑':'↓' ?>)</span>
              </th>
            <?php endforeach; ?>
            <th>D⁺</th>
            <th>D⁻</th>
            <th>CC</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ranking as $r):
            $parts3 = explode(' ', $r['nama'], 2);
          ?>
          <tr <?= $r['rank'] == 1 ? 'style="background:#FFFBEB"' : ($r['rank'] <= 3 ? 'style="background:#F8FAFC"' : '') ?>>
            <td>
              <?php
                if ($r['rank'] == 1) echo '🥇';
                elseif ($r['rank'] == 2) echo '🥈';
                elseif ($r['rank'] == 3) echo '🥉';
                else echo '<strong>'.$r['rank'].'</strong>';
              ?>
            </td>
            <td style="font-weight:700;color:var(--primary);white-space:nowrap"><?= clean($parts3[0]) ?></td>
            <td style="font-size:0.82rem;white-space:nowrap;max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= clean($parts3[1] ?? '') ?></td>
            <?php foreach ($r['data_krit'] as $kode => $val): ?>
              <td><?= formatAngka($val, 1) ?></td>
            <?php endforeach; ?>
            <td style="color:var(--danger)"><?= formatAngka($r['D_plus'], 4) ?></td>
            <td style="color:var(--success)"><?= formatAngka($r['D_minus'], 4) ?></td>
            <td>
              <strong style="color:var(--primary)"><?= formatAngka($r['CC'], 4) ?></strong>
              <div class="cc-bar" style="width:70px;margin-top:3px">
                <div class="cc-bar-fill" style="width:<?= round($r['CC']*100,1) ?>%"></div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- BOBOT AHP YANG DIGUNAKAN -->
<div class="card fade-up mb-4">
  <div class="card-header">
    <h3>⚖️ Bobot Kriteria AHP yang Digunakan</h3>
    <?= badgeKonsisten($bobotDB[0]['cr'] ?? 0) ?>
  </div>
  <div class="card-body">
    <div class="bobot-grid">
      <?php foreach ($kriteria as $idx => $k): ?>
      <div class="bobot-card">
        <div class="kode"><?= clean($k['kode']) ?></div>
        <div class="nama"><?= clean($k['nama']) ?></div>
        <div class="val mt-1"><?= formatAngka($bobotArr[$idx], 4) ?></div>
        <div class="pct">(<?= formatPersen($bobotArr[$idx]) ?>)</div>
        <?= badgeTipe($k['tipe']) ?>
        <div class="progress-bar mt-2">
          <div class="progress-fill" style="width:<?= round($bobotArr[$idx]*100,2) ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="mt-3" style="font-size:0.82rem;color:var(--text-muted)">
      CR = <?= formatAngka($bobotDB[0]['cr'] ?? 0, 4) ?> |
      CI = <?= formatAngka($bobotDB[0]['ci'] ?? 0, 4) ?> |
      λ_max = <?= formatAngka($bobotDB[0]['lambda_max'] ?? 0, 4) ?>
    </div>
  </div>
</div>

<!-- AKSI -->
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:4px" class="fade-up">
  <a href="<?= APP_URL ?>/pages/topsis.php" class="btn btn-outline">📊 Detail Perhitungan</a>
  <a href="<?= APP_URL ?>/pages/ahp.php" class="btn btn-outline">⚖️ Ubah Bobot AHP</a>
  <a href="<?= APP_URL ?>/pages/preferensi.php" class="btn btn-outline">🎯 Ubah Preferensi</a>
  <a href="<?= APP_URL ?>/pages/history.php" class="btn btn-outline">📋 Riwayat</a>
  <button onclick="window.print()" class="btn btn-secondary">🖨️ Cetak Laporan</button>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>