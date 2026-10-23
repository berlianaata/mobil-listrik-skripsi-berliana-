-- ============================================================
-- FILE: config/init.sql
-- FUNGSI: Inisialisasi database SPK Mobil Listrik (AHP + TOPSIS)
-- Data    : 43 model BEV yang dipasarkan di Indonesia
--           (40 perorangan, 3 kantor/niaga)
-- Sumber  : Kaggle arsan5623 - Indonesia Electric Vehicle (EV) Dataset 2026
--           (bersumber dari Oto.com, harga OTR per April 2026)
-- Penyaringan dari 60 model: 12 model dikeluarkan karena harga belum
--           diumumkan resmi, 5 model karena tidak ada data pengisian cepat DC.
-- Jenis bodi dan foto: dari halaman Oto.com tiap model (bukan dari dataset Kaggle).
-- Nilai per model: rata-rata nilai min dan maks (kursi = nilai maks).
--           Daya motor: hp dikonversi ke kW (x 0,7457).
-- Cara pakai: Import file ini di phpMyAdmin
--             atau jalankan: mysql -u root -p < init.sql
-- ============================================================

-- Buat database
CREATE DATABASE IF NOT EXISTS spk_ev_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE spk_ev_db;

-- ============================================================
-- TABEL: users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nama        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('user','admin') DEFAULT 'user',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABEL: kendaraan_ev
-- Satu baris = satu model; nilai C1-C6 sudah berupa satu nilai
-- ============================================================
CREATE TABLE IF NOT EXISTS kendaraan_ev (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    brand                   VARCHAR(30)  NOT NULL,
    model                   VARCHAR(60)  NOT NULL,
    tipe_penggunaan         ENUM('pribadi','kantor') NOT NULL DEFAULT 'pribadi',
    harga_rp_juta           DECIMAL(10,2) NOT NULL,
    range_km                DECIMAL(6,1) NOT NULL,
    battery_capacity_kwh    DECIMAL(6,2) NOT NULL,
    daya_kw                 DECIMAL(6,1) NOT NULL,
    seats                   INT          NOT NULL,
    waktu_isi_menit         DECIMAL(5,1) NOT NULL,
    tipe_bodi               VARCHAR(20)  DEFAULT NULL,
    foto_url                VARCHAR(255) DEFAULT NULL,
    source_url              VARCHAR(150) DEFAULT NULL,
    status                  ENUM('aktif','nonaktif') DEFAULT 'aktif',
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_brand_model (brand, model),
    INDEX idx_tipe        (tipe_penggunaan),
    INDEX idx_bodi        (tipe_bodi),
    INDEX idx_harga       (harga_rp_juta),
    INDEX idx_status      (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABEL: kriteria
-- ============================================================
CREATE TABLE IF NOT EXISTS kriteria (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    kode            VARCHAR(10)  UNIQUE NOT NULL,
    nama            VARCHAR(100) NOT NULL,
    kolom_db        VARCHAR(60)  NOT NULL,
    tipe            ENUM('benefit','cost') NOT NULL DEFAULT 'benefit',
    bobot_default   DECIMAL(6,4) DEFAULT 0.2000,
    satuan          VARCHAR(20),
    deskripsi       TEXT,
    aktif           TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABEL: matriks_ahp
-- Menyimpan nilai perbandingan berpasangan per user
-- ============================================================
CREATE TABLE IF NOT EXISTS matriks_ahp (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    kriteria_i      VARCHAR(10) NOT NULL,
    kriteria_j      VARCHAR(10) NOT NULL,
    nilai           DECIMAL(10,6) NOT NULL DEFAULT 1.000000,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_pair (user_id, kriteria_i, kriteria_j)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABEL: bobot_ahp
-- Menyimpan hasil bobot prioritas AHP per user
-- ============================================================
CREATE TABLE IF NOT EXISTS bobot_ahp (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    kriteria_id     INT NOT NULL,
    bobot           DECIMAL(10,8) NOT NULL,
    lambda_max      DECIMAL(12,8) DEFAULT NULL,
    ci              DECIMAL(12,8) DEFAULT NULL,
    cr              DECIMAL(12,8) DEFAULT NULL,
    konsisten       TINYINT(1) DEFAULT NULL,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (kriteria_id)REFERENCES kriteria(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_kriteria (user_id, kriteria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABEL: preferensi_user
-- Menyimpan pilihan kebutuhan (perorangan/kantor) tiap user
-- ============================================================
CREATE TABLE IF NOT EXISTS preferensi_user (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL UNIQUE,
    filter_tipe     VARCHAR(10)  DEFAULT NULL,
    filter_bodi     VARCHAR(20)  DEFAULT NULL,
    filter_kursi_min INT         DEFAULT NULL,
    filter_anggaran_max DECIMAL(10,2) DEFAULT NULL,
    jumlah_alt      INT          DEFAULT 50,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABEL: history_perhitungan
-- Menyimpan setiap sesi perhitungan AHP-TOPSIS
-- ============================================================
CREATE TABLE IF NOT EXISTS history_perhitungan (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    nama_sesi       VARCHAR(100),
    filter_json     TEXT,
    bobot_json      TEXT,
    ahp_json        TEXT,
    topsis_json     TEXT,
    ranking_json    TEXT,
    cr_value        DECIMAL(10,8) DEFAULT NULL,
    konsisten       TINYINT(1)    DEFAULT NULL,
    jumlah_alt      INT           DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id  (user_id),
    INDEX idx_created  (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DATA: Kriteria Evaluasi (5 Kriteria AHP-TOPSIS)
-- Bobot default sama rata; bobot sebenarnya dihitung dari matriks AHP
-- Jumlah kursi dan anggaran BUKAN kriteria, melainkan filter alternatif
-- ============================================================
INSERT IGNORE INTO kriteria (kode, nama, kolom_db, tipe, bobot_default, satuan, deskripsi) VALUES
('C1', 'Harga',
 'harga_rp_juta', 'cost', 0.2000, 'Rp juta',
 'Harga on the road (OTR) dalam rupiah. Semakin kecil semakin baik (cost). Kolom DB: harga_rp_juta'),
('C2', 'Jangkauan (Range)',
 'range_km', 'benefit', 0.2000, 'km',
 'Jarak tempuh dengan baterai penuh. Semakin besar semakin baik (benefit). Kolom DB: range_km'),
('C3', 'Kapasitas Baterai',
 'battery_capacity_kwh', 'benefit', 0.2000, 'kWh',
 'Total energi yang dapat disimpan baterai. Semakin besar semakin baik (benefit). Kolom DB: battery_capacity_kwh'),
('C4', 'Daya Motor',
 'daya_kw', 'benefit', 0.2000, 'kW',
 'Daya keluaran motor penggerak sebagai ukuran performa (data hp dikonversi ke kW, 1 hp = 0,7457 kW). Semakin besar semakin baik (benefit). Kolom DB: daya_kw'),
('C5', 'Waktu Pengisian Cepat DC',
 'waktu_isi_menit', 'cost', 0.2000, 'menit',
 'Lama pengisian cepat DC sesuai data. Semakin singkat semakin baik (cost). Kolom DB: waktu_isi_menit');

-- ============================================================
-- DATA: Akun Admin Default
-- Email   : admin@spkev.com
-- Password: admin123
-- Hash    : SHA256(password + 'spk_ev_salt_2024')
-- ============================================================
INSERT IGNORE INTO users (nama, email, password, role) VALUES
('Administrator', 'admin@spkev.com', '$2y$10$pAeHMOtOOfQKAO3tnKEZz.laD98NKBBE7VLhma5JGJYLWfchKyk22', 'admin');

-- ============================================================
-- DATA: Kendaraan Listrik (43 model, Oto.com via Kaggle arsan5623)
-- ============================================================
INSERT IGNORE INTO kendaraan_ev (brand, model, tipe_penggunaan, harga_rp_juta, range_km, battery_capacity_kwh, daya_kw, seats, waktu_isi_menit, tipe_bodi, foto_url, source_url) VALUES
('VinFast','VF 5','pribadi',323.17,260,29.6,70.1,5,30,'Hatchback','https://imgcdn.oto.com/large/gallery/exterior/180/2888/vinfast-vf-5-front-angle-low-view-464813.jpg','https://www.oto.com/mobil-baru/vinfast/vf-5'),
('MG','4 EV','pribadi',382,425,57.5,137.6,5,35,'SUV','https://imgcdn.oto.com/large/gallery/exterior/139/2751/mg-4-ev-front-angle-low-view-153361.jpg','https://www.oto.com/mobil-baru/mg/4-ev'),
('BYD','M6','pribadi',408,475,63.6,114.8,7,40,'MPV','https://imgcdn.oto.com/large/gallery/exterior/177/3039/byd-m6-front-angle-low-view-779791.jpg','https://www.oto.com/mobil-baru/byd/m6'),
('Wuling','Binguo EV','pribadi',305.5,371.5,34.9,50,5,35,'Hatchback','https://imgcdn.oto.com/large/gallery/exterior/110/2845/wuling-binguo-ev-front-angle-low-view-496719.jpg','https://www.oto.com/mobil-baru/wuling/binguo-ev'),
('VinFast','VF e34','pribadi',411.86,318,41.9,109.6,5,27,'SUV','https://imgcdn.oto.com/large/gallery/exterior/180/2886/vinfast-vf-e34-front-angle-low-view-306678.jpg','https://www.oto.com/mobil-baru/vinfast/vf-e34'),
('Wuling','Cloud EV','pribadi',429,460,50.6,99.9,5,30,'Crossover','https://imgcdn.oto.com/large/gallery/exterior/110/2899/wuling-cloud-front-angle-low-view-568299.jpg','https://www.oto.com/mobil-baru/wuling/cloud'),
('BYD','Seal','pribadi',694.5,615,82.56,314.3,5,37,'Sedan','https://imgcdn.oto.com/large/gallery/exterior/177/2882/byd-seal-front-angle-low-view-332440.jpg','https://www.oto.com/mobil-baru/byd/seal'),
('BYD','Dolphin','pribadi',399,450,52.69,111.5,5,30,'Hatchback','https://imgcdn.oto.com/large/gallery/exterior/177/2883/byd-dolphin-front-angle-low-view-155793.jpg','https://www.oto.com/mobil-baru/byd/dolphin'),
('CHERY','E5','pribadi',399.9,430,61,155.1,5,28,'SUV','https://imgcdn.oto.com/large/gallery/exterior/153/2792/chery-omoda-e5-front-angle-low-view-145192.jpg','https://www.oto.com/mobil-baru/chery/e5'),
('BYD','Atto 3','pribadi',455,445,55.2,152.1,5,45,'SUV','https://imgcdn.oto.com/large/gallery/exterior/177/2866/byd-atto-3-front-angle-low-view-588776.jpg','https://www.oto.com/mobil-baru/byd/atto-3'),
('Neta','V-II','pribadi',299,401,40.7,70.1,5,30,'SUV','https://imgcdn.oto.com/large/gallery/exterior/168/2956/neta-v-ii-front-angle-low-view-330746.jpg','https://www.oto.com/mobil-baru/neta/v-ii'),
('ZEEKR','7X','pribadi',1100,692.5,100,475,5,15,'SUV','https://imgcdn.oto.com/large/gallery/exterior/195/3334/zeekr-7x-front-angle-low-view-232386.jpg','https://www.oto.com/mobil-baru/zeekr/7x'),
('Suzuki','e-Vitara','pribadi',756.5,400,61,128.3,5,60,'SUV','https://imgcdn.oto.com/large/gallery/exterior/37/3281/suzuki-e-vitara-front-angle-low-view-916073.jpg','https://www.oto.com/mobil-baru/suzuki/e-vitara'),
('MG','S5 EV','pribadi',394.9,420,49,125.3,5,35,'SUV','https://imgcdn.oto.com/large/gallery/exterior/139/3323/mg-s5-ev-front-angle-low-view-545837.jpg','https://www.oto.com/mobil-baru/mg/s5-ev'),
('Mercedes Benz','G-Class Electric','pribadi',5900,473,116,437.7,5,32,'SUV','https://imgcdn.oto.com/large/gallery/exterior/25/3205/mercedes-benz-g-class-electric-front-angle-low-view-753040.jpg','https://www.oto.com/mobil-baru/mercedes-benz/g-class-electric'),
('BYD','Denza D9','pribadi',950,600,103.36,229.7,7,45,'MPV','https://imgcdn.oto.com/large/gallery/exterior/177/2910/byd-denza-d9-front-angle-low-view-521393.jpg','https://www.oto.com/mobil-baru/byd/denza-d9'),
('Hyundai','Ioniq 5','pribadi',871.3,432.5,65.3,142.4,5,18,'Hatchback','https://imgcdn.oto.com/large/gallery/exterior/15/2605/hyundai-ioniq-5-front-angle-low-view-395881.jpg','https://www.oto.com/mobil-baru/hyundai/ioniq-5'),
('MG','ZS EV','pribadi',396.75,403,50.3,132,5,30,'SUV','https://imgcdn.oto.com/large/gallery/exterior/139/2485/mg-zs-ev-front-angle-low-view-234118.jpg','https://www.oto.com/mobil-baru/mg/zs-ev'),
('Hyundai','Kona Electric','pribadi',627.45,394.5,57.45,137.2,5,43,'SUV','https://imgcdn.oto.com/large/gallery/exterior/15/2962/hyundai-kona-electric-front-angle-low-view-965907.jpg','https://www.oto.com/mobil-baru/hyundai/kona-electric'),
('Hyundai','Ioniq 6','pribadi',1237,566.5,77.4,240.1,5,18,'Sedan','https://imgcdn.oto.com/large/gallery/exterior/15/2802/hyundai-ioniq-6-front-angle-low-view-590398.jpg','https://www.oto.com/mobil-baru/hyundai/ioniq-6'),
('Citroen','E-C3','pribadi',380.5,320,29.2,41.8,5,57,'Crossover','https://imgcdn.oto.com/large/gallery/exterior/158/2808/citroen-e-c3-front-angle-low-view-393917.jpg','https://www.oto.com/mobil-baru/citroen/e-c3'),
('GAC','Aion Y Plus','pribadi',449,450,56.9,149.9,5,45,'Crossover','https://imgcdn.oto.com/large/gallery/exterior/183/2963/gac-aion-y-plus-front-angle-low-view-694983.jpg','https://www.oto.com/mobil-baru/gac/aion-y-plus'),
('Volkswagen','ID Buzz','pribadi',1522.5,440,77,180.8,6,30,'Van','https://imgcdn.oto.com/large/gallery/exterior/39/3053/volkswagen-id-buzz-front-angle-low-view-505564.jpg','https://www.oto.com/mobil-baru/volkswagen/id-buzz'),
('GAC','Aion Hyptec HT','pribadi',767.25,600,83,249.8,5,15,'SUV','https://imgcdn.oto.com/large/gallery/exterior/183/3042/gac-aion-hyper-ht-22482.jpg','https://www.oto.com/mobil-baru/gac/aion-hyptec-ht'),
('Neta','X','pribadi',438,480,63.56,120.1,5,30,'SUV','https://imgcdn.oto.com/large/gallery/exterior/168/2976/neta-x-front-angle-low-view-195281.jpg','https://www.oto.com/mobil-baru/neta/x'),
('MG','Cyberster','pribadi',1705,541.5,77,400.4,2,26,'Convertible','https://imgcdn.oto.com/large/gallery/exterior/139/2973/mg-cyberster-front-angle-low-view-986512.jpg','https://www.oto.com/mobil-baru/mg/cyberster'),
('VinFast','VF 6','pribadi',412.3,399,59.6,149.9,5,24,'SUV','https://imgcdn.oto.com/large/gallery/exterior/180/2889/vinfast-vf-6-front-angle-low-view-104166.jpg','https://www.oto.com/mobil-baru/vinfast/vf-6'),
('BMW','i5','pribadi',2527.5,549,81.2,350.9,5,30,'Sedan','https://imgcdn.oto.com/large/gallery/exterior/3/2950/bmw-i5-front-angle-low-view-717899.jpg','https://www.oto.com/mobil-baru/bmw/i5'),
('Kia','EV9','pribadi',1776.5,497,99.8,283.4,7,24,'SUV','https://imgcdn.oto.com/large/gallery/exterior/20/2799/kia-ev9-front-angle-low-view-130091.jpg','https://www.oto.com/mobil-baru/kia/ev9'),
('Mercedes Benz','EQS','pribadi',3622.5,770,107.8,248.3,5,31,'Sedan','https://imgcdn.oto.com/large/gallery/exterior/25/2712/mercedes-benz-eqs-front-angle-low-view-116785.jpg','https://www.oto.com/mobil-baru/mercedes-benz/eqs'),
('Nissan','Leaf','pribadi',740,311,40,110.4,5,60,'Hatchback','https://imgcdn.oto.com/large/gallery/exterior/29/2056/nissan-leaf-front-angle-low-view-429915.jpg','https://www.oto.com/mobil-baru/nissan/leaf'),
('GWM','Ora 03 BEV','pribadi',379,400,47.7,77.6,5,45,'Hatchback','https://imgcdn.oto.com/large/gallery/exterior/169/2959/gwm-ora-03-bev-front-angle-low-view-907978.jpg','https://www.oto.com/mobil-baru/gwm/ora-03-bev'),
('BMW','iX','pribadi',2628,398.5,94.05,304.2,5,35,'SUV','https://imgcdn.oto.com/large/gallery/exterior/3/2372/bmw-ix-front-angle-low-view-516654.jpg','https://www.oto.com/mobil-baru/bmw/ix'),
('Toyota','bZ4X','pribadi',1190,500,71.4,149.9,5,30,'SUV','https://imgcdn.oto.com/large/gallery/exterior/38/2689/toyota-bz4x-front-angle-low-view-650554.jpg','https://www.oto.com/mobil-baru/toyota/bz4x'),
('BMW','i7','pribadi',3415,625,101.7,405.7,5,34,'Sedan','https://imgcdn.oto.com/large/gallery/exterior/3/2749/bmw-i7-front-angle-low-view-582404.jpg','https://www.oto.com/mobil-baru/bmw/i7'),
('BMW','iX1','pribadi',1379,438,64.7,233.4,5,29,'SUV','https://imgcdn.oto.com/large/gallery/exterior/3/2884/bmw-ix1-front-angle-low-view-565038.jpg','https://www.oto.com/mobil-baru/bmw/ix1'),
('BMW','i5 Touring','pribadi',2275,506,81.2,253.5,5,30,'Wagon','https://imgcdn.oto.com/large/gallery/exterior/3/3123/bmw-i5-touring-front-angle-low-view-275042.jpg','https://www.oto.com/mobil-baru/bmw/i5-touring'),
('Volvo','EX30','pribadi',1015,476,57.5,202.8,5,26,'SUV','https://imgcdn.oto.com/large/gallery/exterior/40/3037/volvo-ex30-front-angle-low-view-442220.jpg','https://www.oto.com/mobil-baru/volvo/ex30'),
('VinFast','VF 7','pribadi',549,431,75.3,204.7,5,28,'SUV','https://imgcdn.oto.com/large/gallery/exterior/180/2891/vinfast-vf-7-front-angle-low-view-284253.jpg','https://www.oto.com/mobil-baru/vinfast/vf-7'),
('Hyundai','Ioniq 5 N','pribadi',1500,448,84,478,5,18,'SUV','https://imgcdn.oto.com/large/gallery/exterior/15/2964/hyundai-ioniq-5-n-front-angle-low-view-951863.jpg','https://www.oto.com/mobil-baru/hyundai/ioniq-5-n'),
('Farizon','SV','kantor',698,415,82.88,169.3,16,45,'Van','https://imgcdn.oto.com/large/gallery/exterior/214/3328/farizon-sv-front-angle-low-view-907860.jpg','https://www.oto.com/mobil-baru/farizon/sv'),
('VinFast','Limo Green','kantor',389,400,60.13,149.9,7,30,'MPV','https://imgcdn.oto.com/large/gallery/exterior/180/3335/vinfast-limo-green-front-angle-low-view-961001.jpg','https://www.oto.com/mobil-baru/vinfast/limo-green'),
('Mitsubishi','L100 EV','kantor',323.3,180,20.1,30.6,2,42,'Van','https://imgcdn.oto.com/large/gallery/exterior/28/2907/mitsubishi-l100-ev-front-angle-low-view-396034.jpg','https://www.oto.com/mobil-baru/mitsubishi/l100-ev');
