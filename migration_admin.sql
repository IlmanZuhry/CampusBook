-- =====================================================================
-- CampusBook - Migrasi untuk modul admin
-- Fasilitas, Pemesanan, Pembayaran, Notifikasi
--
-- Jalankan SEKALI di phpMyAdmin (tab SQL) pada database `campusbook`,
-- setelah campusbook.sql diimpor.
--
-- CATATAN: bagian 2 memindahkan foreign key dari `users` ke `students`.
-- Jika tabel bookings / notifications sudah berisi data yang user_id-nya
-- merujuk ke `users`, kosongkan dulu (DELETE FROM notifications;
-- DELETE FROM payments; DELETE FROM bookings;) agar tidak gagal.
-- =====================================================================

-- 1. Fasilitas: kategori dan gambar (sesuai kartu fasilitas di desain)
ALTER TABLE `facilities`
  ADD COLUMN `category` varchar(50) NOT NULL DEFAULT 'Lainnya' AFTER `name`,
  ADD COLUMN `image` varchar(255) DEFAULT NULL AFTER `description`;

-- 2. Pemesan & penerima notifikasi adalah mahasiswa (tabel `students`),
--    bukan tabel `users` yang hanya berisi admin.
ALTER TABLE `bookings`
  DROP FOREIGN KEY `bookings_ibfk_1`;
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_booking_student`
  FOREIGN KEY (`user_id`) REFERENCES `students` (`id`);

ALTER TABLE `notifications`
  DROP FOREIGN KEY `fk_notification_user`;
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_student`
  FOREIGN KEY (`user_id`) REFERENCES `students` (`id`);

-- 3. Pemesanan: catatan admin (alasan penolakan / pembatalan)
ALTER TABLE `bookings`
  ADD COLUMN `admin_note` text DEFAULT NULL AFTER `purpose`;

-- 4. Pembayaran: tambahkan e-wallet (ada di desain halaman pembayaran)
ALTER TABLE `payments`
  MODIFY `payment_method` enum('transfer','qris','ewallet') NOT NULL;

-- 5. Data contoh fasilitas (opsional, hapus jika tidak diperlukan)
INSERT INTO `facilities`
  (`name`, `category`, `description`, `location`, `capacity`, `hourly_rate`, `opening_time`, `closing_time`, `status`)
VALUES
  ('Aula Utama', 'Aula', 'Aula serbaguna untuk seminar dan kegiatan organisasi.', 'Gedung Rektorat Lt. 2', 200, 150000.00, '08:00:00', '17:00:00', 'active'),
  ('Lab Komputer 1', 'Laboratorium', 'Laboratorium dengan 40 unit komputer.', 'Gedung Fakultas Teknik', 40, 75000.00, '08:00:00', '17:00:00', 'active'),
  ('Lapangan Futsal', 'Lapangan', 'Lapangan futsal outdoor dengan pencahayaan malam.', 'Area Olahraga', 20, 50000.00, '08:00:00', '22:00:00', 'active');
