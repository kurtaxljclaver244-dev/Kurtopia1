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
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $country = trim($_POST['country'] ?? 'USA');

    // Optional validation, e.g., if first_name empty, error, but since optional, maybe not.

    if (empty($errors)) {
        $stmt = $conn->prepare(
            'UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ?, city = ?, state = ?, zip_code = ?, country = ? WHERE id = ?'
        );
        if ($stmt) {
            $stmt->bind_param('ssssssssi', $first_name, $last_name, $phone, $address, $city, $state, $zip_code, $country, $user_id);
            if ($stmt->execute()) {
                $success = 'Profile updated successfully!';
            } else {
                $errors[] = 'Update failed: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }
}

// Fetch current data
$stmt = $conn->prepare('SELECT first_name, last_name, phone, address, city, state, zip_code, country FROM users WHERE id = ?');
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($first_name, $last_name, $phone, $address, $city, $state, $zip_code, $country);
    $stmt->fetch();
    $stmt->close();
} else {
    die('Database error: ' . $conn->error);
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
    <title>User Settings | Kurtopia Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .settings-container {
            width: 100%;
            max-width: 600px;
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #1f2937;
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        .subtitle {
            text-align: center;
            color: #6b7280;
            margin-bottom: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .input-group {
            display: flex;
            flex-direction: column;
        }

        .input-group.full-width {
            grid-column: span 2;
        }

        .input-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #374151;
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

        @media (max-width: 560px) {
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
    <div class="settings-container">
        <h1><i class="fas fa-user-cog"></i> User Settings</h1>
        <p class="subtitle">Complete your profile information</p>

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

        <form method="post" action="user_settings.php">
            <div class="form-grid">
                <div class="input-group">
                    <label><i class="fas fa-user"></i> First name</label>
                    <input type="text" name="first_name" value="<?php echo esc($first_name); ?>" placeholder="John">
                </div>
                <div class="input-group">
                    <label><i class="fas fa-user"></i> Last name</label>
                    <input type="text" name="last_name" value="<?php echo esc($last_name); ?>" placeholder="Doe">
                </div>
                
                <div class="input-group full-width">
                    <label><i class="fas fa-phone-alt"></i> Phone number</label>
                    <input type="tel" name="phone" value="<?php echo esc($phone); ?>" placeholder="+1 555 123 4567">
                </div>
                
                <div class="input-group full-width">
                    <label><i class="fas fa-map-marker-alt"></i> Street address</label>
                    <textarea name="address" placeholder="123 Main Street, Apt 4B"><?php echo esc($address); ?></textarea>
                </div>
                
                <div class="input-group">
                    <label><i class="fas fa-city"></i> City</label>
                    <input type="text" name="city" value="<?php echo esc($city); ?>" placeholder="New York">
                </div>
                <div class="input-group">
                    <label><i class="fas fa-building"></i> State</label>
                    <input type="text" name="state" value="<?php echo esc($state); ?>" placeholder="NY">
                </div>
                
                <div class="input-group">
                    <label><i class="fas fa-mail-bulk"></i> ZIP code</label>
                    <input type="text" name="zip_code" value="<?php echo esc($zip_code); ?>" placeholder="10001">
                </div>
                <div class="input-group">
                    <label><i class="fas fa-globe"></i> Country</label>
                    <input type="text" name="country" value="<?php echo esc($country); ?>" placeholder="USA">
                </div>
            </div>
            
            <button type="submit" class="button">
                <i class="fas fa-save"></i> Update Profile
            </button>
        </form>
        
        <div class="bottom-links">
            <a href="products.php" class="bottom-link"><i class="fas fa-home"></i> Back to Products</a>
        </div>
    </div>
</body>
</html>