<?php
require_once 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if it's a resend verification request
    if (isset($_POST['action']) && $_POST['action'] === 'resend_verification') {
        $email = $conn->real_escape_string($_POST['email']);
        
        // Check if user exists and is not verified
        $stmt = $conn->prepare("SELECT id, full_name, email_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if ($user['email_verified']) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'This account is already verified. Please login.'
                ]);
                exit();
            }
            
            // Generate new verification token
            $verification_token = bin2hex(random_bytes(16));
            
            // Update user with new token
            $update_stmt = $conn->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
            $update_stmt->bind_param("si", $verification_token, $user['id']);
            
            if ($update_stmt->execute()) {
                // Send verification email
                $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/verify.php?token=$verification_token";
                $subject = "Verify Your Email";
                $body = "
                    <html>
                    <body>
                        <h2>Email Verification</h2>
                        <p>Hello {$user['full_name']},</p>
                        <p>Please click the button below to verify your email address:</p>
                        <p>
                            <a href='$verification_link' style='background-color: #4CAF50; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer;'>Verify Email</a>
                        </p>
                        <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                        <p>$verification_link</p>
                        <p>Thank you,<br>" . COMPANY_NAME . "</p>
                    </body>
                    </html>
                ";
                
                if (sendEmail($email, $subject, $body)) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Verification email has been resent. Please check your inbox.'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Failed to send verification email. Please try again later.'
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'An error occurred. Please try again.'
                ]);
            }
            $update_stmt->close();
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Email not found. Please register first.'
            ]);
        }
        $stmt->close();
        exit();
    }

    // Regular login process
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
                    'message' => 'Please verify your email before logging in.',
                    'unverified' => true,
                    'email' => $email
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