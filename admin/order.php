<?php
// admin/orders.php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=bup_books", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['admin_role'];

// Update order status
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    $payment_status = $_POST['payment_status'] ?? 'pending';
    
    try {
        $stmt = $pdo->prepare("UPDATE orders SET Status = ?, PaymentStatus = ? WHERE OrderID = ?");
        $stmt->execute([$new_status, $payment_status, $order_id]);
        $message = "Order #$order_id updated successfully!";
    } catch (PDOException $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// Search and filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment'] ?? '';

$query = "SELECT o.*, u.Name as UserName, u.Email, s.Name as SellerName 
          FROM orders o 
          JOIN users u ON o.UserID = u.UserID 
          JOIN seller s ON o.SellerID = s.SellerID 
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (o.OrderID LIKE ? OR u.Name LIKE ? OR s.Name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $query .= " AND o.Status = ?";
    $params[] = $status_filter;
}

if ($payment_filter) {
    $query .= " AND o.PaymentStatus = ?";
    $params[] = $payment_filter;
}

$query .= " ORDER BY o.OrderDate DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$total_revenue = $pdo->query("SELECT SUM(TotalAmount) FROM orders WHERE PaymentStatus = 'paid'")->fetchColumn() ?: 0;
$pending_count = $pdo->query("SELECT COUNT(*) FROM orders WHERE Status = 'pending'")->fetchColumn();
$completed_count = $pdo->query("SELECT COUNT(*) FROM orders WHERE Status = 'completed'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - BUP BOOKS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        /* Reuse styles from previous pages */
        :root {
            --bup-blue: #0A3143;
            --bup-orange: #FF914D;
            --bup-yellow: #FFC107;
        }

        .order-amount {
            font-weight: 700;
            color: var(--bup-orange);
        }

        .payment-badge {
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
        }

        .payment-paid {
            background: rgba(40,167,69,0.15);
            color: #28a745;
        }

        .payment-pending {
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange);
        }

        .payment-failed {
            background: rgba(220,53,69,0.15);
            color: #dc3545;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <!-- ... -->
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 style="font-size: 28px; font-weight: 800; color: var(--bup-blue);">
                <i class="bi bi-cart me-3" style="color: var(--bup-orange);"></i>Orders Management
            </h1>
        </div>

        <!-- Stats -->
        <div class="stats-mini">
            <div class="stat-mini-card">
                <h6>Total Orders</h6>
                <h2 style="font-size: 32px; font-weight: 800;"><?php echo count($orders); ?></h2>
            </div>
            <div class="stat-mini-card">
                <h6>Total Revenue</h6>
                <h2 style="font-size: 32px; font-weight: 800; color: var(--bup-orange);">$<?php echo number_format($total_revenue, 2); ?></h2>
            </div>
            <div class="stat-mini-card">
                <h6>Pending Orders</h6>
                <h2 style="font-size: 32px; font-weight: 800; color: #FFC107;"><?php echo $pending_count; ?></h2>
            </div>
            <div class="stat-mini-card">
                <h6>Completed</h6>
                <h2 style="font-size: 32px; font-weight: 800; color: #28a745;"><?php echo $completed_count; ?></h2>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="content-card">
            <form method="GET" class="filter-bar">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" placeholder="Search by Order ID, Customer, or Seller..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
                
                <select name="payment" class="filter-select">
                    <option value="">All Payments</option>
                    <option value="pending" <?php echo $payment_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="paid" <?php echo $payment_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="failed" <?php echo $payment_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                </select>
                
                <button type="submit" class="btn" style="background: var(--bup-blue); color: white; border-radius: 30px;">
                    Apply Filter
                </button>
                <a href="orders.php" class="btn" style="background: var(--bup-gray-light); color: var(--bup-blue); border-radius: 30px;">
                    Reset
                </a>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="content-card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Seller</th>
                            <th>Amount</th>
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
                                <td><?php echo htmlspecialchars($order['SellerName']); ?></td>
                                <td class="order-amount">$<?php echo number_format($order['TotalAmount'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php 
                                        echo $order['Status'] == 'completed' ? 'status-active' : 
                                            ($order['Status'] == 'pending' ? 'status-pending' : 'status-pending'); 
                                    ?>">
                                        <?php echo ucfirst($order['Status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="payment-badge payment-<?php echo $order['PaymentStatus']; ?>">
                                        <?php echo ucfirst($order['PaymentStatus']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm" style="background: var(--bup-orange); color: white;" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $order['OrderID']; ?>">
                                        <i class="bi bi-pencil"></i> Update
                                    </button>
                                </td>
                            </tr>

                            <!-- Update Modal -->
                            <div class="modal fade" id="updateModal<?php echo $order['OrderID']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Update Order #<?php echo $order['OrderID']; ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="order_id" value="<?php echo $order['OrderID']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Order Status</label>
                                                    <select name="status" class="form-select">
                                                        <option value="pending" <?php echo $order['Status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="processing" <?php echo $order['Status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                        <option value="completed" <?php echo $order['Status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                        <option value="cancelled" <?php echo $order['Status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Payment Status</label>
                                                    <select name="payment_status" class="form-select">
                                                        <option value="pending" <?php echo $order['PaymentStatus'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="paid" <?php echo $order['PaymentStatus'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                        <option value="failed" <?php echo $order['PaymentStatus'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="update_status" class="btn" style="background: var(--bup-orange); color: white;">Update Order</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-cart" style="font-size: 48px; color: var(--bup-gray-light);"></i>
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
</body>
</html>