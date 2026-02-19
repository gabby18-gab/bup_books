<?php
session_start();

// Log the logout if admin was logged in
if (isset($_SESSION['admin_id'])) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=bup_books", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $logStmt = $pdo->prepare("INSERT INTO admin_logs (AdminID, Action, Details, IPAddress) VALUES (?, 'logout', 'Admin logged out', ?)");
        $logStmt->execute([$_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        // Silently fail - don't interrupt logout
    }
}

session_unset();
session_destroy();
header("Location: ../index.php");
exit();
?>