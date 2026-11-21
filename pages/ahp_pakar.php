<?php
// ============================================================
// FILE: pages/ahp_pakar.php
// FUNGSI: Perhitungan AHP pakar per responden (khusus Admin).
// Menampilkan matriks perbandingan berpasangan, normalisasi, bobot prioritas,
// λmaks, CI, RI, dan CR setiap responden. Hanya menampilkan hasil hitungan
// pakarHitung() (tidak ada rumus baru, tidak ada pembulatan pada perhitungan).
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pakar.php';
requireAdmin();

$pageTitle = 'Perhitungan AHP Pakar';
$p     = pakarHitung(false);
$kode  = $p['kode'];
$n     = count($kode);
$resp  = $p['responden'];
$f6    = fn($x) => number_format((float)$x, 6, ',', '');

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="info-box fade-up">
  <strong>Perhitungan AHP per responden.</strong>
  Setiap responden dihitung sendiri: matriks perbandingan berpasangan → jumlah kolom → normalisasi → bobot prioritas (rata-rata baris) →
  λ<sub>maks</sub> = Σ(jumlah kolom<sub>j</sub> × bobot<sub>j</sub>) → CI = (λ<sub>maks</sub> − n)/(n − 1) → CR = CI/RI.
  Responden dengan <strong>CR ≤ 0,1</strong> dinyatakan konsisten dan ikut ke tahap
  <a href="<?= APP_URL ?>/pages/agregasi.php">Agregasi Bobot Pakar</a>. Data responden diubah di
  <a href="<?= APP_URL ?>/pages/responden.php">Kelola Responden Pakar</a>.
</div>

<?php if (!$resp): ?>
<div class="card fade-up"><div class="card-body">
  <p>Belum ada responden pakar. Tambahkan di <a href="<?= APP_URL ?>/pages/responden.php">Kelola Responden Pakar</a>.</p>
</div></div>
<?php else: ?>

<div class="card fade-up mb-4">
  <div class="card-header"><h3>Ringkasan Konsistensi</h3><span class="badge badge-blue"><?= count($resp) ?> responden</span></div>
  <div class="card-body"><div class="table-wrap"><table>
    <thead><tr><th>Responden</th><?php foreach ($kode as $c): ?><th>w <?= clean($c) ?></th><?php endforeach; ?><th>λ<sub>maks</sub></th><th>CI</th><th>CR</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($resp as $r): ?>
      <tr>
        <td><strong><?= clean($r['nama']) ?></strong></td>
        <?php foreach ($r['bobot'] as $w): ?><td><?= $f6($w) ?></td><?php endforeach; ?>
        <td><?= $f6($r['lambda_max']) ?></td><td><?= $f6($r['CI']) ?></td><td><?= $f6($r['CR']) ?></td>
        <td><?= $r['konsisten'] ? '<span class="badge badge-green">Konsisten</span>' : '<span class="badge badge-red">Tidak konsisten</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="text-muted" style="font-size:.8rem;margin-top:12px">RI untuk n = <?= $n ?> adalah <?= $f6($resp[0]['RI']) ?>. Batas konsistensi CR ≤ 0,1.</p>
  </div>
</div>

<?php foreach ($resp as $r):
    $M = pakarMatriks($r['nilai'], $n);
    $jk = $r['jumlah_kolom']; ?>
<div class="card fade-up mb-4">
  <div class="card-header">
    <h3><?= clean($r['nama']) ?><?= $r['jabatan'] ? ' <span class="text-muted" style="font-weight:400">· ' . clean($r['jabatan']) . '</span>' : '' ?></h3>
    <?= $r['konsisten'] ? '<span class="badge badge-green">CR ' . $f6($r['CR']) . ' · konsisten</span>' : '<span class="badge badge-red">CR ' . $f6($r['CR']) . ' · tidak ikut agregasi</span>' ?>
  </div>
  <div class="card-body">
    <h4 style="font-size:.9rem;margin-bottom:8px">1. Matriks perbandingan berpasangan</h4>
    <div class="table-wrap"><table>
      <thead><tr><th></th><?php foreach ($kode as $c): ?><th><?= clean($c) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php for ($i = 0; $i < $n; $i++): ?>
        <tr><td><strong><?= clean($kode[$i]) ?></strong></td><?php for ($j = 0; $j < $n; $j++): ?><td><?= $f6($M[$i][$j]) ?></td><?php endfor; ?></tr>
      <?php endfor; ?>
        <tr><td><strong>Jumlah kolom</strong></td><?php foreach ($jk as $s): ?><td><strong><?= $f6($s) ?></strong></td><?php endforeach; ?></tr>
      </tbody>
    </table></div>

    <h4 style="font-size:.9rem;margin:18px 0 8px">2. Normalisasi matriks dan bobot prioritas</h4>
    <div class="table-wrap"><table>
      <thead><tr><th></th><?php foreach ($kode as $c): ?><th><?= clean($c) ?></th><?php endforeach; ?><th>Bobot (rata-rata baris)</th></tr></thead>
      <tbody>
      <?php for ($i = 0; $i < $n; $i++): ?>
        <tr><td><strong><?= clean($kode[$i]) ?></strong></td>
          <?php for ($j = 0; $j < $n; $j++): ?><td><?= $f6($M[$i][$j] / $jk[$j]) ?></td><?php endfor; ?>
          <td><strong style="color:var(--primary)"><?= $f6($r['bobot'][$i]) ?></strong></td></tr>
      <?php endfor; ?>
      </tbody>
    </table></div>

    <h4 style="font-size:.9rem;margin:18px 0 8px">3. Uji konsistensi</h4>
    <div class="table-wrap"><table>
      <thead><tr><th>λ<sub>maks</sub></th><th>CI</th><th>RI</th><th>CR</th><th>Keputusan</th></tr></thead>
      <tbody><tr><td><?= $f6($r['lambda_max']) ?></td><td><?= $f6($r['CI']) ?></td><td><?= $f6($r['RI']) ?></td><td><?= $f6($r['CR']) ?></td>
        <td><?= $r['konsisten'] ? 'Konsisten (CR ≤ 0,1), ikut agregasi' : 'Tidak konsisten (CR &gt; 0,1), tidak ikut agregasi' ?></td></tr></tbody>
    </table></div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
