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
$can_manage_invoices = in_array($current_user['role'], ['admin', 'accountant']);

if (!$can_manage_invoices) {
    header("Location: dashboard.php");
    exit();
}

// Generate unique invoice number
function generateInvoiceNumber() {
    global $conn;
    $prefix = 'INV-';
    $year = date('Y');
    $month = date('m');
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM invoices WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?");
    $stmt->bind_param("ss", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $count = $result['count'] + 1;
    $stmt->close();
    
    return $prefix . $year . $month . str_pad($count, 4, '0', STR_PAD_LEFT);
}

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage_invoices) {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'create_invoice':
                    $client_id = filter_var($_POST['client_id'], FILTER_VALIDATE_INT);
                    $invoice_date = $_POST['invoice_date'];
                    $due_date = $_POST['due_date'];
                    $items = json_decode($_POST['invoice_items'], true);
                    $notes = trim($_POST['notes']);
                    $status = 'draft';
                    
                    if (!$client_id) {
                        throw new Exception("Invalid client selection");
                    }
                    
                    if (empty($items)) {
                        throw new Exception("Invoice must have at least one item");
                    }
                    
                    // Calculate totals
                    $subtotal = 0;
                    foreach ($items as $item) {
                        $subtotal += $item['quantity'] * $item['unit_price'];
                    }
                    
                    $tax_rate = 0.10; // 10% tax rate
                    $tax_amount = $subtotal * $tax_rate;
                    $total_amount = $subtotal + $tax_amount;
                    
                    // Start transaction
                    $conn->begin_transaction();
                    
                    // Create invoice
                    $invoice_number = generateInvoiceNumber();
                    $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, client_id, invoice_date, due_date, subtotal, tax_rate, tax_amount, total_amount, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sissddddss", $invoice_number, $client_id, $invoice_date, $due_date, $subtotal, $tax_rate, $tax_amount, $total_amount, $notes, $status);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to create invoice");
                    }
                    
                    $invoice_id = $conn->insert_id;
                    $stmt->close();
                    
                    // Insert invoice items
                    $stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, amount) VALUES (?, ?, ?, ?, ?)");
                    
                    foreach ($items as $item) {
                        $amount = $item['quantity'] * $item['unit_price'];
                        $stmt->bind_param("isddd", $invoice_id, $item['description'], $item['quantity'], $item['unit_price'], $amount);
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to add invoice item");
                        }
                    }
                    
                    $stmt->close();
                    $conn->commit();
                    
                    $message = "Invoice created successfully";
                    $message_type = "success";
                    break;

                case 'update_status':
                    $invoice_id = filter_var($_POST['invoice_id'], FILTER_VALIDATE_INT);
                    $new_status = $_POST['status'];
                    
                    if (!$invoice_id) {
                        throw new Exception("Invalid invoice ID");
                    }
                    
                    if (!in_array($new_status, ['draft', 'pending', 'paid', 'overdue', 'cancelled'])) {
                        throw new Exception("Invalid status");
                    }
                    
                    $stmt = $conn->prepare("UPDATE invoices SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_status, $invoice_id);
                    
                    if ($stmt->execute()) {
                        $message = "Invoice status updated successfully";
                        $message_type = "success";
                    } else {
                        throw new Exception("Failed to update invoice status");
                    }
                    $stmt->close();
                    break;

                case 'delete_invoice':
                    $invoice_id = filter_var($_POST['invoice_id'], FILTER_VALIDATE_INT);
                    
                    if (!$invoice_id) {
                        throw new Exception("Invalid invoice ID");
                    }
                    
                    // Check if invoice can be deleted (only draft invoices)
                    $stmt = $conn->prepare("SELECT status FROM invoices WHERE id = ?");
                    $stmt->bind_param("i", $invoice_id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if ($result['status'] !== 'draft') {
                        throw new Exception("Only draft invoices can be deleted");
                    }
                    
                    // Start transaction
                    $conn->begin_transaction();
                    
                    // Delete invoice items first
                    $stmt = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
                    $stmt->bind_param("i", $invoice_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to delete invoice items");
                    }
                    $stmt->close();
                    
                    // Delete invoice
                    $stmt = $conn->prepare("DELETE FROM invoices WHERE id = ?");
                    $stmt->bind_param("i", $invoice_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to delete invoice");
                    }
                    $stmt->close();
                    
                    $conn->commit();
                    
                    $message = "Invoice deleted successfully";
                    $message_type = "success";
                    break;
            }
        } catch (Exception $e) {
            if (isset($conn) && $conn->connect_errno) {
                $conn->rollback();
            }
            $message = $e->getMessage();
            $message_type = "error";
        }
    }
}

// Fetch all clients for the dropdown
$clients = [];
$stmt = $conn->prepare("SELECT id, name FROM clients ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $clients[] = $row;
}
$stmt->close();

// Fetch invoices with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count
$total_invoices = $conn->query("SELECT COUNT(*) as count FROM invoices")->fetch_assoc()['count'];
$total_pages = ceil($total_invoices / $per_page);

// Fetch invoices for current page
$stmt = $conn->prepare("
    SELECT i.*, c.name as client_name 
    FROM invoices i 
    JOIN clients c ON i.client_id = c.id 
    ORDER BY i.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $per_page, $offset);
$stmt->execute();
$invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Management - Invoice & Billing System</title>
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
        <?php echo getNavigation('invoices', $current_user['role']); ?>

        <main class="flex-1 overflow-y-auto">
            <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <div class="px-4 py-6 sm:px-0">
                    <h1 class="text-2xl font-semibold text-gray-900">Invoice Management</h1>
                    
                    <?php if ($message): ?>
                    <div class="mt-4 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                    <?php endif; ?>

                    <!-- Create Invoice Form -->
                    <div class="mt-6 bg-white shadow-sm rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium leading-6 text-gray-900">Create New Invoice</h3>
                            <form id="createInvoiceForm" action="" method="POST" class="mt-5">
                                <input type="hidden" name="action" value="create_invoice">
                                <input type="hidden" name="invoice_items" id="invoice_items">
                                
                                <div class="grid grid-cols-1 gap-y-6 sm:grid-cols-2 sm:gap-x-8">
                                    <div>
                                        <label for="client_id" class="block text-sm font-medium text-gray-700">Client</label>
                                        <select name="client_id" id="client_id" required
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <option value="">Select a client</option>
                                            <?php foreach ($clients as $client): ?>
                                            <option value="<?php echo $client['id']; ?>">
                                                <?php echo htmlspecialchars($client['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="invoice_date" class="block text-sm font-medium text-gray-700">Invoice Date</label>
                                        <input type="date" name="invoice_date" id="invoice_date" required
                                            value="<?php echo date('Y-m-d'); ?>"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>

                                    <div>
                                        <label for="due_date" class="block text-sm font-medium text-gray-700">Due Date</label>
                                        <input type="date" name="due_date" id="due_date" required
                                            value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>

                                <!-- Invoice Items -->
                                <div class="mt-6">
                                    <h4 class="text-sm font-medium text-gray-900">Invoice Items</h4>
                                    <div id="invoice_items_container" class="mt-4 space-y-4">
                                        <!-- Items will be added here dynamically -->
                                    </div>
                                    <button type="button" onclick="addInvoiceItem()"
                                        class="mt-4 inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        Add Item
                                    </button>
                                </div>

                                <div class="mt-6">
                                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                                    <textarea name="notes" id="notes" rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                                </div>

                                <!-- Totals -->
                                <div class="mt-6 bg-gray-50 p-4 rounded-md">
                                    <div class="flex justify-between text-sm">
                                        <span class="font-medium text-gray-700">Subtotal:</span>
                                        <span id="subtotal">$0.00</span>
                                    </div>
                                    <div class="flex justify-between text-sm mt-2">
                                        <span class="font-medium text-gray-700">Tax (10%):</span>
                                        <span id="tax">$0.00</span>
                                    </div>
                                    <div class="flex justify-between text-base mt-2 pt-2 border-t border-gray-200">
                                        <span class="font-medium text-gray-900">Total:</span>
                                        <span id="total" class="font-medium">$0.00</span>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <button type="submit"
                                        class="w-full inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                        Create Invoice
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Invoice List -->
                    <div class="mt-8">
                        <div class="sm:flex sm:items-center">
                            <div class="sm:flex-auto">
                                <h2 class="text-xl font-semibold text-gray-900">Invoices</h2>
                                <p class="mt-2 text-sm text-gray-700">A list of all invoices in the system.</p>
                            </div>
                        </div>
                        
                        <div class="mt-4 flex flex-col">
                            <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
                                <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                                    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                                        <table class="min-w-full divide-y divide-gray-300">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Invoice #</th>
                                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Client</th>
                                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Date</th>
                                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Due Date</th>
                                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Amount</th>
                                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                                                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                                        <span class="sr-only">Actions</span>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 bg-white">
                                                <?php foreach ($invoices as $invoice): ?>
                                                <tr>
                                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                                                        <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($invoice['client_name']); ?>
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($invoice['invoice_date']); ?>
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($invoice['due_date']); ?>
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                        $<?php echo number_format($invoice['total_amount'], 2); ?>
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-4 text-sm">
                                                        <span class="inline-flex rounded-full px-2 text-xs font-semibold leading-5 
                                                            <?php
                                                            echo match($invoice['status']) {
                                                                'paid' => 'bg-green-100 text-green-800',
                                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                                'overdue' => 'bg-red-100 text-red-800',
                                                                'draft' => 'bg-gray-100 text-gray-800',
                                                                'cancelled' => 'bg-gray-100 text-gray-800',
                                                                default => 'bg-gray-100 text-gray-800'
                                                            };
                                                            ?>">
                                                            <?php echo ucfirst(htmlspecialchars($invoice['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                                        <div class="flex justify-end space-x-4">
                                                            <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>"
                                                                class="text-indigo-600 hover:text-indigo-900">View</a>
                                                            <?php if ($invoice['status'] === 'draft'): ?>
                                                            <button onclick="deleteInvoice(<?php echo $invoice['id']; ?>)"
                                                                class="text-red-600 hover:text-red-900">Delete</button>
                                                            <?php endif; ?>
                                                            <?php if (in_array($invoice['status'], ['draft', 'pending'])): ?>
                                                            <button onclick="updateStatus(<?php echo $invoice['id']; ?>, 'paid')"
                                                                class="text-green-600 hover:text-green-900">Mark as Paid</button>
                                                            <?php endif; ?>
                                                        </div>
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
                                        <span class="font-medium"><?php echo min($offset + $per_page, $total_invoices); ?></span> of
                                        <span class="font-medium"><?php echo $total_invoices; ?></span> results
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

    <script>
    let invoiceItems = [];
    const TAX_RATE = 0.10; // 10%

    function addInvoiceItem() {
        const itemId = Date.now();
        const itemHtml = `
            <div id="item_${itemId}" class="grid grid-cols-12 gap-4">
                <div class="col-span-5">
                    <input type="text" placeholder="Description" required
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        onchange="updateItem(${itemId}, 'description', this.value)">
                </div>
                <div class="col-span-2">
                    <input type="number" placeholder="Quantity" required min="1" step="1"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        onchange="updateItem(${itemId}, 'quantity', this.value)">
                </div>
                <div class="col-span-2">
                    <input type="number" placeholder="Unit Price" required min="0" step="0.01"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        onchange="updateItem(${itemId}, 'unit_price', this.value)">
                </div>
                <div class="col-span-2">
                    <input type="text" readonly value="$0.00"
                        class="block w-full rounded-md border-gray-300 bg-gray-50 shadow-sm sm:text-sm">
                </div>
                <div class="col-span-1">
                    <button type="button" onclick="removeItem(${itemId})"
                        class="inline-flex items-center p-1 border border-transparent rounded-full shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        `;
        
        document.getElementById('invoice_items_container').insertAdjacentHTML('beforeend', itemHtml);
        invoiceItems.push({
            id: itemId,
            description: '',
            quantity: 0,
            unit_price: 0
        });
    }

    function updateItem(itemId, field, value) {
        const item = invoiceItems.find(item => item.id === itemId);
        if (item) {
            item[field] = field === 'description' ? value : parseFloat(value) || 0;
            updateItemTotal(itemId);
            updateTotals();
        }
    }

    function updateItemTotal(itemId) {
        const item = invoiceItems.find(item => item.id === itemId);
        if (item) {
            const total = item.quantity * item.unit_price;
            const itemElement = document.getElementById(`item_${itemId}`);
            const totalInput = itemElement.querySelector('input[readonly]');
            totalInput.value = formatCurrency(total);
        }
    }

    function removeItem(itemId) {
        const itemElement = document.getElementById(`item_${itemId}`);
        itemElement.remove();
        invoiceItems = invoiceItems.filter(item => item.id !== itemId);
        updateTotals();
    }

    function updateTotals() {
        const subtotal = invoiceItems.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
        const tax = subtotal * TAX_RATE;
        const total = subtotal + tax;

        document.getElementById('subtotal').textContent = formatCurrency(subtotal);
        document.getElementById('tax').textContent = formatCurrency(tax);
        document.getElementById('total').textContent = formatCurrency(total);
    }

    function formatCurrency(amount) {
        return '$' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    // Form submission
    document.getElementById('createInvoiceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (invoiceItems.length === 0) {
            alert('Please add at least one item to the invoice');
            return;
        }
        
        document.getElementById('invoice_items').value = JSON.stringify(invoiceItems);
        this.submit();
    });

    // Add initial item
    addInvoiceItem();

    function updateStatus(invoiceId, newStatus) {
        if (confirm('Are you sure you want to update this invoice\'s status?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="invoice_id" value="${invoiceId}">
                <input type="hidden" name="status" value="${newStatus}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function deleteInvoice(invoiceId) {
        if (confirm('Are you sure you want to delete this invoice? This action cannot be undone.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_invoice">
                <input type="hidden" name="invoice_id" value="${invoiceId}">
            `;
            document.body.appendChild(form);
            form.submit();
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