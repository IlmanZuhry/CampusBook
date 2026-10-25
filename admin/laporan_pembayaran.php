<?php
session_start();

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/admin_helpers.php";

require_admin();

const PAYMENT_METHOD_LABELS = [
    "transfer" => "Transfer Bank",
    "qris" => "QRIS",
    "va_bca" => "BCA Virtual Account",
    "va_bni" => "BNI Virtual Account",
    "va_bri" => "BRI Virtual Account",
    "va_mandiri" => "Mandiri Virtual Account",
];

$keyword = trim($_GET["search"] ?? "");
$statusFilter = $_GET["status"] ?? "";
$methodFilter = $_GET["method"] ?? "";

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

$allowedMethods = array_keys(PAYMENT_METHOD_LABELS);
if ($methodFilter === "transfer") {
    $where .= " AND p.payment_method IN ('transfer', 'va_bca', 'va_bni', 'va_bri', 'va_mandiri')";
} elseif (in_array($methodFilter, $allowedMethods, true)) {
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

$payments = db_all(
    $conn,
    "SELECT p.id, p.amount, p.payment_method, p.payment_status,
            p.transaction_reference, p.paid_at, p.created_at,
            b.booking_code, b.booking_date,
            s.name AS student_name, s.nim,
            f.name AS facility_name
     " . $from . $where . "
     ORDER BY p.created_at DESC",
    $types,
    $params
);

$totalAmountPaid = 0;
foreach ($payments as $payment) {
    if ($payment["payment_status"] === "paid") {
        $totalAmountPaid += (float) $payment["amount"];
    }
}

$filterDescriptions = [];
if ($keyword !== "") $filterDescriptions[] = "Pencarian: " . $keyword;
if ($statusFilter !== "") $filterDescriptions[] = "Status: " . ["pending" => "Menunggu Verifikasi", "paid" => "Lunas", "failed" => "Gagal"][$statusFilter];
if ($methodFilter !== "") $filterDescriptions[] = "Metode: " . (PAYMENT_METHOD_LABELS[$methodFilter] ?? $methodFilter);

function report_payment_status(string $status): string {
    return match ($status) {
        "paid" => "Lunas",
        "pending" => "Menunggu Verifikasi",
        "failed" => "Gagal",
        default => ucfirst($status),
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Pembayaran - CampusBook</title>
<style>
    @page { size: A4 landscape; margin: 14mm; }
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; color: #172b4d; margin: 0; font-size: 11px; }
    .toolbar { display: flex; justify-content: flex-end; gap: 8px; margin: 0 0 18px; }
    .toolbar button { border: 0; border-radius: 6px; padding: 10px 14px; cursor: pointer; font-weight: 700; }
    .print { background: #1769ff; color: white; }
    .close { background: #edf2f7; color: #172b4d; }
    .brand { font-size: 12px; font-weight: 800; letter-spacing: 2px; color: #1769ff; margin-bottom: 7px; }
    h1 { font-size: 24px; margin: 0 0 5px; }
    .subtitle { color: #64748b; margin: 0 0 18px; }
    .meta { display: flex; justify-content: space-between; gap: 16px; border-top: 2px solid #1769ff; border-bottom: 1px solid #dbe4f0; padding: 10px 0; margin-bottom: 14px; }
    .summary { display: flex; gap: 12px; margin: 0 0 16px; }
    .summary div { border: 1px solid #dbe4f0; border-radius: 7px; padding: 10px 12px; flex: 1; }
    .summary span { display: block; color: #64748b; margin-bottom: 5px; }
    .summary strong { font-size: 14px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #edf4ff; text-align: left; font-size: 10px; }
    th, td { border: 1px solid #d7e0ec; padding: 7px 6px; vertical-align: top; }
    td.amount { text-align: right; white-space: nowrap; }
    .empty { text-align: center; padding: 22px; color: #64748b; }
    .footer { margin-top: 15px; display: flex; justify-content: space-between; color: #64748b; font-size: 10px; }
    @media print { .toolbar { display: none; } body { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
</style>
</head>
<body>
<div class="toolbar">
    <button class="close" type="button" onclick="window.close()">Tutup</button>
    <button class="print" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
</div>
<div class="brand">CAMPUSBOOK</div>
<h1>Laporan Pembayaran</h1>
<p class="subtitle">Rekap transaksi pembayaran fasilitas kampus.</p>
<div class="meta">
    <div><strong>Tanggal cetak:</strong> <?= e(date("d-m-Y H:i")) ?></div>
    <div><strong>Filter:</strong> <?= e($filterDescriptions ? implode(" | ", $filterDescriptions) : "Semua data") ?></div>
</div>
<div class="summary">
    <div><span>Jumlah transaksi</span><strong><?= count($payments) ?></strong></div>
    <div><span>Total pembayaran lunas</span><strong><?= e(rupiah($totalAmountPaid)) ?></strong></div>
    <div><span>Menunggu verifikasi</span><strong><?= count(array_filter($payments, fn($p) => $p["payment_status"] === "pending")) ?></strong></div>
    <div><span>Pembayaran gagal</span><strong><?= count(array_filter($payments, fn($p) => $p["payment_status"] === "failed")) ?></strong></div>
</div>
<table>
<thead><tr>
    <th>No.</th><th>Kode Pesanan</th><th>Nama Mahasiswa</th><th>NIM</th><th>Fasilitas</th>
    <th>Metode Pembayaran</th><th>Jumlah</th><th>Referensi Transaksi</th><th>Status</th><th>Waktu Bayar</th>
</tr></thead>
<tbody>
<?php if ($payments): ?>
    <?php foreach ($payments as $index => $payment): ?>
    <tr>
        <td><?= $index + 1 ?></td>
        <td><?= e($payment["booking_code"]) ?></td>
        <td><?= e($payment["student_name"]) ?></td>
        <td><?= e($payment["nim"]) ?></td>
        <td><?= e($payment["facility_name"]) ?></td>
        <td><?= e(PAYMENT_METHOD_LABELS[$payment["payment_method"]] ?? $payment["payment_method"]) ?></td>
        <td class="amount"><?= e(rupiah($payment["amount"])) ?></td>
        <td><?= e($payment["transaction_reference"] ?: "-") ?></td>
        <td><?= e(report_payment_status($payment["payment_status"])) ?></td>
        <td><?= $payment["paid_at"] ? e(tanggal_id($payment["paid_at"]) . " " . substr($payment["paid_at"], 11, 5)) : "-" ?></td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr><td class="empty" colspan="10">Tidak ada data pembayaran untuk filter yang dipilih.</td></tr>
<?php endif; ?>
</tbody>
</table>
<div class="footer"><span>Dokumen laporan pembayaran CampusBook</span><span>Jumlah data: <?= count($payments) ?></span></div>
<script>
window.addEventListener("load", function () {
    // Tampilkan dialog cetak agar pengguna dapat memilih printer atau Save as PDF.
    setTimeout(function () { window.print(); }, 350);
});
</script>
</body>
</html>
