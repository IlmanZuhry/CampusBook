<?php
session_start();

require_once __DIR__ . "/../../config/database.php";

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../../login.php");
    exit;
}

$success = "";
$error = "";

// Proses tambah mahasiswa
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nim = trim($_POST["nim"] ?? "");
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (
        $nim === "" ||
        $name === "" ||
        $email === "" ||
        $password === ""
    ) {
        $error = "Semua kolom wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } elseif (strlen($password) < 8) {
        $error = "Password minimal 8 karakter.";
    } else {
        $check = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE nim = ? OR email = ?"
        );

        mysqli_stmt_bind_param($check, "ss", $nim, $email);
        mysqli_stmt_execute($check);

        $result = mysqli_stmt_get_result($check);

        if (mysqli_num_rows($result) > 0) {
            $error = "NIM atau email sudah terdaftar.";
        } else {
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO users
                    (nim, name, email, password, role, status)
                 VALUES (?, ?, ?, ?, 'user', 'active')"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssss",
                $nim,
                $name,
                $email,
                $hashedPassword
            );

            if (mysqli_stmt_execute($stmt)) {
                header("Location: index.php?added=1");
                exit;
            } else {
                $error = "Gagal menambahkan mahasiswa.";
            }

            mysqli_stmt_close($stmt);
        }

        mysqli_stmt_close($check);
    }
}

if (isset($_GET["added"])) {
    $success = "Akun mahasiswa berhasil ditambahkan.";
}

// Ambil daftar mahasiswa
$result = mysqli_query(
    $conn,
    "SELECT id, nim, name, email, status, created_at
     FROM users
     WHERE role = 'user'
     ORDER BY id DESC"
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Mahasiswa - CampusBook</title>
</head>
<body>

    <h1>Kelola Mahasiswa</h1>

    <p>
        <a href="../dashboard.php">Kembali ke Dashboard</a>
    </p>

    <?php if ($success !== ""): ?>
        <p style="color: green;">
            <?= htmlspecialchars($success) ?>
        </p>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <p style="color: red;">
            <?= htmlspecialchars($error) ?>
        </p>
    <?php endif; ?>

    <h2>Tambah Akun Mahasiswa</h2>

    <form method="POST">
        <div>
            <label for="nim">NIM</label><br>
            <input type="text" id="nim" name="nim"
                   maxlength="20" required>
        </div>

        <br>

        <div>
            <label for="name">Nama Lengkap</label><br>
            <input type="text" id="name" name="name"
                   maxlength="100" required>
        </div>

        <br>

        <div>
            <label for="email">Email</label><br>
            <input type="email" id="email" name="email"
                   maxlength="150" required>
        </div>

        <br>

        <div>
            <label for="password">Password Awal</label><br>
            <input type="password" id="password"
                   name="password" minlength="8" required>
        </div>

        <br>

        <button type="submit">Tambah Mahasiswa</button>
    </form>

    <hr>

    <h2>Daftar Mahasiswa</h2>

    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>NIM</th>
                <th>Nama</th>
                <th>Email</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row["nim"] ?? "") ?></td>
                        <td><?= htmlspecialchars($row["name"]) ?></td>
                        <td><?= htmlspecialchars($row["email"]) ?></td>
                        <td><?= htmlspecialchars($row["status"]) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">Belum ada mahasiswa terdaftar.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
