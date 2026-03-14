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
        
        // Create order - FIXED: Added missing $order_id variable
        $stmt = $pdo->prepare("
            INSERT INTO orders (UserID, SellerID, OrderDate, Status, TotalAmount, PaymentStatus, ShippingAddress)
            VALUES (?, ?, NOW(), 'pending', ?, 'pending', ?)
        ");
        $stmt->execute([$user_id, $seller_id, $total, $user['Address'] ?? 'No address provided']);
        $order_id = $pdo->lastInsertId(); // This was missing!
        
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

<!-- Rest of your HTML remains the same -->