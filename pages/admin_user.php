<?php
// ============================================================
// FILE: pages/admin_user.php
// FUNGSI: Kelola pengguna (khusus Admin): ubah peran, hapus
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pageTitle = 'Kelola Pengguna';
$meId      = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id   = (int)($_POST['id'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';
    if ($id === $meId) {
        setFlash('warning', 'Anda tidak dapat mengubah atau menghapus akun Anda sendiri di sini.');
    } elseif ($aksi === 'role' && $id > 0) {
        executeQuery("UPDATE users SET role = IF(role='admin','user','admin') WHERE id = ?", [$id], 'i');
        setFlash('success', 'Peran pengguna diubah.');
    } elseif ($aksi === 'hapus' && $id > 0) {
        executeQuery("DELETE FROM users WHERE id = ?", [$id], 'i');
        setFlash('success', 'Pengguna dihapus beserta riwayatnya.');
    }
    header('Location: ' . APP_URL . '/pages/admin_user.php');
    exit;
}

$users = fetchAll("SELECT u.id, u.nama, u.email, u.role, u.created_at,
                          (SELECT COUNT(*) FROM history_perhitungan h WHERE h.user_id = u.id) AS jml_hitung
                   FROM users u ORDER BY u.created_at DESC");

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
showFlash();
?>
<div class="card fade-up">
  <div class="card-header"><h3>Pengguna Terdaftar (<?= count($users) ?>)</h3></div>
  <div class="card-body">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Perhitungan</th><th>Terdaftar</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= clean($u['nama']) ?></td><td><?= clean($u['email']) ?></td>
            <td><span class="badge <?= $u['role'] === 'admin' ? 'badge-blue' : 'badge-green' ?>"><?= clean($u['role']) ?></span></td>
            <td><?= (int)$u['jml_hitung'] ?></td><td><?= tglIndonesia($u['created_at']) ?></td>
            <td style="white-space:nowrap">
              <?php if ((int)$u['id'] !== $meId): ?>
              <form method="POST" style="display:inline"><?= csrf_field() ?>
                <input type="hidden" name="aksi" value="role"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-outline btn-sm" type="submit"><?= $u['role'] === 'admin' ? 'Jadikan User' : 'Jadikan Admin' ?></button></form>
              <form method="POST" style="display:inline" onsubmit="return confirm('Hapus pengguna ini beserta riwayatnya?')"><?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">Hapus</button></form>
              <?php else: ?><em style="color:var(--text-muted)">akun Anda</em><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
