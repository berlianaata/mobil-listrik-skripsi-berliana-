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
      <div class="logo-icon"></div>
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
        <span class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span> Dashboard
      </a>
      <a href="<?= APP_URL ?>/pages/katalog.php"
         class="menu-item <?= isActive('katalog') ?>">
        <span class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg></span> Katalog EV
      </a>
    </div>

    <div class="menu-section">
      <div class="menu-section-title">Analisis SPK</div>
      <a href="<?= APP_URL ?>/pages/preferensi.php"
         class="menu-item <?= isActive('preferensi') ?>">
        <span class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h8M18 6h2M4 12h2M12 12h8M4 18h10M20 18h0"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/></svg></span> Set Preferensi
      </a>
      <a href="<?= APP_URL ?>/pages/ahp.php"
         class="menu-item <?= isActive('ahp') ?>">
        <span class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18M5 7h14"/><path d="M5 7l-3 7a3 3 0 006 0zM19 7l-3 7a3 3 0 006 0z"/></svg></span> Penilaian AHP
      </a>
      <a href="<?= APP_URL ?>/pages/topsis.php"
         class="menu-item <?= isActive('topsis') ?>">
        <span class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg></span> Perhitungan TOPSIS
      </a>
      <a href="<?= APP_URL ?>/pages/hasil.php"
         class="menu-item <?= isActive('hasil') ?>">
        <span class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0zM17 5h3v2a3 3 0 01-3 3M7 5H4v2a3 3 0 003 3"/></svg></span> Hasil &amp; Rekomendasi
      </a>
    </div>

    <?php if (isAdmin()): ?>
    <div class="menu-section">
      <div class="menu-section-title">Administrator</div>
      <a href="<?= APP_URL ?>/pages/admin_kendaraan.php"
         class="menu-item <?= isActive('admin_kendaraan') ?>">
        <span class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.7 6.3a4 4 0 00-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 005.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z"/></svg></span> Kelola Kendaraan
      </a>
      <a href="<?= APP_URL ?>/pages/admin_user.php"
         class="menu-item <?= isActive('admin_user') ?>">
        <span class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-6 7-6s7 2.5 7 6M17 5a3.5 3.5 0 010 7M22 20c0-2.6-1.7-4.6-4.5-5.5"/></svg></span> Kelola Pengguna
      </a>
    </div>
    <?php endif; ?>

    <div class="menu-section">
      <div class="menu-section-title">Akun Saya</div>
      <a href="<?= APP_URL ?>/pages/history.php"
         class="menu-item <?= isActive('history') ?>">
        <span class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 109-9 9 9 0 00-7 3.5M3 4v4h4M12 7v5l3 2"/></svg></span> Riwayat Perhitungan
      </a>
      <a href="<?= APP_URL ?>/pages/profil.php"
         class="menu-item <?= isActive('profil') ?>">
        <span class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg></span> Profil
      </a>
      <a href="<?= APP_URL ?>/pages/tentang.php"
         class="menu-item <?= isActive('tentang') ?>">
        <span class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h0"/></svg></span> Tentang Metode
      </a>
      <a href="<?= APP_URL ?>/auth/logout.php"
         class="menu-item"
         onclick="return confirm('Yakin ingin keluar?')">
        <span class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg></span> Keluar
      </a>
    </div>
  </nav>

  <div class="sidebar-user">
    <div class="user-avatar"><?= $initials ?></div>
    <div class="user-info">
      <div class="name"><?= clean($user['nama'] ?? 'Pengguna') ?></div>
      <div class="role"><?= isAdmin() ? 'Administrator' : 'Pengguna Sistem' ?></div>
    </div>
  </div>
</aside>

<div class="main-content">
  <!-- TOPBAR -->
  <header class="topbar">
    <div style="display:flex;align-items:center;gap:12px">
      <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')"
              aria-label="Buka menu" style="display:none;background:none;border:1px solid var(--border-strong);border-radius:4px;padding:6px 8px;cursor:pointer;line-height:0"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
      <h1 class="topbar-title"><?= isset($pageTitle) ? clean($pageTitle) : 'Dashboard' ?></h1>
    </div>
    <div class="topbar-actions">
      <?php if (isset($topbarActions)) echo $topbarActions; ?>
      <a href="<?= APP_URL ?>/pages/ahp.php" class="btn btn-primary btn-sm">
        Mulai Analisis
      </a>
    </div>
  </header>

  <div class="page-content">
    <?php showFlash(); ?>
