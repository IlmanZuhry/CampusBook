
<?php
$pageTitle = $pageTitle ?? "Beranda";
$pageDescription = $pageDescription
    ?? "Pantau aktivitas dan kebutuhan fasilitas kampusmu.";
?>

<header class="admin-header user-header">
    <div class="header-heading">
        <span class="header-eyebrow">CAMPUSBOOK STUDENT PORTAL</span>

        <h1><?= htmlspecialchars($pageTitle) ?></h1>

        <p><?= htmlspecialchars($pageDescription) ?></p>
    </div>

    <div class="admin-profile">
        <div class="profile-avatar">
            <?= strtoupper(substr($_SESSION["name"] ?? "M", 0, 1)) ?>
        </div>

        <div class="profile-info">
            <strong>
                <?= htmlspecialchars($_SESSION["name"] ?? "Mahasiswa") ?>
            </strong>
            <span>Mahasiswa</span>
        </div>
    </div>
</header>
