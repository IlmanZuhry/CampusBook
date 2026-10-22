<?php
$facilities = [
    [
        "name" => "Aula Utama",
        "category" => "Aula",
        "capacity" => "200 orang",
        "status" => "Tersedia",
        "image" => "https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=900&q=80"
    ],
    [
        "name" => "Lab Komputer",
        "category" => "Laboratorium",
        "capacity" => "40 orang",
        "status" => "Tersedia",
        "image" => "https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&w=900&q=80"
    ],
    [
        "name" => "Lapangan Futsal",
        "category" => "Olahraga",
        "capacity" => "20 orang",
        "status" => "Tersedia",
        "image" => "https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=900&q=80"
    ]
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CampusBook - Pesan Fasilitas Kampus Lebih Mudah</title>

    <meta name="description"
          content="CampusBook membantu mahasiswa memesan fasilitas kampus secara online dengan mudah dan teratur.">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">
   <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<!-- NAVBAR -->
<header class="site-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid px-0">

            <a class="brand" href="#beranda">
                <img src="assets/images/logoc.png"
                     alt="Logo CampusBook">
                <span>Campus<span>Book</span></span>
            </a>

            <button class="navbar-toggler" type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#mainNavbar"
                    aria-controls="mainNavbar"
                    aria-expanded="false"
                    aria-label="Buka navigasi">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#beranda">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#fasilitas">Fasilitas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#cara-pesan">Cara Pemesanan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#tentang">Tentang</a>
                    </li>
                </ul>

                <a href="login.php" class="btn-primary-custom">
                    Masuk <span aria-hidden="true">→</span>
                </a>
            </div>

        </div>
    </nav>
</header>

<main>

<!-- HERO -->
<section class="hero-section" id="beranda">
    <div class="container">
        <div class="row align-items-center g-5">

            <div class="col-lg-6 hero-content">
                
                <h1 class="hero-title">
                    Pesan Fasilitas Kampus
                    <span>Lebih Mudah</span>
                </h1>

                <p class="hero-description">
                    CampusBook adalah sistem pemesanan fasilitas kampus
                    secara online untuk laboratorium, aula, dan lapangan
                    dengan jadwal yang teratur, pembayaran, dan notifikasi.
                </p>

                <div class="hero-actions">
                    <a href="login.php" class="btn-primary-custom">
                        Pesan Sekarang <span aria-hidden="true">→</span>
                    </a>

                    <a href="#fasilitas" class="btn-outline-custom">
                        Lihat Fasilitas
                    </a>
                </div>

                <div class="hero-highlights">
                    <div class="highlight-item">
                        <div class="highlight-icon">▦</div>
                        <span>Jadwal<br>Teratur</span>
                    </div>

                    <div class="highlight-item">
                        <div class="highlight-icon">▤</div>
                        <span>Pembayaran<br>Mudah</span>
                    </div>

                    <div class="highlight-item">
                        <div class="highlight-icon">♧</div>
                        <span>Notifikasi<br>Email / WA</span>
                    </div>

                    <div class="highlight-item">
                        <div class="highlight-icon">▣</div>
                        <span>Laporan<br>Pemakaian</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="hero-visual">

                    
                    <div class="hero-photo">
                        <img
                            id="heroMainImage"
                            src="<?= htmlspecialchars($facilities[0]["image"]) ?>"
                            alt="<?= htmlspecialchars($facilities[0]["name"]) ?>"
                        >
                    </div>


                    <div class="facility-preview">
                        <div class="preview-heading">
                            <h3>Fasilitas Pilihan</h3>
                            <a href="#fasilitas">Lihat Semua →</a>
                        </div>

                        
                    <div class="row g-3">
                        <?php foreach ($facilities as $index => $facility): ?>
                            <div class="col-4">
                                <button
                                    type="button"
                                    class="preview-card <?= $index === 0 ? 'selected' : '' ?>"
                                    data-image="<?= htmlspecialchars($facility['image']) ?>"
                                    data-name="<?= htmlspecialchars($facility['name']) ?>"
                                    aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"
                                >
                                    <img
                                        src="<?= htmlspecialchars($facility['image']) ?>"
                                        alt="<?= htmlspecialchars($facility['name']) ?>"
                                        loading="lazy"
                                    >

                                    <span class="preview-name">
                                        <?= htmlspecialchars($facility['name']) ?>
                                    </span>

                                    <span class="availability">
                                        <?= htmlspecialchars($facility['status']) ?>
                                    </span>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<!-- FACILITIES -->
<section class="section facilities-section" id="fasilitas">
    <div class="container">

        <div class="section-heading">
            <div class="section-label">Fasilitas Kampus</div>
            <h2>Temukan Fasilitas yang Kamu Butuhkan</h2>
            <p>
                Pilih fasilitas sesuai kebutuhan kegiatanmu dan
                lanjutkan pemesanan melalui CampusBook.
            </p>
        </div>

        <div class="row g-4">
            <?php foreach ($facilities as $facility): ?>
                <div class="col-md-6 col-lg-4">
                    <article class="facility-card">

                        <div class="facility-image">
                            <img
                                src="<?= htmlspecialchars($facility["image"]) ?>"
                                alt="<?= htmlspecialchars($facility["name"]) ?>"
                                loading="lazy">

                            <span class="status-badge">
                                <?= htmlspecialchars($facility["status"]) ?>
                            </span>
                        </div>

                        <div class="facility-body">
                            <div class="facility-category">
                                <?= htmlspecialchars($facility["category"]) ?>
                            </div>

                            <h3>
                                <?= htmlspecialchars($facility["name"]) ?>
                            </h3>

                            <p class="facility-description">
                                Fasilitas kampus untuk mendukung kegiatan
                                akademik, organisasi, dan aktivitas mahasiswa.
                            </p>

                            <div class="facility-meta">
                                <span>♙ Kapasitas</span>
                                <strong>
                                    <?= htmlspecialchars($facility["capacity"]) ?>
                                </strong>
                            </div>

                            <a href="login.php" class="btn-outline-custom">
                                Masuk untuk Memesan →
                            </a>
                        </div>

                    </article>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>

<!-- FEATURES -->
<section class="section" id="tentang">
    <div class="container">

        <div class="section-heading">
            <div class="section-label">Keunggulan CampusBook</div>
            <h2>Pemesanan Lebih Praktis dan Teratur</h2>
            <p>
                Semua kebutuhan pemesanan fasilitas dalam satu sistem
                yang mudah digunakan.
            </p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon">▦</div>
                    <h3>Jadwal Teratur</h3>
                    <p>
                        Periksa ketersediaan jadwal dan kurangi risiko
                        bentrok pemakaian fasilitas.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon">▤</div>
                    <h3>Pembayaran Simulasi</h3>
                    <p>
                        Lihat nominal transaksi dan status pembayaran
                        dalam satu halaman.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon">♧</div>
                    <h3>Notifikasi</h3>
                    <p>
                        Dapatkan informasi mengenai status permohonan
                        dan konfirmasi pemesanan.
                    </p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon">▣</div>
                    <h3>Laporan Pemakaian</h3>
                    <p>
                        Bantu admin memantau riwayat booking dan
                        penggunaan fasilitas kampus.
                    </p>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- HOW IT WORKS -->
<section class="section steps-section" id="cara-pesan">
    <div class="container">

        <div class="section-heading">
            <div class="section-label">Cara Pemesanan</div>
            <h2>Hanya Beberapa Langkah</h2>
            <p>
                Proses pemesanan yang jelas dari awal hingga konfirmasi.
            </p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3>Masuk ke Akun</h3>
                    <p>Gunakan akun mahasiswa yang sudah didaftarkan admin.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3>Pilih Fasilitas</h3>
                    <p>Tentukan fasilitas, tanggal, dan waktu penggunaan.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h3>Ajukan Booking</h3>
                    <p>Periksa rincian dan kirim permohonan pemesanan.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="step-card">
                    <div class="step-number">4</div>
                    <h3>Konfirmasi</h3>
                    <p>Selesaikan pembayaran simulasi dan lihat bukti booking.</p>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- CALL TO ACTION -->
<section class="cta-section">
    <div class="container">
        <div class="cta-box">
            <h2>Siap Memesan Fasilitas Kampus?</h2>
            <p>
                Masuk ke akunmu dan mulai proses pemesanan dengan mudah.
            </p>
            <a href="login.php" class="btn-white">
                Masuk ke CampusBook →
            </a>
        </div>
    </div>
</section>

</main>

<!-- FOOTER -->
<footer>
    <div class="container">
        <div class="footer-content">

            <a class="brand" href="#beranda">
                <img src="assets/images/logoc.png"
                     alt="Logo CampusBook">
                <span>Campus<span>Book</span></span>
            </a>

            <p class="footer-copy">
                © <?= date("Y") ?> CampusBook. Proyek E-Application.
            </p>

            <div class="footer-links">
                <a href="#beranda">Beranda</a>
                <a href="#fasilitas">Fasilitas</a>
                <a href="#cara-pesan">Cara Pemesanan</a>
                <a href="login.php">Masuk</a>
            </div>

        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>



<script>
const mainImage = document.getElementById("heroMainImage");
const previewCards = document.querySelectorAll(".preview-card");

let imageChanging = false;

previewCards.forEach((card) => {
    card.addEventListener("click", () => {
        if (imageChanging) return;

        const newImage = card.dataset.image;
        const newName = card.dataset.name;

        if (mainImage.getAttribute("src") === newImage) return;

        imageChanging = true;

        previewCards.forEach((item) => {
            const selected = item === card;
            item.classList.toggle("selected", selected);
            item.setAttribute("aria-pressed", String(selected));
        });

        const preload = new Image();

        preload.onload = () => {
            mainImage.classList.add("is-changing");

            const onFadeOut = (event) => {
                if (event.propertyName !== "opacity") return;

                mainImage.removeEventListener("transitionend", onFadeOut);

                mainImage.src = newImage;
                mainImage.alt = newName;

                requestAnimationFrame(() => {
                    mainImage.classList.remove("is-changing");
                    imageChanging = false;
                });
            };

            mainImage.addEventListener("transitionend", onFadeOut);
        };

        preload.onerror = () => {
            imageChanging = false;
        };

        preload.src = newImage;
    });
});
</script>

</body>
</html>
