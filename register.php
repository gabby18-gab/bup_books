<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: user/index.php'); // Changed from client/ to user/
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
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* Your existing styles - keep as is */
        /* ... */
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
                    <div class="logo-platform">Platform</div>
                </div>
                <h1>Create Account</h1>
                <p>Join BUP Books community</p>
            </div>

            <?php if ($success): ?>
                <div class="success-message show" id="successMessage">
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
                        window.location.href = 'user/index.php'; // CHANGED: client/ to user/
                    }, 2500);
                </script>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="php-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form class="register-form" method="POST" action="" id="registerForm">
                    <div class="input-group">
                        <input type="text" id="name" name="name" required autocomplete="name" placeholder=" "
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        <label for="name">Full name</label>
                        <span class="input-border"></span>
                        <span class="error-message" id="nameError"></span>
                    </div>

                    <div class="input-group">
                        <input type="email" id="email" name="email" required autocomplete="email" placeholder=" "
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <label for="email">Email address</label>
                        <span class="input-border"></span>
                        <span class="error-message" id="emailError"></span>
                    </div>

                    <div class="input-group">
                        <input type="password" id="password" name="password" required autocomplete="new-password" placeholder=" ">
                        <label for="password">Password</label>
                        <span class="input-border"></span>
                        <span class="error-message" id="passwordError"></span>
                        <div class="password-strength">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                        <span class="strength-text" id="strengthText">Enter a password</span>
                    </div>

                    <div class="input-group">
                        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password" placeholder=" ">
                        <label for="confirm_password">Confirm password</label>
                        <span class="input-border"></span>
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
    </div>

    <script>
        // Your existing JavaScript
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
        });
    </script>
</body>
</html>