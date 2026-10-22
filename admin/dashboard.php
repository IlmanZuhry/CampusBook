
<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Admin - CampusBook</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<div class="admin-layout">

    <?php require_once __DIR__ . "/../includes/admin_sidebar.php"; ?>

    <main class="admin-main">

        <?php require_once __DIR__ . "/../includes/admin_header.php"; ?>

        <section class="welcome-banner">
            <div>
                <span class="banner-label">ADMIN WORKSPACE</span>
                <h2>Selamat datang, <?= htmlspecialchars($_SESSION["name"] ?? "Administrator") ?>!</h2>
                <p>Kelola fasilitas dan pantau aktivitas pemesanan kampus dari satu tempat.</p>
            </div>
            <div class="banner-symbol">CB</div>
        </section>

        <section class="stats-grid">
            <article class="stat-card">
                <div class="stat-top">
                    <span>Total Mahasiswa</span>
                    <span class="stat-icon blue">♙</span>
                </div>
                <strong>—</strong>
                <p>Jumlah akun mahasiswa</p>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Total Fasilitas</span>
                    <span class="stat-icon purple">▣</span>
                </div>
                <strong>—</strong>
                <p>Fasilitas terdaftar</p>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Total Pemesanan</span>
                    <span class="stat-icon green">▤</span>
                </div>
                <strong>—</strong>
                <p>Seluruh permohonan booking</p>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Menunggu Persetujuan</span>
                    <span class="stat-icon orange">◷</span>
                </div>
                <strong>—</strong>
                <p>Pemesanan yang perlu ditinjau</p>
            </article>
        </section>

        <section class="content-card">
            <div class="section-heading">
                <div>
                    <h2>Pemesanan Terbaru</h2>
                    <p>Ringkasan permohonan penggunaan fasilitas kampus.</p>
                </div>
                <a href="#" class="text-link">Lihat semua →</a>
            </div>

            <div class="empty-state">
                <div class="empty-icon">▤</div>
                <h3>Data pemesanan akan ditampilkan di sini</h3>
                <p>Setelah struktur tabel booking dihubungkan, daftar pemesanan terbaru akan muncul di bagian ini.</p>
            </div>
        </section>

    </main>
</div>

<script>
const logoutModal = document.getElementById("logoutModal");
const openLogoutModal = document.getElementById("openLogoutModal");
const cancelLogout = document.getElementById("cancelLogout");

function showLogoutModal() {
    logoutModal.classList.add("show");
    logoutModal.setAttribute("aria-hidden", "false");
    cancelLogout.focus();
}

function hideLogoutModal() {
    logoutModal.classList.remove("show");
    logoutModal.setAttribute("aria-hidden", "true");
    openLogoutModal.focus();
}

openLogoutModal.addEventListener("click", showLogoutModal);
cancelLogout.addEventListener("click", hideLogoutModal);

logoutModal.addEventListener("click", function (event) {
    if (event.target === logoutModal) {
        hideLogoutModal();
    }
});

document.addEventListener("keydown", function (event) {
    if (
        event.key === "Escape" &&
        logoutModal.classList.contains("show")
    ) {
        hideLogoutModal();
    }
});
</script>

</body>
</html>
