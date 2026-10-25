
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

function kirimEmailCampusBook(
    string $emailTujuan,
    string $namaMahasiswa,
    string $nim,
    string $passwordAwal
): bool {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username = $_ENV['CAMPUSBOOK_MAIL_USER'] ?? '';
        $mail->Password = $_ENV['CAMPUSBOOK_MAIL_PASS'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        if ($mail->Username === '' || $mail->Password === '') {
            error_log('Konfigurasi Gmail CampusBook belum diatur.');
            return false;
        }

        $mail->setFrom($mail->Username, 'CampusBook');
        $mail->addAddress($emailTujuan, $namaMahasiswa);

        $mail->isHTML(true);
        $mail->Subject = 'Akun CampusBook Anda Telah Dibuat';

        $namaAman = htmlspecialchars(
            $namaMahasiswa,
            ENT_QUOTES,
            'UTF-8'
        );
        $nimAman = htmlspecialchars($nim, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <h2>Selamat datang di CampusBook!</h2>
            <p>Halo, {$namaAman}.</p>
            <p>Akun mahasiswa Anda berhasil dibuat oleh admin.</p>
            <p><strong>NIM:</strong> {$nimAman}</p>
            <p><strong>Email:</strong> " .
                htmlspecialchars($emailTujuan, ENT_QUOTES, 'UTF-8') .
            "</p>
            <p><strong>Password awal:</strong> " .
                htmlspecialchars($passwordAwal, ENT_QUOTES, 'UTF-8') .
            "</p>
            <p>Silakan login ke CampusBook dan segera ganti password
            awal Anda setelah berhasil masuk.</p>
        ";

        $mail->AltBody =
            "Selamat datang di CampusBook!\n" .
            "Nama: {$namaMahasiswa}\n" .
            "NIM: {$nim}\n" .
            "Email: {$emailTujuan}\n" .
            "Password awal: {$passwordAwal}\n" .
            "Segera ganti password setelah login.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Email CampusBook gagal dikirim: ' . $mail->ErrorInfo);
        return false;
    }
}
