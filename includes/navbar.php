<?php
// ============================================================
// FILE: includes/navbar.php
// FUNGSI: Sidebar navigasi utama (diinclude di setiap halaman)
// ============================================================
$currentPath = $_SERVER['PHP_SELF'];
function isActive($path) {
    global $currentPath;
    return strpos($currentPath, $path) !== false ? 'active' : '';
}
$user = currentUser();
$initials = strtoupper(substr($user['nama'] ?? 'U', 0, 1) . substr(explode(' ', $user['nama'] ?? 'U')[1] ?? '', 0, 1));
?>
<div class="layout">
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">
      <div class="logo-icon">⚡</div>
      <div>
        <div class="logo-text">SPK-EV</div>
        <div class="logo-sub">AHP &amp; TOPSIS</div>
      </div>
    </div>
  </div>

  <nav class="sidebar-menu">
    <div class="menu-section">
      <div class="menu-section-title">Utama</div>
      <a href="<?= APP_URL ?>/pages/dashboard.php"
         class="menu-item <?= isActive('dashboard') ?>">
        <span class="icon">🏠</span> Dashboard
      </a>
      <a href="<?= APP_URL ?>/pages/katalog.php"
         class="menu-item <?= isActive('katalog') ?>">
        <span class="icon">🚗</span> Katalog EV
      </a>
    </div>

    <div class="menu-section">
      <div class="menu-section-title">Analisis SPK</div>
      <a href="<?= APP_URL ?>/pages/preferensi.php"
         class="menu-item <?= isActive('preferensi') ?>">
        <span class="icon">🎯</span> Set Preferensi
      </a>
      <a href="<?= APP_URL ?>/pages/ahp.php"
         class="menu-item <?= isActive('ahp') ?>">
        <span class="icon">⚖️</span> Penilaian AHP
      </a>
      <a href="<?= APP_URL ?>/pages/topsis.php"
         class="menu-item <?= isActive('topsis') ?>">
        <span class="icon">📊</span> Perhitungan TOPSIS
      </a>
      <a href="<?= APP_URL ?>/pages/hasil.php"
         class="menu-item <?= isActive('hasil') ?>">
        <span class="icon">🏆</span> Hasil &amp; Rekomendasi
      </a>
    </div>

    <div class="menu-section">
      <div class="menu-section-title">Akun Saya</div>
      <a href="<?= APP_URL ?>/pages/history.php"
         class="menu-item <?= isActive('history') ?>">
        <span class="icon">📋</span> Riwayat Perhitungan
      </a>
      <a href="<?= APP_URL ?>/pages/profil.php"
         class="menu-item <?= isActive('profil') ?>">
        <span class="icon">👤</span> Profil
      </a>
      <a href="<?= APP_URL ?>/pages/tentang.php"
         class="menu-item <?= isActive('tentang') ?>">
        <span class="icon">ℹ️</span> Tentang Metode
      </a>
      <a href="<?= APP_URL ?>/auth/logout.php"
         class="menu-item"
         onclick="return confirm('Yakin ingin keluar?')">
        <span class="icon">🚪</span> Keluar
      </a>
    </div>
  </nav>

  <div class="sidebar-user">
    <div class="user-avatar"><?= $initials ?></div>
    <div class="user-info">
      <div class="name"><?= clean($user['nama'] ?? 'Pengguna') ?></div>
      <div class="role">Pengguna Sistem</div>
    </div>
  </div>
</aside>

<div class="main-content">
  <!-- TOPBAR -->
  <header class="topbar">
    <div style="display:flex;align-items:center;gap:12px">
      <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')"
              style="display:none;background:none;border:none;font-size:1.4rem;cursor:pointer">☰</button>
      <h1 class="topbar-title"><?= isset($pageTitle) ? clean($pageTitle) : 'Dashboard' ?></h1>
    </div>
    <div class="topbar-actions">
      <?php if (isset($topbarActions)) echo $topbarActions; ?>
      <a href="<?= APP_URL ?>/pages/ahp.php" class="btn btn-green btn-sm">
        ⚡ Mulai Analisis
      </a>
    </div>
  </header>

  <div class="page-content">
    <?php showFlash(); ?>
