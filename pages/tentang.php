<?php
// ============================================================
// FILE: pages/tentang.php
// FUNGSI: Penjelasan lengkap metode AHP dan TOPSIS untuk skripsi
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$pageTitle = 'Tentang Metode AHP & TOPSIS';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<!-- HEADER INFO -->
<div class="card fade-up mb-4" style="background:linear-gradient(135deg,var(--secondary),#1a3a5c);padding:28px 32px">
  <h2 style="font-family:'Space Grotesk',sans-serif;color:#fff;font-size:1.5rem;margin-bottom:8px">
    📚 Landasan Teori: AHP &amp; TOPSIS
  </h2>
  <p style="color:rgba(255,255,255,0.65);font-size:0.9rem;max-width:700px">
    Sistem Pendukung Keputusan (SPK) ini mengimplementasikan dua metode MADM
    (Multi Attribute Decision Making) yang banyak digunakan dalam penelitian ilmiah.
  </p>
</div>

<div class="tab-nav fade-up mb-4">
  <button class="tab-btn active" onclick="showTab('tab-ahp',this)">⚖️ Metode AHP</button>
  <button class="tab-btn" onclick="showTab('tab-topsis',this)">📊 Metode TOPSIS</button>
  <button class="tab-btn" onclick="showTab('tab-kombinasi',this)">🔗 Integrasi AHP-TOPSIS</button>
  <button class="tab-btn" onclick="showTab('tab-kriteria',this)">🎯 Kriteria & Bobot</button>
  <button class="tab-btn" onclick="showTab('tab-referensi',this)">📖 Referensi</button>
</div>

<!-- ═══ TAB AHP ═══ -->
<div id="tab-ahp" class="tab-pane active fade-up">
  <div class="card mb-4">
    <div class="card-header"><h3>⚖️ Analytic Hierarchy Process (AHP)</h3></div>
    <div class="card-body">
      <h4 style="font-size:1rem;font-weight:700;color:var(--secondary);margin-bottom:10px">📌 Pengertian</h4>
      <p style="color:var(--text-muted);font-size:0.9rem;line-height:1.8;margin-bottom:18px">
        <strong>AHP (Analytic Hierarchy Process)</strong> adalah metode pengambilan keputusan
        yang dikembangkan oleh <em>Thomas L. Saaty</em> pada tahun 1970-an. AHP digunakan
        untuk menyederhanakan masalah kompleks dengan membentuk hierarki dan melakukan
        perbandingan berpasangan (pairwise comparison) antar kriteria.
      </p>

      <h4 style="font-size:1rem;font-weight:700;color:var(--secondary);margin-bottom:10px">📐 Langkah-Langkah AHP</h4>
      <?php $steps_ahp = [
        ['num'=>1,'title'=>'Mendefinisikan Masalah & Hierarki','desc'=>'Susun masalah ke dalam struktur hierarki: Tujuan → Kriteria → Alternatif.'],
        ['num'=>2,'title'=>'Matriks Perbandingan Berpasangan','desc'=>'Buat matriks A berukuran n×n dimana n = jumlah kriteria. Setiap elemen a_ij menunjukkan tingkat kepentingan kriteria i dibanding j menggunakan skala Saaty 1–9.'],
        ['num'=>3,'title'=>'Normalisasi Matriks','desc'=>'r_ij = a_ij / Σ(kolom_j). Setiap elemen dibagi dengan jumlah kolomnya untuk mendapatkan matriks normalisasi.'],
        ['num'=>4,'title'=>'Vektor Bobot Prioritas','desc'=>'w_i = rata-rata baris dari matriks normalisasi. Jumlah semua bobot = 1.0 (100%).'],
        ['num'=>5,'title'=>'Uji Konsistensi','desc'=>'Hitung λ_max, CI = (λ_max − n)/(n−1), CR = CI/RI. Jika CR ≤ 0.1, matriks dinyatakan konsisten dan bobot dapat digunakan.'],
      ]; ?>
      <?php foreach ($steps_ahp as $s): ?>
      <div class="calc-step mb-2">
        <div class="calc-step-head">
          <div class="calc-step-num"><?= $s['num'] ?></div>
          <div class="calc-step-title"><?= $s['title'] ?></div>
        </div>
        <div class="calc-step-body">
          <p style="font-size:0.88rem;color:var(--text-muted)"><?= $s['desc'] ?></p>
        </div>
      </div>
      <?php endforeach; ?>

      <h4 style="font-size:1rem;font-weight:700;color:var(--secondary);margin:18px 0 10px">📏 Skala Perbandingan Saaty</h4>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Nilai</th><th>Definisi</th><th>Penjelasan</th></tr></thead>
          <tbody>
            <?php $saaty = [
              [1,'Sama pentingnya','Kedua elemen mempunyai pengaruh yang sama'],
              [3,'Sedikit lebih penting','Pengalaman dan pertimbangan sedikit mendukung satu elemen'],
              [5,'Lebih penting','Pengalaman dan pertimbangan sangat memihak satu elemen'],
              [7,'Sangat lebih penting','Satu elemen sangat dominan, dilakukan secara praktis'],
              [9,'Mutlak lebih penting','Satu elemen terbukti mutlak lebih disukai'],
              ['2,4,6,8','Nilai antara','Diperlukan kompromi antara dua penilaian yang berdekatan'],
            ];
            foreach($saaty as $s): ?>
            <tr>
              <td><strong style="color:var(--primary)"><?= $s[0] ?></strong></td>
              <td><?= $s[1] ?></td>
              <td style="color:var(--text-muted);font-size:0.84rem"><?= $s[2] ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h4 style="font-size:1rem;font-weight:700;color:var(--secondary);margin:18px 0 10px">📋 Tabel Random Index (RI) Saaty</h4>
      <div class="table-wrap">
        <table>
          <thead><tr><th>n</th><?php for($i=1;$i<=10;$i++) echo "<th>$i</th>"; ?></tr></thead>
          <tbody><tr><td><strong>RI</strong></td>
            <?php $ri=[0.00,0.00,0.58,0.90,1.12,1.24,1.32,1.41,1.45,1.49];
            foreach($ri as $r) echo "<td>$r</td>"; ?>
          </tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ═══ TAB TOPSIS ═══ -->
<div id="tab-topsis" class="tab-pane fade-up">
  <div class="card mb-4">
    <div class="card-header"><h3>📊 TOPSIS (Technique for Order Preference by Similarity to Ideal Solution)</h3></div>
    <div class="card-body">
      <h4 style="font-size:1rem;font-weight:700;color:var(--secondary);margin-bottom:10px">📌 Pengertian</h4>
      <p style="color:var(--text-muted);font-size:0.9rem;line-height:1.8;margin-bottom:18px">
        <strong>TOPSIS</strong> adalah metode pengambilan keputusan multi-kriteria yang dikembangkan
        oleh <em>Hwang dan Yoon (1981)</em>. TOPSIS bekerja berdasarkan prinsip bahwa alternatif
        terpilih harus memiliki <strong>jarak terpendek</strong> ke solusi ideal positif (A⁺)
        dan <strong>jarak terjauh</strong> dari solusi ideal negatif (A⁻).
      </p>

      <h4 style="font-size:1rem;font-weight:700;color:var(--secondary);margin-bottom:10px">📐 Algoritma TOPSIS (7 Langkah)</h4>
      <?php $steps_topsis = [
        ['num'=>1,'title'=>'Matriks Keputusan Awal (X)','rumus'=>'X = [x_ij], i=1..m alternatif, j=1..n kriteria','desc'=>'Susun semua nilai alternatif pada setiap kriteria ke dalam matriks m×n.'],
        ['num'=>2,'title'=>'Normalisasi Matriks (Vector Normalization)','rumus'=>'r_ij = x_ij / √(Σᵢ x_ij²)','desc'=>'Normalisasi agar data dari berbagai satuan dapat dibandingkan secara adil.'],
        ['num'=>3,'title'=>'Matriks Normalisasi Terbobot','rumus'=>'v_ij = w_j × r_ij','desc'=>'Kalikan setiap elemen normalisasi dengan bobot kriteria yang diperoleh dari AHP.'],
        ['num'=>4,'title'=>'Solusi Ideal Positif (A⁺) & Negatif (A⁻)','rumus'=>"Benefit: A⁺=max(v), A⁻=min(v)\nCost:    A⁺=min(v), A⁻=max(v)",'desc'=>'A⁺ adalah skenario terbaik, A⁻ adalah skenario terburuk untuk setiap kriteria.'],
        ['num'=>5,'title'=>'Jarak ke Solusi Ideal','rumus'=>"D⁺ᵢ = √Σⱼ(vᵢⱼ − A⁺ⱼ)²\nD⁻ᵢ = √Σⱼ(vᵢⱼ − A⁻ⱼ)²",'desc'=>'Hitung jarak Euclidean setiap alternatif ke solusi ideal positif dan negatif.'],
        ['num'=>6,'title'=>'Nilai Preferensi Relatif (CC)','rumus'=>'CCᵢ = D⁻ᵢ / (D⁺ᵢ + D⁻ᵢ)','desc'=>'CC ∈ [0,1]. Semakin besar CC → semakin mendekati solusi ideal positif.'],
        ['num'=>7,'title'=>'Perangkingan','rumus'=>'Urutkan CCᵢ secara descending','desc'=>'Alternatif dengan CC terbesar adalah rekomendasi terbaik.'],
      ]; ?>
      <?php foreach ($steps_topsis as $s): ?>
      <div class="calc-step mb-2">
        <div class="calc-step-head">
          <div class="calc-step-num"><?= $s['num'] ?></div>
          <div class="calc-step-title"><?= $s['title'] ?></div>
        </div>
        <div class="calc-step-body">
          <div class="formula-box"><?= $s['rumus'] ?></div>
          <p style="font-size:0.88rem;color:var(--text-muted)"><?= $s['desc'] ?></p>
        </div>
      </div>
      <?php endforeach; ?>

      <h4 style="font-size:1rem;font-weight:700;color:var(--secondary);margin:18px 0 10px">✅ Keunggulan TOPSIS</h4>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
        <?php $plus = [
          ['🎯','Logika Jelas','Mempertimbangkan jarak ke solusi terbaik sekaligus terburuk'],
          ['📐','Matematis Ketat','Rumusan matematika yang terstruktur dan terverifikasi'],
          ['⚡','Efisien','Komputasi cepat bahkan untuk banyak alternatif dan kriteria'],
          ['🔄','Fleksibel','Dapat dikombinasikan dengan metode lain (seperti AHP)'],
        ]; foreach($plus as $p): ?>
        <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;padding:14px">
          <div style="font-size:1.3rem;margin-bottom:6px"><?= $p[0] ?></div>
          <div style="font-weight:700;font-size:0.86rem;margin-bottom:4px"><?= $p[1] ?></div>
          <div style="font-size:0.78rem;color:var(--text-muted)"><?= $p[2] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ═══ TAB KOMBINASI ═══ -->
<div id="tab-kombinasi" class="tab-pane fade-up">
  <div class="card mb-4">
    <div class="card-header"><h3>🔗 Integrasi AHP dan TOPSIS</h3></div>
    <div class="card-body">
      <div class="info-box mb-4">
        <strong>💡 Mengapa AHP + TOPSIS?</strong><br>
        AHP digunakan untuk menentukan <strong>bobot relatif tiap kriteria</strong> secara subjektif-matematis,
        sedangkan TOPSIS menggunakan bobot tersebut untuk <strong>merangking semua alternatif</strong>
        secara objektif berdasarkan kedekatan ke solusi ideal.
      </div>

      <!-- Alur -->
      <div style="display:flex;gap:0;overflow-x:auto;padding-bottom:8px;margin-bottom:24px">
        <?php $alur = [
          ['📋','Definisi\nKriteria','Tentukan 5 kriteria evaluasi EV'],
          ['⚖️','Penilaian\nAHP','Isi matriks perbandingan berpasangan (Saaty 1-9)'],
          ['🔢','Hitung\nBobot','Normalisasi → Eigenvector → Bobot w_j'],
          ['✅','Uji\nKonsistensi','CR ≤ 0.1 → Bobot valid'],
          ['📊','Hitung\nTOPSIS','Normalisasi → Pembobotan → Ideal → Jarak → CC'],
          ['🏆','Hasil\nRanking','Perangkingan CC Descending → Rekomendasi'],
        ];
        foreach ($alur as $i=>$a): ?>
        <div style="flex:1;min-width:100px;text-align:center;padding:14px 8px;border-right:<?= $i<count($alur)-1?'1px solid var(--border)':'none' ?>;position:relative">
          <div style="font-size:1.5rem;margin-bottom:6px"><?= $a[0] ?></div>
          <div style="font-size:0.72rem;font-weight:700;color:var(--secondary);line-height:1.3;white-space:pre-line"><?= $a[1] ?></div>
          <div style="font-size:0.65rem;color:var(--text-muted);margin-top:4px;line-height:1.3"><?= $a[2] ?></div>
          <?php if ($i < count($alur)-1): ?>
          <div style="position:absolute;right:-10px;top:50%;transform:translateY(-50%);color:var(--primary);font-size:1rem;z-index:2">→</div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="grid-2">
        <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:12px;padding:18px">
          <div style="font-weight:700;color:#1D4ED8;margin-bottom:10px">⚖️ Peran AHP dalam Sistem Ini</div>
          <ul style="padding-left:18px;font-size:0.86rem;color:var(--text-muted);line-height:1.8">
            <li>Pengguna mengisi matriks perbandingan berpasangan antar 5 kriteria</li>
            <li>Sistem menghitung bobot prioritas menggunakan metode eigenvector</li>
            <li>Uji konsistensi (CR ≤ 0.1) memastikan penilaian logis</li>
            <li>Bobot yang dihasilkan mencerminkan preferensi subjektif pengguna</li>
          </ul>
        </div>
        <div style="background:#ECFDF5;border:1px solid #6EE7B7;border-radius:12px;padding:18px">
          <div style="font-weight:700;color:#065F46;margin-bottom:10px">📊 Peran TOPSIS dalam Sistem Ini</div>
          <ul style="padding-left:18px;font-size:0.86rem;color:var(--text-muted);line-height:1.8">
            <li>Menggunakan bobot dari AHP sebagai input pembobotan</li>
            <li>Normalisasi vektor menyetarakan skala berbeda antar kriteria</li>
            <li>Solusi ideal dihitung otomatis berdasarkan tipe kriteria (benefit/cost)</li>
            <li>Skor CC merangking semua EV dari yang terbaik ke terburuk</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ TAB KRITERIA ═══ -->
<div id="tab-kriteria" class="tab-pane fade-up">
  <div class="card mb-4">
    <div class="card-header"><h3>🎯 Kriteria Pemilihan EV</h3></div>
    <div class="card-body">
      <div class="info-box mb-4">
        Sistem menggunakan <strong>5 kriteria utama</strong> dalam pemilihan EV terbaik,
        dipilih berdasarkan relevansi teknis dan kemudahan pengukuran dari data dataset EV global.
      </div>
      <?php foreach (getKriteria() as $k): ?>
      <div style="padding:18px 0;border-bottom:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:8px">
          <div style="display:flex;align-items:center;gap:10px">
            <span style="background:var(--primary);color:#fff;font-size:0.72rem;font-weight:800;padding:4px 10px;border-radius:20px">
              <?= clean($k['kode']) ?>
            </span>
            <strong style="font-size:0.95rem;color:var(--secondary)"><?= clean($k['nama']) ?></strong>
          </div>
          <div style="display:flex;gap:8px;align-items:center">
            <?= badgeTipe($k['tipe']) ?>
            <span class="badge badge-blue">Bobot: <?= formatPersen($k['bobot_default'] ?? 0) ?></span>
            <span class="badge badge-gray">Satuan: <?= clean($k['satuan']) ?></span>
          </div>
        </div>
        <p style="font-size:0.86rem;color:var(--text-muted);line-height:1.7"><?= clean($k['deskripsi'] ?? '') ?></p>
        <div class="formula-box" style="margin-top:8px">
          Kolom Database: <?= clean($k['kolom_db'] ?? '-') ?>
          Tipe: <?= $k['tipe'] === 'benefit' ? 'BENEFIT (↑) — semakin besar semakin baik' : 'COST (↓) — semakin kecil semakin baik' ?>
          Bobot Default: <?= formatAngka($k['bobot_default'] ?? 0, 4) ?> (<?= formatPersen($k['bobot_default'] ?? 0) ?>)
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ═══ TAB REFERENSI ═══ -->
<div id="tab-referensi" class="tab-pane fade-up">
  <div class="card">
    <div class="card-header"><h3>📖 Daftar Referensi</h3></div>
    <div class="card-body">
      <?php $refs = [
        ['[1]','Saaty, T. L.','(1980)','The Analytic Hierarchy Process: Planning, Priority Setting, Resource Allocation','McGraw-Hill, New York.'],
        ['[2]','Hwang, C. L., & Yoon, K.','(1981)','Multiple Attribute Decision Making: Methods and Applications','Springer-Verlag, Berlin Heidelberg.'],
        ['[3]','Saaty, T. L.','(1990)','How to make a decision: The Analytic Hierarchy Process','European Journal of Operational Research, 48(1), 9–26.'],
        ['[4]','Chen, C. T.','(2000)','Extensions of the TOPSIS for group decision-making under fuzzy environment','Fuzzy Sets and Systems, 114(1), 1–9.'],
        ['[5]','Yoon, K. P., & Hwang, C. L.','(1995)','Multiple Attribute Decision Making: An Introduction','Sage Publications, California.'],
        ['[6]','Kusumadewi, S., et al.','(2006)','Fuzzy Multi-Attribute Decision Making (Fuzzy MADM)','Graha Ilmu, Yogyakarta.'],
        ['[7]','EV Database','(2024)','Electric Vehicle Specifications Database','https://ev-database.org'],
      ]; ?>
      <div style="display:flex;flex-direction:column;gap:14px">
        <?php foreach ($refs as $r): ?>
        <div style="display:flex;gap:14px;padding:14px;background:#F8FAFC;border-radius:10px;border-left:4px solid var(--primary)">
          <div style="font-weight:800;color:var(--primary);flex-shrink:0;min-width:32px"><?= $r[0] ?></div>
          <div style="font-size:0.88rem;line-height:1.6">
            <strong><?= $r[1] ?></strong> <?= $r[2] ?>.
            <em><?= $r[3] ?></em>. <?= $r[4] ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
function showTab(id, btn) {
  document.querySelectorAll('.tab-pane').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById(id)?.classList.add('active');
  btn.classList.add('active');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>