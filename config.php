<?php
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'invoice');

// JWT configuration
define('JWT_SECRET', 'da2b760e930bf0145078c8475d73b3004108363dbe7e');
define('JWT_EXPIRATION', 3600); // 1 hour

// SMTP configuration
define('SMTP_HOST', 'mtl101.truehost.cloud');
define('SMTP_PORT', 587);
define('SMTP_USER', 'test@wheatchain.xyz');
define('SMTP_PASS', 'EL2W7FZsJQG4uYV');
define('SMTP_FROM', 'noreply@wheatchain.xyz');
define('SMTP_FROM_NAME', 'IBMS');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
       $mail->Port       = 587;
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function generateJWT($user_id) {
    $issuedAt = time();
    $expirationTime = $issuedAt + JWT_EXPIRATION;

    $payload = [
        'iat' => $issuedAt,
        'exp' => $expirationTime,
        'user_id' => $user_id
    ];

    return JWT::encode($payload, JWT_SECRET, 'HS256');
}

function verifyJWT($token) {
    try {
        $decoded = JWT::decode($token, JWT_SECRET, ['HS256']);
        return $decoded->user_id;
    } catch (Exception $e) {
        return false;
    }
}