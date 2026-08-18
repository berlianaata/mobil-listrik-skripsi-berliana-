<?php
// ============================================================
// FILE: pages/topsis.php
// FUNGSI: Proses perhitungan TOPSIS lengkap dengan detail setiap langkah
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/TOPSIS.php';
requireLogin();

$pageTitle = 'Perhitungan TOPSIS';
$userId    = $_SESSION['user_id'];
$kriteria  = getKriteria();
$n         = count($kriteria);

// ─── Ambil bobot AHP user ───
$bobotDB = getBobotAHP($userId);
if (empty($bobotDB)) {
    setFlash('warning', 'Anda belum melakukan penilaian AHP. Silakan isi terlebih dahulu.');
    header('Location: ' . APP_URL . '/pages/ahp.php');
    exit;
}

// Susun array bobot sesuai urutan kriteria
$bobotArr = [];
$tipeArr  = [];
$namaKrit = [];
$kolom    = [];
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

// ─── Ambil data kendaraan sesuai preferensi ───
$pref   = getPreferensiUser($userId);
$filter = [
    'segment'    => $pref['filter_segment'] ?? null,
    'drivetrain' => $pref['filter_drive']   ?? null,
    'body_type'  => $pref['filter_body']    ?? null,
    'seats'      => $pref['filter_seats']   ?? null,
];
$limit      = $pref['jumlah_alt'] ?? 20;
$kendaraan  = getKendaraanFiltered($filter, $limit);

if (count($kendaraan) < 2) {
    setFlash('warning', 'Data kendaraan kurang dari 2. Ubah preferensi filter Anda.');
    header('Location: ' . APP_URL . '/pages/preferensi.php');
    exit;
}

// ─── Susun matriks keputusan ───
$dataMatrix = [];
$namaAlt    = [];
$idAlt      = [];

foreach ($kendaraan as $ev) {
    $baris = [];
    foreach ($kolom as $col) {
        $baris[] = (float)($ev[$col] ?? 0);
    }
    $dataMatrix[] = $baris;
    $namaAlt[]    = $ev['brand'] . ' ' . $ev['model'];
    $idAlt[]      = $ev['id'];
}

// ─── Jalankan TOPSIS ───
$topsis = new TOPSIS($dataMatrix, $bobotArr, $tipeArr, $namaAlt, $namaKrit);
$hasil  = $topsis->hitung();
$steps  = $topsis->getDetailSteps();

// ─── Simpan ke history ───
$pref2 = getPreferensiUser($userId);
saveHistory($userId, [
    'nama_sesi'  => 'Perhitungan ' . date('d/m/Y H:i'),
    'filter_json'=> json_encode($filter),
    'bobot_json' => json_encode(array_combine($namaKrit, $bobotArr)),
    'ahp_json'   => json_encode($bobotDB),
    'topsis_json'=> json_encode(array_slice($hasil, 0, 5)), // ringkas
    'ranking_json'=> json_encode(array_slice($hasil['ranking'], 0, 10)),
    'cr'         => $bobotDB[0]['cr'] ?? 0,
    'konsisten'  => $bobotDB[0]['konsisten'] ?? 0,
    'jumlah_alt' => count($kendaraan),
]);

$_SESSION['last_hasil'] = $hasil;

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- STEP NAV -->
<div class="step-nav fade-up mb-4">
  <div class="step-item done"><div class="step-num">✓</div><div class="step-label">Preferensi</div></div>
  <div class="step-item done"><div class="step-num">✓</div><div class="step-label">AHP</div></div>
  <div class="step-item active"><div class="step-num">3</div><div class="step-label">Hitung TOPSIS</div></div>
  <div class="step-item"><div class="step-num">4</div><div class="step-label">Hasil</div></div>
</div>

<!-- INFO SINGKAT -->
<div class="stats-grid fade-up mb-4" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
  <div class="stat-card green">
    <div class="stat-icon green">🚗</div>
    <div class="stat-info"><div class="value"><?= count($kendaraan) ?></div><div class="label">Alternatif EV</div></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon blue">⚖️</div>
    <div class="stat-info"><div class="value"><?= $n ?></div><div class="label">Kriteria</div></div>
  </div>
  <div class="stat-card orange">
    <div class="stat-icon orange">🏆</div>
    <div class="stat-info">
      <div class="value" style="font-size:0.9rem"><?= explode(' ', $hasil['terbaik']['nama'])[0] ?? '-' ?></div>
      <div class="label">Rekomendasi Terbaik</div>
    </div>
  </div>
  <div class="stat-card teal">
    <div class="stat-icon teal">📊</div>
    <div class="stat-info">
      <div class="value"><?= formatAngka($hasil['terbaik']['CC'], 4) ?></div>
      <div class="label">Skor CC Tertinggi</div>
    </div>
  </div>
</div>

<!-- TAB NAVIGASI DETAIL TOPSIS -->
<div class="card fade-up mb-4">
  <div class="card-header">
    <h3>📐 Detail Perhitungan TOPSIS (7 Langkah)</h3>
    <a href="<?= APP_URL ?>/pages/hasil.php" class="btn btn-primary btn-sm">Lihat Hasil → </a>
  </div>
  <div class="card-body">

    <div class="tab-nav mb-3">
      <button class="tab-btn active" onclick="showTab('t1',this)">Langkah 1–2</button>
      <button class="tab-btn" onclick="showTab('t3',this)">Langkah 3–4</button>
      <button class="tab-btn" onclick="showTab('t5',this)">Langkah 5–7</button>
      <button class="tab-btn" onclick="showTab('t-summary',this)">Ringkasan</button>
    </div>

    <!-- ═══════ LANGKAH 1 & 2 ═══════ -->
    <div id="t1" class="tab-pane active">

      <!-- Langkah 1: Matriks Awal -->
      <div class="calc-step mb-3">
        <div class="calc-step-head">
          <div class="calc-step-num">1</div>
          <div class="calc-step-title">Matriks Keputusan Awal (X)</div>
        </div>
        <div class="calc-step-body">
          <div class="formula-box">X = [x_ij] ; i = 1,...,<?= count($kendaraan) ?> (alternatif) ; j = 1,...,<?= $n ?> (kriteria)</div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Alternatif (EV)</th>
                  <?php foreach ($kriteria as $k): ?>
                    <th title="<?= clean($k['nama']) ?>"><?= clean($k['kode']) ?><br>
                      <span style="font-size:0.62rem;font-weight:400">(<?= $k['tipe'] === 'benefit' ? '↑' : '↓' ?>)</span>
                    </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($hasil['matriks_awal'] as $i => $baris): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td style="font-size:0.82rem;font-weight:600;white-space:nowrap;max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= clean($namaAlt[$i]) ?></td>
                  <?php foreach ($baris as $v): ?>
                    <td><?= formatAngka($v, 2) ?></td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Langkah 2: Matriks Normalisasi -->
      <div class="calc-step">
        <div class="calc-step-head">
          <div class="calc-step-num">2</div>
          <div class="calc-step-title">Matriks Normalisasi (R) — Vector Normalization</div>
        </div>
        <div class="calc-step-body">
          <div class="formula-box">r_ij = x_ij / √(Σᵢ x_ij²)

Normalisasi panjang vektor setiap kolom = 1
Tujuan: Menyetarakan skala antar kriteria yang berbeda satuan</div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Alternatif</th>
                  <?php foreach ($kriteria as $k): ?><th><?= clean($k['kode']) ?></th><?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($hasil['matriks_normal'] as $i => $baris): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td style="font-size:0.8rem;white-space:nowrap;max-width:180px;overflow:hidden;text-overflow:ellipsis"><?= clean($namaAlt[$i]) ?></td>
                  <?php foreach ($baris as $v): ?>
                    <td><?= formatAngka($v, 5) ?></td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div><!-- /#t1 -->

    <!-- ═══════ LANGKAH 3 & 4 ═══════ -->
    <div id="t3" class="tab-pane">

      <!-- Langkah 3: Matriks Terbobot -->
      <div class="calc-step mb-3">
        <div class="calc-step-head">
          <div class="calc-step-num">3</div>
          <div class="calc-step-title">Matriks Normalisasi Terbobot (V)</div>
        </div>
        <div class="calc-step-body">
          <div class="formula-box">v_ij = w_j × r_ij

Bobot dari AHP:
<?php foreach ($kriteria as $idx => $k): echo $k['kode'].' = '.formatAngka($bobotArr[$idx],4).'  '; endforeach; ?></div>

          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
            <?php foreach ($kriteria as $idx => $k): ?>
            <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;padding:7px 12px;font-size:0.78rem">
              <strong><?= clean($k['kode']) ?></strong>: w = <?= formatAngka($bobotArr[$idx],4) ?>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Alternatif</th>
                  <?php foreach ($kriteria as $k): ?><th><?= clean($k['kode']) ?></th><?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($hasil['matriks_bobot'] as $i => $baris): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td style="font-size:0.8rem;white-space:nowrap;max-width:180px;overflow:hidden;text-overflow:ellipsis"><?= clean($namaAlt[$i]) ?></td>
                  <?php foreach ($baris as $v): ?>
                    <td><?= formatAngka($v, 5) ?></td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Langkah 4: Solusi Ideal -->
      <div class="calc-step">
        <div class="calc-step-head">
          <div class="calc-step-num">4</div>
          <div class="calc-step-title">Solusi Ideal Positif (A⁺) dan Negatif (A⁻)</div>
        </div>
        <div class="calc-step-body">
          <div class="formula-box">Kriteria Benefit (↑): A⁺_j = max(v_ij) , A⁻_j = min(v_ij)
Kriteria Cost   (↓): A⁺_j = min(v_ij) , A⁻_j = max(v_ij)

A⁺ = kondisi terbaik yang mungkin dicapai
A⁻ = kondisi terburuk yang mungkin terjadi</div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Solusi Ideal</th>
                  <?php foreach ($kriteria as $k): ?>
                    <th><?= clean($k['kode']) ?><br>
                      <span style="font-size:0.65rem">(<?= $k['tipe'] ?>)</span>
                    </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><strong style="color:var(--success)">A⁺ (Positif)</strong></td>
                  <?php foreach ($hasil['A_plus'] as $v): ?>
                    <td style="background:#ECFDF5;font-weight:600;color:var(--success)"><?= formatAngka($v, 5) ?></td>
                  <?php endforeach; ?>
                </tr>
                <tr>
                  <td><strong style="color:var(--danger)">A⁻ (Negatif)</strong></td>
                  <?php foreach ($hasil['A_minus'] as $v): ?>
                    <td style="background:#FEF2F2;font-weight:600;color:var(--danger)"><?= formatAngka($v, 5) ?></td>
                  <?php endforeach; ?>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div><!-- /#t3 -->

    <!-- ═══════ LANGKAH 5–7 ═══════ -->
    <div id="t5" class="tab-pane">

      <!-- Langkah 5 & 6: D+, D-, CC -->
      <div class="calc-step mb-3">
        <div class="calc-step-head">
          <div class="calc-step-num">5–6</div>
          <div class="calc-step-title">Jarak Euclidean &amp; Nilai Preferensi (CC)</div>
        </div>
        <div class="calc-step-body">
          <div class="formula-box">Langkah 5 — Jarak Euclidean:
D⁺_i = √Σⱼ(v_ij − A⁺_j)²   [Jarak ke solusi ideal positif]
D⁻_i = √Σⱼ(v_ij − A⁻_j)²   [Jarak ke solusi ideal negatif]

Langkah 6 — Nilai Preferensi Relatif:
CC_i = D⁻_i / (D⁺_i + D⁻_i)
CC ∈ [0, 1] — Semakin besar → semakin mendekati solusi ideal positif</div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Alternatif (EV)</th>
                  <th>D⁺</th>
                  <th>D⁻</th>
                  <th>CC = D⁻/(D⁺+D⁻)</th>
                  <th>Preferensi</th>
                </tr>
              </thead>
              <tbody>
                <?php for ($i = 0; $i < count($namaAlt); $i++): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td style="font-size:0.82rem;white-space:nowrap;max-width:200px;overflow:hidden;text-overflow:ellipsis">
                    <strong><?= clean($namaAlt[$i]) ?></strong>
                  </td>
                  <td><?= formatAngka($hasil['D_plus'][$i], 6) ?></td>
                  <td><?= formatAngka($hasil['D_minus'][$i], 6) ?></td>
                  <td>
                    <strong style="color:var(--primary)"><?= formatAngka($hasil['CC'][$i], 6) ?></strong>
                  </td>
                  <td>
                    <div class="cc-bar" style="width:100px">
                      <div class="cc-bar-fill" style="width:<?= round($hasil['CC'][$i]*100, 1) ?>%"></div>
                    </div>
                    <span style="font-size:0.72rem;color:var(--text-muted)"><?= round($hasil['CC'][$i]*100, 2) ?>%</span>
                  </td>
                </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Langkah 7: Ranking -->
      <div class="calc-step">
        <div class="calc-step-head">
          <div class="calc-step-num">7</div>
          <div class="calc-step-title">Perangkingan Alternatif (Descending CC)</div>
        </div>
        <div class="calc-step-body">
          <div class="formula-box">Urutkan CC_i secara menurun (descending)
Alternatif dengan CC terbesar = rekomendasi terbaik</div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Rank</th>
                  <th>Brand</th>
                  <th>Model</th>
                  <?php foreach ($kriteria as $k): ?><th><?= clean($k['kode']) ?></th><?php endforeach; ?>
                  <th>D⁺</th>
                  <th>D⁻</th>
                  <th>CC</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($hasil['ranking'] as $r): ?>
                <tr <?= $r['rank'] <= 3 ? 'style="background:'.($r['rank']==1?'#FFFBEB':($r['rank']==2?'#F8FAFC':'#FFF8F0')).'"' : '' ?>>
                  <td>
                    <?php
                      if ($r['rank'] == 1) echo '<span style="font-size:1.2rem">🥇</span>';
                      elseif ($r['rank'] == 2) echo '<span style="font-size:1.2rem">🥈</span>';
                      elseif ($r['rank'] == 3) echo '<span style="font-size:1.2rem">🥉</span>';
                      else echo '<strong>' . $r['rank'] . '</strong>';
                    ?>
                  </td>
                  <?php
                    $parts = explode(' ', $r['nama'], 2);
                    $brand = $parts[0] ?? $r['nama'];
                    $model = $parts[1] ?? '';
                  ?>
                  <td style="font-weight:700;color:var(--primary)"><?= clean($brand) ?></td>
                  <td style="font-size:0.82rem"><?= clean($model) ?></td>
                  <?php foreach ($r['data_krit'] as $val): ?>
                    <td><?= formatAngka($val, 1) ?></td>
                  <?php endforeach; ?>
                  <td><?= formatAngka($r['D_plus'], 5) ?></td>
                  <td><?= formatAngka($r['D_minus'], 5) ?></td>
                  <td><strong style="color:var(--primary)"><?= formatAngka($r['CC'], 4) ?></strong></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div><!-- /#t5 -->

    <!-- ═══════ RINGKASAN ═══════ -->
    <div id="t-summary" class="tab-pane">
      <div class="info-box mb-3">
        <strong>📋 Ringkasan Perhitungan TOPSIS</strong><br>
        Alternatif: <?= count($kendaraan) ?> EV |
        Kriteria: <?= $n ?> |
        Metode Normalisasi: Vector Normalization |
        Solusi Ideal: Berdasarkan tipe kriteria (benefit/cost)
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Rank</th>
              <th>Kendaraan EV</th>
              <th>D⁺</th>
              <th>D⁻</th>
              <th>CC (Skor)</th>
              <th>Persentase</th>
              <th>Visualisasi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($hasil['ranking'] as $r): ?>
            <tr>
              <td><strong><?= $r['rank'] ?></strong></td>
              <td style="font-weight:600"><?= clean($r['nama']) ?></td>
              <td><?= formatAngka($r['D_plus'], 5) ?></td>
              <td><?= formatAngka($r['D_minus'], 5) ?></td>
              <td><strong style="color:var(--primary)"><?= formatAngka($r['CC'], 4) ?></strong></td>
              <td><?= round($r['CC']*100, 2) ?>%</td>
              <td>
                <div class="cc-bar" style="width:120px">
                  <div class="cc-bar-fill" style="width:<?= round($r['CC']*100,1) ?>%"></div>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div style="margin-top:24px;text-align:right">
        <a href="<?= APP_URL ?>/pages/hasil.php" class="btn btn-primary btn-lg">
          🏆 Lihat Rekomendasi Lengkap →
        </a>
      </div>
    </div>
  </div><!-- /.card-body -->
</div><!-- /.card -->

<script>
function showTab(id, btn) {
  document.querySelectorAll('.tab-pane').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById(id)?.classList.add('active');
  btn.classList.add('active');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>