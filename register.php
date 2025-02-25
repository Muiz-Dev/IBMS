<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $verification_token = bin2hex(random_bytes(16));

    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, verification_token) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $full_name, $email, $password, $verification_token);
    
    if ($stmt->execute()) {
        $verification_link = "http://localhost:8001/verify.php?token=$verification_token";
        $subject = "Verify Your Email";
        $body = "Click the following link to verify your email: $verification_link";
        
        if (sendEmail($email, $subject, $body)) {
            echo json_encode(['success' => true, 'message' => 'Registration successful. Please check your email to verify your account.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration successful, but failed to send verification email. Please contact support.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
    }
    $stmt->close();
}