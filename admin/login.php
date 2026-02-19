<?php
session_start();

// Redirect if already logged in as admin
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=bup_books", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("SELECT AdminID, Name, Email, Password, Role FROM admin WHERE Email = ? AND Status = 'A'");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['Password'])) {
            $_SESSION['admin_id'] = $admin['AdminID'];
            $_SESSION['admin_name'] = $admin['Name'];
            $_SESSION['admin_email'] = $admin['Email'];
            $_SESSION['admin_role'] = $admin['Role'];
            $_SESSION['admin_logged_in'] = true;
            
            // Update last login
            $updateStmt = $pdo->prepare("UPDATE admin SET LastLogin = NOW() WHERE AdminID = ?");
            $updateStmt->execute([$admin['AdminID']]);
            
            // Log the login
            $logStmt = $pdo->prepare("INSERT INTO admin_logs (AdminID, Action, Details, IPAddress) VALUES (?, 'login', 'Admin logged in', ?)");
            $logStmt->execute([$admin['AdminID'], $_SERVER['REMOTE_ADDR']]);
            
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid email or password!';
        }
    } catch (PDOException $e) {
        error_log("Admin login error: " . $e->getMessage());
        $error = 'Login failed. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - BUP BOOKS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --bup-blue: #0A3143;
            --bup-blue-light: #1C4E6C;
            --bup-orange: #FF914D;
            --bup-yellow: #FFC107;
        }
        
        body {
            background: linear-gradient(145deg, var(--bup-blue), var(--bup-blue-light));
            font-family: 'Inter', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 50px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        
        .admin-badge {
            background: var(--bup-orange);
            color: var(--bup-blue);
            display: inline-block;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 800;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-text {
            font-size: 36px;
            font-weight: 900;
            color: var(--bup-blue);
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .input-group input:focus {
            border-color: var(--bup-orange);
            outline: none;
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(145deg, var(--bup-orange), var(--bup-yellow));
            color: var(--bup-blue);
            font-weight: 800;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 0 #C7511E;
        }
        
        .btn-login:hover {
            transform: translateY(2px);
            box-shadow: 0 3px 0 #C7511E;
        }
        
        .error {
            background: #FEE;
            color: #c00;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 5px solid #c00;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: var(--bup-gray);
            text-decoration: none;
        }
        
        .back-link a:hover {
            color: var(--bup-orange);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center">
            <span class="admin-badge"><i class="bi bi-shield-lock me-2"></i>ADMIN PANEL</span>
        </div>
        <div class="logo">
            <span class="logo-text">BUP BOOKS</span>
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="input-group">
                <input type="email" name="email" placeholder="Email address" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>Login to Dashboard
            </button>
        </form>
        
        <div class="back-link">
            <a href="../index.php"><i class="bi bi-arrow-left me-2"></i>Back to Main Site</a>
        </div>
    </div>
</body>
</html>