<?php
// ============================================================
// FILE: classes/AHP.php
// FUNGSI: Implementasi lengkap Metode AHP (Analytic Hierarchy Process)
// Referensi: Saaty, T.L. (1980). The Analytic Hierarchy Process.
//            New York: McGraw-Hill.
// ============================================================

class AHP {

    // Tabel Random Index (RI) Saaty
    const RI_TABLE = [
        1  => 0.00,
        2  => 0.00,
        3  => 0.58,
        4  => 0.90,
        5  => 1.12,
        6  => 1.24,
        7  => 1.32,
        8  => 1.41,
        9  => 1.45,
        10 => 1.49,
    ];

    // Skala Saaty 1–9
    const SKALA_SAATY = [
        1 => 'Sama pentingnya',
        2 => 'Di antara sama dan sedikit lebih penting',
        3 => 'Sedikit lebih penting',
        4 => 'Di antara sedikit dan lebih penting',
        5 => 'Lebih penting',
        6 => 'Di antara lebih dan sangat lebih penting',
        7 => 'Sangat lebih penting',
        8 => 'Di antara sangat lebih dan mutlak lebih penting',
        9 => 'Mutlak lebih penting',
    ];

    private array  $kriteria;      // nama/kode kriteria
    private int    $n;             // jumlah kriteria
    private array  $matriks;       // matriks perbandingan n×n (2D array)
    private array  $bobot;         // vektor bobot prioritas
    private array  $matriksNormal; // matriks normalisasi
    private array  $jumlahKolom;   // jumlah tiap kolom
    private float  $lambdaMax;
    private float  $CI;
    private float  $CR;
    private float  $RI;
    private bool   $konsisten;

    // ─────────────────────────────────────────
    // KONSTRUKTOR
    // ─────────────────────────────────────────

    public function __construct(array $kriteria) {
        $this->kriteria = array_values($kriteria);
        $this->n        = count($kriteria);

        // Inisialisasi matriks identitas (diagonal = 1)
        $this->matriks  = array_fill(0, $this->n,
                            array_fill(0, $this->n, 1.0));
    }

    // ─────────────────────────────────────────
    // INPUT MATRIKS
    // ─────────────────────────────────────────

    /**
     * Set nilai perbandingan berpasangan (segitiga atas saja)
     * Diagonal otomatis = 1, segitiga bawah = 1/nilai
     *
     * @param array $input  [['i'=>0,'j'=>1,'nilai'=>3], ...]
     *                      atau matriks 2D penuh $input[i][j]
     */
    public function setMatriks(array $input): void {
        // Cek apakah input adalah matriks 2D atau array pasangan
        if (isset($input[0]) && is_array($input[0]) && isset($input[0][0])) {
            // Sudah bentuk matriks 2D
            for ($i = 0; $i < $this->n; $i++) {
                for ($j = 0; $j < $this->n; $j++) {
                    $this->matriks[$i][$j] = (float)($input[$i][$j] ?? 1.0);
                }
            }
        } else {
            // Array pasangan [{i, j, nilai}]
            foreach ($input as $p) {
                $i    = (int)$p['i'];
                $j    = (int)$p['j'];
                $val  = (float)($p['nilai'] ?? 1.0);
                if ($val <= 0) $val = 1.0 / abs($val ?: 1);

                $this->matriks[$i][$j] = $val;
                $this->matriks[$j][$i] = 1.0 / $val;
            }
        }

        // Pastikan diagonal = 1
        for ($i = 0; $i < $this->n; $i++) {
            $this->matriks[$i][$i] = 1.0;
        }
    }

    /**
     * Set dari form POST: matriks[$i][$j] = nilai string
     */
    public function setMatriksFromPost(array $formData): void {
        for ($i = 0; $i < $this->n; $i++) {
            for ($j = 0; $j < $this->n; $j++) {
                if ($i === $j) {
                    $this->matriks[$i][$j] = 1.0;
                } elseif ($j > $i) {
                    $raw = $formData[$i][$j] ?? 1;
                    $this->matriks[$i][$j] = max(1/9, min(9.0, (float)$raw));
                    $this->matriks[$j][$i] = 1.0 / $this->matriks[$i][$j];
                }
            }
        }
    }

    // ─────────────────────────────────────────
    // PERHITUNGAN UTAMA AHP
    // ─────────────────────────────────────────

    /**
     * Hitung bobot, lambda max, CI, CR
     * @return array hasil lengkap
     */
    public function hitung(): array {
        // ── LANGKAH 1: Jumlahkan setiap kolom ──
        $this->jumlahKolom = array_fill(0, $this->n, 0.0);
        for ($j = 0; $j < $this->n; $j++) {
            for ($i = 0; $i < $this->n; $i++) {
                $this->jumlahKolom[$j] += $this->matriks[$i][$j];
            }
        }

        // ── LANGKAH 2: Normalisasi matriks ──
        $this->matriksNormal = [];
        for ($i = 0; $i < $this->n; $i++) {
            $this->matriksNormal[$i] = [];
            for ($j = 0; $j < $this->n; $j++) {
                $this->matriksNormal[$i][$j] =
                    ($this->jumlahKolom[$j] != 0)
                    ? $this->matriks[$i][$j] / $this->jumlahKolom[$j]
                    : 0.0;
            }
        }

        // ── LANGKAH 3: Vektor bobot (rata-rata tiap baris) ──
        $this->bobot = [];
        for ($i = 0; $i < $this->n; $i++) {
            $sum = 0;
            for ($j = 0; $j < $this->n; $j++) {
                $sum += $this->matriksNormal[$i][$j];
            }
            $this->bobot[$i] = $sum / $this->n;
        }

        // ── LANGKAH 4: Hitung λ_max ──
        // AX = jumlah kolom × bobot tiap kriteria
        $ax = array_fill(0, $this->n, 0.0);
        for ($i = 0; $i < $this->n; $i++) {
            for ($j = 0; $j < $this->n; $j++) {
                $ax[$i] += $this->matriks[$i][$j] * $this->bobot[$j];
            }
        }

        $lambdaSum = 0;
        for ($i = 0; $i < $this->n; $i++) {
            $lambdaSum += ($this->bobot[$i] != 0)
                ? $ax[$i] / $this->bobot[$i]
                : 0;
        }
        $this->lambdaMax = $lambdaSum / $this->n;

        // ── LANGKAH 5: Consistency Index (CI) ──
        $this->CI = ($this->n > 1)
            ? ($this->lambdaMax - $this->n) / ($this->n - 1)
            : 0;

        // ── LANGKAH 6: Random Index (RI) ──
        $this->RI = self::RI_TABLE[$this->n] ?? 1.49;

        // ── LANGKAH 7: Consistency Ratio (CR) ──
        $this->CR        = ($this->RI != 0) ? $this->CI / $this->RI : 0;
        $this->konsisten = $this->CR <= 0.1;

        return $this->getHasil();
    }

    // ─────────────────────────────────────────
    // GETTER HASIL
    // ─────────────────────────────────────────

    public function getHasil(): array {
        return [
            'n'               => $this->n,
            'kriteria'        => $this->kriteria,
            'matriks'         => $this->matriks,
            'jumlah_kolom'    => $this->jumlahKolom,
            'matriks_normal'  => $this->matriksNormal,
            'bobot'           => $this->bobot,
            'lambda_max'      => round($this->lambdaMax, 6),
            'CI'              => round($this->CI, 6),
            'RI'              => $this->RI,
            'CR'              => round($this->CR, 6),
            'konsisten'       => $this->konsisten,
            'interpretasi'    => $this->konsisten
                ? '✅ Konsisten (CR = ' . round($this->CR,4) . ' ≤ 0.1)'
                : '❌ TIDAK Konsisten (CR = ' . round($this->CR,4) . ' > 0.1). Mohon revisi penilaian.',
        ];
    }

    public function getBobot(): array  { return $this->bobot ?? []; }
    public function getLambdaMax(): float { return $this->lambdaMax ?? 0; }
    public function getCI(): float     { return $this->CI ?? 0; }
    public function getCR(): float     { return $this->CR ?? 0; }
    public function getRI(): float     { return $this->RI ?? 0; }
    public function isKonsisten(): bool { return $this->konsisten ?? false; }
    public function getMatriks(): array { return $this->matriks; }
    public function getMatriksNormal(): array { return $this->matriksNormal ?? []; }
    public function getJumlahKolom(): array   { return $this->jumlahKolom ?? []; }

    // ─────────────────────────────────────────
    // DETAIL STEP BY STEP (untuk halaman penjelasan)
    // ─────────────────────────────────────────

    public function getDetailSteps(): array {
        return [
            [
                'no'          => 1,
                'judul'       => 'Matriks Perbandingan Berpasangan (A)',
                'rumus'       => 'a_ij = tingkat kepentingan kriteria-i terhadap kriteria-j (skala Saaty 1–9)',
                'keterangan'  => 'Matriks bujursangkar ordo ' . $this->n . '×' . $this->n .
                                 '. Nilai diagonal selalu = 1. Jika a_ij = x maka a_ji = 1/x.',
                'data'        => $this->matriks,
            ],
            [
                'no'          => 2,
                'judul'       => 'Penjumlahan Setiap Kolom',
                'rumus'       => 'Jumlah_j = Σ a_ij  (untuk setiap kolom j)',
                'keterangan'  => 'Setiap kolom dijumlahkan untuk proses normalisasi.',
                'data'        => $this->jumlahKolom,
            ],
            [
                'no'          => 3,
                'judul'       => 'Matriks Normalisasi',
                'rumus'       => 'r_ij = a_ij / Jumlah_j',
                'keterangan'  => 'Setiap elemen dibagi dengan jumlah kolomnya.',
                'data'        => $this->matriksNormal,
            ],
            [
                'no'          => 4,
                'judul'       => 'Vektor Bobot Prioritas (w)',
                'rumus'       => 'w_i = (Σ r_ij) / n',
                'keterangan'  => 'Rata-rata tiap baris dari matriks normalisasi. Jumlah semua bobot = 1.',
                'data'        => $this->bobot,
            ],
            [
                'no'          => 5,
                'judul'       => 'Uji Konsistensi',
                'rumus'       => "λ_max = rata-rata(AX / w)\nCI = (λ_max − n) / (n − 1)\nCR = CI / RI",
                'keterangan'  => "n = {$this->n} | RI = {$this->RI} | λ_max = " . round($this->lambdaMax,4) .
                                 " | CI = " . round($this->CI,4) . " | CR = " . round($this->CR,4) .
                                 "\n" . ($this->konsisten ? '✅ CR ≤ 0.1 → Konsisten' : '❌ CR > 0.1 → TIDAK Konsisten'),
                'data'        => [],
            ],
        ];
    }

    // ─────────────────────────────────────────
    // GENERATE MATRIKS DEFAULT (untuk testing)
    // ─────────────────────────────────────────

    public static function generateDefaultMatrix(int $n): array {
        // Matriks default yang konsisten untuk n kriteria
        $defaults = [
            3 => [[1,3,5],[1/3,1,3],[1/5,1/3,1]],
            5 => [
                [1,   2,   3,   4,   5  ],
                [1/2, 1,   2,   3,   4  ],
                [1/3, 1/2, 1,   2,   3  ],
                [1/4, 1/3, 1/2, 1,   2  ],
                [1/5, 1/4, 1/3, 1/2, 1  ],
            ],
        ];
        if (isset($defaults[$n])) return $defaults[$n];

        // Buat identitas jika tidak ada default
        $m = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $m[$i][$j] = ($i === $j) ? 1.0 : ($i < $j ? 2.0 : 0.5);
            }
        }
        return $m;
    }
}
?>
