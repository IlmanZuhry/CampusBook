<?php
/**
 * Helper bersama untuk halaman admin CampusBook.
 * Pola yang dipakai sama dengan admin/data_mahasiswa.php:
 * guard sesi admin, token CSRF, flash message, prepared statement.
 */

/* ---------- Akses & keamanan ---------- */

function require_admin(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (
        !isset($_SESSION["user_id"]) ||
        ($_SESSION["role"] ?? "") !== "admin"
    ) {
        header("Location: ../login.php");
        exit;
    }

    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function csrf_valid(): bool
{
    return hash_equals(
        $_SESSION["csrf_token"] ?? "",
        $_POST["csrf_token"] ?? ""
    );
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . e($_SESSION["csrf_token"] ?? "") . '">';
}

/* ---------- Flash message ---------- */

function flash_set(string $message, string $type = "success"): void
{
    $_SESSION["flash"] = ["message" => $message, "type" => $type];
}

function flash_get(): ?array
{
    if (!isset($_SESSION["flash"])) {
        return null;
    }

    $flash = $_SESSION["flash"];
    unset($_SESSION["flash"]);

    return $flash;
}

function redirect_to(string $page): void
{
    header("Location: " . $page);
    exit;
}

/**
 * URL kembali setelah aksi POST dengan mempertahankan filter aktif.
 * Nilai filter dibaca dari field tersembunyi "back" dan hanya kunci
 * yang diizinkan ($allowedKeys) yang diteruskan.
 */
function back_url_for(string $page, array $allowedKeys): string
{
    parse_str($_POST["back"] ?? "", $query);
    $query = array_intersect_key($query, array_flip($allowedKeys));
    $query = array_filter($query, fn($v) => is_string($v) && $v !== "");

    return $page . ($query ? "?" . http_build_query($query) : "");
}

/* ---------- Database ---------- */

/**
 * Jalankan SELECT dengan prepared statement, kembalikan semua baris.
 */
function db_all(mysqli $conn, string $sql, string $types = "", array $params = []): array
{
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return [];
    }

    if ($params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $rows;
}

function db_one(mysqli $conn, string $sql, string $types = "", array $params = []): ?array
{
    $rows = db_all($conn, $sql, $types, $params);

    return $rows[0] ?? null;
}

/**
 * Jalankan INSERT/UPDATE/DELETE. Mengembalikan jumlah baris terdampak,
 * atau false jika gagal. Kode error MySQL disimpan di $errno.
 */
function db_run(mysqli $conn, string $sql, string $types = "", array $params = [], ?int &$errno = null)
{
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        $errno = mysqli_errno($conn);
        return false;
    }

    if ($params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    try {
        $ok = mysqli_stmt_execute($stmt);
    } catch (mysqli_sql_exception $ex) {
        $errno = $ex->getCode();
        mysqli_stmt_close($stmt);
        return false;
    }

    $errno = mysqli_stmt_errno($stmt);
    $affected = $ok ? mysqli_stmt_affected_rows($stmt) : false;
    mysqli_stmt_close($stmt);

    return $affected;
}

/* ---------- Format tampilan ---------- */

function rupiah($amount): string
{
    return "Rp " . number_format((float) $amount, 0, ",", ".");
}

function tanggal_id(?string $date): string
{
    if (!$date) {
        return "-";
    }

    $months = [
        1 => "Jan", "Feb", "Mar", "Apr", "Mei", "Jun",
        "Jul", "Agu", "Sep", "Okt", "Nov", "Des",
    ];

    $time = strtotime($date);

    return date("j", $time) . " " . $months[(int) date("n", $time)] . " " . date("Y", $time);
}

function jam(?string $time): string
{
    return $time ? substr($time, 0, 5) : "-";
}

/* ---------- Label & kelas status ---------- */

function booking_status_meta(string $status): array
{
    $map = [
        "pending"   => ["Menunggu",   "badge-orange"],
        "approved"  => ["Disetujui",  "badge-blue"],
        "completed" => ["Selesai",    "badge-green"],
        "rejected"  => ["Ditolak",    "badge-red"],
        "cancelled" => ["Dibatalkan", "badge-gray"],
    ];

    return $map[$status] ?? [$status, "badge-gray"];
}

function payment_status_meta(string $status): array
{
    $map = [
        "pending" => ["Menunggu verifikasi", "badge-orange"],
        "paid"    => ["Lunas",               "badge-green"],
        "failed"  => ["Gagal",               "badge-red"],
    ];

    return $map[$status] ?? [$status, "badge-gray"];
}

function payment_method_label(string $method): string
{
    $map = ["transfer" => "Transfer Bank", "qris" => "QRIS", "ewallet" => "E-Wallet"];

    return $map[$method] ?? $method;
}

function channel_label(string $channel): string
{
    $map = ["in_app" => "Dalam aplikasi", "email" => "Email", "whatsapp" => "WhatsApp"];

    return $map[$channel] ?? $channel;
}

function badge(string $label, string $class): string
{
    return '<span class="badge ' . e($class) . '">' . e($label) . '</span>';
}

/* ---------- Komponen halaman ---------- */


function render_admin_header(string $eyebrow, string $title, string $description): void
{
    global $conn;

    $adminName = $_SESSION["name"] ?? "Administrator";
    $adminPhoto = null;

    if (isset($conn, $_SESSION["user_id"])) {
        $stmt = $conn->prepare(
            "SELECT profile_photo FROM admins WHERE id = ? LIMIT 1"
        );

        if ($stmt) {
            $adminId = (int) $_SESSION["user_id"];
            $stmt->bind_param("i", $adminId);
            $stmt->execute();

            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            if ($row) {
                $adminPhoto = $row["profile_photo"] ?? null;
            }

            $stmt->close();
        }
    }

    $initial = strtoupper(substr($adminName, 0, 1));
    ?>
    <header class="admin-header">
        <div class="header-heading">
            <span class="header-eyebrow"><?= e($eyebrow) ?></span>
            <h1><?= e($title) ?></h1>
            <p><?= e($description) ?></p>
        </div>

        <div class="admin-profile">
            <div class="profile-avatar admin-avatar">
                <?php if (!empty($adminPhoto)): ?>
                    <img
                        src="../assets/uploads/profile/<?= rawurlencode(basename($adminPhoto)) ?>"
                        alt="Foto profil admin"
                    >
                <?php else: ?>
                    <?= e($initial) ?>
                <?php endif; ?>
            </div>

            <div class="profile-info">
                <strong><?= e($adminName) ?></strong>
                <span>Administrator</span>
            </div>
        </div>
    </header>
    <?php
}

function render_flash(?array $flash, string $error = ""): void
{
    if ($flash) {
        echo '<div class="page-alert ' . e($flash["type"]) . '" role="status">'
            . e($flash["message"]) . '</div>';
    }

    if ($error !== "") {
        echo '<div class="page-alert error" role="alert">' . e($error) . '</div>';
    }
}

/**
 * Tautan halaman 1..n. $query berisi filter aktif (tanpa "page").
 */
function render_pagination(int $page, int $pages, array $query): void
{
    if ($pages <= 1) {
        return;
    }

    echo '<nav class="pagination" aria-label="Halaman">';

    for ($i = 1; $i <= $pages; $i++) {
        $href = "?" . http_build_query(array_merge($query, ["page" => $i]));
        $class = $i === $page ? "page-link active" : "page-link";
        $current = $i === $page ? ' aria-current="page"' : "";
        echo '<a class="' . $class . '" href="' . e($href) . '"' . $current . '>' . $i . '</a>';
    }

    echo '</nav>';
}

/* ---------- Notifikasi ke mahasiswa ---------- */

/**
 * Kirim pesan lewat satu kanal. Mengembalikan [status, sent_at].
 *
 * - in_app   : langsung dianggap terkirim.
 * - email    : memakai mail() bawaan PHP. Di server lokal (XAMPP) biasanya
 *              belum dikonfigurasi sehingga berstatus "failed".
 *              Ganti dengan PHPMailer/SMTP saat produksi.
 * - whatsapp : tabel students belum punya nomor telepon dan belum ada
 *              gateway, jadi tetap "pending" (antrean).
 */
function deliver_channel(
    mysqli $conn,
    int $studentId,
    string $title,
    string $message,
    string $channel
): array {
    if ($channel === "in_app") {
        return ["sent", date("Y-m-d H:i:s")];
    }

    if ($channel === "email") {
        $student = db_one(
            $conn,
            "SELECT email FROM students WHERE id = ? LIMIT 1",
            "i",
            [$studentId]
        );

        $sent = $student && @mail(
            $student["email"],
            $title,
            $message,
            "Content-Type: text/plain; charset=UTF-8\r\nFrom: CampusBook <no-reply@campusbook.local>"
        );

        return $sent ? ["sent", date("Y-m-d H:i:s")] : ["failed", null];
    }

    return ["pending", null];
}

/**
 * Buat dan kirim satu notifikasi. Mengembalikan status akhir:
 * "sent", "failed", atau "pending".
 */
function send_notification(
    mysqli $conn,
    int $studentId,
    ?int $bookingId,
    string $title,
    string $message,
    string $channel = "in_app"
): string {
    if (!in_array($channel, ["in_app", "email", "whatsapp"], true)) {
        $channel = "in_app";
    }

    [$status, $sentAt] = deliver_channel($conn, $studentId, $title, $message, $channel);

    $saved = db_run(
        $conn,
        "INSERT INTO notifications
            (user_id, booking_id, title, message, channel, status, sent_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        "iisssss",
        [$studentId, $bookingId, $title, $message, $channel, $status, $sentAt]
    );

    return $saved === false ? "failed" : $status;
}