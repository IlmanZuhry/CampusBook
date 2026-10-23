<?php
session_start();

require_once __DIR__ . "/../config/database.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "mahasiswa"
) {
    header("Location: ../login.php");
    exit;
}

$pageTitle = "Beranda";
$pageDescription = "Pantau aktivitas dan kebutuhan fasilitas kampusmu.";

$studentName = $_SESSION["name"] ?? "Mahasiswa";
$firstName = explode(" ", trim($studentName))[0];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda Mahasiswa | CampusBook</title>

    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/user.css">

    <!-- Hapus baris ini jika Font Awesome sudah dimuat di admin.css -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

<div class="admin-layout">
    <?php require_once __DIR__ . "/../includes/user_sidebar.php"; ?>

    <main class="admin-main">
        <?php require_once __DIR__ . "/../includes/user_header.php"; ?>

        <section class="user-dashboard">

            <div class="user-welcome">
                <div class="welcome-content">
                    <span class="welcome-label">STUDENT DASHBOARD</span>

                    <h2>
                        Halo, <?= htmlspecialchars($firstName) ?>!
                        <span class="welcome-wave">👋</span>
                    </h2>

                    <p>
                        Selamat datang di CampusBook. Temukan fasilitas kampus
                        dan kelola kebutuhan pemesananmu dengan lebih mudah.
                    </p>

                    <a href="fasilitas.php" class="welcome-button">
                        <i class="fas fa-search"></i>
                        Jelajahi Fasilitas
                    </a>
                </div>

                
            </div>

            <div class="section-heading">
                <div>
                    <h2>Ringkasan Aktivitas</h2>
                    <p>Informasi singkat untuk membantu aktivitas kampusmu.</p>
                </div>
            </div>

            <div class="user-stat-grid">
                <a href="pemesanan.php" class="user-stat-card">
                    <div class="user-stat-icon stat-blue">
                        <i class="fas fa-calendar-check"></i>
                    </div>

                    <div class="user-stat-info">
                        <span>Pemesanan Saya</span>
                        <strong>Lihat pemesanan</strong>
                        <small>Periksa status pemesanan fasilitas</small>
                    </div>

                    <i class="fas fa-arrow-up-right-from-square stat-arrow"></i>
                </a>

                <a href="pembayaran.php" class="user-stat-card">
                    <div class="user-stat-icon stat-green">
                        <i class="fas fa-wallet"></i>
                    </div>

                    <div class="user-stat-info">
                        <span>Pembayaran</span>
                        <strong>Lihat pembayaran</strong>
                        <small>Periksa informasi pembayaranmu</small>
                    </div>

                    <i class="fas fa-arrow-up-right-from-square stat-arrow"></i>
                </a>

                <a href="notifikasi.php" class="user-stat-card">
                    <div class="user-stat-icon stat-orange">
                        <i class="fas fa-bell"></i>
                    </div>

                    <div class="user-stat-info">
                        <span>Notifikasi</span>
                        <strong>Lihat notifikasi</strong>
                        <small>Dapatkan informasi terbaru</small>
                    </div>

                    <i class="fas fa-arrow-up-right-from-square stat-arrow"></i>
                </a>
            </div>

            <div class="user-content-grid">

                <section class="user-panel">
                    <div class="panel-heading">
                        <div>
                            <h3>Akses Cepat</h3>
                            <p>Pilih layanan yang ingin kamu gunakan.</p>
                        </div>
                    </div>

                    <div class="quick-access-grid">
                        <a href="fasilitas.php" class="quick-access-item">
                            <div class="quick-access-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <span>Jelajahi Fasilitas</span>
                            <small>Temukan fasilitas kampus</small>
                        </a>

                        <a href="pemesanan.php" class="quick-access-item">
                            <div class="quick-access-icon">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                            <span>Pemesanan Saya</span>
                            <small>Kelola pemesanan</small>
                        </a>

                        <a href="pembayaran.php" class="quick-access-item">
                            <div class="quick-access-icon">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <span>Pembayaran</span>
                            <small>Periksa pembayaran</small>
                        </a>

                        <a href="profil.php" class="quick-access-item">
                            <div class="quick-access-icon">
                                <i class="fas fa-user-gear"></i>
                            </div>
                            <span>Profil Saya</span>
                            <small>Periksa informasi akun</small>
                        </a>
                    </div>
                </section>

                <section class="user-panel user-help-panel">
                    <div class="help-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>

                    <h3>Butuh fasilitas kampus?</h3>

                    <p>
                        Mulai dengan melihat daftar fasilitas yang tersedia,
                        lalu buka halaman pemesanan untuk mengelola kebutuhanmu.
                    </p>

                    <a href="fasilitas.php" class="help-link">
                        Lihat Fasilitas
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </section>

            </div>

        </section>
    </main>
</div>

</body>
</html>
