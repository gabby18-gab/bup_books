<?php
// admin/users.php
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
$admin_name = $_SESSION['admin_name'];
$admin_role = $_SESSION['admin_role'];

// Handle actions
$message = '';
$error = '';

// Delete user
if (isset($_GET['delete']) && $admin_role == 'super_admin') {
    $user_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE UserID = ?");
        $stmt->execute([$user_id]);
        $message = "User deleted successfully!";
    } catch (PDOException $e) {
        $error = "Cannot delete user: " . $e->getMessage();
    }
}

// Toggle user status
if (isset($_GET['toggle'])) {
    $user_id = $_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE users SET Status = IF(Status = 'A', 'I', 'A') WHERE UserID = ?");
    $stmt->execute([$user_id]);
    $message = "User status updated!";
}

// Search and filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM seller WHERE UserID = u.UserID) as is_seller,
          (SELECT COUNT(*) FROM orders WHERE UserID = u.UserID) as order_count
          FROM users u WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (u.Name LIKE ? OR u.Email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter && in_array($status_filter, ['A', 'I'])) {
    $query .= " AND u.Status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY u.CreatedAt DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total counts
$total_users = count($users);
$active_users = $pdo->query("SELECT COUNT(*) FROM users WHERE Status = 'A'")->fetchColumn();
$inactive_users = $pdo->query("SELECT COUNT(*) FROM users WHERE Status = 'I'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - BUP BOOKS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bup-blue: #0A3143;
            --bup-blue-light: #1C4E6C;
            --bup-orange: #FF914D;
            --bup-yellow: #FFC107;
            --bup-gray-light: #E1E9F0;
            --bup-offwhite: #F8FAFC;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bup-offwhite);
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(145deg, #0A3143, #1C4E6C);
            color: white;
            padding: 30px 20px;
            overflow-y: auto;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
        }

        /* Reuse sidebar styles from dashboard.php */
        /* ... (copy sidebar styles from dashboard.php) ... */

        .content-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-mini-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border-left: 5px solid var(--bup-orange);
        }

        .filter-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--bup-gray);
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid var(--bup-gray-light);
            border-radius: 30px;
            font-size: 15px;
        }

        .filter-select {
            padding: 12px 25px;
            border: 2px solid var(--bup-gray-light);
            border-radius: 30px;
            font-size: 15px;
            min-width: 150px;
        }

        .btn-add {
            background: var(--bup-orange);
            color: white;
            border: none;
            border-radius: 30px;
            padding: 12px 30px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .user-avatar-sm {
            width: 40px;
            height: 40px;
            background: linear-gradient(145deg, var(--bup-orange), var(--bup-yellow));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--bup-blue);
            font-weight: 700;
        }

        .badge-seller {
            background: rgba(40,167,69,0.15);
            color: #28a745;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-icon:hover {
            transform: translateY(-2px);
        }

        .alert {
            border-radius: 16px;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
    </style>
</head>
<body>
    <!-- Sidebar (copy from dashboard.php) -->
    <div class="sidebar">
        <!-- ... sidebar content from dashboard.php ... -->
    </div>

    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 style="font-size: 28px; font-weight: 800; color: var(--bup-blue);">
                <i class="bi bi-people me-3" style="color: var(--bup-orange);"></i>Users Management
            </h1>
            <a href="users.php?action=add" class="btn-add">
                <i class="bi bi-person-plus"></i> Add New User
            </a>
        </div>

        <!-- Mini Stats -->
        <div class="stats-mini">
            <div class="stat-mini-card">
                <h6 style="color: #6B7F8B; font-size: 14px;">Total Users</h6>
                <h2 style="font-size: 32px; font-weight: 800; color: var(--bup-blue);"><?php echo $total_users; ?></h2>
            </div>
            <div class="stat-mini-card">
                <h6 style="color: #6B7F8B; font-size: 14px;">Active Users</h6>
                <h2 style="font-size: 32px; font-weight: 800; color: #28a745;"><?php echo $active_users; ?></h2>
            </div>
            <div class="stat-mini-card">
                <h6 style="color: #6B7F8B; font-size: 14px;">Inactive Users</h6>
                <h2 style="font-size: 32px; font-weight: 800; color: #dc3545;"><?php echo $inactive_users; ?></h2>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="content-card">
            <form method="GET" class="filter-bar">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="A" <?php echo $status_filter == 'A' ? 'selected' : ''; ?>>Active</option>
                    <option value="I" <?php echo $status_filter == 'I' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <button type="submit" class="btn" style="background: var(--bup-blue); color: white; border-radius: 30px; padding: 12px 30px;">
                    <i class="bi bi-funnel me-2"></i>Apply Filter
                </button>
                <a href="users.php" class="btn" style="background: var(--bup-gray-light); color: var(--bup-blue); border-radius: 30px; padding: 12px 30px;">
                    <i class="bi bi-arrow-repeat me-2"></i>Reset
                </a>
            </form>
        </div>

        <!-- Users Table -->
        <div class="content-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Joined</th>
                            <th>Status</th>
                            <th>Orders</th>
                            <th>Seller</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar-sm me-3">
                                            <?php echo strtoupper(substr($user['Name'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['Name']); ?></strong>
                                            <br>
                                            <small class="text-muted">ID: #<?php echo $user['UserID']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['Email']); ?></td>
                                <td><?php echo $user['ContactNo'] ?: 'Not provided'; ?></td>
                                <td><?php echo date('M d, Y', strtotime($user['CreatedAt'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $user['Status'] == 'A' ? 'status-active' : 'status-pending'; ?>">
                                        <?php echo $user['Status'] == 'A' ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo $user['order_count']; ?></td>
                                <td>
                                    <?php if ($user['is_seller']): ?>
                                        <span class="badge-seller"><i class="bi bi-check-circle"></i> Seller</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="users.php?view=<?php echo $user['UserID']; ?>" class="btn-icon btn-view" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="users.php?edit=<?php echo $user['UserID']; ?>" class="btn-icon btn-edit" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="users.php?toggle=<?php echo $user['UserID']; ?>" class="btn-icon" style="background: rgba(255,193,7,0.15); color: #FFC107;" title="Toggle Status">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </a>
                                        <?php if ($admin_role == 'super_admin'): ?>
                                        <a href="users.php?delete=<?php echo $user['UserID']; ?>" class="btn-icon" style="background: rgba(220,53,69,0.15); color: #dc3545;" title="Delete" onclick="return confirm('Are you sure you want to delete this user?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-people" style="font-size: 48px; color: var(--bup-gray-light);"></i>
                                    <h5 class="mt-3 text-muted">No users found</h5>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Copy the sidebar toggle and loading spinner functions from dashboard.php
    </script>
</body>
</html>