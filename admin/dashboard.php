<?php
// admin/dashboard.php
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

// Get admin info
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];
$admin_role = $_SESSION['admin_role'];

// Get comprehensive statistics
$stats = [];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE Status = 'A'");
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total sellers
$stmt = $pdo->query("SELECT COUNT(*) as count FROM seller WHERE Status = 'A'");
$stats['total_sellers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total products
$stmt = $pdo->query("SELECT COUNT(*) as count FROM product WHERE Status = 'A'");
$stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Pending products
$stmt = $pdo->query("SELECT COUNT(*) as count FROM product WHERE Status = 'P'");
$stats['pending_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total orders
$stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
$stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total revenue
$stmt = $pdo->query("SELECT SUM(TotalAmount) as total FROM orders WHERE PaymentStatus = 'paid'");
$stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Pending orders
$stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE Status = 'pending'");
$stats['pending_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get order status distribution for chart (data still available if needed later)
$stmt = $pdo->query("
    SELECT Status, COUNT(*) as count 
    FROM orders 
    GROUP BY Status
");
$order_status = [];
$order_status_labels = [];
$order_status_data = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $order_status[$row['Status']] = $row['count'];
    $order_status_labels[] = ucfirst($row['Status']);
    $order_status_data[] = $row['count'];
}

// If no data, provide default
if (empty($order_status_data)) {
    $order_status_labels = ['Pending', 'Processing', 'Completed'];
    $order_status_data = [0, 0, 0];
}

// Get weekly orders data (keeping for potential future use)
$weekly_labels = [];
$weekly_data = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_name = date('D', strtotime("-$i days"));
    $weekly_labels[] = $day_name;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE DATE(OrderDate) = ?
    ");
    $stmt->execute([$date]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $weekly_data[] = $count;
}

// Recent users
$stmt = $pdo->query("SELECT UserID, Name, Email, CreatedAt FROM users ORDER BY CreatedAt DESC LIMIT 5");
$recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent orders
$stmt = $pdo->query("
    SELECT o.OrderID, u.Name as UserName, o.OrderDate, o.Status, o.TotalAmount 
    FROM orders o 
    JOIN users u ON o.UserID = u.UserID 
    ORDER BY o.OrderDate DESC 
    LIMIT 5
");
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top selling products
$stmt = $pdo->query("
    SELECT p.ProductID, p.ProductName, p.Price, SUM(od.Quantity) as TotalSold 
    FROM product p 
    JOIN orderdetails od ON p.ProductID = od.ProductID 
    GROUP BY p.ProductID 
    ORDER BY TotalSold DESC 
    LIMIT 5
");
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent admin logs
$stmt = $pdo->prepare("
    SELECT al.*, a.Name as AdminName 
    FROM admin_logs al 
    JOIN admin a ON al.AdminID = a.AdminID 
    ORDER BY al.CreatedAt DESC 
    LIMIT 5
");
$stmt->execute();
$recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BUP BOOKS</title>
    
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

        /* Sidebar */
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
            background: rgba(255,255,255,0.05);
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
            transition: all 0.3s ease;
        }
        
        .admin-info:hover .admin-avatar {
            transform: scale(1.05);
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
        
        .edit-profile-link {
            display: inline-block;
            margin-top: 8px;
            color: var(--bup-yellow);
            font-size: 12px;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        
        .edit-profile-link:hover {
            opacity: 1;
            color: var(--bup-orange);
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

        .nav-link i {
            font-size: 20px;
            width: 25px;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s ease;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px 30px;
            border-radius: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--bup-gray-light);
        }

        .page-title h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--bup-blue);
            margin-bottom: 5px;
        }

        .page-title p {
            color: var(--bup-gray);
            margin: 0;
            font-size: 14px;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
            border: 1px solid var(--bup-gray-light);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--bup-orange);
        }

        .stat-info h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--bup-gray);
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: var(--bup-blue);
            line-height: 1;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--bup-gray);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(145deg, rgba(255,145,77,0.1), rgba(255,193,7,0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: var(--bup-orange);
        }

        /* Tables */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
            border: 1px solid var(--bup-gray-light);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--bup-blue);
            margin: 0;
        }

        .section-header a {
            color: var(--bup-orange);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: color 0.3s;
        }

        .section-header a:hover {
            color: var(--bup-blue);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            border-bottom: 2px solid var(--bup-gray-light);
            color: var(--bup-gray);
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px;
        }

        .table tbody td {
            padding: 15px 12px;
            vertical-align: middle;
            color: var(--bup-blue);
            font-weight: 500;
            border-bottom: 1px solid var(--bup-gray-light);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-active {
            background: rgba(40,167,69,0.15);
            color: #28a745;
        }

        .status-pending {
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange-dark);
        }

        .status-completed {
            background: rgba(10,49,67,0.1);
            color: var(--bup-blue);
        }

        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-view {
            background: rgba(10,49,67,0.1);
            color: var(--bup-blue);
        }

        .btn-view:hover {
            background: var(--bup-blue);
            color: white;
        }

        .btn-edit {
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange-dark);
        }

        .btn-edit:hover {
            background: var(--bup-orange);
            color: white;
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
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
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
        
        /* Avatar initials */
        .avatar-initials {
            font-size: 32px;
            font-weight: 700;
        }
    </style>
</head>
<body>

    <!-- Loading Spinner -->
    <div class="spinner-overlay" id="loadingSpinner">
        <div class="spinner"></div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <div class="logo-text">BUP</div>
            <div class="logo-sub">ADMIN PANEL</div>
        </div>
        
        <div class="admin-info" onclick="window.location.href='profile.php'">
            <div class="admin-avatar">
                <span class="avatar-initials"><?php echo strtoupper(substr($admin_name, 0, 2)); ?></span>
            </div>
            <div class="admin-name"><?php echo htmlspecialchars($admin_name); ?></div>
            <span class="admin-badge">
                <i class="bi bi-shield-lock me-1"></i>
                <?php echo ucfirst(str_replace('_', ' ', $admin_role)); ?>
            </span>
            <div class="edit-profile-link">
                <i class="bi bi-pencil-square"></i> Edit Profile
            </div>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link active">
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
                <a href="pending_products.php" class="nav-link">
                    <i class="bi bi-clock-history"></i>
                    <span>Pending Approvals</span>
                    <?php if ($stats['pending_products'] > 0): ?>
                        <span class="badge bg-warning ms-auto"><?php echo $stats['pending_products']; ?></span>
                    <?php endif; ?>
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
                <a href="profile.php" class="nav-link">
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

    <!-- Mobile Menu Toggle -->
    <div style="position: fixed; bottom: 30px; right: 30px; z-index: 999; display: none;" id="mobileMenuBtn">
        <button onclick="toggleSidebar()" style="width: 60px; height: 60px; background: var(--bup-gradient-accent); border: none; border-radius: 50%; box-shadow: var(--shadow-lg); color: var(--bup-blue); font-size: 28px;">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Header (without notifications and quick actions button) -->
        <div class="header">
            <div class="page-title">
                <h1>Dashboard</h1>
                <p>
                    <i class="bi bi-calendar me-2" style="color: var(--bup-orange);"></i>
                    <?php echo date('l, F j, Y'); ?>
                </p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Users</h3>
                    <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                    <span class="stat-label">Active accounts</span>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-people"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Sellers</h3>
                    <div class="stat-number"><?php echo $stats['total_sellers']; ?></div>
                    <span class="stat-label">Active sellers</span>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-shop"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Products</h3>
                    <div class="stat-number"><?php echo $stats['total_products']; ?></div>
                    <span class="stat-label">Books listed</span>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-book"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Orders</h3>
                    <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
                    <span class="stat-label">All time</span>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-cart"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Revenue</h3>
                    <div class="stat-number">₱<?php echo number_format($stats['total_revenue'], 2); ?></div>
                    <span class="stat-label">Completed payments</span>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Pending Orders</h3>
                    <div class="stat-number"><?php echo $stats['pending_orders']; ?></div>
                    <span class="stat-label">Need attention</span>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-clock-history"></i>
                </div>
            </div>
        </div>

        <!-- Recent Users and Orders -->
        <div class="row">
            <div class="col-lg-6">
                <div class="table-container">
                    <div class="section-header">
                        <h3><i class="bi bi-people me-2" style="color: var(--bup-orange);"></i>Recent Users</h3>
                        <a href="users.php">View All <i class="bi bi-arrow-right"></i></a>
                    </div>
                    
                    <?php if (count($recent_users) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Joined</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div style="width: 35px; height: 35px; background: var(--bup-gradient-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--bup-blue); font-weight: 700; margin-right: 10px;">
                                                <?php echo strtoupper(substr($user['Name'], 0, 2)); ?>
                                            </div>
                                            <?php echo htmlspecialchars($user['Name']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['Email']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($user['CreatedAt'])); ?></td>
                                    <td>
                                        <a href="users.php?view=<?php echo $user['UserID']; ?>" class="btn-action btn-view">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-people" style="font-size: 48px; color: var(--bup-gray-light);"></i>
                        <p class="mt-2 text-muted">No users yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="table-container">
                    <div class="section-header">
                        <h3><i class="bi bi-cart me-2" style="color: var(--bup-orange);"></i>Recent Orders</h3>
                        <a href="orders.php">View All <i class="bi bi-arrow-right"></i></a>
                    </div>
                    
                    <?php if (count($recent_orders) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                32
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td><strong>#<?php echo $order['OrderID']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['UserName']); ?></td>
                                    <td style="font-weight: 700; color: var(--bup-orange);">₱<?php echo number_format($order['TotalAmount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php 
                                            echo $order['Status'] == 'completed' ? 'status-completed' : 
                                                ($order['Status'] == 'pending' ? 'status-pending' : 'status-active'); 
                                        ?>">
                                            <?php echo ucfirst($order['Status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="orders.php?view=<?php echo $order['OrderID']; ?>" class="btn-action btn-view">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-cart" style="font-size: 48px; color: var(--bup-gray-light);"></i>
                        <p class="mt-2 text-muted">No orders yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Selling Products -->
        <div class="table-container">
            <div class="section-header">
                <h3><i class="bi bi-trophy me-2" style="color: var(--bup-yellow);"></i>Top Selling Books</h3>
                <a href="products.php">View All Products <i class="bi bi-arrow-right"></i></a>
            </div>
            
            <?php if (count($top_products) > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        32
                            <th>Product</th>
                            <th>Price</th>
                            <th>Total Sold</th>
                            <th>Revenue</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_products as $product): ?>
                        <tr>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($product['ProductName']); ?></td>
                            <td>₱<?php echo number_format($product['Price'], 2); ?></td>
                            <td><?php echo $product['TotalSold']; ?> units</td>
                            <td style="font-weight: 700; color: var(--bup-orange);">₱<?php echo number_format($product['Price'] * $product['TotalSold'], 2); ?></td>
                            <td>
                                <a href="products.php?view=<?php echo $product['ProductID']; ?>" class="btn-action btn-view">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="bi bi-book" style="font-size: 48px; color: var(--bup-gray-light);"></i>
                <p class="mt-2 text-muted">No sales data yet</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity Logs (kept but without sidebar link) -->
        <div class="table-container">
            <div class="section-header">
                <h3><i class="bi bi-clock-history me-2" style="color: var(--bup-orange);"></i>Recent Activity</h3>
                <!-- No "View All" link to logs.php -->
            </div>
            
            <?php if (isset($recent_logs) && count($recent_logs) > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['AdminName']); ?></td>
                            <td>
                                <span class="badge" style="background: rgba(255,145,77,0.15); color: var(--bup-orange-dark);">
                                    <?php echo ucfirst($log['Action']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($log['Details']); ?></td>
                            <td><?php echo htmlspecialchars($log['IPAddress']); ?></td>
                            <td><?php echo date('M d, H:i', strtotime($log['CreatedAt'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="bi bi-clock" style="font-size: 48px; color: var(--bup-gray-light);"></i>
                <p class="mt-2 text-muted">No recent activity</p>
            </div>
            <?php endif; ?>
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
                mobileBtn.style.display = 'block';
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