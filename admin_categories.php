<?php
// admin_categories.php - Category Management
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

// Add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name)));
    
    if ($name) {
        $stmt = $conn->prepare('INSERT INTO categories (name, slug) VALUES (?, ?)');
        if ($stmt) {
            $stmt->bind_param('ss', $name, $slug);
            if ($stmt->execute()) {
                $message = 'Category added successfully!';
            } else {
                $error = 'Category already exists or error occurred.';
            }
            $stmt->close();
        }
    }
}

// Delete category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $id = intval($_POST['id']);
    $stmt = $conn->prepare('DELETE FROM categories WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $message = 'Category deleted successfully!';
        }
        $stmt->close();
    }
}

// Fetch categories
$categories = [];
$result = $conn->query('SELECT * FROM categories ORDER BY name');
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
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
    <title>Category Management | Kurtopia Shop Admin</title>
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

        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .add-category {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .add-category h3 {
            margin-bottom: 1rem;
        }

        .add-form {
            display: flex;
            gap: 1rem;
        }

        .add-form input {
            flex: 1;
            padding: 0.75rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
        }

        .add-form button {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 40px;
            cursor: pointer;
        }

        .categories-table {
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

        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            cursor: pointer;
        }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .add-form { flex-direction: column; }
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
            <a href="admin_orders.php" class="nav-item">
                <i class="fas fa-shopping-cart"></i> Orders
            </a>
            <a href="admin_users.php" class="nav-item">
                <i class="fas fa-users"></i> Users
            </a>
            <a href="admin_categories.php" class="nav-item active">
                <i class="fas fa-tags"></i> Categories
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <h1 class="page-title">Category Management</h1>
            <form method="post" action="logout.php" style="display: inline;">
                <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </form>
        </div>

        <?php if ($message): ?>
            <div class="message"><?php echo esc($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?php echo esc($error); ?></div>
        <?php endif; ?>

        <div class="add-category">
            <h3><i class="fas fa-plus-circle"></i> Add New Category</h3>
            <form method="post" class="add-form">
                <input type="text" name="name" placeholder="Category name" required>
                <button type="submit" name="add_category">Add Category</button>
            </form>
        </div>

        <div class="categories-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category Name</th>
                        <th>Slug</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo $category['id']; ?></td>
                            <td><strong><?php echo esc($category['name']); ?></strong></td>
                            <td><?php echo esc($category['slug']); ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Delete this category? Products in this category will remain but category will be removed.')">
                                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                    <button type="submit" name="delete_category" class="btn-delete">Delete</button>
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