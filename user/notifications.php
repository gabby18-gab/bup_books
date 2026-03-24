<?php
// user/notifications.php
session_start();

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

// Handle mark as read
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET IsRead = 1 WHERE NotificationID = ? AND UserID = ?");
    $stmt->execute([$_GET['read'], $user_id]);
    header('Location: notifications.php');
    exit();
}

// Handle mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET IsRead = 1 WHERE UserID = ?");
    $stmt->execute([$user_id]);
    header('Location: notifications.php');
    exit();
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE NotificationID = ? AND UserID = ?");
    $stmt->execute([$_GET['delete'], $user_id]);
    header('Location: notifications.php');
    exit();
}

// Fetch all notifications for this user
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE UserID = ? ORDER BY CreatedAt DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts for header (if needed)
$unread_count = 0;
foreach ($notifications as $n) {
    if (!$n['IsRead']) $unread_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - BUP Book Resale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --bup-blue: #0A3143;
            --bup-orange: #FF914D;
            --bup-yellow: #FFC107;
            --bup-gray: #5A6C74;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            padding: 30px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            color: var(--bup-blue);
        }
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: var(--bup-gray);
            color: white;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-back:hover {
            background: var(--bup-orange);
            color: var(--bup-blue);
        }
        .btn-mark-all {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: var(--bup-orange);
            color: white;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-mark-all:hover {
            background: #e67a3a;
            color: white;
        }
        .notification-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-left: 5px solid transparent;
            transition: all 0.3s;
        }
        .notification-item.unread {
            border-left-color: var(--bup-orange);
            background: #fff9f5;
        }
        .notification-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--bup-blue);
        }
        .notification-message {
            color: var(--bup-gray);
            margin-bottom: 10px;
        }
        .notification-time {
            font-size: 12px;
            color: #999;
        }
        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .btn-action {
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-read {
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange);
        }
        .btn-delete {
            background: rgba(220,53,69,0.15);
            color: #dc3545;
        }
        .btn-link {
            background: rgba(10,49,67,0.1);
            color: var(--bup-blue);
        }
        .badge-unread {
            background: var(--bup-orange);
            color: white;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 14px;
        }
        @media (max-width: 600px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <a href="index.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back</a>
                <h1><i class="bi bi-bell" style="color: var(--bup-orange);"></i> Notifications</h1>
            </div>
            <?php if ($unread_count > 0): ?>
                <a href="?mark_all_read=1" class="btn-mark-all" onclick="return confirm('Mark all as read?')">
                    <i class="bi bi-check-all"></i> Mark All Read
                </a>
            <?php endif; ?>
        </div>

        <?php if (count($notifications) == 0): ?>
            <div class="text-center py-5">
                <i class="bi bi-bell-slash" style="font-size: 60px; color: #ccc;"></i>
                <h3 class="mt-3">No notifications</h3>
                <p class="text-muted">You're all caught up!</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-item <?php echo $notif['IsRead'] ? '' : 'unread'; ?>">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="notification-title">
                                <?php echo htmlspecialchars($notif['Title']); ?>
                                <?php if (!$notif['IsRead']): ?>
                                    <span class="badge bg-warning ms-2">New</span>
                                <?php endif; ?>
                            </div>
                            <div class="notification-message"><?php echo htmlspecialchars($notif['Message']); ?></div>
                            <div class="notification-time">
                                <i class="bi bi-clock"></i> <?php echo date('M d, Y h:i A', strtotime($notif['CreatedAt'])); ?>
                            </div>
                        </div>
                    </div>
                    <div class="notification-actions">
                        <?php if (!$notif['IsRead']): ?>
                            <a href="?read=<?php echo $notif['NotificationID']; ?>" class="btn-action btn-read">
                                <i class="bi bi-check"></i> Mark as read
                            </a>
                        <?php endif; ?>
                        <?php if (isset($notif['Link']) && !empty($notif['Link'])): ?>
                            <a href="<?php echo htmlspecialchars($notif['Link']); ?>" class="btn-action btn-link">
                                <i class="bi bi-eye"></i> View
                            </a>
                        <?php endif; ?>
                        <a href="?delete=<?php echo $notif['NotificationID']; ?>" class="btn-action btn-delete" onclick="return confirm('Delete this notification?')">
                            <i class="bi bi-trash"></i> Delete
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>