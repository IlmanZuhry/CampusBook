
<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

function profileEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$adminId = (int) $_SESSION["user_id"];
$success = "";
$error = "";

if (empty($_SESSION["profile_csrf"])) {
    $_SESSION["profile_csrf"] = bin2hex(random_bytes(32));
}

/* Ambil data admin yang sedang login. */
$stmt = $conn->prepare(
    "SELECT id, name, email, password, status, created_at, profile_photo
     FROM admins
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin || $admin["status"] !== "active") {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["profile_csrf"], $token)
    ) {
        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";
    } else {
        $action = $_POST["action"] ?? "";

        if ($action === "upload_photo") {
            $file = $_FILES["profile_photo"] ?? null;

            if (!$file || !isset($file["error"]) || $file["error"] !== UPLOAD_ERR_OK) {
                $error = "Pilih foto yang valid untuk diunggah.";
            } elseif (!isset($file["size"]) || $file["size"] > 2 * 1024 * 1024) {
                $error = "Ukuran foto maksimal 2 MB.";
            } else {
                $imageInfo = @getimagesize($file["tmp_name"]);
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file["tmp_name"]);
                $allowedTypes = [
                    "image/jpeg" => "jpg",
                    "image/png"  => "png",
                    "image/webp" => "webp",
                ];

                if (!$imageInfo || !isset($allowedTypes[$mime])) {
                    $error = "Foto harus berformat JPG, PNG, atau WebP.";
                } else {
                    $uploadDir = __DIR__ . "/../assets/uploads/profile/";

                    if (!is_dir($uploadDir) &&
                        !mkdir($uploadDir, 0755, true) &&
                        !is_dir($uploadDir)) {
                        $error = "Folder upload tidak dapat dibuat.";
                    } else {
                        $filename = bin2hex(random_bytes(16)) . "." . $allowedTypes[$mime];
                        $targetPath = $uploadDir . $filename;

                        if (!move_uploaded_file($file["tmp_name"], $targetPath)) {
                            $error = "Foto gagal diunggah. Periksa izin folder.";
                        } else {
                            $oldPhoto = $admin["profile_photo"] ?? null;
                            $update = $conn->prepare(
                                "UPDATE admins SET profile_photo = ? WHERE id = ?"
                            );
                            $update->bind_param("si", $filename, $adminId);

                            if ($update->execute()) {
                                if ($oldPhoto && basename($oldPhoto) === $oldPhoto) {
                                    $oldPath = $uploadDir . $oldPhoto;
                                    if (is_file($oldPath)) {
                                        @unlink($oldPath);
                                    }
                                }
                                $success = "Foto profil berhasil diperbarui.";
                            } else {
                                @unlink($targetPath);
                                $error = "Foto profil gagal disimpan ke database.";
                            }
                            $update->close();
                        }
                    }
                }
            }
        } elseif ($action === "update_profile") {
            $name = trim($_POST["name"] ?? "");
            $email = trim($_POST["email"] ?? "");

            if ($name === "" || mb_strlen($name) > 100) {
                $error = "Nama wajib diisi dan maksimal 100 karakter.";
            } elseif (
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                mb_strlen($email) > 150
            ) {
                $error = "Format email tidak valid atau terlalu panjang.";
            } else {
                $check = $conn->prepare(
                    "SELECT id FROM admins WHERE email = ? AND id <> ? LIMIT 1"
                );
                $check->bind_param("si", $email, $adminId);
                $check->execute();
                $emailExists = $check->get_result()->num_rows > 0;
                $check->close();

                if ($emailExists) {
                    $error = "Email tersebut sudah digunakan akun admin lain.";
                } else {
                    $update = $conn->prepare(
                        "UPDATE admins SET name = ?, email = ? WHERE id = ?"
                    );
                    $update->bind_param("ssi", $name, $email, $adminId);

                    if ($update->execute()) {
                        $_SESSION["name"] = $name;

                        $success = "Profil berhasil diperbarui.";
                    } else {
                        $error = "Profil gagal diperbarui.";
                    }

                    $update->close();
                }
            }
        } elseif ($action === "change_password") {
            $currentPassword = $_POST["current_password"] ?? "";
            $newPassword = $_POST["new_password"] ?? "";
            $confirmPassword = $_POST["confirm_password"] ?? "";

            if (
                $currentPassword === "" ||
                $newPassword === "" ||
                $confirmPassword === ""
            ) {
                $error = "Semua kolom password wajib diisi.";
            } elseif (!password_verify($currentPassword, $admin["password"])) {
                $error = "Password saat ini tidak sesuai.";
            } elseif (strlen($newPassword) < 8) {
                $error = "Password baru minimal 8 karakter.";
            } elseif ($newPassword !== $confirmPassword) {
                $error = "Konfirmasi password baru tidak cocok.";
            } elseif ($currentPassword === $newPassword) {
                $error = "Password baru harus berbeda dari password lama.";
            } else {
                $hashedPassword = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

                $update = $conn->prepare(
                    "UPDATE admins SET password = ? WHERE id = ?"
                );
                $update->bind_param("si", $hashedPassword, $adminId);

                if ($update->execute()) {
                    $success = "Password berhasil diubah.";
                } else {
                    $error = "Password gagal diubah.";
                }

                $update->close();
            }
        } else {
            $error = "Aksi tidak dikenali.";
        }

        /* Ambil ulang data terbaru setelah pemrosesan. */
        $stmt = $conn->prepare(
            "SELECT id, name, email, password, status, created_at, profile_photo
             FROM admins WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$pageTitle = "Profil Saya";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Admin | CampusBook</title>

    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/admin_modules.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>

        
    </style>
</head>

<body>
<div class="admin-layout">
    <?php require_once __DIR__ . "/../includes/admin_sidebar.php"; ?>

    <main class="admin-main">
        <header class="admin-header">
            <div class="header-heading">
                <span class="header-eyebrow">PENGATURAN AKUN</span>
                <h1>Profil Saya</h1>
                <p>Kelola informasi akun dan keamanan administrator.</p>
            </div>

            <div class="admin-profile">
                <div class="profile-avatar profile-avatar-photo">
                    <?php if (!empty($admin["profile_photo"])): ?>
                        <img src="../assets/uploads/profile/<?= rawurlencode(basename($admin["profile_photo"])) ?>"
                             alt="Foto profil admin">
                    <?php else: ?>
                        <?= profileEscape(mb_strtoupper(mb_substr($admin["name"], 0, 1))) ?>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <strong><?= profileEscape($admin["name"]) ?></strong>
                    <span>Administrator</span>
                </div>
            </div>
        </header>

        <section class="admin-profile-page">

            <?php if ($success !== ""): ?>
                <div class="profile-alert success" role="status">
                    <i class="fa-solid fa-circle-check"></i>
                    <?= profileEscape($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <div class="profile-alert error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= profileEscape($error) ?>
                </div>
            <?php endif; ?>

            <section class="profile-hero">
                <form method="POST" enctype="multipart/form-data" class="profile-photo-form">
                    <input type="hidden" name="csrf_token"
                           value="<?= profileEscape($_SESSION["profile_csrf"]) ?>">
                    <input type="hidden" name="action" value="upload_photo">

                    <label for="profile_photo" class="profile-hero-avatar profile-photo-trigger"
                           title="Klik untuk mengganti foto profil">
                        <?php if (!empty($admin["profile_photo"])): ?>
                            <img src="../assets/uploads/profile/<?= rawurlencode(basename($admin["profile_photo"])) ?>"
                                 alt="Foto profil admin" class="profile-photo-image">
                        <?php else: ?>
                            <span class="profile-avatar-initial">
                                <?= profileEscape(mb_strtoupper(mb_substr($admin["name"], 0, 1))) ?>
                            </span>
                        <?php endif; ?>
                        <span class="profile-photo-overlay" aria-hidden="true">
                            <i class="fa-solid fa-camera"></i>
                            <small>Ganti foto</small>
                        </span>
                    </label>

                    <input type="file" id="profile_photo" name="profile_photo"
                           accept="image/jpeg,image/png,image/webp" required hidden>
                </form>

                <div>
                    <h2><?= profileEscape($admin["name"]) ?>
                    <p><?= profileEscape($admin["email"]) ?></p>
                    <span class="profile-active">
                        <i class="fa-solid fa-circle-check"></i>
                        Akun Aktif
                    </span>
                </div>
            </section>

            <div class="profile-grid">
                <section class="profile-card">
                    <div class="profile-card-heading">
                        <div class="profile-card-icon">
                            <i class="fa-solid fa-user-pen"></i>
                        </div>
                        <div>
                            <h3>Informasi Profil</h3>
                            <p>Perbarui nama dan email akun admin.</p>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="csrf_token"
                               value="<?= profileEscape($_SESSION["profile_csrf"]) ?>">
                        <input type="hidden" name="action"
                               value="update_profile">

                        <div class="profile-field">
                            <label for="name">Nama Admin</label>
                            <input type="text" id="name" name="name"
                                   maxlength="100" required
                                   value="<?= profileEscape($admin["name"]) ?>">
                        </div>

                        <div class="profile-field">
                            <label for="email">Alamat Email</label>
                            <input type="email" id="email" name="email"
                                   maxlength="150" required
                                   value="<?= profileEscape($admin["email"]) ?>">
                            <small>Email harus unik dan memiliki format yang valid.</small>
                        </div>

                        <button type="submit" class="profile-submit">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Simpan Profil
                        </button>
                    </form>

                    <div class="profile-account-info">
                        <div class="profile-info-item">
                            <span>ID Admin</span>
                            <strong>#<?= (int) $admin["id"] ?></strong>
                        </div>

                        <div class="profile-info-item">
                            <span>Terdaftar Sejak</span>
                            <strong>
                                <?= $admin["created_at"]
                                    ? profileEscape(date(
                                        "d M Y",
                                        strtotime($admin["created_at"])
                                    ))
                                    : "-" ?>
                            </strong>
                        </div>
                    </div>
                </section>

                <section class="profile-card">
                    <div class="profile-card-heading">
                        <div class="profile-card-icon">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <div>
                            <h3>Keamanan Akun</h3>
                            <p>Ganti password administrator.</p>
                        </div>
                    </div>

                    <form method="POST" autocomplete="off">
                        <input type="hidden" name="csrf_token"
                               value="<?= profileEscape($_SESSION["profile_csrf"]) ?>">
                        <input type="hidden" name="action"
                               value="change_password">

                        <div class="profile-field">
                            <label for="current_password">Password Saat Ini</label>
                            <input type="password" id="current_password"
                                   name="current_password"
                                   autocomplete="current-password" required>
                        </div>

                        <div class="profile-field">
                            <label for="new_password">Password Baru</label>
                            <input type="password" id="new_password"
                                   name="new_password"
                                   minlength="8"
                                   autocomplete="new-password" required>
                            <small>Gunakan minimal 8 karakter.</small>
                        </div>

                        <div class="profile-field">
                            <label for="confirm_password">Konfirmasi Password Baru</label>
                            <input type="password" id="confirm_password"
                                   name="confirm_password"
                                   minlength="8"
                                   autocomplete="new-password" required>
                        </div>

                        <button type="submit" class="profile-submit">
                            <i class="fa-solid fa-key"></i>
                            Ganti Password
                        </button>
                    </form>
                </section>
            </div>
        </section>
    </main>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const photoInput = document.getElementById("profile_photo");
    const photoForm = document.querySelector(".profile-photo-form");

    if (photoInput && photoForm) {
        photoInput.addEventListener("change", function () {
            if (photoInput.files && photoInput.files.length > 0) {
                photoForm.submit();
            }
        });
    }
});
</script>
</body>
</html>
