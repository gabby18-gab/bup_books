<?php
// admin/profile.php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=bup_books", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$admin_id = $_SESSION['admin_id'];
$message = '';
$error = '';

// Get current admin data
$stmt = $pdo->prepare("SELECT * FROM admin WHERE AdminID = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Name is required.';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }
    
    // Check if email already exists (for another admin)
    if ($email != $admin['Email']) {
        $check = $pdo->prepare("SELECT AdminID FROM admin WHERE Email = ? AND AdminID != ?");
        $check->execute([$email, $admin_id]);
        if ($check->fetch()) {
            $errors[] = 'Email already used by another admin.';
        }
    }
    
    // Password change validation
    if (!empty($new_password) || !empty($current_password)) {
        if (empty($current_password)) {
            $errors[] = 'Current password is required to change password.';
        } elseif (!password_verify($current_password, $admin['Password'])) {
            // Check if plain text password (for backward compatibility)
            if ($current_password !== $admin['Password']) {
                $errors[] = 'Current password is incorrect.';
            }
        }
        
        if (strlen($new_password) < 6 && !empty($new_password)) {
            $errors[] = 'New password must be at least 6 characters.';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match.';
        }
    }
    
    // If no errors, update profile
    if (empty($errors)) {
        try {
            // Update basic info
            $updateSql = "UPDATE admin SET Name = ?, Email = ?";
            $params = [$name, $email];
            
            // Update password if provided
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $updateSql .= ", Password = ?";
                $params[] = $hashed_password;
            }
            
            $updateSql .= " WHERE AdminID = ?";
            $params[] = $admin_id;
            
            $updateStmt = $pdo->prepare($updateSql);
            if ($updateStmt->execute($params)) {
                // Update session
                $_SESSION['admin_name'] = $name;
                $_SESSION['admin_email'] = $email;
                
                // Log the action
                $logStmt = $pdo->prepare("INSERT INTO admin_logs (AdminID, Action, Details, IPAddress) VALUES (?, 'update', 'Admin profile updated', ?)");
                $logStmt->execute([$admin_id, $_SERVER['REMOTE_ADDR']]);
                
                $message = 'Profile updated successfully!';
                
                // Refresh admin data
                $stmt = $pdo->prepare("SELECT * FROM admin WHERE AdminID = ?");
                $stmt->execute([$admin_id]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Get admin statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as log_count FROM admin_logs WHERE AdminID = ?");
$stmt->execute([$admin_id]);
$log_count = $stmt->fetch(PDO::FETCH_ASSOC)['log_count'];

$stmt = $pdo->prepare("SELECT CreatedAt, LastLogin FROM admin WHERE AdminID = ?");
$stmt->execute([$admin_id]);
$dates = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - BUP BOOKS</title>
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bup-blue: #0A3143;
            --bup-blue-light: #1C4E6C;
            --bup-orange: #FF914D;
            --bup-orange-dark: #E67A3A;
            --bup-yellow: #FFC107;
            --bup-white: #ffffff;
            --bup-offwhite: #F8FAFC;
            --bup-gray: #5A6C74;
            --bup-gray-light: #E1E9F0;
            --bup-gradient: linear-gradient(145deg, #0A3143, #1C4E6C);
            --bup-gradient-accent: linear-gradient(145deg, #FF914D, #FFC107);
            --shadow-sm: 0 5px 15px rgba(0,0,0,0.05);
            --shadow-md: 0 10px 25px rgba(255,145,77,0.15);
            --shadow-lg: 0 15px 35px rgba(10,49,67,0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bup-offwhite);
            color: var(--bup-blue);
        }

        /* Sidebar (copy from dashboard) */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: var(--bup-gradient);
            color: white;
            padding: 30px 20px;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 5px 0 30px rgba(0,0,0,0.15);
        }

        .sidebar-logo {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .logo-text {
            font-size: 32px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--bup-orange), var(--bup-yellow));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2px;
        }

        .logo-sub {
            font-size: 12px;
            color: var(--bup-yellow);
            letter-spacing: 2px;
        }

        .admin-info {
            text-align: center;
            margin-bottom: 30px;
            padding: 15px;
            border-radius: 16px;
        }

        .admin-avatar {
            width: 80px;
            height: 80px;
            background: var(--bup-gradient-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            font-weight: 700;
            color: var(--bup-blue);
            border: 3px solid white;
            box-shadow: 0 8px 0 #C7511E;
        }

        .admin-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .admin-badge {
            display: inline-block;
            background: rgba(255,145,77,0.3);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin-top: 40px;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
            gap: 12px;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }

        .nav-link.active {
            background: var(--bup-gradient-accent);
            color: var(--bup-blue);
            font-weight: 700;
            box-shadow: 0 5px 0 #C7511E;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s ease;
        }

        /* Profile Card */
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .profile-header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
            border: 1px solid var(--bup-gray-light);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: var(--bup-gradient);
            z-index: 0;
        }

        .profile-avatar-large {
            width: 120px;
            height: 120px;
            background: var(--bup-gradient-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px auto 15px;
            font-size: 48px;
            font-weight: 700;
            color: var(--bup-blue);
            border: 5px solid white;
            box-shadow: 0 8px 0 #C7511E, 0 15px 25px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1;
        }

        .profile-name {
            font-size: 28px;
            font-weight: 800;
            color: var(--bup-blue);
            margin-bottom: 5px;
        }

        .profile-role {
            display: inline-block;
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange-dark);
            padding: 8px 25px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .profile-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--bup-gray-light);
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--bup-orange);
        }

        .stat-label {
            font-size: 13px;
            color: var(--bup-gray);
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--bup-gray-light);
        }

        .form-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 25px;
            color: var(--bup-blue);
            border-bottom: 2px solid var(--bup-orange);
            padding-bottom: 10px;
        }

        .form-label {
            font-weight: 600;
            color: var(--bup-blue);
            margin-bottom: 8px;
        }

        .form-control {
            border: 2px solid var(--bup-gray-light);
            border-radius: 12px;
            padding: 12px 15px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--bup-orange);
            box-shadow: 0 0 0 4px rgba(255,145,77,0.1);
            outline: none;
        }

        .form-text {
            color: var(--bup-gray);
            font-size: 12px;
            margin-top: 5px;
        }

        .btn-save {
            background: var(--bup-gradient-accent);
            color: var(--bup-blue);
            font-weight: 700;
            border: none;
            border-radius: 12px;
            padding: 14px 30px;
            font-size: 16px;
            box-shadow: 0 6px 0 #C7511E;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-save:hover {
            transform: translateY(3px);
            box-shadow: 0 3px 0 #C7511E;
        }

        .btn-save:active {
            transform: translateY(5px);
            box-shadow: 0 1px 0 #C7511E;
        }

        .alert {
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
        }

        /* Info boxes */
        .info-box {
            background: #F0F7FF;
            border-left: 5px solid var(--bup-orange);
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            color: var(--bup-blue);
            font-size: 14px;
        }

        .info-box i {
            color: var(--bup-orange);
            margin-right: 8px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .profile-stats {
                flex-direction: column;
                gap: 15px;
            }
        }

        /* Loading Spinner */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--bup-gray-light);
            border-top-color: var(--bup-orange);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Mobile menu button */
        .mobile-menu-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: var(--bup-gradient-accent);
            border: none;
            border-radius: 50%;
            box-shadow: var(--shadow-lg);
            color: var(--bup-blue);
            font-size: 28px;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <!-- Loading Spinner -->
    <div class="spinner-overlay" id="loadingSpinner">
        <div class="spinner"></div>
    </div>

    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <div class="logo-text">BUP</div>
            <div class="logo-sub">ADMIN PANEL</div>
        </div>
        
        <div class="admin-info">
            <div class="admin-avatar">
                <?php echo strtoupper(substr($admin['Name'], 0, 2)); ?>
            </div>
            <div class="admin-name"><?php echo htmlspecialchars($admin['Name']); ?></div>
            <span class="admin-badge">
                <i class="bi bi-shield-lock me-1"></i>
                <?php echo ucfirst(str_replace('_', ' ', $admin['Role'])); ?>
            </span>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="users.php" class="nav-link">
                    <i class="bi bi-people"></i>
                    <span>Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="sellers.php" class="nav-link">
                    <i class="bi bi-shop"></i>
                    <span>Sellers</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="products.php" class="nav-link">
                    <i class="bi bi-book"></i>
                    <span>Products</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="orders.php" class="nav-link">
                    <i class="bi bi-cart"></i>
                    <span>Orders</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="payments.php" class="nav-link">
                    <i class="bi bi-credit-card"></i>
                    <span>Payments</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="feedback.php" class="nav-link">
                    <i class="bi bi-chat"></i>
                    <span>Feedback</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admins.php" class="nav-link">
                    <i class="bi bi-shield"></i>
                    <span>Admins</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="logs.php" class="nav-link">
                    <i class="bi bi-journal-text"></i>
                    <span>Activity Logs</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="nav-link">
                    <i class="bi bi-graph-up"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="profile.php" class="nav-link active">
                    <i class="bi bi-person-gear"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="settings.php" class="nav-link">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link" style="margin-top: 20px; background: rgba(255,69,58,0.2);">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="profile-container">
            
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 style="font-size: 28px; font-weight: 800; color: var(--bup-blue);">
                    <i class="bi bi-person-gear me-3" style="color: var(--bup-orange);"></i>My Profile
                </h1>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
            
            <!-- Profile Header Card -->
            <div class="profile-header">
                <div class="profile-avatar-large">
                    <?php echo strtoupper(substr($admin['Name'], 0, 2)); ?>
                </div>
                <h2 class="profile-name"><?php echo htmlspecialchars($admin['Name']); ?></h2>
                <span class="profile-role">
                    <i class="bi bi-shield-lock me-1"></i>
                    <?php echo ucfirst(str_replace('_', ' ', $admin['Role'])); ?>
                </span>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $log_count; ?></div>
                        <div class="stat-label">Activities</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo date('M d, Y', strtotime($dates['CreatedAt'])); ?></div>
                        <div class="stat-label">Joined</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">
                            <?php echo $dates['LastLogin'] ? date('M d', strtotime($dates['LastLogin'])) : 'Never'; ?>
                        </div>
                        <div class="stat-label">Last Login</div>
                    </div>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Edit Profile Form -->
            <div class="form-card">
                <h3 class="form-title">
                    <i class="bi bi-pencil-square me-2" style="color: var(--bup-orange);"></i>
                    Edit Profile Information
                </h3>
                
                <div class="info-box">
                    <i class="bi bi-info-circle-fill"></i>
                    Leave password fields empty if you don't want to change your password.
                </div>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($admin['Name']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['Email']); ?>" required>
                            <div class="form-text">This will be used for login</div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h5 class="mb-3" style="color: var(--bup-orange);">Change Password</h5>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" placeholder="Enter current password">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Min. 6 characters">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password">
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn-save">
                                <i class="bi bi-check-lg me-2"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Account Info Card -->
            <div class="form-card mt-4">
                <h3 class="form-title">
                    <i class="bi bi-shield-check me-2" style="color: var(--bup-orange);"></i>
                    Account Information
                </h3>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="p-3" style="background: var(--bup-offwhite); border-radius: 12px;">
                            <small class="text-muted d-block">Admin ID</small>
                            <strong>#<?php echo $admin['AdminID']; ?></strong>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="p-3" style="background: var(--bup-offwhite); border-radius: 12px;">
                            <small class="text-muted d-block">Role</small>
                            <strong><?php echo ucfirst(str_replace('_', ' ', $admin['Role'])); ?></strong>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="p-3" style="background: var(--bup-offwhite); border-radius: 12px;">
                            <small class="text-muted d-block">Account Created</small>
                            <strong><?php echo date('F j, Y \a\t h:i A', strtotime($dates['CreatedAt'])); ?></strong>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="p-3" style="background: var(--bup-offwhite); border-radius: 12px;">
                            <small class="text-muted d-block">Last Login</small>
                            <strong><?php echo $dates['LastLogin'] ? date('F j, Y \a\t h:i A', strtotime($dates['LastLogin'])) : 'Never logged in'; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle sidebar on mobile
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        // Check mobile view
        function checkMobileView() {
            const sidebar = document.getElementById('sidebar');
            const mobileBtn = document.getElementById('mobileMenuBtn');
            
            if (window.innerWidth <= 992) {
                mobileBtn.style.display = 'flex';
                sidebar.classList.remove('active');
            } else {
                mobileBtn.style.display = 'none';
                sidebar.classList.add('active');
            }
        }

        window.addEventListener('resize', checkMobileView);
        window.addEventListener('load', checkMobileView);

        // Loading spinner
        document.querySelectorAll('a:not([href^="#"]):not([href^="javascript:"]):not(.btn-icon)').forEach(link => {
            link.addEventListener('click', function(e) {
                document.getElementById('loadingSpinner').style.display = 'flex';
            });
        });

        window.addEventListener('load', function() {
            document.getElementById('loadingSpinner').style.display = 'none';
        });
    </script>
</body>
</html>