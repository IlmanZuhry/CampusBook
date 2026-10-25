<?php
session_start();

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/admin_helpers.php";

require_admin();

$keyword = trim($_GET["search"] ?? "");
$statusFilter = $_GET["status"] ?? "";
$dateFilter = $_GET["date"] ?? "";
$facilityFilter = (int) ($_GET["facility"] ?? 0);
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
} else {
    $statusFilter = "";
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
} else {
    $facilityFilter = 0;
}

$from = "
    FROM bookings b
    JOIN students s ON s.id = b.user_id
    JOIN facilities f ON f.id = b.facility_id
    LEFT JOIN payments p ON p.booking_id = b.id
";

$bookings = db_all(
    $conn,
    "SELECT b.booking_code, b.booking_date, b.start_time, b.end_time,
            b.status, b.purpose, b.admin_note, b.created_at,
            s.name AS student_name, s.nim,
            f.name AS facility_name, f.hourly_rate,
            p.payment_status, p.amount AS payment_amount
     " . $from . $where . "
     ORDER BY b.booking_date DESC, b.start_time DESC",
    $types,
    $params
);

$facilityName = "Semua Fasilitas";
if ($facilityFilter > 0) {
    $facility = db_one($conn, "SELECT name FROM facilities WHERE id = ? LIMIT 1", "i", [$facilityFilter]);
    if ($facility) $facilityName = $facility["name"];
}
$statusName = $statusFilter !== "" ? booking_status_meta($statusFilter)[0] : "Semua Status";
$paidCount = 0;
foreach ($bookings as $row) {
    if (($row["payment_status"] ?? "") === "paid") $paidCount++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pemesanan - CampusBook</title>
    <style>
        @page { size: landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #172033; margin: 0; font-size: 10px; }
        .toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; margin: 0 auto 18px; max-width: 1200px; }
        .toolbar button, .toolbar a { border:0; border-radius:7px; padding:10px 14px; background:#1264ed; color:white; text-decoration:none; font-weight:700; cursor:pointer; font-size:13px; }
        .toolbar a { background:#edf2fa; color:#26364f; }
        .report { max-width: 1200px; margin: 0 auto; }
        .brand { border-bottom:3px solid #1768f5; padding-bottom:12px; margin-bottom:16px; }
        .brand small { color:#1768f5; letter-spacing:2px; font-weight:bold; }
        h1 { margin:8px 0 5px; font-size:24px; }
        .muted { color:#64748b; }
        .meta { display:flex; flex-wrap:wrap; gap:8px 24px; margin:14px 0; }
        .summary { display:flex; gap:12px; margin:14px 0 18px; }
        .summary div { border:1px solid #dbe4f0; border-radius:8px; padding:10px 14px; min-width:150px; }
        .summary span { display:block; color:#64748b; margin-bottom:5px; }
        .summary strong { font-size:16px; }
        table { width:100%; border-collapse:collapse; }
        th,td { border:1px solid #d9e1ec; padding:7px 6px; text-align:left; vertical-align:top; }
        th { background:#edf3fc; color:#1e3150; font-size:9px; }
        td { font-size:9px; }
        .footer { margin-top:14px; display:flex; justify-content:space-between; color:#64748b; }
        @media print { .toolbar { display:none; } .report { max-width:none; } body { font-size:9px; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <span class="muted">Pratinjau laporan pemesanan CampusBook</span>
        <div><a href="pemesanan.php">Kembali</a> <button onclick="window.print()">Cetak / Simpan PDF</button></div>
    </div>
    <main class="report">
        <header class="brand">
            <small>CAMPUSBOOK · ADMINISTRATION REPORT</small>
            <h1>Laporan Pemesanan Fasilitas</h1>
            <div class="muted">Rekap data pemesanan fasilitas kampus</div>
        </header>
        <div class="meta">
            <span><strong>Tanggal cetak:</strong> <?= e(date("d-m-Y H:i")) ?></span>
            <span><strong>Status:</strong> <?= e($statusName) ?></span>
            <span><strong>Fasilitas:</strong> <?= e($facilityName) ?></span>
            <?php if ($dateFilter !== ""): ?><span><strong>Tanggal pemakaian:</strong> <?= e(tanggal_id($dateFilter)) ?></span><?php endif; ?>
            <?php if ($keyword !== ""): ?><span><strong>Kata kunci:</strong> <?= e($keyword) ?></span><?php endif; ?>
        </div>
        <section class="summary">
            <div><span>Total pemesanan</span><strong><?= count($bookings) ?></strong></div>
            <div><span>Pemesanan lunas</span><strong><?= $paidCount ?></strong></div>
        </section>
        <table>
            <thead><tr><th>No.</th><th>Kode Pesanan</th><th>Nama Mahasiswa</th><th>NIM</th><th>Fasilitas</th><th>Tanggal</th><th>Waktu</th><th>Status Pemesanan</th><th>Status Pembayaran</th><th>Jumlah (Rp)</th><th>Keperluan</th></tr></thead>
            <tbody>
            <?php if ($bookings): ?>
                <?php foreach ($bookings as $i => $booking):
                    $bookingStatus = booking_status_meta($booking["status"])[0];
                    $paymentStatus = $booking["payment_status"] ? payment_status_meta($booking["payment_status"])[0] : ((float)$booking["hourly_rate"] > 0 ? "Belum dibayar" : "Gratis");
                ?>
                <tr>
                    <td><?= $i + 1 ?></td><td><?= e($booking["booking_code"]) ?></td><td><?= e($booking["student_name"]) ?></td><td><?= e($booking["nim"]) ?></td><td><?= e($booking["facility_name"]) ?></td><td><?= e(tanggal_id($booking["booking_date"])) ?></td><td><?= e(jam($booking["start_time"])) ?>–<?= e(jam($booking["end_time"])) ?></td><td><?= e($bookingStatus) ?></td><td><?= e($paymentStatus) ?></td><td><?= $booking["payment_amount"] !== null ? e(number_format((float)$booking["payment_amount"], 0, ",", ".")) : "-" ?></td><td><?= e($booking["purpose"] ?? "-") ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="11" style="text-align:center">Tidak ada data pemesanan yang sesuai filter.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <div class="footer"><span>CampusBook · Laporan internal admin</span><span>Jumlah data: <?= count($bookings) ?></span></div>
    </main>
</body>
</html>
