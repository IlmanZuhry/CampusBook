<?php
session_start();

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/admin_helpers.php";

require_admin();

const PER_PAGE = 10;
const FILTER_KEYS = ["search", "status", "method", "page"];
const PAYMENT_METHODS = ["transfer", "qris", "va_bca", "va_bni", "va_bri", "va_mandiri"];

$error = "";
$showModal = false;
$formData = [
    "booking_id" => 0,
    "method"     => "transfer",
    "amount"     => "",
    "reference"  => "",
    "status"     => "paid",
];

$paymentMethodLabels = [
    "transfer"   => "Transfer Bank",
    "qris"       => "QRIS",
    "va_bca"     => "BCA Virtual Account",
    "va_bni"     => "BNI Virtual Account",
    "va_bri"     => "BRI Virtual Account",
    "va_mandiri" => "Mandiri Virtual Account",
];

$paymentMethodLabel = static function ($method) use ($paymentMethodLabels): string {
    return $paymentMethodLabels[$method] ?? (string) $method;
};

/* =========================
   PROSES KONFIRMASI, TOLAK, CATAT PEMBAYARAN
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_valid()) {
        $error = "Sesi formulir tidak valid. Muat ulang halaman.";
    } else {
        $action = $_POST["action"] ?? "";

        if (in_array($action, ["confirm", "fail"], true)) {
            $id = (int) ($_POST["id"] ?? 0);
            $reference = trim($_POST["reference"] ?? "");
            $reason = trim($_POST["reason"] ?? "");

            $payment = db_one(
                $conn,
                "SELECT p.*, b.booking_code, b.user_id
                 FROM payments p
                 JOIN bookings b ON b.id = p.booking_id
                 WHERE p.id = ? LIMIT 1",
                "i",
                [$id]
            );

            if (!$payment) {
                $error = "Data pembayaran tidak ditemukan.";
            } elseif ($payment["payment_status"] !== "pending") {
                $error = "Pembayaran ini sudah diproses. Muat ulang halaman untuk melihat data terbaru.";
            } elseif ($action === "fail" && $reason === "") {
                $error = "Alasan penolakan wajib diisi agar mahasiswa tahu penyebabnya.";
            } else {
                $errno = 0;

                if ($action === "confirm") {
                    $affected = db_run(
                        $conn,
                        "UPDATE payments
                         SET payment_status = 'paid',
                             paid_at = NOW(),
                             transaction_reference = COALESCE(?, transaction_reference)
                         WHERE id = ? AND payment_status = 'pending'",
                        "si",
                        [$reference !== "" ? $reference : null, $id],
                        $errno
                    );
                } else {
                    $affected = db_run(
                        $conn,
                        "UPDATE payments SET payment_status = 'failed'
                         WHERE id = ? AND payment_status = 'pending'",
                        "i",
                        [$id],
                        $errno
                    );
                }

                if ($affected === false) {
                    $error = $errno === 1062
                        ? "Referensi transaksi sudah digunakan pada pembayaran lain."
                        : "Gagal memperbarui status pembayaran.";
                } elseif ($affected === 0) {
                    $error = "Pembayaran ini sudah diproses oleh admin lain.";
                } else {
                    if ($action === "confirm") {
                        send_notification(
                            $conn,
                            (int) $payment["user_id"],
                            (int) $payment["booking_id"],
                            "Pembayaran dikonfirmasi",
                            "Pembayaran " . rupiah($payment["amount"])
                                . " untuk pemesanan " . $payment["booking_code"]
                                . " telah dikonfirmasi.",
                            "in_app"
                        );
                        flash_set("Pembayaran dikonfirmasi dan mahasiswa sudah diberi tahu.");
                    } else {
                        send_notification(
                            $conn,
                            (int) $payment["user_id"],
                            (int) $payment["booking_id"],
                            "Pembayaran ditolak",
                            "Pembayaran untuk pemesanan " . $payment["booking_code"]
                                . " tidak dapat diverifikasi. Alasan: " . $reason
                                . " Silakan lakukan pembayaran ulang.",
                            "in_app"
                        );
                        flash_set("Pembayaran ditolak dan mahasiswa sudah diberi tahu.");
                    }

                    redirect_to(back_url_for("pembayaran.php", FILTER_KEYS));
                }
            }
        } elseif ($action === "create") {
            $formData = [
                "booking_id" => (int) ($_POST["booking_id"] ?? 0),
                "method"     => $_POST["method"] ?? "",
                "amount"     => trim($_POST["amount"] ?? ""),
                "reference"  => trim($_POST["reference"] ?? ""),
                "status"     => $_POST["status"] ?? "",
            ];

            $showModal = true;

            $booking = db_one(
                $conn,
                "SELECT b.id, b.booking_code, b.user_id, b.status,
                        (SELECT COUNT(*) FROM payments WHERE booking_id = b.id) AS has_payment
                 FROM bookings b
                 WHERE b.id = ? LIMIT 1",
                "i",
                [$formData["booking_id"]]
            );

            if (!$booking) {
                $error = "Pilih pemesanan yang akan dicatat pembayarannya.";
            } elseif (!in_array($booking["status"], ["approved", "completed"], true)) {
                $error = "Pembayaran hanya dapat dicatat untuk pemesanan yang disetujui.";
            } elseif ((int) $booking["has_payment"] > 0) {
                $error = "Pemesanan ini sudah memiliki data pembayaran.";
            } elseif (!in_array($formData["method"], PAYMENT_METHODS, true)) {
                $error = "Metode pembayaran tidak valid.";
            } elseif (
                !is_numeric($formData["amount"]) ||
                (float) $formData["amount"] <= 0
            ) {
                $error = "Jumlah pembayaran harus lebih dari 0.";
            } elseif (!in_array($formData["status"], ["paid", "pending"], true)) {
                $error = "Status pembayaran tidak valid.";
            } else {
                $errno = 0;
                $isPaid = $formData["status"] === "paid";

                $saved = db_run(
                    $conn,
                    "INSERT INTO payments
                        (booking_id, amount, payment_method, payment_status,
                         transaction_reference, paid_at)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    "idssss",
                    [
                        $booking["id"],
                        (float) $formData["amount"],
                        $formData["method"],
                        $formData["status"],
                        $formData["reference"] !== "" ? $formData["reference"] : null,
                        $isPaid ? date("Y-m-d H:i:s") : null,
                    ],
                    $errno
                );

                if ($saved === false) {
                    $error = $errno === 1062
                        ? "Referensi transaksi sudah digunakan pada pembayaran lain."
                        : "Gagal menyimpan data pembayaran.";
                } else {
                    if ($isPaid) {
                        send_notification(
                            $conn,
                            (int) $booking["user_id"],
                            (int) $booking["id"],
                            "Pembayaran dikonfirmasi",
                            "Pembayaran " . rupiah($formData["amount"])
                                . " untuk pemesanan " . $booking["booking_code"]
                                . " telah dicatat dan dikonfirmasi.",
                            "in_app"
                        );
                    }

                    flash_set("Pembayaran berhasil dicatat.");
                    redirect_to("pembayaran.php");
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
$statusFilter = $_GET["status"] ?? "";
$methodFilter = $_GET["method"] ?? "";
$page = max(1, (int) ($_GET["page"] ?? 1));

$where = " WHERE 1 = 1";
$params = [];
$types = "";

if ($keyword !== "") {
    $where .= " AND (b.booking_code LIKE ? OR s.name LIKE ? OR s.nim LIKE ? OR p.transaction_reference LIKE ?)";
    $like = "%" . $keyword . "%";
    array_push($params, $like, $like, $like, $like);
    $types .= "ssss";
}

if (in_array($statusFilter, ["pending", "paid", "failed"], true)) {
    $where .= " AND p.payment_status = ?";
    $params[] = $statusFilter;
    $types .= "s";
} else {
    $statusFilter = "";
}

if ($methodFilter === "transfer") {
    // "Transfer Bank" juga mencakup semua metode Virtual Account.
    $where .= " AND p.payment_method IN (
        'transfer', 'va_bca', 'va_bni', 'va_bri', 'va_mandiri'
    )";
} elseif (in_array($methodFilter, PAYMENT_METHODS, true)) {
    $where .= " AND p.payment_method = ?";
    $params[] = $methodFilter;
    $types .= "s";
} else {
    $methodFilter = "";
}

$from = "
    FROM payments p
    JOIN bookings b ON b.id = p.booking_id
    JOIN students s ON s.id = b.user_id
    JOIN facilities f ON f.id = b.facility_id
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

$payments = db_all(
    $conn,
    "SELECT p.*, b.booking_code, b.booking_date,
            s.name AS student_name, s.nim,
            f.name AS facility_name
     " . $from . $where . "
     ORDER BY (p.payment_status = 'pending') DESC, p.created_at DESC
     LIMIT ? OFFSET ?",
    $types . "ii",
    array_merge($params, [PER_PAGE, $offset])
);

/* Pemesanan yang bisa dicatat pembayarannya */
$payableBookings = db_all(
    $conn,
    "SELECT b.id, b.booking_code, s.name AS student_name,
            f.name AS facility_name,
            ROUND(f.hourly_rate * TIME_TO_SEC(TIMEDIFF(b.end_time, b.start_time)) / 3600) AS estimate
     FROM bookings b
     JOIN students s ON s.id = b.user_id
     JOIN facilities f ON f.id = b.facility_id
     LEFT JOIN payments p ON p.booking_id = b.id
     WHERE b.status IN ('approved', 'completed') AND p.id IS NULL
     ORDER BY b.booking_date DESC, b.start_time DESC
     LIMIT 100"
);

/* Statistik */
$stats = db_one(
    $conn,
    "SELECT COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN amount END), 0) AS revenue,
            COALESCE(SUM(payment_status = 'pending'), 0) AS pending_total,
            COALESCE(SUM(payment_status = 'paid'), 0) AS paid_total,
            COALESCE(SUM(payment_status = 'failed'), 0) AS failed_total
     FROM payments"
) ?? ["revenue" => 0, "pending_total" => 0, "paid_total" => 0, "failed_total" => 0];

$activeFilters = array_filter([
    "search" => $keyword,
    "status" => $statusFilter,
    "method" => $methodFilter,
], fn($v) => $v !== "");

$backValue = http_build_query($page > 1 ? $activeFilters + ["page" => $page] : $activeFilters);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - CampusBook</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/admin_modules.css">
</head>
<body>

<div class="admin-layout">
    <?php require_once __DIR__ . "/../includes/admin_sidebar.php"; ?>

    <main class="admin-main">
        <?php render_admin_header(
            "MANAJEMEN PEMBAYARAN",
            "Pembayaran",
            "Verifikasi pembayaran mahasiswa dan pantau pendapatan fasilitas."
        ); ?>

        <?php render_flash($flash, $error); ?>

        <section class="summary-grid">
            <article class="summary-card">
                <span class="summary-icon blue">
                    <i class="fa-solid fa-wallet"></i>
                </span>
                <div>
                    <p>Total Pendapatan</p>
                    <strong><?= e(rupiah($stats["revenue"])) ?></strong>
                </div>
            </article>

            <article class="summary-card">
                <span class="summary-icon orange">
                    <i class="fa-solid fa-clock"></i>
                </span>
                <div>
                    <p>Menunggu Verifikasi</p>
                    <strong><?= (int) $stats["pending_total"] ?></strong>
                </div>
            </article>

            <article class="summary-card">
                <span class="summary-icon green">
                    <i class="fa-solid fa-circle-check"></i>
                </span>
                <div>
                    <p>Pembayaran Lunas</p>
                    <strong><?= (int) $stats["paid_total"] ?></strong>
                </div>
            </article>

            <article class="summary-card">
                <span class="summary-icon pink">
                    <i class="fa-solid fa-circle-xmark"></i>
                </span>
                <div>
                    <p>Pembayaran Gagal</p>
                    <strong><?= (int) $stats["failed_total"] ?></strong>
                </div>
            </article>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>Daftar Pembayaran</h2>
                    <p>Pembayaran yang menunggu verifikasi ditampilkan paling atas.</p>
                </div>

                <div class="row-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="laporan_pembayaran.php<?= $activeFilters ? "?" . e(http_build_query($activeFilters)) : "" ?>"
                       class="btn btn-primary" target="_blank" rel="noopener">
                        <i class="fa-solid fa-file-pdf"></i> Cetak Laporan PDF
                    </a>
                </div>
            </div>

            <form method="GET" class="toolbar">
                <div class="toolbar-search">
                    <span>⌕</span>
                    <input
                        type="search"
                        name="search"
                        placeholder="Cari kode, nama, NIM, atau referensi..."
                        value="<?= e($keyword) ?>"
                        aria-label="Cari pembayaran"
                    >
                </div>

                <select name="status" aria-label="Filter status">
                    <option value="">Semua Status</option>
                    <?php foreach (["pending", "paid", "failed"] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $statusFilter === $status ? "selected" : "" ?>>
                            <?= e(payment_status_meta($status)[0]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="method" aria-label="Filter metode">
                    <option value="">Semua Metode</option>
                    <?php foreach (PAYMENT_METHODS as $method): ?>
                        <option value="<?= e($method) ?>" <?= $methodFilter === $method ? "selected" : "" ?>>
                            <?= e($paymentMethodLabel($method)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-soft">Cari</button>

                <?php if ($activeFilters): ?>
                    <a href="pembayaran.php" class="btn btn-soft">Reset</a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Mahasiswa</th>
                            <th>Fasilitas</th>
                            <th>Metode</th>
                            <th>Jumlah</th>
                            <th>Referensi</th>
                            <th>Status</th>
                            <th>Waktu bayar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($payments): ?>
                        <?php foreach ($payments as $index => $payment): ?>
                            <?php [$statusLabel, $statusClass] = payment_status_meta($payment["payment_status"]); ?>
                            <tr>
                                <td><?= $offset + $index + 1 ?></td>
                                <td>
                                    <strong><?= e($payment["student_name"]) ?></strong>
                                    <span class="cell-sub"><?= e($payment["nim"]) ?></span>
                                </td>
                                <td>
                                    <strong><?= e($payment["facility_name"]) ?></strong>
                                    <span class="cell-sub cell-code"><?= e($payment["booking_code"]) ?></span>
                                </td>
                                <td><?= e($paymentMethodLabel($payment["payment_method"])) ?></td>
                                <td><strong><?= e(rupiah($payment["amount"])) ?></strong></td>
                                <td><?= e($payment["transaction_reference"] ?: "-") ?></td>
                                <td><?= badge($statusLabel, $statusClass) ?></td>
                                <td><?= $payment["paid_at"] ? e(tanggal_id($payment["paid_at"]) . " " . substr($payment["paid_at"], 11, 5)) : "-" ?></td>
                                <td>
                                    <?php if ($payment["payment_status"] === "pending"): ?>
                                        <div class="row-actions">
                                            <button
                                                type="button"
                                                class="btn btn-success btn-sm open-decision"
                                                data-mode="confirm"
                                                data-id="<?= (int) $payment["id"] ?>"
                                                data-code="<?= e($payment["booking_code"]) ?>"
                                                data-amount="<?= e(rupiah($payment["amount"])) ?>"
                                                data-reference="<?= e($payment["transaction_reference"] ?? "") ?>"
                                            >Konfirmasi</button>

                                            <button
                                                type="button"
                                                class="btn btn-danger btn-sm open-decision"
                                                data-mode="fail"
                                                data-id="<?= (int) $payment["id"] ?>"
                                                data-code="<?= e($payment["booking_code"]) ?>"
                                                data-amount="<?= e(rupiah($payment["amount"])) ?>"
                                                data-reference=""
                                            >Tolak</button>
                                        </div>
                                    <?php else: ?>
                                        <span class="cell-sub" style="margin: 0;">Selesai diproses</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="table-empty">
                                <strong>Pembayaran tidak ditemukan</strong>
                                <p>Belum ada pembayaran masuk atau filter tidak cocok.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="panel-footer split">
                <span>
                    Menampilkan <?= count($payments) ?> dari <?= $totalRows ?> pembayaran.
                </span>
                <?php render_pagination($page, $pages, $activeFilters); ?>
            </div>
        </section>
    </main>
</div>

<!-- Modal konfirmasi / tolak pembayaran -->
<div class="app-modal" id="decisionModal" aria-hidden="true">
    <div class="modal-card narrow" role="dialog" aria-modal="true" aria-labelledby="decisionTitle">
        <div class="modal-head">
            <div>
                <h2 id="decisionTitle">Konfirmasi Pembayaran</h2>
                <p id="decisionDesc"></p>
            </div>
            <button type="button" class="modal-x" data-close-modal="decisionModal" aria-label="Tutup">&times;</button>
        </div>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="back" value="<?= e($backValue) ?>">
            <input type="hidden" name="action" id="decisionAction" value="confirm">
            <input type="hidden" name="id" id="decisionId">

            <div class="field" id="referenceField">
                <label for="decisionReference">Referensi transaksi (opsional)</label>
                <input type="text" id="decisionReference" name="reference" maxlength="100"
                       placeholder="Nomor transfer atau ID transaksi">
            </div>

            <div class="field" id="reasonField" hidden>
                <label for="decisionReason">Alasan penolakan</label>
                <textarea id="decisionReason" name="reason" maxlength="500"
                          placeholder="Contoh: Nominal transfer tidak sesuai tagihan."></textarea>
                <small>Alasan ini dikirim ke mahasiswa sebagai notifikasi.</small>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn btn-outline" data-close-modal="decisionModal">Kembali</button>
                <button type="submit" class="btn btn-success" id="decisionSubmit">Konfirmasi Pembayaran</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal catat pembayaran manual -->
<div class="app-modal" id="createModal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="createTitle">
        <div class="modal-head">
            <div>
                <h2 id="createTitle">Catat Pembayaran</h2>
                <p>Untuk pembayaran yang diterima di luar sistem, misalnya tunai atau transfer langsung.</p>
            </div>
            <button type="button" class="modal-x" data-close-modal="createModal" aria-label="Tutup">&times;</button>
        </div>

        <?php if ($payableBookings): ?>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div class="field">
                    <label for="payBooking">Pemesanan</label>
                    <select id="payBooking" name="booking_id" required>
                        <option value="">Pilih pemesanan</option>
                        <?php foreach ($payableBookings as $option): ?>
                            <option
                                value="<?= (int) $option["id"] ?>"
                                data-estimate="<?= (int) $option["estimate"] ?>"
                                <?= $formData["booking_id"] === (int) $option["id"] ? "selected" : "" ?>
                            >
                                <?= e($option["booking_code"] . " - " . $option["student_name"] . " - " . $option["facility_name"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Hanya pemesanan yang disetujui dan belum memiliki pembayaran.</small>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="payMethod">Metode</label>
                        <select id="payMethod" name="method" required>
                            <?php foreach (PAYMENT_METHODS as $method): ?>
                                <option value="<?= e($method) ?>" <?= $formData["method"] === $method ? "selected" : "" ?>>
                                    <?= e($paymentMethodLabel($method)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="payStatus">Status</label>
                        <select id="payStatus" name="status" required>
                            <option value="paid" <?= $formData["status"] === "paid" ? "selected" : "" ?>>Lunas</option>
                            <option value="pending" <?= $formData["status"] === "pending" ? "selected" : "" ?>>Menunggu verifikasi</option>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label for="payAmount">Jumlah (Rp)</label>
                    <input type="number" id="payAmount" name="amount" min="1" step="1"
                           value="<?= e($formData["amount"]) ?>" required>
                    <small id="payAmountHint">Terisi otomatis dari tarif dan durasi pemesanan. Boleh diubah.</small>
                </div>

                <div class="field">
                    <label for="payReference">Referensi transaksi (opsional)</label>
                    <input type="text" id="payReference" name="reference" maxlength="100"
                           value="<?= e($formData["reference"]) ?>">
                </div>

                <div class="modal-foot">
                    <button type="button" class="btn btn-outline" data-close-modal="createModal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Pembayaran</button>
                </div>
            </form>
        <?php else: ?>
            <div class="table-empty" style="padding: 24px 0; text-align: center;">
                <strong>Tidak ada pemesanan yang perlu dicatat</strong>
                <p>Semua pemesanan yang disetujui sudah memiliki data pembayaran.</p>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline" data-close-modal="createModal">Tutup</button>
            </div>
        <?php endif; ?>
    </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function () {
    const reasonField = document.getElementById("reasonField");
    const referenceField = document.getElementById("referenceField");
    const reason = document.getElementById("decisionReason");
    const submit = document.getElementById("decisionSubmit");

    document.querySelectorAll(".open-decision").forEach(function (button) {
        button.addEventListener("click", function () {
            const confirming = button.dataset.mode === "confirm";

            document.getElementById("decisionAction").value = button.dataset.mode;
            document.getElementById("decisionId").value = button.dataset.id;
            document.getElementById("decisionTitle").textContent =
                confirming ? "Konfirmasi Pembayaran" : "Tolak Pembayaran";
            document.getElementById("decisionDesc").textContent =
                "Pemesanan " + button.dataset.code + " - " + button.dataset.amount;
            document.getElementById("decisionReference").value = button.dataset.reference || "";
            reason.value = "";

            referenceField.hidden = !confirming;
            reasonField.hidden = confirming;
            reason.required = !confirming;

            submit.textContent = confirming ? "Konfirmasi Pembayaran" : "Tolak Pembayaran";
            submit.className = "btn " + (confirming ? "btn-success" : "btn-danger-solid");

            openModal("decisionModal");
        });
    });

    const createButton = document.getElementById("openCreateModal");
    const booking = document.getElementById("payBooking");
    const amount = document.getElementById("payAmount");

    createButton.addEventListener("click", function () {
        openModal("createModal");
    });

    if (booking && amount) {
        booking.addEventListener("change", function () {
            const option = booking.options[booking.selectedIndex];
            const estimate = option ? parseInt(option.dataset.estimate || "0", 10) : 0;
            amount.value = estimate > 0 ? estimate : "";
        });
    }

<?php if ($showModal): ?>
    openModal("createModal");
<?php endif; ?>
});
</script>

</body>
</html>