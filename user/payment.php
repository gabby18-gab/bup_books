<?php
// user/payment.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : 0;

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=bup_books", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection error");
}

// Get order details
$stmt = $pdo->prepare("SELECT o.*, u.Name FROM orders o JOIN users u ON o.UserID = u.UserID WHERE o.OrderID = ? AND o.UserID = ?");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: my_orders.php');
    exit();
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Update order status
        $updateStmt = $pdo->prepare("UPDATE orders SET PaymentStatus = 'paid', Status = 'processing' WHERE OrderID = ?");
        $updateStmt->execute([$order_id]);
        
        // Update payment record
        $paymentStmt = $pdo->prepare("UPDATE payment SET PaymentMethod = ?, PaymentDate = NOW() WHERE OrderID = ?");
        $paymentStmt->execute([$_POST['payment_method'], $order_id]);
        
        // Create notification
        $notifyStmt = $pdo->prepare("
            INSERT INTO notifications (UserID, Type, Title, Message) 
            VALUES (?, 'payment_received', 'Payment Received', ?)
        ");
        $notifyStmt->execute([$_SESSION['user_id'], "Payment for order #$order_id has been received."]);
        
        $pdo->commit();
        
        $success = "Payment successful! Your order is now being processed.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Payment failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Order #<?php echo $order_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --bup-blue: #0A3143;
            --bup-orange: #FF914D;
        }
        body {
            background: linear-gradient(135deg, #0A3143, #1C4E6C);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
        }
        .payment-card {
            background: white;
            border-radius: 30px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
        }
        .payment-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .payment-header h1 {
            color: var(--bup-blue);
            font-size: 28px;
            font-weight: 800;
        }
        .order-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .btn-pay {
            background: linear-gradient(145deg, #FF914D, #FFC107);
            color: #0A3143;
            border: none;
            padding: 15px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 18px;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 0 #C7511E;
        }
        .btn-pay:hover {
            transform: translateY(2px);
            box-shadow: 0 2px 0 #C7511E;
        }
        .btn-back {
            display: inline-block;
            color: var(--bup-blue);
            text-decoration: none;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="payment-card">
        <div class="payment-header">
            <h1><i class="bi bi-credit-card me-2" style="color: var(--bup-orange);"></i>Make Payment</h1>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <a href="my_orders.php" class="btn btn-primary">View My Orders</a>
        <?php else: ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="order-details">
                <p><strong>Order ID:</strong> #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></p>
                <p><strong>Amount:</strong> ?<?php echo number_format($order['TotalAmount'], 2); ?></p>
                <p><strong>Status:</strong> <span class="badge bg-warning">Pending Payment</span></p>
            </div>
            
            <form method="POST">
                <h5 class="mb-3">Select Payment Method</h5>
                <div class="mb-3">
                    <select name="payment_method" class="form-select" required>
                        <option value="bKash">bKash</option>
                        <option value="Nagad">Nagad</option>
                        <option value="Rocket">Rocket</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Cash on Delivery">Cash on Delivery</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-pay">
                    <i class="bi bi-check-circle me-2"></i>Confirm Payment
                </button>
            </form>
            
            <div class="text-center">
                <a href="my_orders.php" class="btn-back">
                    <i class="bi bi-arrow-left me-2"></i>Back to Orders
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>