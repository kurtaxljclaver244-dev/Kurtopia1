<?php
// admin_users.php - User Management
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

// Update user role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $user_id = intval($_POST['user_id']);
    $role = $_POST['role'];
    
    $stmt = $conn->prepare('UPDATE users SET role = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('si', $role, $user_id);
        if ($stmt->execute()) {
            $message = 'User role updated successfully!';
        }
        $stmt->close();
    }
}

// Delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    
    $stmt = $conn->prepare('DELETE FROM users WHERE id = ? AND role = "customer"');
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        if ($stmt->execute()) {
            $message = 'User deleted successfully!';
        }
        $stmt->close();
    }
}

// Fetch all users
$users = [];
$result = $conn->query('SELECT id, username, email, first_name, last_name, role, created_at FROM users ORDER BY created_at DESC');
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
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
    <title>User Management | Kurtopia Shop Admin</title>
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

        .users-table {
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

        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .role-admin {
            background: #dbeafe;
            color: #2563eb;
        }

        .role-customer {
            background: #f1f5f9;
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
            <a href="admin_users.php" class="nav-item active">
                <i class="fas fa-users"></i> Users
            </a>
            <a href="admin_categories.php" class="nav-item">
                <i class="fas fa-tags"></i> Categories
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <h1 class="page-title">User Management</h1>
            <form method="post" action="logout.php" style="display: inline;">
                <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </form>
        </div>

        <?php if ($message): ?>
            <div class="message"><?php echo esc($message); ?></div>
        <?php endif; ?>

        <div class="users-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><strong><?php echo esc($user['username']); ?></strong></td>
                            <td><?php echo esc($user['email']); ?></td>
                            <td><?php echo esc($user['first_name'] . ' ' . $user['last_name']); ?></td>
                            <td>
                                <span class="role-badge <?php echo $user['role'] === 'admin' ? 'role-admin' : 'role-customer'; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if ($user['role'] !== 'admin'): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <select name="role" style="padding: 0.25rem; border-radius: 6px; border: 1px solid #e2e8f0;">
                                            <option value="customer" <?php echo $user['role'] === 'customer' ? 'selected' : ''; ?>>Customer</option>
                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                        <button type="submit" name="update_role" style="background: #3b82f6; color: white; border: none; padding: 0.25rem 0.5rem; border-radius: 6px; cursor: pointer;">Update</button>
                                    </form>
                                    <form method="post" style="display: inline; margin-left: 0.5rem;" onsubmit="return confirm('Delete this user?')">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="delete_user" class="btn-delete">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">Protected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>