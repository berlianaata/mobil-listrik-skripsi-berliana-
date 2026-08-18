<?php
// ============================================================
// FILE: pages/ahp.php
// FUNGSI: Input matriks perbandingan berpasangan AHP dan perhitungan lengkap
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/AHP.php';
requireLogin();

$pageTitle = 'Penilaian AHP';
$userId    = $_SESSION['user_id'];
$kriteria  = getKriteria();
$n         = count($kriteria);
$hasil     = null;
$error     = '';

// ─── Ambil matriks tersimpan ───
$matriksSimpan = [];
$dbMatrix      = getMatriksAHP($userId);
foreach ($dbMatrix as $row) {
    $matriksSimpan[$row['kriteria_i']][$row['kriteria_j']] = (float)$row['nilai'];
}

// ─── PROSES SUBMIT ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ahp = new AHP(array_column($kriteria, 'kode'));

    // Bangun matriks dari POST
    $matInput = [];
    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j < $n; $j++) {
            $ki = $kriteria[$i]['kode'];
            $kj = $kriteria[$j]['kode'];
            if ($i === $j) {
                $matInput[$i][$j] = 1.0;
            } elseif ($j > $i) {
                $val = (float)($_POST['m'][$i][$j] ?? 1);
                $val = max(1/9, min(9, $val));
                $matInput[$i][$j] = $val;
                $matInput[$j][$i] = round(1 / $val, 6);

                // Simpan ke DB
                saveMatriksAHP($userId, $ki, $kj, $val);
                saveMatriksAHP($userId, $kj, $ki, round(1/$val, 6));
                $matriksSimpan[$ki][$kj] = $val;
                $matriksSimpan[$kj][$ki] = round(1/$val, 6);
            }
        }
    }

    $ahp->setMatriks($matInput);
    $hasil = $ahp->hitung();

    // Simpan bobot ke DB
    $bobotArr  = $hasil['bobot'];
    $lambdaMax = $hasil['lambda_max'];
    $ci        = $hasil['CI'];
    $cr        = $hasil['CR'];
    $konsisten = $hasil['konsisten'] ? 1 : 0;

    foreach ($kriteria as $idx => $k) {
        saveBobotAHP($userId, $k['id'], $bobotArr[$idx], $lambdaMax, $ci, $cr, $konsisten);
    }

    if ($hasil['konsisten']) {
        setFlash('success', 'Perhitungan AHP berhasil! CR = ' . round($cr, 4) . ' (Konsisten). Lanjutkan ke TOPSIS.');
    } else {
        setFlash('warning', 'CR = ' . round($cr, 4) . ' > 0.1. Matriks belum konsisten. Silakan revisi nilai perbandingan.');
    }
}

// ─── Ambil bobot tersimpan ───
$bobotSimpan = getBobotAHP($userId);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- STEP NAV -->
<div class="step-nav fade-up mb-4">
  <div class="step-item done"><div class="step-num">✓</div><div class="step-label">Preferensi</div></div>
  <div class="step-item active"><div class="step-num">2</div><div class="step-label">Penilaian AHP</div></div>
  <div class="step-item"><div class="step-num">3</div><div class="step-label">Hitung TOPSIS</div></div>
  <div class="step-item"><div class="step-num">4</div><div class="step-label">Hasil</div></div>
</div>

<!-- INFO BOX -->
<div class="info-box fade-up">
  <strong>ℹ️ Cara Mengisi Matriks AHP:</strong>
  Bandingkan setiap pasangan kriteria menggunakan <strong>skala Saaty 1–9</strong>.
  Nilai > 1 berarti kriteria baris lebih penting dari kriteria kolom.
  Nilai 1/x diisi otomatis. Pastikan CR ≤ 0.1 agar hasil konsisten.
</div>

<!-- SKALA SAATY -->
<div class="card fade-up mb-4">
  <div class="card-header"><h3>📏 Referensi Skala Perbandingan Saaty</h3></div>
  <div class="card-body">
    <div class="saaty-ref">
      <div class="saaty-item"><div class="num">1</div><div class="desc">Sama Penting</div></div>
      <div class="saaty-item"><div class="num">2</div><div class="desc">Di antara 1&amp;3</div></div>
      <div class="saaty-item"><div class="num">3</div><div class="desc">Sedikit Lebih Penting</div></div>
      <div class="saaty-item"><div class="num">4</div><div class="desc">Di antara 3&amp;5</div></div>
      <div class="saaty-item"><div class="num">5</div><div class="desc">Lebih Penting</div></div>
      <div class="saaty-item"><div class="num">6</div><div class="desc">Di antara 5&amp;7</div></div>
      <div class="saaty-item"><div class="num">7</div><div class="desc">Sangat Lebih Penting</div></div>
      <div class="saaty-item"><div class="num">8</div><div class="desc">Di antara 7&amp;9</div></div>
      <div class="saaty-item"><div class="num">9</div><div class="desc">Mutlak Lebih Penting</div></div>
    </div>
    <p style="font-size:0.78rem;color:var(--text-muted);margin-top:10px">
      Pecahan (misal 1/3) berarti kriteria kolom <em>lebih penting</em> dari kriteria baris. Isi hanya <strong>nilai ≥ 1</strong>; nilai kebalikan diisi otomatis.
    </p>
  </div>
</div>

<!-- FORM MATRIKS AHP -->
<div class="card fade-up mb-4">
  <div class="card-header">
    <h3>⚖️ Matriks Perbandingan Berpasangan</h3>
    <span class="badge badge-blue">Ordo <?= $n ?>×<?= $n ?></span>
  </div>
  <div class="card-body">
    <form method="POST" action="" id="formAHP">
      <div class="table-wrap">
        <table class="matrix-table">
          <thead>
            <tr>
              <th style="min-width:140px">Kriteria</th>
              <?php foreach ($kriteria as $k): ?>
                <th title="<?= clean($k['nama']) ?>">
                  <?= clean($k['kode']) ?><br>
                  <span style="font-size:0.65rem;font-weight:400;color:var(--text-muted)">
                    <?= mb_substr(clean($k['nama']), 0, 10) ?>…
                  </span>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($kriteria as $i => $ki): ?>
            <tr>
              <td style="font-size:0.82rem">
                <strong><?= clean($ki['kode']) ?></strong><br>
                <span style="color:var(--text-muted);font-size:0.72rem"><?= clean($ki['nama']) ?></span>
              </td>
              <?php foreach ($kriteria as $j => $kj): ?>
                <?php
                  $savedVal = $matriksSimpan[$ki['kode']][$kj['kode']] ?? null;
                ?>
                <td>
                  <?php if ($i === $j): ?>
                    <div class="cell-diagonal">1</div>
                    <input type="hidden" name="m[<?=$i?>][<?=$j?>]" value="1">
                  <?php elseif ($j > $i): ?>
                    <input type="number" class="matrix-input"
                           name="m[<?=$i?>][<?=$j?>]"
                           id="m_<?=$i?>_<?=$j?>"
                           min="0.111" max="9" step="0.001"
                           value="<?= $savedVal ? formatAngka($savedVal,3) : '1.000' ?>"
                           title="<?= clean($ki['kode']) ?> vs <?= clean($kj['kode']) ?>"
                           onchange="updateRecipr(<?=$i?>,<?=$j?>)">
                  <?php else: ?>
                    <div class="cell-lower" id="r_<?=$i?>_<?=$j?>">
                      <?= ($savedVal !== null && $savedVal != 0)
                            ? formatAngka($savedVal, 3)
                            : '1/x' ?>
                    </div>
                    <input type="hidden" name="m[<?=$i?>][<?=$j?>]"
                           id="h_<?=$i?>_<?=$j?>"
                           value="<?= $savedVal ? formatAngka($savedVal,6) : '1' ?>">
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div style="display:flex;gap:10px;margin-top:22px;flex-wrap:wrap">
        <button type="submit" class="btn btn-primary btn-lg">
          ⚖️ Hitung Bobot AHP
        </button>
        <button type="button" class="btn btn-outline" onclick="resetMatrix()">
          🔄 Reset Matriks
        </button>
        <button type="button" class="btn btn-outline" onclick="isiContoh()">
          📝 Isi Contoh Nilai
        </button>
        <?php if (!empty($bobotSimpan)): ?>
          <a href="<?= APP_URL ?>/pages/topsis.php" class="btn btn-secondary">
            Lanjut ke TOPSIS →
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- HASIL AHP (setelah submit) -->
<?php if ($hasil): ?>
<div class="card fade-up mb-4" id="hasilAHP">
  <div class="card-header">
    <h3>📊 Hasil Perhitungan AHP</h3>
    <?= badgeKonsisten($hasil['CR']) ?>
  </div>
  <div class="card-body">

    <!-- Uji Konsistensi Box -->
    <div class="cr-box <?= $hasil['konsisten'] ? 'ok' : 'bad' ?>">
      <div class="cr-icon"><?= $hasil['konsisten'] ? '✅' : '❌' ?></div>
      <div class="cr-text">
        <div class="cr-title"><?= $hasil['interpretasi'] ?></div>
        <div class="cr-detail">
          λ_max = <strong><?= $hasil['lambda_max'] ?></strong> |
          CI = <strong><?= $hasil['CI'] ?></strong> |
          RI = <strong><?= $hasil['RI'] ?></strong> |
          CR = <strong><?= $hasil['CR'] ?></strong>
          <?= $hasil['konsisten'] ? '— Lanjutkan ke TOPSIS' : '— Revisi matriks perbandingan' ?>
        </div>
      </div>
    </div>

    <!-- Tabulasi langkah -->
    <div class="tab-nav mb-3">
      <button class="tab-btn active" onclick="showTab('tab-bobot',this)">Bobot Prioritas</button>
      <button class="tab-btn" onclick="showTab('tab-normal',this)">Matriks Normalisasi</button>
      <button class="tab-btn" onclick="showTab('tab-steps',this)">Detail Langkah</button>
    </div>

    <!-- Tab: Bobot -->
    <div id="tab-bobot" class="tab-pane active">
      <div class="bobot-grid">
        <?php foreach ($kriteria as $idx => $k): ?>
        <div class="bobot-card">
          <div class="kode"><?= clean($k['kode']) ?></div>
          <div class="nama"><?= clean($k['nama']) ?></div>
          <div style="display:flex;align-items:flex-end;gap:8px;margin-top:6px">
            <div class="val"><?= formatAngka($hasil['bobot'][$idx], 4) ?></div>
            <div class="pct">(<?= formatPersen($hasil['bobot'][$idx]) ?>)</div>
            <?= badgeTipe($k['tipe']) ?>
          </div>
          <div class="progress-bar mt-2">
            <div class="progress-fill" style="width:<?= round($hasil['bobot'][$idx]*100, 2) ?>%"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Tab: Matriks Normalisasi -->
    <div id="tab-normal" class="tab-pane">
      <p class="text-sm text-muted mb-3">r_ij = a_ij / Σ(kolom_j)</p>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Kriteria</th>
              <?php foreach ($kriteria as $k): ?><th><?= clean($k['kode']) ?></th><?php endforeach; ?>
              <th>Bobot (w_i)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($hasil['matriks_normal'] as $i => $baris): ?>
            <tr>
              <td><strong><?= clean($kriteria[$i]['kode']) ?></strong></td>
              <?php foreach ($baris as $val): ?>
                <td><?= formatAngka($val, 4) ?></td>
              <?php endforeach; ?>
              <td><strong style="color:var(--primary)"><?= formatAngka($hasil['bobot'][$i], 4) ?></strong></td>
            </tr>
            <?php endforeach; ?>
            <!-- Jumlah kolom -->
            <tr style="background:#F8FAFC">
              <td><strong>Jumlah Kolom</strong></td>
              <?php foreach ($hasil['jumlah_kolom'] as $jk): ?>
                <td><strong><?= formatAngka($jk, 4) ?></strong></td>
              <?php endforeach; ?>
              <td><strong style="color:var(--success)"><?= formatAngka(array_sum($hasil['bobot']),4) ?></strong></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tab: Detail Langkah -->
    <div id="tab-steps" class="tab-pane">
      <div class="calc-step">
        <div class="calc-step-head"><div class="calc-step-num">1</div><div class="calc-step-title">Matriks Perbandingan Berpasangan (A)</div></div>
        <div class="calc-step-body">
          <div class="formula-box">a_ij = tingkat kepentingan kriteria-i terhadap kriteria-j (Skala Saaty 1-9)
Diagonal: a_ii = 1 | Simetri: a_ji = 1/a_ij</div>
          <div class="table-wrap"><table>
            <thead><tr><th>Kriteria</th><?php foreach($kriteria as $k): ?><th><?=clean($k['kode'])?></th><?php endforeach; ?></tr></thead>
            <tbody>
              <?php foreach ($hasil['matriks'] as $i => $baris): ?>
              <tr><td><strong><?=clean($kriteria[$i]['kode'])?></strong></td>
              <?php foreach($baris as $j=>$v): ?>
                <td <?=$i===$j?'style="background:rgba(0,200,150,0.1);font-weight:800"':''?>><?=formatAngka($v,3)?></td>
              <?php endforeach; ?></tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        </div>
      </div>
      <div class="calc-step">
        <div class="calc-step-head"><div class="calc-step-num">2</div><div class="calc-step-title">Hitung λ_max, CI, CR</div></div>
        <div class="calc-step-body">
          <div class="formula-box">λ_max = rata-rata dari (AW / w)_i
CI    = (λ_max − n) / (n − 1)  =  (<?=$hasil['lambda_max']?> − <?=$n?>) / (<?=$n?> − 1)  =  <?=$hasil['CI']?>
RI    = <?=$hasil['RI']?>  (Tabel Random Index Saaty, n=<?=$n?>)
CR    = CI / RI  =  <?=$hasil['CI']?> / <?=$hasil['RI']?>  =  <?=$hasil['CR']?>

<?= $hasil['konsisten'] ? '✅ CR = '.$hasil['CR'].' ≤ 0.1  →  Matriks KONSISTEN' : '❌ CR = '.$hasil['CR'].' > 0.1  →  Matriks TIDAK KONSISTEN' ?></div>
        </div>
      </div>
    </div>

    <?php if ($hasil['konsisten']): ?>
    <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border);text-align:right">
      <a href="<?= APP_URL ?>/pages/topsis.php" class="btn btn-primary btn-lg">
        Lanjut ke Perhitungan TOPSIS →
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- BOBOT TERSIMPAN (jika sudah ada) -->
<?php if (!empty($bobotSimpan) && !$hasil): ?>
<div class="card fade-up">
  <div class="card-header">
    <h3>💾 Bobot AHP Tersimpan</h3>
    <?= badgeKonsisten($bobotSimpan[0]['cr']) ?>
  </div>
  <div class="card-body">
    <div class="bobot-grid mb-4">
      <?php foreach ($bobotSimpan as $b): ?>
      <div class="bobot-card">
        <div class="kode"><?= clean($b['kode']) ?></div>
        <div class="nama"><?= clean($b['nama']) ?></div>
        <div style="display:flex;align-items:flex-end;gap:8px;margin-top:6px">
          <div class="val"><?= formatAngka($b['bobot'], 4) ?></div>
          <div class="pct">(<?= formatPersen($b['bobot']) ?>)</div>
          <?= badgeTipe($b['tipe']) ?>
        </div>
        <div class="progress-bar mt-2">
          <div class="progress-fill" style="width:<?= round($b['bobot']*100, 2) ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="text-align:right">
      <a href="<?= APP_URL ?>/pages/topsis.php" class="btn btn-primary">
        📊 Lanjut ke TOPSIS →
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// ─── Update sel resiprokal ───
function updateRecipr(i, j) {
  const inp = document.getElementById('m_' + i + '_' + j);
  const val = parseFloat(inp.value);
  if (!val || val <= 0) return;
  const recip = 1 / val;

  // Update tampilan sel bawah
  const display = document.getElementById('r_' + j + '_' + i);
  const hidden  = document.getElementById('h_' + j + '_' + i);
  if (display) display.textContent = recip.toFixed(3);
  if (hidden)  hidden.value = recip.toFixed(6);
}

// Inisialisasi semua resiprokal saat load
document.querySelectorAll('.matrix-input').forEach(inp => {
  inp.addEventListener('change', function() {
    const [, i, j] = this.id.split('_').map(Number);
    updateRecipr(i, j);
  });
  inp.dispatchEvent(new Event('change'));
});

// Reset matriks
function resetMatrix() {
  if (!confirm('Reset semua nilai matriks menjadi 1?')) return;
  document.querySelectorAll('.matrix-input').forEach(inp => {
    inp.value = '1.000';
    inp.dispatchEvent(new Event('change'));
  });
}

// Isi contoh nilai (konsisten, CR ≈ 0.036)
function isiContoh() {
  // Contoh matriks 5×5 yang konsisten
  const contoh = {
    '0_1': 2, '0_2': 3, '0_3': 4, '0_4': 5,
    '1_2': 2, '1_3': 3, '1_4': 4,
    '2_3': 2, '2_4': 3,
    '3_4': 2
  };
  Object.entries(contoh).forEach(([key, val]) => {
    const inp = document.getElementById('m_' + key);
    if (inp) {
      inp.value = val;
      inp.dispatchEvent(new Event('change'));
    }
  });
}

// Tab handling
function showTab(tabId, btn) {
  document.querySelectorAll('.tab-pane').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById(tabId)?.classList.add('active');
  btn.classList.add('active');
}

// Scroll ke hasil
<?php if ($hasil): ?>
document.getElementById('hasilAHP')?.scrollIntoView({behavior:'smooth', block:'start'});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
