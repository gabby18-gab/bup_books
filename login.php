<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: user/index.php');
    exit();
}
if (isset($_SESSION['admin_id'])) {
    header('Location: admin/dashboard.php');
    exit();
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? 'user'; // 'user' or 'admin'
    
    // Basic validation
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=bup_books", "root", "");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            if ($user_type === 'admin') {
                // ADMIN LOGIN
                $adminStmt = $pdo->prepare("SELECT AdminID, Name, Email, Password, Role FROM admin WHERE Email = ? AND Status = 'A'");
                $adminStmt->execute([$email]);
                $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if password is hashed or plain text (for backward compatibility)
                if ($admin) {
                    $valid = false;
                    
                    if (strpos($admin['Password'], '$2y$') === 0) {
                        // Hashed password
                        $valid = password_verify($password, $admin['Password']);
                    } else {
                        // Plain text password (from SQL dump)
                        $valid = ($password === $admin['Password']);
                        
                        // If valid with plain text, hash it for future use
                        if ($valid) {
                            $hashed = password_hash($password, PASSWORD_DEFAULT);
                            $updateStmt = $pdo->prepare("UPDATE admin SET Password = ? WHERE AdminID = ?");
                            $updateStmt->execute([$hashed, $admin['AdminID']]);
                        }
                    }
                    
                    if ($valid) {
                        // Admin login successful
                        $_SESSION['admin_id'] = $admin['AdminID'];
                        $_SESSION['admin_name'] = $admin['Name'];
                        $_SESSION['admin_email'] = $admin['Email'];
                        $_SESSION['admin_role'] = $admin['Role'];
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['user_type'] = 'admin';
                        
                        // Update last login
                        $updateStmt = $pdo->prepare("UPDATE admin SET LastLogin = NOW() WHERE AdminID = ?");
                        $updateStmt->execute([$admin['AdminID']]);
                        
                        // Log the login
                        try {
                            $logStmt = $pdo->prepare("INSERT INTO admin_logs (AdminID, Action, Details, IPAddress) VALUES (?, 'login', 'Admin logged in', ?)");
                            $logStmt->execute([$admin['AdminID'], $_SERVER['REMOTE_ADDR']]);
                        } catch (PDOException $e) {
                            // Log table might not exist yet, continue anyway
                        }
                        
                        header('Location: admin/dashboard.php');
                        exit();
                    } else {
                        $error = 'Invalid email or password!';
                    }
                } else {
                    $error = 'Admin not found or inactive!';
                }
            } else {
                // USER LOGIN
                $userStmt = $pdo->prepare("SELECT UserID, Name, Email, Password, Address, ContactNo FROM users WHERE Email = ?");
                $userStmt->execute([$email]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['Password'])) {
                    // User login successful
                    $_SESSION['user_id'] = $user['UserID'];
                    $_SESSION['user_name'] = $user['Name'];
                    $_SESSION['user_email'] = $user['Email'];
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_type'] = 'user';
                    
                    // Check if user is also a seller
                    try {
                        $sellerStmt = $pdo->prepare("SELECT SellerID FROM seller WHERE UserID = ?");
                        $sellerStmt->execute([$user['UserID']]);
                        $seller = $sellerStmt->fetch(PDO::FETCH_ASSOC);
                        if ($seller) {
                            $_SESSION['seller_id'] = $seller['SellerID'];
                        }
                    } catch (PDOException $e) {
                        // Seller table might not have UserID column yet
                    }
                    
                    $success = true;
                } else {
                    $error = 'Invalid email or password!';
                }
            }
            
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Login failed. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BUP BOOKS - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --bup-blue: #0A3143;
            --bup-blue-light: #1C4E6C;
            --bup-orange: #FF914D;
            --bup-orange-dark: #E67A3A;
            --bup-yellow: #FFC107;
            --bup-white: #ffffff;
            --bup-gray: #5A6C74;
            --bup-gray-light: #E1E9F0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(145deg, var(--bup-blue), var(--bup-blue-light));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 500px;
        }

        .login-card {
            background: var(--bup-white);
            border-radius: 30px;
            padding: 50px 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255, 145, 77, 0.1), rgba(255, 193, 7, 0.05));
            border-radius: 50%;
            z-index: 0;
        }

        .login-header {
            text-align: center;
            margin-bottom: 35px;
            position: relative;
            z-index: 1;
        }

        .logo {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .logo-wrapper {
            background: linear-gradient(145deg, var(--bup-blue), var(--bup-blue-light));
            padding: 15px 30px;
            border-radius: 20px;
            margin-bottom: 10px;
            position: relative;
            display: inline-block;
        }

        .logo-wrapper::before {
            content: '';
            position: absolute;
            top: 4px;
            left: 4px;
            right: -4px;
            bottom: -4px;
            background: linear-gradient(145deg, var(--bup-orange), var(--bup-yellow));
            border-radius: 22px;
            z-index: -1;
            opacity: 0.6;
        }

        .logo-text {
            font-size: 42px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--bup-orange), var(--bup-yellow));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 4px;
            line-height: 1;
        }

        .logo-sub {
            font-size: 16px;
            font-weight: 600;
            color: white;
            letter-spacing: 2px;
        }

        h1 {
            color: var(--bup-blue);
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .login-header p {
            color: var(--bup-gray);
            font-size: 15px;
        }

        /* User Type Selector */
        .user-type-selector {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
            background: var(--bup-gray-light);
            padding: 10px;
            border-radius: 50px;
            position: relative;
            z-index: 1;
        }

        .user-type-option {
            flex: 1;
            text-align: center;
            padding: 12px 20px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.3s ease;
            color: var(--bup-gray);
        }

        .user-type-option.active {
            background: linear-gradient(145deg, var(--bup-orange), var(--bup-yellow));
            color: var(--bup-blue);
            box-shadow: 0 5px 0 #C7511E;
        }

        .user-type-option i {
            margin-right: 8px;
            font-size: 18px;
        }

        /* Input styles */
        .input-group {
            position: relative;
            margin-bottom: 25px;
            z-index: 1;
        }

        .input-group input {
            width: 100%;
            border: 2px solid var(--bup-gray-light);
            border-radius: 16px;
            padding: 18px 20px 10px;
            font-size: 16px;
            outline: none;
            transition: all 0.3s ease;
            background: transparent;
        }

        .input-group input:focus {
            border-color: var(--bup-orange);
            box-shadow: 0 0 0 5px rgba(255, 145, 77, 0.1);
        }

        .input-group label {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--bup-gray);
            transition: all 0.2s ease;
            background: white;
            padding: 0 5px;
            pointer-events: none;
            font-size: 15px;
        }

        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label {
            top: 0;
            font-size: 13px;
            font-weight: 600;
            color: var(--bup-orange);
        }

        .input-group input::placeholder {
            color: transparent;
        }

        /* Error state */
        .input-group.error input {
            border-color: #dc3545;
            background: #fff8f8;
        }

        .input-group.error label {
            color: #dc3545;
        }

        .error-message {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: none;
            font-weight: 500;
        }

        .error-message.show {
            display: block;
        }

        /* Form options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 14px;
            z-index: 1;
            position: relative;
        }

        .form-options label {
            color: var(--bup-gray);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-options input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--bup-orange);
        }

        .forgot-link {
            color: var(--bup-orange);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .forgot-link:hover {
            color: var(--bup-blue);
            text-decoration: underline;
        }

        /* Submit button */
        .submit-btn {
            width: 100%;
            background: linear-gradient(145deg, var(--bup-orange), var(--bup-yellow));
            color: var(--bup-blue);
            border: none;
            border-radius: 16px;
            padding: 16px;
            font-size: 18px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 0 #C7511E;
            position: relative;
            z-index: 1;
            letter-spacing: 1px;
        }

        .submit-btn:hover {
            transform: translateY(3px);
            box-shadow: 0 5px 0 #C7511E;
        }

        .submit-btn:active {
            transform: translateY(5px);
            box-shadow: 0 3px 0 #C7511E;
        }

        /* Loading state */
        .submit-btn.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .submit-btn.loading::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            top: 50%;
            right: 20px;
            transform: translateY(-50%);
            border: 3px solid var(--bup-blue);
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: translateY(-50%) rotate(360deg); }
        }

        /* Divider */
        .divider {
            text-align: center;
            margin: 30px 0 20px;
            position: relative;
            z-index: 1;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--bup-gray-light);
            z-index: -1;
        }

        .divider span {
            background: white;
            padding: 0 20px;
            color: var(--bup-gray);
            font-size: 14px;
            font-weight: 600;
        }

        /* Sign up link */
        .signup-link {
            text-align: center;
            z-index: 1;
            position: relative;
            font-size: 15px;
            color: var(--bup-gray);
        }

        .signup-link a {
            color: var(--bup-orange);
            font-weight: 700;
            text-decoration: none;
            margin-left: 5px;
            transition: color 0.3s;
        }

        .signup-link a:hover {
            color: var(--bup-blue);
            text-decoration: underline;
        }

        /* PHP Error message */
        .php-error {
            background: #fff3f3;
            color: #dc3545;
            padding: 15px 20px;
            border-radius: 16px;
            margin-bottom: 25px;
            border-left: 5px solid #dc3545;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1;
            position: relative;
        }

        .php-error i {
            font-size: 20px;
        }

        /* Success message */
        .success-message {
            text-align: center;
            padding: 30px 20px;
            z-index: 1;
            position: relative;
        }

        .success-message .success-icon {
            margin-bottom: 20px;
        }

        .success-message h3 {
            color: var(--bup-blue);
            font-weight: 800;
            margin-bottom: 10px;
            font-size: 24px;
        }

        .success-message p {
            color: var(--bup-gray);
            margin-bottom: 0;
        }

        /* Admin/User badges */
        .role-badge {
            display: inline-block;
            background: linear-gradient(145deg, #FFE5D0, #FFF0D0);
            color: var(--bup-orange-dark);
            padding: 6px 15px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            margin-top: 10px;
            border: 1px solid var(--bup-orange);
        }

        .role-badge i {
            margin-right: 5px;
        }

        /* Back to home link */
        .back-home {
            text-align: center;
            margin-top: 20px;
            z-index: 1;
            position: relative;
        }

        .back-home a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s;
        }

        .back-home a:hover {
            color: var(--bup-yellow);
        }

        .back-home i {
            margin-right: 5px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 35px 25px;
            }

            .logo-text {
                font-size: 36px;
            }

            h1 {
                font-size: 24px;
            }
            
            .user-type-option i {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <div class="logo-wrapper">
                        <div class="logo-text">BUP</div>
                        <div class="logo-sub">Book Resale</div>
                    </div>
                </div>
                <h1>Welcome Back!</h1>
                <p>Sign in to your BUP Books account</p>
                <div class="role-badge">
                    <i class="bi bi-shield-lock"></i> Demo Admin: gabgab@gmail.com / gabgab@123
                </div>
            </div>

            <?php if ($success): ?>
                <div class="success-message" id="successMessage">
                    <div class="success-icon">
                        <svg width="70" height="70" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="12" fill="#FFC107"/>
                            <path d="M8 12l3 3 5-5" stroke="#0A3143" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>!</h3>
                    <p>Redirecting to your dashboard...</p>
                </div>
                <script>
                    setTimeout(function() {
                        window.location.href = 'user/index.php';
                    }, 2000);
                </script>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="php-error">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <!-- User Type Selector -->
                    <div class="user-type-selector">
                        <div class="user-type-option <?php echo (!isset($_POST['user_type']) || $_POST['user_type'] == 'user') ? 'active' : ''; ?>" onclick="selectUserType('user')" id="userTypeUser">
                            <i class="bi bi-person"></i> User
                        </div>
                        <div class="user-type-option <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'admin') ? 'active' : ''; ?>" onclick="selectUserType('admin')" id="userTypeAdmin">
                            <i class="bi bi-shield-lock"></i> Admin
                        </div>
                    </div>
                    
                    <input type="hidden" name="user_type" id="userTypeInput" value="<?php echo isset($_POST['user_type']) ? htmlspecialchars($_POST['user_type']) : 'user'; ?>">

                    <div class="input-group">
                        <input type="email" id="email" name="email" required placeholder=" " 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <label for="email">Email address</label>
                        <span class="error-message" id="emailError"></span>
                    </div>

                    <div class="input-group">
                        <input type="password" id="password" name="password" required placeholder=" ">
                        <label for="password">Password</label>
                        <span class="error-message" id="passwordError"></span>
                    </div>

                    <div class="form-options">
                        <label>
                            <input type="checkbox" name="remember" id="remember">
                            <span>Remember me</span>
                        </label>
                        <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn">
                        <span>Sign In</span>
                    </button>
                </form>

                <div class="divider">
                    <span>New to BUP Books?</span>
                </div>

                <div class="signup-link">
                    <span>Don't have an account?</span>
                    <a href="register.php">Sign up as Student</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="back-home">
            <a href="index.php"><i class="bi bi-arrow-left"></i> Back to Home</a>
        </div>
    </div>

    <script>
        // User type selection
        function selectUserType(type) {
            document.getElementById('userTypeInput').value = type;
            
            // Update active class
            document.getElementById('userTypeUser').classList.remove('active');
            document.getElementById('userTypeAdmin').classList.remove('active');
            
            if (type === 'admin') {
                document.getElementById('userTypeAdmin').classList.add('active');
            } else {
                document.getElementById('userTypeUser').classList.add('active');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            
            if (form) {
                const emailInput = document.getElementById('email');
                const passwordInput = document.getElementById('password');
                const submitBtn = document.getElementById('submitBtn');

                // Clear error on input
                function clearError(input) {
                    const inputGroup = input.closest('.input-group');
                    const errorElement = inputGroup.querySelector('.error-message');
                    inputGroup.classList.remove('error');
                    if (errorElement) {
                        errorElement.classList.remove('show');
                        errorElement.textContent = '';
                    }
                }

                // Show error
                function showError(input, message) {
                    const inputGroup = input.closest('.input-group');
                    const errorElement = inputGroup.querySelector('.error-message');
                    inputGroup.classList.add('error');
                    if (errorElement) {
                        errorElement.textContent = message;
                        errorElement.classList.add('show');
                    }
                }

                // Email validation
                function validateEmail(email) {
                    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    return re.test(email);
                }

                // Input event listeners
                emailInput.addEventListener('input', function() {
                    clearError(this);
                });

                passwordInput.addEventListener('input', function() {
                    clearError(this);
                });

                // Form submit validation
                form.addEventListener('submit', function(e) {
                    let isValid = true;
                    
                    clearError(emailInput);
                    clearError(passwordInput);

                    // Validate email
                    if (!emailInput.value.trim()) {
                        showError(emailInput, 'Email is required');
                        isValid = false;
                    } else if (!validateEmail(emailInput.value.trim())) {
                        showError(emailInput, 'Please enter a valid email address');
                        isValid = false;
                    }

                    // Validate password
                    if (!passwordInput.value) {
                        showError(passwordInput, 'Password is required');
                        isValid = false;
                    } else if (passwordInput.value.length < 6) {
                        showError(passwordInput, 'Password must be at least 6 characters');
                        isValid = false;
                    }

                    if (!isValid) {
                        e.preventDefault();
                        return false;
                    }

                    // Show loading state
                    if (submitBtn) {
                        submitBtn.classList.add('loading');
                    }
                    
                    return true;
                });

                // Focus/blur effects
                const inputs = document.querySelectorAll('.input-group input');
                inputs.forEach(input => {
                    if (input.value) {
                        input.setAttribute('placeholder', ' ');
                    }
                    
                    input.addEventListener('focus', function() {
                        this.parentElement.classList.add('focused');
                    });
                    
                    input.addEventListener('blur', function() {
                        this.parentElement.classList.remove('focused');
                    });
                });
            }

            // Auto-hide PHP error after 5 seconds
            const phpError = document.querySelector('.php-error');
            if (phpError) {
                setTimeout(() => {
                    phpError.style.opacity = '0';
                    phpError.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        phpError.style.display = 'none';
                    }, 500);
                }, 5000);
            }

            console.log('✓ Login page loaded successfully');
        });
    </script>
</body>
</html>