<?php
// checkout.php - Auto-fill shipping address from user profile
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
$session_id = session_id();
$cart = [];
$cart_total = 0;
$cart_id = null;
$errors = [];
$success_message = '';

// Get user's saved address information
$user_address = [
    'address' => '',
    'city' => '',
    'state' => '',
    'zip_code' => '',
    'country' => 'USA',
    'phone' => '',
    'first_name' => '',
    'last_name' => ''
];

$stmt = $conn->prepare('SELECT address, city, state, zip_code, country, phone, first_name, last_name FROM users WHERE id = ?');
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $user_address['address'] = $row['address'] ?? '';
        $user_address['city'] = $row['city'] ?? '';
        $user_address['state'] = $row['state'] ?? '';
        $user_address['zip_code'] = $row['zip_code'] ?? '';
        $user_address['country'] = $row['country'] ?? 'USA';
        $user_address['phone'] = $row['phone'] ?? '';
        $user_address['first_name'] = $row['first_name'] ?? '';
        $user_address['last_name'] = $row['last_name'] ?? '';
    }
    $stmt->close();
}

// Get user's active cart
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

if (!$cart_id) {
    header('Location: cart.php');
    exit;
}

// Get cart items
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

if (empty($cart)) {
    header('Location: cart.php');
    exit;
}

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use POST values if provided, otherwise use saved user data
    $shipping_address = trim($_POST['shipping_address'] ?? $user_address['address']);
    $shipping_city = trim($_POST['shipping_city'] ?? $user_address['city']);
    $shipping_state = trim($_POST['shipping_state'] ?? $user_address['state']);
    $shipping_zip = trim($_POST['shipping_zip'] ?? $user_address['zip_code']);
    $shipping_country = trim($_POST['shipping_country'] ?? $user_address['country']);
    $shipping_phone = trim($_POST['shipping_phone'] ?? $user_address['phone']);
    $payment_method = $_POST['payment_method'] ?? '';

    // Validate - only check if fields are completely empty
    if (empty($shipping_address)) {
        $errors[] = 'Shipping address is required.';
    }
    if (empty($shipping_city)) {
        $errors[] = 'City is required.';
    }
    if (empty($shipping_state)) {
        $errors[] = 'State is required.';
    }
    if (empty($shipping_zip)) {
        $errors[] = 'ZIP code is required.';
    }
    if (empty($shipping_country)) {
        $errors[] = 'Country is required.';
    }
    if (empty($payment_method)) {
        $errors[] = 'Payment method is required';
    }

    if (empty($errors)) {
        // Calculate totals
        $subtotal = $cart_total;
        $tax_rate = 0.08; // 8% tax
        $tax = $subtotal * $tax_rate;
        $shipping_cost = $subtotal > 50 ? 0 : 9.99; // Free shipping over $50
        $total_amount = $subtotal + $tax + $shipping_cost;

        // Generate order number
        $order_number = 'ORD-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Start transaction
        $conn->begin_transaction();

        try {
            // Insert order - FIXED: exactly 14 placeholders for 14 bind variables
            $stmt = $conn->prepare('
                INSERT INTO orders (
                    order_number, user_id, subtotal, tax, shipping_cost, total_amount,
                    status, payment_method, payment_status,
                    shipping_address, shipping_city, shipping_state, shipping_zip, shipping_country
                ) VALUES (?, ?, ?, ?, ?, ?, "pending", ?, "pending", ?, ?, ?, ?, ?)
            ');
            
            if ($stmt) {
                $stmt->bind_param(
                    'sddddsssssss',
                    $order_number,      // 1: string
                    $user_id,           // 2: int
                    $subtotal,          // 3: double
                    $tax,               // 4: double
                    $shipping_cost,     // 5: double
                    $total_amount,      // 6: double
                    $payment_method,    // 7: string
                    $shipping_address,  // 8: string
                    $shipping_city,     // 9: string
                    $shipping_state,    // 10: string
                    $shipping_zip,      // 11: string
                    $shipping_country   // 12: string
                );
                $stmt->execute();
                $order_id = $conn->insert_id;
                $stmt->close();
            } else {
                throw new Exception('Failed to prepare order statement');
            }

            // Insert order items
            $stmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase, subtotal) VALUES (?, ?, ?, ?, ?)');
            if ($stmt) {
                foreach ($cart as $item) {
                    $stmt->bind_param('iiidd', $order_id, $item['product_id'], $item['quantity'], $item['price_at_add'], $item['subtotal']);
                    $stmt->execute();
                }
                $stmt->close();
            }

            // Update cart status
            $stmt = $conn->prepare('UPDATE carts SET status = "converted" WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $cart_id);
                $stmt->execute();
                $stmt->close();
            }

            // Clear cart items
            $stmt = $conn->prepare('DELETE FROM cart_items WHERE cart_id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $cart_id);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            $success_message = 'Order placed successfully! Order #' . $order_number;

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Failed to process order. Please try again. Error: ' . $e->getMessage();
        }
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
    <title>Checkout | Kurtopia Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Header */
        header {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #1e293b, #3b82f6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-link {
            color: #475569;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-link:hover {
            color: #3b82f6;
        }

        main {
            padding: 2rem 0;
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 2rem;
        }

        .checkout-form, .order-summary {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-section h3 i {
            color: #3b82f6;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .full-width {
            grid-column: span 2;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .input-group label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-group label i {
            width: 1.25rem;
            color: #3b82f6;
        }

        .input-group input,
        .input-group textarea,
        .input-group select {
            padding: 0.75rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            font-size: 0.875rem;
            font-family: inherit;
            transition: all 0.2s;
            background: #fefefe;
        }

        .input-group input:focus,
        .input-group textarea:focus,
        .input-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .input-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .info-note {
            background: #f0fdf4;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.75rem;
            color: #166534;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-note i {
            font-size: 1rem;
        }

        .info-note a {
            color: #166534;
            font-weight: 600;
        }

        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .payment-method {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .payment-method:hover {
            border-color: #3b82f6;
            background: #f8fafc;
        }

        .payment-method input[type="radio"] {
            width: 18px;
            height: 18px;
            margin: 0;
        }

        .payment-method i {
            font-size: 1.2rem;
            color: #64748b;
        }

        .btn-primary {
            width: 100%;
            background: #1e293b;
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary:hover {
            background: #0f172a;
            transform: translateY(-2px);
        }

        .btn-primary:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }

        /* Order Summary */
        .order-summary h3 {
            margin-bottom: 1.25rem;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
        }

        .summary-product {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .summary-product-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 10px;
            background: #f1f5f9;
        }

        .summary-product-details {
            flex: 1;
        }

        .summary-product-name {
            font-weight: 600;
            font-size: 0.85rem;
        }

        .summary-product-price {
            font-size: 0.75rem;
            color: #64748b;
        }

        .summary-divider {
            border-top: 1px solid #e2e8f0;
            margin: 1rem 0;
        }

        .summary-total {
            font-weight: 700;
            font-size: 1.1rem;
            padding-top: 0.75rem;
            border-top: 2px solid #e2e8f0;
            margin-top: 0.5rem;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 1rem 1.5rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .success-message a {
            color: #065f46;
            font-weight: 600;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem 1.5rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
        }

        .error-message ul {
            margin-left: 1.5rem;
        }

        @media (max-width: 768px) {
            .container { padding: 0 1rem; }
            .checkout-container { grid-template-columns: 1fr; gap: 1.5rem; }
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
        }
    </style>
</head>
<body>

<header>
    <div class="container">
        <div class="header-content">
            <a href="index.php" class="logo">✨ Kurtopia Shop</a>
            <nav class="nav-links">
                <a href="products.php" class="nav-link">Products</a>
                <a href="orders.php" class="nav-link">My Orders</a>
                <a href="user_settings.php" class="nav-link">Settings</a>
                <a href="wishlist.php" class="nav-link">Wishlist</a>
                <a href="logout.php" class="nav-link">Logout</a>
                <a href="cart.php" class="nav-link"><i class="fas fa-shopping-cart"></i> Cart</a>
            </nav>
        </div>
    </div>
</header>

<main class="container">
    <h1>Checkout</h1>

    <?php if ($success_message): ?>
        <div class="success-message">
            <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
            <?php echo esc($success_message); ?>
            <a href="orders.php">→ View your orders</a>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo esc($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$success_message): ?>
        <div class="checkout-container">
            <form method="post" class="checkout-form">
                <!-- Shipping Address Section - Auto-filled from user profile -->
                <div class="form-section">
                    <h3><i class="fas fa-truck"></i> Shipping Address</h3>
                    
                    <?php if (!empty($user_address['address']) || !empty($user_address['city'])): ?>
                        <div class="info-note">
                            <i class="fas fa-info-circle"></i>
                            Address loaded from your profile. 
                            <a href="user_settings.php">Update profile →</a>
                        </div>
                    <?php else: ?>
                        <div class="info-note" style="background: #fef3c7; color: #92400e;">
                            <i class="fas fa-exclamation-triangle"></i>
                            No address found in your profile. Please fill in your details below or 
                            <a href="user_settings.php" style="color: #92400e;">update your profile</a>.
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="input-group full-width">
                            <label><i class="fas fa-map-marker-alt"></i> Street Address</label>
                            <textarea name="shipping_address" placeholder="123 Main Street, Apt 4B"><?php echo esc($_POST['shipping_address'] ?? $user_address['address']); ?></textarea>
                        </div>
                        
                        <div class="input-group">
                            <label><i class="fas fa-city"></i> City</label>
                            <input type="text" name="shipping_city" value="<?php echo esc($_POST['shipping_city'] ?? $user_address['city']); ?>" placeholder="New York">
                        </div>
                        
                        <div class="input-group">
                            <label><i class="fas fa-building"></i> State</label>
                            <input type="text" name="shipping_state" value="<?php echo esc($_POST['shipping_state'] ?? $user_address['state']); ?>" placeholder="NY">
                        </div>
                        
                        <div class="input-group">
                            <label><i class="fas fa-mail-bulk"></i> ZIP Code</label>
                            <input type="text" name="shipping_zip" value="<?php echo esc($_POST['shipping_zip'] ?? $user_address['zip_code']); ?>" placeholder="10001">
                        </div>
                        
                        <div class="input-group">
                            <label><i class="fas fa-globe"></i> Country</label>
                            <input type="text" name="shipping_country" value="<?php echo esc($_POST['shipping_country'] ?? $user_address['country']); ?>" placeholder="USA">
                        </div>
                        
                        <div class="input-group">
                            <label><i class="fas fa-phone-alt"></i> Phone Number</label>
                            <input type="tel" name="shipping_phone" value="<?php echo esc($_POST['shipping_phone'] ?? $user_address['phone']); ?>" placeholder="+1 555 123 4567">
                        </div>
                    </div>
                </div>

                <!-- Payment Method Section -->
                <div class="form-section">
                    <h3><i class="fas fa-credit-card"></i> Payment Method</h3>
                    <div class="payment-methods">
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="credit_card" <?php echo ($_POST['payment_method'] ?? '') === 'credit_card' ? 'checked' : ''; ?> required>
                            <i class="fas fa-credit-card"></i>
                            <span>Credit / Debit Card</span>
                        </label>
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="paypal" <?php echo ($_POST['payment_method'] ?? '') === 'paypal' ? 'checked' : ''; ?>>
                            <i class="fab fa-paypal"></i>
                            <span>PayPal</span>
                        </label>
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="bank_transfer" <?php echo ($_POST['payment_method'] ?? '') === 'bank_transfer' ? 'checked' : ''; ?>>
                            <i class="fas fa-university"></i>
                            <span>Bank Transfer</span>
                        </label>
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="Cash On Delivery" <?php echo ($_POST['payment_method'] ?? '') === 'Cash On Delivery' ? 'checked' : ''; ?>>
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Cash on Delivery</span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-check-circle"></i> Place Order
                </button>
            </form>

            <!-- Order Summary -->
            <div class="order-summary">
                <h3><i class="fas fa-receipt"></i> Order Summary</h3>
                
                <?php 
                $calculated_subtotal = 0;
                foreach ($cart as $item): 
                    $calculated_subtotal += $item['subtotal'];
                ?>
                    <div class="summary-product">
                        <img src="<?php echo !empty($item['image_url']) ? esc($item['image_url']) : 'https://picsum.photos/id/' . ($item['product_id'] * 7 % 100 + 10) . '/50/50'; ?>" class="summary-product-img">
                        <div class="summary-product-details">
                            <div class="summary-product-name"><?php echo esc($item['name']); ?></div>
                            <div class="summary-product-price">Qty: <?php echo $item['quantity']; ?> × $<?php echo number_format($item['price_at_add'], 2); ?></div>
                        </div>
                        <div>$<?php echo number_format($item['subtotal'], 2); ?></div>
                    </div>
                <?php endforeach; ?>

                <div class="summary-divider"></div>

                <div class="summary-item">
                    <span>Subtotal</span>
                    <span>$<?php echo number_format($cart_total, 2); ?></span>
                </div>
                <div class="summary-item">
                    <span>Shipping</span>
                    <span><?php echo $cart_total > 50 ? 'FREE' : '$9.99'; ?></span>
                </div>
                <div class="summary-item">
                    <span>Tax (8%)</span>
                    <span>$<?php echo number_format($cart_total * 0.08, 2); ?></span>
                </div>
                <div class="summary-item summary-total">
                    <span>Total</span>
                    <span>$<?php echo number_format($cart_total + ($cart_total * 0.08) + ($cart_total > 50 ? 0 : 9.99), 2); ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

</body>
</html>