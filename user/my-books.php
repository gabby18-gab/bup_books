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
$user_name = $_SESSION['user_name'] ?? 'User';

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
            font-size: 32px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--bup-orange), var(--bup-yellow));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2px;
        }

        .logo-sub {
            font-size: 12px;
            color: var(--bup-yellow);
            letter-spacing: 2px;
        }

        .user-info {
            text-align: center;
            margin-bottom: 30px;
            padding: 15px;
            border-radius: 16px;
        }

        .user-avatar {
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

        .user-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .user-badge {
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
            margin-top: 40px;
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

        /* Book card styles */
        .book-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--bup-gray-light);
            transition: all 0.3s ease;
        }
        .book-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--bup-orange);
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
            display: inline-block;
            transition: all 0.2s;
        }
        .btn-edit {
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange);
        }
        .btn-edit:hover {
            background: var(--bup-orange);
            color: white;
        }
        .btn-toggle {
            background: rgba(255,193,7,0.15);
            color: #ffc107;
        }
        .btn-toggle:hover {
            background: #ffc107;
            color: white;
        }
        .btn-add {
            background: var(--bup-gradient-accent);
            color: var(--bup-blue);
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: var(--bup-blue);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--bup-blue);
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
            <div class="logo-sub">BOOK RESALE</div>
        </div>
        
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user_name, 0, 2)); ?>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
            <span class="user-badge">
                <i class="bi bi-shop me-1"></i>
                Seller
            </span>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="bi bi-search"></i>
                    <span>Browse Books</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="my-books.php" class="nav-link active">
                    <i class="bi bi-book"></i>
                    <span>My Books</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="sell.php" class="nav-link">
                    <i class="bi bi-plus-circle"></i>
                    <span>Sell New Book</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="orders.php" class="nav-link">
                    <i class="bi bi-cart"></i>
                    <span>My Orders</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="profile.php" class="nav-link">
                    <i class="bi bi-person-gear"></i>
                    <span>My Profile</span>
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
                            <span class="price">₱<?php echo number_format($book['Price'], 2); ?></span>
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
                            <a href="edit-book.php?id=<?php echo $book['ProductID']; ?>" class="btn-icon btn-edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="?toggle&id=<?php echo $book['ProductID']; ?>" class="btn-icon btn-toggle">
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle sidebar on mobile
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        // Check mobile view
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