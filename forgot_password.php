<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $reset_token = bin2hex(random_bytes(16));
    $reset_token_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
    $stmt->bind_param("sss", $reset_token, $reset_token_expires, $email);
    
    if ($stmt->execute()) {
        $reset_link = "http://yourdomain.com/reset_password.php?token=$reset_token";
        $subject = "Reset Your Password";
        $body = "Click the following link to reset your password: $reset_link";
        
        if (sendEmail($email, $subject, $body)) {
            echo json_encode(['success' => true, 'message' => 'Password reset instructions sent to your email.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send password reset email. Please try again.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// ... (rest of the HTML code remains the same)
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Invoice & Billing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md max-w-md w-full">
        <h1 class="text-2xl font-bold mb-4">Forgot Password</h1>
        <form id="forgotPasswordForm" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                <input type="email" id="email" name="email" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            </div>
            <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Send Reset Link
            </button>
        </form>
        <div class="mt-4 text-center">
            <a href="index.php" class="text-sm text-indigo-600 hover:text-indigo-500">Back to Login</a>
        </div>
    </div>

    <div id="alert" class="fixed bottom-5 right-5 px-4 py-2 rounded-md shadow-lg hidden"></div>

    <script>
        const form = document.getElementById('forgotPasswordForm');
        const alert = document.getElementById('alert');

        function showAlert(message, type) {
            alert.textContent = message;
            alert.classList.remove('hidden', 'bg-green-500', 'bg-red-500');
            alert.classList.add(type === 'success' ? 'bg-green-500' : 'bg-red-500', 'text-white');
            setTimeout(() => alert.classList.add('hidden'), 5000);
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
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
            }
        });
    </script>
</body>
</html>