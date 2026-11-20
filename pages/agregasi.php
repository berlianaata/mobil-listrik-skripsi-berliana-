<?php
// ============================================================
// FILE: pages/agregasi.php
// FUNGSI: Agregasi bobot pakar (khusus Admin), menurut Teknomo (2006):
//   g_c = (w_c(1) x ... x w_c(K))^(1/K) hanya untuk responden CR <= 0,1,
//   lalu w_c = g_c / jumlah g. Tidak ada matriks gabungan dan tidak ada CR gabungan.
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/pakar.php';
requireAdmin();

$pageTitle = 'Agregasi Bobot Pakar';
$p     = pakarHitung(false);
$kr    = getKriteria();
$kode  = $p['kode'];
$resp  = $p['responden'];
$f6    = fn($x) => number_format((float)$x, 6, ',', '');
$ikut  = array_values(array_filter($resp, fn($r) => $r['konsisten']));
$tidak = array_values(array_filter($resp, fn($r) => !$r['konsisten']));

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="info-box fade-up">
  <strong>Agregasi bobot pakar (rata-rata geometrik).</strong>
  Bobot tiap responden dihitung lebih dulu di <a href="<?= APP_URL ?>/pages/ahp_pakar.php">Perhitungan AHP Pakar</a>. Hanya responden
  dengan <strong>CR ≤ 0,1</strong> yang digabung: <em>g<sub>c</sub></em> = (<em>w<sub>c</sub></em>(1) × … × <em>w<sub>c</sub></em>(K))<sup>1/K</sup>,
  lalu dinormalisasi <em>w<sub>c</sub></em> = <em>g<sub>c</sub></em> / Σ<em>g</em>. Tidak ada matriks gabungan dan tidak ada CR gabungan;
  konsistensi diperiksa per responden.
</div>

<?php if (!$p['bobot']): ?>
<div class="card fade-up"><div class="card-body">
  <p>Belum ada responden yang konsisten (CR ≤ 0,1), sehingga bobot pakar belum dapat dihitung.
  Tambahkan atau perbaiki responden di <a href="<?= APP_URL ?>/pages/responden.php">Kelola Responden Pakar</a>.</p>
</div></div>
<?php else: ?>

<div class="card fade-up mb-4">
  <div class="card-header"><h3>Bobot Pakar Akhir</h3><span class="badge badge-blue">K = <?= (int)$p['K'] ?> dari <?= count($resp) ?> responden</span></div>
  <div class="card-body">
    <div class="table-wrap"><table>
      <thead><tr><th>Baris</th><?php foreach ($kr as $k): ?><th><?= clean($k['kode']) ?><br><span class="text-muted" style="font-weight:400"><?= clean($k['nama']) ?></span></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($ikut as $r): ?>
        <tr><td><?= clean($r['nama']) ?> <span class="text-muted">(CR <?= $f6($r['CR']) ?>)</span></td><?php foreach ($r['bobot'] as $w): ?><td><?= $f6($w) ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
        <tr><td><strong>Rata-rata geometrik (g)</strong></td><?php foreach ($p['geo'] as $g): ?><td><?= $f6($g) ?></td><?php endforeach; ?></tr>
        <tr><td><strong>Bobot pakar (g / Σg)</strong></td><?php foreach ($p['bobot'] as $w): ?><td><strong style="color:var(--primary)"><?= $f6($w) ?></strong> <span class="text-muted">(<?= formatPersen($w) ?>)</span></td><?php endforeach; ?></tr>
      </tbody>
    </table></div>
    <p class="text-muted" style="font-size:.8rem;margin-top:12px">Σ bobot pakar = <?= $f6(array_sum($p['bobot'])) ?>. Cara cek di Excel: untuk tiap kriteria pakai <code>=GEOMEAN(...)</code> atas bobot responden konsisten, lalu bagi dengan jumlahnya.</p>
  </div>
</div>

<?php if ($tidak): ?>
<div class="alert alert-warning" role="alert">
  Tidak ikut agregasi (CR &gt; 0,1):
  <?= implode(', ', array_map(fn($r) => clean($r['nama']) . ' (CR ' . $f6($r['CR']) . ')', $tidak)) ?>.
</div>
<?php endif; ?>

<div class="card fade-up mb-4"><div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap">
  <a class="btn btn-primary" href="<?= APP_URL ?>/pages/pakar.php">Lanjut ke Hasil TOPSIS Pakar</a>
  <a class="btn btn-outline" href="<?= APP_URL ?>/pages/hasil.php?sumber=pakar">Lihat Rekomendasi Pakar (tampilan pengguna)</a>
</div></div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
