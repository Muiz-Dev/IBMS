<?php
require_once 'config.php';
require 'vendor/autoload.php';

use Firebase\JWT\JWT;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT id, full_name, password, role FROM users WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $payload = [
                'user_id' => $user['id'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'exp' => time() + (60 * 60) // Token expires in 1 hour
            ];

            $jwt = JWT::encode($payload, JWT_SECRET, 'HS256');
            echo json_encode(['success' => true, 'token' => $jwt]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
}