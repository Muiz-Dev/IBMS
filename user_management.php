<?php
require_once 'auth_middleware.php';
require_once 'config.php';
require_once 'nav.php';

$user_id = requireAuth();

// Fetch current user data
$stmt = $conn->prepare("SELECT id, full_name, email, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();
$stmt->close();

// Check if the current user is an admin
$is_admin = ($current_user['role'] === 'admin');

if (!$is_admin) {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin) {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'create_user':
                    // Validate input
                    $full_name = trim($_POST['full_name']);
                    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
                    $password = $_POST['password'];
                    $role = $_POST['role'];
                    
                    if (!$email) {
                        throw new Exception("Invalid email address");
                    }
                    
                    if (strlen($password) < 8) {
                        throw new Exception("Password must be at least 8 characters long");
                    }
                    
                    if (!in_array($role, ['admin', 'accountant', 'client'])) {
                        throw new Exception("Invalid role selected");
                    }
                    
                    // Check if email already exists
                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception("A user with this email already exists");
                    }
                    $stmt->close();
                    
                    // Create user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $verification_token = bin2hex(random_bytes(16));
                    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, verification_token) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $full_name, $email, $hashed_password, $role, $verification_token);
                    
                    if ($stmt->execute()) {
                        $new_user_id = $conn->insert_id;
                        
                        // Log the action
                        $action = "Created new user: $full_name ($role)";
                        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, created_at) VALUES (?, ?, NOW())");
                        $stmt->bind_param("is", $user_id, $action);
                        $stmt->execute();
                        
                        // Send welcome email with verification link
                        $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/verify.php?token=$verification_token";
                        $subject = "Welcome to " . COMPANY_NAME . " - Verify Your Email";
                        $body = "
                            <html>
                            <body>
                                <h2>Welcome to " . COMPANY_NAME . "!</h2>
                                <p>Hello $full_name,</p>
                                <p>Your account has been created successfully.</p>
                                <p><strong>Login Email:</strong> $email<br>
                                <strong>Temporary Password:</strong> $password</p>
                                <p>Please verify your email address by clicking the button below:</p>
                                <p>
                                    <a href='$verification_link' style='background-color: #4CAF50; border: none; color: white; padding: 15px 32px; text-align: center; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer;'>Verify Email</a>
                                </p>
                                <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                                <p>$verification_link</p>
                                <p>Please change your password after first login.</p>
                                <p>Best regards,<br>" . COMPANY_NAME . "</p>
                            </body>
                            </html>
                        ";
                        
                        if (sendEmail($email, $subject, $body)) {
                            $message = "User created successfully. A welcome email with verification link has been sent.";
                        } else {
                            $message = "User created successfully, but failed to send welcome email.";
                        }
                        $message_type = "success";
                    } else {
                        throw new Exception("Failed to create user");
                    }
                    $stmt->close();
                    break;

                case 'update_user':
                    $user_id_to_update = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
                    $full_name = trim($_POST['full_name']);
                    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
                    $role = $_POST['role'];
                    $status = $_POST['status'];
                    
                    if (!$user_id_to_update) {
                        throw new Exception("Invalid user ID");
                    }
                    
                    if (!$email) {
                        throw new Exception("Invalid email address");
                    }
                    
                    // Check if email exists for other users
                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->bind_param("si", $email, $user_id_to_update);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception("Another user with this email already exists");
                    }
                    $stmt->close();
                    
                    // Update user
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, status = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $full_name, $email, $role, $status, $user_id_to_update);
                    
                    if ($stmt->execute()) {
                        // Log the action
                        $action = "Updated user: $full_name ($role)";
                        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, created_at) VALUES (?, ?, NOW())");
                        $stmt->bind_param("is", $user_id, $action);
                        $stmt->execute();
                        
                        $message = "User updated successfully";
                        $message_type = "success";
                    } else {
                        throw new Exception("Failed to update user");
                    }
                    $stmt->close();
                    break;

                case 'delete_user':
                    $user_id_to_delete = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
                    
                    if (!$user_id_to_delete) {
                        throw new Exception("Invalid user ID");
                    }
                    
                    // Check if user exists and get their info for logging
                    $stmt = $conn->prepare("SELECT full_name, role FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id_to_delete);
                    $stmt->execute();
                    $user_info = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if (!$user_info) {
                        throw new Exception("User not found");
                    }
                    
                    // Delete user
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id_to_delete);
                    
                    if ($stmt->execute()) {
                        // Log the action
                        $action = "Deleted user: {$user_info['full_name']} ({$user_info['role']})";
                        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, created_at) VALUES (?, ?, NOW())");
                        $stmt->bind_param("is", $user_id, $action);
                        $stmt->execute();
                        
                        $message = "User deleted successfully";
                        $message_type = "success";
                    } else {
                        throw new Exception("Failed to delete user");
                    }
                    $stmt->close();
                    break;

                case 'reset_password':
                    $user_id_to_reset = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
                    
                    if (!$user_id_to_reset) {
                        throw new Exception("Invalid user ID");
                    }
                    
                    // Generate temporary password
                    $temp_password = bin2hex(random_bytes(8));
                    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                    
                    // Update password
                    $stmt = $conn->prepare("UPDATE users SET password = ?, password_reset_required = 1 WHERE id = ?");
                    $stmt->bind_param("si", $hashed_password, $user_id_to_reset);
                    
                    if ($stmt->execute()) {
                        // Get user email
                        $stmt = $conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
                        $stmt->bind_param("i", $user_id_to_reset);
                        $stmt->execute();
                        $user_info = $stmt->get_result()->fetch_assoc();
                        
                        // Send password reset email
                        $subject = "Password Reset - " . COMPANY_NAME;
                        $body = "
                            <html>
                            <body>
                                <h2>Password Reset</h2>
                                <p>Hello {$user_info['full_name']},</p>
                                <p>Your password has been reset by an administrator.</p>
                                <p><strong>Temporary Password:</strong> $temp_password</p>
                                <p>Please change your password after logging in.</p>
                                <p>Best regards,<br>" . COMPANY_NAME . "</p>
                            </body>
                            </html>
                        ";
                        
                        if (sendEmail($user_info['email'], $subject, $body)) {
                            $message = "Password reset successfully. A temporary password has been sent to the user's email.";
                        } else {
                            $message = "Password reset successfully, but failed to send email notification.";
                        }
                        
                        // Log the action
                        $action = "Reset password for user: {$user_info['full_name']}";
                        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, created_at) VALUES (?, ?, NOW())");
                        $stmt->bind_param("is", $user_id, $action);
                        $stmt->execute();
                        
                        $message_type = "success";
                    } else {
                        throw new Exception("Failed to reset password");
                    }
                    $stmt->close();
                    break;
                    
                case 'resend_verification':
                    $user_id_to_verify = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
                    
                    if (!$user_id_to_verify) {
                        throw new Exception("Invalid user ID");
                    }
                    
                    // Get user info
                    $stmt = $conn->prepare("SELECT email, full_name, email_verified FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id_to_verify);
                    $stmt->execute();
                    $user_info = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if (!$user_info) {
                        throw new Exception("User not found");
                    }
                    
                    if ($user_info['email_verified']) {
                        throw new Exception("This user's email is already verified");
                    }
                    
                    // Generate new verification token
                    $verification_token = bin2hex(random_bytes(16));
                    
                    // Update user with new token
                    $stmt = $conn->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
                    $stmt->bind_param("si", $verification_token, $user_id_to_verify);
                    
                    if ($stmt->execute()) {
                        // Send verification email
                        $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/verify.php?token=$verification_token";
                        $subject = "Verify Your Email - " . COMPANY_NAME;
                        $body = "
                            <html>
                            <body>
                                <h2>Email Verification</h2>
                                <p>Hello {$user_info['full_name']},</p>
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
                        
                        if (sendEmail($user_info['email'], $subject, $body)) {
                            $message = "Verification email has been resent to the user.";
                        } else {
                            $message = "Failed to send verification email. Please try again.";
                        }
                        
                        // Log the action
                        $action = "Resent verification email to user: {$user_info['full_name']}";
                        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, created_at) VALUES (?, ?, NOW())");
                        $stmt->bind_param("is", $user_id, $action);
                        $stmt->execute();
                        
                        $message_type = "success";
                    } else {
                        throw new Exception("Failed to update verification token");
                    }
                    $stmt->close();
                    break;
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $message_type = "error";
        }
    }
}

// Fetch users with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_pages = ceil($total_users / $per_page);

// Fetch users for current page
$stmt = $conn->prepare("
    SELECT u.*, 
           COUNT(al.id) as activity_count,
           MAX(al.created_at) as last_activity
    FROM users u
    LEFT JOIN activity_logs al ON u.id = al.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $per_page, $offset);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch recent activity logs
$stmt = $conn->prepare("
    SELECT al.*, u.full_name
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Invoice & Billing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <?php echo getNavigation('users', $current_user['role']); ?>

        <main class="flex-1 overflow-y-auto">
            <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <div class="px-4 py-6 sm:px-0">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-semibold text-gray-900">User Management</h1>
                        <?php if ($is_admin): ?>
                        <button onclick="showCreateUserModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            Add New User
                        </button>
                        <?php endif; ?>
                    </div>

                    <?php if ($message): ?>
                    <div class="mb-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- User List -->
                        <div class="lg:col-span-2">
                            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Activity</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <span class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center">
                                                            <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                                                        </span>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            <?php echo htmlspecialchars($user['email']); ?>
                                                            <?php if (!$user['email_verified']): ?>
                                                            <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                                Unverified
                                                            </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo match($user['role']) {
                                                        'admin' => 'bg-purple-100 text-purple-800',
                                                        'accountant' => 'bg-blue-100 text-blue-800',
                                                        'client' => 'bg-green-100 text-green-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    }; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php echo $user['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $user['last_activity'] ? date('M j, Y g:i A', strtotime($user['last_activity'])) : 'Never'; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                                    class="text-indigo-600 hover:text-indigo-900">Edit</button>
                                                <button onclick="resetPassword(<?php echo $user['id']; ?>)"
                                                    class="ml-4 text-yellow-600 hover:text-yellow-900">Reset Password</button>
                                                <?php if (!$user['email_verified']): ?>
                                                <button onclick="resendVerification(<?php echo $user['id']; ?>)"
                                                    class="ml-4 text-blue-600 hover:text-blue-900">Resend Verification</button>
                                                <?php endif; ?>
                                                <?php if ($user['id'] !== $current_user['id']): ?>
                                                <button onclick="deleteUser(<?php echo $user['id']; ?>)"
                                                    class="ml-4 text-red-600 hover:text-red-900">Delete</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                                    <div class="flex-1 flex justify-between sm:hidden">
                                        <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                                        <?php endif; ?>
                                        <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-sm text-gray-700">
                                                Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                                                <span class="font-medium"><?php echo min($offset + $per_page, $total_users); ?></span> of
                                                <span class="font-medium"><?php echo $total_users; ?></span> users
                                            </p>
                                        </div>
                                        <div>
                                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <a href="?page=<?php echo $i; ?>"
                                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50
                                                        <?php echo $i === $page ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600' : ''; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                                <?php endfor; ?>
                                            </nav>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="lg:col-span-1">
                            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                                <div class="px-4 py-5 sm:px-6">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Activity</h3>
                                </div>
                                <div class="border-t border-gray-200">
                                    <ul class="divide-y divide-gray-200">
                                        <?php foreach ($recent_activities as $activity): ?>
                                        <li class="px-4 py-3">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($activity['action']); ?></div>
                                            <div class="text-xs text-gray-500">
                                                by <?php echo htmlspecialchars($activity['full_name']); ?> •
                                                <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create User Modal -->
    <div id="createUserModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                <div>
                    <h3 class="text-lg font-medium leading-6 text-gray-900">Create New User</h3>
                    <form id="createUserForm" method="POST" class="mt-5">
                        <input type="hidden" name="action" value="create_user">
                        
                        <div class="space-y-4">
                            <div>
                                <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                                <input type="text" name="full_name" id="full_name" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" name="email" id="email" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                                <input type="password" name="password" id="password" required minlength="8"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                                <select name="role" id="role" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="admin">Admin</option>
                                    <option value="accountant">Accountant</option>
                                    <option value="client">Client</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                            <button type="submit"
                                class="inline-flex w-full justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:col-start-2 sm:text-sm">
                                Create User
                            </button>
                            <button type="button" onclick="closeModal('createUserModal')"
                                class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:col-start-1 sm:mt-0 sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                <div>
                    <h3 class="text-lg font-medium leading-6 text-gray-900">Edit User</h3>
                    <form id="editUserForm" method="POST" class="mt-5">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="space-y-4">
                            <div>
                                <label for="edit_full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                                <input type="text" name="full_name" id="edit_full_name" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="edit_email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" name="email" id="edit_email" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="edit_role" class="block text-sm font-medium text-gray-700">Role</label>
                                <select name="role" id="edit_role" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="admin">Admin</option>
                                    <option value="accountant">Accountant</option>
                                    <option value="client">Client</option>
                                </select>
                            </div>

                            <div>
                                <label for="edit_status" class="block text-sm font-medium text-gray-700">Status</label>
                                <select name="status" id="edit_status" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                            <button type="submit"
                                class="inline-flex w-full justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:col-start-2 sm:text-sm">
                                Save Changes
                            </button>
                            <button type="button" onclick="closeModal('editUserModal')"
                                class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:col-start-1 sm:mt-0 sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function showCreateUserModal() {
        document.getElementById('createUserModal').classList.remove('hidden');
    }

    function editUser(user) {
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_full_name').value = user.full_name;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_role').value = user.role;
        document.getElementById('edit_status').value = user.status;
        document.getElementById('editUserModal').classList.remove('hidden');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }

    function resetPassword(userId) {
        if (confirm('Are you sure you want to reset this user\'s password? They will receive an email with their new temporary password.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" value="${userId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function resendVerification(userId) {
        if (confirm('Are you sure you want to resend the verification email to this user?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="resend_verification">
                <input type="hidden" name="user_id" value="${userId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function deleteUser(userId) {
        if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" value="${userId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const modals = document.getElementsByClassName('fixed inset-0');
        for (let modal of modals) {
            if (event.target === modal) {
                modal.classList.add('hidden');
            }
        }
    }

    // Handle form submission messages
    <?php if ($message): ?>
    setTimeout(() => {
        const messageDiv = document.querySelector('.bg-<?php echo $message_type === "success" ? "green" : "red"; ?>-50');
        if (messageDiv) {
            messageDiv.style.opacity = '0';
            messageDiv.style.transition = 'opacity 0.5s ease-out';
            setTimeout(() => messageDiv.remove(), 500);
        }
    }, 3000);
    <?php endif; ?>
    </script>
    <script src="assets/js/nav.js"></script>
</body>
</html>
