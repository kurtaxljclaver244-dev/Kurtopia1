<?php
session_start();
require_once 'db.php';

if (!$conn->select_db('claver')) {
    die('Database selection failed: ' . $conn->error);
}

if (!isset($_SESSION['user_id'])) {
    header('Location: signup.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$orders = [];

$stmt = $conn->prepare('SELECT id, status, total_amount, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC');
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
}

$is_logged_in = true;

function esc($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | Kurtopia Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1f2937;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        header {
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2563eb;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .nav-link {
            color: #4b5563;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-link:hover {
            color: #2563eb;
        }

        .cart-link {
            position: relative;
            color: #4b5563;
            text-decoration: none;
            font-weight: 500;
        }

        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc2626;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
        }

        main {
            padding: 2rem 0;
        }

        .page-title {
            text-align: center;
            margin-bottom: 2rem;
            font-size: 2.5rem;
            font-weight: 700;
            color: #1f2937;
        }

        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .order-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .order-id {
            font-weight: 600;
            color: #2563eb;
        }

        .order-status {
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .order-status.pending {
            background: #fef3c7;
            color: #d97706;
        }

        .order-status.completed {
            background: #d1fae5;
            color: #065f46;
        }

        .order-status.cancelled {
            background: #fee2e2;
            color: #dc2626;
        }

        .order-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-total {
            font-size: 1.25rem;
            font-weight: 700;
            color: #2563eb;
        }

        .order-date {
            color: #6b7280;
        }

        .no-orders {
            text-align: center;
            font-size: 1.125rem;
            color: #6b7280;
            padding: 2rem;
        }

        footer {
            background: #1f2937;
            color: white;
            padding: 2rem 0;
            margin-top: 4rem;
        }

        .footer-content {
            text-align: center;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1rem;
        }

        .footer-link {
            color: #d1d5db;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .footer-link:hover {
            color: white;
        }

        .copyright {
            font-size: 0.8125rem;
            color: #9ca3af;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .order-details {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">Kurtopia Shop</a>
                <nav class="nav-links">
                    <a href="index.php" class="nav-link">Home</a>
                    <a href="products.php" class="nav-link">Products</a>
                    <a href="orders.php" class="nav-link">My Orders</a>
                    <a href="user_settings.php" class="nav-link">Settings</a>
                    <a href="wishlist.php" class="nav-link">Wishlist</a>
                    <a href="logout.php" class="nav-link">Logout</a>
                    <a href="index.php" class="cart-link">
                        <i class="fas fa-shopping-cart"></i> Cart
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <h1 class="page-title">My Orders</h1>

        <?php if (empty($orders)): ?>
            <p class="no-orders">You haven't placed any orders yet.</p>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-id">Order #<?php echo esc($order['id']); ?></div>
                            <div class="order-status <?php echo esc($order['status']); ?>">
                                <?php echo esc(ucfirst($order['status'])); ?>
                            </div>
                        </div>
                        <div class="order-details">
                            <div class="order-total">$<?php echo number_format($order['total_amount'], 2); ?></div>
                            <div class="order-date"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-links">
                    <a href="#" class="footer-link">Home</a>
                    <a href="products.php" class="footer-link">Products</a>
                    <a href="signup.php" class="footer-link">Account</a>
                </div>
                <p class="copyright">&copy; 2026 Kurtopia Shop. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>