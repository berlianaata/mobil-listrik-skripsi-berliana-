<?php
// ============================================================
// FILE: pages/validasi.php  (khusus Admin)
// FUNGSI: Membandingkan nilai hasil perhitungan Excel (pembanding independen)
//         dengan nilai hasil website, butir per butir:
//         bobot AHP tiap responden, geometric mean bobot, normalisasi bobot
//         kelompok, dan seluruh tahapan TOPSIS pakar.
// Excel TIDAK dipakai sebagai masukan perhitungan; website menghitung sendiri
// dari jawaban mentah responden (includes/pakar.php).
// ============================================================
require_once __DIR__ . '/../includes/pakar.php';
requireAdmin();

$pageTitle = 'Validasi Perhitungan';
$p      = pakarHitung();
$butir  = pakarButir($p);

// ─── Unduh templat: hanya kode dan keterangan butir, TANPA nilai website ───
if (($_GET['unduh'] ?? '') === 'templat') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="templat_validasi_excel.csv"');
    echo "\xEF\xBB\xBF";
    $f = fopen('php://output', 'w');
    fputcsv($f, ['Kode', 'Keterangan', 'Nilai Excel']);
    foreach ($butir as $kode => [$ket]) fputcsv($f, [explode('|', $kode, 2)[1], $ket, '']);
    fclose($f);
    exit;
}

$hasil = null; $galat = []; $tidakDikenal = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $berkas = $_FILES['berkas'] ?? null;
    if (!$berkas || $berkas['error'] === UPLOAD_ERR_NO_FILE) {
        $galat[] = 'Pilih berkas CSV hasil perhitungan Excel terlebih dahulu.';
    } elseif ($berkas['error'] !== UPLOAD_ERR_OK) {
        $galat[] = 'Berkas gagal diunggah (kode ' . (int)$berkas['error'] . ').';
    } elseif ($berkas['size'] > 2 * 1024 * 1024) {
        $galat[] = 'Ukuran berkas melebihi 2 MB.';
    } elseif (strtolower(pathinfo($berkas['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $galat[] = 'Format harus CSV. Di Excel: File → Simpan Sebagai → CSV (pemisah koma atau titik koma).';
    } else {
        $isi = file_get_contents($berkas['tmp_name']);
        if (!mb_check_encoding($isi, 'UTF-8')) $isi = mb_convert_encoding($isi, 'UTF-8', 'Windows-1252');
        $bc = pakarBacaCsv($isi);
        $galat = array_merge($galat, $bc['galat']);
        if (!$bc['nilai']) {
            $galat[] = 'Tidak ada nilai Excel yang terbaca. Pastikan kolom "Nilai Excel" terisi.';
        } else {
            $kodeAda = array_map(fn($k) => explode('|', $k, 2)[1], array_keys($butir));
            $tidakDikenal = array_values(array_diff(array_keys($bc['nilai']), $kodeAda));
            $hasil = pakarBandingkan($butir, $bc['nilai']);
        }
    }
}

// Ringkasan
$ring = []; $tot = ['sesuai' => 0, 'tidak' => 0, 'belum' => 0];
if ($hasil) {
    foreach ($hasil as $b) {
        $ring[$b['kel']] ??= ['sesuai' => 0, 'tidak' => 0, 'belum' => 0, 'baris' => []];
        $ring[$b['kel']][$b['status']]++;
        $ring[$b['kel']]['baris'][] = $b;
        $tot[$b['status']]++;
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';

function fmtVal($v, $d = 10) { return $v === null ? '–' : number_format((float)$v, $d, ',', ''); }
?>

<div class="info-box fade-up">
  <strong>Validasi Perhitungan (Excel vs Website).</strong>
  Website menghitung sendiri seluruh tahap dari jawaban mentah responden. Berkas Excel yang diunggah <strong>hanya dipakai sebagai pembanding</strong>
  dan tidak mengubah hasil website. Butir dinyatakan <em>Sesuai</em> bila selisih absolut ≤ <?= number_format(PAKAR_TOLERANSI, 6, ',', '') ?>.
  TOPSIS dibandingkan pada seluruh alternatif aktif tanpa filter pengguna (ranking umum).
</div>

<?php if (!$p['K']): ?>
<div class="alert alert-warning">Belum ada responden yang konsisten, sehingga belum ada nilai website untuk dibandingkan.
  Tambahkan responden di menu <a href="<?= APP_URL ?>/pages/responden.php">Responden AHP</a>.</div>
<?php else: ?>

<div class="card fade-up mb-4">
  <div class="card-header"><h3>Langkah Validasi</h3><span class="badge badge-blue"><?= count($butir) ?> butir</span></div>
  <div class="card-body">
    <ol style="margin:0 0 14px 18px;font-size:.9rem;line-height:1.7">
      <li>Unduh templat. Isinya hanya kode dan keterangan butir, <strong>tanpa angka dari website</strong>.</li>
      <li>Isi kolom <em>Nilai Excel</em> dari perhitungan Excel Anda (jangan dibulatkan; gunakan presisi penuh).</li>
      <li>Simpan sebagai CSV, lalu unggah di bawah. Butir yang dikosongkan ditandai <em>Belum diisi</em>.</li>
    </ol>
    <a class="btn btn-outline" href="?unduh=templat">Unduh Templat CSV</a>
    <form method="POST" enctype="multipart/form-data" style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <?= csrf_field() ?>
      <input class="form-control" type="file" name="berkas" accept=".csv" style="max-width:360px">
      <button class="btn btn-primary" type="submit">Bandingkan</button>
    </form>
    <?php foreach ($galat as $g): ?><div class="alert alert-danger" style="margin-top:12px"><?= clean($g) ?></div><?php endforeach; ?>
  </div>
</div>

<?php if ($hasil): ?>
<?php
  $lengkap = $tot['belum'] === 0 && $tot['tidak'] === 0;
  $kelas = $lengkap ? 'alert-success' : ($tot['tidak'] > 0 ? 'alert-danger' : 'alert-warning');
?>
<div class="alert <?= $kelas ?> fade-up">
  <strong>
  <?php if ($lengkap): ?>Seluruh <?= $tot['sesuai'] ?> butir SESUAI (selisih ≤ <?= number_format(PAKAR_TOLERANSI, 6, ',', '') ?>).
  <?php elseif ($tot['tidak'] > 0): ?><?= $tot['tidak'] ?> butir TIDAK SESUAI. Telusuri sumber selisih sebelum menyimpulkan validasi berhasil.
  <?php else: ?>Belum lengkap: <?= $tot['belum'] ?> butir belum diisi di Excel. Validasi belum dapat dinyatakan selesai.
  <?php endif; ?></strong>
  <div style="font-size:.85rem;margin-top:4px">Sesuai <?= $tot['sesuai'] ?> · Tidak sesuai <?= $tot['tidak'] ?> · Belum diisi <?= $tot['belum'] ?> · Total <?= count($hasil) ?> butir.</div>
</div>
<?php if ($tidakDikenal): ?>
<div class="alert alert-warning">Kode berikut ada di berkas tetapi tidak dikenal (diabaikan): <?= clean(implode(', ', array_slice($tidakDikenal, 0, 15))) ?><?= count($tidakDikenal) > 15 ? ' …' : '' ?></div>
<?php endif; ?>

<?php foreach ($ring as $kel => $g): ?>
<details class="card fade-up mb-4" <?= $g['tidak'] > 0 ? 'open' : '' ?>>
  <summary class="card-header" style="cursor:pointer">
    <h3><?= clean($kel) ?></h3>
    <span>
      <span class="badge badge-green"><?= $g['sesuai'] ?> sesuai</span>
      <?php if ($g['tidak']): ?><span class="badge badge-red"><?= $g['tidak'] ?> tidak sesuai</span><?php endif; ?>
      <?php if ($g['belum']): ?><span class="badge badge-gray"><?= $g['belum'] ?> belum diisi</span><?php endif; ?>
    </span>
  </summary>
  <div class="card-body">
    <div class="table-wrap"><table>
      <thead><tr><th>Kode</th><th>Butir</th><th>Nilai Excel</th><th>Nilai Website</th><th>Selisih absolut</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($g['baris'] as $b): ?>
        <tr>
          <td><code><?= clean($b['kode']) ?></code></td>
          <td><?= clean($b['ket']) ?></td>
          <td><?= fmtVal($b['excel']) ?></td>
          <td><?= fmtVal($b['web']) ?></td>
          <td><?= $b['selisih'] === null ? '–' : sprintf('%.3E', $b['selisih']) ?></td>
          <td><?php if ($b['status'] === 'sesuai'): ?><span class="badge badge-green">Sesuai</span>
              <?php elseif ($b['status'] === 'tidak'): ?><span class="badge badge-red">Tidak sesuai</span>
              <?php else: ?><span class="badge badge-gray">Belum diisi</span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</details>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
