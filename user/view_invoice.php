<?php
// user/view_invoice.php
session_start();

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$database = "bup_books";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get order_id from URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$user_id = $_SESSION['user_id'];

// Fetch specific order for this user only
$invoice_query = "
    SELECT 
        o.OrderID, 
        u.Name as CustomerName, 
        u.Email, 
        u.Address, 
        u.ContactNo,
        s.Name as SellerName, 
        s.ContactInfo as SellerContact,
        p.ProductName, 
        p.Price, 
        od.Quantity,
        (p.Price * od.Quantity) as Subtotal,
        py.Amount, 
        py.PaymentMethod, 
        py.PaymentDate,
        o.OrderDate, 
        o.Status,
        o.TotalAmount
    FROM orders o
    JOIN users u ON o.UserID = u.UserID
    JOIN seller s ON o.SellerID = s.SellerID
    JOIN orderdetails od ON o.OrderID = od.OrderID
    JOIN product p ON od.ProductID = p.ProductID
    LEFT JOIN payment py ON o.OrderID = py.OrderID
    WHERE o.OrderID = $order_id AND o.UserID = $user_id
";

$result = mysqli_query($conn, $invoice_query);
$invoice = mysqli_fetch_assoc($result);

// Check if order exists and belongs to user
if (!$invoice) {
    die("Invoice not found or access denied.");
}

// Calculate totals
$subtotal = $invoice['Price'] * $invoice['Quantity'];
$total = $invoice['TotalAmount'] ?: $subtotal;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo str_pad($invoice['OrderID'], 6, '0', STR_PAD_LEFT); ?> - BUP Books</title>
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
            padding: 20px;
        }

        header {
            background: var(--bup-blue);
            padding: 15px 5%;
            color: white;
            margin-bottom: 30px;
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

        .invoice-wrapper {
            max-width: 800px;
            margin: 0 auto;
        }

        .invoice-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e1e1e1;
        }

        .company-info h2 {
            color: var(--bup-blue);
            font-weight: 800;
            margin-bottom: 10px;
        }

        .invoice-meta {
            text-align: right;
        }

        .invoice-meta h2 {
            color: var(--bup-orange);
            font-weight: 700;
        }

        .billing-sections {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .billing-section h4 {
            color: var(--bup-blue);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .invoice-table th {
            background: var(--bup-blue);
            color: white;
            padding: 12px;
            text-align: left;
        }

        .invoice-table td {
            padding: 12px;
            border-bottom: 1px solid #e1e1e1;
        }

        .totals-section {
            text-align: right;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .totals-section p {
            margin: 5px 0;
            font-size: 16px;
        }

        .grand-total {
            font-size: 20px;
            font-weight: 800;
            color: var(--bup-orange);
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px dashed #ccc;
        }

        .payment-info {
            background: #e8f4fd;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .button-container {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .print-btn, .back-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .print-btn {
            background: var(--bup-orange);
            color: white;
        }

        .back-btn {
            background: #6c757d;
            color: white;
        }

        .invoice-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e1e1;
            color: #999;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending { background: rgba(255,145,77,0.15); color: #FF914D; }
        .status-processing { background: rgba(0,123,255,0.15); color: #007bff; }
        .status-shipped { background: rgba(23,162,184,0.15); color: #17a2b8; }
        .status-completed { background: rgba(40,167,69,0.15); color: #28a745; }
        .status-cancelled { background: rgba(220,53,69,0.15); color: #dc3545; }

        @media print {
            header, .button-container {
                display: none;
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
            <a href="cart.php"><span class="material-symbols-outlined">shopping_cart</span></a>
            <a href="orderlist.php"><span class="material-symbols-outlined">local_shipping</span></a>
        </div>
    </div>
    <nav class="menu">
        <a href="index.php">Home</a>
        <a href="browse-books.php">Books</a>
        <a href="contact.php">Contact</a>
    </nav>
</header>

<div class="invoice-wrapper">
    <div class="invoice-card">
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="company-info">
                <h2>BUP BOOKS</h2>
                <p>Bangladesh University of Professionals<br>
                Mirpur Cantonment, Dhaka<br>
                support@bupbooks.edu.bd</p>
            </div>
            <div class="invoice-meta">
                <h2>INVOICE</h2>
                <p>#FF<?php echo str_pad($invoice['OrderID'], 6, '0', STR_PAD_LEFT); ?><br>
                Date: <?php echo date('M j, Y', strtotime($invoice['OrderDate'])); ?><br>
                Status: <span class="status-badge status-<?php echo strtolower($invoice['Status']); ?>">
                    <?php echo $invoice['Status']; ?>
                </span></p>
            </div>
        </div>

        <!-- Billing Information -->
        <div class="billing-sections">
            <div class="billing-section">
                <h4>Bill To:</h4>
                <p><strong><?php echo htmlspecialchars($invoice['CustomerName']); ?></strong><br>
                <?php echo htmlspecialchars($invoice['Email']); ?><br>
                <?php echo htmlspecialchars($invoice['ContactNo'] ?? 'N/A'); ?><br>
                <?php echo htmlspecialchars($invoice['Address'] ?: 'No address provided'); ?></p>
            </div>
            
            <div class="billing-section">
                <h4>From:</h4>
                <p><strong><?php echo htmlspecialchars($invoice['SellerName']); ?></strong><br>
                <?php echo htmlspecialchars($invoice['SellerContact']); ?></p>
            </div>
        </div>

        <!-- Invoice Table -->
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($invoice['ProductName']); ?></td>
                    <td><?php echo $invoice['Quantity']; ?></td>
                    <td>?<?php echo number_format($invoice['Price'], 2); ?></td>
                    <td>?<?php echo number_format($subtotal, 2); ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-section">
            <p>Subtotal: ?<?php echo number_format($subtotal, 2); ?></p>
            <p>Shipping: ?0.00</p>
            <p class="grand-total">Total: ?<?php echo number_format($total, 2); ?></p>
        </div>

        <!-- Payment Info -->
        <div class="payment-info">
            <p><strong>Payment Method:</strong> 
                <?php 
                if (isset($invoice['PaymentMethod'])) {
                    echo htmlspecialchars($invoice['PaymentMethod']);
                } else {
                    echo 'Not specified';
                }
                ?>
                <?php if (isset($invoice['PaymentDate'])): ?>
                <br><strong>Paid on:</strong> <?php echo date('M j, Y', strtotime($invoice['PaymentDate'])); ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- Buttons -->
        <div class="button-container">
            <button class="print-btn" onclick="window.print()">
                <span class="material-symbols-outlined">print</span>
                Print Invoice
            </button>
            <a href="orderlist.php" class="back-btn">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to Orders
            </a>
        </div>

        <!-- Footer Note -->
        <div class="invoice-footer">
            <p>Thank you for shopping at BUP Books!</p>
        </div>
    </div>
</div>

</body>
</html>
<?php mysqli_close($conn); ?>