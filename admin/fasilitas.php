<?php
session_start();

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/admin_helpers.php";

require_admin();

const FACILITY_CATEGORIES = [
    "Aula",
    "Laboratorium",
    "Lapangan",
    "Ruang Diskusi",
    "Lainnya",
];

const UPLOAD_DIR = __DIR__ . "/../assets/uploads/facilities/";
const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

/**
 * Simpan gambar fasilitas. Mengembalikan nama file baru,
 * atau null jika tidak ada file / terjadi kesalahan ($error terisi).
 */
function save_facility_image(array $file, string &$error): ?string
{
    if (($file["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file["error"] !== UPLOAD_ERR_OK) {
        $error = "Gambar gagal diunggah. Coba lagi dengan file lain.";
        return null;
    }

    if ($file["size"] > MAX_IMAGE_BYTES) {
        $error = "Ukuran gambar maksimal 2 MB.";
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file["tmp_name"]);
    $allowed = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp",
    ];

    if (!isset($allowed[$mime])) {
        $error = "Format gambar harus JPG, PNG, atau WebP.";
        return null;
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
        $error = "Folder penyimpanan gambar tidak dapat dibuat.";
        return null;
    }

    $name = bin2hex(random_bytes(12)) . "." . $allowed[$mime];

    if (!move_uploaded_file($file["tmp_name"], UPLOAD_DIR . $name)) {
        $error = "Gambar tidak dapat disimpan di server.";
        return null;
    }

    return $name;
}

function delete_facility_image(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $name)) {
        $path = UPLOAD_DIR . $name;

        if (is_file($path)) {
            @unlink($path);
        }
    }
}

$error = "";
$showModal = false;
$formData = [
    "id"           => 0,
    "name"         => "",
    "category"     => "Aula",
    "description"  => "",
    "location"     => "",
    "capacity"     => "",
    "hourly_rate"  => "0",
    "opening_time" => "08:00",
    "closing_time" => "17:00",
    "status"       => "active",
];

/* =========================
   PROSES CREATE, UPDATE, TOGGLE, DELETE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_valid()) {
        $error = "Sesi formulir tidak valid. Muat ulang halaman.";
    } else {
        $action = $_POST["action"] ?? "";

        if (in_array($action, ["create", "update"], true)) {
            $formData = [
                "id"           => (int) ($_POST["id"] ?? 0),
                "name"         => trim($_POST["name"] ?? ""),
                "category"     => $_POST["category"] ?? "",
                "description"  => trim($_POST["description"] ?? ""),
                "location"     => trim($_POST["location"] ?? ""),
                "capacity"     => trim($_POST["capacity"] ?? ""),
                "hourly_rate"  => trim($_POST["hourly_rate"] ?? ""),
                "opening_time" => $_POST["opening_time"] ?? "",
                "closing_time" => $_POST["closing_time"] ?? "",
                "status"       => $_POST["status"] ?? "active",
            ];

            $showModal = true;
            $timePattern = '/^([01]\d|2[0-3]):[0-5]\d$/';

            if (
                $formData["name"] === "" ||
                $formData["location"] === "" ||
                $formData["capacity"] === ""
            ) {
                $error = "Nama, lokasi, dan kapasitas wajib diisi.";
            } elseif (!in_array($formData["category"], FACILITY_CATEGORIES, true)) {
                $error = "Kategori fasilitas tidak valid.";
            } elseif (
                !ctype_digit($formData["capacity"]) ||
                (int) $formData["capacity"] < 1
            ) {
                $error = "Kapasitas harus berupa angka minimal 1.";
            } elseif (
                !is_numeric($formData["hourly_rate"]) ||
                (float) $formData["hourly_rate"] < 0
            ) {
                $error = "Tarif per jam harus berupa angka 0 atau lebih.";
            } elseif (
                !preg_match($timePattern, $formData["opening_time"]) ||
                !preg_match($timePattern, $formData["closing_time"])
            ) {
                $error = "Format jam operasional tidak valid.";
            } elseif ($formData["closing_time"] <= $formData["opening_time"]) {
                $error = "Jam tutup harus lebih akhir dari jam buka.";
            } elseif (!in_array($formData["status"], ["active", "inactive"], true)) {
                $error = "Status fasilitas tidak valid.";
            } elseif ($action === "update" && $formData["id"] <= 0) {
                $error = "ID fasilitas tidak valid.";
            } else {
                $newImage = save_facility_image($_FILES["image"] ?? [], $error);

                if ($error === "") {
                    $capacity = (int) $formData["capacity"];
                    $rate = (float) $formData["hourly_rate"];
                    $opening = $formData["opening_time"] . ":00";
                    $closing = $formData["closing_time"] . ":00";
                    $errno = 0;

                    if ($action === "create") {
                        $saved = db_run(
                            $conn,
                            "INSERT INTO facilities
                                (name, category, description, image, location,
                                 capacity, hourly_rate, opening_time,
                                 closing_time, status)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            "sssssidsss",
                            [
                                $formData["name"],
                                $formData["category"],
                                $formData["description"],
                                $newImage,
                                $formData["location"],
                                $capacity,
                                $rate,
                                $opening,
                                $closing,
                                $formData["status"],
                            ],
                            $errno
                        );
                    } else {
                        $current = db_one(
                            $conn,
                            "SELECT image FROM facilities WHERE id = ? LIMIT 1",
                            "i",
                            [$formData["id"]]
                        );

                        if (!$current) {
                            $saved = false;
                            $error = "Fasilitas tidak ditemukan.";
                        } elseif ($newImage !== null) {
                            $saved = db_run(
                                $conn,
                                "UPDATE facilities
                                 SET name = ?, category = ?, description = ?,
                                     location = ?, capacity = ?, hourly_rate = ?,
                                     opening_time = ?, closing_time = ?,
                                     status = ?, image = ?
                                 WHERE id = ?",
                                "ssssidssssi",
                                [
                                    $formData["name"],
                                    $formData["category"],
                                    $formData["description"],
                                    $formData["location"],
                                    $capacity,
                                    $rate,
                                    $opening,
                                    $closing,
                                    $formData["status"],
                                    $newImage,
                                    $formData["id"],
                                ],
                                $errno
                            );

                            if ($saved !== false) {
                                delete_facility_image($current["image"]);
                            }
                        } else {
                            $saved = db_run(
                                $conn,
                                "UPDATE facilities
                                 SET name = ?, category = ?, description = ?,
                                     location = ?, capacity = ?, hourly_rate = ?,
                                     opening_time = ?, closing_time = ?,
                                     status = ?
                                 WHERE id = ?",
                                "ssssidsssi",
                                [
                                    $formData["name"],
                                    $formData["category"],
                                    $formData["description"],
                                    $formData["location"],
                                    $capacity,
                                    $rate,
                                    $opening,
                                    $closing,
                                    $formData["status"],
                                    $formData["id"],
                                ],
                                $errno
                            );
                        }
                    }

                    if ($saved !== false && $error === "") {
                        flash_set(
                            $action === "create"
                                ? "Fasilitas berhasil ditambahkan."
                                : "Data fasilitas berhasil diperbarui."
                        );
                        redirect_to("fasilitas.php");
                    }

                    // Penyimpanan gagal: buang gambar yang sudah terunggah.
                    if ($newImage !== null) {
                        delete_facility_image($newImage);
                    }

                    if ($error === "") {
                        $error = "Gagal menyimpan data fasilitas.";
                    }
                }
            }
        } elseif ($action === "toggle") {
            $id = (int) ($_POST["id"] ?? 0);

            $affected = db_run(
                $conn,
                "UPDATE facilities
                 SET status = IF(status = 'active', 'inactive', 'active')
                 WHERE id = ?",
                "i",
                [$id]
            );

            if ($affected === false) {
                $error = "Gagal mengubah status fasilitas.";
            } else {
                flash_set("Status fasilitas berhasil diubah.");
                redirect_to("fasilitas.php");
            }
        } elseif ($action === "delete") {
            $id = (int) ($_POST["id"] ?? 0);

            $usage = db_one(
                $conn,
                "SELECT COUNT(*) AS total FROM bookings WHERE facility_id = ?",
                "i",
                [$id]
            );

            if ($id <= 0) {
                $error = "ID fasilitas tidak valid.";
            } elseif ((int) ($usage["total"] ?? 0) > 0) {
                $error = "Fasilitas ini sudah memiliki riwayat pemesanan dan tidak dapat dihapus. Nonaktifkan saja agar tidak muncul untuk mahasiswa.";
            } else {
                $current = db_one(
                    $conn,
                    "SELECT image FROM facilities WHERE id = ? LIMIT 1",
                    "i",
                    [$id]
                );

                $affected = db_run(
                    $conn,
                    "DELETE FROM facilities WHERE id = ?",
                    "i",
                    [$id]
                );

                if ($affected === false) {
                    $error = "Gagal menghapus fasilitas.";
                } else {
                    delete_facility_image($current["image"] ?? null);
                    flash_set(
                        $affected > 0
                            ? "Fasilitas berhasil dihapus."
                            : "Fasilitas tidak ditemukan.",
                        $affected > 0 ? "success" : "warning"
                    );
                    redirect_to("fasilitas.php");
                }
            }
        } else {
            $error = "Aksi tidak dikenal.";
        }
    }
}

$flash = flash_get();

/* =========================
   PENCARIAN DAN FILTER
========================= */
$keyword = trim($_GET["search"] ?? "");
$categoryFilter = $_GET["category"] ?? "";
$statusFilter = $_GET["status"] ?? "";

$sql = "
    SELECT f.*,
           COALESCE(b.booked_seconds, 0) AS booked_seconds
    FROM facilities f
    LEFT JOIN (
        SELECT facility_id,
               SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time))) AS booked_seconds
        FROM bookings
        WHERE booking_date = CURDATE() AND status = 'approved'
        GROUP BY facility_id
    ) b ON b.facility_id = f.id
    WHERE 1 = 1
";

$params = [];
$types = "";

if ($keyword !== "") {
    $sql .= " AND (f.name LIKE ? OR f.location LIKE ?)";
    $like = "%" . $keyword . "%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

if (in_array($categoryFilter, FACILITY_CATEGORIES, true)) {
    $sql .= " AND f.category = ?";
    $params[] = $categoryFilter;
    $types .= "s";
}

if (in_array($statusFilter, ["active", "inactive"], true)) {
    $sql .= " AND f.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql .= " ORDER BY f.status ASC, f.name ASC";

$facilities = db_all($conn, $sql, $types, $params);

$counts = db_one(
    $conn,
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'active'), 0) AS active_total,
            COALESCE(SUM(status = 'inactive'), 0) AS inactive_total
     FROM facilities"
) ?? ["total" => 0, "active_total" => 0, "inactive_total" => 0];

/**
 * Label ketersediaan hari ini berdasarkan jam yang sudah disetujui.
 */
function availability(array $facility): array
{
    if ($facility["status"] !== "active") {
        return ["Nonaktif", "badge-gray"];
    }

    $operating = strtotime($facility["closing_time"]) - strtotime($facility["opening_time"]);
    $booked = (int) $facility["booked_seconds"];

    if ($booked <= 0) {
        return ["Tersedia", "badge-green"];
    }

    return $booked >= $operating
        ? ["Penuh hari ini", "badge-red"]
        : ["Sebagian terisi", "badge-orange"];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fasilitas - CampusBook</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/admin_modules.css">
</head>
<body>

<div class="admin-layout">
    <?php require_once __DIR__ . "/../includes/admin_sidebar.php"; ?>

    <main class="admin-main">
        <?php render_admin_header(
            "MANAJEMEN FASILITAS",
            "Fasilitas",
            "Kelola aula, laboratorium, dan lapangan yang dapat dipesan mahasiswa."
        ); ?>

        <?php render_flash($flash, $error); ?>

        <section class="summary-grid cols-3">
            <article class="summary-card">
                <span class="summary-icon purple">▣</span>
                <div>
                    <p>Total Fasilitas</p>
                    <strong><?= (int) $counts["total"] ?></strong>
                </div>
            </article>

            <article class="summary-card">
                <span class="summary-icon green">✓</span>
                <div>
                    <p>Fasilitas Aktif</p>
                    <strong><?= (int) $counts["active_total"] ?></strong>
                </div>
            </article>

            <article class="summary-card">
                <span class="summary-icon orange">◷</span>
                <div>
                    <p>Fasilitas Nonaktif</p>
                    <strong><?= (int) $counts["inactive_total"] ?></strong>
                </div>
            </article>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>Daftar Fasilitas</h2>
                    <p>Fasilitas aktif akan tampil dan dapat dipesan oleh mahasiswa.</p>
                </div>

                <button type="button" class="btn btn-primary" id="openAddModal">
                    + Tambah Fasilitas
                </button>
            </div>

            <form method="GET" class="toolbar">
                <div class="toolbar-search">
                    <span>⌕</span>
                    <input
                        type="search"
                        name="search"
                        placeholder="Cari nama atau lokasi fasilitas..."
                        value="<?= e($keyword) ?>"
                        aria-label="Cari fasilitas"
                    >
                </div>

                <select name="category" aria-label="Filter kategori">
                    <option value="">Semua Kategori</option>
                    <?php foreach (FACILITY_CATEGORIES as $category): ?>
                        <option value="<?= e($category) ?>" <?= $categoryFilter === $category ? "selected" : "" ?>>
                            <?= e($category) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" aria-label="Filter status">
                    <option value="">Semua Status</option>
                    <option value="active" <?= $statusFilter === "active" ? "selected" : "" ?>>Aktif</option>
                    <option value="inactive" <?= $statusFilter === "inactive" ? "selected" : "" ?>>Nonaktif</option>
                </select>

                <button type="submit" class="btn btn-soft">Cari</button>

                <?php if ($keyword !== "" || $categoryFilter !== "" || $statusFilter !== ""): ?>
                    <a href="fasilitas.php" class="btn btn-soft">Reset</a>
                <?php endif; ?>
            </form>

            <?php if ($facilities): ?>
                <div class="facility-grid">
                    <?php foreach ($facilities as $facility): ?>
                        <?php [$availLabel, $availClass] = availability($facility); ?>
                        <article class="facility-card <?= $facility["status"] === "active" ? "" : "is-off" ?>">
                            <div class="facility-photo">
                                <?php if (!empty($facility["image"])): ?>
                                    <img
                                        src="../assets/uploads/facilities/<?= e($facility["image"]) ?>"
                                        alt="Foto <?= e($facility["name"]) ?>"
                                        loading="lazy"
                                    >
                                <?php else: ?>
                                    <div class="photo-placeholder">
                                        <?= e(strtoupper(substr($facility["name"], 0, 1))) ?>
                                    </div>
                                <?php endif; ?>
                                <?= badge($availLabel, $availClass) ?>
                            </div>

                            <div class="facility-body">
                                <h3><?= e($facility["name"]) ?></h3>
                                <p class="facility-category"><?= e($facility["category"]) ?></p>

                                <ul class="facility-meta">
                                    <li><span>Kapasitas</span><strong><?= (int) $facility["capacity"] ?> orang</strong></li>
                                    <li><span>Lokasi</span><strong><?= e($facility["location"]) ?></strong></li>
                                    <li><span>Tarif</span><strong><?= $facility["hourly_rate"] > 0 ? rupiah($facility["hourly_rate"]) . " / jam" : "Gratis" ?></strong></li>
                                    <li><span>Jam operasional</span><strong><?= e(jam($facility["opening_time"])) ?> - <?= e(jam($facility["closing_time"])) ?></strong></li>
                                </ul>

                                <div class="facility-actions">
                                    <button
                                        type="button"
                                        class="btn btn-outline btn-sm edit-facility"
                                        data-id="<?= (int) $facility["id"] ?>"
                                        data-name="<?= e($facility["name"]) ?>"
                                        data-category="<?= e($facility["category"]) ?>"
                                        data-description="<?= e($facility["description"]) ?>"
                                        data-location="<?= e($facility["location"]) ?>"
                                        data-capacity="<?= (int) $facility["capacity"] ?>"
                                        data-rate="<?= e((int) $facility["hourly_rate"]) ?>"
                                        data-opening="<?= e(jam($facility["opening_time"])) ?>"
                                        data-closing="<?= e(jam($facility["closing_time"])) ?>"
                                        data-status="<?= e($facility["status"]) ?>"
                                        data-image="<?= e($facility["image"] ? "../assets/uploads/facilities/" . $facility["image"] : "") ?>"
                                    >Edit</button>

                                    <form method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $facility["id"] ?>">
                                        <button type="submit" class="btn btn-soft btn-sm">
                                            <?= $facility["status"] === "active" ? "Nonaktifkan" : "Aktifkan" ?>
                                        </button>
                                    </form>

                                    <form method="POST" data-confirm="Yakin ingin menghapus fasilitas ini?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $facility["id"] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="table-empty" style="padding: 48px 20px; text-align: center;">
                    <strong>Fasilitas tidak ditemukan</strong>
                    <p>Belum ada fasilitas atau filter tidak cocok. Tambahkan fasilitas pertama dengan tombol di atas.</p>
                </div>
            <?php endif; ?>

            <div class="panel-footer">
                Menampilkan <?= count($facilities) ?> fasilitas.
            </div>
        </section>
    </main>
</div>

<!-- Modal tambah/edit fasilitas -->
<div class="app-modal" id="facilityModal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="facilityModalTitle">
        <div class="modal-head">
            <div>
                <h2 id="facilityModalTitle">Tambah Fasilitas</h2>
                <p id="facilityModalDesc">Masukkan informasi fasilitas baru.</p>
            </div>
            <button type="button" class="modal-x" data-close-modal="facilityModal" aria-label="Tutup">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data" id="facilityForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="facilityAction" value="create">
            <input type="hidden" name="id" id="facilityId">

            <div class="field">
                <label for="facName">Nama Fasilitas</label>
                <input type="text" id="facName" name="name" maxlength="100" required>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="facCategory">Kategori</label>
                    <select id="facCategory" name="category" required>
                        <?php foreach (FACILITY_CATEGORIES as $category): ?>
                            <option value="<?= e($category) ?>"><?= e($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="facStatus">Status</label>
                    <select id="facStatus" name="status" required>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="facLocation">Lokasi</label>
                <input type="text" id="facLocation" name="location" maxlength="150" required>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="facCapacity">Kapasitas (orang)</label>
                    <input type="number" id="facCapacity" name="capacity" min="1" step="1" required>
                </div>

                <div class="field">
                    <label for="facRate">Tarif per jam (Rp)</label>
                    <input type="number" id="facRate" name="hourly_rate" min="0" step="1000" required>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="facOpening">Jam buka</label>
                    <input type="time" id="facOpening" name="opening_time" required>
                </div>

                <div class="field">
                    <label for="facClosing">Jam tutup</label>
                    <input type="time" id="facClosing" name="closing_time" required>
                </div>
            </div>

            <div class="field">
                <label for="facDescription">Deskripsi</label>
                <textarea id="facDescription" name="description" placeholder="Kondisi, perlengkapan, atau aturan penggunaan."></textarea>
            </div>

            <div class="field">
                <label for="facImage">Foto fasilitas</label>
                <input type="file" id="facImage" name="image" accept="image/jpeg,image/png,image/webp">
                <small id="facImageHint">JPG, PNG, atau WebP. Maksimal 2 MB.</small>
                <img class="image-preview" id="facPreview" alt="Pratinjau foto">
            </div>

            <div class="modal-foot">
                <button type="button" class="btn btn-outline" data-close-modal="facilityModal">Batal</button>
                <button type="submit" class="btn btn-primary" id="facilitySave">Simpan Fasilitas</button>
            </div>
        </form>
    </div>
</div>



<script>
document.addEventListener("DOMContentLoaded", function () {
    function openModal(modalId) {
        const modal = document.getElementById(modalId);

        if (!modal) {
            console.error("Modal tidak ditemukan:", modalId);
            return;
        }

        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
        document.body.classList.add("modal-open");
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);

        if (!modal) return;

        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
        document.body.classList.remove("modal-open");
    }
    
document.querySelectorAll("[data-close-modal]").forEach(function (button) {
    button.addEventListener("click", function () {
        closeModal(button.dataset.closeModal);
    });
});

    const f = {
        action: document.getElementById("facilityAction"),
        id: document.getElementById("facilityId"),
        name: document.getElementById("facName"),
        category: document.getElementById("facCategory"),
        status: document.getElementById("facStatus"),
        location: document.getElementById("facLocation"),
        capacity: document.getElementById("facCapacity"),
        rate: document.getElementById("facRate"),
        opening: document.getElementById("facOpening"),
        closing: document.getElementById("facClosing"),
        description: document.getElementById("facDescription"),
        image: document.getElementById("facImage"),
        preview: document.getElementById("facPreview")
    };

    function setPreview(src) {
        if (src) {
            f.preview.src = src;
            f.preview.classList.add("show");
        } else {
            f.preview.removeAttribute("src");
            f.preview.classList.remove("show");
        }
    }

    function fill(values, mode) {
        const editing = mode === "update";
        f.action.value = mode;
        f.id.value = values.id || "";
        f.name.value = values.name || "";
        f.category.value = values.category || "Aula";
        f.status.value = values.status || "active";
        f.location.value = values.location || "";
        f.capacity.value = values.capacity || "";
        f.rate.value = values.rate !== undefined ? values.rate : "0";
        f.opening.value = values.opening || "08:00";
        f.closing.value = values.closing || "17:00";
        f.description.value = values.description || "";
        f.image.value = "";
        setPreview(values.image || "");

        document.getElementById("facilityModalTitle").textContent =
            editing ? "Edit Fasilitas" : "Tambah Fasilitas";
        document.getElementById("facilityModalDesc").textContent =
            editing ? "Perbarui informasi fasilitas." : "Masukkan informasi fasilitas baru.";
        document.getElementById("facilitySave").textContent =
            editing ? "Simpan Perubahan" : "Simpan Fasilitas";
        document.getElementById("facImageHint").textContent = editing
            ? "Kosongkan jika tidak ingin mengganti foto. Maksimal 2 MB."
            : "JPG, PNG, atau WebP. Maksimal 2 MB.";
    }

    document.getElementById("openAddModal").addEventListener("click", function () {
        fill({}, "create");
        openModal("facilityModal");
    });

    document.querySelectorAll(".edit-facility").forEach(function (button) {
        button.addEventListener("click", function () {
            const d = button.dataset;
            fill({
                id: d.id, name: d.name, category: d.category,
                description: d.description, location: d.location,
                capacity: d.capacity, rate: d.rate, opening: d.opening,
                closing: d.closing, status: d.status, image: d.image
            }, "update");
            openModal("facilityModal");
        });
    });

    f.image.addEventListener("change", function () {
        const file = f.image.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            alert("Ukuran gambar maksimal 2 MB.");
            f.image.value = "";
            return;
        }
        setPreview(URL.createObjectURL(file));
    });

<?php if ($showModal): ?>
    // Buka kembali modal dengan data terakhir jika validasi gagal.
    fill(<?= json_encode([
        "id"          => $formData["id"] ?: "",
        "name"        => $formData["name"],
        "category"    => $formData["category"],
        "description" => $formData["description"],
        "location"    => $formData["location"],
        "capacity"    => $formData["capacity"],
        "rate"        => $formData["hourly_rate"],
        "opening"     => $formData["opening_time"],
        "closing"     => $formData["closing_time"],
        "status"      => $formData["status"],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>,
        <?= $formData["id"] ? '"update"' : '"create"' ?>);
    openModal("facilityModal");
<?php endif; ?>
});
</script>

</body>
</html>