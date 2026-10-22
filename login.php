<?php
session_start();

require_once __DIR__ . "/config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Email dan password wajib diisi.";
    } else {
        $sql = "SELECT id, name, email, password, role, status
                FROM users
                WHERE email = ?
                LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if (
            $user &&
            password_verify($password, $user["password"])
        ) {
            if ($user["status"] !== "active") {
                $error = "Akun kamu tidak aktif. Hubungi admin.";
            } else {
                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["name"] = $user["name"];
                $_SESSION["email"] = $user["email"];
                $_SESSION["role"] = $user["role"];

                if ($user["role"] === "admin") {
                    header("Location: admin/dashboard.php");
                    exit;
                }

                header("Location: user/dashboard.php");
                exit;
            }
        } else {
            $error = "Email atau password salah.";
        }

        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CampusBook</title>
</head>
<body>

    <h1>CampusBook</h1>
    <h2>Login</h2>

    <?php if ($error !== ""): ?>
        <p style="color: red;">
            <?= htmlspecialchars($error) ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="">
        <div>
            <label for="email">Email</label><br>
            <input
                type="email"
                id="email"
                name="email"
                required
                autocomplete="username"
            >
        </div>

        <br>

        <div>
            <label for="password">Password</label><br>
            <input
                type="password"
                id="password"
                name="password"
                required
                autocomplete="current-password"
            >
        </div>

        <br>

        <button type="submit">Login</button>
    </form>

</body>
</html>
