<?php
// ============================================================
// FILE: pages/responden.php
// FUNGSI: Kelola responden ahli (khusus Admin) dan agregasi kelompok menurut
//         Teknomo (2006): bobot tiap responden dihitung lebih dulu, lalu
//         rata-rata geometrik bobot:  g_c = (w_c(1) x ... x w_c(K))^(1/K),
//         dinormalisasi  w_c = g_c / jumlah g.
// Hanya responden dengan CR <= 0,1 yang ikut digabung.
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/AHP.php';
requireAdmin();

$pageTitle = 'Responden AHP';
$userId    = (int)$_SESSION['user_id'];
$kriteria  = getKriteria();
$n         = count($kriteria);
$kodeList  = array_column($kriteria, 'kode');

// Daftar pasangan (segitiga atas): [i, j]
$pasangan = [];
for ($i = 0; $i < $n; $i++) for ($j = $i + 1; $j < $n; $j++) $pasangan[] = [$i, $j];

/** Bangun matriks penuh dari daftar nilai segitiga atas. */
function matriksDariNilai(array $nilai, array $pasangan, int $n): array {
    $m = array_fill(0, $n, array_fill(0, $n, 1.0));
    foreach ($pasangan as $idx => [$i, $j]) {
        $v = (float)($nilai[$idx] ?? 1);
        if ($v <= 0) $v = 1.0;   // nilai sudah divalidasi saat input; tidak diubah
        $m[$i][$j] = $v;
        $m[$j][$i] = 1 / $v;
    }
    return $m;
}

/** Hitung AHP untuk satu matriks. */
function hitungAHP(array $kodeList, array $matriks): array {
    $ahp = new AHP($kodeList);
    $ahp->setMatriks($matriks);
    return $ahp->hitung();
}

// ─── PROSES FORM ───
$errForm = []; $posted = []; $idEdit = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'simpan') {
        $id      = (int)($_POST['id'] ?? 0);
        $nama    = trim($_POST['nama'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        $nilai   = [];
        $kosong  = []; $invalid = [];
        foreach ($pasangan as $idx => [$pi, $pj]) {
            $mentah = trim((string)($_POST['v'][$idx] ?? ''));
            $posted[$idx] = $mentah;
            $label = $kodeList[$pi] . ' dibanding ' . $kodeList[$pj];
            if ($mentah === '') { $kosong[] = $label; continue; }
            $v = str_replace(',', '.', $mentah);
            if (!is_numeric($v) || (float)$v < 0.111 - 1e-12 || (float)$v > 9) { $invalid[] = $label . ' (' . $mentah . ')'; continue; }
            $nilai[] = (float)$v;
        }
        $idEdit = $id;
        if ($nama === '') {
            $errForm[] = 'Nama responden wajib diisi.';
        }
        if ($kosong) {
            $errForm[] = 'Jawaban belum lengkap. Pasangan yang belum diisi (' . count($kosong) . ' dari ' . count($pasangan) . '): ' . implode('; ', $kosong) . '.';
        }
        if ($invalid) {
            $errForm[] = 'Nilai harus angka antara 0,111 (≈1/9) dan 9. Periksa: ' . implode('; ', $invalid) . '.';
        }
        if (!$errForm) {
            $nilai = array_map(fn($v) => round($v, 10), $nilai);   // presisi tinggi agar tidak membulat dini
            $h  = hitungAHP($kodeList, matriksDariNilai($nilai, $pasangan, $n));
            $js = json_encode($nilai);
            $cr = $h['CR'];
            $ok = $h['konsisten'] ? 1 : 0;
            if ($id > 0) {
                executeQuery("UPDATE responden_ahp SET nama=?, jabatan=?, nilai_json=?, cr=?, konsisten=? WHERE id=?",
                    [$nama, $jabatan, $js, $cr, $ok, $id], 'sssdii');
            } else {
                executeQuery("INSERT INTO responden_ahp (nama, jabatan, nilai_json, cr, konsisten) VALUES (?,?,?,?,?)",
                    [$nama, $jabatan, $js, $cr, $ok], 'sssdi');
            }
            setFlash($ok ? 'success' : 'warning',
                $ok ? 'Responden disimpan. CR = ' . round($cr, 4) . ' (konsisten).'
                    : 'Responden disimpan, tetapi penilaian TIDAK KONSISTEN (CR = ' . round($cr, 4) . ' > 0,1) dan tidak ikut digabung. Mohon minta responden menilai ulang.');
        }
    } elseif ($aksi === 'hapus') {
        executeQuery("DELETE FROM responden_ahp WHERE id = ?", [(int)($_POST['id'] ?? 0)], 'i');
        setFlash('success', 'Responden dihapus.');
    }
    if (!$errForm) {
        header('Location: ' . APP_URL . '/pages/responden.php');
        exit;
    }
}

// ─── DATA ───
$responden = fetchAll("SELECT * FROM responden_ahp ORDER BY id");
$edit = null;
if ($errForm && $idEdit > 0) {
    $edit = fetchOne("SELECT * FROM responden_ahp WHERE id = ?", [$idEdit], 'i');
    if ($edit) $edit['nilai'] = json_decode($edit['nilai_json'], true);
} elseif (isset($_GET['edit'])) {
    $edit = fetchOne("SELECT * FROM responden_ahp WHERE id = ?", [(int)$_GET['edit']], 'i');
    if ($edit) $edit['nilai'] = json_decode($edit['nilai_json'], true);
}

$rincian = [];
foreach ($responden as $r) {
    $rincian[$r['id']] = hitungAHP($kodeList, matriksDariNilai(json_decode($r['nilai_json'], true), $pasangan, $n));
}
$konsistenList = array_values(array_filter($responden, fn($r) => (int)$r['konsisten'] === 1));
$K = count($konsistenList);
$pakar = bobotPakar();
$gabBobot = $pakar ? $pakar['bobot'] : null;

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="info-box fade-up">
  <strong>Responden Ahli AHP.</strong>
  Isi 10 nilai perbandingan berpasangan (skala Saaty 1–9; pecahan seperti 0,333 berarti kriteria kolom lebih penting) untuk setiap responden.
  Menurut Teknomo (2006), bobot tiap responden dihitung lebih dulu, lalu bobot responden yang <strong>konsisten (CR ≤ 0,1)</strong>
  digabung dengan <strong>rata-rata geometrik</strong>: <em>g<sub>c</sub></em> = (<em>w<sub>c</sub></em>(1) × … × <em>w<sub>c</sub></em>(K))<sup>1/K</sup>,
  kemudian dinormalisasi <em>w<sub>c</sub></em> = <em>g<sub>c</sub></em> / Σ<em>g</em>.
</div>

<div class="card fade-up mb-4">
  <div class="card-header"><h3>Daftar Responden</h3><span class="badge badge-blue"><?= count($responden) ?> responden</span></div>
  <div class="card-body">
  <?php if ($responden): ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>Nama</th><th>Jabatan</th><th>CR</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($responden as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><strong><?= clean($r['nama']) ?></strong></td>
          <td><?= clean($r['jabatan'] ?? '') ?></td>
          <td><?= formatAngka($r['cr'], 4) ?></td>
          <td><?= $r['konsisten'] ? '<span class="badge badge-green">Konsisten · ikut digabung</span>' : '<span class="badge badge-red">Tidak konsisten · dikecualikan</span>' ?></td>
          <td style="white-space:nowrap">
            <a class="btn btn-outline btn-sm" href="?edit=<?= (int)$r['id'] ?>#form">Ubah</a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Hapus responden ini?')">
              <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-outline btn-sm" type="submit">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <p class="text-muted">Belum ada responden. Tambahkan responden ahli pada formulir di bawah.</p>
  <?php endif; ?>
  </div>
</div>

<div class="card fade-up mb-4" id="form">
  <div class="card-header"><h3><?= $edit ? 'Ubah Responden' : 'Tambah Responden' ?></h3></div>
  <div class="card-body">
    <?php if ($errForm): ?>
    <div class="alert alert-danger" role="alert" style="margin-bottom:14px">
      <strong>Data belum disimpan.</strong>
      <ul style="margin:6px 0 0 18px"><?php foreach ($errForm as $e): ?><li><?= clean($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
      <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px">
        <div class="form-group"><label>Nama responden</label>
          <input class="form-control" name="nama" required value="<?= clean($errForm ? ($_POST['nama'] ?? '') : ($edit['nama'] ?? '')) ?>"></div>
        <div class="form-group"><label>Jabatan / latar belakang</label>
          <input class="form-control" name="jabatan" value="<?= clean($errForm ? ($_POST['jabatan'] ?? '') : ($edit['jabatan'] ?? '')) ?>"></div>
      </div>
      <h4 style="margin:18px 0 4px;font-size:.95rem">Perbandingan berpasangan (<?= count($pasangan) ?> pasangan)</h4>
      <p class="text-muted" style="font-size:.8rem;margin-bottom:10px">Seluruh <?= count($pasangan) ?> pasangan wajib diisi. Gunakan 1–9 bila kriteria pertama lebih penting, dan 1/9–1 (mis. 0,333) bila kriteria kedua yang lebih penting. Isian kosong tidak dianggap 1.</p>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px">
      <?php foreach ($pasangan as $idx => [$i, $j]): ?>
        <div class="resp-pair">
          <label><b><?= clean($kriteria[$i]['kode']) ?></b> <?= clean($kriteria[$i]['nama']) ?><br>
            <span class="text-muted" style="font-size:.74rem">dibanding</span>
            <b><?= clean($kriteria[$j]['kode']) ?></b> <?= clean($kriteria[$j]['nama']) ?></label>
          <input class="form-control<?= ($errForm && ($posted[$idx] ?? '') === '') ? ' is-invalid' : '' ?>" type="text" inputmode="decimal" name="v[<?= $idx ?>]" required
                 placeholder="mis. 3 atau 0,333"
                 value="<?= clean($errForm ? ($posted[$idx] ?? '') : ($edit ? formatAngka($edit['nilai'][$idx], 3) : '')) ?>"
                 <?= ($errForm && ($posted[$idx] ?? '') === '') ? 'style="border-color:#dc2626"' : '' ?>>
        </div>
      <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap">
        <button class="btn btn-primary" type="submit"><?= $edit ? 'Simpan Perubahan' : 'Tambah Responden' ?></button>
        <?php if ($edit): ?><a class="btn btn-outline" href="<?= APP_URL ?>/pages/responden.php">Batal</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if ($responden): ?>
<div class="card fade-up mb-4">
  <div class="card-header"><h3>Rincian per Responden</h3><span class="badge badge-blue">untuk dicocokkan dengan Excel</span></div>
  <div class="card-body">
    <div class="table-wrap"><table>
      <thead><tr><th>Responden</th><?php foreach ($kriteria as $k): ?><th>w <?= clean($k['kode']) ?></th><?php endforeach; ?><th>λ_max</th><th>CI</th><th>CR</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($responden as $r): $h = $rincian[$r['id']]; ?>
        <tr>
          <td><strong><?= clean($r['nama']) ?></strong></td>
          <?php foreach ($h['bobot'] as $w): ?><td><?= number_format($w, 6, ',', '') ?></td><?php endforeach; ?>
          <td><?= number_format($h['lambda_max'], 6, ',', '') ?></td>
          <td><?= number_format($h['CI'], 6, ',', '') ?></td>
          <td><?= number_format($h['CR'], 6, ',', '') ?></td>
          <td><?= $h['konsisten'] ? 'Konsisten' : 'Tidak konsisten' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>

<?php if ($pakar): ?>
<div class="card fade-up mb-4">
  <div class="card-header">
    <h3>Bobot Pakar (Rata-Rata Geometrik Bobot)</h3>
    <span class="badge badge-blue">K = <?= (int)$pakar['K'] ?> responden</span>
  </div>
  <div class="card-body">
    <div class="table-wrap"><table>
      <thead><tr><th>Baris</th><?php foreach ($kriteria as $k): ?><th><?= clean($k['kode']) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($responden as $r): if (!$r['konsisten']) continue; $h = $rincian[$r['id']]; ?>
        <tr><td><?= clean($r['nama']) ?></td><?php foreach ($h['bobot'] as $w): ?><td><?= number_format($w, 6, ',', '') ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
        <tr><td><strong>Rata-rata geometrik (g)</strong></td><?php foreach ($pakar['geo'] as $g): ?><td><?= number_format($g, 6, ',', '') ?></td><?php endforeach; ?></tr>
        <tr><td><strong>Bobot pakar (g / Σg)</strong></td><?php foreach ($pakar['bobot'] as $w): ?><td><strong style="color:var(--primary)"><?= number_format($w, 6, ',', '') ?></strong> <span class="text-muted">(<?= formatPersen($w) ?>)</span></td><?php endforeach; ?></tr>
      </tbody>
    </table></div>
    <div class="cr-box ok" style="margin-top:16px">
      <div class="cr-icon"><?= icon('check') ?></div>
      <div class="cr-text">
        <div class="cr-title">Seluruh <?= (int)$pakar['K'] ?> responden yang digabung konsisten</div>
        <div class="cr-detail">CR terbesar di antara responden = <strong><?= number_format($pakar['cr_maks'], 6, ',', '') ?></strong> (batas 0,1). Pada agregasi bobot, konsistensi diperiksa per responden.</div>
      </div>
    </div>
    <p class="text-muted" style="font-size:.8rem;margin-top:14px">
      Cara cek di Excel: hitung bobot tiap responden (kolom bobot di tabel Rincian), lalu pada setiap kriteria pakai <code>=GEOMEAN(...)</code>
      untuk seluruh responden konsisten, kemudian bagi tiap hasil dengan jumlahnya. Bobot ini dipakai sebagai <strong>rekomendasi pakar</strong> di halaman pengguna
      dan tidak mengubah penilaian pribadi siapa pun.
    </p>
    <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn btn-primary" href="<?= APP_URL ?>/pages/hasil.php">Lihat Rekomendasi Pakar</a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
