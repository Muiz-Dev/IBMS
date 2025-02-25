<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, full_name, password, email_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            if ($user['email_verified']) {
                $token = generateJWT($user['id']);
                setcookie('auth_token', $token, time() + JWT_EXPIRATION, '/', '', true, true);
                echo json_encode(['success' => true, 'message' => 'Login successful', 'redirect' => 'dashboard.php']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Please verify your email before logging in.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    $stmt->close();
}