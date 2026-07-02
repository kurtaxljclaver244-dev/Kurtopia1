<?php
// create_admin.php - Run this file once to create an admin user
session_start();
require_once 'db.php';

if (!$conn->select_db('claver')) {
    die('Database selection failed: ' . $conn->error);
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if ($username === '') {
        $error = 'Username is required.';
    } elseif ($email === '') {
        $error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password === '') {
        $error = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    }
    
    if (empty($error)) {
        // Check if user already exists
        $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = 'A user with that username or email already exists.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare('INSERT INTO users (username, email, password_hash, role, first_name, last_name) VALUES (?, ?, ?, "admin", ?, ?)');
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $stmt->bind_param('sssss', $username, $email, $password_hash, $first_name, $last_name);
            
            if ($stmt->execute()) {
                $message = 'Admin user created successfully! You can now login at <a href="admin_login.php">admin_login.php</a>';
            } else {
                $error = 'Failed to create admin user: ' . $stmt->error;
            }
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
    <title>Create Admin User | Kurtopia Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        
        .container {
            background: white;
            border-radius: 28px;
            max-width: 500px;
            width: 100%;
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }
        
        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .subtitle {
            color: #64748b;
            margin-bottom: 1.5rem;
        }
        
        .input-group {
            margin-bottom: 1rem;
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
            padding: 0.75rem 1rem;
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
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        button {
            width: 100%;
            background: #1e293b;
            color: white;
            border: none;
            padding: 0.875rem;
            border-radius: 40px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 1rem;
        }
        
        button:hover {
            background: #0f172a;
            transform: translateY(-2px);
        }
        
        .message {
            background: #d1fae5;
            color: #065f46;
            padding: 0.75rem 1rem;
            border-radius: 14px;
            margin-bottom: 1rem;
        }
        
        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.75rem 1rem;
            border-radius: 14px;
            margin-bottom: 1rem;
        }
        
        .warning {
            background: #fef3c7;
            color: #d97706;
            padding: 0.75rem 1rem;
            border-radius: 14px;
            margin-bottom: 1rem;
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
        
        @media (max-width: 560px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create Admin User</h1>
        <p class="subtitle">Set up the first administrator account</p>
        
        <div class="warning">
            <i class="fas fa-shield-alt"></i> 
            <strong>Security Note:</strong> Delete this file after creating the admin user!
        </div>
        
        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo esc($error); ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="input-group">
                <label>Username *</label>
                <input type="text" name="username" placeholder="admin" required>
            </div>
            
            <div class="input-group">
                <label>Email *</label>
                <input type="email" name="email" placeholder="admin@example.com" required>
            </div>
            
            <div class="form-row">
                <div class="input-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" placeholder="Admin">
                </div>
                <div class="input-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" placeholder="User">
                </div>
            </div>
            
            <div class="input-group">
                <label>Password *</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            
            <div class="input-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" placeholder="••••••••" required>
            </div>
            
            <button type="submit">Create Admin User</button>
        </form>
        
        <div class="back-link">
            <a href="index.php">← Back to Shop</a>
        </div>
    </div>
</body>
</html>