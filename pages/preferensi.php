<?php
// ============================================================
// FILE: pages/preferensi.php
// FUNGSI: Set preferensi/filter kendaraan sebelum analisis AHP-TOPSIS
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pageTitle = 'Set Preferensi Kendaraan';
$opts      = getFilterOptions();
$pref      = getPreferensiUser($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $tipe = $_POST['tipe'] ?? '';
    $data = [
        'tipe'       => in_array($tipe, ['pribadi','kantor'], true) ? $tipe : null,
        'jumlah_alt' => min(50, max(5, (int)($_POST['jumlah_alt'] ?? 50))),
    ];

    savePreferensiUser($_SESSION['user_id'], $data);
    setFlash('success', 'Preferensi berhasil disimpan! Sekarang lanjutkan ke penilaian AHP.');
    header('Location: ' . APP_URL . '/pages/ahp.php');
    exit;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- Step Navigator -->
<div class="step-nav fade-up mb-4">
  <div class="step-item done"><div class="step-num"><?= icon('check') ?></div><div class="step-label">Login</div></div>
  <div class="step-item active"><div class="step-num">1</div><div class="step-label">Set Preferensi</div></div>
  <div class="step-item"><div class="step-num">2</div><div class="step-label">Penilaian AHP</div></div>
  <div class="step-item"><div class="step-num">3</div><div class="step-label">Hitung TOPSIS</div></div>
  <div class="step-item"><div class="step-num">4</div><div class="step-label">Hasil</div></div>
</div>

<div class="grid-2 fade-up" style="gap:22px;align-items:start">

  <!-- FORM PREFERENSI -->
  <div class="card">
    <div class="card-header">
      <h3>Pilih Kebutuhan Anda</h3>
    </div>
    <div class="card-body">
      <div class="info-box">
        <strong>Tentang Filter:</strong> Atur preferensi ini untuk memilih subset kendaraan
        yang akan dianalisis. Kosongkan filter untuk menggunakan semua kendaraan di database.
        Kendaraan yang dipilih akan menjadi alternatif dalam perhitungan TOPSIS.
      </div>

      <form method="POST" action="">
        <?= csrf_field() ?>
        <!-- Kebutuhan Pengguna -->
        <div class="form-group">
          <label for="tipe">Kebutuhan Pengguna</label>
          <select class="form-control" name="tipe" id="tipe">
            <option value="">— Semua (perorangan &amp; kantor) —</option>
            <?php foreach ($opts['tipe'] as $t): ?>
              <option value="<?= clean($t['value']) ?>"
                <?= ($pref['filter_tipe'] ?? '') === $t['value'] ? 'selected' : '' ?>>
                <?= clean($t['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted text-xs mt-1">
            Perorangan: mobil untuk penggunaan pribadi. Kantor: kendaraan niaga/operasional.
            Pilihan ini hanya menyaring alternatif, bukan kriteria penilaian.
          </small>
        </div>

        <!-- Jumlah Alternatif -->
        <div class="form-group">
          <label for="jumlah_alt">Jumlah Alternatif Maksimal</label>
          <select class="form-control" name="jumlah_alt" id="jumlah_alt">
            <?php foreach ([10,15,20,25,30,40,50] as $j): ?>
              <option value="<?= $j ?>"
                <?= (int)($pref['jumlah_alt'] ?? 50) === $j ? 'selected' : '' ?>>
                <?= $j ?> Kendaraan Termurah
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted text-xs mt-1">
            Bila alternatif lebih banyak dari batas ini, kendaraan diurutkan dari harga termurah sebelum analisis.
          </small>
        </div>

        <div style="display:flex;gap:10px;margin-top:20px">
          <button type="submit" class="btn btn-primary" style="flex:1">
            Simpan &amp; Lanjut ke AHP →
          </button>
          <a href="<?= APP_URL ?>/pages/katalog.php"
             class="btn btn-outline">Lihat Katalog</a>
        </div>
      </form>
    </div>
  </div>

  <!-- PREVIEW DATA -->
  <div>
    <div class="card mb-3">
      <div class="card-header"><h3>Informasi Kriteria Analisis</h3></div>
      <div class="card-body">
        <p class="text-sm text-muted mb-3">
          Berikut adalah <strong><?= count(getKriteria()) ?> kriteria</strong> yang digunakan dalam analisis AHP-TOPSIS:
        </p>
        <?php $kriteria = getKriteria(); ?>
        <?php foreach ($kriteria as $k): ?>
        <div style="padding:12px 0;border-bottom:1px solid var(--border)">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
            <div>
              <span style="background:var(--primary-light);color:var(--primary);font-size:0.72rem;font-weight:700;padding:2px 8px;border-radius:20px">
                <?= clean($k['kode']) ?>
              </span>
              <strong style="margin-left:8px;font-size:0.88rem"><?= clean($k['nama']) ?></strong>
            </div>
            <?= badgeTipe($k['tipe']) ?>
          </div>
          <p style="font-size:0.78rem;color:var(--text-muted);margin-top:5px;line-height:1.5">
            <?= clean($k['satuan'] !== null ? 'Satuan pengukuran: ' . $k['satuan'] : '') ?>
          </p>
          <div style="font-size:0.75rem;color:var(--primary);margin-top:3px">
            Satuan: <?= clean($k['satuan'] ?? '-') ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Preview jumlah kendaraan -->
    <?php
      $prevFilter = ['tipe' => $pref['filter_tipe'] ?? null];
      $prevData = getKendaraanFiltered($prevFilter, $pref['jumlah_alt'] ?? 50);
    ?>
    <div class="card">
      <div class="card-header">
        <h3>Preview (Preferensi Tersimpan)</h3>
        <span class="badge badge-green"><?= count($prevData) ?> EV</span>
      </div>
      <div class="card-body" style="padding:0">
        <?php if (empty($prevData)): ?>
          <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:0.86rem">
            Simpan preferensi untuk melihat preview.
          </div>
        <?php else: ?>
          <div class="table-wrap" style="max-height:320px;overflow-y:auto">
            <table>
              <thead><tr><th>#</th><th>Brand</th><th>Model</th><th>Harga</th></tr></thead>
              <tbody>
                <?php foreach (array_slice($prevData,0,10) as $i=>$ev): ?>
                <tr>
                  <td><?= $i+1 ?></td>
                  <td><strong><?= clean($ev['brand']) ?></strong></td>
                  <td><?= clean($ev['model']) ?></td>
                  <td><span class="badge badge-green"><?= formatRupiahJuta($ev['harga_rp_juta']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (count($prevData)>10): ?>
                <tr><td colspan="4" style="text-align:center;color:var(--text-muted);font-style:italic">
                  ...dan <?= count($prevData)-10 ?> kendaraan lainnya
                </td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div><!-- /.grid-2 -->

<?php include __DIR__ . '/../includes/footer.php'; ?>