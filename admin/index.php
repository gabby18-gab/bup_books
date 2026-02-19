<?php
// admin/index.php - Main Admin Dashboard
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
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

// Get current date and time
$current_time = date('l, F j, Y - h:i A');

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

// Total orders
$stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
$stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total revenue
$stmt = $pdo->query("SELECT SUM(TotalAmount) as total FROM orders WHERE PaymentStatus = 'paid'");
$stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Pending orders
$stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE Status = 'pending'");
$stats['pending_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Today's orders
$stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE DATE(OrderDate) = CURDATE()");
$stats['today_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Low stock products (< 3)
$stmt = $pdo->query("SELECT COUNT(*) as count FROM product WHERE StockQuantity < 3 AND Status = 'A'");
$stats['low_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Recent users (last 7 days)
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE CreatedAt >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['new_users_week'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Recent users list
$stmt = $pdo->query("
    SELECT UserID, Name, Email, CreatedAt, Status 
    FROM users 
    ORDER BY CreatedAt DESC 
    LIMIT 5
");
$recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent orders
$stmt = $pdo->query("
    SELECT o.OrderID, u.Name as UserName, o.OrderDate, o.Status, o.TotalAmount, o.PaymentStatus 
    FROM orders o 
    JOIN users u ON o.UserID = u.UserID 
    ORDER BY o.OrderDate DESC 
    LIMIT 5
");
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top selling products
$stmt = $pdo->query("
    SELECT p.ProductName, p.Price, SUM(od.Quantity) as TotalSold,
           (p.Price * SUM(od.Quantity)) as Revenue
    FROM product p 
    JOIN orderdetails od ON p.ProductID = od.ProductID 
    GROUP BY p.ProductID 
    ORDER BY TotalSold DESC 
    LIMIT 5
");
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent products
$stmt = $pdo->query("
    SELECT p.*, s.Name as SellerName 
    FROM product p 
    JOIN seller s ON p.SellerID = s.SellerID 
    ORDER BY p.ProductID DESC 
    LIMIT 5
");
$recent_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent sellers
$stmt = $pdo->query("
    SELECT s.*, u.Name as UserName, 
           (SELECT COUNT(*) FROM product WHERE SellerID = s.SellerID) as product_count
    FROM seller s
    JOIN users u ON s.UserID = u.UserID
    ORDER BY s.CreatedAt DESC 
    LIMIT 5
");
$recent_sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Category distribution
$stmt = $pdo->query("
    SELECT Category, COUNT(*) as count 
    FROM product 
    GROUP BY Category 
    ORDER BY count DESC 
    LIMIT 6
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly order data for chart
$monthly_orders = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M', strtotime("-$i months"));
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, SUM(TotalAmount) as revenue 
        FROM orders 
        WHERE DATE_FORMAT(OrderDate, '%Y-%m') = ?
    ");
    $stmt->execute([$month]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $monthly_orders[] = [
        'month' => $month_name,
        'orders' => $data['count'] ?? 0,
        'revenue' => $data['revenue'] ?? 0
    ];
}

// Get order status distribution
$stmt = $pdo->query("
    SELECT Status, COUNT(*) as count 
    FROM orders 
    GROUP BY Status
");
$order_status = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $order_status[$row['Status']] = $row['count'];
}

// Get payment status distribution
$stmt = $pdo->query("
    SELECT PaymentStatus, COUNT(*) as count 
    FROM orders 
    GROUP BY PaymentStatus
");
$payment_status = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $payment_status[$row['PaymentStatus']] = $row['count'];
}
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
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
            overflow-x: hidden;
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
            font-size: 36px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--bup-orange), var(--bup-yellow));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2px;
        }

        .logo-sub {
            font-size: 14px;
            color: var(--bup-yellow);
            letter-spacing: 2px;
            font-weight: 600;
        }

        .admin-info {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px 15px;
            background: rgba(255,255,255,0.05);
            border-radius: 20px;
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

        .admin-time {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
            margin-top: 10px;
        }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin-top: 20px;
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
            font-weight: 500;
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

        .nav-link span {
            font-size: 14px;
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

        .notification-badge {
            position: relative;
            cursor: pointer;
        }

        .notification-badge i {
            font-size: 24px;
            color: var(--bup-gray);
        }

        .badge-count {
            position: absolute;
            top: -5px;
            right: -8px;
            background: var(--bup-orange);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            border: 2px solid white;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
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
            font-size: 14px;
            font-weight: 600;
            color: var(--bup-gray);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: var(--bup-blue);
            line-height: 1;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
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
            font-size: 28px;
            color: var(--bup-orange);
        }

        /* Charts Row */
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--bup-gray-light);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-header h3 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }

        .chart-header span {
            font-size: 13px;
            color: var(--bup-gray);
        }

        /* Quick Action Cards */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: white;
            border-radius: 20px;
            padding: 25px 20px;
            text-align: center;
            border: 2px dashed var(--bup-gray-light);
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .action-card:hover {
            border-color: var(--bup-orange);
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .action-icon {
            width: 60px;
            height: 60px;
            background: var(--bup-gradient-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            color: white;
            box-shadow: 0 5px 0 #C7511E;
        }

        .action-card h4 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .action-card p {
            font-size: 13px;
            color: var(--bup-gray);
            margin: 0;
        }

        /* Tables */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 20px;
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

        .btn-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s;
            margin: 0 3px;
        }

        .btn-icon:hover {
            transform: translateY(-2px);
        }

        .btn-view {
            background: rgba(10,49,67,0.1);
            color: var(--bup-blue);
        }

        .btn-edit {
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange-dark);
        }

        .avatar-sm {
            width: 35px;
            height: 35px;
            background: var(--bup-gradient-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--bup-blue);
            font-weight: 700;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 260px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .charts-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
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

        /* Welcome Banner */
        .welcome-banner {
            background: var(--bup-gradient);
            border-radius: 20px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner h2 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .welcome-banner p {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 20px;
            max-width: 500px;
        }

        .welcome-banner::after {
            content: 'BUP';
            position: absolute;
            bottom: -20px;
            right: 20px;
            font-size: 120px;
            font-weight: 900;
            color: rgba(255,255,255,0.1);
            line-height: 1;
        }

        .btn-welcome {
            background: var(--bup-gradient-accent);
            color: var(--bup-blue);
            font-weight: 700;
            padding: 10px 25px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 5px 0 #C7511E;
            transition: all 0.3s;
        }

        .btn-welcome:hover {
            transform: translateY(3px);
            box-shadow: 0 2px 0 #C7511E;
            color: var(--bup-blue);
        }

        /* Category badges */
        .category-badge {
            display: inline-block;
            background: var(--bup-gray-light);
            color: var(--bup-blue);
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
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
            transition: all 0.3s;
        }

        .mobile-menu-btn:hover {
            transform: scale(1.1);
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
                <?php echo strtoupper(substr($admin_name, 0, 2)); ?>
            </div>
            <div class="admin-name"><?php echo htmlspecialchars($admin_name); ?></div>
            <span class="admin-badge">
                <i class="bi bi-shield-lock me-1"></i>
                <?php echo ucfirst(str_replace('_', ' ', $admin_role)); ?>
            </span>
            <div class="admin-time">
                <i class="bi bi-clock me-1"></i> <?php echo $current_time; ?>
            </div>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="index.php" class="nav-link active">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="users.php" class="nav-link">
                    <i class="bi bi-people"></i>
                    <span>Users</span>
                    <?php if ($stats['new_users_week'] > 0): ?>
                        <span class="badge bg-warning ms-auto">+<?php echo $stats['new_users_week']; ?></span>
                    <?php endif; ?>
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
                    <?php if ($stats['low_stock'] > 0): ?>
                        <span class="badge bg-danger ms-auto"><?php echo $stats['low_stock']; ?> low</span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="orders.php" class="nav-link">
                    <i class="bi bi-cart"></i>
                    <span>Orders</span>
                    <?php if ($stats['pending_orders'] > 0): ?>
                        <span class="badge bg-warning ms-auto"><?php echo $stats['pending_orders']; ?></span>
                    <?php endif; ?>
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
        
        <!-- Header -->
        <div class="header">
            <div class="page-title">
                <h1>Dashboard Overview</h1>
                <p>
                    <i class="bi bi-calendar-check me-2" style="color: var(--bup-orange);"></i>
                    <?php echo $current_time; ?>
                </p>
            </div>
            <div class="header-actions">
                <div class="notification-badge">
                    <i class="bi bi-bell"></i>
                    <span class="badge-count"><?php echo $stats['pending_orders']; ?></span>
                </div>
                <div class="dropdown">
                    <button class="btn" style="background: var(--bup-gradient-accent); color: var(--bup-blue); font-weight: 700; border-radius: 30px; padding: 10px 20px; border: none; box-shadow: 0 4px 0 #C7511E;" data-bs-toggle="dropdown">
                        <i class="bi bi-plus-circle me-2"></i>Quick Actions
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="users.php?action=add"><i class="bi bi-person-plus me-2"></i>Add User</a></li>
                        <li><a class="dropdown-item" href="products.php?action=add"><i class="bi bi-book me-2"></i>Add Product</a></li>
                        <li><a class="dropdown-item" href="admins.php?action=add"><i class="bi bi-shield me-2"></i>Add Admin</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="reports.php"><i class="bi bi-file-text me-2"></i>Generate Report</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?>! ??</h2>
            <p>Here's what's happening with your marketplace today. You have <?php echo $stats['pending_orders']; ?> pending orders that need attention.</p>
            <a href="orders.php?status=pending" class="btn-welcome">
                <i class="bi bi-eye me-2"></i>View Pending Orders
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Users</h3>
                    <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                    <span class="stat-label">+<?php echo $stats['new_users_week']; ?> this week</span>
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
                    <span class="stat-label"><?php echo $stats['low_stock']; ?> low in stock</span>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-book"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Orders</h3>
                    <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
                    <span class="stat-label"><?php echo $stats['today_orders']; ?> today</span>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-cart"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Revenue</h3>
                    <div class="stat-number">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
                    <span class="stat-label">All time</span>
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

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="users.php?action=add" class="action-card">
                <div class="action-icon">
                    <i class="bi bi-person-plus"></i>
                </div>
                <h4>Add User</h4>
                <p>Create new account</p>
            </a>
            
            <a href="products.php?action=add" class="action-card">
                <div class="action-icon">
                    <i class="bi bi-book"></i>
                </div>
                <h4>Add Book</h4>
                <p>List new product</p>
            </a>
            
            <a href="orders.php" class="action-card">
                <div class="action-icon">
                    <i class="bi bi-truck"></i>
                </div>
                <h4>Manage Orders</h4>
                <p><?php echo $stats['pending_orders']; ?> pending</p>
            </a>
            
            <a href="reports.php" class="action-card">
                <div class="action-icon">
                    <i class="bi bi-file-text"></i>
                </div>
                <h4>Reports</h4>
                <p>Monthly summary</p>
            </a>
            
            <a href="sellers.php" class="action-card">
                <div class="action-icon">
                    <i class="bi bi-shop"></i>
                </div>
                <h4>Sellers</h4>
                <p><?php echo $stats['total_sellers']; ?> active</p>
            </a>
        </div>

        <!-- Charts Row -->
        <div class="charts-row">
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="bi bi-graph-up me-2" style="color: var(--bup-orange);"></i>Monthly Orders</h3>
                    <span>Last 6 months</span>
                </div>
                <canvas id="ordersChart" style="height: 250px;"></canvas>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="bi bi-pie-chart me-2" style="color: var(--bup-orange);"></i>Order Status</h3>
                    <span>Distribution</span>
                </div>
                <canvas id="statusChart" style="height: 250px;"></canvas>
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
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-2">
                                                <?php echo strtoupper(substr($user['Name'], 0, 2)); ?>
                                            </div>
                                            <?php echo htmlspecialchars($user['Name']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['Email']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($user['CreatedAt'])); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $user['Status'] == 'A' ? 'status-active' : 'status-pending'; ?>">
                                            <?php echo $user['Status'] == 'A' ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="users.php?view=<?php echo $user['UserID']; ?>" class="btn-icon btn-view">
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
                                <tr>
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
                                    <td style="font-weight: 700; color: var(--bup-orange);">$<?php echo number_format($order['TotalAmount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php 
                                            echo $order['Status'] == 'completed' ? 'status-completed' : 
                                                ($order['Status'] == 'pending' ? 'status-pending' : 'status-active'); 
                                        ?>">
                                            <?php echo ucfirst($order['Status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="orders.php?view=<?php echo $order['OrderID']; ?>" class="btn-icon btn-view">
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

        <!-- Recent Products and Sellers -->
        <div class="row">
            <div class="col-lg-6">
                <div class="table-container">
                    <div class="section-header">
                        <h3><i class="bi bi-book me-2" style="color: var(--bup-orange);"></i>Recent Products</h3>
                        <a href="products.php">View All <i class="bi bi-arrow-right"></i></a>
                    </div>
                    
                    <?php if (count($recent_products) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Seller</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(substr($product['ProductName'], 0, 25)); ?></td>
                                    <td><span class="category-badge"><?php echo htmlspecialchars($product['Category']); ?></span></td>
                                    <td><?php echo htmlspecialchars($product['SellerName']); ?></td>
                                    <td style="color: var(--bup-orange); font-weight: 700;">$<?php echo number_format($product['Price'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $product['StockQuantity'] < 3 ? 'status-pending' : 'status-active'; ?>">
                                            <?php echo $product['StockQuantity']; ?> units
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-book" style="font-size: 48px; color: var(--bup-gray-light);"></i>
                        <p class="mt-2 text-muted">No products yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="table-container">
                    <div class="section-header">
                        <h3><i class="bi bi-shop me-2" style="color: var(--bup-orange);"></i>Recent Sellers</h3>
                        <a href="sellers.php">View All <i class="bi bi-arrow-right"></i></a>
                    </div>
                    
                    <?php if (count($recent_sellers) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Store Name</th>
                                    <th>Owner</th>
                                    <th>Products</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_sellers as $seller): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($seller['Name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($seller['UserName']); ?></td>
                                    <td><?php echo $seller['product_count']; ?> books</td>
                                    <td><?php echo date('M d, Y', strtotime($seller['CreatedAt'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-shop" style="font-size: 48px; color: var(--bup-gray-light);"></i>
                        <p class="mt-2 text-muted">No sellers yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Products and Recent Activity -->
        <div class="row">
            <div class="col-lg-6">
                <div class="table-container">
                    <div class="section-header">
                        <h3><i class="bi bi-trophy me-2" style="color: var(--bup-yellow);"></i>Top Selling Books</h3>
                    </div>
                    
                    <?php if (count($top_products) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Book Title</th>
                                    <th>Sold</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['ProductName']); ?></td>
                                    <td><?php echo $product['TotalSold']; ?> units</td>
                                    <td style="color: var(--bup-orange); font-weight: 700;">$<?php echo number_format($product['Revenue'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-trophy" style="font-size: 48px; color: var(--bup-gray-light);"></i>
                        <p class="mt-2 text-muted">No sales data yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="table-container">
                    <div class="section-header">
                        <h3><i class="bi bi-clock-history me-2" style="color: var(--bup-orange);"></i>Recent Activity</h3>
                        <a href="logs.php">View All <i class="bi bi-arrow-right"></i></a>
                    </div>
                    
                    <?php if (isset($recent_logs) && count($recent_logs) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Admin</th>
                                    <th>Action</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['AdminName']); ?></td>
                                    <td>
                                        <span class="badge" style="background: <?php 
                                            if($log['Action'] == 'login') echo 'rgba(40,167,69,0.15); color: #28a745;';
                                            elseif($log['Action'] == 'logout') echo 'rgba(108,117,125,0.15); color: #6c757d;';
                                            elseif($log['Action'] == 'create') echo 'rgba(0,123,255,0.15); color: #007bff;';
                                            elseif($log['Action'] == 'update') echo 'rgba(255,193,7,0.15); color: #ffc107;';
                                            elseif($log['Action'] == 'delete') echo 'rgba(220,53,69,0.15); color: #dc3545;';
                                        ?>">
                                            <?php echo ucfirst($log['Action']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('h:i A', strtotime($log['CreatedAt'])); ?></td>
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
        </div>

        <!-- Category Distribution -->
        <div class="row">
            <div class="col-12">
                <div class="table-container">
                    <div class="section-header">
                        <h3><i class="bi bi-grid me-2" style="color: var(--bup-orange);"></i>Category Distribution</h3>
                    </div>
                    
                    <div class="row">
                        <?php foreach ($categories as $cat): ?>
                        <div class="col-md-2 col-4 mb-3">
                            <div class="text-center">
                                <div style="font-weight: 700; color: var(--bup-blue);"><?php echo htmlspecialchars($cat['Category']); ?></div>
                                <div style="font-size: 24px; font-weight: 800; color: var(--bup-orange);"><?php echo $cat['count']; ?></div>
                                <div style="font-size: 12px; color: var(--bup-gray);">books</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
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

        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Monthly Orders Chart
            const ctx1 = document.getElementById('ordersChart').getContext('2d');
            
            // Get data from PHP
            const months = <?php echo json_encode(array_column($monthly_orders, 'month')); ?>;
            const ordersData = <?php echo json_encode(array_column($monthly_orders, 'orders')); ?>;
            const revenueData = <?php echo json_encode(array_column($monthly_orders, 'revenue')); ?>;
            
            new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'Orders',
                            data: ordersData,
                            borderColor: '#FF914D',
                            backgroundColor: 'rgba(255,145,77,0.1)',
                            tension: 0.4,
                            fill: true,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Revenue ($)',
                            data: revenueData,
                            borderColor: '#0A3143',
                            backgroundColor: 'rgba(10,49,67,0.1)',
                            tension: 0.4,
                            fill: true,
                            yAxisID: 'y1',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Number of Orders'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Revenue ($)'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });

            // Status Chart
            const ctx2 = document.getElementById('statusChart').getContext('2d');
            
            const statusLabels = <?php echo json_encode(array_keys($order_status)); ?>;
            const statusData = <?php echo json_encode(array_values($order_status)); ?>;
            
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusData,
                        backgroundColor: [
                            '#28a745',
                            '#FF914D',
                            '#0A3143',
                            '#dc3545',
                            '#ffc107'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 15
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
        });
    </script>
</body>
</html>