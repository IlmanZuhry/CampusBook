# CampusBook

CampusBook adalah aplikasi berbasis web untuk mengelola data dan akun mahasiswa. Proyek ini menggunakan PHP dan MySQL serta menyediakan halaman admin untuk mengelola data mahasiswa.

## ✨ Fitur Utama

- Login dan pembatasan akses berdasarkan peran pengguna.
- Tambah, lihat, edit, dan hapus data mahasiswa.
- Pencarian mahasiswa berdasarkan NIM, nama, atau email.
- Filter status akun mahasiswa.
- Validasi input dan pemeriksaan duplikasi NIM/email.
- Penyimpanan password menggunakan hashing.
- Pengiriman email informasi akun mahasiswa melalui Gmail SMTP.
- Notifikasi hasil operasi pada halaman admin.

## 🛠️ Teknologi

- **Backend:** PHP
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript
- **Dependency Manager:** Composer
- **Email:** PHPMailer
- **Environment Configuration:** PHP dotenv

## 📋 Persyaratan

- PHP dengan ekstensi MySQLi dan OpenSSL aktif.
- MySQL atau MariaDB.
- Composer.
- Web server lokal seperti Laragon atau XAMPP.
- Gmail dengan Verifikasi 2 Langkah dan App Password untuk fitur email.

## 🚀 Instalasi

### 1. Clone repository

```bash
git clone https://github.com/IlmanZuhry/CampusBook.git
```

### 2. Siapkan lingkungan lokal

Letakkan folder proyek di direktori web server. Contohnya, untuk Laragon:

```text
C:\laragon\www\CampusBook
```

### 3. Siapkan database

Buat database MySQL bernama `campusbook`, kemudian import struktur tabel yang diperlukan oleh aplikasi.

Sesuaikan konfigurasi koneksi database pada `config/database.php` dengan pengaturan lokal kamu.

### 4. Instal dependensi

Buka terminal di folder proyek, lalu jalankan:

```bash
composer install
```

### 5. Konfigurasi email

Buat file `.env` di direktori utama proyek, sejajar dengan `composer.json`.

```dotenv
CAMPUSBOOK_MAIL_USER="alamat-gmail-anda@gmail.com"
CAMPUSBOOK_MAIL_PASS="app-password-gmail-anda"
```

Gunakan **Google App Password**, bukan password login Gmail biasa.

### 6. Jalankan aplikasi

Aktifkan web server dan MySQL melalui Laragon atau XAMPP, kemudian buka aplikasi melalui browser:

```text
http://localhost/CampusBook/
```

Sesuaikan URL dengan entry point aplikasi yang tersedia.

## 📧 Konfigurasi Email

CampusBook menggunakan PHPMailer untuk mengirim email melalui Gmail SMTP.

Jika email tidak terkirim, periksa:
- Alamat Gmail dan App Password.
- Koneksi internet.
- Konfigurasi SMTP di `config/mail.php`.
- Log PHP untuk detail kesalahan.

## 🔐 Keamanan

- Jangan mengunggah file `.env` ke GitHub.
- Jangan membagikan App Password Gmail.
- Pastikan file `.env` dan folder `vendor/` tercantum di `.gitignore`.
- Password mahasiswa disimpan dalam bentuk hash di database.
- Mahasiswa sebaiknya mengganti password awal setelah berhasil login.

Contoh `.gitignore`:

```gitignore
.env
/vendor/
```

## 📁 Struktur Direktori

```text
CampusBook/
├── admin/
│   └── data_mahasiswa.php
├── assets/
│   └── css/
├── config/
│   ├── database.php
│   └── mail.php
├── includes/
├── composer.json
├── composer.lock
├── README.md
├── .env
└── vendor/
```

Folder `.env` dan `vendor/` pada contoh di atas tidak perlu diunggah ke repository; `.env` berisi konfigurasi rahasia, sedangkan `vendor/` dapat dibuat kembali melalui Composer.
