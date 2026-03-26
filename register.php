<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: user/index.php');
    exit();
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strcasecmp($email, 'BUP_BOOKS@GMAIL.COM') == 0) {
        $error = 'This email is reserved for admin. Please use another email.';
    } else {
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=bup_books", "root", "");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check if email already exists
            $checkStmt = $pdo->prepare("SELECT UserID FROM users WHERE Email = ?");
            $checkStmt->execute([$email]);
            
            if ($checkStmt->fetch()) {
                $error = 'An account with this email already exists.';
            } else {
                // Hash password and insert new user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $insertStmt = $pdo->prepare("INSERT INTO users (Name, Email, Password) VALUES (?, ?, ?)");
                $insertStmt->execute([$name, $email, $hashedPassword]);
                
                $success = true;
                
                // Auto-login after registration
                $newUserId = $pdo->lastInsertId();
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['logged_in'] = true;
            }
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = 'Registration failed. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BUP BOOKS - Create Account</title>
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

        .register-container {
            width: 100%;
            max-width: 500px;
        }

        .register-card {
            background: var(--bup-white);
            border-radius: 30px;
            padding: 50px 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .register-card::before {
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

        .register-header {
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

        .register-header p {
            color: var(--bup-gray);
            font-size: 15px;
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

        /* Password strength */
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: var(--bup-gray-light);
            border-radius: 4px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s ease, background 0.3s ease;
        }

        .strength-text {
            display: block;
            font-size: 11px;
            color: var(--bup-gray);
            margin-top: 5px;
            text-align: right;
        }

        /* Checkbox */
        .terms-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 28px;
            font-size: 13px;
            z-index: 1;
            position: relative;
        }

        .terms-group input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--bup-orange);
        }

        .terms-group label {
            color: var(--bup-gray);
            margin: 0;
        }

        .terms-group a {
            color: var(--bup-orange);
            text-decoration: none;
            font-weight: 600;
        }

        .terms-group a:hover {
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

        /* Login link */
        .login-link {
            text-align: center;
            z-index: 1;
            position: relative;
            font-size: 15px;
            color: var(--bup-gray);
        }

        .login-link a {
            color: var(--bup-orange);
            font-weight: 700;
            text-decoration: none;
            transition: color 0.3s;
        }

        .login-link a:hover {
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
            .register-card {
                padding: 35px 25px;
            }

            .logo-text {
                font-size: 36px;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="logo">
                    <div class="logo-wrapper">
                        <div class="logo-text">BUP</div>
                        <div class="logo-sub">Book Resale</div>
                    </div>
                </div>
                <h1>Create Account</h1>
                <p>Join the BUP Books community</p>
            </div>

            <?php if ($success): ?>
                <div class="success-message" id="successMessage">
                    <div class="success-icon">
                        <svg width="70" height="70" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="12" fill="#FFC107"/>
                            <path d="M8 12l3 3 5-5" stroke="#0A3143" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <h3>Welcome, <?php echo htmlspecialchars($name); ?>!</h3>
                    <p>Your account has been created successfully.<br>Redirecting to your dashboard...</p>
                </div>
                <script>
                    setTimeout(function() {
                        window.location.href = 'user/index.php';
                    }, 2500);
                </script>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="php-error">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form class="register-form" method="POST" action="" id="registerForm">
                    <div class="input-group">
                        <input type="text" id="name" name="name" required autocomplete="name" placeholder=" "
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        <label for="name">Full name</label>
                        <span class="error-message" id="nameError"></span>
                    </div>

                    <div class="input-group">
                        <input type="email" id="email" name="email" required autocomplete="email" placeholder=" "
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <label for="email">Email address</label>
                        <span class="error-message" id="emailError"></span>
                    </div>

                    <div class="input-group">
                        <input type="password" id="password" name="password" required autocomplete="new-password" placeholder=" ">
                        <label for="password">Password</label>
                        <span class="error-message" id="passwordError"></span>
                        <div class="password-strength">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                        <span class="strength-text" id="strengthText">Enter a password</span>
                    </div>

                    <div class="input-group">
                        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password" placeholder=" ">
                        <label for="confirm_password">Confirm password</label>
                        <span class="error-message" id="confirmError"></span>
                    </div>

                    <div class="terms-group">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a></label>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn">
                        <span class="btn-text">Create account</span>
                    </button>
                </form>

                <div class="divider">
                    <span>Already have an account?</span>
                </div>

                <div class="login-link">
                    <a href="login.php">Sign in to your account</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="back-home">
            <a href="index.php"><i class="bi bi-arrow-left"></i> Back to Home</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            
            if (form) {
                const submitBtn = document.getElementById('submitBtn');
                const nameInput = document.getElementById('name');
                const emailInput = document.getElementById('email');
                const passwordInput = document.getElementById('password');
                const confirmInput = document.getElementById('confirm_password');
                const termsCheckbox = document.getElementById('terms');
                const strengthBar = document.getElementById('strengthBar');
                const strengthText = document.getElementById('strengthText');

                const inputs = document.querySelectorAll('.input-group input');
                inputs.forEach(input => {
                    if (input.value) {
                        input.setAttribute('placeholder', ' ');
                    }
                });

                function clearError(input) {
                    const inputGroup = input.closest('.input-group');
                    if (inputGroup) {
                        const errorElement = inputGroup.querySelector('.error-message');
                        inputGroup.classList.remove('error');
                        if (errorElement) {
                            errorElement.classList.remove('show');
                            errorElement.textContent = '';
                        }
                    }
                }

                function showError(input, message) {
                    const inputGroup = input.closest('.input-group');
                    if (inputGroup) {
                        const errorElement = inputGroup.querySelector('.error-message');
                        inputGroup.classList.add('error');
                        if (errorElement) {
                            errorElement.textContent = message;
                            errorElement.classList.add('show');
                        }
                    }
                }

                function checkPasswordStrength(password) {
                    let strength = 0;
                    let feedback = '';
                    
                    if (password.length >= 6) strength += 1;
                    if (password.length >= 8) strength += 1;
                    if (/[a-z]/.test(password)) strength += 1;
                    if (/[A-Z]/.test(password)) strength += 1;
                    if (/[0-9]/.test(password)) strength += 1;
                    if (/[^a-zA-Z0-9]/.test(password)) strength += 1;
                    
                    if (strength <= 2) {
                        feedback = 'Weak';
                        strengthBar.style.background = '#E64A19';
                        strengthBar.style.width = '33%';
                    } else if (strength <= 4) {
                        feedback = 'Moderate';
                        strengthBar.style.background = '#FFB347';
                        strengthBar.style.width = '66%';
                    } else {
                        feedback = 'Strong';
                        strengthBar.style.background = '#28A745';
                        strengthBar.style.width = '100%';
                    }
                    
                    if (password.length === 0) {
                        feedback = 'Enter a password';
                        strengthBar.style.width = '0';
                    }
                    
                    strengthText.textContent = feedback;
                }

                function validateEmail(email) {
                    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    return re.test(email);
                }

                if (passwordInput) {
                    passwordInput.addEventListener('input', function() {
                        clearError(this);
                        checkPasswordStrength(this.value);
                        
                        if (confirmInput.value) {
                            if (this.value !== confirmInput.value) {
                                showError(confirmInput, 'Passwords do not match');
                            } else {
                                clearError(confirmInput);
                            }
                        }
                    });
                }

                if (confirmInput) {
                    confirmInput.addEventListener('input', function() {
                        clearError(this);
                        if (this.value && passwordInput.value !== this.value) {
                            showError(this, 'Passwords do not match');
                        }
                    });
                }

                nameInput.addEventListener('input', function() { clearError(this); });
                emailInput.addEventListener('input', function() { clearError(this); });

                form.addEventListener('submit', function(e) {
                    let isValid = true;
                    
                    clearError(nameInput);
                    clearError(emailInput);
                    clearError(passwordInput);
                    clearError(confirmInput);

                    if (!nameInput.value.trim()) {
                        showError(nameInput, 'Full name is required');
                        isValid = false;
                    }

                    if (!emailInput.value) {
                        showError(emailInput, 'Email is required');
                        isValid = false;
                    } else if (!validateEmail(emailInput.value)) {
                        showError(emailInput, 'Please enter a valid email address');
                        isValid = false;
                    }

                    if (!passwordInput.value) {
                        showError(passwordInput, 'Password is required');
                        isValid = false;
                    } else if (passwordInput.value.length < 6) {
                        showError(passwordInput, 'Password must be at least 6 characters');
                        isValid = false;
                    }

                    if (!confirmInput.value) {
                        showError(confirmInput, 'Please confirm your password');
                        isValid = false;
                    } else if (passwordInput.value !== confirmInput.value) {
                        showError(confirmInput, 'Passwords do not match');
                        isValid = false;
                    }

                    if (!termsCheckbox.checked) {
                        alert('Please agree to the Terms of Service and Privacy Policy');
                        isValid = false;
                    }

                    if (!isValid) {
                        e.preventDefault();
                        return false;
                    }

                    if (submitBtn) {
                        submitBtn.classList.add('loading');
                    }
                    
                    return true;
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
        });
    </script>
</body>
</html>