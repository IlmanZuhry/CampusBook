
<?php
$currentPage = basename($_SERVER["PHP_SELF"]);
?>

<aside class="admin-sidebar">
    <a href="dashboard.php" class="admin-brand">
        <img src="../assets/images/logoc.png" alt="CampusBook">
        <span>Campus<span class="brand-accent">Book</span></span>
    </a>

    <div class="sidebar-label">MENU UTAMA</div>

    <nav class="sidebar-nav">
        <a href="dashboard.php"
           class="sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">▦</span>
            <span>Beranda</span>
        </a>

        <a href="data_mahasiswa.php"
           class="sidebar-link <?= $currentPage === 'data_mahasiswa.php' ? 'active' : '' ?>">
            <span class="nav-icon">♙</span>
            <span>Data Mahasiswa</span>
        </a>

        <a href="#"
           class="sidebar-link <?= $currentPage === 'fasilitas.php' ? 'active' : '' ?>">
            <span class="nav-icon">▣</span>
            <span>Fasilitas</span>
        </a>

        <a href="#"
           class="sidebar-link <?= $currentPage === 'pemesanan.php' ? 'active' : '' ?>">
            <span class="nav-icon">▤</span>
            <span>Pemesanan</span>
        </a>

        <a href="#"
           class="sidebar-link <?= $currentPage === 'pembayaran.php' ? 'active' : '' ?>">
            <span class="nav-icon">◇</span>
            <span>Pembayaran</span>
        </a>

        <a href="#"
           class="sidebar-link <?= $currentPage === 'notifikasi.php' ? 'active' : '' ?>">
            <span class="nav-icon">♧</span>
            <span>Notifikasi</span>
        </a>
    </nav>

    <div class="sidebar-bottom">
        <button type="button" class="sidebar-link logout-link"
                id="openLogoutModal">
            <span class="nav-icon">↪</span>
            <span>Keluar</span>
        </button>

        <div class="logout-modal" id="logoutModal" aria-hidden="true">
            <div class="logout-dialog"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="logoutTitle"
                 aria-describedby="logoutDescription">

                <div class="logout-modal-icon">↪</div>

                <h2 id="logoutTitle">Konfirmasi Logout</h2>

                <p id="logoutDescription">
                    Apakah kamu yakin ingin keluar dari akun CampusBook?
                </p>

                <div class="logout-modal-actions">
                    <button type="button" class="logout-cancel"
                            id="cancelLogout">
                        Batal
                    </button>

                    <a href="../logout.php" class="logout-confirm">
                        Ya, Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</aside>
<script>
const logoutModal = document.getElementById("logoutModal");
const openLogoutModal = document.getElementById("openLogoutModal");
const cancelLogout = document.getElementById("cancelLogout");

function showLogoutModal() {
    logoutModal.classList.add("show");
    logoutModal.setAttribute("aria-hidden", "false");
    cancelLogout.focus();
}

function hideLogoutModal() {
    logoutModal.classList.remove("show");
    logoutModal.setAttribute("aria-hidden", "true");
    openLogoutModal.focus();
}

openLogoutModal.addEventListener("click", showLogoutModal);
cancelLogout.addEventListener("click", hideLogoutModal);

logoutModal.addEventListener("click", function (event) {
    if (event.target === logoutModal) {
        hideLogoutModal();
    }
});

document.addEventListener("keydown", function (event) {
    if (
        event.key === "Escape" &&
        logoutModal.classList.contains("show")
    ) {
        hideLogoutModal();
    }
});
</script>
