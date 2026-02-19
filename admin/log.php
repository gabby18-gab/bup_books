<?php
// admin/logs.php
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

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total logs count
$total = $pdo->query("SELECT COUNT(*) FROM admin_logs")->fetchColumn();
$total_pages = ceil($total / $limit);

// Get logs with admin info
$stmt = $pdo->prepare("
    SELECT al.*, a.Name as AdminName, a.Email as AdminEmail 
    FROM admin_logs al 
    JOIN admin a ON al.AdminID = a.AdminID 
    ORDER BY al.CreatedAt DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$limit, $offset]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get action summary
$action_summary = $pdo->query("
    SELECT Action, COUNT(*) as count 
    FROM admin_logs 
    GROUP BY Action 
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - BUP BOOKS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        /* Reuse styles */
        .log-entry {
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 13px;
        }
        
        .log-action {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .action-login { background: rgba(40,167,69,0.15); color: #28a745; }
        .action-logout { background: rgba(108,117,125,0.15); color: #6c757d; }
        .action-create { background: rgba(0,123,255,0.15); color: #007bff; }
        .action-update { background: rgba(255,193,7,0.15); color: #ffc107; }
        .action-delete { background: rgba(220,53,69,0.15); color: #dc3545; }
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
                <i class="bi bi-journal-text me-3" style="color: var(--bup-orange);"></i>Activity Logs
            </h1>
        </div>

        <div class="row">
            <!-- Action Summary -->
            <div class="col-md-3">
                <div class="content-card">
                    <h5 class="mb-3">Action Summary</h5>
                    <?php foreach ($action_summary as $action): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="log-action action-<?php echo $action['Action']; ?>">
                                <?php echo ucfirst($action['Action']); ?>
                            </span>
                            <span class="badge bg-secondary"><?php echo $action['count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between">
                        <span>Total Logs:</span>
                        <strong><?php echo $total; ?></strong>
                    </div>
                </div>
            </div>
            
            <!-- Logs Table -->
            <div class="col-md-9">
                <div class="content-card">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Admin</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr class="log-entry">
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['CreatedAt'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['AdminName']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($log['AdminEmail']); ?></small>
                                    </td>
                                    <td>
                                        <span class="log-action action-<?php echo $log['Action']; ?>">
                                            <?php echo ucfirst($log['Action']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['Details']); ?></td>
                                    <td><?php echo htmlspecialchars($log['IPAddress']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>