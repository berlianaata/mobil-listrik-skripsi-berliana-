<?php
// ============================================================
// FILE: auth/register.php
// FUNGSI: Halaman pendaftaran akun pengguna baru
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
requireGuest();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $pw1   = $_POST['password']  ?? '';
    $pw2   = $_POST['password2'] ?? '';

    if (!$name || !$email || !$pw1 || !$pw2) {
        $error = 'Semua field harus diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($pw1) < 6) {
        $error = 'Kata sandi minimal 6 karakter.';
    } elseif ($pw1 !== $pw2) {
        $error = 'Konfirmasi kata sandi tidak cocok.';
    } else {
        $conn  = db();
        $exist = fetchOne("SELECT id FROM users WHERE email = ?", [$email], 's');
        if ($exist) {
            $error = 'Email sudah terdaftar. Silakan login.';
        } else {
            $hashed = hashPassword($pw1);
            executeQuery(
                "INSERT INTO users (nama, email, password) VALUES (?, ?, ?)",
                [$name, $email, $hashed], 'sss'
            );
            $success = 'Akun berhasil dibuat! Silakan login.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Akun | SPK-EV</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚡</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>

<div class="auth-wrapper">
  <div class="auth-orb auth-orb-1"></div>
  <div class="auth-orb auth-orb-2"></div>

  <!-- KIRI: Ilustrasi & Info -->
  <div class="auth-left">
    <div class="auth-left-inner fade-up">
      <div class="auth-badge">⚡ Sistem Pendukung Keputusan EV</div>
      <h1>Temukan <span>EV Terbaik</span> untuk Anda, Secara Ilmiah</h1>
      <p>Daftar dan gunakan metode AHP & TOPSIS untuk membandingkan ratusan kendaraan listrik berdasarkan kriteria yang Anda tentukan sendiri.</p>
      <ul class="feature-list">
        <li><span class="fi">⚖️</span> Penilaian AHP dengan matriks perbandingan berpasangan</li>
        <li><span class="fi">📊</span> Perangkingan TOPSIS berbasis solusi ideal</li>
        <li><span class="fi">🏆</span> Rekomendasi EV terbaik yang personal & objektif</li>
        <li><span class="fi">📋</span> Riwayat perhitungan tersimpan untuk referensi</li>
        <li><span class="fi">🔬</span> Detail perhitungan step-by-step untuk skripsi</li>
      </ul>
    </div>
  </div>

  <!-- KANAN: Form Register -->
  <div class="auth-right">
    <div class="auth-form-box fade-up">
      <div class="auth-logo-row">
        <div class="auth-logo-icon">⚡</div>
        <span class="logo-name">SPK-EV</span>
      </div>

      <h2>Buat Akun Baru</h2>
      <p class="subtitle">Sudah punya akun? <a href="<?= APP_URL ?>/auth/login.php">Masuk di sini</a></p>

      <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= clean($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= clean($success) ?>
          <a href="<?= APP_URL ?>/auth/login.php" style="margin-left:8px;font-weight:600;color:inherit">→ Login</a>
        </div>
      <?php endif; ?>

      <?php if (!$success): ?>
      <form method="POST" action="" novalidate>
        <div class="form-group">
          <label for="name">Nama Lengkap</label>
          <input class="form-control" type="text" id="name" name="name"
                 placeholder="Contoh: Budi Santoso"
                 value="<?= clean($_POST['name'] ?? '') ?>" required autofocus>
        </div>
        <div class="form-group">
          <label for="email">Alamat Email</label>
          <input class="form-control" type="email" id="email" name="email"
                 placeholder="nama@email.com"
                 value="<?= clean($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="password">Kata Sandi</label>
          <input class="form-control" type="password" id="password" name="password"
                 placeholder="Minimal 6 karakter" required minlength="6">
        </div>
        <div class="form-group">
          <label for="password2">Konfirmasi Kata Sandi</label>
          <input class="form-control" type="password" id="password2" name="password2"
                 placeholder="Ulangi kata sandi" required>
        </div>

        <div style="margin-top:22px">
          <button type="submit" class="btn btn-primary btn-lg btn-block">
            ⚡ Daftar Sekarang
          </button>
        </div>
      </form>
      <?php endif; ?>

      <div style="margin-top:24px;text-align:center;font-size:0.75rem;color:var(--text-muted)">
        Dengan mendaftar, Anda menyetujui penggunaan data untuk keperluan analisis SPK.<br>
        <strong style="color:var(--primary)">SPK Pemilihan Kendaraan Listrik | AHP &amp; TOPSIS</strong>
      </div>
    </div>
  </div>
</div>

</body>
</html>
