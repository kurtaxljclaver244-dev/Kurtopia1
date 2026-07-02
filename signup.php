<?php
// signup.php - Complete User Registration & Login with all fields in container
session_start();
require_once 'db.php';

if (!$conn->select_db('claver')) {
    die('Database selection failed: ' . $conn->error);
}

$errors = [];
$success = '';
$active_tab = $_POST['active_tab'] ?? $_GET['tab'] ?? 'signup';

// Form field values for repopulation
$username = '';
$first_name = '';
$last_name = '';
$email = '';
$phone = '';
$address = '';
$city = '';
$state = '';
$zip_code = '';
$country = 'USA';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'login') {
        $active_tab = 'login';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '') {
            $errors[] = 'Username is required.';
        }
        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        if (empty($errors)) {
            $stmt = $conn->prepare('SELECT id, password_hash FROM users WHERE username = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows === 1) {
                    $stmt->bind_result($user_id, $password_hash);
                    $stmt->fetch();
                    if (password_verify($password, $password_hash)) {
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $username;
                        header('Location: products.php');
                        exit;
                    } else {
                        $errors[] = 'Invalid username or password.';
                    }
                } else {
                    $errors[] = 'Invalid username or password.';
                }
                $stmt->close();
            } else {
                $errors[] = 'Database error: ' . $conn->error;
            }
        }
    } else {
        // Signup action
        $active_tab = 'signup';
        $username = trim($_POST['username'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zip_code = trim($_POST['zip_code'] ?? '');
        $country = trim($_POST['country'] ?? 'USA');

        // Validation
        if ($username === '') {
            $errors[] = 'Username is required.';
        } elseif (!preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $username)) {
            $errors[] = 'Username must be 3-30 characters and contain only letters, numbers, dots, or underscores.';
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        if ($password === '') {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('ss', $username, $email);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $errors[] = 'A user with that username or email already exists.';
                }
                $stmt->close();
            } else {
                $errors[] = 'Database error: ' . $conn->error;
            }
        }

        if (empty($errors)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'customer';

            $stmt = $conn->prepare(
                'INSERT INTO users (email, password_hash, first_name, last_name, role, phone, address, city, state, zip_code, country, username)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            if ($stmt) {
                $stmt->bind_param(
                    'ssssssssssss',
                    $email,
                    $password_hash,
                    $first_name,
                    $last_name,
                    $role,
                    $phone,
                    $address,
                    $city,
                    $state,
                    $zip_code,
                    $country,
                    $username
                );
                if ($stmt->execute()) {
                    $_SESSION['user_id'] = $stmt->insert_id;
                    $_SESSION['username'] = $username;
                    header('Location: products.php');
                    exit;
                } else {
                    $errors[] = 'Registration failed: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = 'Database error: ' . $conn->error;
            }
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
    <title>Sign Up | Kurtopia Shop</title>
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
            background: linear-gradient(135deg, #f5f7fa 0%, #e9eef3 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }

        .signup-container {
            width: 100%;
            max-width: 580px;
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        /* Tab Buttons */
        .auth-header {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .tab-button {
            padding: 1rem;
            border: none;
            background: transparent;
            font-size: 1rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .tab-button.active {
            color: #2563eb;
            background: white;
        }

        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #2563eb;
            border-radius: 3px 3px 0 0;
        }

        /* Panel Wrapper */
        .panel-wrapper {
            padding: 2rem;
        }

        .panel {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .panel.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 1.75rem;
            border-left: 3px solid #2563eb;
            padding-left: 0.75rem;
        }

        /* Form Grid - Compact and Organized */
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
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-group label i {
            width: 1.25rem;
            color: #2563eb;
        }

        .input-group input,
        .input-group textarea {
            padding: 0.75rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            font-size: 0.875rem;
            font-family: inherit;
            transition: all 0.2s;
            background: #fefefe;
        }

        .input-group input:focus,
        .input-group textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .input-group textarea {
            resize: vertical;
            min-height: 70px;
        }

        /* Button */
        .button {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.2);
        }

        /* Alerts */
        .alerts {
            margin-bottom: 1.5rem;
        }

        .alert {
            padding: 0.875rem 1rem;
            border-radius: 14px;
            font-size: 0.8125rem;
            margin-bottom: 0.75rem;
        }

        .alert.error {
            background: #fef2f2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert.success {
            background: #f0fdf4;
            color: #16a34a;
            border-left: 4px solid #16a34a;
        }

        /* Bottom Links */
        .bottom-links {
            margin-top: 1.5rem;
            text-align: center;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .bottom-link {
            color: #64748b;
            text-decoration: none;
            font-size: 0.8125rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.2s;
        }

        .bottom-link:hover {
            color: #2563eb;
        }

        .switch-link {
            color: #2563eb;
            cursor: pointer;
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 560px) {
            body {
                padding: 1rem;
            }
            .panel-wrapper {
                padding: 1.5rem;
            }
            .form-grid {
                grid-template-columns: 1fr;
                gap: 0.875rem;
            }
            .full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <!-- Tab Header -->
        <div class="auth-header">
            <button type="button" id="signupTab" class="tab-button <?php echo $active_tab === 'signup' ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i> Sign Up
            </button>
            <button type="button" id="loginTab" class="tab-button <?php echo $active_tab === 'login' ? 'active' : ''; ?>">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </div>

        <div class="panel-wrapper">
            <!-- Display Errors/Success -->
            <?php if (!empty($errors)): ?>
                <div class="alerts">
                    <?php foreach ($errors as $error): ?>
                        <div class="alert error"><i class="fas fa-exclamation-circle"></i> <?php echo esc($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert success"><i class="fas fa-check-circle"></i> <?php echo esc($success); ?></div>
            <?php endif; ?>

            <!-- SIGNUP PANEL - ALL CREDENTIALS INSIDE CONTAINER -->
            <div id="signupPanel" class="panel <?php echo $active_tab === 'signup' ? 'active' : ''; ?>">
                <h1>Create an account</h1>
                <p class="subtitle">Sign up as a customer to get exclusive discounts at Kurtopia.</p>
                
                <form method="post" action="signup.php">
                    <input type="hidden" name="action" value="signup">
                    <input type="hidden" name="active_tab" value="signup">
                    
                    <div class="form-grid">
                        <!-- Username -->
                        <div class="input-group full-width">
                            <label><i class="fas fa-user-circle"></i> Username *</label>
                            <input type="text" name="username" value="<?php echo esc($username); ?>" placeholder="kurtopia_fan" required>
                        </div>
                        
                        <!-- Email -->
                        <div class="input-group full-width">
                            <label><i class="fas fa-envelope"></i> Email address *</label>
                            <input type="email" name="email" value="<?php echo esc($email); ?>" placeholder="hello@kurtopia.com" required>
                        </div>
                        
                        <!-- Password + Confirm -->
                        <div class="input-group">
                            <label><i class="fas fa-lock"></i> Password *</label>
                            <input type="password" name="password" placeholder="••••••••" required>
                        </div>
                        <div class="input-group">
                            <label><i class="fas fa-check-circle"></i> Confirm password *</label>
                            <input type="password" name="confirm_password" placeholder="••••••••" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="button">
                        <i class="fas fa-user-check"></i> Create account
                    </button>
                </form>
                
                <div class="bottom-links">
                    <span class="bottom-link">Already have an account? <span class="switch-link" id="switchToLogin">Login here</span></span>
                </div>
            </div>

            <!-- LOGIN PANEL -->
            <div id="loginPanel" class="panel <?php echo $active_tab === 'login' ? 'active' : ''; ?>">
                <h1>Welcome back</h1>
                <p class="subtitle">Log in with your username and password to continue shopping.</p>
                
                <form method="post" action="signup.php">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="active_tab" value="login">
                    
                    <div class="form-grid">
                        <div class="input-group full-width">
                            <label><i class="fas fa-user"></i> Username *</label>
                            <input type="text" name="username" value="<?php echo esc($username); ?>" placeholder="kurtopia_fan" required>
                        </div>
                        
                        <div class="input-group full-width">
                            <label><i class="fas fa-key"></i> Password *</label>
                            <input type="password" name="password" placeholder="••••••••" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="button">
                        <i class="fas fa-arrow-right-to-bracket"></i> Log In
                    </button>
                </form>
                
                <div class="bottom-links">
                    <span class="bottom-link">Don't have an account? <span class="switch-link" id="switchToSignup">Sign up here</span></span>
                </div>
            </div>

            <!-- Back to Home Link (as per image) -->
            <div class="bottom-links" style="margin-top: 0.75rem; border-top: none;">
                <a href="index.php" class="bottom-link">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
            <a href="admin_login.php" class="bottom-link">
                <i class="fas fa-shield-alt"></i> 
            </a>
        </div>
    </div>

    <script>
        // Tab switching
        const signupTab = document.getElementById('signupTab');
        const loginTab = document.getElementById('loginTab');
        const signupPanel = document.getElementById('signupPanel');
        const loginPanel = document.getElementById('loginPanel');
        const switchToLogin = document.getElementById('switchToLogin');
        const switchToSignup = document.getElementById('switchToSignup');

        function setActiveTab(tab) {
            if (tab === 'login') {
                signupTab.classList.remove('active');
                loginTab.classList.add('active');
                signupPanel.classList.remove('active');
                loginPanel.classList.add('active');
            } else {
                signupTab.classList.add('active');
                loginTab.classList.remove('active');
                signupPanel.classList.add('active');
                loginPanel.classList.remove('active');
            }
        }

        signupTab.addEventListener('click', () => setActiveTab('signup'));
        loginTab.addEventListener('click', () => setActiveTab('login'));
        
        if (switchToLogin) {
            switchToLogin.addEventListener('click', () => setActiveTab('login'));
        }
        if (switchToSignup) {
            switchToSignup.addEventListener('click', () => setActiveTab('signup'));
        }

        // Preserve active tab on form error by reading URL or server-side value
        const currentTab = '<?php echo $active_tab; ?>';
        setActiveTab(currentTab);
    </script>
</body>
</html>