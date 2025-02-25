<?php
require_once 'auth_middleware.php';
require_once 'config.php';
require_once 'nav.php';

$user_id = requireAuth();

// Fetch user data
$stmt = $conn->prepare("SELECT full_name, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Define role-based permissions
$permissions = [
    'admin' => ['view_all_stats', 'view_revenue', 'view_invoices', 'manage_users'],
    'accountant' => ['view_all_stats', 'view_revenue', 'view_invoices'],
    'client' => ['view_own_stats', 'view_own_invoices']
];

// Check if user has permission
function hasPermission($user_role, $permission) {
    global $permissions;
    return isset($permissions[$user_role]) && in_array($permission, $permissions[$user_role]);
}

// Initialize statistics
$stats = [
    'total_invoices' => 0,
    'pending_invoices' => 0,
    'total_revenue' => 0,
    'outstanding_amount' => 0
];

// Fetch statistics based on user role
try {
    if (hasPermission($user['role'], 'view_all_stats')) {
        // Admin and Accountant see all data
        $sql = "SELECT 
            COUNT(*) as total_invoices,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_invoices,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END), 0) as outstanding_amount
            FROM invoices";
        $result = $conn->query($sql);
        if ($result) {
            $stats = $result->fetch_assoc();
        }
    } else {
        // Clients only see their own data
        $stmt = $conn->prepare("SELECT 
            COUNT(*) as total_invoices,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_invoices,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END), 0) as outstanding_amount
            FROM invoices i
            JOIN clients c ON i.client_id = c.id
            WHERE c.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
} catch (Exception $e) {
    // Log error and continue with default values
    error_log("Error fetching statistics: " . $e->getMessage());
}

// Fetch recent invoices
$recentInvoices = [];
try {
    if (hasPermission($user['role'], 'view_all_stats')) {
        $sql = "SELECT 
            i.id, 
            i.invoice_number,
            c.name as client_name, 
            i.total_amount, 
            i.due_date, 
            i.status
            FROM invoices i
            JOIN clients c ON i.client_id = c.id
            ORDER BY i.created_at DESC LIMIT 5";
        $result = $conn->query($sql);
        if ($result) {
            $recentInvoices = $result->fetch_all(MYSQLI_ASSOC);
        }
    } else {
        $stmt = $conn->prepare("SELECT 
            i.id, 
            i.invoice_number,
            c.name as client_name, 
            i.total_amount, 
            i.due_date, 
            i.status
            FROM invoices i
            JOIN clients c ON i.client_id = c.id
            WHERE c.user_id = ?
            ORDER BY i.created_at DESC LIMIT 5");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $recentInvoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Error fetching recent invoices: " . $e->getMessage());
}

// Get monthly revenue data for chart
$monthlyRevenue = [];
try {
    if (hasPermission($user['role'], 'view_revenue')) {
        $sql = "SELECT 
            DATE_FORMAT(invoice_date, '%Y-%m') as month,
            SUM(total_amount) as revenue
            FROM invoices
            WHERE invoice_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
            ORDER BY month ASC";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $monthlyRevenue[$row['month']] = $row['revenue'];
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching monthly revenue: " . $e->getMessage());
}

// Get invoice status distribution for chart
$invoiceStatus = [
    'paid' => 0,
    'pending' => 0,
    'overdue' => 0
];
try {
    if (hasPermission($user['role'], 'view_revenue')) {
        $sql = "SELECT 
            status,
            COUNT(*) as count
            FROM invoices
            GROUP BY status";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $invoiceStatus[$row['status']] = $row['count'];
            }
        }
    }
} catch (Exception $e) {
    error_log("Error fetching invoice status: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Invoice & Billing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <?php echo getNavigation('dashboard', $user['role']); ?>

        <main class="flex-1 overflow-y-auto">
            <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <!-- Welcome message -->
                <div class="px-4 py-5 sm:px-6">
                    <h1 class="text-2xl font-semibold text-gray-900">
                        Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>
                    </h1>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">
                        <?php
                        switch($user['role']) {
                            case 'admin':
                                echo 'Here\'s an overview of all system activity';
                                break;
                            case 'accountant':
                                echo 'Here\'s an overview of all financial activity';
                                break;
                            default:
                                echo 'Here\'s an overview of your account';
                        }
                        ?>
                    </p>
                </div>

                <!-- Stats Grid -->
                <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Total Invoices -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            <?php echo $user['role'] === 'client' ? 'Your Invoices' : 'Total Invoices'; ?>
                                        </dt>
                                        <dd class="text-3xl font-semibold text-gray-900">
                                            <?php echo number_format($stats['total_invoices']); ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Invoices -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            Pending Invoices
                                        </dt>
                                        <dd class="text-3xl font-semibold text-gray-900">
                                            <?php echo number_format($stats['pending_invoices']); ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (hasPermission($user['role'], 'view_revenue')): ?>
                    <!-- Total Revenue -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            Total Revenue
                                        </dt>
                                        <dd class="text-3xl font-semibold text-gray-900">
                                            $<?php echo number_format($stats['total_revenue'], 2); ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Outstanding Amount -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">
                                            Outstanding Amount
                                        </dt>
                                        <dd class="text-3xl font-semibold text-gray-900">
                                            $<?php echo number_format($stats['outstanding_amount'], 2); ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (hasPermission($user['role'], 'view_revenue')): ?>
                <!-- Charts -->
                <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <!-- Revenue Chart -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Revenue Overview</h3>
                            <div class="mt-2">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Invoice Status Chart -->
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Invoice Status</h3>
                            <div class="mt-2">
                                <canvas id="invoiceStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Invoices -->
                <?php if (!empty($recentInvoices)): ?>
                <div class="mt-8">
                    <h2 class="text-lg leading-6 font-medium text-gray-900">
                        <?php echo $user['role'] === 'client' ? 'Your Recent Invoices' : 'Recent Invoices'; ?>
                    </h2>
                    <div class="mt-2 flex flex-col">
                        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                                <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Invoice #
                                                </th>
                                                <?php if ($user['role'] !== 'client'): ?>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Client
                                                </th>
                                                <?php endif; ?>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Amount
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Due Date
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($recentInvoices as $invoice): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                                </td>
                                                <?php if ($user['role'] !== 'client'): ?>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($invoice['client_name']); ?>
                                                </td>
                                                <?php endif; ?>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    $<?php echo number_format($invoice['total_amount'], 2); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($invoice['due_date']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        <?php 
                                                        echo match($invoice['status']) {
                                                            'paid' => 'bg-green-100 text-green-800',
                                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                                            'overdue' => 'bg-red-100 text-red-800',
                                                            default => 'bg-gray-100 text-gray-800'
                                                        };
                                                        ?>">
                                                        <?php echo htmlspecialchars(ucfirst($invoice['status'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');

    mobileMenuButton.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
    });

    <?php if (hasPermission($user['role'], 'view_revenue')): ?>
    // Revenue Chart
    const monthlyRevenueData = <?php echo json_encode(array_values($monthlyRevenue)); ?>;
    const monthLabels = <?php echo json_encode(array_map(function($m) { 
        return date('M Y', strtotime($m . '-01')); 
    }, array_keys($monthlyRevenue))); ?>;

    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: monthLabels,
            datasets: [{
                label: 'Revenue',
                data: monthlyRevenueData,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // Invoice Status Chart
    const statusData = <?php echo json_encode(array_values($invoiceStatus)); ?>;
    const statusLabels = <?php echo json_encode(array_map('ucfirst', array_keys($invoiceStatus))); ?>;

    const statusCtx = document.getElementById('invoiceStatusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: [
                    'rgb(75, 192, 192)',
                    'rgb(255, 205, 86)',
                    'rgb(255, 99, 132)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
    <?php endif; ?>
    </script>
    <script src="assets/js/nav.js"></script>
</body>
</html>