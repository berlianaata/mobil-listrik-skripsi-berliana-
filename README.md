# SPK-EV — Sistem Pendukung Keputusan Pemilihan Kendaraan Listrik
## Menggunakan Metode AHP & TOPSIS Berbasis Web

---

## 📋 Deskripsi

Sistem ini merupakan aplikasi web berbasis **PHP & MySQL** yang mengimplementasikan
dua metode **Sistem Pendukung Keputusan (SPK)**:

- **AHP** (Analytic Hierarchy Process) — untuk menentukan bobot kriteria
- **TOPSIS** (Technique for Order Preference by Similarity to Ideal Solution) — untuk merangking alternatif

**Judul Lengkap:**
> Sistem Pendukung Keputusan Pemilihan Kendaraan Listrik (Electric Vehicle/EV) Terbaik
> Menggunakan Metode Analytic Hierarchy Process (AHP) dan Technique for Order Preference
> by Similarity to Ideal Solution (TOPSIS) Berbasis Web

---

## 📁 Struktur Folder

```
spk_ev_php/
│
├── config/
│   ├── database.php          # Konfigurasi koneksi MySQL
│   └── init.sql              # SQL inisialisasi database & data awal
│
├── includes/
│   ├── functions.php         # Helper functions utama
│   ├── header.php            # Header HTML global
│   ├── navbar.php            # Sidebar navigasi
│   └── footer.php            # Footer HTML global
│
├── assets/
│   ├── css/
│   │   └── style.css         # CSS utama (modern, responsive)
│   ├── js/
│   │   └── script.js         # JavaScript utama
│   └── img/                  # Folder gambar/logo
│
├── classes/
│   ├── AHP.php               # Class implementasi metode AHP
│   └── TOPSIS.php            # Class implementasi metode TOPSIS
│
├── auth/
│   ├── register.php          # Halaman pendaftaran akun
│   ├── login.php             # Halaman login
│   └── logout.php            # Proses logout
│
├── pages/
│   ├── dashboard.php         # Dashboard utama pengguna
│   ├── katalog.php           # Katalog semua EV dengan filter & pagination
│   ├── preferensi.php        # Set preferensi/filter kendaraan
│   ├── ahp.php               # Input matriks AHP & perhitungan lengkap
│   ├── topsis.php            # Perhitungan TOPSIS 7 langkah detail
│   ├── hasil.php             # Hasil rekomendasi EV terbaik
│   ├── history.php           # Riwayat semua perhitungan
│   ├── profil.php            # Profil & ubah kata sandi
│   └── tentang.php           # Penjelasan metode AHP & TOPSIS
│
├── index.php                 # Landing page utama
└── README.md                 # Dokumentasi ini
```

---

## ⚙️ Cara Instalasi (Localhost)

### Prasyarat
- **PHP** >= 7.4 (disarankan 8.0+)
- **MySQL** >= 5.7 atau **MariaDB** >= 10.3
- **XAMPP / WAMP / Laragon** (atau server lokal sejenis)

### Langkah Instalasi

**1. Salin folder ke htdocs**
```
Salin folder spk_ev_php/ ke:
  XAMPP : C:/xampp/htdocs/spk_ev_php/
  WAMP  : C:/wamp64/www/spk_ev_php/
  Laragon: C:/laragon/www/spk_ev_php/
```

**2. Buat database di phpMyAdmin**
```
- Buka browser: http://localhost/phpmyadmin
- Klik "New" → buat database baru: spk_ev_db
- Pilih database spk_ev_db
- Klik tab "Import" → pilih file config/init.sql → klik "Go"
```

**3. Sesuaikan konfigurasi database**
```php
// Edit file: config/database.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Username MySQL Anda
define('DB_PASS', '');           // Password MySQL Anda (kosong jika default XAMPP)
define('DB_NAME', 'spk_ev_db');
define('APP_URL', 'http://localhost/spk_ev_php');
```

**4. Akses aplikasi**
```
Buka browser: http://localhost/spk_ev_php
```

---

## 🔑 Akun Default

| Role  | Email              | Password   |
|-------|--------------------|------------|
| Admin | admin@spkev.com    | admin123   |

> **Pengguna umum** dapat mendaftar melalui halaman Register.

---

## 🎯 Kriteria Analisis

| Kode | Nama Kriteria           | Tipe    | Satuan  | Bobot Default |
|------|-------------------------|---------|---------|---------------|
| C1   | Jangkauan (Range)       | Benefit | km      | 34.29%        |
| C2   | Efisiensi Energi        | Cost    | Wh/km   | 22.86%        |
| C3   | Daya Pengisian Cepat    | Benefit | kW      | 17.14%        |
| C4   | Akselerasi 0-100 km/h   | Cost    | detik   | 17.14%        |
| C5   | Kapasitas Baterai       | Benefit | kWh     | 8.57%         |

---

## 📐 Alur Sistem SPK

```
[Pengguna Daftar/Login]
        ↓
[1. Set Preferensi]  → Filter: Segmen, Drivetrain, Body Type, Seats
        ↓
[2. Penilaian AHP]   → Matriks Perbandingan Berpasangan (Skala Saaty 1-9)
        ↓             → Normalisasi → Bobot Prioritas
                       → Uji Konsistensi: CR ≤ 0.1
        ↓
[3. Hitung TOPSIS]   → Langkah 1: Matriks Keputusan Awal
        ↓             → Langkah 2: Normalisasi Vektor
                       → Langkah 3: Matriks Terbobot (× bobot AHP)
                       → Langkah 4: Solusi Ideal A+ dan A−
                       → Langkah 5: Jarak Euclidean D+ dan D−
                       → Langkah 6: Nilai Preferensi CC
                       → Langkah 7: Perangkingan
        ↓
[4. Lihat Hasil]     → Rekomendasi EV Terbaik + Detail Perhitungan
```

---

## 📚 Referensi Ilmiah

1. Saaty, T. L. (1980). *The Analytic Hierarchy Process*. McGraw-Hill, New York.
2. Hwang, C. L., & Yoon, K. (1981). *Multiple Attribute Decision Making: Methods and Applications*. Springer-Verlag.
3. Saaty, T. L. (1990). How to make a decision: The Analytic Hierarchy Process. *European Journal of Operational Research*, 48(1), 9–26.
4. Chen, C. T. (2000). Extensions of the TOPSIS for group decision-making under fuzzy environment. *Fuzzy Sets and Systems*, 114(1), 1–9.
5. Kusumadewi, S., et al. (2006). *Fuzzy Multi-Attribute Decision Making (Fuzzy MADM)*. Graha Ilmu, Yogyakarta.
6. EV Database. (2024). Electric Vehicle Specifications. https://ev-database.org

---

## 🛠️ Teknologi

| Komponen    | Detail                        |
|-------------|-------------------------------|
| Backend     | PHP 8.x (OOP)                 |
| Database    | MySQL 5.7+ / MariaDB          |
| Frontend    | HTML5, CSS3, JavaScript ES6+  |
| Font        | Plus Jakarta Sans, Space Grotesk |
| Hosting     | Localhost (XAMPP/WAMP/Laragon)|
| Metode SPK  | AHP + TOPSIS                  |

---

## ✅ Fitur Lengkap

- [x] Registrasi & Login pengguna
- [x] Landing page informatif
- [x] Dashboard dengan statistik
- [x] Katalog EV dengan filter & pagination
- [x] Set preferensi kendaraan
- [x] Input matriks AHP (5×5)
- [x] Uji konsistensi AHP (CR ≤ 0.1)
- [x] Perhitungan TOPSIS 7 langkah
- [x] Tampilan detail setiap langkah
- [x] Perangkingan hasil akhir
- [x] Simpan riwayat perhitungan
- [x] Halaman detail riwayat
- [x] Profil & ubah password
- [x] Halaman tentang metode (landasan teori)
- [x] Responsive design (mobile-friendly)
- [x] Cetak laporan
