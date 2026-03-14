<?php
// user/complete.php
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

// Check if order ID is provided
if (isset($_GET['id'])) {
    $order_id = $_GET['id'];
    $user_id = $_SESSION['user_id'];
    
    // Verify the order belongs to the logged-in user
    $verify_sql = "SELECT OrderID FROM orders WHERE OrderID = ? AND UserID = ?";
    $stmt = mysqli_prepare($conn, $verify_sql);
    mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
    mysqli_stmt_execute($stmt);
    $verify_result = mysqli_stmt_get_result($stmt);
    
    // Only proceed if user owns this order
    if (mysqli_num_rows($verify_result) > 0) {
        // Update order status to Completed
        $update_sql = "UPDATE orders SET Status = 'Completed', DeliveredAt = NOW() WHERE OrderID = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        
        header("Location: orderlist.php?message=Order+marked+as+received");
        exit();
    } else {
        header("Location: orderlist.php?error=Order+not+found");
        exit();
    }
}

header("Location: orderlist.php");
exit();
?>