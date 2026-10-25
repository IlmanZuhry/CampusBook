<?php
$pageTitle = $pageTitle ?? "Beranda";
$pageDescription = $pageDescription ?? "Pantau aktivitas dan kebutuhan fasilitas kampusmu.";

// Ambil foto profil terbaru dari database agar tampil konsisten di semua halaman mahasiswa.
$headerPhoto = null;
$headerUserId = (int) ($_SESSION["user_id"] ?? 0);

if ($headerUserId > 0) {
    // Pastikan koneksi tersedia, tanpa menginisialisasi ulang jika sudah dimuat halaman.
    if (!isset($conn) || !($conn instanceof mysqli)) {
        require_once __DIR__ . "/../config/database.php";
    }

    if (isset($conn) && $conn instanceof mysqli) {
        $photoStmt = $conn->prepare("SELECT profile_photo FROM students WHERE id = ? LIMIT 1");
        if ($photoStmt) {
            $photoStmt->bind_param("i", $headerUserId);
            if ($photoStmt->execute()) {
                $photoResult = $photoStmt->get_result();
                $photoRow = $photoResult ? $photoResult->fetch_assoc() : null;
                if (!empty($photoRow["profile_photo"])) {
                    $photoFilename = basename((string) $photoRow["profile_photo"]);
                    $photoDiskPath = __DIR__ . "/../assets/uploads/profile/" . $photoFilename;
                    if (is_file($photoDiskPath)) {
                        // Cache-busting agar browser tidak menampilkan foto lama setelah diganti.
                        $headerPhoto = "../assets/uploads/profile/" . rawurlencode($photoFilename) . "?v=" . filemtime($photoDiskPath);
                    }
                }
            }
            $photoStmt->close();
        }
    }
}

$headerName = (string) ($_SESSION["name"] ?? "Mahasiswa");
$headerInitial = strtoupper(substr($headerName, 0, 1));
?>

<header class="admin-header user-header">
    <div class="header-heading">
        <span class="header-eyebrow">CAMPUSBOOK STUDENT PORTAL</span>
        <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, "UTF-8") ?></h1>
        <p><?= htmlspecialchars($pageDescription, ENT_QUOTES, "UTF-8") ?></p>
    </div>

    <div class="admin-profile">
        <div class="profile-avatar<?= $headerPhoto ? ' has-photo' : '' ?>">
            <?php if ($headerPhoto): ?>
                <img
                    src="<?= htmlspecialchars($headerPhoto, ENT_QUOTES, "UTF-8") ?>"
                    alt="Foto profil <?= htmlspecialchars($headerName, ENT_QUOTES, "UTF-8") ?>"
                    class="header-profile-photo"
                >
            <?php else: ?>
                <?= htmlspecialchars($headerInitial, ENT_QUOTES, "UTF-8") ?>
            <?php endif; ?>
        </div>

        <div class="profile-info">
            <strong><?= htmlspecialchars($headerName, ENT_QUOTES, "UTF-8") ?></strong>
            <span>Mahasiswa</span>
        </div>
    </div>
</header>

<style>
.user-header .profile-avatar.has-photo {
    padding: 0;
    overflow: hidden;
}
.user-header .header-profile-photo {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: inherit;
}
</style>
