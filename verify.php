<?php
require_once 'config.php';
session_start();

$message = '';
$messageType = '';

if (isset($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);
    
    // First check if token exists and is valid
    $check_stmt = $conn->prepare("SELECT id, email FROM users WHERE verification_token = ?");
    $check_stmt->bind_param("s", $token);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Update user verification status
        $update_stmt = $conn->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE verification_token = ?");
        $update_stmt->bind_param("s", $token);
        
        if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
            $messageType = 'success';
            $message = "Your email has been successfully verified!";
            
            // Generate JWT token for automatic login
            $auth_token = generateJWT($user['id']);
            setcookie('auth_token', $auth_token, time() + JWT_EXPIRATION, '/', '', true, true);
        } else {
            $messageType = 'error';
            $message = "An error occurred while verifying your email. Please try again.";
        }
        $update_stmt->close();
    } else {
        $messageType = 'error';
        $message = "Invalid or expired verification token.";
    }
    $check_stmt->close();
} else {
    $messageType = 'error';
    $message = "No verification token provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Invoice & Billing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white shadow-lg rounded-lg p-8 text-center">
            <?php if ($messageType === 'success'): ?>
                <div class="mb-6">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                        <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 class="mt-4 text-2xl font-semibold text-gray-900">Email Verified!</h2>
                    <p class="mt-2 text-gray-600"><?php echo htmlspecialchars($message); ?></p>
                    <div class="mt-6">
                        <a href="dashboard.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Continue to Dashboard
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="mb-6">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <h2 class="mt-4 text-2xl font-semibold text-gray-900">Verification Failed</h2>
                    <p class="mt-2 text-gray-600"><?php echo htmlspecialchars($message); ?></p>
                    <div class="mt-6">
                        <a href="index.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Return to Login
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-redirect after successful verification
        <?php if ($messageType === 'success'): ?>
        setTimeout(() => {
            window.location.href = 'dashboard.php';
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>