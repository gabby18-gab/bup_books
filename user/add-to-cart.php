<?php
// user/add-to-cart.php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to add items to cart']);
    exit();
}

// Check if product_id is provided
if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit();
}

$product_id = intval($_POST['product_id']);
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
$user_id = $_SESSION['user_id'];

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=bup_books", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Cart DB connection error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit();
}

// Check if product exists and has stock
try {
    $stmt = $pdo->prepare("SELECT ProductName, StockQuantity FROM product WHERE ProductID = ? AND Status = 'A'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }
    
    if ($product['StockQuantity'] < $quantity) {
        echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
        exit();
    }
    
    // Check if product already in cart
    $stmt = $pdo->prepare("SELECT CartID, Quantity FROM cart WHERE UserID = ? AND ProductID = ?");
    $stmt->execute([$user_id, $product_id]);
    $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cart_item) {
        // Update existing cart item
        $new_quantity = $cart_item['Quantity'] + $quantity;
        
        // Check stock again for new quantity
        if ($product['StockQuantity'] < $new_quantity) {
            echo json_encode(['success' => false, 'message' => 'Cannot add more than available stock']);
            exit();
        }
        
        $stmt = $pdo->prepare("UPDATE cart SET Quantity = ? WHERE CartID = ?");
        $stmt->execute([$new_quantity, $cart_item['CartID']]);
    } else {
        // Insert new cart item
        $stmt = $pdo->prepare("INSERT INTO cart (UserID, ProductID, Quantity) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $product_id, $quantity]);
    }
    
    // Get updated cart count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE UserID = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $cart_count = $result ? $result['count'] : 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Book added to cart successfully',
        'cart_count' => $cart_count,
        'product_name' => $product['ProductName']
    ]);
    
} catch (PDOException $e) {
    error_log("Error adding to cart: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error adding to cart']);
}
?>