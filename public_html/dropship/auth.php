<?php
require_once 'config.php';

$error = '';
$success = '';

// Handle registration
if (isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM dropship_customers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Email already registered';
        } else {
            // Create account
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO dropship_customers (name, email, password_hash, active) VALUES (?, ?, ?, 1)");
            $stmt->bind_param("sss", $name, $email, $password_hash);
            
            if ($stmt->execute()) {
                $success = 'Account created! Please login.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

// Handle login
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        $stmt = $conn->prepare("SELECT id, name, email, password_hash, active FROM dropship_customers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = 'Invalid email or password';
        } else {
            $customer = $result->fetch_assoc();
            
            if (!$customer['active']) {
                $error = 'Account is inactive. Please contact support.';
            } elseif (!password_verify($password, $customer['password_hash'])) {
                $error = 'Invalid email or password';
            } else {
                // Login successful
                $_SESSION['dropship_customer_id'] = $customer['id'];
                $_SESSION['dropship_customer_name'] = $customer['name'];
                $_SESSION['dropship_customer_email'] = $customer['email'];
                
                header('Location: products.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Register - CosmicTRD Dropshipping</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 900px;
            width: 100%;
            overflow: hidden;
            display: flex;
        }
        .auth-form {
            flex: 1;
            padding: 50px;
        }
        .auth-form h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .auth-form p {
            color: #666;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #333;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: border 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .toggle-form {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .toggle-form a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        .side-panel {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .side-panel h1 {
            font-size: 36px;
            margin-bottom: 20px;
        }
        .side-panel p {
            font-size: 18px;
            line-height: 1.6;
            opacity: 0.9;
        }
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .side-panel { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="side-panel">
            <h1>🚀 CosmicTRD</h1>
            <p>Start your dropshipping business today! Import products to your Shopify store with one click and fulfill orders automatically.</p>
            <ul style="margin-top: 30px; list-style: none; line-height: 2;">
                <li>✓ Thousands of products</li>
                <li>✓ One-click import to Shopify</li>
                <li>✓ Automated order fulfillment</li>
                <li>✓ Real-time inventory sync</li>
            </ul>
        </div>
        
        <div class="auth-form" id="loginForm">
            <h2>Welcome Back!</h2>
            <p>Login to your dropshipping account</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                
                <button type="submit" name="login" class="btn">Login</button>
                
                <div class="toggle-form">
                    Don't have an account? <a href="#" onclick="toggleForms(); return false;">Register here</a>
                </div>
            </form>
        </div>
        
        <div class="auth-form" id="registerForm" style="display: none;">
            <h2>Create Account</h2>
            <p>Start your dropshipping journey</p>
            
            <?php if ($error && isset($_POST['register'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required>
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                
                <button type="submit" name="register" class="btn">Create Account</button>
                
                <div class="toggle-form">
                    Already have an account? <a href="#" onclick="toggleForms(); return false;">Login here</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleForms() {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            
            if (loginForm.style.display === 'none') {
                loginForm.style.display = 'block';
                registerForm.style.display = 'none';
            } else {
                loginForm.style.display = 'none';
                registerForm.style.display = 'block';
            }
        }
        
        <?php if (isset($_POST['register'])): ?>
        toggleForms();
        <?php endif; ?>
    </script>
</body>
</html>
