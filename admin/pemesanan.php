<?php
session_start();

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/admin_helpers.php";

require_admin();

const PER_PAGE = 10;
const FILTER_KEYS = ["search", "status", "date", "facility", "page"];

/**
 * Ubah status pemesanan jika status saat ini diizinkan,
 * lalu kirim notifikasi dalam aplikasi ke mahasiswa.
 * Mengembalikan pesan kesalahan, atau "" jika berhasil.
 */
function transition_booking(
    mysqli $conn,
    int $id,
    array $allowedFrom,
    string $newStatus,
    string $note,
    string $notifTitle,
    string $notifBody
): string {
    $booking = db_one(
        $conn,
        "SELECT b.*, f.name AS facility_name
         FROM bookings b
         JOIN facilities f ON f.id = b.facility_id
         WHERE b.id = ? LIMIT 1",
        "i",
        [$id]
    );

    if (!$booking) {
        return "Pemesanan tidak ditemukan.";
    }

    if (!in_array($booking["status"], $allowedFrom, true)) {
        return "Status pemesanan sudah berubah. Muat ulang halaman untuk melihat data terbaru.";
    }

    if ($newStatus === "approved") {
        $clash = db_one(
            $conn,
            "SELECT booking_code FROM bookings
             WHERE facility_id = ? AND booking_date = ?
               AND status = 'approved' AND id <> ?
               AND start_time < ? AND end_time > ?
             LIMIT 1",
            "isiss",
            [
                $booking["facility_id"],
                $booking["booking_date"],
                $id,
                $booking["end_time"],
                $booking["start_time"],
            ]
        );

        if ($clash) {
            return "Jadwal bentrok dengan pemesanan " . $clash["booking_code"]
                . " yang sudah disetujui. Tolak atau ubah salah satunya.";
        }
    }

    $note = $note !== "" ? $note : null;

    // Klausa status memastikan dua admin tidak menimpa satu sama lain.
    $placeholders = implode(",", array_fill(0, count($allowedFrom), "?"));
    $affected = db_run(
        $conn,
        "UPDATE bookings SET status = ?, admin_note = COALESCE(?, admin_note)
         WHERE id = ? AND status IN ($placeholders)",
        "ssi" . str_repeat("s", count($allowedFrom)),
        array_merge([$newStatus, $note, $id], $allowedFrom)
    );

    if ($affected === false) {
        return "Gagal memperbarui status pemesanan.";
    }

    if ($affected === 0) {
        return "Status pemesanan sudah berubah. Muat ulang halaman.";
    }

    $schedule = $booking["facility_name"] . ", "
        . tanggal_id($booking["booking_date"]) . " "
        . jam($booking["start_time"]) . "-" . jam($booking["end_time"]);

    send_notification(
        $conn,
        (int) $booking["user_id"],
        (int) $booking["id"],
        $notifTitle,
        "Pemesanan " . $booking["booking_code"] . " (" . $schedule . ") "
            . $notifBody . ($note ? " Catatan admin: " . $note : ""),
        "in_app"
    );

    return "";
}

$error = "";

/* =========================
   PROSES APPROVE, TOLAK, SELESAI, BATALKAN
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_valid()) {
        $error = "Sesi formulir tidak valid. Muat ulang halaman.";
    } else {
        $action = $_POST["action"] ?? "";
        $id = (int) ($_POST["id"] ?? 0);
        $reason = trim($_POST["reason"] ?? "");
        $result = null;
        $success = "";

        if ($id <= 0) {
            $error = "ID pemesanan tidak valid.";
        } elseif ($action === "approve") {
            $result = transition_booking(
                $conn, $id, ["pending"], "approved", "",
                "Pemesanan disetujui",
                "telah disetujui. Silakan lanjutkan ke pembayaran jika fasilitas berbayar."
            );
            $success = "Pemesanan berhasil disetujui.";
        } elseif ($action === "reject") {
            if ($reason === "") {
                $error = "Alasan penolakan wajib diisi agar mahasiswa tahu penyebabnya.";
            } else {
                $result = transition_booking(
                    $conn, $id, ["pending"], "rejected", $reason,
                    "Pemesanan ditolak",
                    "tidak dapat disetujui."
                );
                $success = "Pemesanan ditolak dan mahasiswa sudah diberi tahu.";
            }
        } elseif ($action === "complete") {
            $result = transition_booking(
                $conn, $id, ["approved"], "completed", "",
                "Pemesanan selesai",
                "telah ditandai selesai. Terima kasih telah menggunakan CampusBook."
            );
            $success = "Pemesanan ditandai selesai.";
        } elseif ($action === "cancel") {
            if ($reason === "") {
                $error = "Alasan pembatalan wajib diisi agar mahasiswa tahu penyebabnya.";
            } else {
                $result = transition_booking(
                    $conn, $id, ["pending", "approved"], "cancelled", $reason,
                    "Pemesanan dibatalkan",
                    "dibatalkan oleh admin."
                );
                $success = "Pemesanan dibatalkan dan mahasiswa sudah diberi tahu.";
            }
        } else {
            $error = "Aksi tidak dikenal.";
        }

        if ($result !== null) {
            if ($result === "") {
                flash_set($success);
            } else {
                flash_set($result, "error");
            }
            redirect_to(back_url_for("pemesanan.php", FILTER_KEYS));
        }
    }
}

$flash = flash_get();

/* =========================
   PENCARIAN DAN FILTER
========================= */
$keyword = trim($_GET["search"] ?? "");
$statusFilter = $_GET["status"] ?? "";
$dateFilter = $_GET["date"] ?? "";
$facilityFilter = (int) ($_GET["facility"] ?? 0);
$page = max(1, (int) ($_GET["page"] ?? 1));

$validStatuses = ["pending", "approved", "completed", "rejected", "cancelled"];

$where = " WHERE 1 = 1";
$params = [];
$types = "";

if ($keyword !== "") {
    $where .= " AND (b.booking_code LIKE ? OR s.name LIKE ? OR s.nim LIKE ? OR f.name LIKE ?)";
    $like = "%" . $keyword . "%";
    array_push($params, $like, $like, $like, $like);
    $types .= "ssss";
}

if (in_array($statusFilter, $validStatuses, true)) {
    $where .= " AND b.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
    $where .= " AND b.booking_date = ?";
    $params[] = $dateFilter;
    $types .= "s";
} else {
    $dateFilter = "";
}

if ($facilityFilter > 0) {
    $where .= " AND b.facility_id = ?";
    $params[] = $facilityFilter;
    $types .= "i";
}

$from = "
    FROM bookings b
    JOIN students s ON s.id = b.user_id
    JOIN facilities f ON f.id = b.facility_id
    LEFT JOIN payments p ON p.booking_id = b.id
";

$totalRows = (int) (db_one(
    $conn,
    "SELECT COUNT(*) AS total " . $from . $where,
    $types,
    $params
)["total"] ?? 0);

$pages = max(1, (int) ceil($totalRows / PER_PAGE));
$page = min($page, $pages);
$offset = ($page - 1) * PER_PAGE;

$bookings = db_all(
    $conn,
    "SELECT b.*, s.name AS student_name, s.nim, s.email AS student_email,
            f.name AS facility_name, f.hourly_rate,
            p.payment_status, p.amount AS payment_amount
     " . $from . $where . "
     ORDER BY (b.status = 'pending') DESC, b.booking_date DESC, b.start_time DESC
     LIMIT ? OFFSET ?",
    $types . "ii",
    array_merge($params, [PER_PAGE, $offset])
);

$facilityOptions = db_all(
    $conn,
    "SELECT id, name FROM facilities ORDER BY name ASC"
);

/* Statistik */
$stats = db_one(
    $conn,
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(booking_date = CURDATE()), 0) AS today_total,
            COALESCE(SUM(status = 'pending'), 0) AS pending_total
     FROM bookings"
) ?? ["total" => 0, "today_total" => 0, "pending_total" => 0];

$revenue = db_one(
    $conn,
    "SELECT COALESCE(SUM(amount), 0) AS total
     FROM payments
     WHERE payment_status = 'paid'
       AND YEAR(paid_at) = YEAR(CURDATE())
       AND MONTH(paid_at) = MONTH(CURDATE())"
)["total"] ?? 0;

$activeFilters = array_filter([
    "search"   => $keyword,
    "status"   => in_array($statusFilter, $validStatuses, true) ? $statusFilter : "",
    "date"     => $dateFilter,
    "facility" => $facilityFilter > 0 ? (string) $facilityFilter : "",
], fn($v) => $v !== "");

$backValue = http_build_query($page > 1 ? $activeFilters + ["page" => $page] : $activeFilters);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemesanan - CampusBook</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/admin_modules.css">
</head>
<body>

<div class="admin-layout">
    <?php require_once __DIR__ . "/../includes/admin_sidebar.php"; ?>

    <main class="admin-main">
        <?php render_admin_header(
            "MANAJEMEN PEMESANAN",
            "Pemesanan",
            "Tinjau, setujui, atau tolak permohonan penggunaan fasilitas kampus."
        ); ?>

        <?php render_flash($flash, $error); ?>

        <section class="summary-grid">
            <article class="summary-card">
                <span class="summary-icon blue">
                    <i class="fa-solid fa-calendar-check"></i>
                </span>
                <div>
                    <p>Total Pemesanan</p>
                    <strong><?= (int) $stats["total"] ?></strong>
                </div>
            </article>

            <article class="summary-card">
                <span class="summary-icon green">
                    <i class="fa-solid fa-calendar-day"></i>
                </span>
                <div>
                    <p>Pemesanan Hari Ini</p>
                    <strong><?= (int) $stats["today_total"] ?></strong>
                </div>
            </article>

            <article class="summary-card">
                <span class="summary-icon orange">
                    <i class="fa-solid fa-clock"></i>
                </span>
                <div>
                    <p>Menunggu Persetujuan</p>
                    <strong><?= (int) $stats["pending_total"] ?></strong>
                </div>
            </article>

            <article class="summary-card">
                <span class="summary-icon pink">
                    <i class="fa-solid fa-wallet"></i>
                </span>
                <div>
                    <p>Pendapatan Bulan Ini</p>
                    <strong><?= e(rupiah($revenue)) ?></strong>
                </div>
            </article>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>Daftar Pemesanan</h2>
                    <p>Permohonan yang menunggu persetujuan ditampilkan paling atas.</p>
                </div>
                 <a class="btn btn-primary"
                href="laporan_pemesanan.php<?= $activeFilters ? '?' . e(http_build_query($activeFilters)) : '' ?>"
                target="_blank"
                rel="noopener">
                    <i class="fa-solid fa-file-pdf"></i>
                    Cetak Laporan PDF
                </a>
            </div>

            <form method="GET" class="toolbar">
                <div class="toolbar-search">
                    <span>⌕</span>
                    <input
                        type="search"
                        name="search"
                        placeholder="Cari kode, nama, NIM, atau fasilitas..."
                        value="<?= e($keyword) ?>"
                        aria-label="Cari pemesanan"
                    >
                </div>

                <select name="status" aria-label="Filter status">
                    <option value="">Semua Status</option>
                    <?php foreach ($validStatuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= $statusFilter === $status ? "selected" : "" ?>>
                            <?= e(booking_status_meta($status)[0]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="facility" aria-label="Filter fasilitas">
                    <option value="">Semua Fasilitas</option>
                    <?php foreach ($facilityOptions as $option): ?>
                        <option value="<?= (int) $option["id"] ?>" <?= $facilityFilter === (int) $option["id"] ? "selected" : "" ?>>
                            <?= e($option["name"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="date" name="date" value="<?= e($dateFilter) ?>" aria-label="Filter tanggal pemakaian">

                <button type="submit" class="btn btn-soft">Cari</button>

                <?php if ($activeFilters): ?>
                    <a href="pemesanan.php" class="btn btn-soft">Reset</a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Mahasiswa</th>
                            <th>Fasilitas</th>
                            <th>Tanggal</th>
                            <th>Waktu</th>
                            <th>Status</th>
                            <th>Pembayaran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($bookings): ?>
                        <?php foreach ($bookings as $index => $booking): ?>
                            <?php
                            [$statusLabel, $statusClass] = booking_status_meta($booking["status"]);
                            $paymentLabel = $booking["payment_status"]
                                ? payment_status_meta($booking["payment_status"])
                                : null;
                            ?>
                            <tr>
                                <td><?= $offset + $index + 1 ?></td>
                                <td>
                                    <strong><?= e($booking["student_name"]) ?></strong>
                                    <span class="cell-sub"><?= e($booking["nim"]) ?></span>
                                </td>
                                <td>
                                    <strong><?= e($booking["facility_name"]) ?></strong>
                                    <span class="cell-sub cell-code"><?= e($booking["booking_code"]) ?></span>
                                </td>
                                <td><?= e(tanggal_id($booking["booking_date"])) ?></td>
                                <td><?= e(jam($booking["start_time"])) ?> - <?= e(jam($booking["end_time"])) ?></td>
                                <td><?= badge($statusLabel, $statusClass) ?></td>
                                <td>
                                    <?php if ($paymentLabel): ?>
                                        <?= badge($paymentLabel[0], $paymentLabel[1]) ?>
                                    <?php elseif ($booking["hourly_rate"] > 0): ?>
                                        <span class="cell-sub" style="margin: 0;">Belum dibayar</span>
                                    <?php else: ?>
                                        <span class="cell-sub" style="margin: 0;">Gratis</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <?php if ($booking["status"] === "pending"): ?>
                                            <form method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="back" value="<?= e($backValue) ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="id" value="<?= (int) $booking["id"] ?>">
                                                <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                            </form>

                                            <button
                                                type="button"
                                                class="btn btn-danger btn-sm open-reason"
                                                data-action="reject"
                                                data-id="<?= (int) $booking["id"] ?>"
                                                data-code="<?= e($booking["booking_code"]) ?>"
                                            >Tolak</button>
                                        <?php elseif ($booking["status"] === "approved"): ?>
                                            <form method="POST" data-confirm="Tandai pemesanan ini sebagai selesai?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="back" value="<?= e($backValue) ?>">
                                                <input type="hidden" name="action" value="complete">
                                                <input type="hidden" name="id" value="<?= (int) $booking["id"] ?>">
                                                <button type="submit" class="btn btn-success btn-sm">Selesai</button>
                                            </form>

                                            <button
                                                type="button"
                                                class="btn btn-danger btn-sm open-reason"
                                                data-action="cancel"
                                                data-id="<?= (int) $booking["id"] ?>"
                                                data-code="<?= e($booking["booking_code"]) ?>"
                                            >Batalkan</button>
                                        <?php endif; ?>

                                        <button
                                            type="button"
                                            class="btn btn-outline btn-sm open-detail"
                                            data-code="<?= e($booking["booking_code"]) ?>"
                                            data-student="<?= e($booking["student_name"]) ?>"
                                            data-nim="<?= e($booking["nim"]) ?>"
                                            data-email="<?= e($booking["student_email"]) ?>"
                                            data-facility="<?= e($booking["facility_name"]) ?>"
                                            data-date="<?= e(tanggal_id($booking["booking_date"])) ?>"
                                            data-time="<?= e(jam($booking["start_time"]) . " - " . jam($booking["end_time"])) ?>"
                                            data-purpose="<?= e($booking["purpose"]) ?>"
                                            data-status="<?= e($statusLabel) ?>"
                                            data-note="<?= e($booking["admin_note"] ?? "") ?>"
                                            data-payment="<?= e($paymentLabel ? $paymentLabel[0] : ($booking["hourly_rate"] > 0 ? "Belum dibayar" : "Gratis")) ?>"
                                            data-created="<?= e(tanggal_id($booking["created_at"])) ?>"
                                        >Detail</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="table-empty">
                                <strong>Pemesanan tidak ditemukan</strong>
                                <p>Belum ada permohonan masuk atau filter tidak cocok.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="panel-footer split">
                <span>
                    Menampilkan <?= count($bookings) ?> dari <?= $totalRows ?> pemesanan.
                </span>
                <?php render_pagination($page, $pages, $activeFilters); ?>
            </div>
        </section>
    </main>
</div>

<!-- Modal detail pemesanan -->
<div class="app-modal" id="detailModal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="detailTitle">
        <div class="modal-head">
            <div>
                <h2 id="detailTitle">Detail Pemesanan</h2>
                <p id="detailCode"></p>
            </div>
            <button type="button" class="modal-x" data-close-modal="detailModal" aria-label="Tutup">&times;</button>
        </div>

        <dl class="detail-list">
            <div><dt>Mahasiswa</dt><dd id="dStudent"></dd></div>
            <div><dt>NIM</dt><dd id="dNim"></dd></div>
            <div><dt>Email</dt><dd id="dEmail"></dd></div>
            <div><dt>Fasilitas</dt><dd id="dFacility"></dd></div>
            <div><dt>Tanggal</dt><dd id="dDate"></dd></div>
            <div><dt>Waktu</dt><dd id="dTime"></dd></div>
            <div><dt>Keperluan</dt><dd id="dPurpose"></dd></div>
            <div><dt>Status</dt><dd id="dStatus"></dd></div>
            <div><dt>Pembayaran</dt><dd id="dPayment"></dd></div>
            <div><dt>Catatan admin</dt><dd id="dNote"></dd></div>
            <div><dt>Diajukan</dt><dd id="dCreated"></dd></div>
        </dl>

        <div class="modal-foot">
            <button type="button" class="btn btn-outline" data-close-modal="detailModal">Tutup</button>
        </div>
    </div>
</div>

<!-- Modal alasan (tolak / batalkan) -->
<div class="app-modal" id="reasonModal" aria-hidden="true">
    <div class="modal-card narrow" role="dialog" aria-modal="true" aria-labelledby="reasonTitle">
        <div class="modal-head">
            <div>
                <h2 id="reasonTitle">Tolak Pemesanan</h2>
                <p id="reasonDesc"></p>
            </div>
            <button type="button" class="modal-x" data-close-modal="reasonModal" aria-label="Tutup">&times;</button>
        </div>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="back" value="<?= e($backValue) ?>">
            <input type="hidden" name="action" id="reasonAction" value="reject">
            <input type="hidden" name="id" id="reasonId">

            <div class="field">
                <label for="reasonText">Alasan</label>
                <textarea id="reasonText" name="reason" required maxlength="500"
                          placeholder="Contoh: Jadwal bentrok dengan kegiatan fakultas."></textarea>
                <small>Alasan ini dikirim ke mahasiswa sebagai notifikasi.</small>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn btn-outline" data-close-modal="reasonModal">Kembali</button>
                <button type="submit" class="btn btn-danger-solid" id="reasonSubmit">Tolak Pemesanan</button>
            </div>
        </form>
    </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function () {
    function openModal(id) {
    const modal = document.getElementById(id);

    if (!modal) {
        console.error("Modal tidak ditemukan:", id);
        return;
    }

    modal.classList.add("show");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
}

function closeModal(id) {
    const modal = document.getElementById(id);

    if (!modal) return;

    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
}
    const text = function (id, value) {
        document.getElementById(id).textContent = value && value !== "" ? value : "-";
    };

    document.querySelectorAll(".open-detail").forEach(function (button) {
        button.addEventListener("click", function () {
            const d = button.dataset;
            text("detailCode", d.code);
            text("dStudent", d.student);
            text("dNim", d.nim);
            text("dEmail", d.email);
            text("dFacility", d.facility);
            text("dDate", d.date);
            text("dTime", d.time);
            text("dPurpose", d.purpose);
            text("dStatus", d.status);
            text("dPayment", d.payment);
            text("dNote", d.note);
            text("dCreated", d.created);
            openModal("detailModal");
        });
    });

    const copy = {
        reject: {
            title: "Tolak Pemesanan",
            button: "Tolak Pemesanan",
            desc: "Pemesanan %c akan ditolak."
        },
        cancel: {
            title: "Batalkan Pemesanan",
            button: "Batalkan Pemesanan",
            desc: "Pemesanan %c akan dibatalkan dan jadwal dibuka kembali."
        }
    };

    document.querySelectorAll(".open-reason").forEach(function (button) {
        button.addEventListener("click", function () {
            const mode = copy[button.dataset.action];
            document.getElementById("reasonAction").value = button.dataset.action;
            document.getElementById("reasonId").value = button.dataset.id;
            document.getElementById("reasonTitle").textContent = mode.title;
            document.getElementById("reasonSubmit").textContent = mode.button;
            document.getElementById("reasonDesc").textContent =
                mode.desc.replace("%c", button.dataset.code);
            document.getElementById("reasonText").value = "";
            openModal("reasonModal");
        });
    });
    // Tombol tutup modal dan klik area di luar modal
        document.querySelectorAll("[data-close-modal]").forEach(function (button) {
            button.addEventListener("click", function () {
                closeModal(button.dataset.closeModal);
            });
        });

        document.querySelectorAll(".app-modal").forEach(function (modal) {
            modal.addEventListener("click", function (event) {
                if (event.target === modal) {
                    closeModal(modal.id);
                }
            });
        });
});
</script>

</body>
</html>