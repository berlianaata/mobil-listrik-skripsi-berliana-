<?php
// ============================================================
// FILE: pages/history.php
// FUNGSI: Riwayat semua perhitungan AHP-TOPSIS pengguna
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$pageTitle = 'Riwayat Perhitungan';
$userId    = $_SESSION['user_id'];

// ─── Detail satu history ───
$detail = null;
if (!empty($_GET['id'])) {
    $detail = getHistoryDetail((int)$_GET['id'], $userId);
    if ($detail) {
        $detail['ranking_data'] = json_decode($detail['ranking_json'], true) ?? [];
        $detail['bobot_data']   = json_decode($detail['bobot_json'], true)   ?? [];
        $detail['filter_data']  = json_decode($detail['filter_json'], true)  ?? [];
    }
}

// ─── Hapus history ───
if (!empty($_GET['hapus']) && is_numeric($_GET['hapus'])) {
    executeQuery(
        "DELETE FROM history_perhitungan WHERE id = ? AND user_id = ?",
        [(int)$_GET['hapus'], $userId], 'ii'
    );
    setFlash('success', 'Riwayat berhasil dihapus.');
    header('Location: ' . APP_URL . '/pages/history.php');
    exit;
}

// ─── Semua history user ───
$histories = getHistoryUser($userId, 50);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<?php if ($detail): ?>
<!-- ═══════ DETAIL VIEW ═══════ -->
<div style="margin-bottom:16px">
  <a href="<?= APP_URL ?>/pages/history.php" class="btn btn-outline btn-sm">
    ← Kembali ke Riwayat
  </a>
</div>

<div class="card fade-up mb-4">
  <div class="card-header">
    <h3>📋 Detail Perhitungan: <?= clean($detail['nama_sesi']) ?></h3>
    <div style="display:flex;gap:8px">
      <?= badgeKonsisten($detail['cr_value']) ?>
      <span class="badge badge-gray"><?= tglIndonesia($detail['created_at']) ?></span>
    </div>
  </div>
  <div class="card-body">

    <!-- Info Umum -->
    <div class="grid-2 mb-4">
      <div style="background:#F8FAFC;border-radius:10px;padding:16px">
        <div style="font-size:0.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px">Info Perhitungan</div>
        <div style="display:flex;flex-direction:column;gap:8px;font-size:0.86rem">
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-muted)">Tanggal</span>
            <strong><?= tglIndonesia($detail['created_at']) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-muted)">Jumlah Alternatif</span>
            <strong><?= $detail['jumlah_alt'] ?> EV</strong>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-muted)">Nilai CR</span>
            <strong><?= formatAngka($detail['cr_value'], 4) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-muted)">Konsistensi</span>
            <?= badgeKonsisten($detail['cr_value']) ?>
          </div>
        </div>
      </div>

      <!-- Filter yang digunakan -->
      <div style="background:#F8FAFC;border-radius:10px;padding:16px">
        <div style="font-size:0.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px">Filter Kendaraan</div>
        <div style="display:flex;flex-direction:column;gap:8px;font-size:0.86rem">
          <?php foreach ($detail['filter_data'] as $fk => $fv): ?>
          <?php if ($fv): ?>
          <div style="display:flex;justify-content:space-between">
            <span style="color:var(--text-muted)"><?= clean(ucwords(str_replace('_',' ',$fk))) ?></span>
            <strong><?= clean($fv) ?></strong>
          </div>
          <?php endif; ?>
          <?php endforeach; ?>
          <?php if (empty(array_filter($detail['filter_data']))): ?>
            <div style="color:var(--text-muted);font-style:italic">Semua kendaraan (tanpa filter)</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Bobot AHP -->
    <?php if (!empty($detail['bobot_data'])): ?>
    <div class="mb-4">
      <div style="font-size:0.82rem;font-weight:700;color:var(--secondary);margin-bottom:12px">⚖️ Bobot Kriteria AHP</div>
      <div class="bobot-grid">
        <?php foreach ($detail['bobot_data'] as $kode => $bobot): ?>
        <div class="bobot-card">
          <div class="kode"><?= clean($kode) ?></div>
          <div class="val"><?= formatAngka($bobot, 4) ?></div>
          <div class="pct">(<?= formatPersen($bobot) ?>)</div>
          <div class="progress-bar mt-2">
            <div class="progress-fill" style="width:<?= round($bobot*100, 2) ?>%"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Ranking Tersimpan -->
    <?php if (!empty($detail['ranking_data'])): ?>
    <div>
      <div style="font-size:0.82rem;font-weight:700;color:var(--secondary);margin-bottom:12px">🏆 Hasil Ranking TOPSIS</div>
      <div class="ranking-list">
        <?php foreach ($detail['ranking_data'] as $r):
          $medals  = [1=>'medal-1', 2=>'medal-2', 3=>'medal-3'];
          $icons   = [1=>'🥇', 2=>'🥈', 3=>'🥉'];
          $classes = [1=>'r1', 2=>'r2', 3=>'r3'];
        ?>
        <div class="rank-card <?= $classes[$r['rank']] ?? '' ?>">
          <div class="rank-medal <?= isset($medals[$r['rank']]) ? $medals[$r['rank']] : 'medal-n' ?>">
            <?= isset($icons[$r['rank']]) ? $icons[$r['rank']] : $r['rank'] ?>
          </div>
          <div class="rank-info">
            <?php $p = explode(' ',$r['nama'],2); ?>
            <div class="rank-brand"><?= clean($p[0]) ?></div>
            <div class="rank-model"><?= clean($p[1] ?? '') ?></div>
            <div class="rank-specs">
              <?php if (!empty($r['data_krit'])): ?>
                <?php foreach ($r['data_krit'] as $kk => $vv): ?>
                <span class="rank-spec">📌 <?= clean($kk) ?>: <?= formatAngka($vv,1) ?></span>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
          <div class="rank-score">
            <div class="cc-val"><?= formatAngka($r['CC'],4) ?></div>
            <div class="cc-label">Skor CC</div>
            <div class="cc-bar mt-2">
              <div class="cc-bar-fill" style="width:<?= round($r['CC']*100,1) ?>%"></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- ═══════ LIST VIEW ═══════ -->

<div class="card fade-up">
  <div class="card-header">
    <h3>📋 Semua Riwayat Perhitungan</h3>
    <span class="badge badge-blue"><?= count($histories) ?> sesi</span>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($histories)): ?>
    <div style="text-align:center;padding:48px;color:var(--text-muted)">
      <div style="font-size:3.5rem;margin-bottom:12px">📭</div>
      <div style="font-size:1rem;font-weight:600;margin-bottom:8px">Belum Ada Riwayat</div>
      <p style="font-size:0.86rem;max-width:360px;margin:0 auto">
        Riwayat akan tersimpan otomatis setiap kali Anda menjalankan perhitungan TOPSIS.
      </p>
      <a href="<?= APP_URL ?>/pages/ahp.php" class="btn btn-primary mt-3">
        ⚡ Mulai Perhitungan
      </a>
    </div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Nama Sesi</th>
            <th>Tanggal</th>
            <th>Alternatif</th>
            <th>Nilai CR</th>
            <th>Konsistensi</th>
            <th>Rekomendasi #1</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($histories as $i => $h):
            $rankingData = json_decode($h['ranking_json'], true) ?? [];
            $top1Name    = $rankingData[0]['nama'] ?? '—';
            $top1CC      = $rankingData[0]['CC']   ?? 0;
          ?>
          <tr>
            <td style="color:var(--text-muted)"><?= $i+1 ?></td>
            <td>
              <a href="?id=<?= $h['id'] ?>"
                 style="font-weight:600;color:var(--secondary);text-decoration:none">
                <?= clean($h['nama_sesi'] ?? 'Sesi #'.$h['id']) ?>
              </a>
            </td>
            <td style="font-size:0.82rem;color:var(--text-muted)">
              <?= tglIndonesia($h['created_at']) ?>
            </td>
            <td>
              <span class="badge badge-blue"><?= $h['jumlah_alt'] ?> EV</span>
            </td>
            <td><?= formatAngka($h['cr_value'], 4) ?></td>
            <td><?= badgeKonsisten($h['cr_value']) ?></td>
            <td style="font-size:0.82rem">
              <?php if ($top1Name !== '—'): ?>
              <strong style="color:var(--primary)"><?= clean(substr($top1Name,0,28)) ?></strong>
              <br><span style="font-size:0.72rem;color:var(--text-muted)">CC = <?= formatAngka($top1CC,4) ?></span>
              <?php else: ?>
              <span style="color:var(--text-muted)">—</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:6px">
                <a href="?id=<?= $h['id'] ?>" class="btn btn-outline btn-sm">Detail</a>
                <a href="?hapus=<?= $h['id'] ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Hapus riwayat ini?')">Hapus</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
