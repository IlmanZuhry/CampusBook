
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
            <span class="nav-icon">
                <i class="fa-solid fa-house"></i>
            </span>
            <span>Beranda</span>
        </a>

        <a href="fasilitas.php"
           class="sidebar-link <?= $currentPage === 'fasilitas.php' ? 'active' : '' ?>">
            <span class="nav-icon">
                <i class="fa-solid fa-building"></i>
            </span>
            <span>Fasilitas</span>
        </a>

        <a href="pemesanan.php"
           class="sidebar-link <?= $currentPage === 'pemesanan.php' ? 'active' : '' ?>">
            <span class="nav-icon">
                <i class="fa-solid fa-calendar-check"></i>
            </span>
            <span>Pemesanan Saya</span>
        </a>

        <a href="pembayaran.php"
           class="sidebar-link <?= $currentPage === 'pembayaran.php' ? 'active' : '' ?>">
            <span class="nav-icon">
                <i class="fa-solid fa-credit-card"></i>
            </span>
            <span>Pembayaran</span>
        </a>

        <a href="notifikasi.php"
           class="sidebar-link <?= $currentPage === 'notifikasi.php' ? 'active' : '' ?>">
            <span class="nav-icon">
                <i class="fa-solid fa-bell"></i>
            </span>
            <span>Notifikasi</span>
        </a>

        <div class="sidebar-label" style="margin-top: 24px;">
            AKUN
        </div>

        <a href="profil.php"
           class="sidebar-link <?= $currentPage === 'profil.php' ? 'active' : '' ?>">
            <span class="nav-icon">
                <i class="fa-solid fa-user"></i>
            </span>
            <span>Profil Saya</span>
        </a>
    </nav>

    <div class="sidebar-bottom">
        <button type="button"
                class="sidebar-link logout-link"
                id="openUserLogoutModal">
            <span class="nav-icon">
                <i class="fa-solid fa-right-from-bracket"></i>
            </span>
            <span>Keluar</span>
        </button>
    </div>

    <div class="logout-modal" id="userLogoutModal" aria-hidden="true">
        <div class="logout-dialog"
             role="dialog"
             aria-modal="true"
             aria-labelledby="userLogoutTitle"
             aria-describedby="userLogoutDescription">

            <div class="logout-modal-icon">
                <i class="fa-solid fa-right-from-bracket"></i>
            </div>

            <h2 id="userLogoutTitle">Konfirmasi Logout</h2>

            <p id="userLogoutDescription">
                Apakah kamu yakin ingin keluar dari akun CampusBook?
            </p>

            <div class="logout-modal-actions">
                <button type="button"
                        class="logout-cancel"
                        id="cancelUserLogout">
                    Batal
                </button>

                <a href="../logout.php" class="logout-confirm">
                    Ya, Logout
                </a>
            </div>
        </div>
    </div>
</aside>

<script>
(function () {
    const modal = document.getElementById("userLogoutModal");
    const openButton = document.getElementById("openUserLogoutModal");
    const cancelButton = document.getElementById("cancelUserLogout");

    if (!modal || !openButton || !cancelButton) return;

    function showModal() {
        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
        cancelButton.focus();
    }

    function hideModal() {
        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");
        openButton.focus();
    }

    openButton.addEventListener("click", showModal);
    cancelButton.addEventListener("click", hideModal);

    modal.addEventListener("click", function (event) {
        if (event.target === modal) {
            hideModal();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && modal.classList.contains("show")) {
            hideModal();
        }
    });
})();
</script>
