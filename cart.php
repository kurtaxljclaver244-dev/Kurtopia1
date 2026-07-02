<?php
session_start();
require_once 'db.php';

if (!$conn->select_db('claver')) {
    die('Database selection failed: ' . $conn->error);
}

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$session_id = session_id();
$cart = [];
$cart_total = 0;
$cart_id = null;

// Get or create cart
if ($is_logged_in) {
    // For logged-in users, ensure session exists in sessions table
    $stmt = $conn->prepare('INSERT INTO sessions (session_id, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), last_activity = CURRENT_TIMESTAMP');
    if ($stmt) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt->bind_param('siss', $session_id, $user_id, $ip, $user_agent);
        $stmt->execute();
        $stmt->close();
    }

    // Get their active cart
    $stmt = $conn->prepare('SELECT id FROM carts WHERE user_id = ? AND status = "active" ORDER BY created_at DESC LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $cart_id = $row['id'];
        }
        $stmt->close();
    }
} else {
    // For guests, ensure session exists in sessions table first
    $stmt = $conn->prepare('INSERT IGNORE INTO sessions (session_id, user_id, ip_address, user_agent) VALUES (?, NULL, ?, ?)');
    if ($stmt) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt->bind_param('sss', $session_id, $ip, $user_agent);
        $stmt->execute();
        $stmt->close();
    }

    // Then get cart by session_id
    $stmt = $conn->prepare('SELECT id FROM carts WHERE session_id = ? AND status = "active" ORDER BY created_at DESC LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $session_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $cart_id = $row['id'];
        }
        $stmt->close();
    }
}

// Create cart if it doesn't exist
if (!$cart_id) {
    $stmt = $conn->prepare('INSERT INTO carts (session_id, user_id, status) VALUES (?, ?, "active")');
    if ($stmt) {
        $stmt->bind_param('si', $session_id, $user_id);
        $stmt->execute();
        $cart_id = $conn->insert_id;
        $stmt->close();
    }
}

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);

    switch ($action) {
        case 'add':
            if ($product_id > 0) {
                // Check if item already in cart
                $stmt = $conn->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?');
                if ($stmt) {
                    $stmt->bind_param('ii', $cart_id, $product_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        // Update quantity
                        $new_quantity = $row['quantity'] + $quantity;
                        $stmt2 = $conn->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
                        if ($stmt2) {
                            $stmt2->bind_param('ii', $new_quantity, $row['id']);
                            $stmt2->execute();
                            $stmt2->close();
                        }
                    } else {
                        // Get product price
                        $stmt2 = $conn->prepare('SELECT price FROM products WHERE id = ?');
                        if ($stmt2) {
                            $stmt2->bind_param('i', $product_id);
                            $stmt2->execute();
                            $result2 = $stmt2->get_result();
                            if ($row2 = $result2->fetch_assoc()) {
                                // Add new item
                                $stmt3 = $conn->prepare('INSERT INTO cart_items (cart_id, product_id, quantity, price_at_add) VALUES (?, ?, ?, ?)');
                                if ($stmt3) {
                                    $stmt3->bind_param('iiid', $cart_id, $product_id, $quantity, $row2['price']);
                                    $stmt3->execute();
                                    $stmt3->close();
                                }
                            }
                            $stmt2->close();
                        }
                    }
                    $stmt->close();
                }
            }
            break;

        case 'update':
            if ($product_id > 0 && $quantity >= 0) {
                if ($quantity > 0) {
                    $stmt = $conn->prepare('UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?');
                    if ($stmt) {
                        $stmt->bind_param('iii', $quantity, $cart_id, $product_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                } else {
                    // Remove item if quantity is 0
                    $stmt = $conn->prepare('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?');
                    if ($stmt) {
                        $stmt->bind_param('ii', $cart_id, $product_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
            break;

        case 'remove':
            if ($product_id > 0) {
                $stmt = $conn->prepare('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?');
                if ($stmt) {
                    $stmt->bind_param('ii', $cart_id, $product_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            break;

        case 'clear':
            $stmt = $conn->prepare('DELETE FROM cart_items WHERE cart_id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $cart_id);
                $stmt->execute();
                $stmt->close();
            }
            break;
    }

    // Redirect to avoid form resubmission
    header('Location: cart.php');
    exit;
}

// Get cart items
if ($cart_id) {
    $stmt = $conn->prepare('
        SELECT ci.*, p.name, p.price, p.image_url, (ci.quantity * ci.price_at_add) as subtotal
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        WHERE ci.cart_id = ?
        ORDER BY ci.added_at DESC
    ');
    if ($stmt) {
        $stmt->bind_param('i', $cart_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $cart[] = $row;
            $cart_total += $row['subtotal'];
        }
        $stmt->close();
    }
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
    <title>Shopping Cart | Kurtopia Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1f2937;
            line-height: 1.6;
            margin: 0;
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
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        main {
            padding: 2rem 0;
        }

        .cart-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 2rem;
        }

        .cart-items {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .cart-header {
            background: #f9fafb;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .cart-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .cart-item {
            display: flex;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            align-items: center;
            gap: 1rem;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 80px;
            height: 80px;
            background: #f3f4f6;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .item-price {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .item-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quantity-btn {
            width: 32px;
            height: 32px;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .quantity-btn:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            padding: 0.25rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }

        .item-total {
            font-weight: 600;
            min-width: 80px;
            text-align: right;
        }

        .remove-btn {
            color: #ef4444;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .remove-btn:hover {
            background: #fef2f2;
        }

        .empty-cart {
            text-align: center;
            padding: 3rem 1.5rem;
            color: #6b7280;
        }

        .empty-cart i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }

        .cart-summary {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .summary-total {
            border-top: 1px solid #e5e7eb;
            padding-top: 1rem;
            margin-top: 1rem;
            font-weight: 600;
            font-size: 1.125rem;
        }

        .checkout-btn {
            width: 100%;
            background: #2563eb;
            color: white;
            border: none;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 1rem;
        }

        .checkout-btn:hover {
            background: #1d4ed8;
        }

        .checkout-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .continue-shopping {
            display: inline-block;
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
            margin-top: 1rem;
        }

        .continue-shopping:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .cart-container {
                grid-template-columns: 1fr;
            }

            .cart-summary {
                position: static;
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
                    <?php if ($is_logged_in): ?>
                        <a href="orders.php" class="nav-link">Orders</a>
                        <a href="wishlist.php" class="nav-link">Wishlist</a>
                        <a href="user_settings.php" class="nav-link">Settings</a>
                        <a href="logout.php" class="nav-link">Logout</a>
                    <?php else: ?>
                        <a href="signup.php" class="nav-link">Sign Up</a>
                    <?php endif; ?>
                    <a href="cart.php" class="cart-link">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo count($cart); ?></span>
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="cart-container">
            <div class="cart-items">
                <div class="cart-header">
                    <h2>Shopping Cart</h2>
                </div>

                <?php if (empty($cart)): ?>
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Your cart is empty</p>
                        <a href="products.php" class="continue-shopping">Continue Shopping</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($cart as $item): ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <?php if ($item['image_url']): ?>
                                    <img src="<?php echo esc($item['image_url']); ?>" alt="<?php echo esc($item['name']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">
                                <?php else: ?>
                                    <i class="fas fa-box"></i>
                                <?php endif; ?>
                            </div>
                            <div class="item-details">
                                <div class="item-name"><?php echo esc($item['name']); ?></div>
                                <div class="item-price">$<?php echo number_format($item['price_at_add'], 2); ?> each</div>
                            </div>
                            <div class="item-controls">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                    <div class="quantity-controls">
                                        <button type="submit" name="quantity" value="<?php echo max(1, $item['quantity'] - 1); ?>" class="quantity-btn">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" class="quantity-input" onchange="this.form.submit()">
                                        <button type="submit" name="quantity" value="<?php echo $item['quantity'] + 1; ?>" class="quantity-btn">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </form>
                                <div class="item-total">$<?php echo number_format($item['subtotal'], 2); ?></div>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                    <button type="submit" class="remove-btn" title="Remove item">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="cart-summary">
                <h3>Order Summary</h3>
                <div class="summary-row">
                    <span>Subtotal (<?php echo count($cart); ?> items)</span>
                    <span>$<?php echo number_format($cart_total, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span>Calculated at checkout</span>
                </div>
                <div class="summary-row">
                    <span>Tax</span>
                    <span>Calculated at checkout</span>
                </div>
                <div class="summary-row summary-total">
                    <span>Total</span>
                    <span>$<?php echo number_format($cart_total, 2); ?></span>
                </div>

                <?php if (!empty($cart)): ?>
                    <form method="post" action="checkout.php">
                        <button type="submit" class="checkout-btn">Proceed to Checkout</button>
                    </form>
                <?php else: ?>
                    <button type="button" class="checkout-btn" disabled>Proceed to Checkout</button>
                <?php endif; ?>

                <a href="products.php" class="continue-shopping">Continue Shopping</a>
            </div>
        </div>
    </main>
</body>
</html>