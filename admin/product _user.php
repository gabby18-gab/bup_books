<?php
// admin/product-details.php
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

$product_id = $_GET['id'] ?? 0;

// Get product details
$stmt = $pdo->prepare("
    SELECT p.*, s.Name as SellerName, s.ContactInfo as SellerContact, u.Email as SellerEmail
    FROM product p
    JOIN seller s ON p.SellerID = s.SellerID
    JOIN users u ON s.UserID = u.UserID
    WHERE p.ProductID = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: products.php');
    exit();
}

// Get order history for this product
$stmt = $pdo->prepare("
    SELECT o.OrderID, o.OrderDate, o.Status, od.Quantity, o.TotalAmount, u.Name as BuyerName
    FROM orderdetails od
    JOIN orders o ON od.OrderID = o.OrderID
    JOIN users u ON o.UserID = u.UserID
    WHERE od.ProductID = ?
    ORDER BY o.OrderDate DESC
    LIMIT 10
");
$stmt->execute([$product_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total sold
$stmt = $pdo->prepare("SELECT SUM(Quantity) as total FROM orderdetails WHERE ProductID = ?");
$stmt->execute([$product_id]);
$total_sold = $stmt->fetchColumn() ?: 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Details - <?php echo htmlspecialchars($product['ProductName']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --bup-blue: #0A3143;
            --bup-orange: #FF914D;
        }
        body {
            background: #F8FAFC;
            font-family: 'Segoe UI', sans-serif;
        }
        .details-card {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .product-title {
            font-size: 28px;
            font-weight: 800;
            color: var(--bup-blue);
            margin-bottom: 20px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin: 30px 0;
        }
        .info-item {
            padding: 15px;
            background: #F8FAFC;
            border-radius: 12px;
        }
        .info-label {
            font-size: 12px;
            color: #6B7F8B;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--bup-blue);
        }
        .btn-back {
            background: var(--bup-blue);
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-back:hover {
            background: #1C4E6C;
            color: white;
        }
    </style>
</head>
<body>
    <div class="details-card">
        <a href="products.php" class="btn-back mb-4">
            <i class="bi bi-arrow-left me-2"></i>Back to Products
        </a>
        
        <h1 class="product-title">
            <i class="bi bi-book me-2" style="color: var(--bup-orange);"></i>
            <?php echo htmlspecialchars($product['ProductName']); ?>
        </h1>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Product ID</div>
                <div class="info-value">#<?php echo $product['ProductID']; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Category</div>
                <div class="info-value"><?php echo htmlspecialchars($product['Category']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Price</div>
                <div class="info-value">$<?php echo number_format($product['Price'], 2); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Current Stock</div>
                <div class="info-value"><?php echo $product['StockQuantity']; ?> units</div>
            </div>
            <div class="info-item">
                <div class="info-label">Total Sold</div>
                <div class="info-value"><?php echo $total_sold; ?> units</div>
            </div>
            <div class="info-item">
                <div class="info-label">Status</div>
                <div class="info-value"><?php echo $product['Status'] == 'A' ? 'Active' : 'Inactive'; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Seller</div>
                <div class="info-value"><?php echo htmlspecialchars($product['SellerName']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Seller Contact</div>
                <div class="info-value"><?php echo htmlspecialchars($product['SellerContact']); ?></div>
            </div>
        </div>
        
        <h3 class="mt-4 mb-3">Recent Orders</h3>
        <?php if (count($orders) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Buyer</th>
                        <th>Quantity</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo $order['OrderID']; ?></td>
                        <td><?php echo htmlspecialchars($order['BuyerName']); ?></td>
                        <td><?php echo $order['Quantity']; ?></td>
                        <td><?php echo date('M d, Y', strtotime($order['OrderDate'])); ?></td>
                        <td><?php echo $order['Status']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">No orders yet for this product.</p>
        <?php endif; ?>
    </div>
</body>
</html>