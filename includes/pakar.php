<?php
// ============================================================
// FILE: includes/pakar.php
// FUNGSI: Perhitungan jalur PAKAR (kelompok responden) yang dipakai bersama oleh
//         halaman Responden, Validasi Perhitungan, dan Hasil TOPSIS Pakar.
//
// Metode (Teknomo, 2006):
//   1) bobot prioritas tiap responden dari matriksnya sendiri (AHP)
//   2) rata-rata geometrik bobot antar responden:  g_c = (w_c(1) x ... x w_c(K))^(1/K)
//   3) normalisasi:  w_c = g_c / jumlah g
//   Konsistensi (CR <= 0,1) diperiksa pada tiap responden; yang tidak konsisten
//   dikecualikan dari penggabungan.
// Seluruh angka dihitung di PHP dari jawaban mentah responden. File Excel
// TIDAK pernah dipakai sebagai masukan perhitungan; ia hanya pembanding.
// ============================================================
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../classes/AHP.php';
require_once __DIR__ . '/../classes/TOPSIS.php';

const PAKAR_TOLERANSI = 0.000001;

/** Bangun matriks perbandingan 5x5 dari 10 nilai segitiga atas. */
function pakarMatriks(array $nilai, int $n): array {
    $m = array_fill(0, $n, array_fill(0, $n, 1.0));
    $idx = 0;
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++, $idx++) {
            $v = (float)($nilai[$idx] ?? 1);   // sudah divalidasi saat input; tidak diubah/dibulatkan
            if ($v <= 0) $v = 1.0;
            $m[$i][$j] = $v;
            $m[$j][$i] = 1 / $v;
        }
    }
    return $m;
}

/** Hitung seluruh tahap AHP pakar dan TOPSIS pakar. Tanpa pembulatan. */
function pakarHitung(bool $denganTopsis = true): array {
    $kr   = getKriteria();
    $n    = count($kr);
    $kode = array_column($kr, 'kode');
    $out  = ['kode' => $kode, 'tipe' => array_column($kr, 'tipe'), 'responden' => [], 'K' => 0,
             'geo' => null, 'bobot' => null, 'topsis' => null];

    try {
        $rows = fetchAll("SELECT * FROM responden_ahp ORDER BY id");
    } catch (Throwable $e) {
        return $out;
    }
    $logSum = array_fill(0, $n, 0.0);
    foreach ($rows as $k => $r) {
        $nilai = json_decode($r['nilai_json'], true) ?: [];
        $ahp = new AHP($kode);
        $ahp->setMatriks(pakarMatriks($nilai, $n));
        $h  = $ahp->hitung();
        $cr = $ahp->getCR();               // tanpa pembulatan
        $ok = $cr <= 0.1 + 1e-12;
        $out['responden'][] = [
            'no' => $k + 1, 'id' => (int)$r['id'], 'nama' => $r['nama'], 'jabatan' => $r['jabatan'],
            'nilai' => $nilai, 'jumlah_kolom' => $h['jumlah_kolom'], 'bobot' => $h['bobot'],
            'lambda_max' => $ahp->getLambdaMax(), 'CI' => $ahp->getCI(), 'RI' => $ahp->getRI(),
            'CR' => $cr, 'konsisten' => $ok,
        ];
        if ($ok) {
            $out['K']++;
            foreach ($h['bobot'] as $c => $w) $logSum[$c] += log($w);
        }
    }
    if ($out['K'] === 0) return $out;

    $geo = [];
    foreach ($logSum as $c => $ls) $geo[$c] = exp($ls / $out['K']);
    $tot = array_sum($geo);
    $out['geo']   = $geo;
    $out['bobot'] = array_map(fn($g) => $g / $tot, $geo);

    if (!$denganTopsis) return $out;

    // ─── TOPSIS pakar: seluruh alternatif aktif, tanpa filter pengguna (ranking umum) ───
    $kol = array_column($kr, 'kolom_db');
    $alt = fetchAll("SELECT * FROM kendaraan_ev WHERE status = 'aktif' ORDER BY id");
    $X = []; $nama = []; $kodeAlt = []; $idAlt = [];
    foreach ($alt as $a => $ev) {
        $baris = [];
        foreach ($kol as $c) $baris[] = (float)$ev[$c];
        $X[] = $baris;
        $nama[] = $ev['brand'] . ' ' . $ev['model'];
        $kodeAlt[] = 'A' . ($a + 1);
        $idAlt[] = (int)$ev['id'];
    }
    if (count($X) >= 2) {
        $t  = new TOPSIS($X, $out['bobot'], $out['tipe'], $nama, $kode);
        $hs = $t->hitung();
        $rank = [];
        foreach ($hs['ranking'] as $pos => $rk) $rank[$rk['index']] = $pos + 1;
        $pembagi = [];
        for ($j = 0; $j < $n; $j++) {
            $ss = 0.0; foreach ($X as $b) $ss += $b[$j] ** 2;
            $pembagi[$j] = sqrt($ss);
        }
        $out['topsis'] = [
            'kode_alt' => $kodeAlt, 'id_alt' => $idAlt, 'nama_alt' => $nama, 'X' => $X,
            'pembagi' => $pembagi, 'R' => $hs['matriks_normal'], 'V' => $hs['matriks_bobot'],
            'Aplus' => $hs['A_plus'], 'Aminus' => $hs['A_minus'],
            'Dplus' => $hs['D_plus'], 'Dminus' => $hs['D_minus'], 'CC' => $hs['CC'], 'rank' => $rank,
        ];
    }
    return $out;
}

/**
 * Daftar butir yang dapat dibandingkan dengan Excel: kode => [keterangan, nilai web].
 * Kode dipakai pada templat CSV (kolom Kode) agar Excel dan web dicocokkan butir per butir.
 */
function pakarButir(array $p): array {
    $b = [];
    $kode = $p['kode'];
    foreach ($p['responden'] as $r) {
        $R = 'R' . $r['no'];
        foreach ($kode as $c => $kc) $b["AHP responden|$R.w.$kc"] = ["Bobot $R ({$r['nama']}) $kc", $r['bobot'][$c]];
        $b["AHP responden|$R.lambda"] = ["λmax $R ({$r['nama']})", $r['lambda_max']];
        $b["AHP responden|$R.CI"]     = ["CI $R ({$r['nama']})", $r['CI']];
        $b["AHP responden|$R.CR"]     = ["CR $R ({$r['nama']})", $r['CR']];
    }
    if ($p['bobot']) {
        foreach ($kode as $c => $kc) $b["Geometric mean bobot|G.g.$kc"] = ["g $kc (rata-rata geometrik bobot)", $p['geo'][$c]];
        foreach ($kode as $c => $kc) $b["Normalisasi bobot kelompok|G.w.$kc"] = ["Bobot pakar $kc (g / Σg)", $p['bobot'][$c]];
    }
    if ($t = $p['topsis']) {
        foreach ($kode as $j => $kc) $b["TOPSIS pembagi|T.pembagi.$kc"] = ["Pembagi normalisasi $kc", $t['pembagi'][$j]];
        foreach ($t['kode_alt'] as $i => $ka) foreach ($kode as $j => $kc) $b["TOPSIS matriks keputusan|T.X.$ka.$kc"] = ["x $ka {$t['nama_alt'][$i]} $kc", $t['X'][$i][$j]];
        foreach ($t['kode_alt'] as $i => $ka) foreach ($kode as $j => $kc) $b["TOPSIS normalisasi|T.R.$ka.$kc"] = ["r $ka $kc", $t['R'][$i][$j]];
        foreach ($t['kode_alt'] as $i => $ka) foreach ($kode as $j => $kc) $b["TOPSIS terbobot|T.V.$ka.$kc"] = ["v $ka $kc", $t['V'][$i][$j]];
        foreach ($kode as $j => $kc) $b["TOPSIS solusi ideal|T.Aplus.$kc"]  = ["A⁺ $kc", $t['Aplus'][$j]];
        foreach ($kode as $j => $kc) $b["TOPSIS solusi ideal|T.Aminus.$kc"] = ["A⁻ $kc", $t['Aminus'][$j]];
        foreach ($t['kode_alt'] as $i => $ka) $b["TOPSIS jarak|T.Dplus.$ka"]  = ["D⁺ $ka {$t['nama_alt'][$i]}", $t['Dplus'][$i]];
        foreach ($t['kode_alt'] as $i => $ka) $b["TOPSIS jarak|T.Dminus.$ka"] = ["D⁻ $ka {$t['nama_alt'][$i]}", $t['Dminus'][$i]];
        foreach ($t['kode_alt'] as $i => $ka) $b["TOPSIS nilai preferensi|T.Pref.$ka"] = ["Nilai preferensi $ka {$t['nama_alt'][$i]}", $t['CC'][$i]];
        foreach ($t['kode_alt'] as $i => $ka) $b["TOPSIS peringkat|T.rank.$ka"] = ["Peringkat $ka {$t['nama_alt'][$i]}", (float)$t['rank'][$i]];
    }
    return $b;
}

/** Ubah teks angka (Indonesia/Inggris) menjadi float; null bila bukan angka. */
function pakarAngka(string $t): ?float {
    $t = str_replace([' ', "\xC2\xA0"], '', trim($t));
    if ($t === '') return null;
    $k = strrpos($t, ','); $d = strrpos($t, '.');
    if ($k !== false && $d !== false) {            // ada dua pemisah: yang terakhir adalah desimal
        $t = $k > $d ? str_replace(',', '.', str_replace('.', '', $t)) : str_replace(',', '', $t);
    } elseif ($k !== false) {
        if (substr_count($t, ',') > 1) return null; // ambigu
        $t = str_replace(',', '.', $t);
    }
    return is_numeric($t) ? (float)$t : null;
}

/**
 * Baca CSV unggahan menjadi [kode => nilai]. Pemisah , atau ;  Desimal , atau .
 * Nilai = satu-satunya sel angka setelah kolom Kode (kolom kosong di ujung diabaikan).
 * Baris dengan dua sel angka atau sel nilai non-angka dilaporkan sebagai galat, bukan ditebak.
 */
function pakarBacaCsv(string $isi): array {
    $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi);
    $hasil = []; $galat = [];
    foreach (preg_split('/\r\n|\r|\n/', $isi) as $no => $baris) {
        if (trim($baris) === '') continue;
        $sep = substr_count($baris, ';') > substr_count($baris, ',') ? ';' : ',';
        $kol = str_getcsv($baris, $sep);
        $k = trim($kol[0] ?? '');
        if ($k === '' || strcasecmp($k, 'kode') === 0) continue;
        $angka = [];
        for ($c = 1; $c < count($kol); $c++) {
            $v = pakarAngka($kol[$c]);
            if ($v !== null) $angka[] = $v;
        }
        if (count($angka) > 1) { $galat[] = 'Baris ' . ($no + 1) . " ($k): lebih dari satu angka. Jika memakai koma desimal dengan pemisah koma, beri tanda kutip pada angka atau simpan dengan pemisah titik koma."; continue; }
        if (count($angka) === 1) { $hasil[$k] = $angka[0]; continue; }
        $nilaiMentah = isset($kol[2]) ? trim($kol[2]) : '';
        if ($nilaiMentah !== '') $galat[] = 'Baris ' . ($no + 1) . " ($k): nilai \"$nilaiMentah\" bukan angka (format persen tidak didukung).";
        // sel nilai kosong = butir belum diisi
    }
    return ['nilai' => $hasil, 'galat' => $galat];
}

/** Bandingkan nilai Excel dengan web. Mengembalikan baris per butir. */
function pakarBandingkan(array $butir, array $excel): array {
    $baris = [];
    foreach ($butir as $kode => [$ket, $web]) {
        [$kel, $k] = explode('|', $kode, 2);
        if (!array_key_exists($k, $excel)) {
            $baris[] = ['kel' => $kel, 'kode' => $k, 'ket' => $ket, 'excel' => null, 'web' => $web, 'selisih' => null, 'status' => 'belum'];
            continue;
        }
        $sel = abs($excel[$k] - $web);
        $baris[] = ['kel' => $kel, 'kode' => $k, 'ket' => $ket, 'excel' => $excel[$k], 'web' => $web, 'selisih' => $sel,
                    'status' => $sel <= PAKAR_TOLERANSI ? 'sesuai' : 'tidak'];
    }
    return $baris;
}

/** Susun detail tahap TOPSIS pakar (untuk tampilan dan penyimpanan). */
function pakarDetail(array $p): array {
    $t = $p['topsis'];
    return ['kode_kriteria' => $p['kode'], 'tipe' => $p['tipe'], 'bobot' => $p['bobot'], 'kode_alt' => $t['kode_alt'],
            'nama_alt' => $t['nama_alt'], 'X' => $t['X'], 'pembagi' => $t['pembagi'], 'R' => $t['R'], 'V' => $t['V'],
            'Aplus' => $t['Aplus'], 'Aminus' => $t['Aminus'], 'Dplus' => $t['Dplus'], 'Dminus' => $t['Dminus'],
            'CC' => $t['CC'], 'rank' => $t['rank']];
}

// ─────────────────────────────────────────
// PENYIMPANAN HASIL PAKAR (versi + riwayat)
// ─────────────────────────────────────────
function pakarPastikanTabel(): void {
    static $sudah = false;
    if ($sudah) return;
    $c = db();
    $c->query("CREATE TABLE IF NOT EXISTS hasil_ahp_pakar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        versi INT NOT NULL,
        dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        jumlah_responden INT NOT NULL,
        responden_json TEXT NOT NULL,
        bobot_json TEXT NOT NULL,
        geo_json TEXT NOT NULL,
        cr_maks DECIMAL(12,8) DEFAULT NULL,
        catatan VARCHAR(200) DEFAULT NULL,
        UNIQUE KEY uq_versi (versi)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $c->query("CREATE TABLE IF NOT EXISTS hasil_topsis_pakar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hasil_ahp_id INT NOT NULL,
        dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        jumlah_alternatif INT NOT NULL,
        ranking_json MEDIUMTEXT NOT NULL,
        detail_json MEDIUMTEXT NOT NULL,
        FOREIGN KEY (hasil_ahp_id) REFERENCES hasil_ahp_pakar(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $sudah = true;
}

/** Simpan hasil pakar saat ini sebagai versi baru. Mengembalikan nomor versi atau null. */
function pakarSimpan(string $catatan = ''): ?int {
    pakarPastikanTabel();
    $p = pakarHitung();
    if ($p['K'] === 0 || !$p['topsis']) return null;
    $t = $p['topsis'];
    $c = db();
    $versi = (int)(fetchOne("SELECT COALESCE(MAX(versi),0)+1 AS v FROM hasil_ahp_pakar")['v'] ?? 1);
    $resp = array_map(fn($r) => ['no' => $r['no'], 'id' => $r['id'], 'nama' => $r['nama'], 'bobot' => $r['bobot'],
        'lambda_max' => $r['lambda_max'], 'CI' => $r['CI'], 'CR' => $r['CR'], 'ikut' => $r['konsisten']], $p['responden']);
    $crMaks = 0.0; foreach ($p['responden'] as $r) if ($r['konsisten']) $crMaks = max($crMaks, $r['CR']);
    $s = $c->prepare("INSERT INTO hasil_ahp_pakar (versi, jumlah_responden, responden_json, bobot_json, geo_json, cr_maks, catatan) VALUES (?,?,?,?,?,?,?)");
    $rj = json_encode($resp); $bj = json_encode($p['bobot']); $gj = json_encode($p['geo']); $K = $p['K'];
    $s->bind_param('iisssds', $versi, $K, $rj, $bj, $gj, $crMaks, $catatan);
    $s->execute();
    $idAhp = $c->insert_id;

    $rank = [];
    foreach ($t['kode_alt'] as $i => $ka) {
        $rank[] = ['kode' => $ka, 'nama' => $t['nama_alt'][$i], 'rank' => $t['rank'][$i],
                   'CC' => $t['CC'][$i], 'Dplus' => $t['Dplus'][$i], 'Dminus' => $t['Dminus'][$i]];
    }
    usort($rank, fn($a, $b) => $a['rank'] <=> $b['rank']);
    $detail = pakarDetail($p);
    $s2 = $c->prepare("INSERT INTO hasil_topsis_pakar (hasil_ahp_id, jumlah_alternatif, ranking_json, detail_json) VALUES (?,?,?,?)");
    $m = count($rank); $rkj = json_encode($rank); $dj = json_encode($detail);
    $s2->bind_param('iiss', $idAhp, $m, $rkj, $dj);
    $s2->execute();
    return $versi;
}
