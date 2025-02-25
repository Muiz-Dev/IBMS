<?php
require_once 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember-me']);

    $stmt = $conn->prepare("SELECT id, full_name, password, email_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            if ($user['email_verified']) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                
                // Generate JWT token
                $token = generateJWT($user['id']);
                
                // Set cookie with proper parameters
                $cookie_options = array(
                    'expires' => $remember ? time() + (30 * 24 * 60 * 60) : time() + JWT_EXPIRATION,
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                );
                setcookie('auth_token', $token, $cookie_options);

                // Clear any existing error messages
                unset($_SESSION['login_error']);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Login successful! Redirecting...', 
                    'redirect' => 'dashboard.php'
                ]);
                exit();
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Please verify your email before logging in.'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid credentials'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'User not found'
        ]);
    }
    $stmt->close();
    exit();
}