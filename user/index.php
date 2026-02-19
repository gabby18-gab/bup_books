<?php
// user/index.php
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
    error_log("Dashboard DB connection error: " . $e->getMessage());
    $db_error = "Could not connect to database. Please try again later.";
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Fetch user stats
$stats = [
    'listed_books' => 0,
    'active_orders' => 0,
    'completed_trades' => 0,
    'cart_count' => 0,
    'notification_count' => 0
];

if (!isset($db_error)) {
    // Books listed by user (as seller)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM product WHERE SellerID = (SELECT SellerID FROM seller WHERE UserID = ?)");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['listed_books'] = $result ? $result['count'] : 0;

    // Orders as buyer
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE UserID = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['active_orders'] = $result ? $result['count'] : 0;

    // Completed transactions
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT o.OrderID) as count 
        FROM orders o 
        LEFT JOIN transactions t ON o.OrderID = t.OrderID 
        WHERE o.UserID = ? AND t.Status = 'Completed'
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['completed_trades'] = $result ? $result['count'] : 0;
    
    // Cart count
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE UserID = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['cart_count'] = $result ? $result['count'] : 0;
    } catch (PDOException $e) {
        $stats['cart_count'] = 0;
    }
    
    // Simple notification count - just a static number for now
    $stats['notification_count'] = 3; // Show 3 notifications
    
    // Get seller ID if user is a seller
    $stmt = $pdo->prepare("SELECT SellerID FROM seller WHERE UserID = ?");
    $stmt->execute([$user_id]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);
    $seller_id = $seller ? $seller['SellerID'] : null;
}

// Fetch available books for sale
$available_books = [];
if (!isset($db_error)) {
    try {
        // Check what column name exists in users table
        $stmt = $pdo->prepare("DESCRIBE users");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Determine the correct name column
        $name_column = 'UserName'; // default
        if (in_array('FullName', $columns)) {
            $name_column = 'FullName';
        } elseif (in_array('Name', $columns)) {
            $name_column = 'Name';
        } elseif (in_array('username', $columns)) {
            $name_column = 'username';
        } elseif (in_array('user_name', $columns)) {
            $name_column = 'user_name';
        }
        
        $stmt = $pdo->prepare("
            SELECT p.ProductID, p.ProductName, p.Category, p.Price, p.StockQuantity, p.image, 
                   u.$name_column as SellerName, u.ContactNo as SellerContact
            FROM product p
            LEFT JOIN seller s ON p.SellerID = s.SellerID
            LEFT JOIN users u ON s.UserID = u.UserID
            WHERE p.Status = 'A' AND p.StockQuantity > 0
            ORDER BY p.ProductID DESC
            LIMIT 6
        ");
        $stmt->execute();
        $available_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching books: " . $e->getMessage());
        $available_books = [];
    }
}

// If no books in database, show sample books
if (empty($available_books)) {
    $available_books = [
        [
            'ProductID' => 1,
            'ProductName' => 'Engineering Mathematics',
            'Category' => 'Mathematics',
            'Price' => 42.60,
            'StockQuantity' => 5,
            'image' => null,
            'SellerName' => 'John Doe',
            'SellerContact' => '01712345678'
        ],
        [
            'ProductID' => 2,
            'ProductName' => 'Introduction to Algorithms',
            'Category' => 'Computer Science',
            'Price' => 45.00,
            'StockQuantity' => 3,
            'image' => null,
            'SellerName' => 'Jane Smith',
            'SellerContact' => '01812345678'
        ],
        [
            'ProductID' => 3,
            'ProductName' => 'Physics for Scientists and Engineers',
            'Category' => 'Physics',
            'Price' => 55.00,
            'StockQuantity' => 2,
            'image' => null,
            'SellerName' => 'Robert Johnson',
            'SellerContact' => '01912345678'
        ],
        [
            'ProductID' => 4,
            'ProductName' => 'Organic Chemistry',
            'Category' => 'Chemistry',
            'Price' => 38.50,
            'StockQuantity' => 4,
            'image' => null,
            'SellerName' => 'Maria Garcia',
            'SellerContact' => '01612345678'
        ],
        [
            'ProductID' => 5,
            'ProductName' => 'Data Structures and Algorithms',
            'Category' => 'Computer Science',
            'Price' => 49.99,
            'StockQuantity' => 1,
            'image' => null,
            'SellerName' => 'David Wilson',
            'SellerContact' => '01512345678'
        ],
        [
            'ProductID' => 6,
            'ProductName' => 'Calculus: Early Transcendentals',
            'Category' => 'Mathematics',
            'Price' => 47.25,
            'StockQuantity' => 6,
            'image' => null,
            'SellerName' => 'Sarah Brown',
            'SellerContact' => '01412345678'
        ]
    ];
}

// Fetch user's profile completion
$profile_complete = false;
$profile_data = [];
if (!isset($db_error)) {
    try {
        $stmt = $pdo->prepare("SELECT Address, ContactNo FROM users WHERE UserID = ?");
        $stmt->execute([$user_id]);
        $profile_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $profile_complete = !empty($profile_data['Address']) && !empty($profile_data['ContactNo']);
    } catch (PDOException $e) {
        error_log("Error fetching profile: " . $e->getMessage());
    }
}

// Check if user is a seller
$is_seller = isset($seller_id);

// Handle dark mode preference
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'enabled';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BUP BOOKS</title>
    
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

        /* Collapsed Sidebar */
        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar.collapsed .logo-text,
        .sidebar.collapsed .logo-sub,
        .sidebar.collapsed .user-name,
        .sidebar.collapsed .user-email,
        .sidebar.collapsed .seller-badge,
        .sidebar.collapsed .nav-text {
            display: none;
        }

        .sidebar.collapsed .logo-wrapper {
            padding: 12px 0;
            width: 50px;
            margin: 0 auto;
        }

        .sidebar.collapsed .logo-wrapper::before {
            display: none;
        }

        .sidebar.collapsed .user-info {
            margin-top: 20px;
        }

        .sidebar.collapsed .user-avatar {
            width: 50px;
            height: 50px;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .sidebar.collapsed .nav-link {
            padding: 14px 0;
            justify-content: center;
        }

        .sidebar.collapsed .nav-link i {
            margin: 0;
            font-size: 24px;
        }

        .sidebar.collapsed .nav-link:hover {
            transform: translateX(0);
        }

        .sidebar.collapsed .sidebar-toggle {
            right: -15px;
        }

        .sidebar.collapsed .sidebar-toggle i {
            transform: rotate(180deg);
        }

        /* Adjust main content when sidebar is collapsed */
        .sidebar.collapsed ~ .main-content {
            margin-left: 80px;
        }

        .sidebar-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .logo-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.1);
            padding: 12px 25px;
            border-radius: 20px;
            position: relative;
            margin-bottom: 5px;
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }

        .logo-wrapper::before {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            right: -3px;
            bottom: -3px;
            background: var(--bup-gradient-accent);
            border-radius: 22px;
            z-index: -1;
            opacity: 0.5;
            transition: all 0.3s ease;
        }

        .logo-text {
            font-size: 32px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--bup-orange), var(--bup-yellow));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 3px;
            line-height: 1;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }

        .logo-sub {
            font-size: 13px;
            font-weight: 600;
            color: white;
            letter-spacing: 2px;
            background: rgba(255,145,77,0.3);
            padding: 3px 12px;
            border-radius: 30px;
            margin-top: 3px;
            transition: all 0.3s ease;
        }

        .user-info {
            text-align: center;
            margin-top: 10px;
            transition: all 0.3s ease;
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
            transition: all 0.3s ease;
        }

        .user-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
            transition: all 0.3s ease;
        }

        .user-email {
            font-size: 13px;
            color: rgba(255,255,255,0.8);
            word-break: break-all;
            transition: all 0.3s ease;
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
            transition: all 0.3s ease;
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
            transition: all 0.3s ease;
        }

        .nav-text {
            transition: all 0.3s ease;
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

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 30px 40px;
            transition: all 0.3s ease;
        }

        /* Header */
        .dashboard-header {
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

        .page-title h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .page-title p {
            color: var(--bup-gray);
            margin-bottom: 0;
            font-size: 15px;
        }

        .header-actions {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        /* Search Bar */
        .search-container {
            position: relative;
            width: 300px;
        }

        .search-input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 2px solid var(--bup-gray-light);
            border-radius: 50px;
            background: var(--card-bg);
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--bup-orange);
            box-shadow: 0 0 0 3px rgba(255, 145, 77, 0.2);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--bup-gray);
            font-size: 18px;
        }

        /* Simple Notification Icon */
        .notification-wrapper {
            position: relative;
            cursor: pointer;
        }

        .notification-icon {
            font-size: 24px;
            color: var(--bup-gray);
            transition: all 0.3s ease;
        }

        .notification-icon:hover {
            color: var(--bup-orange);
            transform: scale(1.1);
        }

        .notification-badge {
            position: absolute;
            top: -8px;
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

        /* Cart Icon */
        .cart-wrapper {
            position: relative;
            cursor: pointer;
        }

        .cart-icon {
            font-size: 24px;
            color: var(--bup-orange);
            transition: all 0.3s ease;
        }

        .cart-icon:hover {
            transform: scale(1.1);
        }

        .cart-badge {
            position: absolute;
            top: -8px;
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

        /* Profile Dropdown */
        .profile-dropdown {
            position: relative;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 50px;
            transition: all 0.3s ease;
            background: var(--bup-gradient-accent);
            border: 2px solid white;
        }

        .profile-trigger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .profile-trigger-avatar {
            width: 40px;
            height: 40px;
            background: var(--bup-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            color: white;
            border: 2px solid white;
        }

        .profile-trigger-name {
            font-weight: 600;
            color: var(--bup-blue);
            margin-right: 5px;
        }

        .profile-menu {
            position: absolute;
            top: 100%;
            right: 0;
            width: 320px;
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            margin-top: 15px;
            display: none;
            z-index: 1000;
            overflow: hidden;
            border: 1px solid var(--bup-gray-light);
        }

        .profile-menu.show {
            display: block;
        }

        .profile-header {
            background: var(--bup-gradient);
            padding: 25px;
            color: white;
            text-align: center;
        }

        .profile-header-avatar {
            width: 70px;
            height: 70px;
            background: var(--bup-gradient-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 30px;
            font-weight: 700;
            color: var(--bup-blue);
            border: 3px solid white;
        }

        .profile-header-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .profile-header-email {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .profile-header-info {
            display: flex;
            justify-content: center;
            gap: 15px;
            font-size: 12px;
            background: rgba(255,255,255,0.1);
            padding: 8px;
            border-radius: 30px;
        }

        .profile-menu-items {
            padding: 15px;
        }

        .profile-menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            color: var(--text-primary);
            text-decoration: none;
            border-radius: var(--radius-md);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .profile-menu-item:hover {
            background: rgba(255, 145, 77, 0.1);
        }

        .profile-menu-item i {
            font-size: 20px;
            color: var(--bup-orange);
            width: 25px;
        }

        .profile-menu-item.logout {
            color: #dc3545;
        }

        .profile-menu-item.logout i {
            color: #dc3545;
        }

        .divider {
            height: 1px;
            background: var(--bup-gray-light);
            margin: 10px 0;
        }

        .dark-mode-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--bup-gray-light);
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--bup-orange);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            border: 1px solid rgba(225, 233, 240, 0.5);
            display: flex;
            align-items: center;
            justify-content: space-between;
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
            color: var(--text-primary);
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

        /* Books Grid */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .book-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            border: 1px solid rgba(225, 233, 240, 0.5);
            position: relative;
        }

        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--bup-orange);
        }

        .book-image {
            height: 200px;
            background: linear-gradient(145deg, var(--bup-blue), var(--bup-blue-light));
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .book-image i {
            font-size: 60px;
            color: rgba(255,255,255,0.3);
        }

        .book-stock-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--bup-orange);
            color: white;
            padding: 5px 10px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            z-index: 1;
        }

        .book-stock-badge.low-stock {
            background: #dc3545;
        }

        .book-stock-badge.medium-stock {
            background: var(--bup-yellow);
            color: var(--bup-blue);
        }

        .book-stock-badge.high-stock {
            background: #28a745;
        }

        .book-details {
            padding: 20px;
        }

        .book-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .book-category {
            display: inline-block;
            background: rgba(255,145,77,0.1);
            color: var(--bup-orange);
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .book-price {
            font-size: 24px;
            font-weight: 800;
            color: var(--bup-orange);
            margin-bottom: 10px;
        }

        .book-seller {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
            font-size: 13px;
            color: var(--bup-gray);
        }

        .book-seller i {
            color: var(--bup-orange);
        }

        .book-actions {
            display: flex;
            gap: 10px;
        }

        .btn-add-to-cart {
            flex: 1;
            background: var(--bup-gradient-accent);
            color: var(--bup-blue);
            border: none;
            padding: 12px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 0 #C7511E;
        }

        .btn-add-to-cart:hover {
            transform: translateY(2px);
            box-shadow: 0 2px 0 #C7511E;
        }

        .btn-add-to-cart:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: 0 4px 0 #C7511E;
        }

        .btn-view-details {
            width: 50px;
            background: var(--bup-gray-light);
            color: var(--bup-blue);
            border: none;
            border-radius: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-view-details:hover {
            background: var(--bup-orange);
            color: white;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h3 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0;
        }

        .section-header a {
            color: var(--bup-orange);
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .section-header a:hover {
            color: var(--bup-blue);
        }

        /* Become Seller Card */
        .seller-card {
            background: linear-gradient(145deg, #FFF8F0, #FFF3E0);
            border: 2px dashed var(--bup-orange);
            border-radius: var(--radius-lg);
            padding: 30px;
            text-align: center;
            margin-bottom: 40px;
        }

        .seller-icon {
            font-size: 50px;
            color: var(--bup-orange);
            margin-bottom: 15px;
        }

        .seller-card h3 {
            font-size: 22px;
            font-weight: 800;
            color: var(--bup-blue);
            margin-bottom: 10px;
        }

        .btn-seller {
            background: var(--bup-gradient-accent);
            color: var(--bup-blue);
            font-weight: 700;
            padding: 12px 35px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
            box-shadow: 0 5px 0 #C7511E;
            transition: all 0.3s;
        }

        .btn-seller:hover {
            transform: translateY(3px);
            box-shadow: 0 2px 0 #C7511E;
            color: var(--bup-blue);
        }

        /* Success Toast */
        .toast-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 9999;
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
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .search-container {
                width: 200px;
            }

            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
            }

            .search-container {
                width: 100%;
            }

            .profile-trigger-name {
                display: none;
            }

            .books-grid {
                grid-template-columns: 1fr;
            }
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
            <span id="toastMessage">Book added to cart successfully!</span>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <!-- Sidebar Toggle Button -->
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-chevron-left" id="toggleIcon"></i>
        </button>

        <div class="sidebar-logo">
            <div class="logo-wrapper">
                <span class="logo-text">BUP</span>
                <span class="logo-sub">Book Resale</span>
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
                <a href="index.php" class="nav-link active">
                    <i class="bi bi-speedometer2"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="browse-books.php" class="nav-link">
                    <i class="bi bi-book"></i>
                    <span class="nav-text">Browse Books</span>
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
                <a href="wishlist.php" class="nav-link">
                    <i class="bi bi-heart"></i>
                    <span class="nav-text">Wishlist</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Mobile Menu Toggle -->
    <div style="position: fixed; bottom: 30px; right: 30px; z-index: 9999; display: none;" id="mobileMenuBtn">
        <button onclick="toggleSidebar()" style="width: 60px; height: 60px; background: var(--bup-gradient-accent); border: none; border-radius: 50%; box-shadow: var(--shadow-lg); color: var(--bup-blue); font-size: 28px;">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="page-title">
                <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>! 👋</h1>
                <p><i class="bi bi-calendar-check me-1" style="color: var(--bup-orange);"></i> <?php echo date('l, F j, Y'); ?></p>
            </div>
            <div class="header-actions">
                <!-- Search Bar -->
                <div class="search-container">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="search-input" id="searchInput" placeholder="Search books...">
                </div>

                <!-- Simple Notification Icon -->
                <div class="notification-wrapper" onclick="window.location.href='notifications.php'">
                    <i class="bi bi-bell notification-icon"></i>
                    <?php if($stats['notification_count'] > 0): ?>
                    <span class="notification-badge"><?php echo $stats['notification_count']; ?></span>
                    <?php endif; ?>
                </div>

                <!-- Cart Icon -->
                <div class="cart-wrapper" onclick="window.location.href='cart.php'">
                    <i class="bi bi-cart cart-icon"></i>
                    <?php if($stats['cart_count'] > 0): ?>
                    <span class="cart-badge"><?php echo $stats['cart_count']; ?></span>
                    <?php endif; ?>
                </div>

                <!-- Profile Dropdown -->
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="profile-trigger" onclick="toggleProfileMenu()">
                        <div class="profile-trigger-avatar">
                            <?php echo strtoupper(substr($user_name, 0, 2)); ?>
                        </div>
                        <span class="profile-trigger-name"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span>
                        <i class="bi bi-chevron-down" style="color: var(--bup-blue);"></i>
                    </div>
                    
                    <div class="profile-menu" id="profileMenu">
                        <div class="profile-header">
                            <div class="profile-header-avatar">
                                <?php echo strtoupper(substr($user_name, 0, 2)); ?>
                            </div>
                            <div class="profile-header-name"><?php echo htmlspecialchars($user_name); ?></div>
                            <div class="profile-header-email"><?php echo htmlspecialchars($user_email); ?></div>
                            <div class="profile-header-info">
                                <span><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($profile_data['ContactNo'] ?? 'Not set'); ?></span>
                                <span><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars(substr($profile_data['Address'] ?? 'Not set', 0, 20)) . '...'; ?></span>
                            </div>
                        </div>
                        
                        <div class="profile-menu-items">
                            <a href="profile.php" class="profile-menu-item">
                                <i class="bi bi-person"></i>
                                <span>Edit Profile</span>
                            </a>
                            
                            <a href="orders.php" class="profile-menu-item">
                                <i class="bi bi-box"></i>
                                <span>My Orders</span>
                            </a>
                            
                            <a href="settings.php" class="profile-menu-item">
                                <i class="bi bi-gear"></i>
                                <span>Settings</span>
                            </a>
                            
                            <div class="divider"></div>
                            
                            <!-- Dark Mode Toggle -->
                            <div class="profile-menu-item dark-mode-toggle">
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <i class="bi bi-moon-stars"></i>
                                    <span>Dark Mode</span>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="darkModeToggle" <?php echo $dark_mode ? 'checked' : ''; ?> onchange="toggleDarkMode()">
                                    <span class="slider"></span>
                                </label>
                            </div>
                            
                            <div class="divider"></div>
                            
                            <a href="../logout.php" class="profile-menu-item logout">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>My Books</h3>
                    <div class="stat-number"><?php echo $stats['listed_books']; ?></div>
                    <span class="stat-label">Listed for sale</span>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-book"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Active Orders</h3>
                    <div class="stat-number"><?php echo $stats['active_orders']; ?></div>
                    <span class="stat-label">Pending delivery</span>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-truck"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Completed</h3>
                    <div class="stat-number"><?php echo $stats['completed_trades']; ?></div>
                    <span class="stat-label">Successful trades</span>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>In Cart</h3>
                    <div class="stat-number"><?php echo $stats['cart_count']; ?></div>
                    <span class="stat-label">Items in cart</span>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-cart"></i>
                </div>
            </div>
        </div>

        <!-- Available Books Section -->
        <div class="section-header">
            <h3><i class="bi bi-book me-2" style="color: var(--bup-orange);"></i>Available Books</h3>
            <a href="browse-books.php">View All <i class="bi bi-arrow-right"></i></a>
        </div>

        <div class="books-grid">
            <?php foreach ($available_books as $book): 
                $stock_class = 'high-stock';
                if ($book['StockQuantity'] <= 2) {
                    $stock_class = 'low-stock';
                } elseif ($book['StockQuantity'] <= 5) {
                    $stock_class = 'medium-stock';
                }
            ?>
            <div class="book-card">
                <div class="book-image">
                    <i class="bi bi-journal-bookmark-fill"></i>
                    <span class="book-stock-badge <?php echo $stock_class; ?>">
                        <i class="bi bi-box-seam me-1"></i><?php echo $book['StockQuantity']; ?> left
                    </span>
                </div>
                <div class="book-details">
                    <h3 class="book-title"><?php echo htmlspecialchars(substr($book['ProductName'], 0, 30)) . (strlen($book['ProductName']) > 30 ? '...' : ''); ?></h3>
                    <span class="book-category"><?php echo htmlspecialchars($book['Category']); ?></span>
                    <div class="book-price">$<?php echo number_format($book['Price'], 2); ?></div>
                    <div class="book-seller">
                        <i class="bi bi-person-circle"></i>
                        <span><?php echo htmlspecialchars($book['SellerName'] ?? 'Unknown Seller'); ?></span>
                        <i class="bi bi-telephone ms-2"></i>
                        <span><?php echo htmlspecialchars($book['SellerContact'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="book-actions">
                        <button class="btn-add-to-cart" onclick="addToCart(<?php echo $book['ProductID']; ?>, '<?php echo htmlspecialchars($book['ProductName']); ?>')" <?php echo $book['StockQuantity'] == 0 ? 'disabled' : ''; ?>>
                            <i class="bi bi-cart-plus"></i>
                            Add to Cart
                        </button>
                        <button class="btn-view-details" onclick="window.location.href='book-details.php?id=<?php echo $book['ProductID']; ?>'">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Become Seller Card (if not a seller) -->
        <?php if (!$is_seller): ?>
        <div class="seller-card">
            <div class="seller-icon">
                <i class="bi bi-shop"></i>
            </div>
            <h3>Want to sell your books?</h3>
            <p style="color: var(--bup-gray); max-width: 500px; margin: 0 auto;">
                Become a seller and start earning money from your used textbooks. It's free to join!
            </p>
            <a href="become-seller.php" class="btn-seller">
                <i class="bi bi-person-plus me-2"></i>Become a Seller
            </a>
        </div>
        <?php endif; ?>

        <!-- Tips for Success -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="profile-card">
                    <div class="section-header">
                        <h3><i class="bi bi-lightbulb me-2" style="color: var(--bup-yellow);"></i>Tips for Success</h3>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-3">
                            <div class="d-flex gap-3 align-items-start">
                                <div style="width: 40px; height: 40px; background: rgba(255,145,77,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--bup-orange); font-size: 20px;">
                                    <i class="bi bi-camera"></i>
                                </div>
                                <div>
                                    <h6 style="font-weight: 700;">Clear Photos</h6>
                                    <p style="color: var(--bup-gray); font-size: 14px;">Books with clear cover photos sell 3x faster.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex gap-3 align-items-start">
                                <div style="width: 40px; height: 40px; background: rgba(255,193,7,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--bup-yellow); font-size: 20px;">
                                    <i class="bi bi-tag"></i>
                                </div>
                                <div>
                                    <h6 style="font-weight: 700;">Competitive Pricing</h6>
                                    <p style="color: var(--bup-gray); font-size: 14px;">Check similar listings to price your books right.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex gap-3 align-items-start">
                                <div style="width: 40px; height: 40px; background: rgba(40,167,69,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #28a745; font-size: 20px;">
                                    <i class="bi bi-chat"></i>
                                </div>
                                <div>
                                    <h6 style="font-weight: 700;">Quick Response</h6>
                                    <p style="color: var(--bup-gray); font-size: 14px;">Respond to messages promptly to close deals.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex gap-3 align-items-start">
                                <div style="width: 40px; height: 40px; background: rgba(10,49,67,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--bup-blue); font-size: 20px;">
                                    <i class="bi bi-star"></i>
                                </div>
                                <div>
                                    <h6 style="font-weight: 700;">Accurate Condition</h6>
                                    <p style="color: var(--bup-gray); font-size: 14px;">Honest descriptions build trust and repeat buyers.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
                    
                    // Update toggle icon
                    if (sidebar.classList.contains('collapsed')) {
                        toggleIcon.classList.remove('bi-chevron-left');
                        toggleIcon.classList.add('bi-chevron-right');
                        
                        // Save state to localStorage
                        localStorage.setItem('sidebarCollapsed', 'true');
                    } else {
                        toggleIcon.classList.remove('bi-chevron-right');
                        toggleIcon.classList.add('bi-chevron-left');
                        
                        // Save state to localStorage
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
            
            // Close profile menu when clicking outside
            document.addEventListener('click', function(event) {
                const profileDropdown = document.getElementById('profileDropdown');
                const profileMenu = document.getElementById('profileMenu');
                
                if (!profileDropdown.contains(event.target) && profileMenu.classList.contains('show')) {
                    profileMenu.classList.remove('show');
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
                mobileBtn.style.display = 'block';
                sidebar.classList.remove('active');
            } else {
                mobileBtn.style.display = 'none';
                sidebar.classList.add('active');
            }
        }

        window.addEventListener('resize', checkMobileView);
        window.addEventListener('load', checkMobileView);

        // Profile menu toggle
        function toggleProfileMenu() {
            const profileMenu = document.getElementById('profileMenu');
            profileMenu.classList.toggle('show');
        }

        // Dark mode toggle
        function toggleDarkMode() {
            const isChecked = document.getElementById('darkModeToggle').checked;
            const theme = isChecked ? 'dark' : 'light';
            
            document.body.setAttribute('data-theme', theme);
            
            // Save preference in cookie
            document.cookie = "dark_mode=" + (isChecked ? 'enabled' : 'disabled') + "; path=/; max-age=31536000";
            
            showToast('Dark mode ' + (isChecked ? 'enabled' : 'disabled'));
        }

        // Show toast message
        function showToast(message) {
            const toast = document.getElementById('toastContainer');
            document.getElementById('toastMessage').textContent = message;
            toast.style.display = 'block';
            
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }

        // Add to cart function
        function addToCart(productId, productName) {
            // Show loading spinner
            document.getElementById('loadingSpinner').style.display = 'flex';
            
            // Simulate AJAX request to add to cart
            setTimeout(() => {
                // Hide loading spinner
                document.getElementById('loadingSpinner').style.display = 'none';
                
                // Show success message
                showToast(`"${productName}" added to cart!`);
                
                // Update cart badge (increment)
                const cartBadge = document.querySelector('.cart-badge');
                let currentCount = parseInt(cartBadge.textContent);
                cartBadge.textContent = currentCount + 1;
            }, 1000);
        }

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query.length > 0) {
                    window.location.href = 'browse-books.php?search=' + encodeURIComponent(query);
                }
            }
        });

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
        });
    </script>

    <?php if (isset($db_error)): ?>
    <script>
        alert("<?php echo addslashes($db_error); ?>");
    </script>
    <?php endif; ?>
</body>
</html>