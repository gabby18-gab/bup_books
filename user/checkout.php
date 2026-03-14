<?php
// user/checkout.php
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
    error_log("Checkout DB connection error: " . $e->getMessage());
    die("Database connection error");
}

$user_id = $_SESSION['user_id'];

// Get user address
$userStmt = $pdo->prepare("SELECT Address FROM users WHERE UserID = ?");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

// Fetch cart items
$stmt = $pdo->prepare("
    SELECT c.CartID, c.Quantity,
           p.ProductID, p.ProductName, p.Price, p.StockQuantity, p.image,
           s.Name as SellerName, s.SellerID
    FROM cart c
    JOIN product p ON c.ProductID = p.ProductID
    LEFT JOIN seller s ON p.SellerID = s.SellerID
    WHERE c.UserID = ?
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Redirect if cart is empty
if (empty($cart_items)) {
    header('Location: cart.php');
    exit();
}

// Calculate total
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['Price'] * $item['Quantity'];
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Get the first seller ID (for simplicity - in real app, handle multiple sellers)
        $seller_id = $cart_items[0]['SellerID'];
        
        // Create order
        $stmt = $pdo->prepare("
            INSERT INTO orders (UserID, SellerID, OrderDate, Status, TotalAmount, PaymentStatus, ShippingAddress)
            VALUES (?, ?, NOW(), 'pending', ?, 'pending', ?)
        ");
        $stmt->execute([$user_id, $seller_id, $total, $user['Address'] ?? 'No address provided']);
        $order_id = $pdo->lastInsertId();
        
        // Insert order details
        foreach ($cart_items as $item) {
            $stmt = $pdo->prepare("
                INSERT INTO orderdetails (OrderID, ProductID, Quantity)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$order_id, $item['ProductID'], $item['Quantity']]);
            
            // Update stock
            $stmt = $pdo->prepare("
                UPDATE product SET StockQuantity = StockQuantity - ? WHERE ProductID = ?
            ");
            $stmt->execute([$item['Quantity'], $item['ProductID']]);
        }
        
        // Create payment record
        $stmt = $pdo->prepare("
            INSERT INTO payment (OrderID, Amount, PaymentMethod, PaymentDate)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$order_id, $total, $_POST['payment_method']]);
        
        // Create notification
        $notifyStmt = $pdo->prepare("
            INSERT INTO notifications (UserID, Type, Title, Message) 
            VALUES (?, 'order_placed', 'Order Placed', ?)
        ");
        $notifyStmt->execute([$user_id, "Your order #$order_id has been placed successfully."]);
        
        // Clear cart
        $stmt = $pdo->prepare("DELETE FROM cart WHERE UserID = ?");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        
        // Redirect to success page
        header('Location: order_success.php?order_id=' . $order_id);
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Order failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - BUP Platform Book Resale</title>
    
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
        }

        .nav-link:hover {
            color: var(--bup-orange) !important;
        }

        /* Main Content */
        .checkout-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .checkout-card {
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 8px 20px rgba(10, 49, 67, 0.05);
            border: 1px solid rgba(225, 233, 240, 0.5);
        }

        .checkout-title {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 30px;
            color: var(--bup-blue);
        }

        .order-summary {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .total-row {
            font-size: 20px;
            font-weight: 700;
            color: var(--bup-orange);
        }

        .payment-method {
            margin: 20px 0;
        }

        .payment-option {
            display: block;
            padding: 15px;
            border: 2px solid var(--bup-gray-light);
            border-radius: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-option:hover {
            border-color: var(--bup-orange);
        }

        .payment-option input[type="radio"] {
            margin-right: 10px;
        }

        .btn-place-order {
            background: linear-gradient(145deg, var(--bup-orange), var(--bup-yellow));
            color: var(--bup-blue);
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 18px;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 0 #C7511E;
        }

        .btn-place-order:hover {
            transform: translateY(2px);
            box-shadow: 0 2px 0 #C7511E;
        }

        .btn-back {
            display: inline-block;
            background: var(--bup-gray-light);
            color: var(--bup-blue);
            padding: 12px 25px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
        }

        .btn-back:hover {
            background: var(--bup-orange);
            color: white;
        }

        .price {
            font-weight: 700;
            color: var(--bup-orange);
        }

        .alert {
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background: rgba(220,53,69,0.15);
            color: #dc3545;
            border-left: 5px solid #dc3545;
        }
    </style>
</head>
<body>

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
                        <a class="nav-link" href="my_orders.php">
                            <i class="bi bi-box"></i> My Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-person"></i> <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?>
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
    <div class="checkout-container">
        <div class="checkout-card">
            <h1 class="checkout-title">
                <i class="bi bi-credit-card me-2" style="color: var(--bup-orange);"></i>Checkout
            </h1>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-7">
                    <form method="post" id="checkoutForm">
                        <h4 class="mb-3">Payment Method</h4>
                        <div class="payment-method">
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="Cash on Delivery" required>
                                <i class="bi bi-cash me-2"></i>Cash on Delivery
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="GCash">
                                <i class="bi bi-phone me-2"></i>GCash
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="PayMaya">
                                <i class="bi bi-credit-card me-2"></i>PayMaya
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="Bank Transfer">
                                <i class="bi bi-bank me-2"></i>Bank Transfer
                            </label>
                        </div>
                        
                        <button type="submit" class="btn-place-order">
                            <i class="bi bi-check-circle me-2"></i>Place Order
                        </button>
                    </form>
                </div>
                
                <div class="col-md-5">
                    <div class="order-summary">
                        <h4 class="mb-3">Order Summary</h4>
                        <?php foreach ($cart_items as $item): 
                            $subtotal = $item['Price'] * $item['Quantity'];
                        ?>
                        <div class="summary-item">
                            <span>
                                <?php echo htmlspecialchars($item['ProductName']); ?> 
                                <span class="text-muted">x<?php echo $item['Quantity']; ?></span>
                            </span>
                            <span class="price">₱<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <div class="summary-item total-row">
                            <span>Total</span>
                            <span class="price">₱<?php echo number_format($total, 2); ?></span>
                        </div>
                    </div>
                    
                    <a href="cart.php" class="btn-back">
                        <i class="bi bi-arrow-left me-2"></i>Back to Cart
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>