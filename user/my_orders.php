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

$message = '';
$error = '';

// Handle order cancellation
if (isset($_GET['cancel_order'])) {
    $order_id = $_GET['cancel_order'];
    $reason = isset($_POST['cancel_reason']) ? $_POST['cancel_reason'] : 'Cancelled by user';
    
    try {
        $pdo->beginTransaction();
        
        // Check if order belongs to user and is pending
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE OrderID = ? AND UserID = ? AND Status = 'pending'");
        $stmt->execute([$order_id, $user_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // Update order status
            $updateStmt = $pdo->prepare("
                UPDATE orders SET 
                Status = 'cancelled', 
                PaymentStatus = 'failed',
                CancelledAt = NOW(),
                CancelledBy = 'user',
                CancellationReason = ?
                WHERE OrderID = ?
            ");
            $updateStmt->execute([$reason, $order_id]);
            
            // Restore stock
            $detailsStmt = $pdo->prepare("SELECT ProductID, Quantity FROM orderdetails WHERE OrderID = ?");
            $detailsStmt->execute([$order_id]);
            $items = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as $item) {
                $restoreStmt = $pdo->prepare("UPDATE product SET StockQuantity = StockQuantity + ? WHERE ProductID = ?");
                $restoreStmt->execute([$item['Quantity'], $item['ProductID']]);
            }
            
            // Log the action
            $logStmt = $pdo->prepare("
                INSERT INTO user_logs (UserID, Action, Details, IPAddress) 
                VALUES (?, 'order_cancelled', ?, ?)
            ");
            $logStmt->execute([$user_id, "Cancelled order #$order_id - Reason: $reason", $_SERVER['REMOTE_ADDR']]);
            
            // Create notification
            $notifyStmt = $pdo->prepare("
                INSERT INTO notifications (UserID, Type, Title, Message) 
                VALUES (?, 'order_cancelled', 'Order Cancelled', ?)
            ");
            $notifyStmt->execute([$user_id, "Your order #$order_id has been cancelled successfully."]);
            
            $pdo->commit();
            $message = "? Order #$order_id has been cancelled successfully!";
            
            // Redirect to remove the cancel parameter
            header('Location: my_orders.php?status=cancelled&message=' . urlencode($message));
            exit();
        } else {
            $pdo->rollBack();
            $error = "? You cannot cancel this order. Only pending orders can be cancelled.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "? Failed to cancel order: " . $e->getMessage();
    }
}

// Handle order confirmation (mark as received)
if (isset($_GET['confirm_received'])) {
    $order_id = $_GET['confirm_received'];
    
    try {
        $pdo->beginTransaction();
        
        // Check if order belongs to user and is shipped
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE OrderID = ? AND UserID = ? AND Status = 'shipped'");
        $stmt->execute([$order_id, $user_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // Update order status
            $updateStmt = $pdo->prepare("
                UPDATE orders SET 
                Status = 'completed', 
                DeliveredAt = NOW()
                WHERE OrderID = ?
            ");
            $updateStmt->execute([$order_id]);
            
            // Log the action
            $logStmt = $pdo->prepare("
                INSERT INTO user_logs (UserID, Action, Details, IPAddress) 
                VALUES (?, 'order_received', ?, ?)
            ");
            $logStmt->execute([$user_id, "Confirmed receipt of order #$order_id", $_SERVER['REMOTE_ADDR']]);
            
            // Create notification
            $notifyStmt = $pdo->prepare("
                INSERT INTO notifications (UserID, Type, Title, Message) 
                VALUES (?, 'order_completed', 'Order Completed', ?)
            ");
            $notifyStmt->execute([$user_id, "Thank you! Order #$order_id has been marked as received."]);
            
            $pdo->commit();
            $message = "? Order #$order_id has been marked as received! Thank you for shopping with us.";
            
            header('Location: my_orders.php?status=completed&message=' . urlencode($message));
            exit();
        } else {
            $pdo->rollBack();
            $error = "? Invalid order or order cannot be confirmed as received.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "? Failed to confirm order: " . $e->getMessage();
    }
}

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

// Get user info for profile
$userStmt = $pdo->prepare("SELECT * FROM users WHERE UserID = ?");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
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
        :root {
            --bup-blue: #0A3143;
            --bup-orange: #FF914D;
            --bup-yellow: #FFC107;
            --bup-green: #28a745;
            --bup-red: #dc3545;
            --bup-gray: #6c757d;
            --bup-light-gray: #e9ecef;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            color: var(--bup-blue);
        }

        /* Navbar */
        .navbar {
            background: var(--bup-blue);
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-size: 24px;
            font-weight: 800;
            color: var(--bup-orange) !important;
        }

        .nav-link {
            color: white !important;
            font-weight: 500;
            padding: 8px 16px !important;
        }

        .nav-link:hover {
            color: var(--bup-orange) !important;
        }

        .nav-link.active {
            background: rgba(255,145,77,0.2);
            border-radius: 30px;
        }

        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 25px 30px;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
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

        /* Stats Cards */
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

        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
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

        /* Order Cards */
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

        .order-items {
            margin-bottom: 15px;
        }

        .order-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dashed #e1e1e1;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-name {
            flex: 1;
            font-weight: 500;
        }

        .item-quantity {
            background: var(--bup-orange);
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-left: 10px;
        }

        .item-price {
            font-weight: 700;
            color: var(--bup-orange);
            min-width: 100px;
            text-align: right;
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

        /* Tracking Info */
        .tracking-info {
            background: #e8f4fd;
            padding: 10px 15px;
            border-radius: 12px;
            margin-top: 10px;
            font-size: 14px;
        }

        .tracking-info i {
            color: #007bff;
            margin-right: 8px;
        }

        /* Cancellation Reason */
        .cancellation-reason {
            background: #f8d7da;
            padding: 10px 15px;
            border-radius: 12px;
            margin-top: 10px;
            font-size: 14px;
            color: #721c24;
        }

        .cancellation-reason i {
            margin-right: 8px;
        }

        /* Empty State */
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

        /* Alert */
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
            border: 5px solid #f3f3f3;
            border-top-color: var(--bup-orange);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .order-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .order-footer {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .order-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>

    <!-- Loading Spinner -->
    <div class="spinner-overlay" id="loadingSpinner">
        <div class="spinner"></div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">BUP BOOKS</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="browse-books.php">Books</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="bi bi-cart"></i> Cart
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="my_orders.php">
                            <i class="bi bi-box"></i> My Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-person"></i> <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">
                            <i class="bi bi-box-arrow-right"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Page Header -->
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

        <!-- Messages -->
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

        <!-- Status Stats Cards -->
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

        <!-- Filter Bar -->
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

        <!-- Orders List -->
        <div class="orders-container">
            <?php if (count($orders) > 0): ?>
                <?php foreach ($orders as $order): 
                    $status_class = '';
                    $status_text = '';
                    
                    switch($order['Status']) {
                        case 'pending':
                            $status_class = 'status-pending';
                            $status_text = '? To Pay';
                            break;
                        case 'processing':
                            $status_class = 'status-processing';
                            $status_text = '?? Processing';
                            break;
                        case 'shipped':
                            $status_class = 'status-shipped';
                            $status_text = '?? To Receive';
                            break;
                        case 'completed':
                            $status_class = 'status-completed';
                            $status_text = '? Completed';
                            break;
                        case 'cancelled':
                            $status_class = 'status-cancelled';
                            $status_text = '? Cancelled';
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
                                <span class="ms-3">?? Estimated: <?php echo date('M d, Y', strtotime($order['EstimatedDelivery'])); ?></span>
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
                            Total: <span class="total-amount">?<?php echo number_format($order['TotalAmount'], 2); ?></span>
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

    <!-- Cancel Order Modal -->
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

        // Show loading spinner on link clicks
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