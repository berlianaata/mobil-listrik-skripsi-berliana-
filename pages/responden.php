<?php
// ============================================================
// FILE: pages/responden.php
// FUNGSI: Kelola responden ahli (khusus Admin) dan penggabungan penilaian
//         perbandingan berpasangan dengan rata-rata geometrik.
//   a_ij gabungan = (a_ij(1) x a_ij(2) x ... x a_ij(K)) ^ (1/K)
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
        $v = max(1 / 9, min(9, $v));
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'simpan') {
        $id      = (int)($_POST['id'] ?? 0);
        $nama    = trim($_POST['nama'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        $nilai   = [];
        foreach ($pasangan as $idx => $p) {
            $v = (float)str_replace(',', '.', $_POST['v'][$idx] ?? '1');
            $nilai[] = round(max(1 / 9, min(9, $v ?: 1)), 6);
        }
        if ($nama === '') {
            setFlash('warning', 'Nama responden wajib diisi.');
        } else {
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
    } elseif ($aksi === 'gunakan') {
        // Simpan matriks gabungan sebagai matriks AHP milik admin yang sedang login
        $rows = fetchAll("SELECT nilai_json FROM responden_ahp WHERE konsisten = 1");
        if (!$rows) {
            setFlash('warning', 'Belum ada responden yang konsisten untuk digabung.');
        } else {
            $gab = [];
            foreach ($pasangan as $idx => $p) {
                $sum = 0.0;
                foreach ($rows as $r) $sum += log(json_decode($r['nilai_json'], true)[$idx]);
                $gab[$idx] = exp($sum / count($rows));
            }
            $mat = matriksDariNilai($gab, $pasangan, $n);
            $h   = hitungAHP($kodeList, $mat);
            foreach ($pasangan as $idx => [$i, $j]) {
                saveMatriksAHP($userId, $kodeList[$i], $kodeList[$j], $mat[$i][$j]);
                saveMatriksAHP($userId, $kodeList[$j], $kodeList[$i], round($mat[$j][$i], 6));
            }
            foreach ($kriteria as $idx => $k) {
                saveBobotAHP($userId, $k['id'], $h['bobot'][$idx], $h['lambda_max'], $h['CI'], $h['CR'], $h['konsisten'] ? 1 : 0);
            }
            setFlash('success', 'Matriks gabungan dari ' . count($rows) . ' responden disalin ke penilaian AHP Anda (CR = ' . round($h['CR'], 4) . '). Lanjutkan ke Perhitungan TOPSIS.');
        }
    }
    header('Location: ' . APP_URL . '/pages/responden.php');
    exit;
}

// ─── DATA ───
$responden = fetchAll("SELECT * FROM responden_ahp ORDER BY id");
$edit = null;
if (isset($_GET['edit'])) {
    $edit = fetchOne("SELECT * FROM responden_ahp WHERE id = ?", [(int)$_GET['edit']], 'i');
    if ($edit) $edit['nilai'] = json_decode($edit['nilai_json'], true);
}

$rincian = [];
foreach ($responden as $r) {
    $rincian[$r['id']] = hitungAHP($kodeList, matriksDariNilai(json_decode($r['nilai_json'], true), $pasangan, $n));
}
$konsistenList = array_values(array_filter($responden, fn($r) => (int)$r['konsisten'] === 1));
$K = count($konsistenList);
$gab = null; $hasilGab = null; $bobotResp = [];
if ($K > 0) {
    $nilaiGab = [];
    foreach ($pasangan as $idx => $p) {
        $sum = 0.0;
        foreach ($konsistenList as $r) $sum += log(json_decode($r['nilai_json'], true)[$idx]);
        $nilaiGab[$idx] = exp($sum / $K);
    }
    $gab      = matriksDariNilai($nilaiGab, $pasangan, $n);
    $hasilGab = hitungAHP($kodeList, $gab);
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="info-box fade-up">
  <strong>Responden Ahli AHP.</strong>
  Isi 10 nilai perbandingan berpasangan (skala Saaty 1–9; pecahan seperti 0,333 berarti kriteria kolom lebih penting) untuk setiap responden.
  Sistem menggabungkan penilaian responden yang <strong>konsisten (CR ≤ 0,1)</strong> dengan <strong>rata-rata geometrik</strong>,
  <em>a<sub>ij</sub></em> = (a<sub>ij</sub>(1) × … × a<sub>ij</sub>(K))<sup>1/K</sup>, lalu menghitung bobot, λ<sub>maks</sub>, CI, dan CR dari matriks gabungan.
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
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
      <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px">
        <div class="form-group"><label>Nama responden</label>
          <input class="form-control" name="nama" required value="<?= clean($edit['nama'] ?? '') ?>"></div>
        <div class="form-group"><label>Jabatan / latar belakang</label>
          <input class="form-control" name="jabatan" value="<?= clean($edit['jabatan'] ?? '') ?>"></div>
      </div>
      <h4 style="margin:18px 0 8px;font-size:.95rem">Perbandingan berpasangan (<?= count($pasangan) ?> pasangan)</h4>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px">
      <?php foreach ($pasangan as $idx => [$i, $j]): ?>
        <div class="resp-pair">
          <label><b><?= clean($kriteria[$i]['kode']) ?></b> <?= clean($kriteria[$i]['nama']) ?><br>
            <span class="text-muted" style="font-size:.74rem">dibanding</span>
            <b><?= clean($kriteria[$j]['kode']) ?></b> <?= clean($kriteria[$j]['nama']) ?></label>
          <input class="form-control" type="number" name="v[<?= $idx ?>]" min="0.111" max="9" step="0.001"
                 value="<?= $edit ? formatAngka($edit['nilai'][$idx], 3) : '1' ?>">
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

<?php if ($hasilGab): ?>
<div class="card fade-up mb-4">
  <div class="card-header">
    <h3>Matriks Gabungan (Rata-Rata Geometrik)</h3>
    <span class="badge badge-blue">K = <?= $K ?> responden</span>
  </div>
  <div class="card-body">
    <div class="table-wrap"><table>
      <thead><tr><th>Kriteria</th><?php foreach ($kriteria as $k): ?><th><?= clean($k['kode']) ?></th><?php endforeach; ?><th>Bobot</th></tr></thead>
      <tbody>
      <?php foreach ($gab as $i => $baris): ?>
        <tr><td><strong><?= clean($kriteria[$i]['kode']) ?></strong> <span class="text-muted" style="font-size:.74rem"><?= clean($kriteria[$i]['nama']) ?></span></td>
        <?php foreach ($baris as $v): ?><td><?= number_format($v, 6, ',', '') ?></td><?php endforeach; ?>
        <td><strong style="color:var(--primary)"><?= number_format($hasilGab['bobot'][$i], 6, ',', '') ?></strong> <span class="text-muted">(<?= formatPersen($hasilGab['bobot'][$i]) ?>)</span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="cr-box <?= $hasilGab['konsisten'] ? 'ok' : 'bad' ?>" style="margin-top:16px">
      <div class="cr-icon"><?= icon($hasilGab['konsisten'] ? 'check' : 'alert') ?></div>
      <div class="cr-text">
        <div class="cr-title"><?= $hasilGab['interpretasi'] ?></div>
        <div class="cr-detail">λ_max = <strong><?= $hasilGab['lambda_max'] ?></strong> | CI = <strong><?= $hasilGab['CI'] ?></strong> | RI = <strong><?= $hasilGab['RI'] ?></strong> | CR = <strong><?= $hasilGab['CR'] ?></strong></div>
      </div>
    </div>
    <p class="text-muted" style="font-size:.8rem;margin-top:14px">
      Hasil di atas hanya perhitungan gabungan responden dan <strong>tidak mengubah</strong> penilaian pribadi siapa pun.
      Penilaian AHP pribadi Anda tetap diisi di menu <a href="<?= APP_URL ?>/pages/ahp.php">Penilaian AHP</a>.
      Tombol di bawah menyalin matriks gabungan ke penilaian AHP Anda sendiri (<strong>menimpa</strong> isi sebelumnya) agar bisa dipakai pada TOPSIS.
    </p>
    <form method="POST" style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap"
          onsubmit="return confirm('Matriks gabungan akan menimpa penilaian AHP pribadi Anda. Lanjutkan?')">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="gunakan">
      <button class="btn btn-primary" type="submit">Salin ke Penilaian AHP Saya</button>
      <a class="btn btn-secondary" href="<?= APP_URL ?>/pages/topsis.php">Lanjut ke TOPSIS →</a>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
