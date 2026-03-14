<?php
// user/orderlist.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$database = "bup_books";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$user_id = $_SESSION['user_id'];

// Fetch user's orders with details
$sql = "
    SELECT 
        o.OrderID,
        o.Status,
        o.OrderDate,
        o.TotalAmount,
        o.PaymentStatus,
        o.ShippingAddress,
        p.ProductName,
        p.Price,
        p.image,
        od.Quantity,
        (p.Price * od.Quantity) as ItemTotal,
        u.ContactNo
    FROM orders o
    JOIN orderdetails od ON o.OrderID = od.OrderID
    JOIN product p ON od.ProductID = p.ProductID
    JOIN users u ON o.UserID = u.UserID
    WHERE o.UserID = $user_id
    ORDER BY o.OrderID DESC
";

$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - BUP Books</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        :root {
            --bup-blue: #0A3143;
            --bup-orange: #FF914D;
        }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
        }

        header {
            background: var(--bup-blue);
            padding: 15px 5%;
            color: white;
        }

        .top-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            color: var(--bup-orange);
        }

        .search-bar {
            flex: 1;
            max-width: 400px;
            display: flex;
            align-items: center;
            background: white;
            border-radius: 30px;
            padding: 5px 15px;
        }

        .search-bar span {
            color: #999;
        }

        .search-bar input {
            border: none;
            padding: 8px;
            width: 100%;
            outline: none;
        }

        .userlinks {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .userlinks a {
            color: white;
            text-decoration: none;
            position: relative;
        }

        .profile-dropdown {
            position: relative;
        }

        #profile-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 24px;
        }

        .dropdown-menu {
            position: absolute;
            right: 0;
            background: white;
            min-width: 150px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            display: none;
            z-index: 1000;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-menu a {
            color: #333;
            padding: 10px 15px;
            display: block;
            text-decoration: none;
        }

        .dropdown-menu a:hover {
            background: #f5f5f5;
        }

        .menu {
            margin-top: 15px;
            display: flex;
            gap: 25px;
        }

        .menu a {
            color: white;
            text-decoration: none;
            font-weight: 500;
        }

        .menu a:hover {
            color: var(--bup-orange);
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        h2 {
            color: var(--bup-blue);
            margin-bottom: 25px;
            font-weight: 700;
        }

        table {
            width: 100%;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-collapse: collapse;
        }

        th {
            background: var(--bup-blue);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e1e1e1;
            vertical-align: middle;
        }

        .product-box {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .product-box img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-Pending {
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange);
        }

        .status-Processing {
            background: rgba(0,123,255,0.15);
            color: #007bff;
        }

        .status-Shipped {
            background: rgba(23,162,184,0.15);
            color: #17a2b8;
        }

        .status-Completed {
            background: rgba(40,167,69,0.15);
            color: #28a745;
        }

        .status-Cancelled {
            background: rgba(220,53,69,0.15);
            color: #dc3545;
        }

        .action-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 20px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
            margin: 2px;
        }

        .btn-cancel {
            background: #dc3545;
            color: white;
        }

        .btn-received {
            background: #28a745;
            color: white;
        }

        .btn-invoice {
            background: var(--bup-blue);
            color: white;
        }

        .btn-view {
            background: #6c757d;
            color: white;
        }

        .action-group {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .empty-orders {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 15px;
        }

        .empty-orders i {
            font-size: 60px;
            color: #ccc;
            margin-bottom: 20px;
        }

        .btn-shop {
            background: var(--bup-orange);
            color: white;
            padding: 12px 30px;
            border-radius: 30px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            table {
                font-size: 14px;
            }
            
            td, th {
                padding: 10px;
            }
            
            .product-box {
                flex-direction: column;
                text-align: center;
            }
            
            .action-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="top-header">
        <div class="logo">BUP BOOKS</div>

        <div class="search-bar">
            <span class="material-symbols-outlined">search</span>
            <input type="text" placeholder="Search for books...">
        </div>

        <div class="userlinks">
            <a href="cart.php">
                <span class="material-symbols-outlined">shopping_cart</span>
            </a>

            <a href="orderlist.php">
                <span class="material-symbols-outlined">local_shipping</span>
            </a>

            <div class="profile-dropdown">
                <button id="profile-btn">
                    <span class="material-symbols-outlined">account_circle</span>
                </button>
                <div class="dropdown-menu" id="dropdown-menu">
                    <a href="profile.php">Edit Profile</a>
                    <a href="orderlist.php">My Orders</a>
                    <a href="settings.php">Settings</a>
                    <a href="../logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <nav class="menu">
        <a href="index.php">Home</a>
        <a href="browse-books.php">Books</a>
        <a href="contact.php">Contact</a>
    </nav>
</header>

<div class="container">
    <h2>?? My Orders</h2>

    <?php if (mysqli_num_rows($result) > 0): ?>
    <table>
        <tr>
            <th>PRODUCT</th>
            <th>PRICE</th>
            <th>QTY</th>
            <th>TOTAL</th>
            <th>STATUS</th>
            <th>PAYMENT</th>
            <th>ACTION</th>
        </tr>

        <?php 
        $current_order_id = null;
        while ($order = mysqli_fetch_assoc($result)): 
        ?>
        <tr>
            <td>
                <div class="product-box">
                    <img src="../assets/img/<?php echo $order['image'] ?: 'default-book.jpg'; ?>" alt="Book">
                    <div>
                        <b><?php echo htmlspecialchars($order['ProductName']); ?></b><br>
                        <small>Order #<?php echo $order['OrderID']; ?></small>
                    </div>
                </div>
            </td>
            <td>?<?php echo number_format($order['Price'], 2); ?></td>
            <td><?php echo $order['Quantity']; ?></td>
            <td>?<?php echo number_format($order['ItemTotal'], 2); ?></td>
            <td>
                <span class="status-badge status-<?php echo $order['Status']; ?>">
                    <?php 
                    if($order['Status'] == 'Pending') echo '? Pending';
                    elseif($order['Status'] == 'Processing') echo '?? Processing';
                    elseif($order['Status'] == 'Shipped') echo '?? Shipped';
                    elseif($order['Status'] == 'Completed') echo '? Completed';
                    elseif($order['Status'] == 'Cancelled') echo '? Cancelled';
                    else echo $order['Status'];
                    ?>
                </span>
            </td>
            <td>
                <span class="badge <?php echo $order['PaymentStatus'] == 'paid' ? 'bg-success' : 'bg-warning'; ?>">
                    <?php echo ucfirst($order['PaymentStatus']); ?>
                </span>
            </td>
            <td>
                <div class="action-group">
                    <?php if ($order['Status'] == 'Pending'): ?>
                        <a href="cancel.php?id=<?php echo $order['OrderID']; ?>" 
                           class="action-btn btn-cancel" 
                           onclick="return confirm('Cancel this order?')">
                            Cancel
                        </a>
                        <a href="complete.php?id=<?php echo $order['OrderID']; ?>" 
                           class="action-btn btn-received"
                           onclick="return confirm('Mark as received?')">
                            Received
                        </a>
                    <?php elseif ($order['Status'] == 'Shipped'): ?>
                        <a href="complete.php?id=<?php echo $order['OrderID']; ?>" 
                           class="action-btn btn-received"
                           onclick="return confirm('Mark as received?')">
                            Received
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($order['Status'] == 'Completed' || $order['Status'] == 'Cancelled'): ?>
                        <a href="view_invoice.php?order_id=<?php echo $order['OrderID']; ?>" 
                           class="action-btn btn-invoice">
                            Invoice
                        </a>
                    <?php endif; ?>
                    
                    <a href="order-details.php?id=<?php echo $order['OrderID']; ?>" 
                       class="action-btn btn-view">
                        View
                    </a>
                </div>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php else: ?>
    <div class="empty-orders">
        <span class="material-symbols-outlined" style="font-size: 60px;">shopping_cart</span>
        <h3>No orders yet</h3>
        <p>Looks like you haven't placed any orders.</p>
        <a href="browse-books.php" class="btn-shop">Browse Books</a>
    </div>
    <?php endif; ?>
</div>

<script>
    // Profile dropdown
    const profileBtn = document.getElementById('profile-btn');
    const dropdownMenu = document.getElementById('dropdown-menu');

    profileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
    });

    document.addEventListener('click', () => {
        dropdownMenu.classList.remove('show');
    });
</script>

</body>
</html>
<?php mysqli_close($conn); ?>