<?php
// admin/pending_products.php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=bup_books", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection error");
}

$admin_name = $_SESSION['admin_name'];
$admin_role = $_SESSION['admin_role'];

// Handle approval/rejection
if (isset($_GET['action']) && isset($_GET['id'])) {
    $product_id = $_GET['id'];
    if ($_GET['action'] == 'approve') {
        $stmt = $pdo->prepare("UPDATE product SET Status = 'A' WHERE ProductID = ?");
        $stmt->execute([$product_id]);

        // Get seller's user ID and product name for notification
        $stmt = $pdo->prepare("SELECT s.UserID, p.ProductName FROM product p JOIN seller s ON p.SellerID = s.SellerID WHERE p.ProductID = ?");
        $stmt->execute([$product_id]);
        $sellerData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sellerData) {
            $notifyStmt = $pdo->prepare("INSERT INTO notifications (UserID, Type, Title, Message, Link) VALUES (?, 'book_approved', 'Book Approved', ?, ?)");
            $notifyStmt->execute([
                $sellerData['UserID'],
                "Your book '{$sellerData['ProductName']}' has been approved and is now live.",
                "../user/book-details.php?id=$product_id"
            ]);
        }
    } elseif ($_GET['action'] == 'reject') {
        $stmt = $pdo->prepare("UPDATE product SET Status = 'R' WHERE ProductID = ?");
        $stmt->execute([$product_id]);
        // Optionally notify seller about rejection
    }
    header('Location: pending_products.php');
    exit();
}

// Fetch pending products
$stmt = $pdo->prepare("
    SELECT p.*, s.Name as SellerName, s.ContactInfo, u.Email 
    FROM product p
    JOIN seller s ON p.SellerID = s.SellerID
    JOIN users u ON s.UserID = u.UserID
    WHERE p.Status = 'P'
    ORDER BY p.ProductID DESC
");
$stmt->execute();
$pending_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Product Approvals - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
            --bup-gradient: linear-gradient(145deg, #0A3143, #1C4E6C);
            --bup-gradient-accent: linear-gradient(145deg, #FF914D, #FFC107);
            --shadow-sm: 0 5px 15px rgba(0,0,0,0.05);
            --shadow-md: 0 10px 25px rgba(255,145,77,0.15);
            --shadow-lg: 0 15px 35px rgba(10,49,67,0.2);
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
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: var(--bup-gradient);
            color: white;
            padding: 30px 20px;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 5px 0 30px rgba(0,0,0,0.15);
        }

        .sidebar-logo {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .logo-text {
            font-size: 36px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--bup-orange), var(--bup-yellow));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2px;
        }

        .logo-sub {
            font-size: 14px;
            color: var(--bup-yellow);
            letter-spacing: 2px;
            font-weight: 600;
        }

        .admin-info {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px 15px;
            background: rgba(255,255,255,0.05);
            border-radius: 20px;
        }

        .admin-avatar {
            width: 80px;
            height: 80px;
            background: var(--bup-gradient-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            font-weight: 700;
            color: var(--bup-blue);
            border: 3px solid white;
            box-shadow: 0 8px 0 #C7511E;
        }

        .admin-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .admin-badge {
            display: inline-block;
            background: rgba(255,145,77,0.3);
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin-top: 20px;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
            gap: 12px;
            font-weight: 500;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }

        .nav-link.active {
            background: var(--bup-gradient-accent);
            color: var(--bup-blue);
            font-weight: 700;
            box-shadow: 0 5px 0 #C7511E;
        }

        .nav-link i {
            font-size: 20px;
            width: 25px;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            transition: all 0.3s ease;
        }

        /* Mobile menu button */
        .mobile-menu-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: var(--bup-gradient-accent);
            border: none;
            border-radius: 50%;
            box-shadow: var(--shadow-lg);
            color: var(--bup-blue);
            font-size: 28px;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            cursor: pointer;
        }

        /* Loading Spinner */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--bup-gray-light);
            border-top-color: var(--bup-orange);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 260px;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .mobile-menu-btn {
                display: flex;
            }
        }

        /* Page specific styles */
        .product-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border: 1px solid #e1e1e1;
            transition: all 0.3s ease;
        }
        .product-card:hover {
            box-shadow: 0 8px 20px rgba(255,145,77,0.15);
            border-color: var(--bup-orange);
        }
        .product-image {
            max-height: 150px;
            max-width: 100%;
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 5px;
        }
        .btn-approve {
            background: rgba(40,167,69,0.15);
            color: #28a745;
            border: 1px solid #28a745;
        }
        .btn-approve:hover {
            background: #28a745;
            color: white;
        }
        .btn-reject {
            background: rgba(220,53,69,0.15);
            color: #dc3545;
            border: 1px solid #dc3545;
        }
        .btn-reject:hover {
            background: #dc3545;
            color: white;
        }
        .badge-pending {
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange);
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }
        .footer-actions {
            margin-top: 20px;
        }
    </style>
</head>
<body>

    <!-- Loading Spinner -->
    <div class="spinner-overlay" id="loadingSpinner">
        <div class="spinner"></div>
    </div>

    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <div class="logo-text">BUP</div>
            <div class="logo-sub">ADMIN PANEL</div>
        </div>
        
        <div class="admin-info">
            <div class="admin-avatar">
                <?php echo strtoupper(substr($admin_name, 0, 2)); ?>
            </div>
            <div class="admin-name"><?php echo htmlspecialchars($admin_name); ?></div>
            <span class="admin-badge">
                <i class="bi bi-shield-lock me-1"></i>
                <?php echo ucfirst(str_replace('_', ' ', $admin_role)); ?>
            </span>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="users.php" class="nav-link">
                    <i class="bi bi-people"></i>
                    <span>Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="sellers.php" class="nav-link">
                    <i class="bi bi-shop"></i>
                    <span>Sellers</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="products.php" class="nav-link">
                    <i class="bi bi-book"></i>
                    <span>Products</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="pending_products.php" class="nav-link active">
                    <i class="bi bi-clock-history"></i>
                    <span>Pending Approvals</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="orders.php" class="nav-link">
                    <i class="bi bi-cart"></i>
                    <span>Orders</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="payments.php" class="nav-link">
                    <i class="bi bi-credit-card"></i>
                    <span>Payments</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="feedback.php" class="nav-link">
                    <i class="bi bi-chat"></i>
                    <span>Feedback</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admins.php" class="nav-link">
                    <i class="bi bi-shield"></i>
                    <span>Admins</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="profile.php" class="nav-link">
                    <i class="bi bi-person-gear"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="settings.php" class="nav-link">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link" style="margin-top: 20px; background: rgba(255,69,58,0.2);">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2 class="mb-4">
            <i class="bi bi-clock-history me-2" style="color: var(--bup-orange);"></i>
            Pending Product Approvals
            <span class="badge bg-warning ms-2"><?php echo count($pending_products); ?></span>
        </h2>

        <?php if (count($pending_products) == 0): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                No products pending approval at the moment.
            </div>
        <?php else: ?>
            <?php foreach ($pending_products as $product): ?>
                <div class="product-card">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="d-flex align-items-start gap-3">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="../<?php echo htmlspecialchars($product['image']); ?>" alt="Cover" class="product-image" style="max-width: 100px;">
                                <?php else: ?>
                                    <div class="product-image" style="width: 100px; height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-book" style="font-size: 40px; color: #ccc;"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h4><?php echo htmlspecialchars($product['ProductName']); ?></h4>
                                    <p class="mb-1">
                                        <strong>Category:</strong> <?php echo htmlspecialchars($product['Category']); ?><br>
                                        <strong>Price:</strong> ₱<?php echo number_format($product['Price'], 2); ?><br>
                                        <strong>Stock:</strong> <?php echo $product['StockQuantity']; ?><br>
                                        <strong>Seller:</strong> <?php echo htmlspecialchars($product['SellerName']); ?> 
                                        (<?php echo htmlspecialchars($product['Email']); ?>)<br>
                                        <strong>Contact:</strong> <?php echo htmlspecialchars($product['ContactInfo']); ?>
                                    </p>
                                    <span class="badge-pending"><i class="bi bi-hourglass-split me-1"></i>Pending</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <a href="?action=approve&id=<?php echo $product['ProductID']; ?>" 
                               class="btn btn-approve me-2" 
                               onclick="return confirm('Approve this listing? It will become visible to buyers.')">
                                <i class="bi bi-check-circle"></i> Approve
                            </a>
                            <a href="?action=reject&id=<?php echo $product['ProductID']; ?>" 
                               class="btn btn-reject" 
                               onclick="return confirm('Reject this listing? It will be removed from pending.')">
                                <i class="bi bi-x-circle"></i> Reject
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="footer-actions">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        function checkMobileView() {
            const sidebar = document.getElementById('sidebar');
            const mobileBtn = document.getElementById('mobileMenuBtn');
            
            if (window.innerWidth <= 992) {
                mobileBtn.style.display = 'flex';
                sidebar.classList.remove('active');
            } else {
                mobileBtn.style.display = 'none';
                sidebar.classList.add('active');
            }
        }

        window.addEventListener('resize', checkMobileView);
        window.addEventListener('load', checkMobileView);

        // Loading spinner
        document.querySelectorAll('a:not([href^="#"]):not([href^="javascript:"]):not(.btn-icon)').forEach(link => {
            link.addEventListener('click', function(e) {
                document.getElementById('loadingSpinner').style.display = 'flex';
            });
        });

        window.addEventListener('load', function() {
            document.getElementById('loadingSpinner').style.display = 'none';
        });
    </script>
</body>
</html>