<?php
// user/cart.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=bup_books", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Cart DB connection error: " . $e->getMessage());
    $db_error = "Could not connect to database. Please try again later.";
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'] ?? ''; // Ensure email exists

// Get user info for sidebar
$userStmt = $pdo->prepare("SELECT Email FROM users WHERE UserID = ?");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
if ($user) {
    $user_email = $user['Email'];
}

// Check if user is a seller (for sidebar)
$is_seller = false;
$seller_id = null;
$stmt = $pdo->prepare("SELECT SellerID FROM seller WHERE UserID = ?");
$stmt->execute([$user_id]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);
if ($seller) {
    $is_seller = true;
    $seller_id = $seller['SellerID'];
}

// Unread notifications count
$unread_notifications = 0;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE UserID = ? AND IsRead = 0");
$stmt->execute([$user_id]);
$unread_notifications = $stmt->fetchColumn();

// Handle remove from cart
if (isset($_POST['remove_item'])) {
    $cart_id = $_POST['cart_id'];
    $stmt = $pdo->prepare("DELETE FROM cart WHERE CartID = ? AND UserID = ?");
    $stmt->execute([$cart_id, $user_id]);
    header('Location: cart.php');
    exit();
}

// Handle update quantity
if (isset($_POST['update_qty'])) {
    $cart_id = $_POST['cart_id'];
    $quantity = $_POST['quantity'];
    
    // Check stock
    $stmt = $pdo->prepare("
        SELECT p.StockQuantity 
        FROM cart c 
        JOIN product p ON c.ProductID = p.ProductID 
        WHERE c.CartID = ?
    ");
    $stmt->execute([$cart_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product && $quantity <= $product['StockQuantity'] && $quantity > 0) {
        $stmt = $pdo->prepare("UPDATE cart SET Quantity = ? WHERE CartID = ? AND UserID = ?");
        $stmt->execute([$quantity, $cart_id, $user_id]);
    }
    header('Location: cart.php');
    exit();
}

// Fetch cart items
$cart_items = [];
$total = 0;

if (!isset($db_error)) {
    $stmt = $pdo->prepare("
        SELECT c.CartID, c.Quantity, c.AddedAt,
               p.ProductID, p.ProductName, p.Price, p.StockQuantity, p.image,
               s.Name as SellerName
        FROM cart c
        JOIN product p ON c.ProductID = p.ProductID
        LEFT JOIN seller s ON p.SellerID = s.SellerID
        WHERE c.UserID = ?
        ORDER BY c.AddedAt DESC
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total
    foreach ($cart_items as $item) {
        $total += $item['Price'] * $item['Quantity'];
    }
}

// Cart count (already from $cart_items, but we need it for sidebar)
$cart_count = count($cart_items);

// Dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - BUP Platform Book Resale</title>
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ========== GLOBAL VARIABLES (matching index.php) ========== */
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
            --bup-gradient: linear-gradient(145deg, var(--bup-blue), var(--bup-blue-light));
            --bup-gradient-accent: linear-gradient(145deg, var(--bup-orange), var(--bup-yellow));
            --shadow-sm: 0 8px 20px rgba(10, 49, 67, 0.05);
            --shadow-md: 0 12px 30px rgba(255, 145, 77, 0.12);
            --shadow-lg: 0 20px 40px rgba(10, 49, 67, 0.15);
            --radius-sm: 12px;
            --radius-md: 16px;
            --radius-lg: 24px;
            --bg-primary: var(--bup-offwhite);
            --text-primary: var(--bup-blue);
            --card-bg: white;
        }

        [data-theme="dark"] {
            --bup-blue: #1a1a2e;
            --bup-blue-light: #16213e;
            --bup-orange: #ff9f4d;
            --bup-orange-dark: #ff8533;
            --bup-yellow: #ffd700;
            --bup-white: #1e1e2f;
            --bup-offwhite: #0f0f1a;
            --bup-gray: #a0a0b0;
            --bup-gray-light: #2a2a3a;
            --bg-primary: #0f0f1a;
            --text-primary: #ffffff;
            --card-bg: #1e1e2f;
            --shadow-sm: 0 8px 20px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 12px 30px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 20px 40px rgba(0, 0, 0, 0.5);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            transition: background-color 0.3s ease, color 0.3s ease;
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
            padding: 30px 25px;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 5px 0 30px rgba(0,0,0,0.15);
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: 80px;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px 40px;
            transition: all 0.3s ease;
        }

        /* Sidebar Toggle Button */
        .sidebar-toggle {
            position: absolute;
            top: 20px;
            right: -15px;
            width: 30px;
            height: 30px;
            background: var(--bup-gradient-accent);
            border: 2px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1001;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            padding: 0;
            color: var(--bup-blue);
        }

        .sidebar-toggle:hover {
            transform: scale(1.1);
            background: var(--bup-orange);
        }

        .sidebar-toggle i {
            font-size: 18px;
            transition: transform 0.3s ease;
        }

        .sidebar.collapsed .sidebar-toggle i {
            transform: rotate(180deg);
        }

        /* Logo */
        .sidebar-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .logo-container {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 15px;
        }

        .logo-glow {
            position: absolute;
            width: 120px;
            height: 120px;
            background: radial-gradient(circle, rgba(255,145,77,0.4) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
            animation: pulse 3s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); opacity: 0.5; }
            50% { transform: scale(1.05); opacity: 0.8; }
            100% { transform: scale(0.95); opacity: 0.5; }
        }

        .logo-image-wrapper {
            position: relative;
            z-index: 1;
            background: linear-gradient(145deg, rgba(255,255,255,0.2), rgba(255,255,255,0.05));
            border-radius: 50%;
            padding: 8px;
            backdrop-filter: blur(10px);
            border: 3px solid rgba(255,255,255,0.3);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }

        .logo-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            transition: all 0.3s ease;
            border: 2px solid rgba(255,255,255,0.5);
        }

        .logo-text-container {
            text-align: center;
            z-index: 1;
            margin-bottom: 8px;
        }

        .logo-title {
            font-size: 18px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--bup-orange), var(--bup-yellow));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 1px;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .logo-subtitle {
            font-size: 11px;
            color: rgba(255,255,255,0.8);
            letter-spacing: 2px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .logo-divider {
            width: 80px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--bup-orange), var(--bup-yellow), transparent);
            margin-top: 5px;
            z-index: 1;
        }

        .user-info {
            text-align: center;
            margin-top: 10px;
        }

        .user-avatar {
            width: 70px;
            height: 70px;
            background: var(--bup-gradient-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            font-weight: 700;
            color: var(--bup-blue);
            box-shadow: 0 8px 0 #C7511E, 0 15px 25px rgba(0,0,0,0.2);
            border: 3px solid white;
        }

        .user-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .user-email {
            font-size: 13px;
            color: rgba(255,255,255,0.8);
            word-break: break-all;
        }

        .seller-badge {
            display: inline-block;
            background: var(--bup-yellow);
            color: var(--bup-blue);
            font-size: 11px;
            font-weight: 800;
            padding: 3px 12px;
            border-radius: 30px;
            margin-top: 8px;
            letter-spacing: 0.5px;
            animation: badgePulse 2s infinite;
        }

        @keyframes badgePulse {
            0% { box-shadow: 0 0 0 0 rgba(255,193,7,0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255,193,7,0); }
            100% { box-shadow: 0 0 0 0 rgba(255,193,7,0); }
        }

        .nav-menu {
            margin-top: 40px;
            list-style: none;
            padding: 0;
        }

        .nav-item {
            margin-bottom: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 16px;
            transition: all 0.3s ease;
            font-weight: 500;
            gap: 15px;
        }

        .nav-link i {
            font-size: 22px;
            width: 25px;
            text-align: center;
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
            box-shadow: 0 6px 0 #C7511E;
        }

        .nav-link.active i {
            color: var(--bup-blue);
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

        /* Loading Spinner */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
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

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .sidebar.collapsed {
                width: 280px;
                transform: translateX(-100%);
            }
            .sidebar.collapsed.active {
                transform: translateX(0);
            }
            .sidebar.collapsed ~ .main-content {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .mobile-menu-btn {
                display: flex;
            }
        }

        /* Cart specific styles (keep original) */
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: var(--card-bg);
            padding: 20px 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(225, 233, 240, 0.5);
        }

        .cart-header h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 0;
            color: var(--text-primary);
        }

        .cart-header p {
            color: var(--bup-gray);
            margin-bottom: 0;
            font-size: 15px;
        }

        .cart-container {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(225, 233, 240, 0.5);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cart-table th {
            background: rgba(255, 145, 77, 0.1);
            padding: 15px 20px;
            text-align: left;
            font-weight: 700;
            color: var(--bup-orange);
            border-bottom: 2px solid var(--bup-orange);
        }

        .cart-table td {
            padding: 20px;
            border-bottom: 1px solid var(--bup-gray-light);
            vertical-align: middle;
        }

        .cart-table tr:last-child td {
            border-bottom: none;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .product-image {
            width: 80px;
            height: 100px;
            background: linear-gradient(145deg, var(--bup-blue), var(--bup-blue-light));
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.5);
            font-size: 40px;
        }

        .product-details h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .product-details .seller {
            font-size: 14px;
            color: var(--bup-gray);
        }

        .product-details .seller i {
            color: var(--bup-orange);
            margin-right: 5px;
        }

        .price {
            font-size: 18px;
            font-weight: 700;
            color: var(--bup-orange);
        }

        .quantity-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-input {
            width: 70px;
            padding: 8px;
            border: 2px solid var(--bup-gray-light);
            border-radius: var(--radius-sm);
            text-align: center;
            font-weight: 600;
            background: var(--card-bg);
            color: var(--text-primary);
        }

        .quantity-input:focus {
            outline: none;
            border-color: var(--bup-orange);
        }

        .update-btn {
            background: var(--bup-gray-light);
            border: none;
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            color: var(--bup-blue);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .update-btn:hover {
            background: var(--bup-orange);
            color: white;
        }

        .remove-btn {
            background: none;
            border: none;
            color: #dc3545;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .remove-btn:hover {
            transform: scale(1.2);
        }

        .stock-badge {
            display: inline-block;
            padding: 4px 8px;
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }

        .stock-badge.low {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .cart-summary {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(225, 233, 240, 0.5);
            margin-top: 30px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--bup-gray-light);
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            font-size: 16px;
            color: var(--bup-gray);
        }

        .summary-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .total-row {
            font-size: 24px;
            font-weight: 800;
            color: var(--bup-orange);
        }

        .cart-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            justify-content: flex-end;
        }

        .btn-checkout {
            background: var(--bup-gradient-accent);
            color: var(--bup-blue);
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 0 #C7511E;
            text-decoration: none;
            display: inline-block;
        }

        .btn-checkout:hover {
            transform: translateY(2px);
            box-shadow: 0 2px 0 #C7511E;
            color: var(--bup-blue);
        }

        .btn-continue {
            background: var(--bup-gray-light);
            color: var(--bup-blue);
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-continue:hover {
            background: var(--bup-orange);
            color: white;
        }

        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-cart i {
            font-size: 80px;
            color: var(--bup-gray-light);
            margin-bottom: 20px;
        }

        .empty-cart h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .empty-cart p {
            color: var(--bup-gray);
            margin-bottom: 30px;
        }

        .btn-browse {
            background: var(--bup-gradient-accent);
            color: var(--bup-blue);
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 0 #C7511E;
            text-decoration: none;
            display: inline-block;
        }

        .btn-browse:hover {
            transform: translateY(2px);
            box-shadow: 0 2px 0 #C7511E;
            color: var(--bup-blue);
        }

        .toast-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 9999;
            animation: slideIn 0.3s ease;
        }

        .toast-message {
            background: #28a745;
            color: white;
            padding: 15px 25px;
            border-radius: 50px;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 16px;
            border-left: 5px solid white;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">

    <!-- Loading Spinner -->
    <div class="spinner-overlay" id="loadingSpinner">
        <div class="spinner"></div>
    </div>

    <!-- Toast Container for Notifications -->
    <div class="toast-container" id="toastContainer" style="display: none;">
        <div class="toast-message">
            <i class="bi bi-check-circle-fill"></i>
            <span id="toastMessage">Cart updated!</span>
        </div>
    </div>

    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>

    <!-- Sidebar (matching index.php) -->
    <div class="sidebar" id="sidebar">
        <!-- Sidebar Toggle Button -->
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-chevron-left" id="toggleIcon"></i>
        </button>

        <div class="sidebar-logo">
            <div class="logo-container">
                <div class="logo-glow"></div>
                <div class="logo-image-wrapper">
                    <img src="../assets/img/logo.jpg" alt="BUP Platform Book Resale" class="logo-image">
                </div>
                <div class="logo-text-container">
                    <div class="logo-title">BUP Platform</div>
                    <div class="logo-subtitle">Book Resale</div>
                </div>
                <div class="logo-divider"></div>
            </div>
        </div>

        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user_name, 0, 2)); ?>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
            <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
            <?php if ($is_seller): ?>
                <span class="seller-badge"><i class="bi bi-shop me-1"></i>SELLER</span>
            <?php endif; ?>
        </div>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="bi bi-book"></i>
                    <span class="nav-text">Browse Books</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="my_orders.php" class="nav-link">
                    <i class="bi bi-box"></i>
                    <span class="nav-text">My Orders</span>
                </a>
            </li>
            <?php if ($is_seller): ?>
            <li class="nav-item">
                <a href="my-books.php" class="nav-link">
                    <i class="bi bi-journal"></i>
                    <span class="nav-text">My Books</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="sell.php" class="nav-link">
                    <i class="bi bi-plus-circle"></i>
                    <span class="nav-text">Sell a Book</span>
                </a>
            </li>
            <?php else: ?>
            <li class="nav-item">
                <a href="become-seller.php" class="nav-link">
                    <i class="bi bi-shop"></i>
                    <span class="nav-text">Become a Seller</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a href="cart.php" class="nav-link active">
                    <i class="bi bi-cart"></i>
                    <span class="nav-text">My Cart</span>
                    <?php if ($cart_count > 0): ?>
                    <span class="badge bg-warning text-dark ms-auto"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="notifications.php" class="nav-link">
                    <i class="bi bi-bell"></i>
                    <span class="nav-text">Notifications</span>
                    <?php if ($unread_notifications > 0): ?>
                    <span class="badge bg-danger ms-auto"><?php echo $unread_notifications; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Cart Header -->
        <div class="cart-header">
            <div>
                <h1><i class="bi bi-cart me-2" style="color: var(--bup-orange);"></i>Shopping Cart</h1>
                <p><i class="bi bi-bag me-1"></i> <?php echo $cart_count; ?> item(s) in your cart</p>
            </div>
            <div>
                <a href="index.php" class="btn-continue">
                    <i class="bi bi-arrow-left me-2"></i>Continue Shopping
                </a>
            </div>
        </div>

        <?php if (empty($cart_items)): ?>
            <!-- Empty Cart -->
            <div class="empty-cart">
                <i class="bi bi-cart-x"></i>
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added any books to your cart yet.</p>
                <a href="browse-books.php" class="btn-browse">
                    <i class="bi bi-book me-2"></i>Browse Books
                </a>
            </div>
        <?php else: ?>
            <!-- Cart Items -->
            <div class="cart-container">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): 
                            $subtotal = $item['Price'] * $item['Quantity'];
                            $stock_class = $item['StockQuantity'] <= 2 ? 'low' : '';
                        ?>
                        <tr>
                            <td>
                                <div class="product-info">
                                    <div class="product-image">
                                        <i class="bi bi-journal-bookmark-fill"></i>
                                    </div>
                                    <div class="product-details">
                                        <h3><?php echo htmlspecialchars($item['ProductName']); ?></h3>
                                        <div class="seller">
                                            <i class="bi bi-shop"></i>
                                            <?php echo htmlspecialchars($item['SellerName'] ?? 'Unknown Seller'); ?>
                                        </div>
                                        <span class="stock-badge <?php echo $stock_class; ?>">
                                            <i class="bi bi-box-seam me-1"></i>
                                            <?php echo $item['StockQuantity']; ?> in stock
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="price">₱<?php echo number_format($item['Price'], 2); ?></td>
                            <td>
                                <form method="post" class="quantity-form">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['CartID']; ?>">
                                    <input type="number" name="quantity" value="<?php echo $item['Quantity']; ?>" 
                                           min="1" max="<?php echo $item['StockQuantity']; ?>" 
                                           class="quantity-input" 
                                           data-cart-id="<?php echo $item['CartID']; ?>"
                                           data-price="<?php echo $item['Price']; ?>">
                                    <button type="submit" name="update_qty" class="update-btn">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </form>
                            </td>
                            <td class="price subtotal-<?php echo $item['CartID']; ?>">
                                ₱<?php echo number_format($subtotal, 2); ?>
                            </td>
                            <td>
                                <form method="post" onsubmit="return confirm('Remove this item from cart?');">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['CartID']; ?>">
                                    <button type="submit" name="remove_item" class="remove-btn">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Cart Summary -->
            <div class="cart-summary">
                <div class="summary-row">
                    <span class="summary-label">Subtotal</span>
                    <span class="summary-value">₱<?php echo number_format($total, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Shipping</span>
                    <span class="summary-value">Calculated at checkout</span>
                </div>
                <div class="summary-row total-row">
                    <span class="summary-label">Total</span>
                    <span class="summary-value">₱<?php echo number_format($total, 2); ?></span>
                </div>
                
                <div class="cart-actions">
                    <a href="checkout.php" class="btn-checkout">
                        <i class="bi bi-credit-card me-2"></i>Proceed to Checkout
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    
                    if (sidebar.classList.contains('collapsed')) {
                        toggleIcon.classList.remove('bi-chevron-left');
                        toggleIcon.classList.add('bi-chevron-right');
                        localStorage.setItem('sidebarCollapsed', 'true');
                    } else {
                        toggleIcon.classList.remove('bi-chevron-right');
                        toggleIcon.classList.add('bi-chevron-left');
                        localStorage.setItem('sidebarCollapsed', 'false');
                    }
                });
            }
            
            // Check localStorage for saved sidebar state
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true' && window.innerWidth > 992) {
                sidebar.classList.add('collapsed');
                if (toggleIcon) {
                    toggleIcon.classList.remove('bi-chevron-left');
                    toggleIcon.classList.add('bi-chevron-right');
                }
            }
            
            // Reset on window resize if needed
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('collapsed');
                    if (toggleIcon) {
                        toggleIcon.classList.remove('bi-chevron-right');
                        toggleIcon.classList.add('bi-chevron-left');
                    }
                }
            });
        });

        // Toggle sidebar on mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        // Show/hide mobile menu button based on screen width
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

        // Show toast message
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toastMessage = document.getElementById('toastMessage');
            const toast = toastContainer.querySelector('.toast-message');
            
            if (type === 'error') {
                toast.style.background = '#dc3545';
            } else if (type === 'warning') {
                toast.style.background = '#ffc107';
            } else if (type === 'info') {
                toast.style.background = '#17a2b8';
            } else {
                toast.style.background = '#28a745';
            }
            
            toastMessage.textContent = message;
            toastContainer.style.display = 'block';
            
            setTimeout(() => {
                toastContainer.style.display = 'none';
            }, 3000);
            
            toastContainer.addEventListener('click', function() {
                this.style.display = 'none';
            });
        }

        // Show loading spinner on link clicks
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href && !href.startsWith('#') && !href.startsWith('javascript:') && !this.classList.contains('no-loader')) {
                    document.getElementById('loadingSpinner').style.display = 'flex';
                }
            });
        });

        // Hide spinner on page load
        window.addEventListener('load', function() {
            document.getElementById('loadingSpinner').style.display = 'none';
            
            // Check for URL parameters (success message)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('added') === 'success') {
                showToast('Book added to cart successfully!');
            }
        });

        // Quantity input validation
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                const max = parseInt(this.getAttribute('max'));
                const value = parseInt(this.value);
                
                if (value > max) {
                    this.value = max;
                    showToast('Cannot exceed available stock', 'warning');
                }
                if (value < 1) {
                    this.value = 1;
                }
            });
        });
    </script>

    <?php if (isset($db_error)): ?>
    <script>
        alert("<?php echo addslashes($db_error); ?>");
    </script>
    <?php endif; ?>
</body>
</html>