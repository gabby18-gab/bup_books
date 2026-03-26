<?php
// user/my_orders.php
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
    error_log("Orders DB connection error: " . $e->getMessage());
    die("Database connection error");
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email']; // Assumes email stored in session

$message = '';
$error = '';

// Handle order cancellation (same as before)
// ... (keep existing cancellation and confirmation logic)

// Get filter from URL
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Check for message in URL
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Build query based on filter
$query = "
    SELECT o.*, 
           (SELECT COUNT(*) FROM orderdetails WHERE OrderID = o.OrderID) as item_count,
           (SELECT SUM(Quantity) FROM orderdetails WHERE OrderID = o.OrderID) as total_items,
           (SELECT GROUP_CONCAT(CONCAT(p.ProductName, ' (', od.Quantity, ')') SEPARATOR ' | ') 
            FROM orderdetails od 
            JOIN product p ON od.ProductID = p.ProductID 
            WHERE od.OrderID = o.OrderID) as product_list
    FROM orders o 
    WHERE o.UserID = ?
";

$params = [$user_id];

if ($status_filter !== 'all') {
    $query .= " AND o.Status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (o.OrderID LIKE ? OR o.product_list LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY o.OrderDate DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order counts by status
$counts = [
    'all' => 0,
    'pending' => 0,
    'processing' => 0,
    'shipped' => 0,
    'completed' => 0,
    'cancelled' => 0
];

$countStmt = $pdo->prepare("SELECT Status, COUNT(*) as count FROM orders WHERE UserID = ? GROUP BY Status");
$countStmt->execute([$user_id]);
while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) {
    $counts[$row['Status']] = $row['count'];
    $counts['all'] += $row['count'];
}

// Get user info for profile (including email if not in session)
$userStmt = $pdo->prepare("SELECT * FROM users WHERE UserID = ?");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
$user_email = $user['Email']; // override if not in session

// --- Additional data for sidebar ---
// Check if user is a seller
$is_seller = false;
$seller_id = null;
$stmt = $pdo->prepare("SELECT SellerID FROM seller WHERE UserID = ?");
$stmt->execute([$user_id]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);
if ($seller) {
    $is_seller = true;
    $seller_id = $seller['SellerID'];
}

// Cart count
$cart_count = 0;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE UserID = ?");
$stmt->execute([$user_id]);
$cart_count = $stmt->fetchColumn();

// Unread notifications count
$unread_notifications = 0;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE UserID = ? AND IsRead = 0");
$stmt->execute([$user_id]);
$unread_notifications = $stmt->fetchColumn();

// Dark mode preference (optional)
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - BUP Platform Book Resale</title>
    
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

        /* Page specific styles (unchanged from original my_orders.php) */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 25px 30px;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid var(--bup-gray-light);
        }

        .page-header h1 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .page-header p {
            color: var(--bup-gray);
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #e1e1e1;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(255,145,77,0.15);
            border-color: var(--bup-orange);
        }

        .stat-card.active {
            border: 2px solid var(--bup-orange);
            background: rgba(255,145,77,0.05);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(145deg, rgba(255,145,77,0.1), rgba(255,193,7,0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 24px;
            color: var(--bup-orange);
        }

        .stat-number {
            font-size: 24px;
            font-weight: 800;
            color: var(--bup-blue);
        }

        .stat-label {
            font-size: 13px;
            color: var(--bup-gray);
            font-weight: 500;
        }

        .filter-bar {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            border: 1px solid var(--bup-gray-light);
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--bup-gray);
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e1e1e1;
            border-radius: 30px;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--bup-orange);
        }

        .status-filter {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 20px;
            border-radius: 30px;
            border: 2px solid #e1e1e1;
            background: white;
            color: var(--bup-gray);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .filter-btn:hover {
            border-color: var(--bup-orange);
            color: var(--bup-orange);
        }

        .filter-btn.active {
            background: var(--bup-orange);
            border-color: var(--bup-orange);
            color: white;
        }

        .orders-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .order-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid #e1e1e1;
            transition: all 0.3s ease;
        }

        .order-card:hover {
            box-shadow: 0 8px 20px rgba(255,145,77,0.15);
            border-color: var(--bup-orange);
        }

        .order-header {
            background: linear-gradient(145deg, #f8f9fa, #e9ecef);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #dee2e6;
            flex-wrap: wrap;
            gap: 10px;
        }

        .order-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .order-id {
            font-size: 18px;
            font-weight: 700;
            color: var(--bup-blue);
        }

        .order-date {
            color: var(--bup-gray);
            font-size: 14px;
        }

        .order-status {
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange);
        }

        .status-processing {
            background: rgba(0,123,255,0.15);
            color: #007bff;
        }

        .status-shipped {
            background: rgba(23,162,184,0.15);
            color: #17a2b8;
        }

        .status-completed {
            background: rgba(40,167,69,0.15);
            color: var(--bup-green);
        }

        .status-cancelled {
            background: rgba(220,53,69,0.15);
            color: var(--bup-red);
        }

        .order-body {
            padding: 20px;
        }

        .product-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .product-list i {
            color: var(--bup-orange);
            margin-right: 8px;
        }

        .cancellation-reason {
            background: #f8d7da;
            padding: 10px 15px;
            border-radius: 12px;
            margin-top: 10px;
            font-size: 14px;
            color: #721c24;
        }

        .tracking-info {
            background: #e8f4fd;
            padding: 10px 15px;
            border-radius: 12px;
            margin-top: 10px;
            font-size: 14px;
        }

        .order-footer {
            background: #f8f9fa;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border-top: 1px solid #dee2e6;
        }

        .order-total {
            font-size: 18px;
            font-weight: 700;
        }

        .total-amount {
            color: var(--bup-orange);
            font-size: 24px;
            font-weight: 800;
            margin-left: 10px;
        }

        .order-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-cancel {
            background: rgba(220,53,69,0.15);
            color: #dc3545;
            border: 1px solid #dc3545;
        }

        .btn-cancel:hover {
            background: #dc3545;
            color: white;
        }

        .btn-confirm {
            background: rgba(40,167,69,0.15);
            color: #28a745;
            border: 1px solid #28a745;
        }

        .btn-confirm:hover {
            background: #28a745;
            color: white;
        }

        .btn-track {
            background: rgba(0,123,255,0.15);
            color: #007bff;
            border: 1px solid #007bff;
        }

        .btn-track:hover {
            background: #007bff;
            color: white;
        }

        .btn-view {
            background: rgba(108,117,125,0.15);
            color: #6c757d;
            border: 1px solid #6c757d;
        }

        .btn-view:hover {
            background: #6c757d;
            color: white;
        }

        .btn-disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .empty-orders {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .empty-orders i {
            font-size: 80px;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        .empty-orders h3 {
            color: var(--bup-blue);
            margin-bottom: 10px;
        }

        .empty-orders p {
            color: var(--bup-gray);
            margin-bottom: 20px;
        }

        .btn-shop {
            background: var(--bup-orange);
            color: white;
            padding: 12px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
        }

        .btn-shop:hover {
            background: #e67a3a;
            color: white;
        }

        /* Modal */
        .modal-content {
            border-radius: 20px;
            overflow: hidden;
        }

        .modal-header {
            background: var(--bup-blue);
            color: white;
            padding: 20px;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid #e1e1e1;
        }

        .btn-modal-cancel {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 600;
        }

        .btn-modal-close {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 600;
        }

        .alert {
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
            border: none;
        }

        .alert-success {
            background: rgba(40,167,69,0.15);
            color: #28a745;
            border-left: 5px solid #28a745;
        }

        .alert-danger {
            background: rgba(220,53,69,0.15);
            color: #dc3545;
            border-left: 5px solid #dc3545;
        }
    </style>
</head>
<body data-theme="<?php echo $dark_mode ? 'dark' : 'light'; ?>">

    <!-- Loading Spinner -->
    <div class="spinner-overlay" id="loadingSpinner">
        <div class="spinner"></div>
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
                <a href="my_orders.php" class="nav-link active">
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
                <a href="cart.php" class="nav-link">
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
        <!-- Page Header (unchanged) -->
        <div class="page-header">
            <div>
                <h1><i class="bi bi-box me-2" style="color: var(--bup-orange);"></i>My Orders</h1>
                <p><i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($user_name); ?> · <?php echo $counts['all']; ?> total orders</p>
            </div>
            <div>
                <a href="profile.php" class="btn" style="background: var(--bup-light-gray); color: var(--bup-blue); padding: 10px 20px; border-radius: 30px; text-decoration: none;">
                    <i class="bi bi-gear me-2"></i>Account Settings
                </a>
            </div>
        </div>

        <!-- Messages (unchanged) -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Status Stats Cards (unchanged) -->
        <div class="stats-grid">
            <a href="?status=all" class="stat-card <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                <div class="stat-icon"><i class="bi bi-grid"></i></div>
                <div class="stat-number"><?php echo $counts['all']; ?></div>
                <div class="stat-label">All Orders</div>
            </a>
            <a href="?status=pending" class="stat-card <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                <div class="stat-number"><?php echo $counts['pending']; ?></div>
                <div class="stat-label">To Pay</div>
            </a>
            <a href="?status=processing" class="stat-card <?php echo $status_filter == 'processing' ? 'active' : ''; ?>">
                <div class="stat-icon"><i class="bi bi-gear"></i></div>
                <div class="stat-number"><?php echo $counts['processing']; ?></div>
                <div class="stat-label">Processing</div>
            </a>
            <a href="?status=shipped" class="stat-card <?php echo $status_filter == 'shipped' ? 'active' : ''; ?>">
                <div class="stat-icon"><i class="bi bi-truck"></i></div>
                <div class="stat-number"><?php echo $counts['shipped']; ?></div>
                <div class="stat-label">To Receive</div>
            </a>
            <a href="?status=completed" class="stat-card <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">
                <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                <div class="stat-number"><?php echo $counts['completed']; ?></div>
                <div class="stat-label">Completed</div>
            </a>
            <a href="?status=cancelled" class="stat-card <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">
                <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                <div class="stat-number"><?php echo $counts['cancelled']; ?></div>
                <div class="stat-label">Cancelled</div>
            </a>
        </div>

        <!-- Filter Bar (unchanged) -->
        <div class="filter-bar">
            <form method="GET" class="d-flex w-100 gap-3 flex-wrap">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" placeholder="Search by Order ID or Book Title..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="status-filter">
                    <a href="?status=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
                    <a href="?status=pending<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">To Pay</a>
                    <a href="?status=processing<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo $status_filter == 'processing' ? 'active' : ''; ?>">Processing</a>
                    <a href="?status=shipped<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo $status_filter == 'shipped' ? 'active' : ''; ?>">To Receive</a>
                    <a href="?status=completed<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">Completed</a>
                    <a href="?status=cancelled<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
                </div>
                <?php if (!empty($search)): ?>
                    <a href="my_orders.php" class="filter-btn">Clear Search</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Orders List (unchanged) -->
        <div class="orders-container">
            <?php if (count($orders) > 0): ?>
                <?php foreach ($orders as $order): 
                    $status_class = '';
                    $status_text = '';
                    
                    switch($order['Status']) {
                        case 'pending':
                            $status_class = 'status-pending';
                            $status_text = '⏳ To Pay';
                            break;
                        case 'processing':
                            $status_class = 'status-processing';
                            $status_text = '⚙️ Processing';
                            break;
                        case 'shipped':
                            $status_class = 'status-shipped';
                            $status_text = '🚚 To Receive';
                            break;
                        case 'completed':
                            $status_class = 'status-completed';
                            $status_text = '✅ Completed';
                            break;
                        case 'cancelled':
                            $status_class = 'status-cancelled';
                            $status_text = '❌ Cancelled';
                            break;
                    }
                ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-info">
                            <span class="order-id">#<?php echo str_pad($order['OrderID'], 6, '0', STR_PAD_LEFT); ?></span>
                            <span class="order-date">
                                <i class="bi bi-calendar3"></i> <?php echo date('M d, Y', strtotime($order['OrderDate'])); ?>
                                <span class="ms-2"><i class="bi bi-clock"></i> <?php echo date('h:i A', strtotime($order['OrderDate'])); ?></span>
                            </span>
                        </div>
                        <span class="order-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                    </div>
                    
                    <div class="order-body">
                        <div class="product-list">
                            <i class="bi bi-bag"></i>
                            <?php echo htmlspecialchars($order['product_list'] ?? 'No items'); ?>
                        </div>
                        
                        <?php if ($order['Status'] == 'cancelled' && !empty($order['CancellationReason'])): ?>
                        <div class="cancellation-reason">
                            <i class="bi bi-info-circle"></i>
                            <strong>Cancellation Reason:</strong> <?php echo htmlspecialchars($order['CancellationReason']); ?>
                            <?php if ($order['CancelledBy'] == 'user'): ?>
                                <span class="ms-2 badge bg-secondary">Cancelled by you</span>
                            <?php else: ?>
                                <span class="ms-2 badge bg-danger">Cancelled by admin</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($order['Status'] == 'shipped' && !empty($order['TrackingNumber'])): ?>
                        <div class="tracking-info">
                            <i class="bi bi-truck"></i>
                            <strong>Tracking Number:</strong> <?php echo htmlspecialchars($order['TrackingNumber']); ?>
                            <?php if (!empty($order['EstimatedDelivery'])): ?>
                                <span class="ms-3">📅 Estimated: <?php echo date('M d, Y', strtotime($order['EstimatedDelivery'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($order['Status'] == 'completed' && !empty($order['DeliveredAt'])): ?>
                        <div class="tracking-info" style="background: #d4edda; color: #155724;">
                            <i class="bi bi-check-circle"></i>
                            <strong>Delivered on:</strong> <?php echo date('M d, Y \a\t h:i A', strtotime($order['DeliveredAt'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="order-footer">
                        <div class="order-total">
                            Total: <span class="total-amount">₱<?php echo number_format($order['TotalAmount'], 2); ?></span>
                            <span class="ms-2 text-muted">(<?php echo $order['total_items']; ?> items)</span>
                        </div>
                        
                        <div class="order-actions">
                            <?php if ($order['Status'] == 'pending'): ?>
                                <a href="javascript:void(0)" onclick="showCancelModal(<?php echo $order['OrderID']; ?>)" class="btn-action btn-cancel">
                                    <i class="bi bi-x-circle"></i> Cancel Order
                                </a>
                                <a href="payment.php?order_id=<?php echo $order['OrderID']; ?>" class="btn-action btn-confirm">
                                    <i class="bi bi-credit-card"></i> Pay Now
                                </a>
                            <?php elseif ($order['Status'] == 'processing'): ?>
                                <a href="order-details.php?id=<?php echo $order['OrderID']; ?>" class="btn-action btn-view">
                                    <i class="bi bi-eye"></i> View Details
                                </a>
                                <span class="btn-action btn-disabled" style="background: #f8f9fa; color: #6c757d;">
                                    <i class="bi bi-clock-history"></i> Processing
                                </span>
                            <?php elseif ($order['Status'] == 'shipped'): ?>
                                <a href="track-order.php?id=<?php echo $order['OrderID']; ?>" class="btn-action btn-track">
                                    <i class="bi bi-truck"></i> Track
                                </a>
                                <a href="?confirm_received=<?php echo $order['OrderID']; ?>" class="btn-action btn-confirm" onclick="return confirm('Have you received this order?')">
                                    <i class="bi bi-check-circle"></i> Received
                                </a>
                            <?php elseif ($order['Status'] == 'completed'): ?>
                                <a href="order-details.php?id=<?php echo $order['OrderID']; ?>" class="btn-action btn-view">
                                    <i class="bi bi-eye"></i> View Details
                                </a>
                                <a href="review.php?order_id=<?php echo $order['OrderID']; ?>" class="btn-action btn-confirm">
                                    <i class="bi bi-star"></i> Review
                                </a>
                            <?php elseif ($order['Status'] == 'cancelled'): ?>
                                <a href="order-details.php?id=<?php echo $order['OrderID']; ?>" class="btn-action btn-view">
                                    <i class="bi bi-eye"></i> View Details
                                </a>
                                <a href="browse-books.php" class="btn-action btn-track">
                                    <i class="bi bi-cart-plus"></i> Reorder
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-orders">
                    <i class="bi bi-box-seam"></i>
                    <h3>No Orders Found</h3>
                    <p>
                        <?php if ($status_filter != 'all'): ?>
                            You don't have any <?php echo $status_filter; ?> orders at the moment.
                        <?php else: ?>
                            You haven't placed any orders yet. Start shopping to see your orders here!
                        <?php endif; ?>
                    </p>
                    <a href="browse-books.php" class="btn-shop">
                        <i class="bi bi-book me-2"></i>Browse Books
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cancel Order Modal (unchanged) -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Cancel Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="cancelForm">
                    <div class="modal-body">
                        <p>Are you sure you want to cancel this order? This action cannot be undone.</p>
                        
                        <div class="mb-3">
                            <label for="cancel_reason" class="form-label">Reason for cancellation (optional):</label>
                            <select name="cancel_reason" id="cancel_reason" class="form-select">
                                <option value="Changed my mind">Changed my mind</option>
                                <option value="Found better price">Found better price</option>
                                <option value="Ordered by mistake">Ordered by mistake</option>
                                <option value="Shipping too long">Shipping too long</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-info-circle me-2"></i>
                            Upon cancellation, the items will be restocked and your payment will be refunded within 3-5 business days.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modal-close" data-bs-dismiss="modal">
                            <i class="bi bi-x"></i> Close
                        </button>
                        <button type="submit" class="btn-modal-cancel">
                            <i class="bi bi-check"></i> Confirm Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
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

        // Cancel order modal
        function showCancelModal(orderId) {
            const form = document.getElementById('cancelForm');
            form.action = 'my_orders.php?cancel_order=' + orderId;
            
            const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
            modal.show();
        }

        // Auto-hide alerts
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);

        // Show loading spinner on link clicks (excluding modal triggers)
        document.querySelectorAll('a:not([data-bs-toggle]):not(.btn-close)').forEach(link => {
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
        });
    </script>
</body>
</html>