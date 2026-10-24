
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
$pageDescription = "Ringkasan aktivitas dan pemesanan fasilitas kampus.";

$studentId = (int) $_SESSION["user_id"];
$studentName = $_SESSION["name"] ?? "Mahasiswa";
$firstName = explode(" ", trim($studentName))[0];

function dashboardCount(mysqli $conn, string $sql, int $studentId): int
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    return (int) ($total ?? 0);
}

// Pemesanan aktif
$activeBookings = dashboardCount(
    $conn,
    "SELECT COUNT(*) FROM bookings
     WHERE user_id = ? AND status IN ('pending', 'approved')",
    $studentId
);

// Pemesanan selesai
$completedBookings = dashboardCount(
    $conn,
    "SELECT COUNT(*) FROM bookings
     WHERE user_id = ? AND status = 'completed'",
    $studentId
);

// Total durasi pemakaian dari pemesanan yang selesai
$totalUsageMinutes = 0;

$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time)) / 60), 0)
     FROM bookings
     WHERE user_id = ? AND status = 'completed'"
);

if ($stmt) {
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $stmt->bind_result($totalUsageMinutes);
    $stmt->fetch();
    $stmt->close();
}

$totalUsageHours = round(((float) $totalUsageMinutes) / 60, 1);

// Pembayaran tertunda
$pendingPayments = dashboardCount(
    $conn,
    "SELECT COUNT(*)
     FROM payments p
     INNER JOIN bookings b ON b.id = p.booking_id
     WHERE b.user_id = ? AND p.payment_status = 'pending'",
    $studentId
);

// Fasilitas aktif, diurutkan berdasarkan jumlah pemesanan
$facilities = [];

$sqlFacilities = "
    SELECT
        f.id,
        f.name,
        f.description,
        f.location,
        f.capacity,
        f.hourly_rate,
        COUNT(b.id) AS booking_count
    FROM facilities f
    LEFT JOIN bookings b
        ON b.facility_id = f.id
        AND b.status IN ('approved', 'completed')
    WHERE f.status = 'active'
    GROUP BY
        f.id, f.name, f.description, f.location,
        f.capacity, f.hourly_rate
    ORDER BY booking_count DESC, f.name ASC
    LIMIT 4
";

$resultFacilities = $conn->query($sqlFacilities);

if ($resultFacilities) {
    while ($row = $resultFacilities->fetch_assoc()) {
        $facilities[] = $row;
    }
}

function rupiahDashboard($amount): string
{
    return "Rp " . number_format((float) $amount, 0, ",", ".");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Mahasiswa | CampusBook</title>

    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/user.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

<div class="admin-layout">
    <?php require_once __DIR__ . "/../includes/user_sidebar.php"; ?>

    <main class="admin-main">
        <?php require_once __DIR__ . "/../includes/user_header.php"; ?>

        <section class="user-dashboard">

            <!-- Banner sambutan -->
            <div class="student-banner">
                <div class="student-banner-content">
                    <span class="student-banner-label">
                        STUDENT DASHBOARD
                    </span>

                    <h2>
                        Halo, <?= htmlspecialchars($firstName) ?>!
                        <span>👋</span>
                    </h2>

                    <p>
                        Senang melihatmu kembali. Temukan fasilitas kampus
                        dan kelola pemesananmu dalam satu tempat.
                    </p>

                    <a href="fasilitas.php" class="student-primary-button">
                        <i class="fas fa-search"></i>
                        Jelajahi Fasilitas
                    </a>
                </div>

                <div class="student-banner-art" aria-hidden="true">
                    <div class="banner-building">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="banner-calendar">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
            </div>

            <!-- Statistik -->
            <div class="dashboard-section-heading">
                <div>
                    <h2>Ringkasan Aktivitas</h2>
                    <p>Ringkasan pemakaian fasilitas dan status pemesananmu.</p>
                </div>
            </div>

            <div class="student-stats-grid">
                <a href="pemesanan.php" class="student-stat-card">
                    <div class="student-stat-top">
                        <span>Pemesanan Aktif</span>
                        <div class="student-stat-icon blue">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>

                    <strong><?= number_format($activeBookings) ?></strong>
                    <p>Pemesanan menunggu atau disetujui</p>
                </a>

                <a href="pemesanan.php" class="student-stat-card">
                    <div class="student-stat-top">
                        <span>Pemesanan Selesai</span>
                        <div class="student-stat-icon green">
                            <i class="fas fa-circle-check"></i>
                        </div>
                    </div>

                    <strong><?= number_format($completedBookings) ?></strong>
                    <p>Total pemesanan yang selesai</p>
                </a>

                <div class="student-stat-card">
                    <div class="student-stat-top">
                        <span>Total Pemakaian</span>
                        <div class="student-stat-icon purple">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>

                    <strong><?= number_format($totalUsageHours, 1, ",", ".") ?>
                        <small>Jam</small>
                    </strong>
                    <p>Akumulasi durasi pemesanan selesai</p>
                </div>

                <a href="pembayaran.php" class="student-stat-card">
                    <div class="student-stat-top">
                        <span>Menunggu Pembayaran</span>
                        <div class="student-stat-icon orange">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>

                    <strong><?= number_format($pendingPayments) ?></strong>
                    <p>Transaksi dengan status tertunda</p>
                </a>
            </div>

            <!-- Fasilitas populer -->
            <section class="student-facilities-section">
                <div class="dashboard-section-heading">
                    <div>
                        <h2>Fasilitas Populer</h2>
                        <p>Temukan fasilitas kampus untuk kebutuhanmu.</p>
                    </div>

                    <a href="fasilitas.php" class="student-view-all">
                        Lihat Semua <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <?php if (count($facilities) > 0): ?>
                    <div class="student-facility-grid">
                        <?php foreach ($facilities as $index => $facility): ?>
                            <?php
                            $facilityIcons = [
                                "fa-building",
                                "fa-desktop",
                                "fa-volleyball",
                                "fa-users"
                            ];

                            $facilityIcon = $facilityIcons[$index % count($facilityIcons)];
                            ?>

                            <article class="student-facility-card">
                                <div class="student-facility-image facility-tone-<?= $index + 1 ?>">
                                    <i class="fas <?= $facilityIcon ?>"></i>
                                    <span class="facility-status">
                                        <i class="fas fa-circle"></i> Tersedia
                                    </span>
                                </div>

                                <div class="student-facility-body">
                                    <h3>
                                        <?= htmlspecialchars($facility["name"]) ?>
                                    </h3>

                                    <p class="student-facility-location">
                                        <i class="fas fa-location-dot"></i>
                                        <?= htmlspecialchars($facility["location"]) ?>
                                    </p>

                                    <div class="student-facility-meta">
                                        <span>
                                            <i class="fas fa-users"></i>
                                            <?= number_format((int) $facility["capacity"]) ?>
                                            orang
                                        </span>

                                        <strong>
                                            <?= rupiahDashboard($facility["hourly_rate"]) ?>/jam
                                        </strong>
                                    </div>

                                    <a href="fasilitas.php" class="student-facility-button">
                                        Lihat Fasilitas
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="student-empty-facilities">
                        <div class="student-empty-icon">
                            <i class="fas fa-building-circle-exclamation"></i>
                        </div>
                        <h3>Belum ada fasilitas tersedia</h3>
                        <p>
                            Fasilitas aktif akan ditampilkan di sini setelah
                            tersedia di database CampusBook.
                        </p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Akses cepat -->
            <section class="student-quick-section">
                <div class="dashboard-section-heading">
                    <div>
                        <h2>Akses Cepat</h2>
                        <p>Buka layanan yang kamu butuhkan.</p>
                    </div>
                </div>

                <div class="student-quick-grid">
                    <a href="pemesanan.php" class="student-quick-card">
                        <div class="quick-icon blue">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div>
                            <strong>Pemesanan Saya</strong>
                            <span>Kelola jadwal pemesanan</span>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </a>

                    <a href="pembayaran.php" class="student-quick-card">
                        <div class="quick-icon green">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div>
                            <strong>Pembayaran</strong>
                            <span>Periksa status pembayaran</span>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </a>

                    <a href="notifikasi.php" class="student-quick-card">
                        <div class="quick-icon orange">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div>
                            <strong>Notifikasi</strong>
                            <span>Lihat informasi terbaru</span>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </section>

        </section>
    </main>
</div>

</body>
</html>
