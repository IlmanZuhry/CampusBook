
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

// Mengambil jumlah data untuk kartu statistik.
function getDashboardCount(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);

    if (!$result) {
        error_log("Dashboard query gagal: " . $conn->error);
        return 0;
    }

    $row = $result->fetch_row();
    $result->free();

    return (int) ($row[0] ?? 0);
}

$totalStudents = getDashboardCount(
    $conn,
    "SELECT COUNT(*) FROM students"
);

$totalFacilities = getDashboardCount(
    $conn,
    "SELECT COUNT(*) FROM facilities"
);

$totalBookings = getDashboardCount(
    $conn,
    "SELECT COUNT(*) FROM bookings"
);

$pendingBookings = getDashboardCount(
    $conn,
    "SELECT COUNT(*) FROM bookings WHERE status = 'pending'"
);

// Mengambil lima pemesanan terbaru beserta nama mahasiswa dan fasilitas.
$recentBookings = [];
$bookingQueryError = false;

$sqlRecent = "
    SELECT
        b.id,
        b.booking_code,
        b.booking_date,
        b.start_time,
        b.end_time,
        b.status,
        s.name AS student_name,
        f.name AS facility_name
    FROM bookings b
    LEFT JOIN students s ON s.id = b.user_id
    LEFT JOIN facilities f ON f.id = b.facility_id
    ORDER BY b.created_at DESC, b.id DESC
    LIMIT 5
";

$resultRecent = $conn->query($sqlRecent);

if ($resultRecent) {
    while ($row = $resultRecent->fetch_assoc()) {
        $recentBookings[] = $row;
    }

    $resultRecent->free();
} else {
    error_log("Query pemesanan terbaru gagal: " . $conn->error);
    $bookingQueryError = true;
}

function dashboardEscape($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function dashboardStatus(string $status): string
{
    $labels = [
        "pending"   => "Menunggu",
        "approved"  => "Disetujui",
        "rejected"  => "Ditolak",
        "cancelled" => "Dibatalkan",
        "completed" => "Selesai",
    ];

    return $labels[$status] ?? ucfirst($status);
}
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
                <h2>
                    Selamat datang,
                    <?= dashboardEscape($_SESSION["name"] ?? "Administrator") ?>!
                </h2>
                <p>
                    Kelola fasilitas dan pantau aktivitas pemesanan
                    kampus dari satu tempat.
                </p>
            </div>
            <div class="banner-symbol">CB</div>
        </section>

        <section class="stats-grid">

            <article class="stat-card">
                <div class="stat-top">
                    <span>Total Mahasiswa</span>
                    <span class="stat-icon blue">
                        <i class="fa-solid fa-users"></i>
                    </span>
                </div>
                <strong><?= number_format($totalStudents, 0, ",", ".") ?></strong>
                <p>Jumlah akun mahasiswa</p>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Total Fasilitas</span>
                    <span class="stat-icon purple">
                        <i class="fa-solid fa-building"></i>
                    </span>
                </div>
                <strong><?= number_format($totalFacilities, 0, ",", ".") ?></strong>
                <p>Seluruh fasilitas terdaftar</p>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Total Pemesanan</span>
                    <span class="stat-icon green">
                        <i class="fa-solid fa-calendar-check"></i>
                    </span>
                </div>
                <strong><?= number_format($totalBookings, 0, ",", ".") ?></strong>
                <p>Seluruh permohonan booking</p>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span>Menunggu Persetujuan</span>
                    <span class="stat-icon orange">
                        <i class="fa-solid fa-clock"></i>
                    </span>
                </div>
                <strong><?= number_format($pendingBookings, 0, ",", ".") ?></strong>
                <p>Pemesanan yang perlu ditinjau</p>
            </article>

        </section>

        <section class="content-card">
            <div class="section-heading">
                <div>
                    <h2>Pemesanan Terbaru</h2>
                    <p>Ringkasan permohonan penggunaan fasilitas kampus.</p>
                </div>
                <a href="pemesanan.php" class="text-link">
                    Lihat semua →
                </a>
            </div>

            <?php if ($bookingQueryError): ?>

                <div class="recent-booking-empty">
                    Data pemesanan gagal dimuat. Periksa log PHP untuk
                    mengetahui penyebabnya.
                </div>

            <?php elseif ($recentBookings): ?>

                <div class="recent-booking-list">

                    <?php foreach ($recentBookings as $booking): ?>
                        <?php
                        $status = (string) $booking["status"];
                        $bookingDate = date(
                            "d-m-Y",
                            strtotime($booking["booking_date"])
                        );
                        ?>

                        <article class="recent-booking-row">

                            <div>
                                <strong>
                                    <?= dashboardEscape($booking["booking_code"]) ?>
                                </strong>
                                <small><?= dashboardEscape($bookingDate) ?></small>
                            </div>

                            <div>
                                <strong>
                                    <?= dashboardEscape(
                                        $booking["student_name"] ?? "Mahasiswa tidak ditemukan"
                                    ) ?>
                                </strong>
                                <small>Mahasiswa</small>
                            </div>

                            <div>
                                <strong>
                                    <?= dashboardEscape(
                                        $booking["facility_name"] ?? "Fasilitas tidak ditemukan"
                                    ) ?>
                                </strong>
                                <small>
                                    <?= dashboardEscape(substr($booking["start_time"], 0, 5)) ?>
                                    –
                                    <?= dashboardEscape(substr($booking["end_time"], 0, 5)) ?>
                                </small>
                            </div>

                            <div>
                                <span class="booking-status <?= dashboardEscape($status) ?>">
                                    <?= dashboardEscape(dashboardStatus($status)) ?>
                                </span>
                            </div>

                            <div>
                                <a href="pemesanan.php" class="text-link">
                                    Detail →
                                </a>
                            </div>

                        </article>
                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="recent-booking-empty">
                    <div class="empty-icon">▤</div>
                    <h3>Belum ada pemesanan</h3>
                    <p>
                        Pengajuan mahasiswa akan muncul di sini
                        setelah tersimpan ke database.
                    </p>
                </div>

            <?php endif; ?>
        </section>

    </main>
</div>

</body>
</html>
