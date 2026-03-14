<?php
// user/order-details.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$order_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=bup_books", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection error");
}

// Get order details
$stmt = $pdo->prepare("
    SELECT o.*, u.Name, u.Email, u.Address, u.ContactNo 
    FROM orders o 
    JOIN users u ON o.UserID = u.UserID 
    WHERE o.OrderID = ? AND o.UserID = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: my_orders.php');
    exit();
}

// Get order items
$itemsStmt = $pdo->prepare("
    SELECT od.*, p.ProductName, p.Price, p.image 
    FROM orderdetails od 
    JOIN product p ON od.ProductID = p.ProductID 
    WHERE od.OrderID = ?
");
$itemsStmt->execute([$order_id]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details #<?php echo $order_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --bup-blue: #0A3143;
            --bup-orange: #FF914D;
        }
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
            padding: 30px;
        }
        .details-card {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 700;
        }
        .status-pending { background: rgba(255,145,77,0.15); color: #FF914D; }
        .status-processing { background: rgba(0,123,255,0.15); color: #007bff; }
        .status-shipped { background: rgba(23,162,184,0.15); color: #17a2b8; }
        .status-completed { background: rgba(40,167,69,0.15); color: #28a745; }
        .status-cancelled { background: rgba(220,53,69,0.15); color: #dc3545; }
    </style>
</head>
<body>
    <div class="details-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Order #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></h2>
            <a href="my_orders.php" class="btn btn-outline-secondary">Back to Orders</a>
        </div>
        
        <div class="mb-4">
            <span class="status-badge status-<?php echo $order['Status']; ?>">
                <?php echo ucfirst($order['Status']); ?>
            </span>
            <span class="ms-2 badge bg-info">Payment: <?php echo $order['PaymentStatus']; ?></span>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Order Information</h5>
                <p><strong>Date:</strong> <?php echo date('F j, Y h:i A', strtotime($order['OrderDate'])); ?></p>
                <p><strong>Total Amount:</strong> ?<?php echo number_format($order['TotalAmount'], 2); ?></p>
            </div>
            <div class="col-md-6">
                <h5>Shipping Address</h5>
                <p><?php echo htmlspecialchars($order['Address'] ?: 'No address provided'); ?></p>
                <p><strong>Contact:</strong> <?php echo htmlspecialchars($order['ContactNo'] ?: 'N/A'); ?></p>
            </div>
        </div>
        
        <h5 class="mb-3">Order Items</h5>
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['ProductName']); ?></td>
                    <td>?<?php echo number_format($item['Price'], 2); ?></td>
                    <td><?php echo $item['Quantity']; ?></td>
                    <td>?<?php echo number_format($item['Price'] * $item['Quantity'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-end">Total:</th>
                    <th>?<?php echo number_format($order['TotalAmount'], 2); ?></th>
                </tr>
            </tfoot>
        </table>
        
        <?php if ($order['Status'] == 'cancelled' && $order['CancellationReason']): ?>
        <div class="alert alert-danger mt-3">
            <strong>Cancellation Reason:</strong> <?php echo htmlspecialchars($order['CancellationReason']); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($order['Status'] == 'shipped' && $order['TrackingNumber']): ?>
        <div class="alert alert-info mt-3">
            <strong>Tracking Number:</strong> <?php echo htmlspecialchars($order['TrackingNumber']); ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>