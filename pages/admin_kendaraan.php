<?php
// ============================================================
// FILE: pages/admin_kendaraan.php
// FUNGSI: CRUD data kendaraan listrik (khusus Admin) — F-01, F-07
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pageTitle = 'Kelola Kendaraan';
$errors    = [];
$edit      = null;

// Kolom kriteria + metadata yang dapat diisi lewat form
function validasiKendaraan(array $in, array &$errors): array {
    $d = [];
    $d['brand'] = trim($in['brand'] ?? '');
    $d['model'] = trim($in['model'] ?? '');
    if ($d['brand'] === '' || mb_strlen($d['brand']) > 30) $errors[] = 'Merek wajib diisi (maks. 30 karakter).';
    if ($d['model'] === '' || mb_strlen($d['model']) > 60) $errors[] = 'Model wajib diisi (maks. 60 karakter).';

    $aturan = [  // kolom => [label, min, max, desimal?]
        'harga_rp_juta'        => ['Harga OTR (Rp juta)', 10, 20000, true],
        'range_km'             => ['Jangkauan (km)', 20, 2000, true],
        'battery_capacity_kwh' => ['Kapasitas baterai (kWh)', 5, 300, true],
        'waktu_isi_menit'      => ['Waktu pengisian cepat DC (menit)', 1, 1500, true],
        'daya_hp'              => ['Daya motor (hp)', 5, 2000, true],
        'seats'                => ['Jumlah kursi', 1, 30, false],
    ];
    foreach ($aturan as $kol => [$label, $min, $max, $desimal]) {
        $raw = $in[$kol] ?? '';
        $v   = filter_var($raw, FILTER_VALIDATE_FLOAT);
        if ($v === false || $v < $min || $v > $max) {
            $errors[] = "$label harus berupa angka $min – $max.";
            $d[$kol] = 0;
        } else {
            $d[$kol] = $desimal ? round($v, 2) : (int)round($v);
        }
    }
    $d['tipe_penggunaan'] = in_array($in['tipe_penggunaan'] ?? '', ['pribadi','kantor'], true) ? $in['tipe_penggunaan'] : '';
    if ($d['tipe_penggunaan'] === '') $errors[] = 'Kebutuhan harus Perorangan atau Kantor.';
    $d['source_url'] = trim($in['source_url'] ?? '');
    if (mb_strlen($d['source_url']) > 150) $errors[] = 'Link sumber maksimal 150 karakter.';
    $d['status'] = ($in['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
    return $d;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = $_POST['aksi'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);

    if ($aksi === 'simpan') {
        $d = validasiKendaraan($_POST, $errors);
        if (!$errors) {
            // cek duplikat merek+model
            $dup = fetchOne("SELECT id FROM kendaraan_ev WHERE brand = ? AND model = ? AND id <> ?",
                            [$d['brand'], $d['model'], $id], 'ssi');
            if ($dup) $errors[] = 'Kendaraan dengan merek dan model yang sama sudah ada.';
        }
        if (!$errors) {
            $src = $d['source_url'] !== '' ? $d['source_url'] : null;
            if ($id > 0) {
                executeQuery("UPDATE kendaraan_ev SET brand=?, model=?, tipe_penggunaan=?, harga_rp_juta=?, range_km=?,
                    battery_capacity_kwh=?, waktu_isi_menit=?, daya_hp=?, seats=?, source_url=?, status=? WHERE id=?",
                    [$d['brand'],$d['model'],$d['tipe_penggunaan'],$d['harga_rp_juta'],$d['range_km'],
                     $d['battery_capacity_kwh'],$d['waktu_isi_menit'],$d['daya_hp'],$d['seats'],$src,$d['status'],$id],
                    'sssdddddissi');
                setFlash('success', 'Data kendaraan berhasil diperbarui.');
            } else {
                executeQuery("INSERT INTO kendaraan_ev (brand, model, tipe_penggunaan, harga_rp_juta, range_km,
                    battery_capacity_kwh, waktu_isi_menit, daya_hp, seats, source_url, status)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                    [$d['brand'],$d['model'],$d['tipe_penggunaan'],$d['harga_rp_juta'],$d['range_km'],
                     $d['battery_capacity_kwh'],$d['waktu_isi_menit'],$d['daya_hp'],$d['seats'],$src,$d['status']],
                    'sssdddddiss');
                setFlash('success', 'Kendaraan baru berhasil ditambahkan.');
            }
            header('Location: ' . APP_URL . '/pages/admin_kendaraan.php');
            exit;
        }
        $edit = $_POST;           // tampilkan kembali input + error
        $edit['id'] = $id;
    } elseif ($aksi === 'toggle' && $id > 0) {
        executeQuery("UPDATE kendaraan_ev SET status = IF(status='aktif','nonaktif','aktif') WHERE id = ?", [$id], 'i');
        setFlash('success', 'Status kendaraan diubah.');
        header('Location: ' . APP_URL . '/pages/admin_kendaraan.php'); exit;
    } elseif ($aksi === 'hapus' && $id > 0) {
        executeQuery("DELETE FROM kendaraan_ev WHERE id = ?", [$id], 'i');
        setFlash('success', 'Kendaraan dihapus permanen.');
        header('Location: ' . APP_URL . '/pages/admin_kendaraan.php'); exit;
    }
}

if ($edit === null && !empty($_GET['edit'])) {
    $edit = fetchOne("SELECT * FROM kendaraan_ev WHERE id = ?", [(int)$_GET['edit']], 'i');
}

// ─── Daftar + pencarian + pagination ───
$q       = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$where   = $q !== '' ? "WHERE brand LIKE ? OR model LIKE ?" : '';
$params  = $q !== '' ? ["%$q%", "%$q%"] : [];
$total   = (int)(fetchOne("SELECT COUNT(*) c FROM kendaraan_ev $where", $params, str_repeat('s', count($params)))['c'] ?? 0);
$pages   = max(1, (int)ceil($total / $perPage));
$page    = min($page, $pages);
$rows    = fetchAll("SELECT * FROM kendaraan_ev $where ORDER BY brand, model LIMIT ? OFFSET ?",
                    array_merge($params, [$perPage, ($page - 1) * $perPage]),
                    str_repeat('s', count($params)) . 'ii');

$f = fn($k, $def = '') => clean($edit[$k] ?? $def);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
showFlash();
?>

<div class="card fade-up mb-4">
  <div class="card-header"><h3><?= !empty($edit['id']) ? 'Edit Kendaraan' : 'Tambah Kendaraan' ?></h3></div>
  <div class="card-body">
    <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= clean($e) ?></div><?php endforeach; ?>
    <form method="POST" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="grid-2" style="gap:14px">
        <div class="form-group"><label>Merek</label><input class="form-control" name="brand" maxlength="30" value="<?= $f('brand') ?>" required></div>
        <div class="form-group"><label>Model</label><input class="form-control" name="model" maxlength="60" value="<?= $f('model') ?>" required></div>
        <div class="form-group"><label>Kebutuhan</label>
          <select class="form-control" name="tipe_penggunaan" required>
            <option value="">— pilih —</option>
            <option value="pribadi" <?= ($edit['tipe_penggunaan'] ?? '') === 'pribadi' ? 'selected' : '' ?>>Perorangan (pribadi)</option>
            <option value="kantor" <?= ($edit['tipe_penggunaan'] ?? '') === 'kantor' ? 'selected' : '' ?>>Kantor / niaga</option>
          </select></div>
        <div class="form-group"><label>C1 Harga OTR (Rp juta) · cost</label><input class="form-control" type="number" step="0.01" name="harga_rp_juta" value="<?= $f('harga_rp_juta') ?>" required></div>
        <div class="form-group"><label>C2 Jangkauan (km) · benefit</label><input class="form-control" type="number" step="0.1" name="range_km" value="<?= $f('range_km') ?>" required></div>
        <div class="form-group"><label>C3 Kapasitas baterai (kWh) · benefit</label><input class="form-control" type="number" step="0.01" name="battery_capacity_kwh" value="<?= $f('battery_capacity_kwh') ?>" required></div>
        <div class="form-group"><label>C4 Waktu pengisian cepat DC (menit) · cost</label><input class="form-control" type="number" step="0.1" name="waktu_isi_menit" value="<?= $f('waktu_isi_menit') ?>" required></div>
        <div class="form-group"><label>C5 Daya motor (hp) · benefit</label><input class="form-control" type="number" step="0.1" name="daya_hp" value="<?= $f('daya_hp') ?>" required></div>
        <div class="form-group"><label>C6 Jumlah kursi · benefit</label><input class="form-control" type="number" step="1" name="seats" value="<?= $f('seats', '5') ?>" required></div>
        <div class="form-group"><label>Link sumber (Oto.com)</label><input class="form-control" name="source_url" maxlength="150" value="<?= $f('source_url') ?>"></div>
        <div class="form-group"><label>Status</label>
          <select class="form-control" name="status">
            <option value="aktif" <?= ($edit['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif (ikut perhitungan)</option>
            <option value="nonaktif" <?= ($edit['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
          </select></div>
      </div>
      <button type="submit" class="btn btn-primary">Simpan</button>
      <?php if (!empty($edit['id'])): ?><a href="admin_kendaraan.php" class="btn btn-outline">Batal</a><?php endif; ?>
    </form>
  </div>
</div>

<div class="card fade-up">
  <div class="card-header">
    <h3>Data Kendaraan (<?= $total ?>)</h3>
    <form method="GET" style="display:flex;gap:8px">
      <input class="form-control" type="text" name="q" placeholder="Cari merek / model" value="<?= clean($q) ?>">
      <button class="btn btn-outline btn-sm" type="submit">Cari</button>
    </form>
  </div>
  <div class="card-body">
    <div class="table-wrap">
      <table>
        <thead><tr><th>Merek</th><th>Model</th><th>Kebutuhan</th><th>Harga (Rp juta)</th><th>Range</th><th>kWh</th><th>Isi DC (mnt)</th><th>hp</th><th>Kursi</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= clean($r['brand']) ?></td><td><?= clean($r['model']) ?></td>
            <td><?= $r['tipe_penggunaan'] === 'kantor' ? 'Kantor' : 'Perorangan' ?></td>
            <td><?= clean((float)$r['harga_rp_juta']) ?></td>
            <td><?= clean((float)$r['range_km']) ?></td><td><?= clean((float)$r['battery_capacity_kwh']) ?></td>
            <td><?= clean((float)$r['waktu_isi_menit']) ?></td><td><?= clean((float)$r['daya_hp']) ?></td>
            <td><?= (int)$r['seats'] ?></td>
            <td><span class="badge <?= $r['status'] === 'aktif' ? 'badge-green' : 'badge-orange' ?>"><?= clean($r['status']) ?></span></td>
            <td style="white-space:nowrap">
              <a class="btn btn-outline btn-sm" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
              <form method="POST" style="display:inline"><?= csrf_field() ?>
                <input type="hidden" name="aksi" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-outline btn-sm" type="submit"><?= $r['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
              <form method="POST" style="display:inline" onsubmit="return confirm('Hapus permanen kendaraan ini?')"><?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">Hapus</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="11" style="text-align:center;color:var(--text-muted)">Tidak ada data.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pages > 1): ?>
    <div style="display:flex;gap:6px;margin-top:14px;flex-wrap:wrap">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>"
           href="?<?= http_build_query(['q' => $q, 'page' => $p]) ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
