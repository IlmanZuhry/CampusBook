-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Waktu pembuatan: 25 Okt 2026 pada 09.36
-- Versi server: 8.4.3
-- Versi PHP: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Basis data: `campusbook`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admins`
--

CREATE TABLE `admins` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `profile_photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `admins`
--

INSERT INTO `admins` (`id`, `name`, `email`, `password`, `status`, `created_at`, `profile_photo`) VALUES
(1, 'Asep Admin', 'admin@campusbook.local', '$2y$10$7l5fcHwZeV6m.of/kSNZnu3PViJ72.fVApQrCRL06ooNJoahCsuGm', 'active', '2026-10-21 14:10:11', 'e4cd5f822a14f9ef526735a92aafd9b3.jpg');

-- --------------------------------------------------------

--
-- Struktur dari tabel `bookings`
--

CREATE TABLE `bookings` (
  `id` int UNSIGNED NOT NULL,
  `booking_code` varchar(30) NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `facility_id` int UNSIGNED NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `purpose` text NOT NULL,
  `status` enum('pending','approved','rejected','cancelled','completed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `admin_note` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `bookings`
--

INSERT INTO `bookings` (`id`, `booking_code`, `user_id`, `facility_id`, `booking_date`, `start_time`, `end_time`, `purpose`, `status`, `created_at`, `admin_note`) VALUES
(1, 'CB-20261025-83C0CCC5', 2, 1, '2026-10-29', '10:00:00', '17:00:00', 'tur', 'rejected', '2026-10-25 02:17:32', 'Lagi Maintanance'),
(2, 'CB-20261025-60EC3A49', 2, 2, '2026-10-30', '10:30:00', '15:24:00', 'main', 'completed', '2026-10-25 02:25:14', NULL),
(3, 'CB-20261025-3FE740AB', 3, 1, '2026-10-31', '14:00:00', '17:00:00', 'meeting', 'completed', '2026-10-25 05:18:06', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `facilities`
--

CREATE TABLE `facilities` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'Lainnya',
  `description` text,
  `image` varchar(255) DEFAULT NULL,
  `location` varchar(150) NOT NULL,
  `capacity` int UNSIGNED NOT NULL DEFAULT '1',
  `hourly_rate` decimal(12,2) NOT NULL DEFAULT '0.00',
  `opening_time` time NOT NULL,
  `closing_time` time NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `facilities`
--

INSERT INTO `facilities` (`id`, `name`, `category`, `description`, `image`, `location`, `capacity`, `hourly_rate`, `opening_time`, `closing_time`, `status`, `created_at`) VALUES
(1, 'Aula Utama', 'Aula', 'Mantap', '4bb785e2ab187bd2a55e96fd.jpg', 'gerbang 2', 100, 200000.00, '09:00:00', '17:00:00', 'active', '2026-10-25 01:03:23'),
(2, 'Lapangan Basket', 'Lapangan', 'hebat', '94076ed4162ea8ae8521334a.jpg', 'gerbang 3 jln.hebat', 20, 50000.00, '10:00:00', '17:00:00', 'active', '2026-10-25 02:21:40');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifications`
--

CREATE TABLE `notifications` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `booking_id` int UNSIGNED DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `channel` enum('in_app','email','whatsapp') NOT NULL DEFAULT 'in_app',
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `booking_id`, `title`, `message`, `channel`, `status`, `sent_at`, `created_at`) VALUES
(1, 2, 2, 'Pemesanan disetujui', 'Pemesanan CB-20261025-60EC3A49 (Lapangan Basket, 30 Okt 2026 10:30-15:24) telah disetujui. Silakan lanjutkan ke pembayaran jika fasilitas berbayar.', 'in_app', 'sent', '2026-10-25 03:58:03', '2026-10-25 03:58:03'),
(2, 2, 1, 'Pemesanan ditolak', 'Pemesanan CB-20261025-83C0CCC5 (Aula Utama, 29 Okt 2026 10:00-17:00) tidak dapat disetujui. Catatan admin: Lagi Maintanance', 'in_app', 'sent', '2026-10-25 04:00:51', '2026-10-25 04:00:51'),
(3, 2, 2, 'Pemesanan selesai', 'Pemesanan CB-20261025-60EC3A49 (Lapangan Basket, 30 Okt 2026 10:30-15:24) telah ditandai selesai. Terima kasih telah menggunakan CampusBook.', 'in_app', 'sent', '2026-10-25 04:50:59', '2026-10-25 04:50:59'),
(4, 3, 3, 'Pemesanan disetujui', 'Pemesanan CB-20261025-3FE740AB (Aula Utama, 31 Okt 2026 14:00-17:00) telah disetujui. Silakan lanjutkan ke pembayaran jika fasilitas berbayar.', 'in_app', 'sent', '2026-10-25 05:18:53', '2026-10-25 05:18:53');

-- --------------------------------------------------------

--
-- Struktur dari tabel `payments`
--

CREATE TABLE `payments` (
  `id` int UNSIGNED NOT NULL,
  `booking_id` int UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('qris','va_bca','va_bni','va_bri','va_mandiri') NOT NULL,
  `payment_status` enum('pending','paid','failed') NOT NULL DEFAULT 'pending',
  `transaction_reference` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `amount`, `payment_method`, `payment_status`, `transaction_reference`, `paid_at`, `created_at`) VALUES
(1, 2, 245000.00, 'va_bni', 'paid', 'CB-DUMMY-EB923A3904', '2026-10-25 11:50:13', '2026-10-25 04:29:08'),
(2, 3, 600000.00, 'qris', 'paid', 'CB-DUMMY-BEBB48B072', '2026-10-25 12:19:27', '2026-10-25 05:19:08');

-- --------------------------------------------------------

--
-- Struktur dari tabel `students`
--

CREATE TABLE `students` (
  `id` int UNSIGNED NOT NULL,
  `nim` varchar(20) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fakultas` varchar(150) DEFAULT NULL,
  `prodi` varchar(150) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `profile_photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data untuk tabel `students`
--

INSERT INTO `students` (`id`, `nim`, `name`, `email`, `password`, `fakultas`, `prodi`, `status`, `created_at`, `profile_photo`) VALUES
(2, '241712047', 'Ilman Zuhry', 'ilmanemil31@gmail.com', '$2y$10$F8SommgRn3vf4HFbQM0IOuvV6.YgFTNIEtEjQD1KMzh0Jbq7zGrvC', 'Fakultas Vokasi', 'Teknik Informatika', 'active', '2026-10-23 13:42:58', 'student_2_7f1ba3acbae9d32e.png'),
(3, '241712042', 'Andi Wijaya', 'andiwijaya99@gmail.com', '$2y$10$kBXUg4I5WTKf4XP047OJMOqcZSi8NUnVQx24NdIOd4rRlgwHz6F.a', 'Fakultas Hukum', 'Ilmu Hukum', 'active', '2026-10-25 05:15:43', NULL),
(5, '231712088', 'Mamat Abdurahman', 'crackzuu19@gmail.com', '$2y$10$xf23XCW3t5BX9g3XnFf6XurfF77HSuRgubhy5MwJBOa3np0q4tOXy', 'Fakultas Ilmu Komputer dan Teknologi Informasi (Fasilkom-TI)', 'Ilmu Komputer', 'active', '2026-10-25 09:23:34', 'student_5_e75733829d7cc30e.jpg'),
(6, '251712066', 'Emil Fatih', 'emilfatih31@gmail.com', '$2y$10$zsbypoIT3YQJwcP3RwxLy.qf8pBTL.25HgkPoDUFYSoDd6OGstCiK', 'Fakultas Ilmu Komputer dan Teknologi Informasi (Fasilkom-TI)', 'Ilmu Komputer', 'active', '2026-10-25 09:36:03', NULL);

--
-- Indeks untuk tabel yang dibuang
--

--
-- Indeks untuk tabel `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_admin_email` (`email`);

--
-- Indeks untuk tabel `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_code` (`booking_code`),
  ADD KEY `idx_booking_schedule` (`facility_id`,`booking_date`,`status`),
  ADD KEY `fk_bookings_student` (`user_id`);

--
-- Indeks untuk tabel `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notification_booking` (`booking_id`),
  ADD KEY `fk_notifications_student` (`user_id`);

--
-- Indeks untuk tabel `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`),
  ADD UNIQUE KEY `transaction_reference` (`transaction_reference`);

--
-- Indeks untuk tabel `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_email` (`email`),
  ADD UNIQUE KEY `unique_student_nim` (`nim`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `facilities`
--
ALTER TABLE `facilities`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `students`
--
ALTER TABLE `students`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`),
  ADD CONSTRAINT `fk_bookings_student` FOREIGN KEY (`user_id`) REFERENCES `students` (`id`);

--
-- Ketidakleluasaan untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `fk_notifications_student` FOREIGN KEY (`user_id`) REFERENCES `students` (`id`);

--
-- Ketidakleluasaan untuk tabel `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
