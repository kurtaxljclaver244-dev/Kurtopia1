<?php
// index.php - Kurtopia Shop E-commerce Website
session_start();

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Product catalog
$products = [
    1 => ['id' => 1, 'name' => 'Premium Cotton T-Shirt', 'price' => 29.99, 'category' => 'Clothing', 'badge' => 'Best Seller'],
    2 => ['id' => 2, 'name' => 'Wireless Noise Cancelling Headphones', 'price' => 99.99, 'category' => 'Electronics', 'badge' => 'New'],
    3 => ['id' => 3, 'name' => 'Genuine Leather Wallet', 'price' => 49.99, 'category' => 'Accessories', 'badge' => ''],
    4 => ['id' => 4, 'name' => 'Smart Fitness Watch', 'price' => 199.99, 'category' => 'Electronics', 'badge' => 'Trending'],
    5 => ['id' => 5, 'name' => 'Cozy Fleece Hoodie', 'price' => 59.99, 'category' => 'Clothing', 'badge' => ''],
    6 => ['id' => 6, 'name' => 'Premium Laptop Backpack', 'price' => 79.99, 'category' => 'Accessories', 'badge' => 'Limited'],
];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $product_id = (int)($_POST['product_id'] ?? 0);
    
    switch ($action) {
        case 'add':
            if (isset($products[$product_id]) && $product_id > 0) {
                $_SESSION['cart'][$product_id] = (int)(($_SESSION['cart'][$product_id] ?? 0)) + 1;
            }
            break;
        case 'remove':
            unset($_SESSION['cart'][$product_id]);
            break;
        case 'update':
            $quantity = max(0, (int)($_POST['quantity'] ?? 0));
            if ($quantity > 0) {
                $_SESSION['cart'][$product_id] = $quantity;
            } else {
                unset($_SESSION['cart'][$product_id]);
            }
            break;
        case 'clear':
            $_SESSION['cart'] = [];
            break;
    }
    
    foreach ($_SESSION['cart'] as $pid => $qty) {
        if (!is_scalar($qty) || !is_numeric($qty)) {
            unset($_SESSION['cart'][$pid]);
            continue;
        }
        $_SESSION['cart'][$pid] = (int)$qty;
    }
    
    $cart_count = array_sum($_SESSION['cart']);
    $cart_total = 0;
    foreach ($_SESSION['cart'] as $pid => $qty) {
        if (isset($products[$pid])) {
            $cart_total += $products[$pid]['price'] * (int)$qty;
        }
    }
    
    echo json_encode([
        'success' => true,
        'cart_count' => $cart_count,
        'cart_total' => number_format($cart_total, 2),
        'cart' => $_SESSION['cart']
    ]);
    exit;
}

foreach ($_SESSION['cart'] as $pid => $qty) {
    if (!is_scalar($qty) || !is_numeric($qty)) {
        unset($_SESSION['cart'][$pid]);
        continue;
    }
    $_SESSION['cart'][$pid] = (int)$qty;
}

$cart_count = array_sum($_SESSION['cart']);
$cart_total = 0;
foreach ($_SESSION['cart'] as $pid => $qty) {
    if (isset($products[$pid])) {
        $cart_total += $products[$pid]['price'] * (int)$qty;
    }
}
$is_logged_in = !empty($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurtopia Shop - Quality Products at Great Prices</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
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
            color: #0f172a;
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .logo h1 {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #1e293b, #3b82f6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: -0.5px;
        }

        .logo p {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 4px;
        }

        /* Cart Button */
        .cart-btn {
            background: #f1f5f9;
            padding: 0.75rem 1.5rem;
            border-radius: 100px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid #e2e8f0;
        }

        .cart-btn:hover {
            background: #e2e8f0;
            transform: scale(1.02);
        }

        .cart-btn i {
            font-size: 1.3rem;
            color: #3b82f6;
        }

        .cart-count {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            padding: 4px 12px;
            border-radius: 100px;
            font-weight: 700;
            font-size: 0.85rem;
        }

        /* Hero Section - Exactly as design */
        .hero {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            padding: 5rem 2rem;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }

        .hero h2 {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #1e293b, #3b82f6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 1.25rem;
            letter-spacing: -1px;
        }

        .hero p {
            font-size: 1.2rem;
            color: #334155;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .shop-now-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #1e293b;
            color: white;
            padding: 1rem 2.5rem;
            border-radius: 100px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .shop-now-btn:hover {
            background: #0f172a;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.2);
        }

        /* Featured Products */
        .featured {
            max-width: 1280px;
            margin: 0 auto;
            padding: 5rem 2rem;
        }

        .section-title {
            font-size: 2.2rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 3rem;
            color: #0f172a;
        }

        .section-title::after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            margin: 12px auto 0;
            border-radius: 2px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }

        .product-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -12px rgba(0,0,0,0.15);
        }

        .product-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 4px 12px;
            border-radius: 100px;
            font-size: 0.7rem;
            font-weight: 700;
            z-index: 2;
        }

        .product-image {
            width: 100%;
            height: 260px;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        .product-info {
            padding: 1.5rem;
        }

        .product-category {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #3b82f6;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .product-name {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #0f172a;
        }

        .product-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1e293b;
            margin: 0.75rem 0;
        }

        .add-to-cart {
            width: 100%;
            background: #f1f5f9;
            border: none;
            padding: 0.85rem;
            border-radius: 100px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
            color: #1e293b;
        }

        .add-to-cart:hover {
            background: #1e293b;
            color: white;
        }

        /* Cart Sidebar */
        .cart-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 200;
            visibility: hidden;
            opacity: 0;
            transition: all 0.3s;
        }

        .cart-overlay.active {
            visibility: visible;
            opacity: 1;
        }

        .cart-sidebar {
            position: fixed;
            top: 0;
            right: -450px;
            width: 100%;
            max-width: 450px;
            height: 100%;
            background: white;
            box-shadow: -5px 0 30px rgba(0,0,0,0.1);
            z-index: 201;
            transition: right 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .cart-sidebar.open {
            right: 0;
        }

        .cart-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .close-cart {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: #94a3b8;
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }

        .cart-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            align-items: center;
        }

        .cart-item-img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 16px;
        }

        .cart-item-details {
            flex: 1;
        }

        .cart-item-name {
            font-weight: 700;
            margin-bottom: 4px;
        }

        .cart-item-price {
            color: #3b82f6;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .cart-quantity {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 8px;
        }

        .cart-quantity button {
            background: #f1f5f9;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }

        .remove-item {
            color: #ef4444;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
        }

        .cart-footer {
            padding: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.2rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .checkout-btn {
            width: 100%;
            background: linear-gradient(135deg, #1e293b, #0f172a);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 100px;
            font-weight: 700;
            cursor: pointer;
        }

        .clear-cart-btn {
            width: 100%;
            background: transparent;
            border: 1px solid #e2e8f0;
            padding: 0.75rem;
            border-radius: 100px;
            margin-top: 0.75rem;
            cursor: pointer;
            color: #64748b;
        }

        .empty-cart {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #1e293b;
            color: white;
            padding: 1rem 2rem;
            border-radius: 100px;
            z-index: 300;
            transform: translateX(200%);
            transition: transform 0.3s;
            font-weight: 500;
        }

        .toast.show {
            transform: translateX(0);
        }

        @media (max-width: 768px) {
            .hero h2 { font-size: 2rem; }
            .nav-container { padding: 1rem; }
            .cart-sidebar { max-width: 100%; }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="nav-container">
        <div class="logo">
            <h1>✨ Kurtopia Shop</h1>
            <p>Discover quality products at great prices.</p>
        </div>
        <div class="cart-btn" id="cartIcon">
            <i class="fas fa-shopping-cart"></i>
            <span class="cart-count" id="cartCount"><?php echo $cart_count; ?></span>
        </div>
    </div>
</header>

<section class="hero">
    <div class="hero-content">
        <h2>Welcome to Kurtopia Shop</h2>
        <p>Discover quality products at great prices. Start browsing our catalog and add your favorites to the cart.</p>
       <a href="signup.php" class="shop-now-btn" id="shopNowBtn"><i class="fas fa-store"></i> Shop Now</a>
    </div>
</section>

<section class="featured" id="featuredSection">
    <h2 class="section-title">Featured Products</h2>
    <div class="products-grid" id="productsGrid">
        <?php foreach ($products as $product): ?>
        <div class="product-card" data-id="<?php echo $product['id']; ?>">
            <?php if ($product['badge']): ?>
            <span class="product-badge"><?php echo htmlspecialchars($product['badge']); ?></span>
            <?php endif; ?>
            <img src="https://picsum.photos/id/<?php echo $product['id'] * 7 % 100 + 10; ?>/400/300" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
            <div class="product-info">
                <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                <button class="add-to-cart" data-id="<?php echo $product['id']; ?>" data-name="<?php echo htmlspecialchars($product['name']); ?>" data-price="<?php echo $product['price']; ?>">
                    <i class="fas fa-cart-plus"></i> Add to Cart
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="cart-overlay" id="cartOverlay"></div>
<div class="cart-sidebar" id="cartSidebar">
    <div class="cart-header">
        <h3><i class="fas fa-bag-shopping"></i> Your Cart</h3>
        <button class="close-cart" id="closeCartBtn">&times;</button>
    </div>
    <div class="cart-items" id="cartItems">
        <div class="empty-cart">Your cart is empty</div>
    </div>
    <div class="cart-footer">
        <div class="cart-total">
            <span>Total:</span>
            <span id="cartTotal">$<?php echo number_format($cart_total, 2); ?></span>
        </div>
        <button class="checkout-btn" id="checkoutBtn">Proceed to Checkout →</button>
        <button class="clear-cart-btn" id="clearCartBtn">Clear Cart</button>
    </div>
</div>

<div class="toast" id="toast">Item added to cart!</div>

<script>
    // Cart management
    let cart = <?php echo json_encode($_SESSION['cart']); ?>;
    const products = <?php echo json_encode($products); ?>;
    const isLoggedIn = <?php echo json_encode($is_logged_in); ?>;

    function updateCartDisplay() {
        let count = 0;
        let total = 0;
        for (let id in cart) {
            count += cart[id];
            if (products[id]) {
                total += products[id].price * cart[id];
            }
        }
        document.getElementById('cartCount').textContent = count;
        document.getElementById('cartTotal').textContent = '$' + total.toFixed(2);
        renderCartItems();
    }

    function renderCartItems() {
        const container = document.getElementById('cartItems');
        if (Object.keys(cart).length === 0) {
            container.innerHTML = '<div class="empty-cart">Your cart is empty</div>';
            return;
        }
        
        let html = '';
        for (let id in cart) {
            const product = products[id];
            if (!product) continue;
            html += `
                <div class="cart-item" data-id="${id}">
                    <img src="https://picsum.photos/id/${id * 7 % 100 + 10}/70/70" class="cart-item-img">
                    <div class="cart-item-details">
                        <div class="cart-item-name">${escapeHtml(product.name)}</div>
                        <div class="cart-item-price">$${product.price.toFixed(2)}</div>
                        <div class="cart-quantity">
                            <button class="qty-decr" data-id="${id}">-</button>
                            <span>${cart[id]}</span>
                            <button class="qty-incr" data-id="${id}">+</button>
                        </div>
                    </div>
                    <button class="remove-item" data-id="${id}"><i class="fas fa-trash"></i></button>
                </div>
            `;
        }
        container.innerHTML = html;
        
        // Attach event listeners
        document.querySelectorAll('.qty-decr').forEach(btn => {
            btn.addEventListener('click', () => updateQuantity(btn.dataset.id, -1));
        });
        document.querySelectorAll('.qty-incr').forEach(btn => {
            btn.addEventListener('click', () => updateQuantity(btn.dataset.id, 1));
        });
        document.querySelectorAll('.remove-item').forEach(btn => {
            btn.addEventListener('click', () => removeFromCart(btn.dataset.id));
        });
    }

    async function syncWithServer(action, productId, quantity = null) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('product_id', productId);
        if (quantity !== null) formData.append('quantity', quantity);
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            document.getElementById('cartCount').textContent = data.cart_count;
            document.getElementById('cartTotal').textContent = '$' + data.cart_total;
        }
        return data;
    }

    async function addToCart(productId, productName) {
        if (cart[productId]) {
            cart[productId]++;
        } else {
            cart[productId] = 1;
        }
        updateCartDisplay();
        await syncWithServer('add', productId);
        showToast(`${productName} added to cart!`);
    }

    async function updateQuantity(productId, delta) {
        const newQty = (cart[productId] || 0) + delta;
        if (newQty <= 0) {
            await removeFromCart(productId);
        } else {
            cart[productId] = newQty;
            updateCartDisplay();
            await syncWithServer('update', productId, newQty);
        }
    }

    async function removeFromCart(productId) {
        delete cart[productId];
        updateCartDisplay();
        await syncWithServer('remove', productId);
        showToast('Item removed from cart');
    }

    async function clearCart() {
        if (confirm('Clear your entire cart?')) {
            cart = {};
            updateCartDisplay();
            await syncWithServer('clear', 0);
            showToast('Cart cleared');
        }
    }

    function showToast(message) {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2500);
    }

    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // Cart sidebar
    const cartIcon = document.getElementById('cartIcon');
    const cartSidebar = document.getElementById('cartSidebar');
    const cartOverlay = document.getElementById('cartOverlay');
    const closeCartBtn = document.getElementById('closeCartBtn');

    cartIcon.addEventListener('click', () => {
        cartSidebar.classList.add('open');
        cartOverlay.classList.add('active');
    });

    function closeCart() {
        cartSidebar.classList.remove('open');
        cartOverlay.classList.remove('active');
    }

    closeCartBtn.addEventListener('click', closeCart);
    cartOverlay.addEventListener('click', closeCart);

    // Add to cart buttons
    document.querySelectorAll('.add-to-cart').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const id = btn.dataset.id;
            const name = btn.dataset.name;
            await addToCart(id, name);
        });
    });

    // Shop now button scroll
    document.getElementById('shopNowBtn').addEventListener('click', () => {
        document.getElementById('featuredSection').scrollIntoView({ behavior: 'smooth' });
    });

    // Checkout
    document.getElementById('checkoutBtn').addEventListener('click', () => {
        if (!isLoggedIn) {
            showToast('Please log in before checking out.');
            setTimeout(() => {
                window.location.href = 'signup.php';
            }, 900);
            return;
        }
        if (Object.keys(cart).length === 0) {
            showToast('Your cart is empty!');
            return;
        }
        showToast('Proceeding to checkout...');
        setTimeout(() => {
            alert('Thank you for shopping at Kurtopia! This is a demo checkout.');
            cart = {};
            updateCartDisplay();
            syncWithServer('clear', 0);
            closeCart();
        }, 1000);
    });

    document.getElementById('clearCartBtn').addEventListener('click', clearCart);

    // Initial render
    updateCartDisplay();
</script>
</body>
</html>