<?php
// admin_orders.php - Order Management
session_start();
require_once 'db.php';

if (!$conn->select_db('claver')) {
    die('Database selection failed: ' . $conn->error);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin_login.php');
    exit;
}

$message = '';

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $status = $_POST['status'];
    
    $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('si', $status, $order_id);
        if ($stmt->execute()) {
            $message = 'Order status updated successfully!';
        }
        $stmt->close();
    }
}

// Fetch all orders with user info
$orders = [];
$query = "
    SELECT o.*, u.username, u.email 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC
";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
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
    <title>Order Management | Kurtopia Shop Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100%;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            color: white;
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
        }

        .main-content {
            margin-left: 280px;
            padding: 1.5rem;
        }

        .top-bar {
            background: white;
            border-radius: 16px;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .logout-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 40px;
            cursor: pointer;
        }

        .message {
            background: #d1fae5;
            color: #065f46;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .orders-table {
            background: white;
            border-radius: 20px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
        }

        .status-select {
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .btn-update {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75rem;
        }

        .order-status {
            display: inline-block;
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

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>✨ Kurtopia Shop</h2>
            <p>Admin Dashboard</p>
        </div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="nav-item">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
            <a href="admin_products.php" class="nav-item">
                <i class="fas fa-box"></i> Products
            </a>
            <a href="admin_orders.php" class="nav-item active">
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

    <main class="main-content">
        <div class="top-bar">
            <h1 class="page-title">Order Management</h1>
            <form method="post" action="logout.php" style="display: inline;">
                <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </form>
        </div>

        <?php if ($message): ?>
            <div class="message"><?php echo esc($message); ?></div>
        <?php endif; ?>

        <div class="orders-table">
            <table>
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Total</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong><?php echo esc($order['order_number']); ?></strong></td>
                            <td><?php echo esc($order['username'] ?? 'Guest'); ?></td>
                            <td><?php echo esc($order['email'] ?? 'N/A'); ?></td>
                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                            <td>
                                <span class="order-status status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td>
                                <form method="post" style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <select name="status" class="status-select">
                                        <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn-update">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>