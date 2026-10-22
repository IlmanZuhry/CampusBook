<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin - CampusBook</title>
</head>
<body>

    <h1>Dashboard Admin</h1>

    <p>
        Selamat datang,
        <?= htmlspecialchars($_SESSION["name"]) ?>!
    </p>

    <p>Kamu berhasil login sebagai admin.</p>
    <p>
    <a href="users/index.php">Kelola Mahasiswa</a>
</p>

    <a href="../logout.php">Logout</a>

</body>
</html>
