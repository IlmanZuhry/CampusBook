
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


// Token CSRF untuk formulir peminjaman.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Proses pengajuan peminjaman pada file yang sama.
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["form_action"] ?? "") === "create_booking") {
    $back = static function (string $status): void {
        header("Location: fasilitas.php?booking=" . rawurlencode($status));
        exit;
    };

    $sessionToken = $_SESSION["csrf_token"] ?? "";
    $formToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($formToken) ||
        $sessionToken === "" ||
        !hash_equals($sessionToken, $formToken)
    ) {
        $back("invalid_token");
    }

    $studentId = (int) $_SESSION["user_id"];
    $facilityId = filter_var($_POST["facility_id"] ?? null, FILTER_VALIDATE_INT);
    $date = trim((string) ($_POST["booking_date"] ?? ""));
    $start = trim((string) ($_POST["start_time"] ?? ""));
    $end = trim((string) ($_POST["end_time"] ?? ""));
    $purpose = trim((string) ($_POST["purpose"] ?? ""));

    $dateObject = DateTimeImmutable::createFromFormat("!Y-m-d", $date);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $validDate = $dateObject !== false
        && $dateObject->format("Y-m-d") === $date
        && ($dateErrors === false || (
            $dateErrors["warning_count"] === 0 &&
            $dateErrors["error_count"] === 0
        ));

    $validTime = static function (string $time): bool {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    };

    if (
        !$facilityId || $facilityId < 1 || !$validDate ||
        !$validTime($start) || !$validTime($end) ||
        $start >= $end || $purpose === "" || mb_strlen($purpose) > 2000
    ) {
        $back("invalid_data");
    }

    if ($dateObject < new DateTimeImmutable("today")) {
        $back("past_date");
    }

    try {
        $conn->begin_transaction();

        // Mengunci baris fasilitas selama pemeriksaan jadwal dan penyimpanan.
        $stmt = $conn->prepare(
            "SELECT id, status, opening_time, closing_time
             FROM facilities
             WHERE id = ?
             LIMIT 1
             FOR UPDATE"
        );
        if (!$stmt) {
            throw new RuntimeException("Gagal menyiapkan query fasilitas.");
        }

        $stmt->bind_param("i", $facilityId);
        $stmt->execute();
        $facility = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$facility || $facility["status"] !== "active") {
            $conn->rollback();
            $back("facility_unavailable");
        }

        $opening = substr($facility["opening_time"], 0, 5);
        $closing = substr($facility["closing_time"], 0, 5);

        if ($start < $opening || $end > $closing) {
            $conn->rollback();
            $back("outside_hours");
        }

        // Dua rentang bentrok jika waktu mulai booking lama < waktu akhir baru
        // dan waktu akhir booking lama > waktu mulai baru.
        $stmt = $conn->prepare(
            "SELECT id
             FROM bookings
             WHERE facility_id = ?
               AND booking_date = ?
               AND status IN ('pending', 'approved')
               AND start_time < ?
               AND end_time > ?
             LIMIT 1"
        );
        if (!$stmt) {
            throw new RuntimeException("Gagal menyiapkan pemeriksaan jadwal.");
        }

        $startSql = $start . ":00";
        $endSql = $end . ":00";
        $stmt->bind_param("isss", $facilityId, $date, $endSql, $startSql);
        $stmt->execute();
        $conflict = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($conflict) {
            $conn->rollback();
            $back("schedule_conflict");
        }

        $bookingCode = "CB-" . date("Ymd") . "-" . strtoupper(bin2hex(random_bytes(4)));
        $status = "pending";

        $stmt = $conn->prepare(
            "INSERT INTO bookings
                (booking_code, user_id, facility_id, booking_date,
                 start_time, end_time, purpose, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            throw new RuntimeException("Gagal menyiapkan penyimpanan peminjaman.");
        }

        $stmt->bind_param(
            "siisssss",
            $bookingCode,
            $studentId,
            $facilityId,
            $date,
            $startSql,
            $endSql,
            $purpose,
            $status
        );

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException("Penyimpanan peminjaman gagal.");
        }

        $stmt->close();
        $conn->commit();
        $back("success");
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
            // Abaikan jika transaksi sudah tidak aktif.
        }

        error_log("Pengajuan peminjaman gagal: " . $e->getMessage());
        $back("server_error");
    }
}

$bookingMessages = [
    "success" => ["success", "Pengajuan peminjaman berhasil dikirim dan menunggu persetujuan admin."],
    "invalid_token" => ["error", "Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi."],
    "invalid_data" => ["error", "Data peminjaman tidak valid. Periksa kembali tanggal, jam, dan keperluan."],
    "past_date" => ["error", "Tanggal peminjaman tidak boleh berada di masa lalu."],
    "facility_unavailable" => ["error", "Fasilitas tidak tersedia atau sudah dinonaktifkan."],
    "outside_hours" => ["error", "Jam peminjaman harus berada dalam jam operasional fasilitas."],
    "schedule_conflict" => ["error", "Jadwal tersebut bentrok dengan pengajuan lain. Silakan pilih waktu lain."],
    "server_error" => ["error", "Pengajuan belum dapat disimpan karena terjadi kesalahan server."],
    "invalid_request" => ["error", "Permintaan tidak valid."]
];
$bookingNotice = $bookingMessages[$_GET["booking"] ?? ""] ?? null;


$pageTitle = "Fasilitas";
$pageDescription = "Jelajahi fasilitas kampus yang tersedia.";

function escapeHtml($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function formatRupiah($amount): string
{
    return "Rp " . number_format((float) $amount, 0, ",", ".");
}

$keyword = trim($_GET["search"] ?? "");
$category = trim($_GET["category"] ?? "");

$allowedCategories = [
    "Aula",
    "Laboratorium",
    "Lapangan",
    "Ruang Diskusi",
    "Lainnya"
];

if (!in_array($category, $allowedCategories, true)) {
    $category = "";
}

$sql = "
    SELECT
        id, name, category, description, image,
        location, capacity, hourly_rate,
        opening_time, closing_time
    FROM facilities
    WHERE status = 'active'
";

$params = [];
$types = "";

if ($keyword !== "") {
    $sql .= " AND (name LIKE ? OR location LIKE ? OR description LIKE ?)";
    $term = "%" . $keyword . "%";

    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= "sss";
}

if ($category !== "") {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

$sql .= " ORDER BY name ASC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Query katalog fasilitas gagal: " . $conn->error);
    http_response_code(500);
    exit("Terjadi kesalahan saat memuat fasilitas.");
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    error_log("Eksekusi katalog fasilitas gagal: " . $stmt->error);
    http_response_code(500);
    exit("Terjadi kesalahan saat memuat fasilitas.");
}

$result = $stmt->get_result();
$facilities = [];

while ($row = $result->fetch_assoc()) {
    $facilities[] = $row;
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fasilitas | CampusBook</title>

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

        <section class="catalog-page">
            
            <?php if ($bookingNotice): ?>
                <div
                    class="booking-result-overlay"
                    id="bookingResultModal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="bookingResultTitle"
                >
                    <div class="booking-result-card">
                        <div class="booking-result-icon <?= $bookingNotice[0] === "success" ? "is-success" : "is-error" ?>">
                            <i class="fas <?= $bookingNotice[0] === "success" ? "fa-check" : "fa-xmark" ?>"></i>
                        </div>

                        <h2 id="bookingResultTitle">
                            <?= $bookingNotice[0] === "success" ? "Pengajuan Berhasil!" : "Pengajuan Gagal" ?>
                        </h2>

                        <p><?= escapeHtml($bookingNotice[1]) ?></p>

                        <button type="button" class="catalog-button" id="closeBookingResult">
                            Mengerti
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <div class="catalog-heading">
                <div class="catalog-count">
                    <strong><?= count($facilities) ?></strong>
                    fasilitas ditemukan
                </div>
            </div>

            <form method="GET" class="catalog-toolbar">
                <label class="catalog-search">
                    <i class="fas fa-search"></i>
                    <input
                        type="search"
                        name="search"
                        value="<?= escapeHtml($keyword) ?>"
                        placeholder="Cari nama, lokasi, atau deskripsi..."
                        aria-label="Cari fasilitas"
                    >
                </label>

                <select name="category" aria-label="Filter kategori">
                    <option value="">Semua kategori</option>

                    <?php foreach ($allowedCategories as $item): ?>
                        <option
                            value="<?= escapeHtml($item) ?>"
                            <?= $category === $item ? "selected" : "" ?>
                        >
                            <?= escapeHtml($item) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="catalog-button">
                    <i class="fas fa-filter"></i> Cari
                </button>

                <?php if ($keyword !== "" || $category !== ""): ?>
                    <a href="fasilitas.php" class="catalog-button secondary">Reset</a>
                <?php endif; ?>
            </form>

            <div class="catalog-grid">
                <?php if ($facilities): ?>

                    <?php foreach ($facilities as $facility): ?>
                        <?php
                        $imageName = basename((string) ($facility["image"] ?? ""));
                        $imagePath = __DIR__ . "/../assets/uploads/facilities/" . $imageName;
                        $imageUrl = "../assets/uploads/facilities/" . rawurlencode($imageName);
                        $hasImage = $imageName !== "" && is_file($imagePath);

                        $description = trim((string) ($facility["description"] ?? ""));
                        if ($description === "") {
                            $description = "Belum ada deskripsi fasilitas.";
                        }
                        ?>

                        <article class="catalog-card">
                            <div class="catalog-photo">
                                <?php if ($hasImage): ?>
                                    <img
                                        src="<?= escapeHtml($imageUrl) ?>"
                                        alt="<?= escapeHtml($facility["name"]) ?>"
                                        loading="lazy"
                                    >
                                <?php else: ?>
                                    <div class="catalog-photo-placeholder">
                                        <i class="fas fa-building"></i>
                                    </div>
                                <?php endif; ?>

                                <span class="catalog-category">
                                    <?= escapeHtml($facility["category"] ?: "Lainnya") ?>
                                </span>

                                <span class="catalog-available">
                                    <i class="fas fa-circle"></i> Aktif
                                </span>
                            </div>

                            <div class="catalog-card-body">
                                <h3><?= escapeHtml($facility["name"]) ?></h3>

                                <p class="catalog-description">
                                    <?= escapeHtml($description) ?>
                                </p>

                                <div class="catalog-meta">
                                    <span>
                                        <i class="fas fa-location-dot"></i>
                                        <?= escapeHtml($facility["location"]) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-users"></i>
                                        Kapasitas <?= (int) $facility["capacity"] ?> orang
                                    </span>
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        <?= escapeHtml(substr($facility["opening_time"], 0, 5)) ?>
                                        –
                                        <?= escapeHtml(substr($facility["closing_time"], 0, 5)) ?>
                                    </span>
                                </div>

                                <div class="catalog-price">
                                    <span>Tarif per jam</span>
                                    <strong><?= formatRupiah($facility["hourly_rate"]) ?></strong>
                                </div>

                                <div class="catalog-card-actions">
                                    <button
                                        type="button"
                                        class="catalog-button"
                                        data-facility-detail
                                        data-name="<?= escapeHtml($facility["name"]) ?>"
                                        data-category="<?= escapeHtml($facility["category"] ?: "Lainnya") ?>"
                                        data-location="<?= escapeHtml($facility["location"]) ?>"
                                        data-capacity="<?= (int) $facility["capacity"] ?>"
                                        data-rate="<?= formatRupiah($facility["hourly_rate"]) ?>"
                                        data-hours="<?= escapeHtml(substr($facility["opening_time"], 0, 5)) ?> – <?= escapeHtml(substr($facility["closing_time"], 0, 5)) ?>"
                                        data-description="<?= escapeHtml($description) ?>"
                                        data-image="<?= $hasImage ? escapeHtml($imageUrl) : "" ?>"
                                    >
                                        <i class="fas fa-circle-info"></i> Lihat Detail
                                    </button>
                                    

<button
    type="button"
    class="catalog-button"
    data-book-facility
    data-id="<?= (int) $facility['id'] ?>"
    data-name="<?= escapeHtml($facility['name']) ?>"
    data-opening="<?= escapeHtml(substr($facility['opening_time'], 0, 5)) ?>"
    data-closing="<?= escapeHtml(substr($facility['closing_time'], 0, 5)) ?>"
    data-rate="<?= (float) $facility['hourly_rate'] ?>"
>
    <i class="fas fa-calendar-plus"></i>
    Ajukan Peminjaman
</button>

                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>

                <?php else: ?>
                    <div class="catalog-empty">
                        <i class="fas fa-building-circle-exclamation"></i>
                        <h3>Fasilitas tidak ditemukan</h3>
                        <p>
                            Coba ubah kata pencarian atau kategori.
                            Fasilitas aktif yang ditambahkan admin akan muncul di sini.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<div class="catalog-modal" id="facilityDetailModal" aria-hidden="true">
    <div class="catalog-modal-card" role="dialog" aria-modal="true"
         aria-labelledby="detailFacilityName">

        <button type="button" class="catalog-modal-close"
                id="closeFacilityDetail" aria-label="Tutup">&times;</button>

        <div class="catalog-modal-photo" id="detailPhotoContainer">
            <img id="detailFacilityImage" src="" alt="" hidden>
        </div>

        <div class="catalog-modal-content">
            <span class="catalog-eyebrow" id="detailFacilityCategory"></span>
            <h2 id="detailFacilityName"></h2>

            <div class="catalog-meta">
                <span><i class="fas fa-location-dot"></i><span id="detailFacilityLocation"></span></span>
                <span><i class="fas fa-users"></i><span id="detailFacilityCapacity"></span></span>
                <span><i class="fas fa-clock"></i><span id="detailFacilityHours"></span></span>
                <span><i class="fas fa-money-bill-wave"></i><span id="detailFacilityRate"></span></span>
            </div>

            <p id="detailFacilityDescription"></p>
        </div>
    </div>
</div>

<div class="catalog-modal" id="bookingModal" aria-hidden="true">
    <div class="catalog-modal-card booking-modal-card"
         role="dialog"
         aria-modal="true"
         aria-labelledby="bookingModalTitle">

        <button type="button"
                class="catalog-modal-close"
                id="closeBookingModal"
                aria-label="Tutup">&times;</button>

        <div class="catalog-modal-content">
            <span class="catalog-eyebrow">CAMPUSBOOK</span>
            <h2 id="bookingModalTitle">Ajukan Peminjaman</h2>
            <p id="bookingFacilityName"></p>

            <form method="POST" action="fasilitas.php" id="bookingForm">
                <input type="hidden" name="form_action" value="create_booking">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION["csrf_token"]) ?>">
                <input type="hidden" name="facility_id" id="bookingFacilityId">

                <label for="bookingDate">Tanggal peminjaman</label>
                <input type="date" id="bookingDate" name="booking_date" required>

                <div class="booking-time-fields">
                    <div>
                        <label for="bookingStart">Jam mulai</label>
                        <input type="time" id="bookingStart" name="start_time" required>
                    </div>
                    <div>
                        <label for="bookingEnd">Jam selesai</label>
                        <input type="time" id="bookingEnd" name="end_time" required>
                    </div>
                </div>

                <label for="bookingPurpose">Keperluan</label>
                <textarea id="bookingPurpose" name="purpose"
                          rows="3" maxlength="2000"
                          placeholder="Jelaskan keperluan peminjaman..."
                          required></textarea>

                <p id="bookingOperatingHours"></p>

                <div class="catalog-card-actions">
                    <button type="button" class="catalog-button secondary"
                            id="cancelBooking">Batal</button>
                    <button type="submit" class="catalog-button">
                        <i class="fas fa-paper-plane"></i>
                        Kirim Pengajuan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    
document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("facilityDetailModal");

    if (!modal) return;

    const closeButton = document.getElementById("closeFacilityDetail");
    const detailImage = document.getElementById("detailFacilityImage");
    const photoContainer = document.getElementById("detailPhotoContainer");

    function closeDetail() {
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
    }

    document.querySelectorAll("[data-facility-detail]").forEach(function (button) {
        button.addEventListener("click", function () {
            const data = button.dataset;

            document.getElementById("detailFacilityName").textContent = data.name;
            document.getElementById("detailFacilityCategory").textContent = data.category;
            document.getElementById("detailFacilityLocation").textContent = data.location;
            document.getElementById("detailFacilityCapacity").textContent =
                "Kapasitas " + data.capacity + " orang";
            document.getElementById("detailFacilityHours").textContent = data.hours;
            document.getElementById("detailFacilityRate").textContent = data.rate + "/jam";
            document.getElementById("detailFacilityDescription").textContent =
                data.description;

            if (data.image) {
                detailImage.src = data.image;
                detailImage.alt = data.name;
                detailImage.hidden = false;
                photoContainer.style.display = "block";
            } else {
                detailImage.removeAttribute("src");
                detailImage.hidden = true;
                photoContainer.style.display = "none";
            }

            modal.classList.add("show");
            modal.setAttribute("aria-hidden", "false");
            document.body.style.overflow = "hidden";
        });
    });

    closeButton?.addEventListener("click", closeDetail);

    modal.addEventListener("click", function (event) {
        if (event.target === modal) {
            closeDetail();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && modal.classList.contains("show")) {
            closeDetail();
        }
    });
    
const bookingModal = document.getElementById("bookingModal");
const bookingForm = document.getElementById("bookingForm");

function closeBookingModal() {
    bookingModal.classList.remove("show");
    bookingModal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
}

document.querySelectorAll("[data-book-facility]").forEach(button => {
    button.addEventListener("click", () => {
        const data = button.dataset;

        bookingForm.reset();

        document.getElementById("bookingFacilityId").value = data.id;
        document.getElementById("bookingFacilityName").textContent = data.name;
        document.getElementById("bookingOperatingHours").textContent =
            `Jam operasional: ${data.opening}–${data.closing}`;

        document.getElementById("bookingDate").min =
            new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
                .toISOString().slice(0, 10);

        document.getElementById("bookingStart").min = data.opening;
        document.getElementById("bookingStart").max = data.closing;
        document.getElementById("bookingEnd").min = data.opening;
        document.getElementById("bookingEnd").max = data.closing;

        bookingModal.classList.add("show");
        bookingModal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
    });
    });

        document.getElementById("closeBookingModal")
            .addEventListener("click", closeBookingModal);

        document.getElementById("cancelBooking")
            .addEventListener("click", closeBookingModal);

        bookingModal.addEventListener("click", event => {
            if (event.target === bookingModal) closeBookingModal();
        });

        document.addEventListener("keydown", event => {
            if (event.key === "Escape" && bookingModal.classList.contains("show")) {
                closeBookingModal();
            }
        });

        bookingForm.addEventListener("submit", event => {
            const start = document.getElementById("bookingStart").value;
            const end = document.getElementById("bookingEnd").value;

            if (end <= start) {
                event.preventDefault();
                alert("Jam selesai harus lebih akhir daripada jam mulai.");
            }
            });

        const bookingResultModal = document.getElementById("bookingResultModal");
        const closeBookingResult = document.getElementById("closeBookingResult");

        function dismissBookingResult() {
            if (!bookingResultModal) return;

            bookingResultModal.remove();
            document.body.style.overflow = "";
        }

        if (bookingResultModal) {
            document.body.style.overflow = "hidden";

            closeBookingResult?.addEventListener("click", dismissBookingResult);

            bookingResultModal.addEventListener("click", event => {
                if (event.target === bookingResultModal) {
                    dismissBookingResult();
                }
            });

    document.addEventListener("keydown", function closeResultOnEscape(event) {
        if (event.key === "Escape" && bookingResultModal.isConnected) {
            dismissBookingResult();
            document.removeEventListener("keydown", closeResultOnEscape);
        }
    });
    }

});

</script>
</body>
</html>
