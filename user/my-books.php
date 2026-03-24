<?php
// user/my-books.php
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
    die("Database connection error");
}

$user_id = $_SESSION['user_id'];

// Get seller ID if exists
$stmt = $pdo->prepare("SELECT SellerID FROM seller WHERE UserID = ?");
$stmt->execute([$user_id]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seller) {
    // Not a seller, redirect to become seller
    header('Location: become-seller.php');
    exit();
}

$seller_id = $seller['SellerID'];

// Fetch books listed by this seller
$stmt = $pdo->prepare("SELECT * FROM product WHERE SellerID = ? ORDER BY ProductID DESC");
$stmt->execute([$seller_id]);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle deactivate/activate (optional)
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $product_id = $_GET['id'];
    $stmt = $pdo->prepare("UPDATE product SET Status = IF(Status = 'A', 'I', 'A') WHERE ProductID = ? AND SellerID = ?");
    $stmt->execute([$product_id, $seller_id]);
    header('Location: my-books.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Books - BUP Book Resale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --bup-blue: #0A3143;
            --bup-orange: #FF914D;
            --bup-yellow: #FFC107;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            padding: 30px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: var(--bup-blue);
            font-weight: 800;
        }
        .btn-add {
            background: linear-gradient(145deg, var(--bup-orange), var(--bup-yellow));
            color: var(--bup-blue);
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 700;
            text-decoration: none;
        }
        .book-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #e1e1e1;
        }
        .book-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--bup-blue);
        }
        .price {
            color: var(--bup-orange);
            font-weight: 700;
            font-size: 20px;
        }
        .stock-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }
        .stock-low { background: rgba(220,53,69,0.15); color: #dc3545; }
        .stock-ok { background: rgba(40,167,69,0.15); color: #28a745; }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active { background: rgba(40,167,69,0.15); color: #28a745; }
        .status-inactive { background: rgba(108,117,125,0.15); color: #6c757d; }
        .btn-icon {
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            margin: 0 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="bi bi-book me-2" style="color: var(--bup-orange);"></i>My Books</h1>
            <a href="sell.php" class="btn-add"><i class="bi bi-plus-circle me-2"></i>Sell New Book</a>
        </div>

        <?php if (count($books) > 0): ?>
            <?php foreach ($books as $book): ?>
                <div class="book-card">
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <div class="book-title"><?php echo htmlspecialchars($book['ProductName']); ?></div>
                            <small class="text-muted">Category: <?php echo htmlspecialchars($book['Category']); ?></small>
                        </div>
                        <div class="col-md-2">
                            <span class="price">$<?php echo number_format($book['Price'], 2); ?></span>
                        </div>
                        <div class="col-md-2">
                            <?php 
                            $stock_class = $book['StockQuantity'] <= 2 ? 'stock-low' : 'stock-ok';
                            ?>
                            <span class="stock-badge <?php echo $stock_class; ?>">
                                <?php echo $book['StockQuantity']; ?> in stock
                            </span>
                        </div>
                        <div class="col-md-1">
                            <span class="status-badge <?php echo $book['Status'] == 'A' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $book['Status'] == 'A' ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        <div class="col-md-2 text-end">
                            <a href="edit-book.php?id=<?php echo $book['ProductID']; ?>" class="btn-icon btn-edit" style="background: rgba(255,145,77,0.15); color: var(--bup-orange);">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="?toggle&id=<?php echo $book['ProductID']; ?>" class="btn-icon" style="background: rgba(255,193,7,0.15); color: #ffc107;">
                                <i class="bi bi-arrow-repeat"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-book" style="font-size: 60px; color: #ccc;"></i>
                <h4 class="mt-3">You haven't listed any books yet.</h4>
                <a href="sell.php" class="btn-add mt-3">Sell Your First Book</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>