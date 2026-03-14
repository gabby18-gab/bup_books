<?php
// admin/orders.php
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
$admin_role = $_SESSION['admin_role'];
$admin_name = $_SESSION['admin_name'];

$message = '';
$error = '';

// Confirm Order (Mark as Processing)
if (isset($_GET['confirm'])) {
    $order_id = $_GET['confirm'];
    try {
        $stmt = $pdo->prepare("UPDATE orders SET Status = 'processing', PaymentStatus = 'paid' WHERE OrderID = ?");
        $stmt->execute([$order_id]);
        $message = "✅ Order #$order_id confirmed and payment marked as paid!";
        
        // Log the action
        $log = $pdo->prepare("INSERT INTO admin_logs (AdminID, Action, Details, IPAddress) VALUES (?, 'update', ?, ?)");
        $log->execute([$admin_id, "Confirmed order ID: $order_id", $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        $error = "❌ Failed to confirm order: " . $e->getMessage();
    }
}

// Mark as Completed
if (isset($_GET['complete'])) {
    $order_id = $_GET['complete'];
    try {
        $stmt = $pdo->prepare("UPDATE orders SET Status = 'completed' WHERE OrderID = ?");
        $stmt->execute([$order_id]);
        $message = "✅ Order #$order_id marked as completed!";
        
        $log = $pdo->prepare("INSERT INTO admin_logs (AdminID, Action, Details, IPAddress) VALUES (?, 'update', ?, ?)");
        $log->execute([$admin_id, "Completed order ID: $order_id", $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        $error = "❌ Failed to complete order: " . $e->getMessage();
    }
}

// Cancel Order (Admin)
if (isset($_GET['cancel'])) {
    $order_id = $_GET['cancel'];
    try {
        // Check if order is pending or processing
        $checkStmt = $pdo->prepare("SELECT Status FROM orders WHERE OrderID = ?");
        $checkStmt->execute([$order_id]);
        $order = $checkStmt->fetch();
        
        if ($order && ($order['Status'] == 'pending' || $order['Status'] == 'processing')) {
            $stmt = $pdo->prepare("UPDATE orders SET Status = 'cancelled', PaymentStatus = 'failed' WHERE OrderID = ?");
            $stmt->execute([$order_id]);
            $message = "✅ Order #$order_id cancelled!";
            
            // Restore stock
            $restoreStmt = $pdo->prepare("
                UPDATE product p 
                JOIN orderdetails od ON p.ProductID = od.ProductID 
                SET p.StockQuantity = p.StockQuantity + od.Quantity 
                WHERE od.OrderID = ?
            ");
            $restoreStmt->execute([$order_id]);
            
            $log = $pdo->prepare("INSERT INTO admin_logs (AdminID, Action, Details, IPAddress) VALUES (?, 'update', ?, ?)");
            $log->execute([$admin_id, "Cancelled order ID: $order_id", $_SERVER['REMOTE_ADDR']]);
        } else {
            $error = "❌ Only pending or processing orders can be cancelled.";
        }
    } catch (PDOException $e) {
        $error = "❌ Failed to cancel order: " . $e->getMessage();
    }
}

// Search and filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT o.*, u.Name as UserName, u.Email, u.Address, u.ContactNo,
          s.Name as SellerName,
          (SELECT SUM(od.Quantity) FROM orderdetails od WHERE od.OrderID = o.OrderID) as total_items
          FROM orders o 
          JOIN users u ON o.UserID = u.UserID 
          JOIN seller s ON o.SellerID = s.SellerID 
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (o.OrderID LIKE ? OR u.Name LIKE ? OR u.Email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $query .= " AND o.Status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY o.OrderDate DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get stats
$total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE Status = 'pending'")->fetchColumn();
$processing_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE Status = 'processing'")->fetchColumn();
$completed_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE Status = 'completed'")->fetchColumn();
$cancelled_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE Status = 'cancelled'")->fetchColumn();
$total_revenue = $pdo->query("SELECT SUM(TotalAmount) FROM orders WHERE PaymentStatus = 'paid'")->fetchColumn() ?: 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - BUP BOOKS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bup-blue: #0A3143;
            --bup-orange: #FF914D;
            --bup-yellow: #FFC107;
            --bup-green: #28a745;
            --bup-red: #dc3545;
            --bup-gray: #6c757d;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #F8FAFC;
            margin: 0;
            padding: 0;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(145deg, #0A3143, #1C4E6C);
            color: white;
            padding: 30px 20px;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-left: 5px solid var(--bup-orange);
        }

        .stat-card h6 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .stat-card h2 {
            font-size: 32px;
            font-weight: 800;
            margin: 0;
        }

        .content-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

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

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            border-bottom: 2px solid #e1e1e1;
            color: #666;
            font-weight: 700;
            font-size: 13px;
            padding: 15px 12px;
        }

        .table tbody td {
            padding: 15px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #e1e1e1;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-pending {
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange);
        }

        .status-processing {
            background: rgba(0,123,255,0.15);
            color: #007bff;
        }

        .status-completed {
            background: rgba(40,167,69,0.15);
            color: var(--bup-green);
        }

        .status-cancelled {
            background: rgba(220,53,69,0.15);
            color: var(--bup-red);
        }

        .payment-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
        }

        .payment-paid {
            background: rgba(40,167,69,0.15);
            color: var(--bup-green);
        }

        .payment-pending {
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange);
        }

        .payment-failed {
            background: rgba(220,53,69,0.15);
            color: var(--bup-red);
        }

        .btn-action {
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
            border: none;
            cursor: pointer;
        }

        .btn-confirm {
            background: var(--bup-orange);
            color: white;
        }

        .btn-complete {
            background: var(--bup-green);
            color: white;
        }

        .btn-cancel {
            background: var(--bup-red);
            color: white;
        }

        .btn-view {
            background: var(--bup-blue);
            color: white;
        }

        .alert {
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
        }

        .order-amount {
            font-weight: 700;
            color: var(--bup-orange);
        }

        .nav-tabs {
            border-bottom: 2px solid #e1e9f0;
            margin-bottom: 20px;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 600;
            padding: 12px 20px;
        }

        .nav-tabs .nav-link.active {
            color: var(--bup-orange);
            border-bottom: 3px solid var(--bup-orange);
            background: transparent;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-logo text-center mb-4">
            <div style="font-size: 32px; font-weight: 900; color: var(--bup-orange);">BUP</div>
            <div style="font-size: 12px; color: var(--bup-yellow);">ADMIN PANEL</div>
        </div>
        
        <div class="admin-info text-center mb-4">
            <div style="width: 70px; height: 70px; background: var(--bup-orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 28px; color: var(--bup-blue);">
                <?php echo strtoupper(substr($admin_name, 0, 2)); ?>
            </div>
            <div style="font-weight: 700;"><?php echo htmlspecialchars($admin_name); ?></div>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item mb-2">
                <a href="dashboard.php" class="nav-link text-white">
                    <i class="bi bi-speedometer2 me-3"></i> Dashboard
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="users.php" class="nav-link text-white">
                    <i class="bi bi-people me-3"></i> Users
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="products.php" class="nav-link text-white">
                    <i class="bi bi-book me-3"></i> Products
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="orders.php" class="nav-link text-white active" style="background: var(--bup-orange); color: var(--bup-blue) !important; border-radius: 10px;">
                    <i class="bi bi-cart me-3"></i> Orders
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="sellers.php" class="nav-link text-white">
                    <i class="bi bi-shop me-3"></i> Sellers
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="payments.php" class="nav-link text-white">
                    <i class="bi bi-credit-card me-3"></i> Payments
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="logs.php" class="nav-link text-white">
                    <i class="bi bi-journal-text me-3"></i> Activity Logs
                </a>
            </li>
            <li class="nav-item mt-4">
                <a href="logout.php" class="nav-link text-white" style="background: rgba(255,69,58,0.2); border-radius: 10px;">
                    <i class="bi bi-box-arrow-right me-3"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 style="font-size: 28px; font-weight: 800; color: var(--bup-blue);">
                <i class="bi bi-cart me-3" style="color: var(--bup-orange);"></i>Orders Management
            </h1>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h6>Total Orders</h6>
                <h2 style="color: var(--bup-blue);"><?php echo $total_orders; ?></h2>
            </div>
            <div class="stat-card">
                <h6>Pending Orders</h6>
                <h2 style="color: var(--bup-orange);"><?php echo $pending_orders; ?></h2>
            </div>
            <div class="stat-card">
                <h6>Processing</h6>
                <h2 style="color: #007bff;"><?php echo $processing_orders; ?></h2>
            </div>
            <div class="stat-card">
                <h6>Completed</h6>
                <h2 style="color: var(--bup-green);"><?php echo $completed_orders; ?></h2>
            </div>
            <div class="stat-card">
                <h6>Cancelled</h6>
                <h2 style="color: var(--bup-red);"><?php echo $cancelled_orders; ?></h2>
            </div>
            <div class="stat-card">
                <h6>Total Revenue</h6>
                <h2 style="color: var(--bup-orange);">৳<?php echo number_format($total_revenue, 2); ?></h2>
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
                    <input type="text" name="search" placeholder="Search by Order ID, Customer..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <select name="status" class="filter-select">
                    <option value="">All Orders</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
                
                <button type="submit" class="btn" style="background: var(--bup-blue); color: white; border-radius: 30px; padding: 12px 30px;">
                    Apply Filter
                </button>
                <a href="orders.php" class="btn btn-secondary">Reset</a>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="content-card">
            <ul class="nav nav-tabs" id="orderTabs">
                <li class="nav-item">
                    <a class="nav-link <?php echo $status_filter == '' ? 'active' : ''; ?>" href="orders.php">All Orders</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status_filter == 'pending' ? 'active' : ''; ?>" href="orders.php?status=pending">Pending</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status_filter == 'processing' ? 'active' : ''; ?>" href="orders.php?status=processing">Processing</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status_filter == 'completed' ? 'active' : ''; ?>" href="orders.php?status=completed">Completed</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>" href="orders.php?status=cancelled">Cancelled</a>
                </li>
            </ul>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($orders) > 0): ?>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><strong>#<?php echo $order['OrderID']; ?></strong></td>
                                <td><?php echo date('M d, Y', strtotime($order['OrderDate'])); ?></td>
                                <td><?php echo htmlspecialchars($order['UserName']); ?></td>
                                <td><?php echo $order['total_items']; ?> items</td>
                                <td class="order-amount">৳<?php echo number_format($order['TotalAmount'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['Status']; ?>">
                                        <?php echo ucfirst($order['Status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="payment-badge payment-<?php echo $order['PaymentStatus']; ?>">
                                        <?php echo ucfirst($order['PaymentStatus']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <?php if ($order['Status'] == 'pending'): ?>
                                            <a href="orders.php?confirm=<?php echo $order['OrderID']; ?>" class="btn-action btn-confirm" onclick="return confirm('Confirm this order?')">
                                                <i class="bi bi-check-circle"></i> Confirm
                                            </a>
                                            <a href="orders.php?cancel=<?php echo $order['OrderID']; ?>" class="btn-action btn-cancel" onclick="return confirm('Cancel this order?')">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </a>
                                        <?php elseif ($order['Status'] == 'processing'): ?>
                                            <a href="orders.php?complete=<?php echo $order['OrderID']; ?>" class="btn-action btn-complete" onclick="return confirm('Mark as completed?')">
                                                <i class="bi bi-check-circle"></i> Complete
                                            </a>
                                            <a href="orders.php?cancel=<?php echo $order['OrderID']; ?>" class="btn-action btn-cancel" onclick="return confirm('Cancel this order?')">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </a>
                                        <?php elseif ($order['Status'] == 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php elseif ($order['Status'] == 'cancelled'): ?>
                                            <span class="badge bg-danger">Cancelled</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-cart" style="font-size: 48px; color: #ccc;"></i>
                                    <h5 class="mt-3 text-muted">No orders found</h5>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-hide alerts
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>