<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "user"
) {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Mahasiswa - CampusBook</title>
</head>
<body>

    <h1>Dashboard Mahasiswa</h1>

    <p>
        Selamat datang,
        <?= htmlspecialchars($_SESSION["name"]) ?>!
    </p>

    <p>Kamu berhasil login sebagai mahasiswa.</p>

    <a href="../logout.php">Logout</a>

</body>
</html>
