CREATE DATABASE IF NOT EXISTS peminjaman_ruangan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE peminjaman_ruangan;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 nama VARCHAR(100) NOT NULL,
 username VARCHAR(50) UNIQUE NOT NULL,
 password VARCHAR(255) NOT NULL,
 role ENUM('admin','user') NOT NULL DEFAULT 'user',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE rooms (
 id INT AUTO_INCREMENT PRIMARY KEY,
 nama_ruangan VARCHAR(100) NOT NULL,
 lokasi VARCHAR(150) NOT NULL,
 kapasitas INT NOT NULL,
 fasilitas VARCHAR(255),
 status ENUM('tersedia','nonaktif') DEFAULT 'tersedia'
);

CREATE TABLE bookings (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 room_id INT NOT NULL,
 tanggal DATE NOT NULL,
 jam_mulai TIME NOT NULL,
 jam_selesai TIME NOT NULL,
 keperluan VARCHAR(255) NOT NULL,
 status ENUM('menunggu','disetujui','ditolak') DEFAULT 'menunggu',
 catatan_admin VARCHAR(255),
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

INSERT INTO users (nama, username, password, role) VALUES
('Administrator','admin',MD5('admin123'),'admin'),
('Mahasiswa Contoh','user',MD5('user123'),'user');

INSERT INTO rooms (nama_ruangan,lokasi,kapasitas,fasilitas) VALUES
('Ruang A101','Gedung A Lantai 1',40,'Proyektor, AC, Papan Tulis'),
('Ruang B201','Gedung B Lantai 2',30,'Proyektor, AC'),
('Ruang Seminar','Gedung Utama',100,'Proyektor, Sound System, AC');
