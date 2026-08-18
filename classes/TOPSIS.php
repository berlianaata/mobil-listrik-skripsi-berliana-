<?php
// ============================================================
// FILE: classes/TOPSIS.php
// FUNGSI: Implementasi lengkap Metode TOPSIS
//         (Technique for Order Preference by Similarity
//          to Ideal Solution)
// Referensi: Hwang, C.L. & Yoon, K. (1981).
//            Multiple Attribute Decision Making.
//            Berlin: Springer-Verlag.
// ============================================================

class TOPSIS {

    private array  $X;           // Matriks keputusan awal  [m × n]
    private array  $w;           // Bobot kriteria          [1 × n]
    private array  $tipe;        // 'benefit' / 'cost'      [1 × n]
    private array  $namaAlt;     // Nama alternatif         [1 × m]
    private array  $namaKrit;    // Nama kriteria           [1 × n]
    private int    $m;           // Jumlah alternatif
    private int    $n;           // Jumlah kriteria

    // Hasil perhitungan
    private array  $R;           // Matriks normalisasi
    private array  $V;           // Matriks terbobot
    private array  $Aplus;       // Solusi ideal positif  A+
    private array  $Aminus;      // Solusi ideal negatif  A−
    private array  $Dplus;       // Jarak ke A+
    private array  $Dminus;      // Jarak ke A−
    private array  $CC;          // Nilai preferensi (closeness coefficient)
    private array  $ranking;     // Hasil ranking final

    // ─────────────────────────────────────────
    // KONSTRUKTOR
    // ─────────────────────────────────────────

    /**
     * @param array $dataAlternatif  2D array [m][n] nilai numerik
     * @param array $bobot           1D array bobot [w1,w2,...,wn] (sum=1)
     * @param array $tipeKriteria    1D array ['benefit','cost',...]
     * @param array $namaAlternatif  1D array nama alternatif
     * @param array $namaKriteria    1D array nama/kode kriteria
     */
    public function __construct(
        array $dataAlternatif,
        array $bobot,
        array $tipeKriteria,
        array $namaAlternatif,
        array $namaKriteria
    ) {
        $this->X       = $dataAlternatif;
        $this->w       = $bobot;
        $this->tipe    = $tipeKriteria;
        $this->namaAlt = $namaAlternatif;
        $this->namaKrit= $namaKriteria;
        $this->m       = count($dataAlternatif);
        $this->n       = count($bobot);
    }

    // ─────────────────────────────────────────
    // PERHITUNGAN UTAMA TOPSIS
    // ─────────────────────────────────────────

    public function hitung(): array {
        $this->langkah1_normalisasi();
        $this->langkah2_pembobotan();
        $this->langkah3_solusiIdeal();
        $this->langkah4_jarak();
        $this->langkah5_nilaiPreferensi();
        $this->langkah6_ranking();

        return $this->getHasil();
    }

    // ─────────────────────────────────────────
    // LANGKAH-LANGKAH TOPSIS
    // ─────────────────────────────────────────

    /**
     * LANGKAH 1: Normalisasi Matriks Keputusan
     * r_ij = x_ij / √(Σ x_ij²)
     */
    private function langkah1_normalisasi(): void {
        // Hitung pembagi (panjang vektor tiap kolom)
        $pembagi = array_fill(0, $this->n, 0.0);
        for ($j = 0; $j < $this->n; $j++) {
            $sumSq = 0;
            for ($i = 0; $i < $this->m; $i++) {
                $sumSq += pow((float)($this->X[$i][$j] ?? 0), 2);
            }
            $pembagi[$j] = sqrt($sumSq);
            if ($pembagi[$j] == 0) $pembagi[$j] = 1e-10; // hindari div/0
        }

        // Normalisasi
        $this->R = [];
        for ($i = 0; $i < $this->m; $i++) {
            $this->R[$i] = [];
            for ($j = 0; $j < $this->n; $j++) {
                $this->R[$i][$j] = (float)($this->X[$i][$j] ?? 0) / $pembagi[$j];
            }
        }
    }

    /**
     * LANGKAH 2: Matriks Normalisasi Terbobot
     * v_ij = w_j × r_ij
     */
    private function langkah2_pembobotan(): void {
        $this->V = [];
        for ($i = 0; $i < $this->m; $i++) {
            $this->V[$i] = [];
            for ($j = 0; $j < $this->n; $j++) {
                $this->V[$i][$j] = $this->w[$j] * $this->R[$i][$j];
            }
        }
    }

    /**
     * LANGKAH 3: Solusi Ideal Positif (A+) dan Negatif (A−)
     * Benefit: A+ = max(v_ij), A− = min(v_ij)
     * Cost:    A+ = min(v_ij), A− = max(v_ij)
     */
    private function langkah3_solusiIdeal(): void {
        $this->Aplus  = [];
        $this->Aminus = [];

        for ($j = 0; $j < $this->n; $j++) {
            $kolom = array_column($this->V, $j);

            if ($this->tipe[$j] === 'benefit') {
                $this->Aplus[$j]  = max($kolom);
                $this->Aminus[$j] = min($kolom);
            } else { // cost
                $this->Aplus[$j]  = min($kolom);
                $this->Aminus[$j] = max($kolom);
            }
        }
    }

    /**
     * LANGKAH 4: Jarak Euclidean ke Solusi Ideal
     * D+_i = √Σ(v_ij − A+_j)²
     * D−_i = √Σ(v_ij − A−_j)²
     */
    private function langkah4_jarak(): void {
        $this->Dplus  = [];
        $this->Dminus = [];

        for ($i = 0; $i < $this->m; $i++) {
            $sumPlus  = 0;
            $sumMinus = 0;
            for ($j = 0; $j < $this->n; $j++) {
                $sumPlus  += pow($this->V[$i][$j] - $this->Aplus[$j],  2);
                $sumMinus += pow($this->V[$i][$j] - $this->Aminus[$j], 2);
            }
            $this->Dplus[$i]  = sqrt($sumPlus);
            $this->Dminus[$i] = sqrt($sumMinus);
        }
    }

    /**
     * LANGKAH 5: Nilai Preferensi Relatif (Closeness Coefficient)
     * CC_i = D−_i / (D+_i + D−_i)
     * Semakin besar CC_i → semakin mendekati solusi ideal positif
     */
    private function langkah5_nilaiPreferensi(): void {
        $this->CC = [];
        for ($i = 0; $i < $this->m; $i++) {
            $denom = $this->Dplus[$i] + $this->Dminus[$i];
            $this->CC[$i] = ($denom > 1e-15)
                ? $this->Dminus[$i] / $denom
                : 0.0;
        }
    }

    /**
     * LANGKAH 6: Perangkingan (descending CC)
     */
    private function langkah6_ranking(): void {
        // Buat array index dengan CC-nya
        $urutan = range(0, $this->m - 1);

        // Urutkan descending berdasarkan CC
        usort($urutan, function($a, $b) {
            return $this->CC[$b] <=> $this->CC[$a];
        });

        $this->ranking = [];
        foreach ($urutan as $rank => $idx) {
            $dataKrit = [];
            for ($j = 0; $j < $this->n; $j++) {
                $dataKrit[$this->namaKrit[$j]] = round((float)($this->X[$idx][$j] ?? 0), 4);
            }

            $this->ranking[] = [
                'rank'        => $rank + 1,
                'index'       => $idx,
                'nama'        => $this->namaAlt[$idx],
                'CC'          => round($this->CC[$idx], 6),
                'D_plus'      => round($this->Dplus[$idx], 6),
                'D_minus'     => round($this->Dminus[$idx], 6),
                'data_krit'   => $dataKrit,
                // Nilai V terbobot
                'V_values'    => array_map(fn($v) => round($v, 6), $this->V[$idx]),
                // Nilai R normalisasi
                'R_values'    => array_map(fn($v) => round($v, 6), $this->R[$idx]),
            ];
        }
    }

    // ─────────────────────────────────────────
    // GETTER HASIL
    // ─────────────────────────────────────────

    public function getHasil(): array {
        return [
            'm'              => $this->m,
            'n'              => $this->n,
            'nama_alt'       => $this->namaAlt,
            'nama_krit'      => $this->namaKrit,
            'tipe'           => $this->tipe,
            'bobot'          => $this->w,
            // Matriks
            'matriks_awal'   => $this->X,
            'matriks_normal' => $this->R,
            'matriks_bobot'  => $this->V,
            // Ideal
            'A_plus'         => $this->Aplus,
            'A_minus'        => $this->Aminus,
            // Jarak
            'D_plus'         => $this->Dplus,
            'D_minus'        => $this->Dminus,
            // Skor
            'CC'             => $this->CC,
            // Ranking
            'ranking'        => $this->ranking,
            'terbaik'        => $this->ranking[0] ?? null,
        ];
    }

    public function getRanking(): array  { return $this->ranking; }
    public function getCC(): array       { return $this->CC; }
    public function getAplus(): array    { return $this->Aplus; }
    public function getAminus(): array   { return $this->Aminus; }
    public function getDplus(): array    { return $this->Dplus; }
    public function getDminus(): array   { return $this->Dminus; }
    public function getMatriksNormal(): array { return $this->R; }
    public function getMatriksBobot(): array  { return $this->V; }

    // ─────────────────────────────────────────
    // DETAIL STEP BY STEP (untuk halaman penjelasan)
    // ─────────────────────────────────────────

    public function getDetailSteps(): array {
        return [
            [
                'no'         => 1,
                'judul'      => 'Matriks Keputusan Awal (X)',
                'rumus'      => 'X = [x_ij] dengan i=1..m (alternatif), j=1..n (kriteria)',
                'keterangan' => "m={$this->m} alternatif × n={$this->n} kriteria",
                'data'       => $this->X,
                'header'     => $this->namaKrit,
                'rows'       => $this->namaAlt,
            ],
            [
                'no'         => 2,
                'judul'      => 'Normalisasi Matriks (R)',
                'rumus'      => 'r_ij = x_ij / √(Σᵢ x_ij²)',
                'keterangan' => 'Normalisasi vektor agar skala berbeda dapat dibandingkan.',
                'data'       => $this->R,
                'header'     => $this->namaKrit,
                'rows'       => $this->namaAlt,
            ],
            [
                'no'         => 3,
                'judul'      => 'Matriks Normalisasi Terbobot (V)',
                'rumus'      => 'v_ij = w_j × r_ij',
                'keterangan' => 'Bobot dari AHP dikalikan dengan matriks normalisasi.',
                'data'       => $this->V,
                'header'     => $this->namaKrit,
                'rows'       => $this->namaAlt,
            ],
            [
                'no'         => 4,
                'judul'      => 'Solusi Ideal Positif (A⁺) dan Negatif (A⁻)',
                'rumus'      => "Benefit: A⁺_j=max(v_ij), A⁻_j=min(v_ij)\nCost:    A⁺_j=min(v_ij), A⁻_j=max(v_ij)",
                'keterangan' => 'A⁺ = kondisi terbaik yang mungkin. A⁻ = kondisi terburuk.',
                'A_plus'     => $this->Aplus,
                'A_minus'    => $this->Aminus,
                'header'     => $this->namaKrit,
                'tipe'       => $this->tipe,
            ],
            [
                'no'         => 5,
                'judul'      => 'Jarak Euclidean ke Solusi Ideal',
                'rumus'      => "D⁺_i = √Σⱼ(v_ij − A⁺_j)²\nD⁻_i = √Σⱼ(v_ij − A⁻_j)²",
                'keterangan' => 'Mengukur kedekatan setiap alternatif ke solusi ideal.',
                'D_plus'     => $this->Dplus,
                'D_minus'    => $this->Dminus,
                'rows'       => $this->namaAlt,
            ],
            [
                'no'         => 6,
                'judul'      => 'Nilai Preferensi Relatif (CC)',
                'rumus'      => 'CC_i = D⁻_i / (D⁺_i + D⁻_i)',
                'keterangan' => 'Nilai CC ∈ [0,1]. Semakin besar → semakin mendekati solusi ideal positif.',
                'CC'         => $this->CC,
                'rows'       => $this->namaAlt,
            ],
            [
                'no'         => 7,
                'judul'      => 'Perangkingan Alternatif',
                'rumus'      => 'Urutkan CC_i secara menurun (descending)',
                'keterangan' => 'Alternatif dengan CC terbesar adalah rekomendasi terbaik.',
                'ranking'    => $this->ranking,
            ],
        ];
    }
}
?>
