
<?php
session_start();

require_once __DIR__ . "/config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $identifier = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($identifier === "" || $password === "") {
        $error = "NIM/email dan password wajib diisi.";
    } else {
        $sql = "SELECT id, nim, name, email, password, role, status
                FROM users
                WHERE email = ? OR nim = ?
                LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            $error = "Terjadi kesalahan saat memproses login.";
        } else {
            mysqli_stmt_bind_param(
                $stmt,
                "ss",
                $identifier,
                $identifier
            );

            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);

            if ($user && password_verify($password, $user["password"])) {
                if ($user["status"] !== "active") {
                    $error = "Akun tidak aktif. Hubungi administrator.";
                } else {
                    session_regenerate_id(true);

                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["name"] = $user["name"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"] = $user["role"];

                    mysqli_stmt_close($stmt);

                    if ($user["role"] === "admin") {
                        header("Location: admin/dashboard.php");
                        exit;
                    }

                    header("Location: user/dashboard.php");
                    exit;
                }
            } else {
                $error = "NIM/email atau password salah.";
            }

            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Masuk - CampusBook</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>
    <main class="login-page">

        <section class="login-showcase">
            <a href="index.php" class="login-brand">
                <img
                    src="assets/images/logoc.png"
                    alt="CampusBook"
                >
                <span>Campus<span>Book</span></span>
            </a>

            <div class="showcase-content">
                <span class="showcase-label">
                    CAMPUS FACILITY BOOKING
                </span>

                <h1>
                    Satu Platform,<br>
                    <span>Semua Fasilitas.</span>
                </h1>

                <p>
                    Pesan aula, laboratorium, dan fasilitas kampus
                    dengan proses yang lebih mudah, teratur, dan
                    transparan melalui CampusBook.
                </p>

                <div class="showcase-features">
                    <div class="showcase-feature">
                        <span class="feature-symbol">01</span>
                        <div>
                            <strong>Jadwal Teratur</strong>
                            <small>Periksa jadwal fasilitas</small>
                        </div>
                    </div>

                    <div class="showcase-feature">
                        <span class="feature-symbol">02</span>
                        <div>
                            <strong>Pemesanan Praktis</strong>
                            <small>Ajukan booking secara online</small>
                        </div>
                    </div>

                    <div class="showcase-feature">
                        <span class="feature-symbol">03</span>
                        <div>
                            <strong>Status Terpantau</strong>
                            <small>Pantau status permohonan</small>
                        </div>
                    </div>
                </div>
            </div>

            <p class="showcase-footer">
                © <?= date("Y") ?> CampusBook
            </p>
        </section>

        <section class="login-panel">
            <div class="login-card">

                <a href="index.php" class="login-logo">
                    <img
                        src="assets/images/logoc.png"
                        alt="Logo CampusBook"
                    >
                </a>

                <h2>Selamat Datang!</h2>

                <p class="login-subtitle">
                    Masuk untuk melanjutkan ke CampusBook
                </p>

                <?php if (!empty($error)): ?>
                    <div class="login-alert" role="alert">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">

                    <div class="form-group">
                        <label for="email">NIM atau Email</label>

                        <input
                            type="text"
                            id="email"
                            name="email"
                            placeholder="Masukkan NIM atau email"
                            value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                            autocomplete="username"
                            required
                        >
                        <div class="password-wrapper">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Masukkan password"
                                autocomplete="current-password"
                                required
                            >

                            <button
                                type="button"
                                class="toggle-password"
                                id="togglePassword"
                                aria-label="Tampilkan password"
                                aria-pressed="false"
                            >
                                <svg
                                    id="eyeIcon"
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="20"
                                    height="20"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>


                    <button type="submit" class="login-button">
                        Masuk ke Akun <span>→</span>
                    </button>

                </form>

                <div class="login-divider">
                    <span>INFORMASI AKUN</span>
                </div>

                <div class="account-notice">
                    <span class="notice-icon">i</span>
                    <p>
                        Akun mahasiswa dibuat oleh administrator.
                        Jika belum memiliki akun atau lupa password,
                        silakan hubungi admin kampus.
                    </p>
                </div>

                <a href="index.php" class="back-home">
                    ← Kembali ke Beranda
                </a>

            </div>
        </section>

    </main>
    
<script>
const passwordInput = document.getElementById("password");
const toggleButton = document.getElementById("togglePassword");
const eyeIcon = document.getElementById("eyeIcon");

toggleButton.addEventListener("click", function () {
    const isHidden = passwordInput.type === "password";

    passwordInput.type = isHidden ? "text" : "password";
    toggleButton.setAttribute("aria-pressed", String(isHidden));
    toggleButton.setAttribute(
        "aria-label",
        isHidden ? "Sembunyikan password" : "Tampilkan password"
    );

    eyeIcon.innerHTML = isHidden
        ? '<path d="M3 3l18 18"/><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15.7 15.7 0 0 1-3 3.8"/><path d="M6.6 6.6C3.6 8.5 2 12 2 12s3.6 7 10 7c1 0 1.9-.2 2.7-.5"/>'
        : '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>';
});
</script>

</body>
</html>
