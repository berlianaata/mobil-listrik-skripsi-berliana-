<?php
// ============================================================
// FILE: pages/profil.php
// FUNGSI: Profil pengguna dan ubah kata sandi
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pageTitle = 'Profil Saya';
$userId    = $_SESSION['user_id'];
$user      = currentUser();
$errProfil = ''; $errPw = '';
$okProfil  = ''; $okPw  = '';

// ─── Update Nama ───
if (isset($_POST['action']) && $_POST['action'] === 'update_profil') {
    $nama = trim($_POST['name'] ?? '');
    if (strlen($nama) < 2) {
        $errProfil = 'Nama minimal 2 karakter.';
    } else {
        executeQuery("UPDATE users SET nama = ? WHERE id = ?", [$nama, $userId], 'si');
        $_SESSION['user_name'] = $nama;
        $okProfil = 'Nama berhasil diperbarui.';
        $user = currentUser();
    }
}

// ─── Ubah Password ───
if (isset($_POST['action']) && $_POST['action'] === 'ubah_pw') {
    $pwLama  = $_POST['pw_lama']  ?? '';
    $pwBaru  = $_POST['pw_baru']  ?? '';
    $pwBaru2 = $_POST['pw_baru2'] ?? '';

    if (!verifyPassword($pwLama, $user['password'])) {
        $errPw = 'Kata sandi lama tidak sesuai.';
    } elseif (strlen($pwBaru) < 6) {
        $errPw = 'Kata sandi baru minimal 6 karakter.';
    } elseif ($pwBaru !== $pwBaru2) {
        $errPw = 'Konfirmasi kata sandi baru tidak cocok.';
    } else {
        $hash = hashPassword($pwBaru);
        executeQuery("UPDATE users SET password = ? WHERE id = ?", [$hash, $userId], 'si');
        $okPw = 'Kata sandi berhasil diubah.';
    }
}

// ─── Statistik personal ───
$totalHistory  = fetchOne("SELECT COUNT(*) c FROM history_perhitungan WHERE user_id = ?", [$userId], 'i')['c'] ?? 0;
$konsistenCount= fetchOne("SELECT COUNT(*) c FROM history_perhitungan WHERE user_id = ? AND konsisten = 1", [$userId], 'i')['c'] ?? 0;
$bergabung     = tglIndonesia($user['created_at']);

$initials = strtoupper(
    substr($user['nama'], 0, 1) .
    (strpos($user['nama'],' ') !== false ? substr(explode(' ',$user['nama'])[1], 0, 1) : '')
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="grid-2 fade-up" style="gap:22px;align-items:start">

  <!-- KIRI: Kartu Profil -->
  <div>
    <!-- Avatar & Info -->
    <div class="card mb-4" style="text-align:center;padding:32px 24px">
      <div style="width:80px;height:80px;background:linear-gradient(135deg,var(--primary),var(--accent));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;color:#fff;margin:0 auto 16px">
        <?= $initials ?>
      </div>
      <h2 style="font-size:1.3rem;font-weight:800;color:var(--secondary);margin-bottom:4px">
        <?= clean($user['nama']) ?>
      </h2>
      <p style="color:var(--text-muted);font-size:0.86rem;margin-bottom:16px">
        <?= clean($user['email']) ?>
      </p>
      <div style="display:inline-flex;align-items:center;gap:6px;background:var(--primary-light);color:var(--primary);padding:5px 14px;border-radius:20px;font-size:0.78rem;font-weight:600">
        👤 Pengguna Sistem
      </div>
      <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);font-size:0.8rem;color:var(--text-muted)">
        Bergabung sejak <?= $bergabung ?>
      </div>
    </div>

    <!-- Statistik Personal -->
    <div class="card mb-4">
      <div class="card-header"><h3>📊 Statistik Analisis</h3></div>
      <div class="card-body" style="padding:16px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div style="text-align:center;background:#F8FAFC;border-radius:10px;padding:14px">
            <div style="font-size:1.8rem;font-weight:800;color:var(--primary)"><?= $totalHistory ?></div>
            <div style="font-size:0.72rem;color:var(--text-muted)">Total Perhitungan</div>
          </div>
          <div style="text-align:center;background:#F8FAFC;border-radius:10px;padding:14px">
            <div style="font-size:1.8rem;font-weight:800;color:var(--success)"><?= $konsistenCount ?></div>
            <div style="font-size:0.72rem;color:var(--text-muted)">Hasil Konsisten</div>
          </div>
        </div>
        <?php if ($totalHistory > 0): ?>
        <div style="margin-top:12px;background:#F8FAFC;border-radius:10px;padding:14px;text-align:center">
          <div style="font-size:1.4rem;font-weight:800;color:var(--info)">
            <?= round(($konsistenCount / $totalHistory) * 100) ?>%
          </div>
          <div style="font-size:0.72rem;color:var(--text-muted)">Tingkat Konsistensi</div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tautan Cepat -->
    <div class="card">
      <div class="card-header"><h3>⚡ Menu Cepat</h3></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:8px;padding:14px">
        <a href="<?= APP_URL ?>/pages/ahp.php" class="btn btn-outline" style="justify-content:flex-start">⚖️ Penilaian AHP</a>
        <a href="<?= APP_URL ?>/pages/topsis.php" class="btn btn-outline" style="justify-content:flex-start">📊 Perhitungan TOPSIS</a>
        <a href="<?= APP_URL ?>/pages/history.php" class="btn btn-outline" style="justify-content:flex-start">📋 Riwayat Perhitungan</a>
        <a href="<?= APP_URL ?>/pages/katalog.php" class="btn btn-outline" style="justify-content:flex-start">🚗 Katalog EV</a>
      </div>
    </div>
  </div>

  <!-- KANAN: Form Edit -->
  <div>
    <!-- Update Nama -->
    <div class="card mb-4">
      <div class="card-header"><h3>✏️ Perbarui Nama</h3></div>
      <div class="card-body">
        <?php if ($errProfil): ?><div class="alert alert-danger">❌ <?= clean($errProfil) ?></div><?php endif; ?>
        <?php if ($okProfil):  ?><div class="alert alert-success">✅ <?= clean($okProfil) ?></div><?php endif; ?>
        <form method="POST">
          <input type="hidden" name="action" value="update_profil">
          <div class="form-group">
            <label>Nama Lengkap</label>
            <input class="form-control" type="text" name="name"
                   value="<?= clean($user['nama']) ?>" required minlength="2">
          </div>
          <div class="form-group">
            <label>Email (tidak dapat diubah)</label>
            <input class="form-control" type="email" value="<?= clean($user['email']) ?>" disabled>
          </div>
          <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
        </form>
      </div>
    </div>

    <!-- Ubah Password -->
    <div class="card">
      <div class="card-header"><h3>🔐 Ubah Kata Sandi</h3></div>
      <div class="card-body">
        <?php if ($errPw): ?><div class="alert alert-danger">❌ <?= clean($errPw) ?></div><?php endif; ?>
        <?php if ($okPw):  ?><div class="alert alert-success">✅ <?= clean($okPw) ?></div><?php endif; ?>
        <form method="POST">
          <input type="hidden" name="action" value="ubah_pw">
          <div class="form-group">
            <label>Kata Sandi Saat Ini</label>
            <input class="form-control" type="password" name="pw_lama"
                   placeholder="Masukkan kata sandi lama" required>
          </div>
          <div class="form-group">
            <label>Kata Sandi Baru</label>
            <input class="form-control" type="password" name="pw_baru"
                   placeholder="Minimal 6 karakter" required minlength="6">
          </div>
          <div class="form-group">
            <label>Konfirmasi Kata Sandi Baru</label>
            <input class="form-control" type="password" name="pw_baru2"
                   placeholder="Ulangi kata sandi baru" required>
          </div>
          <button type="submit" class="btn btn-primary">🔑 Ubah Kata Sandi</button>
        </form>
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
