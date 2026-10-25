
<?php
$adminPhoto = null;
$adminName = $_SESSION["name"] ?? "Administrator";

if (isset($conn) && isset($_SESSION["user_id"])) {
    $adminId = (int) $_SESSION["user_id"];

    $stmtPhoto = $conn->prepare(
        "SELECT profile_photo FROM admins WHERE id = ? LIMIT 1"
    );

    if ($stmtPhoto) {
        $stmtPhoto->bind_param("i", $adminId);
        $stmtPhoto->execute();

        $photoResult = $stmtPhoto->get_result()->fetch_assoc();

        if ($photoResult) {
            $adminPhoto = $photoResult["profile_photo"] ?? null;
        }

        $stmtPhoto->close();
    }
}
?>

<header class="admin-header">
    <div class="header-heading">
        <span class="header-eyebrow">CAMPUSBOOK ADMINISTRATION</span>

        <h1>
            <?= htmlspecialchars(
                $pageTitle ?? "Beranda",
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </h1>

        <p>
            <?= htmlspecialchars(
                $pageDescription ?? "Ringkasan aktivitas dan pengelolaan fasilitas kampus.",
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </p>
    </div>

    <div class="admin-profile">
        <div class="profile-avatar admin-avatar">
            <?php if (!empty($adminPhoto)): ?>
                <img
                    src="../assets/uploads/profile/<?= rawurlencode(
                        basename($adminPhoto)
                    ) ?>"
                    alt="Foto profil admin"
                >
            <?php else: ?>
                <?= htmlspecialchars(
                    strtoupper(substr($adminName, 0, 1)),
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            <?php endif; ?>
        </div>

        <div class="profile-info">
            <strong>
                <?= htmlspecialchars(
                    $adminName,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </strong>
            <span>Administrator</span>
        </div>
    </div>
</header>
