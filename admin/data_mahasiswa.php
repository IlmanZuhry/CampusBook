
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

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$error = "";
$success = "";
$formData = [
    "id" => "",
    "nim" => "",
    "name" => "",
    "email" => "",
    "fakultas" => "",
    "prodi" => "",
    "status" => "active"
];

$showModal = false;

/* =========================
   PROSES CREATE, UPDATE, DELETE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $error = "Sesi formulir tidak valid. Muat ulang halaman.";
    } else {
        $action = $_POST["action"] ?? "";

        if (in_array($action, ["create", "update"], true)) {
            $formData = [
                "id" => (int) ($_POST["id"] ?? 0),
                "nim" => trim($_POST["nim"] ?? ""),
                "name" => trim($_POST["name"] ?? ""),
                "email" => trim($_POST["email"] ?? ""),
                "fakultas" => trim($_POST["fakultas"] ?? ""),
                "prodi" => trim($_POST["prodi"] ?? ""),
                "status" => $_POST["status"] ?? "active"
            ];

            $password = $_POST["password"] ?? "";
            $showModal = true;

            if (
                $formData["nim"] === "" ||
                $formData["name"] === "" ||
                $formData["email"] === "" ||
                $formData["fakultas"] === "" ||
                $formData["prodi"] === ""
            ) {
                $error = "Semua data mahasiswa wajib diisi.";
            } elseif (!filter_var($formData["email"], FILTER_VALIDATE_EMAIL)) {
                $error = "Format email tidak valid.";
            } elseif (
                !in_array($formData["status"], ["active", "inactive"], true)
            ) {
                $error = "Status akun tidak valid.";
            } elseif (
                $action === "create" && strlen($password) < 8
            ) {
                $error = "Password minimal 8 karakter.";
            } else {
                // Pastikan NIM dan email tidak digunakan akun lain.
                $sqlDuplicate = "
                    SELECT id FROM students
                    WHERE (nim = ? OR email = ?)
                ";

                if ($action === "update") {
                    $sqlDuplicate .= " AND id <> ?";
                }

                $sqlDuplicate .= " LIMIT 1";

                $check = mysqli_prepare($conn, $sqlDuplicate);

                if (!$check) {
                    $error = "Gagal memeriksa data mahasiswa.";
                } else {
                    if ($action === "update") {
                        mysqli_stmt_bind_param(
                            $check,
                            "ssi",
                            $formData["nim"],
                            $formData["email"],
                            $formData["id"]
                        );
                    } else {
                        mysqli_stmt_bind_param(
                            $check,
                            "ss",
                            $formData["nim"],
                            $formData["email"]
                        );
                    }

                    mysqli_stmt_execute($check);
                    $duplicate = mysqli_stmt_get_result($check);
                    $hasDuplicate = mysqli_num_rows($duplicate) > 0;
                    mysqli_stmt_close($check);

                    if ($hasDuplicate) {
                        $error = "NIM atau email sudah digunakan mahasiswa lain.";
                    } else {
                        if ($action === "create") {
                            $hashedPassword = password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );

                            $sql = "
                                INSERT INTO students
                                (nim, name, email, password, fakultas, prodi, status)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ";

                            $stmt = mysqli_prepare($conn, $sql);

                            if ($stmt) {
                                mysqli_stmt_bind_param(
                                    $stmt,
                                    "sssssss",
                                    $formData["nim"],
                                    $formData["name"],
                                    $formData["email"],
                                    $hashedPassword,
                                    $formData["fakultas"],
                                    $formData["prodi"],
                                    $formData["status"]
                                );
                            }
                        } else {
                            $sql = "
                                UPDATE students
                                SET nim = ?, name = ?, email = ?,
                                    fakultas = ?, prodi = ?, status = ?
                                WHERE id = ?
                            ";

                            $stmt = mysqli_prepare($conn, $sql);

                            if ($stmt) {
                                mysqli_stmt_bind_param(
                                    $stmt,
                                    "ssssssi",
                                    $formData["nim"],
                                    $formData["name"],
                                    $formData["email"],
                                    $formData["fakultas"],
                                    $formData["prodi"],
                                    $formData["status"],
                                    $formData["id"]
                                );
                            }

                            // Password hanya diperbarui jika diisi.
                            if ($stmt && $password !== "") {
                                if (strlen($password) < 8) {
                                    $error = "Password minimal 8 karakter.";
                                } else {
                                    $hashedPassword = password_hash(
                                        $password,
                                        PASSWORD_DEFAULT
                                    );

                                    $passwordStmt = mysqli_prepare(
                                        $conn,
                                        "UPDATE students SET password = ? WHERE id = ?"
                                    );

                                    if ($passwordStmt) {
                                        mysqli_stmt_bind_param(
                                            $passwordStmt,
                                            "si",
                                            $hashedPassword,
                                            $formData["id"]
                                        );

                                        if (!mysqli_stmt_execute($passwordStmt)) {
                                            $error = "Gagal memperbarui password.";
                                        }

                                        mysqli_stmt_close($passwordStmt);
                                    } else {
                                        $error = "Gagal menyiapkan pembaruan password.";
                                    }
                                }
                            }
                        }

                        if (isset($stmt) && $stmt) {
                            if ($error === "") {
                                if (mysqli_stmt_execute($stmt)) {
                                    mysqli_stmt_close($stmt);

                                    $_SESSION["flash_success"] =
                                        $action === "create"
                                            ? "Mahasiswa berhasil ditambahkan."
                                            : "Data mahasiswa berhasil diperbarui.";

                                    header("Location: data_mahasiswa.php");
                                    exit;
                                }

                                $dbError = mysqli_stmt_errno($stmt);
                                mysqli_stmt_close($stmt);

                                $error = $dbError === 1062
                                    ? "NIM atau email sudah digunakan."
                                    : "Gagal menyimpan data mahasiswa.";
                            } else {
                                mysqli_stmt_close($stmt);
                            }
                        } elseif ($error === "") {
                            $error = "Gagal menyiapkan penyimpanan data.";
                        }
                    }
                }
            }
        } elseif ($action === "delete") {
            $id = (int) ($_POST["id"] ?? 0);

            if ($id <= 0) {
                $error = "ID mahasiswa tidak valid.";
            } else {
                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM students WHERE id = ?"
                );

                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $id);

                    if (mysqli_stmt_execute($stmt)) {
                        if (mysqli_stmt_affected_rows($stmt) > 0) {
                            $_SESSION["flash_success"] =
                                "Data mahasiswa berhasil dihapus.";
                        } else {
                            $_SESSION["flash_success"] =
                                "Data mahasiswa tidak ditemukan.";
                        }

                        mysqli_stmt_close($stmt);
                        header("Location: data_mahasiswa.php");
                        exit;
                    }

                    mysqli_stmt_close($stmt);
                    $error = "Gagal menghapus mahasiswa.";
                } else {
                    $error = "Gagal menyiapkan penghapusan data.";
                }
            }
        } else {
            $error = "Aksi tidak dikenal.";
        }
    }
}

if (isset($_SESSION["flash_success"])) {
    $success = $_SESSION["flash_success"];
    unset($_SESSION["flash_success"]);
}

/* =========================
   PENCARIAN DAN FILTER
========================= */
$keyword = trim($_GET["search"] ?? "");
$statusFilter = $_GET["status"] ?? "";

$sql = "
    SELECT id, nim, name, email, fakultas, prodi, status
    FROM students
    WHERE 1 = 1
";

$params = [];
$types = "";

if ($keyword !== "") {
    $sql .= " AND (nim LIKE ? OR name LIKE ? OR email LIKE ?)";
    $searchValue = "%" . $keyword . "%";
    $params = [$searchValue, $searchValue, $searchValue];
    $types = "sss";
}

if (in_array($statusFilter, ["active", "inactive"], true)) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql .= " ORDER BY id DESC";

$stmt = mysqli_prepare($conn, $sql);
$students = [];

if ($stmt) {
    if ($params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }

    mysqli_stmt_close($stmt);
} else {
    exit("Gagal mengambil data mahasiswa. Periksa struktur tabel students.");
}

/* Statistik */
$countResult = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'active'), 0) AS active_total,
        COALESCE(SUM(status = 'inactive'), 0) AS inactive_total
     FROM students"
);

$counts = $countResult
    ? mysqli_fetch_assoc($countResult)
    : ["total" => 0, "active_total" => 0, "inactive_total" => 0];

$totalStudents = (int) $counts["total"];
$activeStudents = (int) $counts["active_total"];
$inactiveStudents = (int) $counts["inactive_total"];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Mahasiswa - CampusBook</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/students.css">
</head>
<body>

<div class="admin-layout">
    <?php require_once __DIR__ . "/../includes/admin_sidebar.php"; ?>

    <main class="admin-main">
        <header class="admin-header">
            <div class="header-heading">
                <span class="header-eyebrow">MANAJEMEN PENGGUNA</span>
                <h1>Data Mahasiswa</h1>
                <p>Kelola akun mahasiswa yang dapat menggunakan CampusBook.</p>
            </div>

            <div class="admin-profile">
                <div class="profile-avatar">
                    <?= e(strtoupper(substr($_SESSION["name"] ?? "A", 0, 1))) ?>
                </div>
                <div class="profile-info">
                    <strong><?= e($_SESSION["name"] ?? "Administrator") ?></strong>
                    <span>Administrator</span>
                </div>
            </div>
        </header>

        <?php if ($success !== ""): ?>
            <div class="student-alert success"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="student-alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="student-stats">
            <article class="student-stat">
                <span class="student-stat-icon blue">♙</span>
                <div>
                    <p>Total Mahasiswa</p>
                    <h2><?= $totalStudents ?></h2>
                </div>
            </article>

            <article class="student-stat">
                <span class="student-stat-icon green">✓</span>
                <div>
                    <p>Akun Aktif</p>
                    <h2><?= $activeStudents ?></h2>
                </div>
            </article>

            <article class="student-stat">
                <span class="student-stat-icon orange">◷</span>
                <div>
                    <p>Akun Nonaktif</p>
                    <h2><?= $inactiveStudents ?></h2>
                </div>
            </article>
        </section>

        <section class="student-panel">
            <div class="student-panel-heading">
                <div>
                    <h2>Daftar Mahasiswa</h2>
                    <p>Daftar akun mahasiswa yang terdaftar di sistem.</p>
                </div>

                <button type="button" class="btn-add" id="openAddModal">
                    + Tambah Mahasiswa
                </button>
            </div>

            <form method="GET" class="student-filters">
                <div class="student-search">
                    <span>⌕</span>
                    <input
                        type="search"
                        name="search"
                        placeholder="Cari NIM, nama, atau email..."
                        value="<?= e($keyword) ?>"
                    >
                </div>

                <select name="status" aria-label="Filter status akun">
                    <option value="">Semua Status</option>
                    <option value="active" <?= $statusFilter === "active" ? "selected" : "" ?>>Aktif</option>
                    <option value="inactive" <?= $statusFilter === "inactive" ? "selected" : "" ?>>Nonaktif</option>
                </select>

                <button type="submit" class="student-filter-button">Cari</button>

                <?php if ($keyword !== "" || $statusFilter !== ""): ?>
                    <a href="data_mahasiswa.php" class="student-reset-button">Reset</a>
                <?php endif; ?>
            </form>

            <div class="student-table-wrapper">
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>Mahasiswa</th>
                            <th>NIM</th>
                            <th>Fakultas / Prodi</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($students): ?>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <div class="student-identity">
                                        <div class="student-avatar">
                                            <?= e(strtoupper(substr($student["name"] ?: "M", 0, 1))) ?>
                                        </div>
                                        <strong><?= e($student["name"]) ?></strong>
                                    </div>
                                </td>
                                <td><?= e($student["nim"]) ?></td>
                                <td>
                                    <strong><?= e($student["fakultas"]) ?></strong>
                                    <small class="student-subtext"><?= e($student["prodi"]) ?></small>
                                </td>
                                <td><?= e($student["email"]) ?></td>
                                <td>
                                    <span class="student-status <?= $student["status"] === "active" ? "is-active" : "is-inactive" ?>">
                                        <?= $student["status"] === "active" ? "Aktif" : "Nonaktif" ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="student-actions">
                                        <button
                                            type="button"
                                            class="student-edit-button edit-student"
                                            data-id="<?= (int) $student["id"] ?>"
                                            data-nim="<?= e($student["nim"]) ?>"
                                            data-name="<?= e($student["name"]) ?>"
                                            data-email="<?= e($student["email"]) ?>"
                                            data-fakultas="<?= e($student["fakultas"]) ?>"
                                            data-prodi="<?= e($student["prodi"]) ?>"
                                            data-status="<?= e($student["status"]) ?>"
                                        >Edit</button>

                                        <form method="POST" class="inline-delete"
                                              onsubmit="return confirm('Yakin ingin menghapus mahasiswa ini?')">
                                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $student["id"] ?>">
                                            <button type="submit" class="student-delete-button">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="student-empty">
                                <div class="student-empty-icon">⌕</div>
                                <strong>Mahasiswa tidak ditemukan</strong>
                                <p>Belum ada data mahasiswa atau kata kunci tidak cocok.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="student-table-footer">
                Menampilkan <?= count($students) ?> data mahasiswa.
            </div>
        </section>
    </main>
</div>

<!-- Modal tambah/edit -->
<div class="student-modal" id="studentModal" aria-hidden="true">
    <div class="student-modal-content">
        <div class="modal-header">
            <div>
                <h2 id="modalTitle">Tambah Mahasiswa</h2>
                <p id="modalDescription">Masukkan informasi mahasiswa baru.</p>
            </div>
            <button type="button" class="modal-close" id="closeModal" aria-label="Tutup">&times;</button>
        </div>

        <form method="POST" id="studentForm">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="studentId">

            <div class="form-group">
                <label for="nim">NIM</label>
                <input type="text" id="nim" name="nim" maxlength="20" required>
            </div>

            <div class="form-group">
                <label for="name">Nama Lengkap</label>
                <input type="text" id="name" name="name" maxlength="100" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="150" required>
            </div>

            <div class="form-group">
                <label for="fakultas">Fakultas</label>
                <input type="text" id="fakultas" name="fakultas" maxlength="150" required>
            </div>

            <div class="form-group">
                <label for="prodi">Program Studi</label>
                <input type="text" id="prodi" name="prodi" maxlength="150" required>
            </div>

            <div class="form-group">
                <label for="password">Password <span id="passwordHint"></span></label>
                <input type="password" id="password" name="password"
                       minlength="8" autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="status">Status Akun</label>
                <select id="status" name="status" required>
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="cancelModal">Batal</button>
                <button type="submit" class="btn-save" id="saveButton">Simpan Mahasiswa</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("studentModal");
    const form = document.getElementById("studentForm");
    const password = document.getElementById("password");

    const fields = {
        id: document.getElementById("studentId"),
        nim: document.getElementById("nim"),
        name: document.getElementById("name"),
        email: document.getElementById("email"),
        fakultas: document.getElementById("fakultas"),
        prodi: document.getElementById("prodi"),
        status: document.getElementById("status")
    };

    function openModal() {
        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
        document.body.classList.add("modal-open");
    }

    function closeModal() {
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
        document.body.classList.remove("modal-open");
    }

    function resetForm() {
        form.reset();
        fields.id.value = "";
        fields.status.value = "active";
        password.required = true;
        password.value = "";
        document.getElementById("formAction").value = "create";
        document.getElementById("modalTitle").textContent = "Tambah Mahasiswa";
        document.getElementById("modalDescription").textContent =
            "Masukkan informasi mahasiswa baru.";
        document.getElementById("passwordHint").textContent = "";
        document.getElementById("saveButton").textContent = "Simpan Mahasiswa";
    }

    document.getElementById("openAddModal").addEventListener("click", function () {
        resetForm();
        openModal();
    });

    document.getElementById("closeModal").addEventListener("click", closeModal);
    document.getElementById("cancelModal").addEventListener("click", closeModal);

    document.querySelectorAll(".edit-student").forEach(function (button) {
        button.addEventListener("click", function () {
            resetForm();

            document.getElementById("formAction").value = "update";
            document.getElementById("modalTitle").textContent = "Edit Mahasiswa";
            document.getElementById("modalDescription").textContent =
                "Perbarui informasi mahasiswa.";
            document.getElementById("passwordHint").textContent =
                "(kosongkan jika tidak diubah)";
            document.getElementById("saveButton").textContent = "Simpan Perubahan";

            fields.id.value = button.dataset.id;
            fields.nim.value = button.dataset.nim;
            fields.name.value = button.dataset.name;
            fields.email.value = button.dataset.email;
            fields.fakultas.value = button.dataset.fakultas;
            fields.prodi.value = button.dataset.prodi;
            fields.status.value = button.dataset.status;

            password.required = false;
            openModal();
        });
    });

    modal.addEventListener("click", function (event) {
        if (event.target === modal) closeModal();
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") closeModal();
    });
});
</script>

</body>
</html>
