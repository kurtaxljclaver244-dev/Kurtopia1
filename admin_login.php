<?php
// admin_login.php - Admin Authentication
session_start();
require_once 'db.php';

if (!$conn->select_db('claver')) {
    die('Database selection failed: ' . $conn->error);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare('SELECT id, password_hash, role FROM users WHERE username = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 1) {
                $stmt->bind_result($user_id, $password_hash, $role);
                $stmt->fetch();
                if (password_verify($password, $password_hash) && $role === 'admin') {
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = 'admin';
                    header('Location: admin_dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid credentials or insufficient privileges.';
                }
            } else {
                $error = 'Invalid credentials.';
            }
            $stmt->close();
        } else {
            $error = 'Database error: ' . $conn->error;
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
    <title>Admin Login | Kurtopia Shop</title>
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
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }

        .login-container {
            background: white;
            border-radius: 28px;
            max-width: 450px;
            width: 100%;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo h1 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #1e293b, #3b82f6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .logo p {
            color: #64748b;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: #64748b;
            margin-bottom: 2rem;
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #334155;
        }

        .input-group input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .input-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn-login {
            width: 100%;
            background: #1e293b;
            color: white;
            border: none;
            padding: 0.875rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-login:hover {
            background: #0f172a;
            transform: translateY(-2px);
        }

        .error {
            background: #fef2f2;
            color: #dc2626;
            padding: 0.75rem 1rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }

        .back-link a {
            color: #64748b;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .back-link a:hover {
            color: #3b82f6;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>✨ Kurtopia Shop</h1>
            <p>Admin Panel</p>
        </div>
        <h2>Admin Login</h2>
        <p class="subtitle">Enter your credentials to access the dashboard</p>

        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?php echo esc($error); ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="input-group">
                <label><i class="fas fa-user"></i> Username</label>
                <input type="text" name="username" placeholder="admin_username" required>
            </div>
            <div class="input-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-login">
                <i class="fas fa-arrow-right-to-bracket"></i> Login
            </button>
        </form>

        <div class="back-link">
            <a href="index.php"><i class="fas fa-arrow-left"></i> Back to Shop</a>
        </div>
    </div>
</body>
</html>