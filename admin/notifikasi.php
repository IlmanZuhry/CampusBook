<?php
session_start();

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/admin_helpers.php";

require_admin();

const PER_PAGE = 10;
const FILTER_KEYS = ["search", "status", "channel", "page"];
const CHANNELS = ["in_app", "email", "whatsapp"];
const MAX_BROADCAST = 1000;

$error = "";
$showModal = false;
$formData = [
    "recipient"  => "all",
    "student_id" => 0,
    "title"      => "",
    "message"    => "",
    "channel"    => "in_app",
];

/* =========================
   PROSES KIRIM, KIRIM ULANG, HAPUS
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_valid()) {
        $error = "Sesi formulir tidak valid. Muat ulang halaman.";
    } else {
        $action = $_POST["action"] ?? "";

        if ($action === "send") {
            $formData = [
                "recipient"  => $_POST["recipient"] ?? "",
                "student_id" => (int) ($_POST["student_id"] ?? 0),
                "title"      => trim($_POST["title"] ?? ""),
                "message"    => trim($_POST["message"] ?? ""),
                "channel"    => $_POST["channel"] ?? "",
            ];

            $showModal = true;

            if ($formData["title"] === "" || $formData["message"] === "") {
                $error = "Judul dan isi pesan wajib diisi.";
            } elseif (!in_array($formData["channel"], CHANNELS, true)) {
                $error = "Kanal notifikasi tidak valid.";
            } elseif (!in_array($formData["recipient"], ["all", "one"], true)) {
                $error = "Penerima notifikasi tidak valid.";
            } else {
                if ($formData["recipient"] === "one") {
                    $recipients = db_all(
                        $conn,
                        "SELECT id FROM students WHERE id = ? AND status = 'active' LIMIT 1",
                        "i",
                        [$formData["student_id"]]
                    );
                } else {
                    $recipients = db_all(
                        $conn,
                        "SELECT id FROM students WHERE status = 'active' ORDER BY id ASC LIMIT " . MAX_BROADCAST
                    );
                }

                if (!$recipients) {
                    $error = $formData["recipient"] === "one"
                        ? "Pilih mahasiswa aktif sebagai penerima."
                        : "Belum ada mahasiswa aktif yang dapat menerima notifikasi.";
                } else {
                    $summary = ["sent" => 0, "pending" => 0, "failed" => 0];

                    foreach ($recipients as $recipient) {
                        $status = send_notification(
                            $conn,
                            (int) $recipient["id"],
                            null,
                            $formData["title"],
                            $formData["message"],
                            $formData["channel"]
                        );
                        $summary[$status]++;
                    }

                    $parts = [];

                    if ($summary["sent"] > 0) {
                        $parts[] = $summary["sent"] . " terkirim";
                    }

                    if ($summary["pending"] > 0) {
                        $parts[] = $summary["pending"] . " menunggu antrean";
                    }

                    if ($summary["failed"] > 0) {
                        $parts[] = $summary["failed"] . " gagal";
                    }

                    flash_set(
                        "Notifikasi diproses untuk " . count($recipients)
                            . " mahasiswa: " . implode(", ", $parts) . ".",
                        $summary["failed"] > 0 || $summary["pending"] > 0 ? "warning" : "success"
                    );
                    redirect_to("notifikasi.php");
                }
            }
        } elseif ($action === "resend") {
            $id = (int) ($_POST["id"] ?? 0);

            $notification = db_one(
                $conn,
                "SELECT * FROM notifications WHERE id = ? LIMIT 1",
                "i",
                [$id]
            );

            if (!$notification) {
                $error = "Notifikasi tidak ditemukan.";
            } elseif ($notification["status"] === "sent") {
                $error = "Notifikasi ini sudah terkirim.";
            } else {
                [$status, $sentAt] = deliver_channel(
                    $conn,
                    (int) $notification["user_id"],
                    $notification["title"],
                    $notification["message"],
                    $notification["channel"]
                );

                db_run(
                    $conn,
                    "UPDATE notifications SET status = ?, sent_at = ? WHERE id = ?",
                    "ssi",
                    [$status, $sentAt, $id]
                );

                if ($status === "sent") {
                    flash_set("Notifikasi berhasil dikirim ulang.");
                } elseif ($status === "pending") {
                    flash_set("Notifikasi WhatsApp masih menunggu antrean karena gateway belum terhubung.", "warning");
                } else {
                    flash_set("Pengiriman ulang gagal. Periksa pengaturan email server.", "error");
                }

                redirect_to(back_url_for("notifikasi.php", FILTER_KEYS));
            }
        } elseif ($action === "delete") {
            $id = (int) ($_POST["id"] ?? 0);

            $affected = db_run(
                $conn,
                "DELETE FROM notifications WHERE id = ?",
                "i",
                [$id]
            );

            if ($affected === false) {
                $error = "Gagal menghapus notifikasi.";
            } else {
                flash_set(
                    $affected > 0 ? "Notifikasi berhasil dihapus." : "Notifikasi tidak ditemukan.",
                    $affected > 0 ? "success" : "warning"
                );
                redirect_to(back_url_for("notifikasi.php", FILTER_KEYS));
            }
        } else {
            $error = "Aksi tidak dikenal.";
        }
    }
}

$flash = flash_get();

/* =========================
   PENCARIAN DAN FILTER
========================= */
$keyword = trim($_GET["search"] ?? "");
$statusFilter = $_GET["status"] ?? "";
$channelFilter = $_GET["channel"] ?? "";
$page = max(1, (int) ($_GET["page"] ?? 1));

$where = " WHERE 1 = 1";
$params = [];
$types = "";

if ($keyword !== "") {
    $where .= " AND (n.title LIKE ? OR n.message LIKE ? OR s.name LIKE ? OR s.nim LIKE ?)";
    $like = "%" . $keyword . "%";
    array_push($params, $like, $like, $like, $like);
    $types .= "ssss";
}

if (in_array($statusFilter, ["pending", "sent", "failed"], true)) {
    $where .= " AND n.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
} else {
    $statusFilter = "";
}

if (in_array($channelFilter, CHANNELS, true)) {
    $where .= " AND n.channel = ?";
    $params[] = $channelFilter;
    $types .= "s";
} else {
    $channelFilter = "";
}

$from = "
    FROM notifications n
    JOIN students s ON s.id = n.user_id
    LEFT JOIN bookings b ON b.id = n.booking_id
";

$totalRows = (int) (db_one(
    $conn,
    "SELECT COUNT(*) AS total " . $from . $where,
    $types,
    $params
)["total"] ?? 0);

$pages = max(1, (int) ceil($totalRows / PER_PAGE));
$page = min($page, $pages);
$offset = ($page - 1) * PER_PAGE;

$notifications = db_all(
    $conn,
    "SELECT n.*, s.name AS student_name, s.nim, b.booking_code
     " . $from . $where . "
     ORDER BY n.created_at DESC, n.id DESC
     LIMIT ? OFFSET ?",
    $types . "ii",
    array_merge($params, [PER_PAGE, $offset])
);

$students = db_all(
    $conn,
    "SELECT id, nim, name FROM students WHERE status = 'active' ORDER BY name ASC"
);

/* Statistik */
$stats = db_one(
    $conn,
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'sent'), 0) AS sent_total,
            COALESCE(SUM(status = 'pending'), 0) AS pending_total,
            COALESCE(SUM(status = 'failed'), 0) AS failed_total
     FROM notifications"
) ?? ["total" => 0, "sent_total" => 0, "pending_total" => 0, "failed_total" => 0];

$activeFilters = array_filter([
    "search"  => $keyword,
    "status"  => $statusFilter,
    "channel" => $channelFilter,
], fn($v) => $v !== "");

$backValue = http_build_query($page > 1 ? $activeFilters + ["page" => $page] : $activeFilters);

function notification_status_meta(string $status): array
{
    $map = [
        "sent"    => ["Terkirim", "badge-green"],
        "pending" => ["Menunggu", "badge-orange"],
        "failed"  => ["Gagal",    "badge-red"],
    ];

    return $map[$status] ?? [$status, "badge-gray"];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - CampusBook</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/admin_modules.css">
</head>
<body>

<div class="admin-layout">
    <?php require_once __DIR__ . "/../includes/admin_sidebar.php"; ?>

    <main class="admin-main">
        <?php render_admin_header(
            "MANAJEMEN NOTIFIKASI",
            "Notifikasi",
            "Kirim pengumuman ke mahasiswa dan pantau riwayat pengirimannya."
        ); ?>

        <?php render_flash($flash, $error); ?>

        <section class="summary-grid">
            <article class="summary-card">
                <span class="summary-icon blue">♧</span>
                <div>
                    <p>Total Notifikasi</p>
                    <strong><?= (int) $stats["total"] ?></strong>
                </div>
            </article>

            <article class="summary-card">
                <span class="summary-icon green">✓</span>
                <div>
                    <p>Terkirim</p>
                    <strong><?= (int) $stats["sent_total"] ?></strong>
                </div>
            </article>

            <article class="summary-card">
                <span class="summary-icon orange">◷</span>
                <div>
                    <p>Menunggu</p>
                    <strong><?= (int) $stats["pending_total"] ?></strong>
                </div>
            </article>

            <article class="summary-card">
                <span class="summary-icon red">!</span>
                <div>
                    <p>Gagal</p>
                    <strong><?= (int) $stats["failed_total"] ?></strong>
                </div>
            </article>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2>Riwayat Notifikasi</h2>
                    <p>Notifikasi otomatis dari pemesanan dan pembayaran juga tercatat di sini.</p>
                </div>

                <button type="button" class="btn btn-primary" id="openSendModal">
                    + Kirim Notifikasi
                </button>
            </div>

            <form method="GET" class="toolbar">
                <div class="toolbar-search">
                    <span>⌕</span>
                    <input
                        type="search"
                        name="search"
                        placeholder="Cari judul, isi, nama, atau NIM..."
                        value="<?= e($keyword) ?>"
                        aria-label="Cari notifikasi"
                    >
                </div>

                <select name="status" aria-label="Filter status">
                    <option value="">Semua Status</option>
                    <?php foreach (["sent", "pending", "failed"] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $statusFilter === $status ? "selected" : "" ?>>
                            <?= e(notification_status_meta($status)[0]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="channel" aria-label="Filter kanal">
                    <option value="">Semua Kanal</option>
                    <?php foreach (CHANNELS as $channel): ?>
                        <option value="<?= e($channel) ?>" <?= $channelFilter === $channel ? "selected" : "" ?>>
                            <?= e(channel_label($channel)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-soft">Cari</button>

                <?php if ($activeFilters): ?>
                    <a href="notifikasi.php" class="btn btn-soft">Reset</a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Penerima</th>
                            <th>Notifikasi</th>
                            <th>Kanal</th>
                            <th>Status</th>
                            <th>Waktu kirim</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($notifications): ?>
                        <?php foreach ($notifications as $index => $item): ?>
                            <?php [$statusLabel, $statusClass] = notification_status_meta($item["status"]); ?>
                            <tr>
                                <td><?= $offset + $index + 1 ?></td>
                                <td>
                                    <strong><?= e($item["student_name"]) ?></strong>
                                    <span class="cell-sub"><?= e($item["nim"]) ?></span>
                                </td>
                                <td>
                                    <div class="cell-wrap">
                                        <strong><?= e($item["title"]) ?></strong>
                                        <span class="cell-sub">
                                            <?= e(mb_strimwidth($item["message"], 0, 70, "...", "UTF-8")) ?>
                                        </span>
                                    </div>
                                </td>
                                <td><?= e(channel_label($item["channel"])) ?></td>
                                <td><?= badge($statusLabel, $statusClass) ?></td>
                                <td>
                                    <?= $item["sent_at"]
                                        ? e(tanggal_id($item["sent_at"]) . " " . substr($item["sent_at"], 11, 5))
                                        : "-" ?>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button
                                            type="button"
                                            class="btn btn-outline btn-sm open-detail"
                                            data-student="<?= e($item["student_name"] . " (" . $item["nim"] . ")") ?>"
                                            data-title="<?= e($item["title"]) ?>"
                                            data-message="<?= e($item["message"]) ?>"
                                            data-channel="<?= e(channel_label($item["channel"])) ?>"
                                            data-status="<?= e($statusLabel) ?>"
                                            data-booking="<?= e($item["booking_code"] ?? "") ?>"
                                            data-created="<?= e(tanggal_id($item["created_at"]) . " " . substr($item["created_at"], 11, 5)) ?>"
                                        >Lihat</button>

                                        <?php if ($item["status"] !== "sent"): ?>
                                            <form method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="back" value="<?= e($backValue) ?>">
                                                <input type="hidden" name="action" value="resend">
                                                <input type="hidden" name="id" value="<?= (int) $item["id"] ?>">
                                                <button type="submit" class="btn btn-soft btn-sm">Kirim ulang</button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" data-confirm="Yakin ingin menghapus notifikasi ini?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="back" value="<?= e($backValue) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $item["id"] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="table-empty">
                                <strong>Notifikasi tidak ditemukan</strong>
                                <p>Belum ada notifikasi atau filter tidak cocok. Kirim pengumuman pertama dengan tombol di atas.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="panel-footer split">
                <span>
                    Menampilkan <?= count($notifications) ?> dari <?= $totalRows ?> notifikasi.
                </span>
                <?php render_pagination($page, $pages, $activeFilters); ?>
            </div>
        </section>
    </main>
</div>

<!-- Modal kirim notifikasi -->
<div class="app-modal" id="sendModal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="sendTitle">
        <div class="modal-head">
            <div>
                <h2 id="sendTitle">Kirim Notifikasi</h2>
                <p>Pesan akan dikirim ke mahasiswa dengan akun aktif.</p>
            </div>
            <button type="button" class="modal-x" data-close-modal="sendModal" aria-label="Tutup">&times;</button>
        </div>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="send">

            <div class="field-row">
                <div class="field">
                    <label for="recipient">Penerima</label>
                    <select id="recipient" name="recipient" required>
                        <option value="all" <?= $formData["recipient"] === "all" ? "selected" : "" ?>>Semua mahasiswa aktif</option>
                        <option value="one" <?= $formData["recipient"] === "one" ? "selected" : "" ?>>Satu mahasiswa</option>
                    </select>
                </div>

                <div class="field">
                    <label for="channel">Kanal</label>
                    <select id="channel" name="channel" required>
                        <?php foreach (CHANNELS as $channel): ?>
                            <option value="<?= e($channel) ?>" <?= $formData["channel"] === $channel ? "selected" : "" ?>>
                                <?= e(channel_label($channel)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="field" id="studentField" hidden>
                <label for="studentId">Mahasiswa</label>
                <select id="studentId" name="student_id">
                    <option value="">Pilih mahasiswa</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= (int) $student["id"] ?>" <?= $formData["student_id"] === (int) $student["id"] ? "selected" : "" ?>>
                            <?= e($student["name"] . " - " . $student["nim"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <small id="channelHint" style="margin: -6px 0 0;"></small>
            </div>

            <div class="field">
                <label for="notifTitle">Judul</label>
                <input type="text" id="notifTitle" name="title" maxlength="150"
                       value="<?= e($formData["title"]) ?>" required>
            </div>

            <div class="field">
                <label for="notifMessage">Isi pesan</label>
                <textarea id="notifMessage" name="message" required><?= e($formData["message"]) ?></textarea>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn btn-outline" data-close-modal="sendModal">Batal</button>
                <button type="submit" class="btn btn-primary">Kirim Notifikasi</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal detail notifikasi -->
<div class="app-modal" id="detailModal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="detailTitle">
        <div class="modal-head">
            <div>
                <h2 id="detailTitle">Detail Notifikasi</h2>
                <p id="dCreated"></p>
            </div>
            <button type="button" class="modal-x" data-close-modal="detailModal" aria-label="Tutup">&times;</button>
        </div>

        <dl class="detail-list">
            <div><dt>Penerima</dt><dd id="dStudent"></dd></div>
            <div><dt>Judul</dt><dd id="dTitle"></dd></div>
            <div><dt>Isi pesan</dt><dd id="dMessage"></dd></div>
            <div><dt>Kanal</dt><dd id="dChannel"></dd></div>
            <div><dt>Status</dt><dd id="dStatus"></dd></div>
            <div><dt>Pemesanan</dt><dd id="dBooking"></dd></div>
        </dl>

        <div class="modal-foot">
            <button type="button" class="btn btn-outline" data-close-modal="detailModal">Tutup</button>
        </div>
    </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function () {
    const recipient = document.getElementById("recipient");
    const studentField = document.getElementById("studentField");
    const studentId = document.getElementById("studentId");
    const channel = document.getElementById("channel");
    const hint = document.getElementById("channelHint");

    const hints = {
        in_app: "Pesan langsung muncul di akun mahasiswa.",
        email: "Dikirim ke email mahasiswa. Server harus sudah dikonfigurasi untuk mengirim email.",
        whatsapp: "WhatsApp belum terhubung ke gateway, sehingga pesan masuk antrean (status Menunggu)."
    };

    function syncForm() {
        const single = recipient.value === "one";
        studentField.hidden = !single;
        studentId.required = single;
        hint.textContent = hints[channel.value] || "";
    }

    recipient.addEventListener("change", syncForm);
    channel.addEventListener("change", syncForm);
    syncForm();

    document.getElementById("openSendModal").addEventListener("click", function () {
        syncForm();
        openModal("sendModal");
    });

    const text = function (id, value) {
        document.getElementById(id).textContent = value && value !== "" ? value : "-";
    };

    document.querySelectorAll(".open-detail").forEach(function (button) {
        button.addEventListener("click", function () {
            const d = button.dataset;
            text("dCreated", "Dibuat " + d.created);
            text("dStudent", d.student);
            text("dTitle", d.title);
            text("dMessage", d.message);
            text("dChannel", d.channel);
            text("dStatus", d.status);
            text("dBooking", d.booking);
            openModal("detailModal");
        });
    });

<?php if ($showModal): ?>
    openModal("sendModal");
<?php endif; ?>
});
</script>

</body>
</html>