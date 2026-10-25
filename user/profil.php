<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"] ?? "", ["mahasiswa", "student"], true)) {
    header("Location: ../login.php");
    exit;
}

$studentId = (int) $_SESSION["user_id"];
$error = "";
$success = "";

function profileEscape($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function getStudent(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare("SELECT nim, name, email, fakultas, prodi, profile_photo, password FROM students WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $student ?: null;
}

$student = getStudent($conn, $studentId);
if (!$student) exit("Data mahasiswa tidak ditemukan.");

$uploadDir = __DIR__ . "/../assets/uploads/profile/";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Ganti foto profil
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["upload_photo"])) {
    if (!isset($_FILES["profile_photo"]) || $_FILES["profile_photo"]["error"] !== UPLOAD_ERR_OK) {
        $error = "Pilih foto terlebih dahulu.";
    } elseif ($_FILES["profile_photo"]["size"] > 2 * 1024 * 1024) {
        $error = "Ukuran foto maksimal 2 MB.";
    } else {
        $tmp = $_FILES["profile_photo"]["tmp_name"];
        $info = @getimagesize($tmp);
        $types = [IMAGETYPE_JPEG => "jpg", IMAGETYPE_PNG => "png", IMAGETYPE_WEBP => "webp"];
        if (!$info || !isset($types[$info[2]])) {
            $error = "Foto harus berformat JPG, PNG, atau WebP.";
        } else {
            $filename = "student_" . $studentId . "_" . bin2hex(random_bytes(8)) . "." . $types[$info[2]];
            $destination = $uploadDir . $filename;
            if (move_uploaded_file($tmp, $destination)) {
                $stmt = $conn->prepare("UPDATE students SET profile_photo = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $filename, $studentId);
                    if ($stmt->execute()) {
                        $old = basename((string)($student["profile_photo"] ?? ""));
                        if ($old !== "" && $old !== $filename && is_file($uploadDir . $old)) unlink($uploadDir . $old);
                        $success = "Foto profil berhasil diperbarui.";
                    } else {
                        unlink($destination);
                        $error = "Gagal menyimpan foto profil ke database.";
                    }
                    $stmt->close();
                } else {
                    unlink($destination);
                    $error = "Gagal menyiapkan penyimpanan foto.";
                }
            } else $error = "Gagal mengunggah foto. Periksa folder penyimpanan.";
        }
    }
    $student = getStudent($conn, $studentId) ?? $student;
}

// Simpan email dan, jika diisi, password baru. Nama sengaja tidak dapat diedit.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_profile"])) {
    $email = trim($_POST["email"] ?? "");
    $oldPassword = $_POST["old_password"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Masukkan alamat email yang valid.";
    } else {
        $check = $conn->prepare("SELECT id FROM students WHERE email = ? AND id <> ? LIMIT 1");
        if (!$check) {
            $error = "Gagal memeriksa email.";
        } else {
            $check->bind_param("si", $email, $studentId);
            $check->execute();
            $duplicate = $check->get_result()->num_rows > 0;
            $check->close();
            if ($duplicate) {
                $error = "Email sudah digunakan akun lain.";
            } elseif ($newPassword !== "" && ($oldPassword === "" || !password_verify($oldPassword, $student["password"]))) {
                $error = "Password lama tidak sesuai.";
            } elseif ($newPassword !== "" && strlen($newPassword) < 8) {
                $error = "Password baru minimal 8 karakter.";
            } elseif ($newPassword !== "" && $newPassword !== $confirmPassword) {
                $error = "Konfirmasi password baru tidak cocok.";
            } else {
                if ($newPassword !== "") {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE students SET email = ?, password = ? WHERE id = ?");
                    if ($stmt) $stmt->bind_param("ssi", $email, $hash, $studentId);
                } else {
                    $stmt = $conn->prepare("UPDATE students SET email = ? WHERE id = ?");
                    if ($stmt) $stmt->bind_param("si", $email, $studentId);
                }
                if (empty($stmt)) {
                    $error = "Gagal menyiapkan pembaruan profil.";
                } else {
                    if ($stmt->execute()) $success = "Profil berhasil diperbarui.";
                    else $error = "Gagal memperbarui profil.";
                    $stmt->close();
                }
            }
        }
    }
    $student = getStudent($conn, $studentId) ?? $student;
}

$initial = strtoupper(substr($student["name"] ?: "M", 0, 1));
$photoUrl = !empty($student["profile_photo"]) ? "../assets/uploads/profile/" . rawurlencode(basename($student["profile_photo"])) : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil Saya - CampusBook</title>
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/user.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
.profile-page{max-width:1000px;margin:0 auto}.profile-hero,.profile-card{background:#fff;border:1px solid #e0e8f5;border-radius:20px;padding:28px;margin-bottom:22px}.profile-hero{display:flex;align-items:center;gap:22px}.profile-photo{width:100px;height:100px;flex:0 0 100px;border-radius:24px;object-fit:cover;background:#eaf1ff}.profile-photo-picker{position:relative;width:100px;height:100px;flex:0 0 100px;border:0;padding:0;border-radius:24px;overflow:hidden;cursor:pointer;background:#eaf1ff;display:block}.profile-photo-picker .profile-photo{display:block;width:100%;height:100%;flex:none;border-radius:0}.profile-photo-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(9,24,49,.48);color:#fff;font-size:27px;opacity:0;transition:opacity .2s ease}.profile-photo-picker:hover .profile-photo-overlay,.profile-photo-picker:focus-visible .profile-photo-overlay{opacity:1}.profile-photo-picker:focus-visible{outline:3px solid #1769ff;outline-offset:3px}.profile-photo-hint{font-size:13px;color:#64748b;margin-top:7px}.profile-hero h2{margin:0 0 8px;color:#10264f}.profile-hero p{margin:5px 0;color:#64748b}.profile-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}.profile-field{display:flex;flex-direction:column;gap:8px}.profile-field label{font-weight:600;color:#334155}.profile-field input{width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid #dbe3ef;border-radius:10px;font:inherit}.profile-field input[readonly]{background:#f1f5f9;color:#64748b}.profile-actions{display:flex;justify-content:flex-end;margin-top:24px}.profile-button{border:0;border-radius:10px;padding:13px 22px;background:#1769ff;color:white;font-weight:600;cursor:pointer}.profile-alert{padding:14px 16px;border-radius:10px;margin-bottom:18px}.profile-alert.success{background:#e4f8ef;color:#047857}.profile-alert.error{background:#fff0f2;color:#be123c}.profile-card h3{margin:0 0 8px;color:#10264f}.profile-card>p{margin:0 0 24px;color:#64748b}.profile-photo-form{display:flex;align-items:flex-end;gap:16px;flex-wrap:wrap}.profile-photo-form .profile-field{flex:1;min-width:220px}@media(max-width:650px){.profile-grid{grid-template-columns:1fr}.profile-hero{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<div class="admin-layout">
<?php require_once __DIR__ . "/../includes/user_sidebar.php"; ?>
<main class="admin-main">
<?php require_once __DIR__ . "/../includes/user_header.php"; ?>
<div class="profile-page">
<section class="profile-hero">
<form id="profilePhotoForm" method="POST" enctype="multipart/form-data" style="margin:0;">
<input type="hidden" name="upload_photo" value="1">
<input type="file" id="profile_photo" name="profile_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required hidden>
<button class="profile-photo-picker" type="button" id="profilePhotoPicker" aria-label="Klik untuk mengganti foto profil" title="Ganti foto profil">
<?php if ($photoUrl): ?><img class="profile-photo" id="profilePhotoPreview" src="<?= profileEscape($photoUrl) ?>" alt="Foto profil mahasiswa"><?php else: ?><div class="profile-photo" id="profilePhotoPreview" style="display:grid;place-items:center;font-size:38px;font-weight:700;color:#1769ff;"><?= profileEscape($initial) ?></div><?php endif; ?>
<span class="profile-photo-overlay"><i class="fa-solid fa-camera"></i></span>
</button>
<div class="profile-photo-hint">Klik foto untuk mengganti</div>
</form>
<div><h2><?= profileEscape($student["name"]) ?></h2><p><?= profileEscape($student["nim"]) ?></p><p><?= profileEscape($student["prodi"]) ?></p></div>
</section>
<?php if ($success !== ""): ?><div class="profile-alert success"><?= profileEscape($success) ?></div><?php endif; ?>
<?php if ($error !== ""): ?><div class="profile-alert error"><?= profileEscape($error) ?></div><?php endif; ?>

<form method="POST">
<section class="profile-card"><h3>Informasi Pribadi</h3><p>Nama dan data akademik mengikuti data kampus.</p>
<div class="profile-grid">
<div class="profile-field"><label for="nim">NIM</label><input id="nim" value="<?= profileEscape($student["nim"]) ?>" readonly></div>
<div class="profile-field"><label for="name">Nama Lengkap</label><input id="name" value="<?= profileEscape($student["name"]) ?>" readonly></div>
<div class="profile-field"><label for="email">Email</label><input id="email" name="email" type="email" value="<?= profileEscape($student["email"]) ?>" maxlength="150" required></div>
<div class="profile-field"><label for="fakultas">Fakultas</label><input id="fakultas" value="<?= profileEscape($student["fakultas"]) ?>" readonly></div>
<div class="profile-field"><label for="prodi">Program Studi</label><input id="prodi" value="<?= profileEscape($student["prodi"]) ?>" readonly></div>
</div></section>
<section class="profile-card"><h3>Ganti Password</h3><p>Kosongkan kolom password jika tidak ingin menggantinya.</p>
<div class="profile-grid">
<div class="profile-field"><label for="old_password">Password Lama</label><input type="password" id="old_password" name="old_password" autocomplete="current-password"></div>
<div class="profile-field"><label for="new_password">Password Baru</label><input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password"></div>
<div class="profile-field"><label for="confirm_password">Konfirmasi Password Baru</label><input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password"></div>
</div><div class="profile-actions"><button class="profile-button" type="submit" name="save_profile" value="1">Simpan Perubahan</button></div>
</section></form>
</div></main></div>
<script>
(() => {
    const picker = document.getElementById("profilePhotoPicker");
    const input = document.getElementById("profile_photo");
    const form = document.getElementById("profilePhotoForm");
    if (!picker || !input || !form) return;
    picker.addEventListener("click", () => input.click());
    input.addEventListener("change", () => {
        if (input.files && input.files.length > 0) form.requestSubmit();
    });
})();
</script>
</body></html>
