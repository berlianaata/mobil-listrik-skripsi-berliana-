<?php
// ============================================================
// FILE: auth/login.php
// FUNGSI: Halaman login pengguna
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
requireGuest();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pw    = $_POST['password'] ?? '';

    if (!$email || !$pw) {
        $error = 'Email dan kata sandi harus diisi.';
    } else {
        $user = fetchOne("SELECT * FROM users WHERE email = ?", [$email], 's');
        if (!$user || !verifyPassword($pw, $user['password'])) {
            $error = 'Email atau kata sandi salah.';
        } else {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['nama'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: ' . APP_URL . '/pages/dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk | SPK-EV</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚡</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>

<div class="auth-wrapper">
  <div class="auth-orb auth-orb-1"></div>
  <div class="auth-orb auth-orb-2"></div>

  <!-- KIRI -->
  <div class="auth-left">
    <div class="auth-left-inner fade-up">
      <div class="auth-badge">🎓 Sistem Skripsi — AHP &amp; TOPSIS</div>
      <h1><span>Sistem Pendukung Keputusan</span> Kendaraan Listrik Terbaik</h1>
      <p>Manfaatkan kecerdasan matematika AHP & TOPSIS untuk memilih Electric Vehicle yang paling sesuai kebutuhan Anda dari ratusan pilihan tersedia.</p>
      <ul class="feature-list">
        <li><span class="fi">🚗</span> Database 450+ kendaraan listrik dari berbagai brand</li>
        <li><span class="fi">🎯</span> Filter berdasarkan segmen, jenis penggerak, body type</li>
        <li><span class="fi">📐</span> Perhitungan AHP dengan uji konsistensi Saaty</li>
        <li><span class="fi">🏅</span> Ranking TOPSIS dengan solusi ideal positif &amp; negatif</li>
        <li><span class="fi">💾</span> Simpan &amp; bandingkan hasil perhitungan</li>
      </ul>
    </div>
  </div>

  <!-- KANAN: Form Login -->
  <div class="auth-right">
    <div class="auth-form-box fade-up">
      <div class="auth-logo-row">
        <div class="auth-logo-icon">⚡</div>
        <span class="logo-name">SPK-EV</span>
      </div>

      <h2>Selamat Datang</h2>
      <p class="subtitle">Belum punya akun? <a href="<?= APP_URL ?>/auth/register.php">Daftar gratis</a></p>

      <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= clean($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="" novalidate>
        <div class="form-group">
          <label for="email">Alamat Email</label>
          <input class="form-control" type="email" id="email" name="email"
                 placeholder="nama@email.com"
                 value="<?= clean($_POST['email'] ?? '') ?>"
                 required autofocus>
        </div>
        <div class="form-group">
          <label for="password">Kata Sandi</label>
          <input class="form-control" type="password" id="password" name="password"
                 placeholder="Masukkan kata sandi" required>
        </div>

        <div style="margin-top:22px">
          <button type="submit" class="btn btn-primary btn-lg btn-block">
            🔐 Masuk ke Sistem
          </button>
        </div>
      </form>

      <div style="margin-top:28px;padding-top:20px;border-top:1px solid var(--border)">
        <p style="font-size:0.78rem;color:var(--text-muted);text-align:center;margin-bottom:10px">
          <strong>Akun Demo:</strong>
        </p>
        <div style="background:#F8FAFC;border-radius:8px;padding:12px 14px;font-size:0.8rem;color:var(--text-muted)">
          📧 Email: <strong>admin@spkev.com</strong><br>
          🔑 Password: <strong>admin123</strong>
        </div>
      </div>

      <div style="margin-top:20px;text-align:center;font-size:0.72rem;color:var(--text-muted)">
        Sistem Pendukung Keputusan Pemilihan EV<br>
        Metode AHP &amp; TOPSIS | Berbasis Web
      </div>
    </div>
  </div>
</div>

</body>
</html>
