
<header class="admin-header">
    <div class="header-heading">
        <span class="header-eyebrow">CAMPUSBOOK ADMINISTRATION</span>
        <h1>Dashboard</h1>
        <p>Ringkasan aktivitas dan pengelolaan fasilitas kampus.</p>
    </div>

    <div class="admin-profile">
        <div class="profile-avatar">
            <?= strtoupper(substr($_SESSION["name"] ?? "A", 0, 1)) ?>
        </div>

        <div class="profile-info">
            <strong>
                <?= htmlspecialchars($_SESSION["name"] ?? "Administrator") ?>
            </strong>
            <span>Administrator</span>
        </div>
    </div>
</header>
