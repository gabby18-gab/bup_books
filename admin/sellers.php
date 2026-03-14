<?php
// admin/sellers.php
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

// Get sellers with stats
$stmt = $pdo->query("
    SELECT s.*, u.Name as UserName, u.Email,
           (SELECT COUNT(*) FROM product WHERE SellerID = s.SellerID) as product_count,
           (SELECT COUNT(*) FROM orders WHERE SellerID = s.SellerID) as order_count,
           (SELECT SUM(TotalAmount) FROM orders WHERE SellerID = s.SellerID) as total_revenue
    FROM seller s
    JOIN users u ON s.UserID = u.UserID
    ORDER BY s.CreatedAt DESC
");
$sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sellers Management - BUP BOOKS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        /* Reuse styles */
        .seller-logo {
            width: 50px;
            height: 50px;
            background: linear-gradient(145deg, #0A3143, #1C4E6C);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 20px;
        }
        
        .revenue-positive {
            color: #28a745;
            font-weight: 700;
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
                <i class="bi bi-shop me-3" style="color: var(--bup-orange);"></i>Sellers Management
            </h1>
        </div>

        <div class="content-card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Seller</th>
                            <th>Contact Info</th>
                            <th>Products</th>
                            <th>Orders</th>
                            <th>Revenue</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sellers as $seller): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="seller-logo me-3">
                                        <?php echo strtoupper(substr($seller['Name'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($seller['Name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($seller['UserName']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($seller['ContactInfo']); ?>
                                <br>
                                <small class="text-muted"><?php echo $seller['AssignPrice']; ?></small>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $seller['product_count']; ?> books</span>
                            </td>
                            <td><?php echo $seller['order_count']; ?></td>
                            <td class="revenue-positive">$<?php echo number_format($seller['total_revenue'] ?: 0, 2); ?></td>
                            <td>
                                <span class="status-badge <?php echo $seller['Status'] == 'A' ? 'status-active' : 'status-pending'; ?>">
                                    <?php echo $seller['Status'] == 'A' ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($seller['CreatedAt'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="sellers.php?view=<?php echo $seller['SellerID']; ?>" class="btn-icon btn-view">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="sellers.php?edit=<?php echo $seller['SellerID']; ?>" class="btn-icon btn-edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>