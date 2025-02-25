<?php
require_once 'config.php';

if (isset($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);
    
    $sql = "UPDATE users SET email_verified = 1, verification_token = NULL WHERE verification_token = '$token'";
    
    if ($conn->query($sql) === TRUE && $conn->affected_rows > 0) {
        echo "Email verified successfully. You can now <a href='index.php'>login</a>.";
    } else {
        echo "Invalid or expired verification token.";
    }
} else {
    echo "No verification token provided.";
}