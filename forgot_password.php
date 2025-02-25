<?php
require_once 'config.php';
session_start();

// CSRF protection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit;
    }

    // Check if the email exists in the database
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Don't reveal that the email doesn't exist, for security reasons
        echo json_encode(['success' => true, 'message' => 'If the email exists, password reset instructions will be sent.']);
        exit;
    }

    $reset_token = bin2hex(random_bytes(32));
    $reset_token_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
    $stmt->bind_param("sss", $reset_token, $reset_token_expires, $email);
    
    if ($stmt->execute()) {
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=$reset_token";
        $subject = "Reset Your Password - Invoice & Billing System";
        $body = "
            <html>
            <body>
                <h2>Reset Your Password</h2>
                <p>You have requested to reset your password for the Invoice & Billing System. Click the button below to reset your password:</p>
                <p>
                    <a href='$reset_link' style='background-color: #4CAF50; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer;'>Reset Password</a>
                </p>
                <p>If you didn't request this, you can safely ignore this email.</p>
                <p>This link will expire in 1 hour for security reasons.</p>
            </body>
            </html>
        ";
        
        if (sendEmail($email, $subject, $body)) {
            echo json_encode(['success' => true, 'message' => 'Password reset instructions sent to your email.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send password reset email. Please try again.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
    $stmt->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Invoice & Billing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md max-w-md w-full">
        <h1 class="text-2xl font-bold mb-6 text-center text-gray-800">Forgot Password</h1>
        <form id="forgotPasswordForm" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                <input type="email" id="email" name="email" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out">
                Send Reset Link
            </button>
        </form>
        <div class="mt-6 text-center">
            <a href="index.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Back to Login</a>
        </div>
    </div>

    <div id="alert" class="fixed bottom-5 right-5 px-6 py-3 rounded-lg shadow-lg hidden"></div>

    <script>
        const form = document.getElementById('forgotPasswordForm');
        const alert = document.getElementById('alert');
        const submitButton = form.querySelector('button[type="submit"]');

        function showAlert(message, type) {
            alert.textContent = message;
            alert.classList.remove('hidden', 'bg-green-500', 'bg-red-500');
            alert.classList.add(type === 'success' ? 'bg-green-500' : 'bg-red-500', 'text-white');
            alert.classList.remove('hidden');
            setTimeout(() => alert.classList.add('hidden'), 5000);
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            submitButton.disabled = true;
            submitButton.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Sending...';

            const formData = new FormData(form);

            try {
                const response = await fetch('forgot_password.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                showAlert(data.message, data.success ? 'success' : 'error');
                if (data.success) form.reset();
            } catch (error) {
                showAlert('An error occurred. Please try again.', 'error');
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = 'Send Reset Link';
            }
        });
    </script>
</body>
</html>