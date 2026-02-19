<?php
// admin/products.php
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

// Handle actions
$message = '';
$error = '';

// Delete product
if (isset($_GET['delete']) && $admin_role == 'super_admin') {
    $product_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM product WHERE ProductID = ?");
        $stmt->execute([$product_id]);
        $message = "Product deleted successfully!";
    } catch (PDOException $e) {
        $error = "Cannot delete product: " . $e->getMessage();
    }
}

// Toggle product status
if (isset($_GET['toggle'])) {
    $product_id = $_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE product SET Status = IF(Status = 'A', 'I', 'A') WHERE ProductID = ?");
    $stmt->execute([$product_id]);
    $message = "Product status updated!";
}

// Search and filter
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT p.*, s.Name as SellerName 
          FROM product p 
          JOIN seller s ON p.SellerID = s.SellerID 
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (p.ProductName LIKE ? OR p.Category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $query .= " AND p.Category = ?";
    $params[] = $category_filter;
}

if ($status_filter && in_array($status_filter, ['A', 'I'])) {
    $query .= " AND p.Status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY p.ProductID DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique categories for filter
$categories = $pdo->query("SELECT DISTINCT Category FROM product ORDER BY Category")->fetchAll(PDO::FETCH_COLUMN);

// Get stats
$total_products = count($products);
$active_products = $pdo->query("SELECT COUNT(*) FROM product WHERE Status = 'A'")->fetchColumn();
$total_value = $pdo->query("SELECT SUM(Price * StockQuantity) FROM product")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - BUP BOOKS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Copy the styles from users.php and add product-specific styles */
        :root {
            --bup-blue: #0A3143;
            --bup-orange: #FF914D;
            --bup-yellow: #FFC107;
        }

        .product-image-sm {
            width: 50px;
            height: 50px;
            background: linear-gradient(145deg, #F0F4F8, #E8F0F5);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--bup-blue);
        }

        .stock-badge {
            background: rgba(10,49,67,0.1);
            color: var(--bup-blue);
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
        }

        .stock-low {
            background: rgba(220,53,69,0.15);
            color: #dc3545;
        }

        .stock-medium {
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange);
        }

        .stock-high {
            background: rgba(40,167,69,0.15);
            color: #28a745;
        }
    </style>
</head>
<body>
    <!-- Sidebar (same as before) -->
    <div class="sidebar">
        <!-- ... -->
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 style="font-size: 28px; font-weight: 800; color: var(--bup-blue);">
                <i class="bi bi-book me-3" style="color: var(--bup-orange);"></i>Products Management
            </h1>
            <a href="products.php?action=add" class="btn-add">
                <i class="bi bi-plus-circle"></i> Add New Product
            </a>
        </div>

        <!-- Mini Stats -->
        <div class="stats-mini">
            <div class="stat-mini-card">
                <h6 style="color: #6B7F8B;">Total Products</h6>
                <h2 style="font-size: 32px; font-weight: 800; color: var(--bup-blue);"><?php echo $total_products; ?></h2>
            </div>
            <div class="stat-mini-card">
                <h6 style="color: #6B7F8B;">Active Products</h6>
                <h2 style="font-size: 32px; font-weight: 800; color: #28a745;"><?php echo $active_products; ?></h2>
            </div>
            <div class="stat-mini-card">
                <h6 style="color: #6B7F8B;">Inventory Value</h6>
                <h2 style="font-size: 32px; font-weight: 800; color: var(--bup-orange);">$<?php echo number_format($total_value, 2); ?></h2>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="content-card">
            <form method="GET" class="filter-bar">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" placeholder="Search by product name or category..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <select name="category" class="filter-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter == $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="A" <?php echo $status_filter == 'A' ? 'selected' : ''; ?>>Active</option>
                    <option value="I" <?php echo $status_filter == 'I' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                
                <button type="submit" class="btn" style="background: var(--bup-blue); color: white; border-radius: 30px; padding: 12px 30px;">
                    <i class="bi bi-funnel me-2"></i>Apply Filter
                </button>
                <a href="products.php" class="btn" style="background: var(--bup-gray-light); color: var(--bup-blue); border-radius: 30px; padding: 12px 30px;">
                    <i class="bi bi-arrow-repeat me-2"></i>Reset
                </a>
            </form>
        </div>

        <!-- Products Table -->
        <div class="content-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Seller</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="product-image-sm me-3">
                                            <i class="bi bi-journal-bookmark-fill"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($product['ProductName']); ?></strong>
                                            <br>
                                            <small class="text-muted">ID: #<?php echo $product['ProductID']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($product['Category']); ?></td>
                                <td><?php echo htmlspecialchars($product['SellerName']); ?></td>
                                <td style="font-weight: 700; color: var(--bup-orange);">$<?php echo number_format($product['Price'], 2); ?></td>
                                <td>
                                    <?php
                                    $stock = $product['StockQuantity'];
                                    $stockClass = 'stock-high';
                                    if ($stock <= 2) $stockClass = 'stock-low';
                                    elseif ($stock <= 5) $stockClass = 'stock-medium';
                                    ?>
                                    <span class="stock-badge <?php echo $stockClass; ?>">
                                        <?php echo $stock; ?> units
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $product['Status'] == 'A' ? 'status-active' : 'status-pending'; ?>">
                                        <?php echo $product['Status'] == 'A' ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="products.php?view=<?php echo $product['ProductID']; ?>" class="btn-icon btn-view" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="products.php?edit=<?php echo $product['ProductID']; ?>" class="btn-icon btn-edit" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="products.php?toggle=<?php echo $product['ProductID']; ?>" class="btn-icon" style="background: rgba(255,193,7,0.15); color: #FFC107;" title="Toggle Status">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </a>
                                        <?php if ($admin_role == 'super_admin'): ?>
                                        <a href="products.php?delete=<?php echo $product['ProductID']; ?>" class="btn-icon" style="background: rgba(220,53,69,0.15); color: #dc3545;" title="Delete" onclick="return confirm('Are you sure you want to delete this product?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="bi bi-book" style="font-size: 48px; color: var(--bup-gray-light);"></i>
                                    <h5 class="mt-3 text-muted">No products found</h5>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>