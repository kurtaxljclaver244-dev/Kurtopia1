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

// Handle remove from wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_wishlist'])) {
    $product_id = (int)$_POST['product_id'];
    $stmt = $conn->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?');
    if ($stmt) {
        $stmt->bind_param('ii', $user_id, $product_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: wishlist.php');
    exit;
}

// Fetch wishlist products
$wishlist_products = [];
$stmt = $conn->prepare('SELECT p.id, p.name, p.price, p.category, p.badge, p.description, p.image_url, p.stock_quantity FROM wishlists w JOIN products p ON w.product_id = p.id WHERE w.user_id = ? AND p.is_active = 1');
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $wishlist_products[] = $row;
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
    <title>My Wishlist | Kurtopia Shop</title>
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

        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
        }

        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .product-image {
            width: 100%;
            height: 200px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .product-placeholder {
            font-size: 3rem;
            color: #d1d5db;
        }

        .product-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: #2563eb;
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .product-info {
            padding: 1.5rem;
        }

        .product-name {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1f2937;
        }

        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: #2563eb;
            margin-bottom: 1rem;
        }

        .product-description {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-category {
            display: inline-block;
            background: #f3f4f6;
            color: #4b5563;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .remove-btn, .add-cart-btn {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            text-align: center;
        }

        .remove-btn {
            background: #dc2626;
            color: white;
        }

        .remove-btn:hover {
            background: #b91c1c;
        }

        .add-cart-btn {
            background: #2563eb;
            color: white;
        }

        .add-cart-btn:hover {
            background: #1d4ed8;
        }

        .no-wishlist {
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

            .wishlist-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 1.5rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .action-buttons {
                flex-direction: column;
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
        <h1 class="page-title">My Wishlist</h1>

        <?php if (empty($wishlist_products)): ?>
            <p class="no-wishlist">Your wishlist is empty. <a href="products.php">Browse products</a> to add some!</p>
        <?php else: ?>
            <div class="wishlist-grid">
                <?php foreach ($wishlist_products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if ($product['image_url']): ?>
                                <img src="<?php echo esc($product['image_url']); ?>" alt="<?php echo esc($product['name']); ?>">
                            <?php else: ?>
                                <i class="fas fa-image product-placeholder"></i>
                            <?php endif; ?>
                            <?php if ($product['badge']): ?>
                                <span class="product-badge"><?php echo esc($product['badge']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name"><?php echo esc($product['name']); ?></h3>
                            <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                            <p class="product-description"><?php echo esc($product['description'] ?? ''); ?></p>
                            <span class="product-category"><?php echo esc($product['category']); ?></span>
                            <div class="action-buttons">
                                <form method="post" style="flex: 1;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" name="remove_wishlist" class="remove-btn">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </form>
                                <form method="post" action="products.php" style="flex: 1;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" name="add_to_cart" class="add-cart-btn">
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </button>
                                </form>
                            </div>
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
                    <a href="index.php" class="footer-link">Home</a>
                    <a href="products.php" class="footer-link">Products</a>
                    <a href="signup.php" class="footer-link">Account</a>
                </div>
                <p class="copyright">&copy; 2026 Kurtopia Shop. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>