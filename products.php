<?php
// products.php - Complete Product Page with Expandable Details & Reviews
session_start();
require_once 'db.php';

if (!$conn->select_db('claver')) {
    die('Database selection failed: ' . $conn->error);
}

$is_logged_in = isset($_SESSION['user_id']);
$success_message = '';
$error_message = 'error';

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    if ($product_id > 0) {
        // Get or create cart
        $session_id = session_id();
        $cart_id = null;

        if ($is_logged_in) {
            // For logged-in users, ensure session exists
            $stmt = $conn->prepare('INSERT INTO sessions (session_id, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), last_activity = CURRENT_TIMESTAMP');
            if ($stmt) {
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $stmt->bind_param('siss', $session_id, $_SESSION['user_id'], $ip, $user_agent);
                $stmt->execute();
                $stmt->close();
            }

            // Get or create cart
            $stmt = $conn->prepare('SELECT id FROM carts WHERE user_id = ? AND status = "active" ORDER BY created_at DESC LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $cart_id = $row['id'];
                }
                $stmt->close();
            }
        } else {
            // For guests, ensure session exists
            $stmt = $conn->prepare('INSERT IGNORE INTO sessions (session_id, user_id, ip_address, user_agent) VALUES (?, NULL, ?, ?)');
            if ($stmt) {
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $stmt->bind_param('sss', $session_id, $ip, $user_agent);
                $stmt->execute();
                $stmt->close();
            }

            // Get or create cart
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
                $user_id_for_cart = $is_logged_in ? $_SESSION['user_id'] : null;
                $stmt->bind_param('si', $session_id, $user_id_for_cart);
                $stmt->execute();
                $cart_id = $conn->insert_id;
                $stmt->close();
            }
        }

        // Add item to cart
        if ($cart_id) {
            // Check if item already in cart
            $stmt = $conn->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?');
            if ($stmt) {
                $stmt->bind_param('ii', $cart_id, $product_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    // Update quantity
                    $new_quantity = $row['quantity'] + 1;
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
                            $stmt3 = $conn->prepare('INSERT INTO cart_items (cart_id, product_id, quantity, price_at_add) VALUES (?, ?, 1, ?)');
                            if ($stmt3) {
                                $stmt3->bind_param('iid', $cart_id, $product_id, $row2['price']);
                                $stmt3->execute();
                                $stmt3->close();
                            }
                        }
                        $stmt2->close();
                    }
                }
                $stmt->close();
            }
            $success_message = 'Product added to cart!';
        }
    }
}

// Handle add to wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_wishlist']) && $is_logged_in) {
    $product_id = (int)$_POST['product_id'];
    if ($product_id > 0) {
        $stmt = $conn->prepare('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)');
        if ($stmt) {
            $stmt->bind_param('ii', $_SESSION['user_id'], $product_id);
            $stmt->execute();
            $stmt->close();
            $success_message = 'Added to wishlist!';
        }
    }
}

// Handle submit review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && $is_logged_in) {
    $product_id = (int)$_POST['product_id'];
    $rating = (int)$_POST['rating'];
    $review_text = trim($_POST['review_text']);
    if ($product_id > 0 && $rating >= 1 && $rating <= 5 && !empty($review_text)) {
        $stmt = $conn->prepare('INSERT INTO reviews (product_id, user_id, rating, review_text, created_at) VALUES (?, ?, ?, ?, NOW())');
        if ($stmt) {
            $stmt->bind_param('iiis', $product_id, $_SESSION['user_id'], $rating, $review_text);
            $stmt->execute();
            $stmt->close();
            $success_message = 'Your review has been submitted!';
        } else {
            $error_message = 'Failed to submit review.';
        }
    } else {
        $error_message = 'Please provide a rating and review text.';
    }
}

// Fetch products with average rating and review count
$products = [];
$stmt = $conn->prepare('
    SELECT p.*, 
           COALESCE(AVG(r.rating), 0) as avg_rating,
           COUNT(r.id) as review_count
    FROM products p 
    LEFT JOIN reviews r ON p.id = r.product_id 
    WHERE p.is_active = 1 
    GROUP BY p.id 
    ORDER BY p.created_at DESC
');
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[$row['id']] = $row;
    }
    $stmt->close();
}

// Fetch all reviews for products
$reviews = [];
$stmt = $conn->prepare('
    SELECT r.*, u.username 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    ORDER BY r.created_at DESC
');
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!isset($reviews[$row['product_id']])) {
            $reviews[$row['product_id']] = [];
        }
        $reviews[$row['product_id']][] = $row;
    }
    $stmt->close();
}

// Calculate cart total
$cart_total = 0;
$cart_count = 0;
foreach ($_SESSION['cart'] as $pid => $qty) {
    if (isset($products[$pid])) {
        $cart_total += $products[$pid]['price'] * $qty;
        $cart_count += $qty;
    }
}

function esc($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function renderStars($rating) {
    $full = floor($rating);
    $half = $rating - $full >= 0.5;
    $empty = 5 - $full - ($half ? 1 : 0);
    $html = '';
    for ($i = 0; $i < $full; $i++) {
        $html .= '<i class="fas fa-star" style="color: #fbbf24;"></i>';
    }
    if ($half) {
        $html .= '<i class="fas fa-star-half-alt" style="color: #fbbf24;"></i>';
    }
    for ($i = 0; $i < $empty; $i++) {
        $html .= '<i class="far fa-star" style="color: #fbbf24;"></i>';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products | Kurtopia Shop</title>
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

        .cart-link {
            position: relative;
            color: #475569;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cart-count {
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* Toast Message */
        .toast {
            position: fixed;
            top: 80px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 40px;
            font-size: 0.875rem;
            z-index: 200;
            transform: translateX(200%);
            transition: transform 0.3s;
        }
        .toast.error { background: #ef4444; }
        .toast.show { transform: translateX(0); }

        /* Products Grid */
        .page-title {
            text-align: center;
            margin: 2rem 0;
            font-size: 2rem;
            font-weight: 700;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid #e2e8f0;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -12px rgba(0,0,0,0.1);
        }

        .product-image {
            width: 100%;
            height: 240px;
            object-fit: cover;
            background: #f1f5f9;
        }

        .product-info {
            padding: 1.25rem;
        }

        .product-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .product-price {
            font-size: 1.3rem;
            font-weight: 800;
            color: #3b82f6;
            margin-bottom: 0.5rem;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            font-size: 0.8rem;
            color: #64748b;
        }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(8px);
            z-index: 300;
            display: none;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 28px;
            max-width: 1000px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #f1f5f9;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 10;
        }

        .modal-close:hover {
            background: #e2e8f0;
        }

        .modal-body {
            display: flex;
            flex-direction: column;
        }

        .modal-product {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            padding: 2rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .modal-product-image {
            width: 100%;
            border-radius: 20px;
            object-fit: cover;
        }

        .modal-product-details h2 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .modal-price {
            font-size: 2rem;
            font-weight: 800;
            color: #3b82f6;
            margin: 1rem 0;
        }

        .modal-description {
            color: #475569;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .modal-category {
            display: inline-block;
            background: #f1f5f9;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            margin-bottom: 1rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn-primary, .btn-wishlist {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #1e293b;
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            background: #0f172a;
        }

        .btn-wishlist {
            background: #f1f5f9;
            color: #ef4444;
        }

        .btn-wishlist:hover {
            background: #fee2e2;
        }

        /* Reviews Section */
        .reviews-section {
            padding: 2rem;
        }

        .reviews-section h3 {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .review-form {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
        }

        .review-form select, .review-form textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-family: inherit;
        }

        .review-form textarea {
            min-height: 100px;
            resize: vertical;
        }

        .review-submit-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
        }

        .review-item {
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 0;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
        }

        .review-user {
            font-weight: 600;
            color: #1e293b;
        }

        .review-date {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .review-text {
            color: #475569;
            margin-top: 0.5rem;
            line-height: 1.5;
        }

        .no-reviews {
            text-align: center;
            padding: 2rem;
            color: #94a3b8;
        }

        .login-prompt {
            text-align: center;
            padding: 2rem;
            background: #f1f5f9;
            border-radius: 20px;
        }

        @media (max-width: 768px) {
            .container { padding: 0 1rem; }
            .modal-product { grid-template-columns: 1fr; gap: 1rem; padding: 1.5rem; }
            .products-grid { gap: 1rem; }
        }
    </style>
</head>
<body>

<header>
    <div class="container">
        <div class="header-content">
            <a href="#" class="logo">✨ Kurtopia Shop</a>
            <nav class="nav-links">
                <a href="products.php" class="nav-link">Products</a>
                <?php if ($is_logged_in): ?>
                    <a href="orders.php" class="nav-link">My Orders</a>
                    <a href="user_settings.php" class="nav-link">Settings</a>
                    <a href="wishlist.php" class="nav-link">Wishlist</a>
                    <a href="logout.php" class="nav-link">Logout</a>
                <?php else: ?>
                    <a href="signup.php" class="nav-link">Login / Sign Up</a>
                <?php endif; ?>
                <a href="cart.php" class="cart-link">
                    <i class="fas fa-shopping-cart"></i> Cart
                    <?php if ($cart_count > 0): ?>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>
            </nav>
        </div>
    </div>
</header>

<main>
    <div class="container">
        <h1 class="page-title">✨ Featured Products</h1>

        <?php if (empty($products)): ?>
            <p style="text-align: center; color: #64748b;">No products available.</p>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card" onclick="openProductModal(<?php echo $product['id']; ?>)">
                        <img src="<?php echo !empty($product['image_url']) ? esc($product['image_url']) : 'https://picsum.photos/id/' . ($product['id'] * 7 % 100 + 10) . '/400/300'; ?>" 
                             alt="<?php echo esc($product['name']); ?>" class="product-image">
                        <div class="product-info">
                            <h3 class="product-name"><?php echo esc($product['name']); ?></h3>
                            <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                            <div class="product-rating">
                                <?php echo renderStars($product['avg_rating']); ?>
                                <span>(<?php echo $product['review_count']; ?> reviews)</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Product Modal -->
<div class="modal-overlay" id="productModal" onclick="closeModalOnClick(event)">
    <div class="modal-content">
        <button class="modal-close" onclick="closeProductModal()">&times;</button>
        <div class="modal-body" id="modalBody">
            <!-- Dynamic content loaded via JS -->
            <div style="text-align: center; padding: 3rem;">Loading...</div>
        </div>
    </div>
</div>

<div class="toast" id="toastMsg"></div>

<script>
    // Product data from PHP
    const productsData = <?php echo json_encode(array_values($products)); ?>;
    const reviewsData = <?php echo json_encode($reviews); ?>;
    const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    const currentUserId = <?php echo $_SESSION['user_id'] ?? 'null'; ?>;

    function showToast(message, isError = false) {
        const toast = document.getElementById('toastMsg');
        toast.textContent = message;
        toast.className = 'toast' + (isError ? ' error' : '');
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function renderStars(rating) {
        const full = Math.floor(rating);
        const half = rating - full >= 0.5;
        const empty = 5 - full - (half ? 1 : 0);
        let html = '';
        for (let i = 0; i < full; i++) html += '<i class="fas fa-star" style="color: #fbbf24;"></i>';
        if (half) html += '<i class="fas fa-star-half-alt" style="color: #fbbf24;"></i>';
        for (let i = 0; i < empty; i++) html += '<i class="far fa-star" style="color: #fbbf24;"></i>';
        return html;
    }

    function openProductModal(productId) {
        const product = productsData.find(p => p.id == productId);
        if (!product) return;

        const productReviews = reviewsData[productId] || [];
        
        let reviewsHtml = '';
        if (productReviews.length === 0) {
            reviewsHtml = '<div class="no-reviews"><i class="far fa-comment-dots"></i> No reviews yet. Be the first to review!</div>';
        } else {
            productReviews.forEach(review => {
                reviewsHtml += `
                    <div class="review-item">
                        <div class="review-header">
                            <span class="review-user"><i class="fas fa-user-circle"></i> ${escapeHtml(review.username)}</span>
                            <span class="review-date">${new Date(review.created_at).toLocaleDateString()}</span>
                        </div>
                        <div class="product-rating" style="margin-bottom: 0.5rem;">${renderStars(review.rating)}</div>
                        <div class="review-text">${escapeHtml(review.review_text)}</div>
                    </div>
                `;
            });
        }

        const reviewFormHtml = isLoggedIn ? `
            <div class="review-form">
                <h4>Leave a Review</h4>
                <form id="reviewForm" onsubmit="submitReview(event, ${product.id})">
                    <select name="rating" id="reviewRating" required>
                        <option value="">Select Rating</option>
                        <option value="5">★★★★★ 5 Stars</option>
                        <option value="4">★★★★☆ 4 Stars</option>
                        <option value="3">★★★☆☆ 3 Stars</option>
                        <option value="2">★★☆☆☆ 2 Stars</option>
                        <option value="1">★☆☆☆☆ 1 Star</option>
                    </select>
                    <textarea name="review_text" id="reviewText" placeholder="Share your experience with this product..." required></textarea>
                    <button type="submit" class="review-submit-btn">Submit Review</button>
                </form>
            </div>
        ` : `
            <div class="login-prompt">
                <i class="fas fa-lock" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                <p>Please <a href="signup.php" style="color: #3b82f6;">login or sign up</a> to leave a review.</p>
            </div>
        `;

        const modalHtml = `
            <div class="modal-product">
                <div>
                    <img src="${product.image_url ? escapeHtml(product.image_url) : 'https://picsum.photos/id/' + (product.id * 7 % 100 + 10) + '/500/400'}" 
                         alt="${escapeHtml(product.name)}" class="modal-product-image">
                </div>
                <div class="modal-product-details">
                    <span class="modal-category">${escapeHtml(product.category)}</span>
                    <h2>${escapeHtml(product.name)}</h2>
                    <div class="product-rating" style="margin: 0.5rem 0;">
                        ${renderStars(parseFloat(product.avg_rating))}
                        <span>(${product.review_count} reviews)</span>
                    </div>
                    <div class="modal-price">$${parseFloat(product.price).toFixed(2)}</div>
                    <p class="modal-description">${escapeHtml(product.description || 'No description available.')}</p>
                    <div class="modal-actions">
                        <form method="post" action="products.php" style="flex:1;" onsubmit="showToast('Adding to cart...');">
                            <input type="hidden" name="product_id" value="${product.id}">
                            <button type="submit" name="add_to_cart" class="btn-primary">
                                <i class="fas fa-cart-plus"></i> Add to Cart
                            </button>
                        </form>
                        ${isLoggedIn ? `
                        <form method="post" action="products.php">
                            <input type="hidden" name="product_id" value="${product.id}">
                            <button type="submit" name="add_to_wishlist" class="btn-wishlist">
                                <i class="fas fa-heart"></i>
                            </button>
                        </form>
                        ` : ''}
                    </div>
                </div>
            </div>
            <div class="reviews-section">
                <h3><i class="fas fa-comments"></i> Customer Reviews (${product.review_count})</h3>
                ${reviewFormHtml}
                <div id="reviewsList">
                    ${reviewsHtml}
                </div>
            </div>
        `;

        document.getElementById('modalBody').innerHTML = modalHtml;
        document.getElementById('productModal').classList.add('active');
    }

    function closeProductModal() {
        document.getElementById('productModal').classList.remove('active');
    }

    function closeModalOnClick(event) {
        if (event.target === document.getElementById('productModal')) {
            closeProductModal();
        }
    }

    async function submitReview(event, productId) {
        event.preventDefault();
        if (!isLoggedIn) {
            showToast('Please login to submit a review', true);
            return;
        }

        const rating = document.getElementById('reviewRating').value;
        const reviewText = document.getElementById('reviewText').value;

        if (!rating || !reviewText.trim()) {
            showToast('Please provide both rating and review', true);
            return;
        }

        const formData = new FormData();
        formData.append('submit_review', '1');
        formData.append('product_id', productId);
        formData.append('rating', rating);
        formData.append('review_text', reviewText);

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            const html = await response.text();
            showToast('Review submitted successfully!');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } catch (error) {
            showToast('Error submitting review', true);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // Show success message if any from PHP
    <?php if ($success_message): ?>
    showToast('<?php echo addslashes($success_message); ?>');
    <?php endif; ?>
    <?php if ($error_message): ?>
    showToast('<?php echo addslashes($error_message); ?>', true);
    <?php endif; ?>
</script>

<footer style="background: #1e293b; color: white; padding: 2rem 0; margin-top: 4rem;">
    <div class="container" style="text-align: center;">
        <p style="opacity: 0.8;">&copy; 2026 Kurtopia Shop. All rights reserved.</p>
    </div>
</footer>

</body>
</html>