<?php
require_once 'auth_middleware.php';
require_once 'config.php';
require_once 'nav.php';

$user_id = requireAuth();

// Fetch current user data
$stmt = $conn->prepare("SELECT id, full_name, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();
$stmt->close();

// Check if the current user is an admin or accountant
$can_manage_clients = in_array($current_user['role'], ['admin', 'accountant']);

if (!$can_manage_clients) {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage_clients) {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'create_client':
                    // Validate input
                    $name = trim($_POST['name']);
                    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
                    $phone = trim($_POST['phone']);
                    $address = trim($_POST['address']);
                    
                    if (!$email) {
                        throw new Exception("Invalid email address");
                    }
                    
                    if (strlen($name) < 2) {
                        throw new Exception("Name is too short");
                    }
                    
                    // Check if email already exists
                    $stmt = $conn->prepare("SELECT id FROM clients WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception("A client with this email already exists");
                    }
                    $stmt->close();
                    
                    // Create client
                    $stmt = $conn->prepare("INSERT INTO clients (name, email, phone, address) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $name, $email, $phone, $address);
                    
                    if ($stmt->execute()) {
                        $message = "Client created successfully";
                        $message_type = "success";
                    } else {
                        throw new Exception("Failed to create client");
                    }
                    $stmt->close();
                    break;

                case 'edit_client':
                    $client_id = filter_var($_POST['client_id'], FILTER_VALIDATE_INT);
                    $name = trim($_POST['name']);
                    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
                    $phone = trim($_POST['phone']);
                    $address = trim($_POST['address']);
                    
                    if (!$client_id) {
                        throw new Exception("Invalid client ID");
                    }
                    
                    if (!$email) {
                        throw new Exception("Invalid email address");
                    }
                    
                    if (strlen($name) < 2) {
                        throw new Exception("Name is too short");
                    }
                    
                    // Check if email exists for other clients
                    $stmt = $conn->prepare("SELECT id FROM clients WHERE email = ? AND id != ?");
                    $stmt->bind_param("si", $email, $client_id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception("Another client with this email already exists");
                    }
                    $stmt->close();
                    
                    $stmt = $conn->prepare("UPDATE clients SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $name, $email, $phone, $address, $client_id);
                    
                    if ($stmt->execute()) {
                        $message = "Client updated successfully";
                        $message_type = "success";
                    } else {
                        throw new Exception("Failed to update client");
                    }
                    $stmt->close();
                    break;

                case 'delete_client':
                    $client_id = filter_var($_POST['client_id'], FILTER_VALIDATE_INT);
                    
                    if (!$client_id) {
                        throw new Exception("Invalid client ID");
                    }
                    
                    // Check if client has any invoices
                    $stmt = $conn->prepare("SELECT COUNT(*) as invoice_count FROM invoices WHERE client_id = ?");
                    $stmt->bind_param("i", $client_id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    if ($result['invoice_count'] > 0) {
                        throw new Exception("Cannot delete client with existing invoices");
                    }
                    $stmt->close();
                    
                    $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
                    $stmt->bind_param("i", $client_id);
                    
                    if ($stmt->execute()) {
                        $message = "Client deleted successfully";
                        $message_type = "success";
                    } else {
                        throw new Exception("Failed to delete client");
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

// Fetch all clients with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count
$total_clients = $conn->query("SELECT COUNT(*) as count FROM clients")->fetch_assoc()['count'];
$total_pages = ceil($total_clients / $per_page);

// Fetch clients for current page
$stmt = $conn->prepare("SELECT id, name, email, phone, address FROM clients ORDER BY name LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $per_page, $offset);
$stmt->execute();
$clients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
                <div class="px-4 py-6 sm:px-0">
                    <h1 class="text-2xl font-semibold text-gray-900">Client Management</h1>
                    
                    <?php if ($message): ?>
                    <div class="mt-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                    <?php endif; ?>

                    <!-- Add Client Form -->
                    <div class="mt-6 bg-white shadow-sm rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Add New Client</h3>
                            <form action="" method="POST" class="mt-5 grid grid-cols-1 gap-y-6 sm:grid-cols-2 sm:gap-x-8">
                                <input type="hidden" name="action" value="create_client">
                                
                                <div class="sm:col-span-2">
                                    <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                                    <input type="text" name="name" id="name" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                    <input type="email" name="email" id="email" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>

                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                                    <input type="tel" name="phone" id="phone"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                                    <textarea name="address" id="address" rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                                </div>

                                <div class="sm:col-span-2">
                                    <button type="submit"
                                        class="w-full inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                        Add Client
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Client List -->
                    <div class="mt-8">
                        <div class="sm:flex sm:items-center">
                            <div class="sm:flex-auto">
                                <h2 class="text-xl font-semibold text-gray-900">Clients</h2>
                                <p class="mt-2 text-sm text-gray-700">A list of all clients in the system.</p>
                            </div>
                        </div>
                        
                        <div class="mt-4 flex flex-col">
                            <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
                                <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                                    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                                        <table class="min-w-full divide-y divide-gray-300">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Name</th>
                                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Email</th>
                                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Phone</th>
                                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Address</th>
                                                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                                        <span class="sr-only">Actions</span>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 bg-white">
                                                <?php foreach ($clients as $client): ?>
                                                <tr>
                                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                                                        <?php echo htmlspecialchars($client['name']); ?>
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($client['email']); ?>
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($client['phone']); ?>
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($client['address']); ?>
                                                    </td>
                                                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                                        <button onclick="editClient(<?php echo htmlspecialchars(json_encode($client)); ?>)"
                                                            class="text-indigo-600 hover:text-indigo-900">Edit</button>
                                                        <button onclick="deleteClient(<?php echo $client['id']; ?>)"
                                                            class="ml-4 text-red-600 hover:text-red-900">Delete</button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="mt-4 flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
                            <div class="flex flex-1 justify-between sm:hidden">
                                <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</a>
                                <?php endif; ?>
                                <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</a>
                                <?php endif; ?>
                            </div>
                            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                                        <span class="font-medium"><?php echo min($offset + $per_page, $total_clients); ?></span> of
                                        <span class="font-medium"><?php echo $total_clients; ?></span> results
                                    </p>
                                </div>
                                <div>
                                    <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <a href="?page=<?php echo $i; ?>"
                                            class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo $i === $page ? 'bg-indigo-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:outline-offset-0'; ?>">
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
            </div>
        </main>
    </div>

    <!-- Edit Client Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                <div>
                    <h3 class="text-lg font-medium leading-6 text-gray-900">Edit Client</h3>
                    <form id="editForm" method="POST" class="mt-5">
                        <input type="hidden" name="action" value="edit_client">
                        <input type="hidden" name="client_id" id="edit_client_id">
                        
                        <div class="space-y-4">
                            <div>
                                <label for="edit_name" class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" name="name" id="edit_name" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="edit_email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" name="email" id="edit_email" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="edit_phone" class="block text-sm font-medium text-gray-700">Phone</label>
                                <input type="tel" name="phone" id="edit_phone"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="edit_address" class="block text-sm font-medium text-gray-700">Address</label>
                                <textarea name="address" id="edit_address" rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                            </div>
                        </div>

                        <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                            <button type="submit"
                                class="inline-flex w-full justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:col-start-2 sm:text-sm">
                                Save Changes
                            </button>
                            <button type="button" onclick="closeEditModal()"
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
    function editClient(client) {
        document.getElementById('edit_client_id').value = client.id;
        document.getElementById('edit_name').value = client.name;
        document.getElementById('edit_email').value = client.email;
        document.getElementById('edit_phone').value = client.phone;
        document.getElementById('edit_address').value = client.address;
        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    function deleteClient(clientId) {
        if (confirm('Are you sure you want to delete this client? This action cannot be undone.')) {
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

    // Close modal when clicking outside
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    });

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