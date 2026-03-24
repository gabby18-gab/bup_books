<?php
// admin/products.php
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

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];
$admin_role = $_SESSION['admin_role'];

$message = '';
$error = '';

// Handle Add Stock
if (isset($_POST['add_stock'])) {
    $product_id = $_POST['product_id'];
    $additional_stock = (int)$_POST['additional_stock'];
    
    if ($additional_stock > 0) {
        $stmt = $pdo->prepare("UPDATE product SET StockQuantity = StockQuantity + ? WHERE ProductID = ?");
        if ($stmt->execute([$additional_stock, $product_id])) {
            $message = "✅ Stock added successfully!";
            
            // Log the action
            $log = $pdo->prepare("INSERT INTO admin_logs (AdminID, Action, Details, IPAddress) VALUES (?, 'update', ?, ?)");
            $log->execute([$admin_id, "Added $additional_stock stock to product ID: $product_id", $_SERVER['REMOTE_ADDR']]);
        } else {
            $error = "❌ Failed to add stock.";
        }
    }
}

// Handle Update Product
if (isset($_POST['update_product'])) {
    $product_id = $_POST['product_id'];
    $product_name = $_POST['product_name'];
    $category = $_POST['category'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE product SET ProductName = ?, Category = ?, Price = ?, StockQuantity = ?, Status = ? WHERE ProductID = ?");
    if ($stmt->execute([$product_name, $category, $price, $stock, $status, $product_id])) {
        $message = "✅ Product updated successfully!";
        
        $log = $pdo->prepare("INSERT INTO admin_logs (AdminID, Action, Details, IPAddress) VALUES (?, 'update', ?, ?)");
        $log->execute([$admin_id, "Updated product ID: $product_id", $_SERVER['REMOTE_ADDR']]);
    } else {
        $error = "❌ Failed to update product.";
    }
}

// Handle Add New Product
if (isset($_POST['add_product'])) {
    $product_name = $_POST['product_name'];
    $category = $_POST['category'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $seller_id = $_POST['seller_id'];
    $status = $_POST['status'];
    $image = $_POST['image'] ?? 'default-book.jpg';
    
    $stmt = $pdo->prepare("INSERT INTO product (ProductName, Category, Price, StockQuantity, Status, SellerID, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$product_name, $category, $price, $stock, $status, $seller_id, $image])) {
        $message = "✅ Product added successfully!";
        
        $log = $pdo->prepare("INSERT INTO admin_logs (AdminID, Action, Details, IPAddress) VALUES (?, 'create', ?, ?)");
        $log->execute([$admin_id, "Added new product: $product_name", $_SERVER['REMOTE_ADDR']]);
    } else {
        $error = "❌ Failed to add product.";
    }
}

// Handle Delete Product (Soft delete)
if (isset($_GET['delete']) && $admin_role == 'super_admin') {
    $product_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("UPDATE product SET Status = 'I' WHERE ProductID = ?");
        $stmt->execute([$product_id]);
        $message = "✅ Product deactivated successfully!";
        
        $log = $pdo->prepare("INSERT INTO admin_logs (AdminID, Action, Details, IPAddress) VALUES (?, 'delete', ?, ?)");
        $log->execute([$admin_id, "Deactivated product ID: $product_id", $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        $error = "❌ Cannot delete product: " . $e->getMessage();
    }
}

// Handle Restore Product
if (isset($_GET['restore']) && $admin_role == 'super_admin') {
    $product_id = $_GET['restore'];
    try {
        $stmt = $pdo->prepare("UPDATE product SET Status = 'A' WHERE ProductID = ?");
        $stmt->execute([$product_id]);
        $message = "✅ Product restored successfully!";
        
        $log = $pdo->prepare("INSERT INTO admin_logs (AdminID, Action, Details, IPAddress) VALUES (?, 'update', ?, ?)");
        $log->execute([$admin_id, "Restored product ID: $product_id", $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        $error = "❌ Cannot restore product: " . $e->getMessage();
    }
}

// Handle Toggle Status
if (isset($_GET['toggle'])) {
    $product_id = $_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE product SET Status = IF(Status = 'A', 'I', 'A') WHERE ProductID = ?");
    $stmt->execute([$product_id]);
    $message = "✅ Product status updated!";
}

// Search and filter
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$seller_filter = $_GET['seller'] ?? '';

$query = "SELECT p.*, s.Name as SellerName, s.SellerID 
          FROM product p 
          JOIN seller s ON p.SellerID = s.SellerID 
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (p.ProductName LIKE ? OR p.Category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $query .= " AND p.Category = ?";
    $params[] = $category_filter;
}

if ($status_filter && in_array($status_filter, ['A', 'I'])) {
    $query .= " AND p.Status = ?";
    $params[] = $status_filter;
}

if ($seller_filter) {
    $query .= " AND p.SellerID = ?";
    $params[] = $seller_filter;
}

$query .= " ORDER BY p.ProductID DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get unique categories
$categories = $pdo->query("SELECT DISTINCT Category FROM product ORDER BY Category")->fetchAll(PDO::FETCH_COLUMN);

// Get all sellers
$sellers = $pdo->query("SELECT SellerID, Name FROM seller WHERE Status = 'A' ORDER BY Name")->fetchAll();

// Get stats
$total_products = $pdo->query("SELECT COUNT(*) FROM product")->fetchColumn();
$active_products = $pdo->query("SELECT COUNT(*) FROM product WHERE Status = 'A'")->fetchColumn();
$inactive_products = $pdo->query("SELECT COUNT(*) FROM product WHERE Status = 'I'")->fetchColumn();
$total_value = $pdo->query("SELECT SUM(Price * StockQuantity) FROM product WHERE Status = 'A'")->fetchColumn();
$low_stock = $pdo->query("SELECT COUNT(*) FROM product WHERE StockQuantity < 3 AND Status = 'A'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - BUP BOOKS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s ease;
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
            }
            .mobile-menu-btn {
                display: flex;
            }
        }

        /* Stats Cards */
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-mini-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--shadow-sm);
            border-left: 5px solid var(--bup-orange);
        }

        /* Content Card */
        .content-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
            border: 1px solid var(--bup-gray-light);
        }

        /* Buttons */
        .btn-add {
            background: var(--bup-orange);
            color: var(--bup-blue);
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            margin: 0 3px;
            border: none;
            cursor: pointer;
        }

        .btn-view {
            background: rgba(10,49,67,0.1);
            color: var(--bup-blue);
        }

        .btn-edit {
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange-dark);
        }

        .btn-stock {
            background: rgba(40,167,69,0.15);
            color: #28a745;
        }

        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
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
            color: #999;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e1e1e1;
            border-radius: 30px;
        }

        .filter-select {
            padding: 12px 20px;
            border: 2px solid #e1e1e1;
            border-radius: 30px;
            min-width: 150px;
        }

        /* Product Image */
        .product-image-sm {
            width: 50px;
            height: 50px;
            background: #f0f0f0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--bup-blue);
        }

        /* Stock Badges */
        .stock-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
        }

        .stock-high {
            background: rgba(40,167,69,0.15);
            color: #28a745;
        }

        .stock-medium {
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange);
        }

        .stock-low {
            background: rgba(220,53,69,0.15);
            color: #dc3545;
        }

        /* Status Badges */
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

        .status-inactive {
            background: rgba(108,117,125,0.15);
            color: #6c757d;
        }

        /* Table */
        .table {
            margin-bottom: 0;
        }

        .table thead th {
            border-bottom: 2px solid #e1e1e1;
            color: #666;
            font-weight: 700;
            font-size: 13px;
        }

        .table tbody td {
            padding: 15px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #e1e1e1;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        /* Modal */
        .modal-header {
            background: var(--bup-blue);
            color: white;
            border-radius: 20px 20px 0 0;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .btn-save {
            background: var(--bup-orange);
            color: var(--bup-blue);
            border: none;
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 700;
        }

        .btn-cancel {
            background: #e1e1e1;
            color: #333;
            border: none;
            padding: 12px 30px;
            border-radius: 30px;
        }

        .alert {
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
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
                <a href="products.php" class="nav-link active">
                    <i class="bi bi-book"></i>
                    <span>Products</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="pending_products.php" class="nav-link">
                    <i class="bi bi-clock-history"></i>
                    <span>Pending Approvals</span>
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

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 style="font-size: 28px; font-weight: 800; color: var(--bup-blue);">
                <i class="bi bi-book me-3" style="color: var(--bup-orange);"></i>Products Management
            </h1>
            <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="bi bi-plus-circle"></i> Add New Product
            </button>
        </div>

        <!-- Mini Stats -->
        <div class="stats-mini">
            <div class="stat-mini-card">
                <h6 style="color: #666;">Total Products</h6>
                <h2 style="font-size: 32px; font-weight: 800;"><?php echo $total_products; ?></h2>
            </div>
            <div class="stat-mini-card">
                <h6 style="color: #666;">Active Products</h6>
                <h2 style="font-size: 32px; font-weight: 800; color: #28a745;"><?php echo $active_products; ?></h2>
            </div>
            <div class="stat-mini-card">
                <h6 style="color: #666;">Low Stock</h6>
                <h2 style="font-size: 32px; font-weight: 800; color: #dc3545;"><?php echo $low_stock; ?></h2>
            </div>
            <div class="stat-mini-card">
                <h6 style="color: #666;">Inventory Value</h6>
                <h2 style="font-size: 32px; font-weight: 800; color: var(--bup-orange);">₱<?php echo number_format($total_value, 2); ?></h2>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="content-card">
            <form method="GET" class="filter-bar">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <select name="category" class="filter-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat; ?>" <?php echo $category_filter == $cat ? 'selected' : ''; ?>>
                            <?php echo $cat; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="A" <?php echo $status_filter == 'A' ? 'selected' : ''; ?>>Active</option>
                    <option value="I" <?php echo $status_filter == 'I' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                
                <button type="submit" class="btn" style="background: var(--bup-blue); color: white; border-radius: 30px; padding: 12px 30px;">
                    Apply Filter
                </button>
                <a href="products.php" class="btn btn-secondary">Reset</a>
            </form>
        </div>

        <!-- Products Table -->
        <div class="content-card">
            <table class="table">
                <thead>
                    32<th>Product</th>
                        <th>Category</th>
                        <th>Seller</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="product-image-sm me-3">
                                        <i class="bi bi-journal-bookmark-fill"></i>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($product['ProductName']); ?></strong>
                                        <br>
                                        <small class="text-muted">ID: #<?php echo $product['ProductID']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($product['Category']); ?></td>
                            <td><?php echo htmlspecialchars($product['SellerName']); ?></td>
                            <td style="font-weight: 700; color: var(--bup-orange);">₱<?php echo number_format($product['Price'], 2); ?></td>
                            <td>
                                <?php
                                $stock = $product['StockQuantity'];
                                $stockClass = 'stock-high';
                                if ($stock <= 2) $stockClass = 'stock-low';
                                elseif ($stock <= 5) $stockClass = 'stock-medium';
                                ?>
                                <span class="stock-badge <?php echo $stockClass; ?>">
                                    <?php echo $stock; ?> units
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $product['Status'] == 'A' ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $product['Status'] == 'A' ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="product-details.php?id=<?php echo $product['ProductID']; ?>" class="btn-icon btn-view" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <button class="btn-icon btn-edit" onclick='editProduct(<?php echo json_encode($product); ?>)' title="Edit" data-bs-toggle="modal" data-bs-target="#editProductModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn-icon btn-stock" onclick="addStock(<?php echo $product['ProductID']; ?>, '<?php echo htmlspecialchars($product['ProductName']); ?>')" title="Add Stock" data-bs-toggle="modal" data-bs-target="#addStockModal">
                                        <i class="bi bi-plus-circle"></i>
                                    </button>
                                    <?php if ($admin_role == 'super_admin'): ?>
                                        <?php if ($product['Status'] == 'A'): ?>
                                            <a href="products.php?delete=<?php echo $product['ProductID']; ?>" class="btn-icon" style="background: rgba(220,53,69,0.15); color: #dc3545;" title="Deactivate" onclick="return confirm('Deactivate this product?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="products.php?restore=<?php echo $product['ProductID']; ?>" class="btn-icon" style="background: rgba(40,167,69,0.15); color: #28a745;" title="Restore">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="bi bi-book" style="font-size: 48px; color: #ccc;"></i>
                                <h5 class="mt-3 text-muted">No products found</h5>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Stock Modal -->
    <div class="modal fade" id="addStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="product_id" id="stock_product_id">
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <input type="text" class="form-control" id="stock_product_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity to Add</label>
                            <input type="number" name="additional_stock" class="form-control" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_stock" class="btn-save">Add Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="product_id" id="edit_product_id">
                        <div class="mb-3">
                            <label class="form-label">Product Name</label>
                            <input type="text" name="product_name" id="edit_product_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" id="edit_category" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price (₱)</label>
                            <input type="number" name="price" id="edit_price" class="form-control" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" name="stock" id="edit_stock" class="form-control" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="A">Active</option>
                                <option value="I">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_product" class="btn-save">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product Name</label>
                            <input type="text" name="product_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price (₱)</label>
                            <input type="number" name="price" class="form-control" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Initial Stock</label>
                            <input type="number" name="stock" class="form-control" min="0" value="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Seller</label>
                            <select name="seller_id" class="form-select" required>
                                <option value="">Select Seller</option>
                                <?php foreach ($sellers as $seller): ?>
                                    <option value="<?php echo $seller['SellerID']; ?>"><?php echo htmlspecialchars($seller['Name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="A">Active</option>
                                <option value="I">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_product" class="btn-save">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function addStock(productId, productName) {
            document.getElementById('stock_product_id').value = productId;
            document.getElementById('stock_product_name').value = productName;
        }

        function editProduct(product) {
            document.getElementById('edit_product_id').value = product.ProductID;
            document.getElementById('edit_product_name').value = product.ProductName;
            document.getElementById('edit_category').value = product.Category;
            document.getElementById('edit_price').value = product.Price;
            document.getElementById('edit_stock').value = product.StockQuantity;
            document.getElementById('edit_status').value = product.Status;
        }

        // Auto-hide alerts
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);

        // Toggle sidebar on mobile
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

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