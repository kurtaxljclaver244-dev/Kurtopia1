<?php
// admin_products.php - Product Management
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
$error = '';

// Handle Add/Edit Product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $badge = trim($_POST['badge'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($name && $price > 0 && $category) {
            $stmt = $conn->prepare('INSERT INTO products (name, price, category, badge, description, image_url, stock_quantity, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('sdsssssi', $name, $price, $category, $badge, $description, $image_url, $stock_quantity, $is_active);
                if ($stmt->execute()) {
                    $message = 'Product added successfully!';
                } else {
                    $error = 'Failed to add product: ' . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $error = 'Please fill all required fields.';
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $badge = trim($_POST['badge'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($id && $name && $price > 0) {
            $stmt = $conn->prepare('UPDATE products SET name = ?, price = ?, category = ?, badge = ?, description = ?, image_url = ?, stock_quantity = ?, is_active = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('sdsssssii', $name, $price, $category, $badge, $description, $image_url, $stock_quantity, $is_active, $id);
                if ($stmt->execute()) {
                    $message = 'Product updated successfully!';
                } else {
                    $error = 'Failed to update product: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $message = 'Product deleted successfully!';
                } else {
                    $error = 'Failed to delete product.';
                }
                $stmt->close();
            }
        }
    }
}

// Fetch all products
$products = [];
$result = $conn->query('SELECT * FROM products ORDER BY created_at DESC');
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

// Fetch categories for dropdown
$categories = [];
$result = $conn->query('SELECT name FROM categories ORDER BY name');
while ($row = $result->fetch_assoc()) {
    $categories[] = $row['name'];
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
    <title>Product Management | Kurtopia Shop Admin</title>
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

        /* Sidebar */
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

        /* Main Content */
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

        .btn-add {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 40px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logout-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 40px;
            cursor: pointer;
        }

        /* Message */
        .message {
            background: #d1fae5;
            color: #065f46;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        /* Products Table */
        .products-table {
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

        .product-image {
            width: 50px;
            height: 50px;
            background: #f1f5f9;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background: #d1fae5;
            color: #059669;
        }

        .status-inactive {
            background: #fee2e2;
            color: #dc2626;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-edit, .btn-delete {
            padding: 0.25rem 0.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75rem;
        }

        .btn-edit {
            background: #dbeafe;
            color: #2563eb;
        }

        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 200;
            display: none;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2rem;
        }

        .modal h2 {
            margin-bottom: 1.5rem;
        }

        .modal input, .modal select, .modal textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-family: inherit;
        }

        .modal textarea {
            min-height: 100px;
            resize: vertical;
        }

        .modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn-save, .btn-cancel {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 40px;
            cursor: pointer;
        }

        .btn-save {
            background: #3b82f6;
            color: white;
        }

        .btn-cancel {
            background: #e2e8f0;
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
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>✨ Kurtopia Shop</h2>
            <p>Admin Dashboard</p>
        </div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="nav-item">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
            <a href="admin_products.php" class="nav-item active">
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

    <main class="main-content">
        <div class="top-bar">
            <h1 class="page-title">Product Management</h1>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <button class="btn-add" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Product
                </button>
                <form method="post" action="logout.php" style="display: inline;">
                    <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
                </form>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message"><?php echo esc($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?php echo esc($error); ?></div>
        <?php endif; ?>

        <div class="products-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo $product['id']; ?></td>
                            <td>
                                <div class="product-image">
                                    <?php if ($product['image_url']): ?>
                                        <img src="<?php echo esc($product['image_url']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                    <?php else: ?>
                                        <i class="fas fa-box"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><strong><?php echo esc($product['name']); ?></strong></td>
                            <td><?php echo esc($product['category']); ?></td>
                            <td>$<?php echo number_format($product['price'], 2); ?></td>
                            <td><?php echo $product['stock_quantity']; ?></td>
                            <td>
                                <span class="status-badge <?php echo $product['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-edit" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">Edit</button>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Delete this product?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" class="btn-delete">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Add/Edit Modal -->
    <div class="modal-overlay" id="productModal">
        <div class="modal">
            <h2 id="modalTitle">Add Product</h2>
            <form method="post" id="productForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="productId">
                
                <input type="text" name="name" id="productName" placeholder="Product Name" required>
                <select name="category" id="productCategory" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc($cat); ?>"><?php echo esc($cat); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" step="0.01" name="price" id="productPrice" placeholder="Price" required>
                <input type="text" name="badge" id="productBadge" placeholder="Badge (e.g., Best Seller, New)">
                <textarea name="description" id="productDescription" placeholder="Product Description"></textarea>
                <input type="text" name="image_url" id="productImageUrl" placeholder="Image URL">
                <input type="number" name="stock_quantity" id="productStock" placeholder="Stock Quantity" value="0">
                <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                    <input type="checkbox" name="is_active" id="productActive" checked> Active
                </label>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Product';
            document.getElementById('formAction').value = 'add';
            document.getElementById('productId').value = '';
            document.getElementById('productName').value = '';
            document.getElementById('productCategory').value = '';
            document.getElementById('productPrice').value = '';
            document.getElementById('productBadge').value = '';
            document.getElementById('productDescription').value = '';
            document.getElementById('productImageUrl').value = '';
            document.getElementById('productStock').value = '0';
            document.getElementById('productActive').checked = true;
            document.getElementById('productModal').classList.add('active');
        }

        function editProduct(product) {
            document.getElementById('modalTitle').textContent = 'Edit Product';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('productId').value = product.id;
            document.getElementById('productName').value = product.name;
            document.getElementById('productCategory').value = product.category;
            document.getElementById('productPrice').value = product.price;
            document.getElementById('productBadge').value = product.badge || '';
            document.getElementById('productDescription').value = product.description || '';
            document.getElementById('productImageUrl').value = product.image_url || '';
            document.getElementById('productStock').value = product.stock_quantity;
            document.getElementById('productActive').checked = product.is_active == 1;
            document.getElementById('productModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('productModal').classList.remove('active');
        }
    </script>
</body>
</html>