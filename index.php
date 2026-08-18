<?php
// ============================================================
// FILE: index.php
// FUNGSI: Landing page / halaman utama SPK-EV
// ============================================================
require_once __DIR__ . '/includes/functions.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/pages/dashboard.php');
    exit;
}

// Statistik publik
$totalEV    = fetchOne("SELECT COUNT(*) c FROM kendaraan_ev WHERE status='aktif'")['c'] ?? 0;
$totalBrand = fetchOne("SELECT COUNT(DISTINCT brand) c FROM kendaraan_ev WHERE status='aktif'")['c'] ?? 0;
$totalUser  = fetchOne("SELECT COUNT(*) c FROM users WHERE role='user'")['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SPK-EV | Sistem Pendukung Keputusan Pemilihan Kendaraan Listrik Terbaik</title>
<meta name="description" content="Sistem Pendukung Keputusan pemilihan Electric Vehicle (EV) terbaik menggunakan metode AHP dan TOPSIS berbasis web.">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚡</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<style>
  .landing-nav {
    position:fixed; top:0; left:0; right:0; z-index:999;
    padding:16px 40px;
    display:flex; align-items:center; justify-content:space-between;
    background: rgba(6,13,26,0.85);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(255,255,255,0.06);
  }
  .landing-nav .logo { display:flex; align-items:center; gap:10px; }
  .landing-nav .logo-icon { width:36px; height:36px; background:linear-gradient(135deg,var(--primary),var(--primary-dark)); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; }
  .landing-nav .logo-text { font-family:'Space Grotesk',sans-serif; font-weight:700; font-size:1rem; color:#fff; }
  .landing-nav .nav-links { display:flex; gap:10px; }
  .steps-section { padding:80px 40px; background:#F8FAFC; }
  .steps-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:0; max-width:900px; margin:0 auto; }
  .step-card { padding:28px 20px; text-align:center; border-right:1px solid var(--border); position:relative; }
  .step-card:last-child { border-right:none; }
  .step-badge { width:48px; height:48px; background:linear-gradient(135deg,var(--primary),var(--primary-dark)); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 14px; font-size:1.2rem; box-shadow:0 4px 14px rgba(0,200,150,0.35); }
  .step-card h4 { font-size:0.92rem; font-weight:700; color:var(--secondary); margin-bottom:6px; }
  .step-card p  { font-size:0.78rem; color:var(--text-muted); line-height:1.6; }
  .footer-landing { background:var(--secondary); color:rgba(255,255,255,0.5); padding:32px 40px; text-align:center; font-size:0.82rem; }
  .footer-landing strong { color:var(--primary); }
  @media(max-width:768px){
    .landing-nav { padding:12px 20px; }
    .hero-title { font-size:1.8rem !important; }
    .hero-content { padding:40px 20px !important; }
    .steps-section, .features-section { padding:48px 20px; }
    .footer-landing { padding:20px; }
  }
</style>
</head>
<body style="margin:0;padding:0">

<!-- NAV -->
<nav class="landing-nav">
  <div class="logo">
    <div class="logo-icon">⚡</div>
    <span class="logo-text">SPK-EV</span>
  </div>
  <div class="nav-links">
    <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-outline btn-sm"
       style="border-color:rgba(255,255,255,0.2);color:#fff">Masuk</a>
    <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-primary btn-sm">Daftar Gratis</a>
  </div>
</nav>

<!-- HERO -->
<section class="landing-hero" style="padding-top:80px">
  <div class="hero-orb-1"></div>
  <div class="hero-orb-2"></div>
  <div class="hero-content" style="width:100%">
    <div class="hero-badge">
      ⚡ Berbasis Metode Ilmiah AHP &amp; TOPSIS
    </div>
    <h1 class="hero-title">
      Sistem Pendukung Keputusan<br>
      Pemilihan <span>Kendaraan Listrik</span><br>
      Terbaik
    </h1>
    <p class="hero-desc">
      Temukan Electric Vehicle yang paling sesuai kebutuhan Anda secara ilmiah
      menggunakan metode <strong style="color:var(--primary)">AHP</strong> untuk
      pembobotan kriteria dan <strong style="color:var(--primary)">TOPSIS</strong>
      untuk perangkingan alternatif. Berbasis web, mudah digunakan, detail perhitungan
      lengkap untuk keperluan skripsi/penelitian.
    </p>
    <div class="hero-actions">
      <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-primary btn-lg">
        ⚡ Mulai Sekarang — Gratis
      </a>
      <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-outline btn-lg"
         style="border-color:rgba(255,255,255,0.25);color:#fff">
        🔐 Masuk ke Sistem
      </a>
    </div>

    <!-- Stats -->
    <div class="hero-stats">
      <div class="hero-stat">
        <div class="hval"><?= number_format($totalEV) ?>+</div>
        <div class="hlbl">Data EV</div>
      </div>
      <div class="hero-stat">
        <div class="hval"><?= $totalBrand ?>+</div>
        <div class="hlbl">Brand</div>
      </div>
      <div class="hero-stat">
        <div class="hval">5</div>
        <div class="hlbl">Kriteria</div>
      </div>
    </div>
  </div>
</section>

<!-- LANGKAH PENGGUNAAN -->
<section class="steps-section">
  <div class="section-header">
    <div class="section-tag">CARA KERJA</div>
    <h2 class="section-title">4 Langkah Mudah Menemukan EV Terbaik</h2>
    <p class="section-sub">Proses analisis yang terstruktur dan ilmiah</p>
  </div>
  <div class="card steps-grid" style="max-width:1000px;margin:0 auto">
    <?php $steps = [
      ['1','🎯','Set Preferensi','Filter kendaraan berdasarkan segmen, tipe bodi, penggerak, dan jumlah kursi.'],
      ['2','⚖️','Penilaian AHP','Bandingkan 5 kriteria secara berpasangan menggunakan skala Saaty 1–9.'],
      ['3','📊','Hitung TOPSIS','Sistem otomatis menghitung ranking semua EV berdasarkan bobot AHP Anda.'],
      ['4','🏆','Lihat Hasil','Dapatkan rekomendasi EV terbaik beserta detail perhitungan lengkap.'],
    ]; foreach ($steps as $s): ?>
    <div class="step-card">
      <div class="step-badge"><?= $s[1] ?></div>
      <h4><?= $s[0] ?>. <?= $s[2] ?></h4>
      <p><?= $s[3] ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- FITUR -->
<section class="features-section">
  <div class="section-header">
    <div class="section-tag">FITUR UNGGULAN</div>
    <h2 class="section-title">Mengapa Menggunakan SPK-EV?</h2>
    <p class="section-sub">Dirancang khusus untuk analisis ilmiah dan keperluan skripsi/penelitian</p>
  </div>
  <div class="features-grid">
    <?php $features = [
      ['⚖️','Metode AHP Lengkap','Matriks perbandingan berpasangan dengan uji konsistensi Saaty (CR ≤ 0.1). Detail perhitungan setiap langkah ditampilkan.'],
      ['📊','Algoritma TOPSIS 7 Langkah','Implementasi lengkap: normalisasi vektor, pembobotan, solusi ideal, jarak Euclidean, dan skor CC.'],
      ['🚗','Database EV Lengkap','Ratusan data kendaraan listrik dari berbagai brand global dengan spesifikasi teknis detail.'],
      ['🎯','Filter Preferensi','Sesuaikan analisis berdasarkan segmen, tipe bodi, drivetrain, dan jumlah kursi yang Anda inginkan.'],
      ['📋','Riwayat Tersimpan','Setiap hasil perhitungan disimpan otomatis. Bandingkan berbagai skenario bobot kriteria.'],
      ['🎓','Siap untuk Skripsi','Semua detail perhitungan ditampilkan lengkap dan dapat dicetak sebagai dokumentasi penelitian.'],
    ]; foreach ($features as $f): ?>
    <div class="feature-card">
      <div class="feature-icon"><?= $f[0] ?></div>
      <h3><?= $f[1] ?></h3>
      <p><?= $f[2] ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- CTA BANNER -->
<section style="background:linear-gradient(135deg,var(--secondary),#1a3a5c);padding:60px 40px;text-align:center">
  <div style="max-width:600px;margin:0 auto">
    <div style="font-size:3rem;margin-bottom:16px">⚡</div>
    <h2 style="font-family:'Space Grotesk',sans-serif;font-size:2rem;font-weight:700;color:#fff;margin-bottom:12px">
      Mulai Analisis SPK Anda Sekarang
    </h2>
    <p style="color:rgba(255,255,255,0.65);margin-bottom:28px;font-size:0.95rem">
      Daftar gratis dan temukan kendaraan listrik terbaik berdasarkan preferensi Anda
      menggunakan metode AHP &amp; TOPSIS yang telah teruji secara ilmiah.
    </p>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
      <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-primary btn-lg">
        🚀 Daftar Gratis Sekarang
      </a>
      <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-outline btn-lg"
         style="border-color:rgba(255,255,255,0.3);color:#fff">
        Sudah punya akun? Masuk
      </a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer-landing">
  <div style="margin-bottom:8px">
    <strong>SPK-EV</strong> — Sistem Pendukung Keputusan Pemilihan Kendaraan Listrik (Electric Vehicle) Terbaik
  </div>
  <div>
    Menggunakan Metode <strong>Analytic Hierarchy Process (AHP)</strong>
    dan <strong>Technique for Order Preference by Similarity to Ideal Solution (TOPSIS)</strong>
  </div>
  <div style="margin-top:8px;font-size:0.75rem">
    Berbasis Web | Untuk Keperluan Penelitian &amp; Skripsi | &copy; <?= date('Y') ?>
  </div>
</footer>

</body>
</html>
