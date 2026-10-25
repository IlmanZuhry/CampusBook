
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

function notifEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$studentId = (int) $_SESSION["user_id"];
$notifications = [];
$unreadCount = 0;

/*
 * Ambil hanya notifikasi milik mahasiswa yang sedang login.
 */
$sql = "
    SELECT
        id,
        booking_id,
        title,
        message,
        channel,
        status,
        sent_at,
        created_at
    FROM notifications
    WHERE user_id = ?
      AND channel = 'in_app'
    ORDER BY created_at DESC, id DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $studentId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    $stmt->close();
}

foreach ($notifications as $notification) {
    if ($notification["status"] === "pending") {
        $unreadCount++;
    }
}

$pageTitle = "Notifikasi";
$pageDescription = "Informasi terbaru tentang pemesanan dan pembayaran kamu.";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi | CampusBook</title>

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

        <section class="notification-page">
            <div class="notification-summary">
                <div>
                    <h2>Semua Notifikasi</h2>
                    <p>Informasi pemesanan, persetujuan, dan pembayaran CampusBook.</p>
                </div>

                <div class="notification-count">
                    <i class="fa-solid fa-bell"></i>
                    <?= count($notifications) ?> notifikasi
                </div>
            </div>

            <?php if (!$notifications): ?>
                <div class="notification-empty">
                    <i class="fa-regular fa-bell-slash"></i>
                    <h3>Belum ada notifikasi</h3>
                    <p>
                        Notifikasi akan muncul di sini ketika ada pembaruan
                        pada pemesanan atau pembayaran kamu.
                    </p>
                </div>
            <?php else: ?>
                <div class="notification-list">
                    <?php foreach ($notifications as $item): ?>
                        <?php
                        $title = strtolower($item["title"]);
                        $icon = "fa-bell";

                        if (strpos($title, "pembayaran") !== false) {
                            $icon = "fa-credit-card";
                        } elseif (
                            strpos($title, "pemesanan") !== false ||
                            strpos($title, "booking") !== false
                        ) {
                            $icon = "fa-calendar-check";
                        }

                        $status = $item["status"];
                        $statusLabels = [
                            "sent" => "Terkirim",
                            "pending" => "Menunggu",
                            "failed" => "Gagal dikirim",
                        ];
                        ?>

                        <article class="notification-card">
                            <div class="notification-icon">
                                <i class="fa-solid <?= notifEscape($icon) ?>"></i>
                            </div>

                            <div class="notification-content">
                                <div class="notification-title-row">
                                    <h3><?= notifEscape($item["title"]) ?></h3>

                                    <span class="notification-status <?= notifEscape($status) ?>">
                                        <?= notifEscape(
                                            $statusLabels[$status] ?? $status
                                        ) ?>
                                    </span>
                                </div>

                                <p class="notification-message">
                                    <?= nl2br(notifEscape($item["message"])) ?>
                                </p>

                                <div class="notification-meta">
                                    <span>
                                        <i class="fa-regular fa-clock"></i>
                                        <?= notifEscape(date(
                                            "d M Y, H:i",
                                            strtotime($item["created_at"])
                                        )) ?>
                                    </span>

                                    <?php if ($item["booking_id"] !== null): ?>
                                        <span>
                                            <i class="fa-solid fa-link"></i>
                                            ID pemesanan #<?= (int) $item["booking_id"] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
