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
    harga_min               DECIMAL(10,2) DEFAULT NULL,
    harga_maks              DECIMAL(10,2) DEFAULT NULL,
    baterai_min             DECIMAL(6,2) DEFAULT NULL,
    baterai_maks            DECIMAL(6,2) DEFAULT NULL,
    range_min               DECIMAL(6,1) DEFAULT NULL,
    range_maks              DECIMAL(6,1) DEFAULT NULL,
    daya_min_kw             DECIMAL(6,1) DEFAULT NULL,
    daya_maks_kw            DECIMAL(6,1) DEFAULT NULL,
    varian_json             TEXT         DEFAULT NULL,
    warna                   VARCHAR(400) DEFAULT NULL,
    galeri_json             MEDIUMTEXT   DEFAULT NULL,
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

-- Rentang min-maks (dataset Kaggle, April 2026; daya hp -> kW), varian dan warna
-- (halaman Oto.com tiap model, Oktober 2026). Hanya untuk tampilan detail;
-- perhitungan AHP-TOPSIS memakai kolom kriteria di atas.
UPDATE kendaraan_ev SET harga_min=323.17, harga_maks=323.17, baterai_min=29.6, baterai_maks=29.6, range_min=260, range_maks=260, daya_min_kw=70.1, daya_maks_kw=70.1, varian_json='[{"nama":"VinFast VF 5 EV","harga":323.17}]', warna='Crimson Red, Brahimny White, Neptune Grey, Vinfast Blue' WHERE brand='VinFast' AND model='VF 5';
UPDATE kendaraan_ev SET galeri_json='{"eksterior":["https://imgcdn.oto.com/large/gallery/exterior/180/2888/vinfast-vf-5-front-angle-low-view-464813.jpg","https://imgcdn.oto.com/large/gallery/exterior/180/2888/vinfast-vf-5-rear-cross-side-view-113183.jpg","https://imgcdn.oto.com/large/gallery/exterior/180/2888/vinfast-vf-5-full-front-view-971672.jpg","https://imgcdn.oto.com/large/gallery/exterior/180/2888/vinfast-vf-5-full-rear-view-167558.jpg","https://imgcdn.oto.com/large/gallery/exterior/180/2888/vinfast-vf-5-door-handle-887485.jpg","https://imgcdn.oto.com/large/gallery/exterior/180/2888/vinfast-vf-5-wheel-854329.jpg","https://imgcdn.oto.com/large/gallery/exterior/180/2888/vinfast-vf-5-rear-cross-view-489249.jpg","https://imgcdn.oto.com/large/gallery/exterior/180/2888/vinfast-vf-5-rear-medium-side-view-509595.jpg","https://imgcdn.oto.com/large/gallery/exterior/180/2888/vinfast-vf-5-front-cross-side-view-701724.jpg","https://imgcdn.oto.com/large/gallery/exterior/180/2888/vinfast-vf-5-medium-angle-front-view-665212.jpg"],"interior":["https://imgcdn.oto.com/large/gallery/interior/180/2888/vinfast-vf-5-dashboard-view-346820.jpg","https://imgcdn.oto.com/large/gallery/interior/180/2888/vinfast-vf-5-steering-wheel-406387.jpg","https://imgcdn.oto.com/large/gallery/interior/180/2888/vinfast-vf-5-drivers-side-in-side-door-controls-103162.jpg","https://imgcdn.oto.com/large/gallery/interior/180/2888/vinfast-vf-5-rear-seats-583866.jpg","https://imgcdn.oto.com/large/gallery/interior/180/2888/vinfast-vf-5-passengers-view-442914.jpg","https://imgcdn.oto.com/large/gallery/interior/180/2888/vinfast-vf-5-front-ac-vents-816087.jpg","https://imgcdn.oto.com/large/gallery/interior/180/2888/vinfast-vf-5-touch-screen-380503.jpg","https://imgcdn.oto.com/large/gallery/interior/180/2888/vinfast-vf-5-center-controls-531522.jpg"],"warna":[{"nama":"Crimson Red","url":"https://imgcdn.oto.com/large/gallery/color/180/2888/vinfast-vf-5-color-403173.jpg"},{"nama":"Brahimny White","url":"https://imgcdn.oto.com/large/gallery/color/180/2888/vinfast-vf-5-color-131913.jpg"},{"nama":"Neptune Grey","url":"https://imgcdn.oto.com/large/gallery/color/180/2888/vinfast-vf-5-color-756789.jpg"},{"nama":"Vinfast Blue","url":"https://imgcdn.oto.com/large/gallery/color/180/2888/vinfast-vf-5-color-834842.jpg"}]}' WHERE brand='VinFast' AND model='VF 5';
UPDATE kendaraan_ev SET harga_min=345, harga_maks=419, baterai_min=51, baterai_maks=64, range_min=425, range_maks=425, daya_min_kw=125.3, daya_maks_kw=149.9, varian_json='[{"nama":"MG 4 EV Magnify","harga":345},{"nama":"MG 4 EV Magnify Max","harga":419}]', warna='Arctic White, Scarlet Red, Night Black, Metal Ash Grey, Bright Blue' WHERE brand='MG' AND model='4 EV';
UPDATE kendaraan_ev SET harga_min=383, harga_maks=433, baterai_min=55.4, baterai_maks=71.8, range_min=420, range_maks=530, daya_min_kw=114.8, daya_maks_kw=114.8, varian_json='[{"nama":"Standard 7-Seater","harga":395},{"nama":"Superior 7-Seater","harga":423},{"nama":"Superior Captain 6-Seater","harga":433}]', warna='Cosmos Black, Crystal White, Harbour Grey, Quartz Blue' WHERE brand='BYD' AND model='M6';
UPDATE kendaraan_ev SET harga_min=279, harga_maks=332, baterai_min=31.9, baterai_maks=37.9, range_min=333, range_maks=410, daya_min_kw=50, daya_maks_kw=50, varian_json='[{"nama":"Lite","harga":289},{"nama":"Pro","harga":319}]', warna='Galaxy Blue, Avocado Green, Lemon Yellow, Tungsten Steel Grey, Milk Tea' WHERE brand='Wuling' AND model='Binguo EV';
UPDATE kendaraan_ev SET harga_min=411.86, harga_maks=411.86, baterai_min=41.9, baterai_maks=41.9, range_min=318, range_maks=318, daya_min_kw=109.6, daya_maks_kw=109.6, varian_json='[{"nama":"VinFast VF e34 Electric","harga":411.86}]', warna='Crimson Red, Jet Black, Brahimny White, DeSat Silver, Neptune Grey, Vinfast Blue, Deep Ocean' WHERE brand='VinFast' AND model='VF e34';
UPDATE kendaraan_ev SET harga_min=415, harga_maks=443, baterai_min=50.6, baterai_maks=50.6, range_min=460, range_maks=460, daya_min_kw=99.9, daya_maks_kw=99.9, varian_json='[{"nama":"Cloud EV SE Lite","harga":299},{"nama":"Cloud EV SE Pro","harga":339},{"nama":"Cloud EV Lite","harga":415},{"nama":"Cloud EV Pro","harga":443}]', warna='Pristine White, Starry Black, Aurora Silver, Tungsten Steel Grey, Milk Tea' WHERE brand='Wuling' AND model='Cloud EV';
UPDATE kendaraan_ev SET harga_min=639, harga_maks=750, baterai_min=82.56, baterai_maks=82.56, range_min=580, range_maks=650, daya_min_kw=233.4, daya_maks_kw=395.2, varian_json='[{"nama":"Premium","harga":639},{"nama":"Performance AWD","harga":750}]', warna='Cosmos Black, Aurora White, Arctic Blue, Atlantis Grey, Lavender Grey' WHERE brand='BYD' AND model='Seal';
UPDATE kendaraan_ev SET harga_min=369, harga_maks=429, baterai_min=44.9, baterai_maks=60.48, range_min=410, range_maks=490, daya_min_kw=70.8, daya_maks_kw=152.1, varian_json='[{"nama":"Dynamic","harga":369},{"nama":"Premium","harga":429}]', warna='Atlantis Grey, Ski White, Surf Blue, Coral Pink' WHERE brand='BYD' AND model='Dolphin';
UPDATE kendaraan_ev SET harga_min=379.9, harga_maks=419.9, baterai_min=61, baterai_maks=61, range_min=430, range_maks=430, daya_min_kw=155.1, daya_maks_kw=155.1, varian_json='[{"nama":"Pure","harga":379.9},{"nama":"Luxury","harga":419.9}]', warna='Black Platinum, Green Jade Two Tone, White Howlite Black, Grey Morganite, White Howlite' WHERE brand='CHERY' AND model='E5';
UPDATE kendaraan_ev SET harga_min=390, harga_maks=520, baterai_min=49.92, baterai_maks=60.48, range_min=410, range_maks=480, daya_min_kw=152.1, daya_maks_kw=152.1, varian_json='[{"nama":"Advanced Plus","harga":415},{"nama":"Superior","harga":null}]', warna='Cosmos Black, Forest Green, Boulder Grey, Ski White, Surf Blue' WHERE brand='BYD' AND model='Atto 3';
UPDATE kendaraan_ev SET harga_min=299, harga_maks=299, baterai_min=40.7, baterai_maks=40.7, range_min=401, range_maks=401, daya_min_kw=70.1, daya_maks_kw=70.1, varian_json='[{"nama":"Neta V-II Electric","harga":299}]', warna='Milk Tea' WHERE brand='Neta' AND model='V-II';
UPDATE kendaraan_ev SET harga_min=1100, harga_maks=1100, baterai_min=100, baterai_maks=100, range_min=605, range_maks=780, daya_min_kw=475, daya_maks_kw=475, varian_json='[{"nama":"ZEEKR 7X EV","harga":1100}]', warna='Crystal White, Forest Green, Onyx Black, Tech Grey' WHERE brand='ZEEKR' AND model='7X';
UPDATE kendaraan_ev SET harga_min=755, harga_maks=758, baterai_min=61, baterai_maks=61, range_min=400, range_maks=400, daya_min_kw=128.3, daya_maks_kw=128.3, varian_json='[{"nama":"e-Vitara GLX","harga":755},{"nama":"e-Vitara GLX 2 Tone","harga":758}]', warna='Arctic White Pearl, Land Breeze Green Pearl Metallic, Bluish Black Pearl' WHERE brand='Suzuki' AND model='e-Vitara';
UPDATE kendaraan_ev SET harga_min=333.9, harga_maks=455.9, baterai_min=49, baterai_maks=49, range_min=420, range_maks=420, daya_min_kw=125.3, daya_maks_kw=125.3, varian_json='[{"nama":"Ignite","harga":333.9},{"nama":"Magnify","harga":355.9},{"nama":"Magnify Max","harga":455.9}]', warna='Silver' WHERE brand='MG' AND model='S5 EV';
UPDATE kendaraan_ev SET harga_min=5900, harga_maks=5900, baterai_min=116, baterai_maks=116, range_min=473, range_maks=473, daya_min_kw=437.7, daya_maks_kw=437.7, varian_json='[{"nama":"G-Class Electric 580 Edition One","harga":5900}]', warna='Blue' WHERE brand='Mercedes Benz' AND model='G-Class Electric';
UPDATE kendaraan_ev SET harga_min=950, harga_maks=950, baterai_min=103.36, baterai_maks=103.36, range_min=600, range_maks=600, daya_min_kw=229.7, daya_maks_kw=229.7, varian_json='[{"nama":"Denza D9 Electric","harga":1080}]', warna='Arctic White, Cosmos Black, Harbour Grey, Whale Sea Blue' WHERE brand='BYD' AND model='Denza D9';
UPDATE kendaraan_ev SET harga_min=809, harga_maks=933.6, baterai_min=58, baterai_maks=72.6, range_min=384, range_maks=481, daya_min_kw=125.3, daya_maks_kw=159.6, varian_json='[{"nama":"Standard Range Prime","harga":809},{"nama":"Long Range Prime","harga":851.5},{"nama":"Standard Range Signature","harga":873.9},{"nama":"Long Range Signature","harga":925.6},{"nama":"Batik","harga":933.6}]', warna='Midnight Black Pearl, Titan Gray Metallic, Magnetic Silver Metallic, Gravity Gold Matte, Optic White' WHERE brand='Hyundai' AND model='Ioniq 5';
UPDATE kendaraan_ev SET harga_min=376, harga_maks=417.5, baterai_min=50.3, baterai_maks=50.3, range_min=403, range_maks=403, daya_min_kw=132, daya_maks_kw=132, varian_json='[{"nama":"MG ZS EV Ignite","harga":376},{"nama":"MG ZS EV Magnify","harga":417.5}]', warna='Red, Blue, Arctic White, Mountain Grey, Black Pearl' WHERE brand='MG' AND model='ZS EV';
UPDATE kendaraan_ev SET harga_min=565.3, harga_maks=689.6, baterai_min=48.9, baterai_maks=66, range_min=305, range_maks=484, daya_min_kw=114.8, daya_maks_kw=159.6, varian_json='[{"nama":"Style Standard Range","harga":565.25},{"nama":"Prime Standard Range","harga":583.5},{"nama":"Prime Long Range","harga":634.5},{"nama":"Signature Standard Range","harga":651.65},{"nama":"Signature Long Range","harga":668.55},{"nama":"N Line","harga":689.55}]', warna='Midnight Black Pearl, Creamy White Pearl, Dragon Red Pearl, Titan Gray Metallic, Magnetic Silver Metallic, Optic White' WHERE brand='Hyundai' AND model='Kona Electric';
UPDATE kendaraan_ev SET harga_min=1237, harga_maks=1237, baterai_min=77.4, baterai_maks=77.4, range_min=519, range_maks=614, daya_min_kw=240.1, daya_maks_kw=240.1, varian_json='[{"nama":"Ioniq 6 Signature","harga":1237}]', warna='Silver Metallic' WHERE brand='Hyundai' AND model='Ioniq 6';
UPDATE kendaraan_ev SET harga_min=380.5, harga_maks=380.5, baterai_min=29.2, baterai_maks=29.2, range_min=320, range_maks=320, daya_min_kw=41.8, daya_maks_kw=41.8, varian_json='[{"nama":"E-C3 Electric","harga":380.5}]', warna='Orange' WHERE brand='Citroen' AND model='E-C3';
UPDATE kendaraan_ev SET harga_min=419, harga_maks=479, baterai_min=50.6, baterai_maks=63.2, range_min=410, range_maks=490, daya_min_kw=149.9, daya_maks_kw=149.9, varian_json='[{"nama":"Exclusive","harga":419},{"nama":"Premium","harga":479}]', warna='Vitality Green, Elegant Grey, Glamour Black, Pure White, Vitality Green with Black Roof, Pure White with Black Roof' WHERE brand='GAC' AND model='Aion Y Plus';
UPDATE kendaraan_ev SET harga_min=1495, harga_maks=1550, baterai_min=77, baterai_maks=77, range_min=400, range_maks=480, daya_min_kw=152.1, daya_maks_kw=209.5, varian_json='[{"nama":"Long Wheelbase","harga":1495},{"nama":"Boss Edition","harga":1550}]', warna='Deep Black Pearl Effect, Candy White, Bay Leaf Green Metallic, Candy White/Medium Blue, Candy White/Mono Silver, Candy White/Orange, Chery Red, Lemon, Mono Silver Metallic, Starlight Blue Metallic' WHERE brand='Volkswagen' AND model='ID Buzz';
UPDATE kendaraan_ev SET harga_min=691, harga_maks=843.5, baterai_min=83, baterai_maks=83, range_min=600, range_maks=600, daya_min_kw=249.8, daya_maks_kw=249.8, varian_json='[{"nama":"Premium","harga":755},{"nama":"Ultra","harga":843.5}]', warna='Orange Sunset, White Pearl, Grey Stone, Maroon Velvet, Black Abyss, Silver Bullet' WHERE brand='GAC' AND model='Aion Hyptec HT';
UPDATE kendaraan_ev SET harga_min=428, harga_maks=448, baterai_min=63.56, baterai_maks=63.56, range_min=480, range_maks=480, daya_min_kw=120.1, daya_maks_kw=120.1, varian_json='[{"nama":"Neta X 480 Elite","harga":428},{"nama":"Neta X 480 Supreme","harga":448}]', warna='Pearl White, Jet Black, Sky Grey, Glacier Blue' WHERE brand='Neta' AND model='X';
UPDATE kendaraan_ev SET harga_min=1705, harga_maks=1705, baterai_min=77, baterai_maks=77, range_min=503, range_maks=580, daya_min_kw=400.4, daya_maks_kw=400.4, varian_json='[{"nama":"MG Cyberster Electric","harga":1705}]', warna='Dynamic Red, Inca Yellow' WHERE brand='MG' AND model='Cyberster';
UPDATE kendaraan_ev SET harga_min=385, harga_maks=439.6, baterai_min=59.6, baterai_maks=59.6, range_min=399, range_maks=399, daya_min_kw=149.9, daya_maks_kw=149.9, varian_json='[{"nama":"Eco","harga":385},{"nama":"Plus","harga":439.6}]', warna='Crimson Red, Jet Black, Infinity Blanc, Urban Mint, Zenith Grey' WHERE brand='VinFast' AND model='VF 6';
UPDATE kendaraan_ev SET harga_min=2225, harga_maks=2830, baterai_min=81.2, baterai_maks=81.2, range_min=516, range_maks=582, daya_min_kw=253.5, daya_maks_kw=448.2, varian_json='[{"nama":"i5 eDrive 40 M Sport","harga":2225},{"nama":"i5 M60 xDrive","harga":2830}]', warna='Red Metallic, Alpine White, Mineral White Metallic, Black Sapphire Metallic, Carbon Black Metallic, Phytonic Blue, Tanzanite Blue Metallic, Brooklyn Grey Metallic, Oxide Grey Metallic, Cape York Green Metallic, Frozen Pure Grey Metallic' WHERE brand='BMW' AND model='i5';
UPDATE kendaraan_ev SET harga_min=1568, harga_maks=1985, baterai_min=99.8, baterai_maks=99.8, range_min=497, range_maks=497, daya_min_kw=283.4, daya_maks_kw=283.4, varian_json='[{"nama":"EV9 Earth","harga":1568},{"nama":"EV9 GT-Line","harga":1985}]', warna='Silver, Grey, White, Blue, Midnight Black, Machine Grey, Ice Blue, Ignite Red, Moon Silver' WHERE brand='Kia' AND model='EV9';
UPDATE kendaraan_ev SET harga_min=3135, harga_maks=4110, baterai_min=107.8, baterai_maks=107.8, range_min=770, range_maks=770, daya_min_kw=248.3, daya_maks_kw=248.3, varian_json='[{"nama":"EQS 450+ Electric Art Line","harga":3135},{"nama":"EQS 450+ AMG Line","harga":3655},{"nama":"EQS 450+ Edition One","harga":4110}]', warna='White' WHERE brand='Mercedes Benz' AND model='EQS';
UPDATE kendaraan_ev SET harga_min=740, harga_maks=740, baterai_min=40, baterai_maks=40, range_min=311, range_maks=311, daya_min_kw=110.4, daya_maks_kw=110.4, varian_json='[{"nama":"Leaf One Tone EV","harga":744},{"nama":"Leaf Two Tone EV","harga":746}]', warna='White Pearl, Black Star, Radiant Red, Night Blue, Blade Silver, Gun Metallic, White Pearl With Blue Roof, White Pearl With Black Roof, Starlight Yellow With Black Roof, Night Blue With Black Roof, Black Star With Grey Roof' WHERE brand='Nissan' AND model='Leaf';
UPDATE kendaraan_ev SET harga_min=379, harga_maks=379, baterai_min=47.7, baterai_maks=47.7, range_min=400, range_maks=400, daya_min_kw=77.6, daya_maks_kw=77.6, varian_json='[{"nama":"Ora 03 BEV 400","harga":369}]', warna='White, Green, Light Blue, Beige' WHERE brand='GWM' AND model='Ora 03 BEV';
UPDATE kendaraan_ev SET harga_min=2628, harga_maks=2628, baterai_min=76.6, baterai_maks=111.5, range_min=372, range_maks=425, daya_min_kw=304.2, daya_maks_kw=304.2, varian_json='[{"nama":"iX xDrive45","harga":2639}]', warna='Alpine White, Mineral White Metallic, Black Sapphire Metallic, Dark Gray Metallic, Phytonic Blue, Storm Bay Metallic, Aventurin Red Metallic, Oxide Grey Metallic, Blue Ridge Mountain Metallic' WHERE brand='BMW' AND model='iX';
UPDATE kendaraan_ev SET harga_min=1190, harga_maks=1190, baterai_min=71.4, baterai_maks=71.4, range_min=500, range_maks=500, daya_min_kw=149.9, daya_maks_kw=149.9, varian_json='[{"nama":"Mono Tone","harga":799},{"nama":"Touring Battery EV","harga":889}]', warna='Attitude Black Mica, Platinum White Pearl' WHERE brand='Toyota' AND model='bZ4X';
UPDATE kendaraan_ev SET harga_min=3415, harga_maks=3415, baterai_min=101.7, baterai_maks=101.7, range_min=625, range_maks=625, daya_min_kw=405.7, daya_maks_kw=405.7, varian_json='[{"nama":"i7 xDrive60 Gran Lusso","harga":3429}]', warna='Mineral White Metallic, Black Sapphire Metallic, Carbon Black Metallic, Dravit Grey Metallic, Tanzanite Blue Metallic, Brooklyn Grey Metallic, Oxide Grey Metallic' WHERE brand='BMW' AND model='i7';
UPDATE kendaraan_ev SET harga_min=1379, harga_maks=1379, baterai_min=64.7, baterai_maks=64.7, range_min=438, range_maks=438, daya_min_kw=233.4, daya_maks_kw=233.4, varian_json='[{"nama":"iX1 eDrive20 M Sport","harga":1379}]', warna='Alpine White, Mineral White Metallic, Black Sapphire Metallic, Portimao Blue, Space Silver Metallic, Cape York Green Metallic, Frozen Pure Grey Metallic' WHERE brand='BMW' AND model='iX1';
UPDATE kendaraan_ev SET harga_min=2275, harga_maks=2275, baterai_min=81.2, baterai_maks=81.2, range_min=506, range_maks=506, daya_min_kw=253.5, daya_maks_kw=253.5, varian_json='[{"nama":"i5 Touring eDrive40 M Sport","harga":2275}]', warna='Grey Metallic, Sophisto Grey Brilliant Effect Metallic, Mineral White Metallic, Black Sapphire Metallic, Carbon Black Metallic, Oxide Grey Metallic, Cape York Green Metallic' WHERE brand='BMW' AND model='i5 Touring';
UPDATE kendaraan_ev SET harga_min=930, harga_maks=1100, baterai_min=49, baterai_maks=66, range_min=476, range_maks=476, daya_min_kw=202.8, daya_maks_kw=202.8, varian_json='[{"nama":"EX30 Plus","harga":930},{"nama":"EX30 Ultra","harga":1100}]', warna='Crystal White, Onyx Black, Cloud Blue, Moss Yellow, Vapour Grey' WHERE brand='Volvo' AND model='EX30';
UPDATE kendaraan_ev SET harga_min=499, harga_maks=599, baterai_min=75.3, baterai_maks=75.3, range_min=431, range_maks=431, daya_min_kw=149.9, daya_maks_kw=259.5, varian_json='[{"nama":"Eco","harga":499},{"nama":"Plus","harga":599}]', warna='Blue' WHERE brand='VinFast' AND model='VF 7';
UPDATE kendaraan_ev SET harga_min=1500, harga_maks=1500, baterai_min=84, baterai_maks=84, range_min=448, range_maks=448, daya_min_kw=478, daya_maks_kw=478, varian_json='[{"nama":"Ioniq 5 N EV","harga":1500}]', warna='Blue' WHERE brand='Hyundai' AND model='Ioniq 5 N';
UPDATE kendaraan_ev SET harga_min=698, harga_maks=698, baterai_min=82.88, baterai_maks=82.88, range_min=415, range_maks=415, daya_min_kw=169.3, daya_maks_kw=169.3, varian_json='[{"nama":"Farizon SV EV","harga":698}]', warna='White' WHERE brand='Farizon' AND model='SV';
UPDATE kendaraan_ev SET harga_min=389, harga_maks=389, baterai_min=60.13, baterai_maks=60.13, range_min=400, range_maks=400, daya_min_kw=149.9, daya_maks_kw=149.9, varian_json='[{"nama":"EV","harga":389}]', warna='Jet Black, Infinity Blanc, Zenith Grey' WHERE brand='VinFast' AND model='Limo Green';
UPDATE kendaraan_ev SET harga_min=323.3, harga_maks=323.3, baterai_min=20.1, baterai_maks=20.1, range_min=180, range_maks=180, daya_min_kw=30.6, daya_maks_kw=30.6, varian_json='[{"nama":"Two-Seater Blind Van","harga":323.3}]', warna='White' WHERE brand='Mitsubishi' AND model='L100 EV';
