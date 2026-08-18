<?php
// ============================================================
// FILE: pages/dashboard.php
// FUNGSI: Dashboard utama pengguna setelah login
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pageTitle = 'Dashboard';
$user      = currentUser();
$stats     = getStatistikDB();

// Ambil history terakhir user ini
$history = getHistoryUser($_SESSION['user_id'], 3);

// Cek apakah user sudah punya bobot AHP
$bobotAda = !empty(getBobotAHP($_SESSION['user_id']));

// Cek preferensi
$pref = getPreferensiUser($_SESSION['user_id']);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- WELCOME BANNER -->
<div class="card mb-4 fade-up" style="background:linear-gradient(135deg,var(--secondary),#1E3A5F);border-radius:var(--radius);padding:28px 32px;display:flex;align-items:center;justify-content:space-between;gap:20px">
  <div>
    <div style="font-size:0.82rem;color:rgba(255,255,255,0.5);margin-bottom:4px">
      Selamat datang kembali 👋
    </div>
    <h2 style="font-family:'Space Grotesk',sans-serif;font-size:1.6rem;font-weight:700;color:#fff;margin-bottom:8px">
      <?= clean($user['nama']) ?>
    </h2>
    <p style="color:rgba(255,255,255,0.6);font-size:0.9rem;max-width:480px">
      Gunakan sistem ini untuk menemukan kendaraan listrik terbaik menggunakan
      metode <strong style="color:var(--primary)">AHP &amp; TOPSIS</strong>.
      <?= $bobotAda ? 'Bobot AHP Anda sudah tersimpan.' : 'Mulai dengan mengatur bobot AHP.' ?>
    </p>
    <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
      <?php if (!$bobotAda): ?>
        <a href="<?= APP_URL ?>/pages/ahp.php" class="btn btn-primary btn-sm">⚖️ Mulai Penilaian AHP</a>
        <a href="<?= APP_URL ?>/pages/preferensi.php" class="btn btn-outline btn-sm" style="border-color:rgba(255,255,255,0.3);color:#fff">🎯 Set Preferensi</a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/pages/topsis.php" class="btn btn-primary btn-sm">📊 Hitung TOPSIS</a>
        <a href="<?= APP_URL ?>/pages/hasil.php" class="btn btn-outline btn-sm" style="border-color:rgba(255,255,255,0.3);color:#fff">🏆 Lihat Hasil</a>
      <?php endif; ?>
    </div>
  </div>
  <div style="font-size:5rem;opacity:0.15;flex-shrink:0">⚡</div>
</div>

<!-- STATISTIK DATABASE -->
<div class="stats-grid fade-up fade-up-d1">
  <div class="stat-card green">
    <div class="stat-icon green">🚗</div>
    <div class="stat-info">
      <div class="value"><?= number_format($stats['total_ev']) ?></div>
      <div class="label">Total Data EV</div>
      <div class="sub">Di database sistem</div>
    </div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon blue">🏭</div>
    <div class="stat-info">
      <div class="value"><?= $stats['total_brand'] ?></div>
      <div class="label">Brand/Merek EV</div>
      <div class="sub">Dari seluruh dunia</div>
    </div>
  </div>
  <div class="stat-card orange">
    <div class="stat-icon orange">📏</div>
    <div class="stat-info">
      <div class="value"><?= number_format($stats['max_range']) ?></div>
      <div class="label">Range Terpanjang (km)</div>
      <div class="sub">EV dengan jangkauan terbaik</div>
    </div>
  </div>
  <div class="stat-card teal">
    <div class="stat-icon teal">📊</div>
    <div class="stat-info">
      <div class="value"><?= $stats['total_history'] ?></div>
      <div class="label">Total Perhitungan</div>
      <div class="sub">Seluruh pengguna sistem</div>
    </div>
  </div>
</div>

<!-- PANDUAN LANGKAH -->
<div class="card fade-up fade-up-d2 mb-4">
  <div class="card-header">
    <h3>🗺️ Panduan Penggunaan Sistem SPK</h3>
  </div>
  <div class="card-body">
    <div class="step-nav">
      <div class="step-item <?= empty($pref) ? 'active' : 'done' ?>">
        <div class="step-num"><?= empty($pref) ? '1' : '✓' ?></div>
        <div class="step-label">Set Preferensi<br><span style="font-size:0.68rem;font-weight:400">Filter kendaraan</span></div>
      </div>
      <div class="step-item <?= !$bobotAda ? ($pref ? 'active' : '') : 'done' ?>">
        <div class="step-num"><?= $bobotAda ? '✓' : '2' ?></div>
        <div class="step-label">Penilaian AHP<br><span style="font-size:0.68rem;font-weight:400">Bobot kriteria</span></div>
      </div>
      <div class="step-item <?= $bobotAda ? 'active' : '' ?>">
        <div class="step-num">3</div>
        <div class="step-label">Hitung TOPSIS<br><span style="font-size:0.68rem;font-weight:400">Proses ranking</span></div>
      </div>
      <div class="step-item">
        <div class="step-num">4</div>
        <div class="step-label">Lihat Hasil<br><span style="font-size:0.68rem;font-weight:400">Rekomendasi EV</span></div>
      </div>
    </div>

    <div class="grid-2" style="gap:14px">
      <a href="<?= APP_URL ?>/pages/preferensi.php"
         class="btn btn-outline" style="justify-content:flex-start">
        🎯 <span>1. Set Preferensi Kendaraan</span>
      </a>
      <a href="<?= APP_URL ?>/pages/ahp.php"
         class="btn btn-outline" style="justify-content:flex-start">
        ⚖️ <span>2. Penilaian AHP</span>
      </a>
      <a href="<?= APP_URL ?>/pages/topsis.php"
         class="btn <?= $bobotAda ? 'btn-primary' : 'btn-outline' ?>"
         style="justify-content:flex-start">
        📊 <span>3. Perhitungan TOPSIS</span>
      </a>
      <a href="<?= APP_URL ?>/pages/hasil.php"
         class="btn btn-outline" style="justify-content:flex-start">
        🏆 <span>4. Hasil &amp; Rekomendasi</span>
      </a>
    </div>
  </div>
</div>

<!-- RIWAYAT TERAKHIR -->
<div class="card fade-up fade-up-d3">
  <div class="card-header">
    <h3>📋 Riwayat Perhitungan Terakhir</h3>
    <a href="<?= APP_URL ?>/pages/history.php" class="btn btn-outline btn-sm">Lihat Semua</a>
  </div>
  <div class="card-body">
    <?php if (empty($history)): ?>
      <div style="text-align:center;padding:32px;color:var(--text-muted)">
        <div style="font-size:3rem;margin-bottom:10px">📭</div>
        <p>Belum ada riwayat perhitungan.<br>
        <a href="<?= APP_URL ?>/pages/ahp.php" style="color:var(--primary);font-weight:600">Mulai perhitungan pertama Anda →</a></p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Nama Sesi</th>
              <th>Nilai CR</th>
              <th>Konsistensi</th>
              <th>Alt.</th>
              <th>Tanggal</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $i => $h): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><strong><?= clean($h['nama_sesi'] ?? 'Tanpa Nama') ?></strong></td>
              <td><?= formatAngka($h['cr_value'], 4) ?></td>
              <td><?= badgeKonsisten($h['cr_value']) ?></td>
              <td><?= $h['jumlah_alt'] ?></td>
              <td><?= tglIndonesia($h['created_at']) ?></td>
              <td>
                <a href="<?= APP_URL ?>/pages/history.php?id=<?= $h['id'] ?>"
                   class="btn btn-outline btn-sm">Detail</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
