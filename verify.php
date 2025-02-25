<?php
require_once 'config.php';

if (isset($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);
    
    $stmt = $conn->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE verification_token = ?");
    $stmt->bind_param("s", $token);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $message = "Email verified successfully. You can now <a href='index.php'>login</a>.";
    } else {
        $message = "Invalid or expired verification token.";
    }
    $stmt->close();
} else {
    $message = "No verification token provided.";
}
