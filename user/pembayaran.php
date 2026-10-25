
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
require_once __DIR__ . "/../includes/admin_helpers.php";

function paymentEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function paymentRupiah($value): string
{
    return "Rp " . number_format((float) $value, 0, ",", ".");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$studentId = (int) $_SESSION["user_id"];
$notice = null;
$allowedMethods = [
    "qris" => "QRIS",
    "va_bca" => "BCA Virtual Account",
    "va_bni" => "BNI Virtual Account",
    "va_bri" => "BRI Virtual Account",
    "va_mandiri" => "Mandiri Virtual Account",
];

$messages = [
    "success" => ["success", "Simulasi pembayaran berhasil. Tagihan sudah lunas."],
    "invalid" => ["error", "Transaksi tidak valid atau status pembayaran sudah berubah."],
    "failed" => ["error", "Pembayaran gagal diproses. Silakan coba kembali."],
];

if (isset($_GET["result"])) {
    $notice = $messages[$_GET["result"]] ?? null;
}

/*
|--------------------------------------------------------------------------
| PROSES SIMULASI PEMBAYARAN
|--------------------------------------------------------------------------
*/
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "simulate_payment"
) {
    $token = $_POST["csrf_token"] ?? "";
    $paymentId = filter_var(
        $_POST["payment_id"] ?? null,
        FILTER_VALIDATE_INT
    );
    $method = $_POST["payment_method"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token) ||
        !$paymentId ||
        !isset($allowedMethods[$method])
    ) {
        header("Location: pembayaran.php?result=invalid");
        exit;
    }

    $reference = "CB-DUMMY-" . strtoupper(bin2hex(random_bytes(5)));

    $sql = "
        UPDATE payments p
        JOIN bookings b ON b.id = p.booking_id
        SET
            p.payment_method = ?,
            p.payment_status = 'paid',
            p.transaction_reference = ?,
            p.paid_at = NOW()
        WHERE p.id = ?
          AND b.user_id = ?
          AND b.status = 'approved'
          AND p.payment_status = 'pending'
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param(
            "ssii",
            $method,
            $reference,
            $paymentId,
            $studentId
        );

        $executed = $stmt->execute();
        $updated = $stmt->affected_rows;
        $stmt->close();

        if ($executed && $updated === 1) {
            header("Location: pembayaran.php?result=success");
            exit;
        }
    }

    header("Location: pembayaran.php?result=invalid");
    exit;
}

/*
|--------------------------------------------------------------------------
| BUAT TAGIHAN UNTUK PEMESANAN YANG DISETUJUI
|--------------------------------------------------------------------------
| booking_id unik sehingga satu pemesanan tidak mendapat tagihan ganda.
*/
$createSql = "
    INSERT IGNORE INTO payments
        (booking_id, amount, payment_method, payment_status)
    SELECT
        b.id,
        ROUND(
            TIMESTAMPDIFF(MINUTE, b.start_time, b.end_time)
            / 60 * f.hourly_rate,
            2
        ),
        'qris',
        'pending'
    FROM bookings b
    JOIN facilities f ON f.id = b.facility_id
    LEFT JOIN payments p ON p.booking_id = b.id
    WHERE b.user_id = ?
      AND b.status = 'approved'
      AND f.hourly_rate > 0
      AND p.id IS NULL
";

$createStmt = $conn->prepare($createSql);

if ($createStmt) {
    $createStmt->bind_param("i", $studentId);
    $createStmt->execute();
    $createStmt->close();
}

/*
|--------------------------------------------------------------------------
| AMBIL TRANSAKSI MAHASISWA
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        p.id AS payment_id,
        p.amount,
        p.payment_method,
        p.payment_status,
        p.transaction_reference,
        p.paid_at,
        p.created_at AS payment_created,
        b.id AS booking_id,
        b.booking_code,
        b.booking_date,
        b.start_time,
        b.end_time,
        b.status AS booking_status,
        f.name AS facility_name
    FROM payments p
    JOIN bookings b ON b.id = p.booking_id
    JOIN facilities f ON f.id = b.facility_id
    WHERE b.user_id = ?
    ORDER BY p.created_at DESC, p.id DESC
";

$stmt = $conn->prepare($sql);
$payments = [];

if ($stmt) {
    $stmt->bind_param("i", $studentId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }

    $stmt->close();
}

$totalPending = 0;
$totalPaid = 0;
$amountPaid = 0;

foreach ($payments as $payment) {
    if ($payment["payment_status"] === "pending") {
        $totalPending++;
    }

    if ($payment["payment_status"] === "paid") {
        $totalPaid++;
        $amountPaid += (float) $payment["amount"];
    }
}

$pageTitle = "Pembayaran";
$pageDescription = "Kelola tagihan peminjaman fasilitas kampus.";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pembayaran | CampusBook</title>

    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/user.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        
    </style>
</head>

<body>
<div class="admin-layout">
    <?php require_once __DIR__ . "/../includes/user_sidebar.php"; ?>

    <main class="admin-main">
        <?php require_once __DIR__ . "/../includes/user_header.php"; ?>

        <section class="payment-page">

            

            <?php if ($notice): ?>
                <div class="payment-notice <?= paymentEscape($notice[0]) ?>"
                     role="status">
                    <i class="fa-solid <?= $notice[0] === "success"
                        ? "fa-circle-check"
                        : "fa-circle-exclamation" ?>"></i>
                    <?= paymentEscape($notice[1]) ?>
                </div>
            <?php endif; ?>

            <div class="payment-stats">
                <article class="payment-stat">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <span>Total Transaksi</span>
                    <strong><?= count($payments) ?></strong>
                </article>

                <article class="payment-stat">
                    <i class="fa-solid fa-clock"></i>
                    <span>Menunggu Pembayaran</span>
                    <strong><?= $totalPending ?></strong>
                </article>

                <article class="payment-stat">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Total Sudah Dibayar</span>
                    <strong><?= paymentEscape(paymentRupiah($amountPaid)) ?></strong>
                </article>
            </div>

            <section class="payment-panel">
                <div class="payment-panel-heading">
                    <h3>Daftar Pembayaran</h3>
                    <p>Tagihan muncul setelah pemesanan berbayar disetujui admin.</p>
                </div>

                <?php if (!$payments): ?>
                    <div class="payment-empty">
                        <i class="fa-solid fa-wallet"></i>
                        <h3>Belum ada tagihan</h3>
                        <p>
                            Jika pemesananmu sudah disetujui, tagihan akan
                            muncul di halaman ini.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="payment-table-wrap">
                        <table class="payment-table">
                            <thead>
                                <tr>
                                    <th>Kode Pemesanan</th>
                                    <th>Fasilitas</th>
                                    <th>Nominal</th>
                                    <th>Metode</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <?php
                                $status = $payment["payment_status"];
                                $method = $allowedMethods[$payment["payment_method"]]
                                    ?? "Belum dipilih";

                                $statusLabels = [
                                    "pending" => "Belum dibayar",
                                    "paid" => "Lunas",
                                    "failed" => "Gagal",
                                ];

                                $date = date(
                                    "d M Y",
                                    strtotime($payment["booking_date"])
                                );
                                ?>

                                <tr>
                                    <td>
                                        <strong>
                                            <?= paymentEscape($payment["booking_code"]) ?>
                                        </strong>
                                        <span class="payment-sub">
                                            <?= paymentEscape($date) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= paymentEscape($payment["facility_name"]) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= paymentEscape(paymentRupiah($payment["amount"])) ?>
                                        </strong>
                                    </td>

                                    <td><?= paymentEscape($method) ?></td>

                                    <td>
                                        <span class="payment-status <?= paymentEscape($status) ?>">
                                            <?php if ($status === "paid"): ?>
                                                <i class="fa-solid fa-check"></i>
                                            <?php elseif ($status === "pending"): ?>
                                                <i class="fa-solid fa-clock"></i>
                                            <?php else: ?>
                                                <i class="fa-solid fa-xmark"></i>
                                            <?php endif; ?>

                                            <?= paymentEscape(
                                                $statusLabels[$status] ?? $status
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if (
                                            $status === "pending" &&
                                            $payment["booking_status"] === "approved"
                                        ): ?>
                                            <button
                                                type="button"
                                                class="catalog-button"
                                                data-open-payment
                                                data-id="<?= (int) $payment["payment_id"] ?>"
                                                data-code="<?= paymentEscape($payment["booking_code"]) ?>"
                                                data-facility="<?= paymentEscape($payment["facility_name"]) ?>"
                                                data-amount="<?= paymentEscape($payment["amount"]) ?>"
                                            >
                                                <i class="fa-solid fa-credit-card"></i>
                                                Bayar
                                            </button>
                                        <?php elseif ($status === "paid"): ?>
                                            <span class="payment-sub">
                                                <i class="fa-solid fa-circle-check"></i>
                                                Selesai
                                            </span>
                                            <?php if (!empty($payment["transaction_reference"])): ?>
                                                <span class="payment-sub">
                                                    <?= paymentEscape($payment["transaction_reference"]) ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="payment-sub">Tidak tersedia</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </main>
</div>

<!-- MODAL PEMBAYARAN -->
<div class="payment-modal" id="paymentModal" aria-hidden="true">
    <div class="payment-backdrop" data-close-payment></div>

    <section class="payment-dialog"
             role="dialog"
             aria-modal="true"
             aria-labelledby="paymentModalTitle">

        <button type="button" class="payment-close"
                data-close-payment aria-label="Tutup modal">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <span class="payment-eyebrow">CAMPUSBOOK</span>
        <h2 id="paymentModalTitle">Pembayaran Tagihan</h2>
        <p class="payment-description">
            Pilih metode pembayaran untuk menyelesaikan transaksi simulasi.
        </p>

        <div class="payment-summary">
            <span id="modalBookingCode">Kode pemesanan</span>
            <strong id="modalFacility">Nama fasilitas</strong>
            <div class="payment-amount" id="modalAmount">Rp 0</div>
            <small>Nominal tagihan</small>
        </div>

        <form method="POST" id="paymentForm">
            <input type="hidden" name="action" value="simulate_payment">
            <input type="hidden" name="csrf_token"
                   value="<?= paymentEscape($_SESSION["csrf_token"]) ?>">
            <input type="hidden" name="payment_id" id="modalPaymentId">

            <label class="payment-label" for="modalPaymentMethod">
                Metode pembayaran
            </label>

            <select class="payment-select"
                    name="payment_method"
                    id="modalPaymentMethod"
                    required>
                <?php foreach ($allowedMethods as $value => $label): ?>
                    <option value="<?= paymentEscape($value) ?>">
                        <?= paymentEscape($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="payment-instruction" id="paymentInstruction">
                <i class="fa-solid fa-qrcode"></i>
                <strong>QRIS Simulasi</strong>
                <p>
                    Ilustrasi QRIS ini bukan kode pembayaran nyata dan
                    tidak dapat dipindai.
                </p>
            </div>

            <p class="payment-demo-small">
                <i class="fa-solid fa-circle-info"></i>
                Pembayaran ini hanya untuk demo. Klik tombol di bawah
                untuk menyelesaikan simulasi.
            </p>

            <div class="payment-actions">
                <button type="button" class="catalog-button secondary"
                        data-close-payment>
                    Batal
                </button>

                <button type="submit" class="catalog-button"
                        id="simulatePaymentButton">
                    <i class="fa-solid fa-check"></i>
                    Simulasikan Pembayaran
                </button>
            </div>
        </form>
    </section>
</div>

<div class="payment-modal" id="paymentConfirmModal" aria-hidden="true">
    <div class="payment-backdrop"></div>

    <section class="payment-dialog payment-confirm-dialog"
             role="dialog"
             aria-modal="true"
             aria-labelledby="confirmPaymentTitle">

        <div class="payment-confirm-icon">
            <i class="fa-solid fa-circle-question"></i>
        </div>

        <h2 id="confirmPaymentTitle">Konfirmasi Pembayaran</h2>

        <p class="payment-description">
            Apakah kamu yakin ingin menyelesaikan simulasi pembayaran ini?
            Tidak ada uang sungguhan yang ditransaksikan.
        </p>

        <div class="payment-actions">
            <button type="button"
                    class="catalog-button secondary"
                    id="confirmPaymentNo">
                Batal
            </button>

            <button type="button"
                    class="catalog-button"
                    id="confirmPaymentYes">
                <i class="fa-solid fa-check"></i>
                Ya, Bayar Sekarang
            </button>
        </div>
    </section>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("paymentModal");
    const form = document.getElementById("paymentForm");
    const paymentId = document.getElementById("modalPaymentId");
    const bookingCode = document.getElementById("modalBookingCode");
    const facility = document.getElementById("modalFacility");
    const amount = document.getElementById("modalAmount");
    const method = document.getElementById("modalPaymentMethod");
    const instruction = document.getElementById("paymentInstruction");
    const submitButton = document.getElementById("simulatePaymentButton");

    if (!modal || !form) return;

    const methodLabels = {
        qris: "QRIS Simulasi",
        va_bca: "BCA Virtual Account",
        va_bni: "BNI Virtual Account",
        va_bri: "BRI Virtual Account",
        va_mandiri: "Mandiri Virtual Account"
    };

    function formatRupiah(value) {
        return "Rp " + Number(value).toLocaleString("id-ID", {
            maximumFractionDigits: 0
        });
    }

    function updateInstruction() {
        const selected = method.value;
        instruction.replaceChildren();

        const icon = document.createElement("i");
        icon.className = selected === "qris"
            ? "fa-solid fa-qrcode"
            : "fa-solid fa-building-columns";

        const title = document.createElement("strong");
        title.textContent = methodLabels[selected] || "Metode pembayaran";

        const description = document.createElement("p");

        if (selected === "qris") {
            description.textContent =
                "Ini hanya ilustrasi QRIS. Tidak dapat dipindai untuk pembayaran nyata.";
        } else {
            const va = document.createElement("div");
            va.className = "payment-va-number";

            // Nomor VA dummy dari ID transaksi, bukan nomor rekening bank.
            va.textContent =
                "8808" + String(paymentId.value).padStart(8, "0");

            const vaDescription = document.createElement("p");
            vaDescription.textContent =
                "Nomor VA dummy untuk demonstrasi. Jangan gunakan untuk transfer nyata.";

            instruction.append(icon, title, va, vaDescription);
            return;
        }

        instruction.append(icon, title, description);
    }

    function openModal(button) {
        paymentId.value = button.dataset.id;
        bookingCode.textContent = button.dataset.code;
        facility.textContent = button.dataset.facility;
        amount.textContent = formatRupiah(button.dataset.amount);

        method.value = "qris";
        updateInstruction();

        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
    }

    function closeModal() {
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
    }

    document.querySelectorAll("[data-open-payment]").forEach(function (button) {
        button.addEventListener("click", function () {
            openModal(button);
        });
    });

    modal.querySelectorAll("[data-close-payment]").forEach(function (button) {
        button.addEventListener("click", closeModal);
    });

    method.addEventListener("change", updateInstruction);

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && modal.classList.contains("show")) {
            closeModal();
        }
    });

    
        const confirmModal = document.getElementById("paymentConfirmModal");
        const confirmYes = document.getElementById("confirmPaymentYes");
        const confirmNo = document.getElementById("confirmPaymentNo");

        let paymentConfirmed = false;

        form.addEventListener("submit", function (event) {
            if (paymentConfirmed) {
                return;
            }

            event.preventDefault();

            confirmModal.classList.add("show");
            confirmModal.setAttribute("aria-hidden", "false");
        });

        confirmNo.addEventListener("click", function () {
            confirmModal.classList.remove("show");
            confirmModal.setAttribute("aria-hidden", "true");
        });

        confirmYes.addEventListener("click", function () {
            paymentConfirmed = true;
            confirmModal.classList.remove("show");
            confirmModal.setAttribute("aria-hidden", "true");

            submitButton.disabled = true;
            submitButton.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';

            form.requestSubmit();
        }); 

});
</script>
</body>
</html>
