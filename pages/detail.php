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

$spek = [
    ['Harga OTR', formatRupiahJuta($ev['harga_rp_juta']), 'C1 · cost'],
    ['Jangkauan', clean((float)$ev['range_km']) . ' km', 'C2 · benefit'],
    ['Kapasitas baterai', clean((float)$ev['battery_capacity_kwh']) . ' kWh', 'C3 · benefit'],
    ['Daya motor', clean((float)$ev['daya_kw']) . ' kW', 'C4 · benefit'],
    ['Waktu pengisian cepat DC', clean((float)$ev['waktu_isi_menit']) . ' menit', 'C5 · cost'],
    ['Jumlah kursi', (int)$ev['seats'] . ' kursi', 'filter'],
    ['Jenis mobil', clean($ev['tipe_bodi'] ?? '-'), 'filter'],
    ['Kebutuhan', $ev['tipe_penggunaan'] === 'kantor' ? 'Kantor / niaga' : 'Perorangan', 'filter'],
];
?>
<div class="mb-3"><a href="<?= clean($back) ?>" class="btn btn-outline btn-sm">← Kembali</a></div>

<div class="card fade-up">
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;align-items:start">
      <div>
        <?php if (!empty($ev['foto_url'])): ?>
          <img src="<?= clean($ev['foto_url']) ?>" alt="<?= clean($nama) ?>" referrerpolicy="no-referrer"
               style="width:100%;border-radius:12px;display:block">
        <?php else: ?>
          <div style="background:#F1F5F9;border-radius:12px;padding:60px 20px;text-align:center;color:var(--text-muted)">Foto belum tersedia</div>
        <?php endif; ?>
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
        <?php if (!empty($ev['source_url'])): ?>
          <p style="margin-top:14px;font-size:0.82rem">
            Sumber data: <a href="<?= clean($ev['source_url']) ?>" target="_blank" rel="noopener noreferrer">halaman Oto.com</a>
          </p>
        <?php endif; ?>
        <p style="margin-top:8px;font-size:0.74rem;color:var(--text-muted)">
          Nilai harga, jangkauan, baterai, daya, dan waktu isi adalah rata-rata nilai minimum dan maksimum model pada dataset.
          Daya motor dikonversi dari hp ke kW. Jenis mobil dan foto diambil dari halaman Oto.com.
        </p>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
