<?php
// user/cancel.php
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

// Get order ID from URL
if (isset($_GET['id'])) {
    $orderID = mysqli_real_escape_string($conn, $_GET['id']);
    $userID = $_SESSION['user_id'];
    
    // First, check if order exists and belongs to user
    $check_sql = "SELECT Status FROM orders WHERE OrderID = '$orderID' AND UserID = '$userID'";
    $result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($result) > 0) {
        $order = mysqli_fetch_assoc($result);
        
        // Only cancel if order is still pending
        if ($order['Status'] == 'Pending') {
            
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Restore product stock quantities
                $restore_stock_sql = "
                    UPDATE product p 
                    JOIN orderdetails od ON p.ProductID = od.ProductID 
                    SET p.StockQuantity = p.StockQuantity + od.Quantity 
                    WHERE od.OrderID = '$orderID'
                ";
                mysqli_query($conn, $restore_stock_sql);
                
                // Update order status to 'Cancelled'
                $update_sql = "UPDATE orders SET Status = 'Cancelled' WHERE OrderID = '$orderID'";
                mysqli_query($conn, $update_sql);
                
                // Commit transaction
                mysqli_commit($conn);
                
                // Redirect with success message
                header("Location: orderlist.php?message=Order+cancelled+successfully");
                exit;
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                header("Location: orderlist.php?error=Failed+to+cancel+order");
                exit;
            }
            
        } else {
            header("Location: orderlist.php?error=Cannot+cancel+order+that+is+already+" . $order['Status']);
            exit;
        }
    } else {
        header("Location: orderlist.php?error=Order+not+found");
        exit;
    }
} else {
    header("Location: orderlist.php?error=No+order+ID+provided");
    exit;
}

mysqli_close($conn);
?>