<script>
/* Modal logout (sidebar) dan helper modal untuk halaman admin */
(function () {
    const logoutModal = document.getElementById("logoutModal");
    const openLogout = document.getElementById("openLogoutModal");
    const cancelLogout = document.getElementById("cancelLogout");

    if (logoutModal && openLogout && cancelLogout) {
        const show = function () {
            logoutModal.classList.add("show");
            logoutModal.setAttribute("aria-hidden", "false");
            cancelLogout.focus();
        };
        const hide = function () {
            logoutModal.classList.remove("show");
            logoutModal.setAttribute("aria-hidden", "true");
            openLogout.focus();
        };

        openLogout.addEventListener("click", show);
        cancelLogout.addEventListener("click", hide);
        logoutModal.addEventListener("click", function (event) {
            if (event.target === logoutModal) hide();
        });
        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && logoutModal.classList.contains("show")) hide();
        });
    }
})();

/* Modal umum: buka/tutup dengan id, Escape, klik latar */
function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add("show");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
    const focusable = modal.querySelector("input:not([type=hidden]), select, textarea, button");
    if (focusable) focusable.focus();
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");
    if (!document.querySelector(".app-modal.show")) {
        document.body.classList.remove("modal-open");
    }
}

document.addEventListener("click", function (event) {
    const closer = event.target.closest("[data-close-modal]");
    if (closer) {
        closeModal(closer.dataset.closeModal);
        return;
    }
    if (event.target.classList && event.target.classList.contains("app-modal")) {
        closeModal(event.target.id);
    }
});

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
        document.querySelectorAll(".app-modal.show").forEach(function (modal) {
            closeModal(modal.id);
        });
    }
});

/* Konfirmasi untuk form berbahaya: <form data-confirm="Pesan"> */
document.addEventListener("submit", function (event) {
    const message = event.target.dataset ? event.target.dataset.confirm : "";
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});
</script>
