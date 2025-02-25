<?php
require_once 'config.php';
session_start();

$token = isset($_GET['token']) ? $_GET['token'] : null;
$error_message = '';

// Validate token
if ($token) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error_message = 'Invalid or expired reset token. Please request a new password reset.';
        $token = null;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $conn->real_escape_string($_POST['token']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->bind_param("ss", $password, $token);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Password reset successful. You can now login with your new password.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token. Please request a new password reset.']);
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
    <title>Reset Password - Invoice & Billing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full space-y-8 p-10 bg-white rounded-xl shadow-lg">
            <div class="text-center">
                <h2 class="mt-6 text-3xl font-bold text-gray-900">
                    Reset Password
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    <?php echo $token ? 'Enter your new password' : 'Invalid or expired token'; ?>
                </p>
            </div>
            <?php if ($token): ?>
                <form id="resetPasswordForm" class="mt-8 space-y-6" method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                        <input id="password" name="password" type="password" required class="mt-1 appearance-none rounded relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" placeholder="Enter new password">
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                        <input id="confirm_password" name="confirm_password" type="password" required class="mt-1 appearance-none rounded relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" placeholder="Confirm new password">
                    </div>
                    <div>
                        <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Reset Password
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="mt-8 text-center">
                    <p class="text-red-500"><?php echo htmlspecialchars($error_message); ?></p>
                    <a href="forgot_password.php" class="mt-4 inline-block text-blue-600 hover:text-blue-800">Request New Password Reset</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="alert" class="fixed bottom-5 right-5 px-4 py-2 rounded-md shadow-lg hidden"></div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('resetPasswordForm');
        const alert = document.getElementById('alert');

        function showAlert(message, type) {
            alert.textContent = message;
            alert.classList.remove('hidden', 'bg-green-500', 'bg-red-500');
            alert.classList.add(type === 'success' ? 'bg-green-500' : 'bg-red-500', 'text-white');
            alert.classList.remove('hidden');
            setTimeout(() => {
                alert.classList.add('hidden');
            }, 3000);
        }

        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                if (password !== confirmPassword) {
                    showAlert('Passwords do not match', 'error');
                    return;
                }

                fetch(window.location.href, {
                    method: 'POST',
                    body: new FormData(this)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => {
                            window.location.href = 'index.php';
                        }, 3000);
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    showAlert('An error occurred. Please try again.', 'error');
                });
            });
        }
    });
    </script>
</body>
</html>