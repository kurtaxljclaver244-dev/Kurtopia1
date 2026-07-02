<?php
// admin_dashboard.php - Main Admin Dashboard
session_start();
require_once 'db.php';

if (!$conn->select_db('claver')) {
    die('Database selection failed: ' . $conn->error);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin_login.php');
    exit;
}

// Get statistics
$stats = [];

// Total products
$result = $conn->query('SELECT COUNT(*) as count FROM products WHERE is_active = 1');
$stats['total_products'] = $result->fetch_assoc()['count'];

// Total orders
$result = $conn->query('SELECT COUNT(*) as count FROM orders');
$stats['total_orders'] = $result->fetch_assoc()['count'];

// Total users
$result = $conn->query('SELECT COUNT(*) as count FROM users WHERE role = "customer"');
$stats['total_users'] = $result->fetch_assoc()['count'];

// Total revenue
$result = $conn->query('SELECT SUM(total_amount) as total FROM orders WHERE status != "cancelled"');
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Recent orders
$recent_orders = [];
$result = $conn->query('SELECT id, order_number, total_amount, status, created_at FROM orders ORDER BY created_at DESC LIMIT 5');
while ($row = $result->fetch_assoc()) {
    $recent_orders[] = $row;
}

// Low stock products
$low_stock = [];
$result = $conn->query('SELECT id, name, price, stock_quantity FROM products WHERE stock_quantity < 10 AND is_active = 1 ORDER BY stock_quantity ASC LIMIT 5');
while ($row = $result->fetch_assoc()) {
    $low_stock[] = $row;
}

// Monthly revenue data for chart
$monthly_revenue = [];
$result = $conn->query("
    SELECT DATE_FORMAT(created_at, '%b') as month, SUM(total_amount) as revenue 
    FROM orders 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    AND status != 'cancelled'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY created_at ASC
");
while ($row = $result->fetch_assoc()) {
    $monthly_revenue[] = $row;
}

function esc($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Kurtopia Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100%;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            color: white;
            transition: all 0.3s;
            z-index: 100;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
        }

        .sidebar-header p {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }

        .sidebar-nav {
            padding: 1.5rem 0;
        }

        .nav-item {
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.2s;
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .nav-item i {
            width: 24px;
            font-size: 1.1rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 1.5rem;
        }

        /* Header */
        .top-bar {
            background: white;
            border-radius: 16px;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logout-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 40px;
            cursor: pointer;
            font-size: 0.875rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .stat-info h3 {
            font-size: 0.875rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #1e293b;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #3b82f6;
        }

        /* Charts */
        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .chart-container h3 {
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        /* Two Column Layout */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .info-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .info-card h3 {
            margin-bottom: 1rem;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .order-item, .stock-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .order-item:last-child, .stock-item:last-child {
            border-bottom: none;
        }

        .order-status {
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .status-pending { background: #fef3c7; color: #d97706; }
        .status-processing { background: #dbeafe; color: #2563eb; }
        .status-shipped { background: #d1fae5; color: #059669; }
        .status-delivered { background: #d1fae5; color: #059669; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }

        .stock-warning {
            background: #fef3c7;
            color: #d97706;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .view-all {
            margin-top: 1rem;
            text-align: center;
        }

        .view-all a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 0.875rem;
        }

        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>✨ Kurtopia Shop</h2>
            <p>Admin Dashboard</p>
        </div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="nav-item active">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
            <a href="admin_products.php" class="nav-item">
                <i class="fas fa-box"></i> Products
            </a>
            <a href="admin_orders.php" class="nav-item">
                <i class="fas fa-shopping-cart"></i> Orders
            </a>
            <a href="admin_users.php" class="nav-item">
                <i class="fas fa-users"></i> Users
            </a>
            <a href="admin_categories.php" class="nav-item">
                <i class="fas fa-tags"></i> Categories
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <h1 class="page-title">Dashboard</h1>
            <div class="user-info">
                <span><i class="fas fa-user-shield"></i> <?php echo esc($_SESSION['username']); ?></span>
                <form method="post" action="logout.php" style="display: inline;">
                    <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
                </form>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Products</h3>
                    <div class="stat-number"><?php echo $stats['total_products']; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-box"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Orders</h3>
                    <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Customers</h3>
                    <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Revenue</h3>
                    <div class="stat-number">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
        </div>

        <!-- Revenue Chart -->
        <div class="chart-container">
            <h3><i class="fas fa-chart-line"></i> Revenue Overview (Last 6 Months)</h3>
            <canvas id="revenueChart" height="100"></canvas>
        </div>

        <!-- Two Columns -->
        <div class="two-columns">
            <!-- Recent Orders -->
            <div class="info-card">
                <h3><i class="fas fa-clock"></i> Recent Orders</h3>
                <?php if (empty($recent_orders)): ?>
                    <p style="color: #94a3b8; text-align: center;">No orders yet.</p>
                <?php else: ?>
                    <?php foreach ($recent_orders as $order): ?>
                        <div class="order-item">
                            <div>
                                <div style="font-weight: 600;">#<?php echo esc($order['order_number']); ?></div>
                                <div style="font-size: 0.75rem; color: #64748b;"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: 600;">$<?php echo number_format($order['total_amount'], 2); ?></div>
                                <span class="order-status status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="view-all">
                        <a href="admin_orders.php">View all orders →</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Low Stock Products -->
            <div class="info-card">
                <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Products</h3>
                <?php if (empty($low_stock)): ?>
                    <p style="color: #94a3b8; text-align: center;">All products have sufficient stock.</p>
                <?php else: ?>
                    <?php foreach ($low_stock as $product): ?>
                        <div class="stock-item">
                            <div>
                                <div style="font-weight: 600;"><?php echo esc($product['name']); ?></div>
                                <div style="font-size: 0.75rem; color: #64748b;">$<?php echo number_format($product['price'], 2); ?></div>
                            </div>
                            <div>
                                <span class="stock-warning">
                                    <i class="fas fa-box"></i> <?php echo $product['stock_quantity']; ?> left
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="view-all">
                        <a href="admin_products.php">Manage products →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_revenue); ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => item.month),
                datasets: [{
                    label: 'Revenue ($)',
                    data: monthlyData.map(item => parseFloat(item.revenue)),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    </script>
</body>
</html>