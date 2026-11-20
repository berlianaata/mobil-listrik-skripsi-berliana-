<?php
// ============================================================
// FILE: pages/pakar.php  (khusus Admin)
// FUNGSI: Hasil AHP dan TOPSIS jalur pakar beserta seluruh tahapannya,
//         penyimpanan versi hasil (waktu + riwayat), dan melihat versi lama.
// TOPSIS pakar dihitung pada seluruh alternatif aktif tanpa filter (ranking umum).
// ============================================================
require_once __DIR__ . '/../includes/pakar.php';
requireAdmin();
pakarPastikanTabel();

$pageTitle = 'Hasil TOPSIS Pakar';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['aksi'] ?? '') === 'simpan') {
        $v = pakarSimpan(trim(substr($_POST['catatan'] ?? '', 0, 200)));
        if ($v) setFlash('success', "Hasil pakar disimpan sebagai versi $v. Versi sebelumnya tetap tersimpan di riwayat.");
        else    setFlash('warning', 'Belum ada responden konsisten, sehingga hasil pakar belum bisa disimpan.');
    }
    header('Location: ' . APP_URL . '/pages/pakar.php');
    exit;
}

$p = pakarHitung();
$lihat = isset($_GET['versi']) ? (int)$_GET['versi'] : 0;
$versiRow = null; $d = null; $judulSumber = 'Perhitungan saat ini (belum disimpan)';
if ($lihat > 0) {
    $versiRow = fetchOne("SELECT a.*, t.detail_json FROM hasil_ahp_pakar a JOIN hasil_topsis_pakar t ON t.hasil_ahp_id = a.id WHERE a.versi = ?", [$lihat], 'i');
    if ($versiRow) { $d = json_decode($versiRow['detail_json'], true); $judulSumber = 'Versi ' . $versiRow['versi'] . ' · ' . $versiRow['dibuat_pada']; }
}
if (!$d && $p['topsis']) $d = pakarDetail($p);
$riwayat = fetchAll("SELECT a.versi, a.dibuat_pada, a.jumlah_responden, a.bobot_json, a.cr_maks, a.catatan, t.ranking_json
                     FROM hasil_ahp_pakar a JOIN hasil_topsis_pakar t ON t.hasil_ahp_id = a.id ORDER BY a.versi DESC");

function f6($v) { return number_format((float)$v, 6, ',', ''); }

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="info-box fade-up">
  <strong>Hasil TOPSIS Pakar.</strong> Bobot pakar diperoleh dari rata-rata geometrik bobot responden yang konsisten (Teknomo, 2006),
  lalu dipakai pada TOPSIS untuk seluruh alternatif aktif tanpa filter pengguna (ranking umum).
</div>

<?php if (!$d): ?>
<div class="alert alert-warning">Belum ada responden yang konsisten. Tambahkan di menu <a href="<?= APP_URL ?>/pages/responden.php">Kelola Responden Pakar</a>.</div>
<?php else: ?>

<?php if (!$versiRow): ?>
<div class="card fade-up mb-4">
  <div class="card-header"><h3>Simpan Hasil Sebagai Versi Baru</h3></div>
  <div class="card-body">
    <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan">
      <div class="form-group" style="flex:1;min-width:240px;margin:0"><label>Catatan (opsional)</label>
        <input class="form-control" name="catatan" maxlength="200" placeholder="mis. setelah responden ke-5 ditambahkan"></div>
      <button class="btn btn-primary" type="submit">Simpan Versi Baru</button>
    </form>
    <p class="text-muted" style="font-size:.8rem;margin-top:10px">Setiap penyimpanan membuat versi baru dengan waktu dan daftar responden yang dipakai. Versi lama tidak ditimpa.</p>
  </div>
</div>
<?php else: ?>
<div class="alert alert-info">Anda sedang melihat <strong>versi <?= (int)$versiRow['versi'] ?></strong> (<?= clean($versiRow['dibuat_pada']) ?>,
  <?= (int)$versiRow['jumlah_responden'] ?> responden)<?= $versiRow['catatan'] ? ' · ' . clean($versiRow['catatan']) : '' ?>.
  <a href="<?= APP_URL ?>/pages/pakar.php">Kembali ke perhitungan saat ini</a></div>
<?php endif; ?>

<div class="card fade-up mb-4">
  <div class="card-header"><h3>Bobot AHP Pakar</h3><span class="badge badge-blue"><?= clean($judulSumber) ?></span></div>
  <div class="card-body"><div class="table-wrap"><table>
    <thead><tr><?php foreach ($d['kode_kriteria'] as $k): ?><th><?= clean($k) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
      <tr><?php foreach ($d['bobot'] as $w): ?><td><strong><?= f6($w) ?></strong> <span class="text-muted">(<?= formatPersen($w) ?>)</span></td><?php endforeach; ?></tr>
      <tr><?php foreach ($d['tipe'] as $t): ?><td><?= $t === 'cost' ? 'Cost' : 'Benefit' ?></td><?php endforeach; ?></tr>
    </tbody></table></div></div>
</div>

<?php
  $m = count($d['kode_alt']); $n = count($d['kode_kriteria']);
  $urut = range(0, $m - 1); usort($urut, fn($a, $b) => $d['rank'][$a] <=> $d['rank'][$b]);
?>
<div class="card fade-up mb-4">
  <div class="card-header"><h3>Peringkat TOPSIS Pakar</h3><span class="badge badge-blue"><?= $m ?> alternatif</span></div>
  <div class="card-body"><div class="table-wrap"><table>
    <thead><tr><th>Peringkat</th><th>Kode</th><th>Alternatif</th><th>D⁺</th><th>D⁻</th><th>Nilai preferensi</th></tr></thead>
    <tbody>
    <?php foreach ($urut as $i): ?>
      <tr><td><strong><?= (int)$d['rank'][$i] ?></strong></td><td><?= clean($d['kode_alt'][$i]) ?></td><td><?= clean($d['nama_alt'][$i]) ?></td>
          <td><?= f6($d['Dplus'][$i]) ?></td><td><?= f6($d['Dminus'][$i]) ?></td><td><strong><?= f6($d['CC'][$i]) ?></strong></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
</div>

<?php
$tahap = [
  ['1. Matriks keputusan (X)', 'x_ij: nilai alternatif i pada kriteria j', 'X', 'alt'],
  ['2. Normalisasi (R)', 'r_ij = x_ij / √(Σ x_ij²). Pembagi per kriteria ditampilkan di bawah tabel.', 'R', 'alt'],
  ['3. Normalisasi terbobot (V)', 'v_ij = w_j × r_ij', 'V', 'alt'],
];
foreach ($tahap as [$judul, $rumus, $kunci]): ?>
<details class="card fade-up mb-4">
  <summary class="card-header" style="cursor:pointer"><h3><?= $judul ?></h3></summary>
  <div class="card-body">
    <p class="text-muted" style="font-size:.82rem"><?= $rumus ?></p>
    <div class="table-wrap"><table>
      <thead><tr><th>Kode</th><th>Alternatif</th><?php foreach ($d['kode_kriteria'] as $k): ?><th><?= clean($k) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($d['kode_alt'] as $i => $ka): ?>
        <tr><td><?= clean($ka) ?></td><td><?= clean($d['nama_alt'][$i]) ?></td>
          <?php foreach ($d[$kunci][$i] as $v): ?><td><?= $kunci === 'X' ? angkaId($v, 3) : f6($v) ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      <?php if ($kunci === 'R'): ?>
        <tr><td colspan="2"><strong>Pembagi √Σx²</strong></td><?php foreach ($d['pembagi'] as $v): ?><td><strong><?= f6($v) ?></strong></td><?php endforeach; ?></tr>
      <?php endif; ?>
      </tbody></table></div>
  </div>
</details>
<?php endforeach; ?>

<details class="card fade-up mb-4">
  <summary class="card-header" style="cursor:pointer"><h3>4. Solusi ideal positif (A⁺) dan negatif (A⁻)</h3></summary>
  <div class="card-body">
    <p class="text-muted" style="font-size:.82rem">Benefit: A⁺ = maks(v), A⁻ = min(v). Cost: A⁺ = min(v), A⁻ = maks(v).</p>
    <div class="table-wrap"><table>
      <thead><tr><th></th><?php foreach ($d['kode_kriteria'] as $k): ?><th><?= clean($k) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
        <tr><td>Jenis</td><?php foreach ($d['tipe'] as $t): ?><td><?= $t === 'cost' ? 'Cost' : 'Benefit' ?></td><?php endforeach; ?></tr>
        <tr><td><strong>A⁺</strong></td><?php foreach ($d['Aplus'] as $v): ?><td><?= f6($v) ?></td><?php endforeach; ?></tr>
        <tr><td><strong>A⁻</strong></td><?php foreach ($d['Aminus'] as $v): ?><td><?= f6($v) ?></td><?php endforeach; ?></tr>
      </tbody></table></div>
  </div>
</details>

<details class="card fade-up mb-4">
  <summary class="card-header" style="cursor:pointer"><h3>5–6. Jarak ke solusi ideal dan nilai preferensi</h3></summary>
  <div class="card-body">
    <p class="text-muted" style="font-size:.82rem">D⁺ᵢ = √Σ(vᵢⱼ − A⁺ⱼ)², D⁻ᵢ = √Σ(vᵢⱼ − A⁻ⱼ)², nilai preferensi = D⁻ᵢ / (D⁺ᵢ + D⁻ᵢ). Diurutkan dari terbesar.</p>
    <div class="table-wrap"><table>
      <thead><tr><th>Peringkat</th><th>Kode</th><th>D⁺</th><th>D⁻</th><th>Nilai preferensi</th></tr></thead>
      <tbody><?php foreach ($urut as $i): ?>
        <tr><td><?= (int)$d['rank'][$i] ?></td><td><?= clean($d['kode_alt'][$i]) ?></td><td><?= f6($d['Dplus'][$i]) ?></td><td><?= f6($d['Dminus'][$i]) ?></td><td><?= f6($d['CC'][$i]) ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
  </div>
</details>
<?php endif; ?>

<div class="card fade-up mb-4">
  <div class="card-header"><h3>Riwayat Versi Hasil Pakar</h3><span class="badge badge-blue"><?= count($riwayat) ?> versi</span></div>
  <div class="card-body">
  <?php if ($riwayat): ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Versi</th><th>Waktu</th><th>Responden</th><th>Bobot (C1–C5)</th><th>Peringkat 1</th><th>Catatan</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($riwayat as $r): $bw = json_decode($r['bobot_json'], true); $rk = json_decode($r['ranking_json'], true); ?>
        <tr>
          <td><strong><?= (int)$r['versi'] ?></strong></td><td><?= clean($r['dibuat_pada']) ?></td><td><?= (int)$r['jumlah_responden'] ?></td>
          <td style="font-size:.8rem"><?= implode(' · ', array_map(fn($w) => formatPersen($w), $bw)) ?></td>
          <td><?= clean($rk[0]['nama'] ?? '-') ?></td><td><?= clean($r['catatan'] ?? '') ?></td>
          <td><a class="btn btn-outline btn-sm" href="?versi=<?= (int)$r['versi'] ?>">Lihat</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
  <?php else: ?>
    <p class="text-muted">Belum ada versi tersimpan.</p>
  <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
