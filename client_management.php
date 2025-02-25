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

// Check if the current user is an admin or accountant
$can_manage_clients = in_array($current_user['role'], ['admin', 'accountant']);

if (!$can_manage_clients) {
    // Redirect users who can't manage clients to the dashboard
    header("Location: dashboard.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage_clients) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_client':
                // Create client logic
                $name = $_POST['name'];
                $email = $_POST['email'];
                $phone = $_POST['phone'];
                $address = $_POST['address'];
                
                $stmt = $conn->prepare("INSERT INTO clients (name, email, phone, address) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $name, $email, $phone, $address);
                $stmt->execute();
                $stmt->close();
                break;
            case 'edit_client':
                // Edit client logic
                $client_id = $_POST['client_id'];
                $name = $_POST['name'];
                $email = $_POST['email'];
                $phone = $_POST['phone'];
                $address = $_POST['address'];
                
                $stmt = $conn->prepare("UPDATE clients SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $name, $email, $phone, $address, $client_id);
                $stmt->execute();
                $stmt->close();
                break;
            case 'delete_client':
                // Delete client logic
                $client_id = $_POST['client_id'];
                
                $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
                $stmt->bind_param("i", $client_id);
                $stmt->execute();
                $stmt->close();
                break;
        }
    }
}

// Fetch all clients
$stmt = $conn->prepare("SELECT id, name, email, phone, address FROM clients");
$stmt->execute();
$result = $stmt->get_result();
$clients = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Management - Invoice & Billing System</title>
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
        <?php echo getNavigation('clients', $current_user['role']); ?>

        <main class="flex-1 overflow-y-auto">
            <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <h1 class="text-2xl font-semibold text-gray-900 mb-6">Client Management</h1>

                <?php if ($can_manage_clients): ?>
                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-2">Add New Client</h2>
                        <form action="" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="create_client">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" name="name" id="name" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" name="email" id="email" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                                <input type="tel" name="phone" id="phone" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                            <div>
                                <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                                <textarea name="address" id="address" rows="3" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
                            </div>
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded">Add Client</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div>
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Client List</h2>
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                    <?php if ($can_manage_clients): ?>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($client['name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($client['email']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($client['phone']); ?></td>
                                        <?php if ($can_manage_clients): ?>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="#" class="text-indigo-600 hover:text-indigo-900" onclick="editClient(<?php echo $client['id']; ?>)">Edit</a>
                                                <a href="#" class="text-red-600 hover:text-red-900 ml-4" onclick="deleteClient(<?php echo $client['id']; ?>)">Delete</a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Toggle mobile menu
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');

        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });

        function editClient(clientId) {
            // Implement edit client functionality
            console.log('Edit client:', clientId);
        }

        function deleteClient(clientId) {
            if (confirm('Are you sure you want to delete this client?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_client">
                    <input type="hidden" name="client_id" value="${clientId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>