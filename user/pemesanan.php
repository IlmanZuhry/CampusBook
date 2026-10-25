
<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "mahasiswa"
) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

$pageTitle = "Pemesanan Saya";
$pageDescription = "Pantau pengajuan peminjaman fasilitas kampus.";

function bookingEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function bookingStatusLabel(string $status): string
{
    $labels = [
        "pending"   => "Menunggu Persetujuan",
        "approved"  => "Disetujui",
        "rejected"  => "Ditolak",
        "cancelled" => "Dibatalkan",
        "completed" => "Selesai",
    ];

    return $labels[$status] ?? ucfirst($status);
}

function bookingRedirect(string $status): never
{
    header("Location: pemesanan.php?result=" . urlencode($status));
    exit;
}

// Token keamanan formulir.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Proses pembatalan pengajuan.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";
    $bookingId = filter_input(INPUT_POST, "booking_id", FILTER_VALIDATE_INT);

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {
        bookingRedirect("invalid_token");
    }

    if (!$bookingId || $bookingId < 1) {
        bookingRedirect("invalid_data");
    }

    $studentId = (int) $_SESSION["user_id"];

    // Hanya pemilik pengajuan yang berstatus pending dapat membatalkannya.
    $stmt = $conn->prepare(
        "UPDATE bookings
         SET status = 'cancelled'
         WHERE id = ?
           AND user_id = ?
           AND status = 'pending'"
    );

    if (!$stmt) {
        error_log("Persiapan pembatalan booking gagal: " . $conn->error);
        bookingRedirect("error");
    }

    $stmt->bind_param("ii", $bookingId, $studentId);
    $executed = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if (!$executed) {
        bookingRedirect("error");
    }

    bookingRedirect($affected > 0 ? "cancelled" : "cannot_cancel");
}

// Filter dan pencarian.
$keyword = trim($_GET["search"] ?? "");
$statusFilter = trim($_GET["status"] ?? "");

$allowedStatuses = [
    "pending",
    "approved",
    "rejected",
    "cancelled",
    "completed",
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = "";
}

$studentId = (int) $_SESSION["user_id"];

$sql = "
    SELECT
        b.id,
        b.booking_code,
        b.booking_date,
        b.start_time,
        b.end_time,
        b.purpose,
        b.status,
        b.admin_note,
        b.created_at,
        f.name AS facility_name,
        f.category AS facility_category,
        f.location AS facility_location,
        f.hourly_rate
    FROM bookings b
    LEFT JOIN facilities f ON f.id = b.facility_id
    WHERE b.user_id = ?
";

$params = [$studentId];
$types = "i";

if ($keyword !== "") {
    $sql .= " AND (
        b.booking_code LIKE ?
        OR f.name LIKE ?
        OR b.purpose LIKE ?
    )";

    $term = "%" . $keyword . "%";
    array_push($params, $term, $term, $term);
    $types .= "sss";
}

if ($statusFilter !== "") {
    $sql .= " AND b.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql .= " ORDER BY b.created_at DESC, b.id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Query pemesanan mahasiswa gagal: " . $conn->error);
    http_response_code(500);
    exit("Terjadi kesalahan saat memuat pemesanan.");
}

$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    error_log("Eksekusi query pemesanan gagal: " . $stmt->error);
    http_response_code(500);
    exit("Terjadi kesalahan saat memuat pemesanan.");
}

$result = $stmt->get_result();
$bookings = [];

while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

$stmt->close();

// Hitung statistik khusus mahasiswa yang sedang login.
$summary = [
    "total" => 0,
    "pending" => 0,
    "approved" => 0,
    "rejected" => 0,
];

$summaryStmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'pending'), 0) AS pending_count,
        COALESCE(SUM(status = 'approved'), 0) AS approved_count,
        COALESCE(SUM(status = 'rejected'), 0) AS rejected_count
     FROM bookings
     WHERE user_id = ?"
);

if ($summaryStmt) {
    $summaryStmt->bind_param("i", $studentId);
    $summaryStmt->execute();
    $summaryResult = $summaryStmt->get_result();
    $summaryRow = $summaryResult->fetch_assoc();

    if ($summaryRow) {
        $summary = [
            "total" => (int) $summaryRow["total"],
            "pending" => (int) $summaryRow["pending_count"],
            "approved" => (int) $summaryRow["approved_count"],
            "rejected" => (int) $summaryRow["rejected_count"],
        ];
    }

    $summaryStmt->close();
}

$messages = [
    "cancelled" => ["success", "Pengajuan berhasil dibatalkan."],
    "cannot_cancel" => ["error", "Pengajuan tidak ditemukan atau tidak dapat dibatalkan karena statusnya sudah berubah."],
    "invalid_token" => ["error", "Permintaan tidak valid. Silakan muat ulang halaman."],
    "invalid_data" => ["error", "Data pembatalan tidak valid."],
    "error" => ["error", "Terjadi kesalahan saat membatalkan pengajuan."],
];

$notice = $messages[$_GET["result"] ?? ""] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemesanan Saya | CampusBook</title>

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

        <section class="my-bookings-page">

            
            


            <?php if ($notice): ?>
                <div class="booking-notice <?= bookingEscape($notice[0]) ?>"
                     role="status">
                    <i class="fa-solid <?= $notice[0] === "success"
                        ? "fa-circle-check"
                        : "fa-circle-exclamation" ?>"></i>
                    <?= bookingEscape($notice[1]) ?>
                </div>
            <?php endif; ?>

            <div class="my-bookings-stats">
                <article class="my-booking-stat">
                    <span>Total Pemesanan</span>
                    <strong><?= number_format($summary["total"], 0, ",", ".") ?></strong>
                    <i class="fa-solid fa-calendar-check"></i>
                </article>

                <article class="my-booking-stat pending">
                    <span>Menunggu</span>
                    <strong><?= number_format($summary["pending"], 0, ",", ".") ?></strong>
                    <i class="fa-solid fa-clock"></i>
                </article>

                <article class="my-booking-stat approved">
                    <span>Disetujui</span>
                    <strong><?= number_format($summary["approved"], 0, ",", ".") ?></strong>
                    <i class="fa-solid fa-circle-check"></i>
                </article>

                <article class="my-booking-stat rejected">
                    <span>Ditolak</span>
                    <strong><?= number_format($summary["rejected"], 0, ",", ".") ?></strong>
                    <i class="fa-solid fa-circle-xmark"></i>
                </article>
            </div>

            <section class="my-bookings-panel">
                <div class="my-bookings-panel-heading">
                    <div>
                        <h3>Riwayat Peminjaman</h3>
                        <p>Daftar pengajuan yang tercatat atas akunmu.</p>
                    </div>
                    <span class="my-bookings-count">
                        <?= count($bookings) ?> data
                    </span>
                </div>

                <form method="GET" class="my-bookings-filter">
                    <label class="my-bookings-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input
                            type="search"
                            name="search"
                            value="<?= bookingEscape($keyword) ?>"
                            placeholder="Cari kode atau nama fasilitas..."
                        >
                    </label>

                    <select name="status" aria-label="Filter status">
                        <option value="">Semua Status</option>
                        <?php foreach ($allowedStatuses as $item): ?>
                            <option value="<?= bookingEscape($item) ?>"
                                <?= $statusFilter === $item ? "selected" : "" ?>>
                                <?= bookingEscape(bookingStatusLabel($item)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="catalog-button">
                        <i class="fa-solid fa-filter"></i> Filter
                    </button>

                    <?php if ($keyword !== "" || $statusFilter !== ""): ?>
                        <a href="pemesanan.php" class="catalog-button secondary">
                            Reset
                        </a>
                    <?php endif; ?>
                </form>

                <?php if (!$bookings): ?>
                    <div class="my-bookings-empty">
                        <div class="my-bookings-empty-icon">
                            <i class="fa-regular fa-calendar-xmark"></i>
                        </div>
                        <h3>
                            <?= $keyword !== "" || $statusFilter !== ""
                                ? "Pemesanan tidak ditemukan"
                                : "Belum ada pemesanan" ?>
                        </h3>
                        <p>
                            <?= $keyword !== "" || $statusFilter !== ""
                                ? "Coba ubah kata pencarian atau filter status."
                                : "Pengajuan peminjaman fasilitasmu akan muncul di sini." ?>
                        </p>

                        <?php if ($keyword === "" && $statusFilter === ""): ?>
                            <a href="fasilitas.php" class="catalog-button">
                                Jelajahi Fasilitas
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>

                    <div class="my-bookings-list">
                        <?php foreach ($bookings as $booking): ?>
                            <?php
                            $status = (string) $booking["status"];
                            $dateObject = DateTimeImmutable::createFromFormat(
                                "!Y-m-d",
                                (string) $booking["booking_date"]
                            );
                            $displayDate = $dateObject
                                ? $dateObject->format("d M Y")
                                : $booking["booking_date"];

                            $start = substr((string) $booking["start_time"], 0, 5);
                            $end = substr((string) $booking["end_time"], 0, 5);
                            $duration = (
                                strtotime($end) - strtotime($start)
                            ) / 3600;

                            $estimatedCost = max(0, $duration)
                                * (float) ($booking["hourly_rate"] ?? 0);
                            ?>

                            <article class="my-booking-card">
                                <div class="my-booking-card-top">
                                    <div class="my-booking-code">
                                        <span>KODE PEMESANAN</span>
                                        <strong><?= bookingEscape($booking["booking_code"]) ?></strong>
                                    </div>

                                    <span class="my-booking-status <?= bookingEscape($status) ?>">
                                        <?= bookingEscape(bookingStatusLabel($status)) ?>
                                    </span>
                                </div>

                                <div class="my-booking-main">
                                    <div class="my-booking-facility-icon">
                                        <i class="fa-solid fa-building"></i>
                                    </div>

                                    <div class="my-booking-facility">
                                        <h4><?= bookingEscape($booking["facility_name"] ?? "Fasilitas tidak ditemukan") ?></h4>
                                        <p>
                                            <i class="fa-solid fa-location-dot"></i>
                                            <?= bookingEscape($booking["facility_location"] ?? "Lokasi tidak tersedia") ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="my-booking-details">
                                    <div>
                                        <span><i class="fa-regular fa-calendar"></i> Tanggal</span>
                                        <strong><?= bookingEscape($displayDate) ?></strong>
                                    </div>

                                    <div>
                                        <span><i class="fa-regular fa-clock"></i> Waktu</span>
                                        <strong><?= bookingEscape($start) ?>–<?= bookingEscape($end) ?></strong>
                                    </div>

                                    <div>
                                        <span><i class="fa-solid fa-money-bill-wave"></i> Estimasi tarif</span>
                                        <strong>
                                            Rp <?= number_format($estimatedCost, 0, ",", ".") ?>
                                        </strong>
                                    </div>
                                </div>

                                <div class="my-booking-purpose">
                                    <span>Keperluan</span>
                                    <p><?= bookingEscape($booking["purpose"]) ?></p>
                                </div>

                                <div class="my-booking-actions">
                                    <button
                                        type="button"
                                        class="catalog-button secondary"
                                        data-booking-detail
                                        data-code="<?= bookingEscape($booking["booking_code"]) ?>"
                                        data-facility="<?= bookingEscape($booking["facility_name"] ?? "Fasilitas tidak ditemukan") ?>"
                                        data-location="<?= bookingEscape($booking["facility_location"] ?? "-") ?>"
                                        data-date="<?= bookingEscape($displayDate) ?>"
                                        data-time="<?= bookingEscape($start . " – " . $end) ?>"
                                        data-purpose="<?= bookingEscape($booking["purpose"]) ?>"
                                        data-status="<?= bookingEscape(bookingStatusLabel($status)) ?>"
                                        data-admin-note="<?= bookingEscape($booking["admin_note"] ?? "") ?>"
                                        data-cost="Rp <?= number_format($estimatedCost, 0, ",", ".") ?>"
                                    >
                                        <i class="fa-solid fa-eye"></i> Detail
                                    </button>

                                    <?php if ($status === "pending"): ?>
                                        <form method="POST" class="my-booking-cancel-form"
                                              data-cancel-booking>
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= bookingEscape($_SESSION["csrf_token"]) ?>">
                                            <input type="hidden" name="booking_id"
                                                   value="<?= (int) $booking["id"] ?>">

                                            <button type="submit"
                                                    class="my-booking-cancel-button">
                                                <i class="fa-solid fa-xmark"></i>
                                                Batalkan Pengajuan
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>
            </section>
        </section>
    </main>
</div>

<div class="catalog-modal" id="myBookingDetailModal" aria-hidden="true">
    <div class="catalog-modal-card my-booking-detail-modal"
         role="dialog" aria-modal="true"
         aria-labelledby="myBookingDetailTitle">

        <button type="button" class="catalog-modal-close"
                id="closeMyBookingDetail" aria-label="Tutup">&times;</button>

        <div class="catalog-modal-content">
            <span class="catalog-eyebrow">DETAIL PEMESANAN</span>
            <h2 id="myBookingDetailTitle">Detail Peminjaman</h2>

            <div class="my-booking-detail-grid">
                <div><span>Kode</span><strong id="detailBookingCode"></strong></div>
                <div><span>Fasilitas</span><strong id="detailBookingFacility"></strong></div>
                <div><span>Lokasi</span><strong id="detailBookingLocation"></strong></div>
                <div><span>Tanggal</span><strong id="detailBookingDate"></strong></div>
                <div><span>Waktu</span><strong id="detailBookingTime"></strong></div>
                <div><span>Estimasi tarif</span><strong id="detailBookingCost"></strong></div>
                <div><span>Status</span><strong id="detailBookingStatus"></strong></div>
                <div class="full-width">
                    <span>Keperluan</span>
                    <p id="detailBookingPurpose"></p>
                </div>

                <div class="full-width">
                    <span>Catatan Admin</span>
                    <p id="detailBookingAdminNote">Tidak ada catatan admin.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const detailModal = document.getElementById("myBookingDetailModal");
    const closeDetail = document.getElementById("closeMyBookingDetail");

    function hideDetailModal() {
        detailModal.classList.remove("show");
        detailModal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
    }

    document.querySelectorAll("[data-booking-detail]").forEach(button => {
        button.addEventListener("click", () => {
            const data = button.dataset;

            document.getElementById("detailBookingCode").textContent = data.code;
            document.getElementById("detailBookingFacility").textContent = data.facility;
            document.getElementById("detailBookingLocation").textContent = data.location;
            document.getElementById("detailBookingDate").textContent = data.date;
            document.getElementById("detailBookingTime").textContent = data.time;
            document.getElementById("detailBookingPurpose").textContent = data.purpose;
            document.getElementById("detailBookingStatus").textContent = data.status;
            document.getElementById("detailBookingCost").textContent = data.cost;
            document.getElementById("detailBookingAdminNote").textContent =
            data.adminNote || "Tidak ada catatan admin.";
            detailModal.classList.add("show");
            detailModal.setAttribute("aria-hidden", "false");
            document.body.style.overflow = "hidden";
        });
    });

    closeDetail.addEventListener("click", hideDetailModal);

    detailModal.addEventListener("click", event => {
        if (event.target === detailModal) hideDetailModal();
    });

    document.addEventListener("keydown", event => {
        if (event.key === "Escape" && detailModal.classList.contains("show")) {
            hideDetailModal();
        }
    });

    document.querySelectorAll("[data-cancel-booking]").forEach(form => {
        form.addEventListener("submit", event => {
            if (!confirm("Yakin ingin membatalkan pengajuan peminjaman ini?")) {
                event.preventDefault();
            }
        });
    });
});
</script>

<?php if ($notice): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const notice = document.querySelector(".booking-notice");
    if (notice) {
        notice.scrollIntoView({ behavior: "smooth", block: "center" });
    }
});
</script>
<?php endif; ?>

</body>
</html>
