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

$message = '';
$error = '';

// Handle user update from edit modal
if (isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $contact = trim($_POST['contact']);
    $status = $_POST['status'];

    try {
        $stmt = $pdo->prepare("UPDATE users SET Name = ?, Email = ?, Address = ?, ContactNo = ?, Status = ? WHERE UserID = ?");
        if ($stmt->execute([$name, $email, $address, $contact, $status, $user_id])) {
            $message = "User updated successfully!";
            // Log action
            $log = $pdo->prepare("INSERT INTO admin_logs (AdminID, Action, Details, IPAddress) VALUES (?, 'update', ?, ?)");
            $log->execute([$admin_id, "Updated user ID: $user_id", $_SERVER['REMOTE_ADDR']]);
        } else {
            $error = "Failed to update user.";
        }
    } catch (PDOException $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// Delete user (super admin only)
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

        /* Stats Cards */
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-mini-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--shadow-sm);
            border-left: 5px solid var(--bup-orange);
        }

        .stat-mini-card h6 {
            color: var(--bup-gray);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .stat-mini-card h2 {
            font-size: 32px;
            font-weight: 800;
            margin: 0;
        }

        /* Content Card */
        .content-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
            border: 1px solid var(--bup-gray-light);
        }

        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
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
            text-decoration: none;
        }

        .btn-add:hover {
            background: var(--bup-orange-dark);
            color: white;
        }

        /* Table */
        .table {
            margin-bottom: 0;
        }

        .table thead th {
            border-bottom: 2px solid var(--bup-gray-light);
            color: var(--bup-gray);
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px 12px;
        }

        .table tbody td {
            padding: 15px 12px;
            vertical-align: middle;
            border-bottom: 1px solid var(--bup-gray-light);
        }

        .user-avatar-sm {
            width: 40px;
            height: 40px;
            background: var(--bup-gradient-accent);
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

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-active {
            background: rgba(40,167,69,0.15);
            color: #28a745;
        }

        .status-pending {
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange-dark);
        }

        .action-buttons {
            display: flex;
            gap: 5px;
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
            border: none;
            background: transparent;
        }

        .btn-icon:hover {
            transform: translateY(-2px);
        }

        .btn-view {
            background: rgba(10,49,67,0.1);
            color: var(--bup-blue);
        }

        .btn-edit {
            background: rgba(255,145,77,0.15);
            color: var(--bup-orange-dark);
        }

        .btn-delete {
            background: rgba(220,53,69,0.15);
            color: #dc3545;
        }

        .btn-toggle {
            background: rgba(255,193,7,0.15);
            color: #ffc107;
        }

        .alert {
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
        }

        /* Modal */
        .modal-header {
            background: var(--bup-blue);
            color: white;
            border-radius: 20px 20px 0 0;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .btn-save {
            background: var(--bup-gradient-accent);
            color: var(--bup-blue);
            font-weight: 700;
            border: none;
            border-radius: 12px;
            padding: 10px 25px;
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

        @media (max-width: 768px) {
            .stats-mini {
                grid-template-columns: 1fr;
            }
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
                <a href="users.php" class="nav-link active">
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
                <a href="pending_products.php" class="nav-link">
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
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 style="font-size: 28px; font-weight: 800; color: var(--bup-blue);">
                <i class="bi bi-people me-3" style="color: var(--bup-orange);"></i>Users Management
            </h1>
            <a href="add_user.php" class="btn-add">
                <i class="bi bi-person-plus"></i> Add New User
            </a>
        </div>

        <!-- Mini Stats -->
        <div class="stats-mini">
            <div class="stat-mini-card">
                <h6>Total Users</h6>
                <h2 style="color: var(--bup-blue);"><?php echo $total_users; ?></h2>
            </div>
            <div class="stat-mini-card">
                <h6>Active Users</h6>
                <h2 style="color: #28a745;"><?php echo $active_users; ?></h2>
            </div>
            <div class="stat-mini-card">
                <h6>Inactive Users</h6>
                <h2 style="color: #dc3545;"><?php echo $inactive_users; ?></h2>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
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
                <a href="users.php" class="btn btn-secondary">Reset</a>
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
                                        <!-- View button triggers modal -->
                                        <button class="btn-icon btn-view" onclick='viewUser(<?php echo json_encode($user); ?>)' data-bs-toggle="modal" data-bs-target="#viewUserModal">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <!-- Edit button triggers modal -->
                                        <button class="btn-icon btn-edit" onclick='editUser(<?php echo json_encode($user); ?>)' data-bs-toggle="modal" data-bs-target="#editUserModal">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="users.php?toggle=<?php echo $user['UserID']; ?>" class="btn-icon btn-toggle" title="Toggle Status">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </a>
                                        <?php if ($admin_role == 'super_admin'): ?>
                                        <a href="users.php?delete=<?php echo $user['UserID']; ?>" class="btn-icon btn-delete" onclick="return confirm('Are you sure you want to delete this user?')">
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

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-eye me-2"></i>User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewUserContent">
                    <!-- Filled by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact" id="edit_contact" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="A">Active</option>
                                <option value="I">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_user" class="btn btn-save">Update User</button>
                    </div>
                </form>
            </div>
        </div>
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

        // View User Modal
        function viewUser(user) {
            const content = `
                <p><strong>ID:</strong> ${user.UserID}</p>
                <p><strong>Name:</strong> ${escapeHtml(user.Name)}</p>
                <p><strong>Email:</strong> ${escapeHtml(user.Email)}</p>
                <p><strong>Address:</strong> ${user.Address ? escapeHtml(user.Address) : 'Not provided'}</p>
                <p><strong>Contact:</strong> ${user.ContactNo ? escapeHtml(user.ContactNo) : 'Not provided'}</p>
                <p><strong>Joined:</strong> ${new Date(user.CreatedAt).toLocaleDateString()}</p>
                <p><strong>Status:</strong> ${user.Status == 'A' ? 'Active' : 'Inactive'}</p>
            `;
            document.getElementById('viewUserContent').innerHTML = content;
        }

        // Edit User Modal - populate fields
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.UserID;
            document.getElementById('edit_name').value = user.Name;
            document.getElementById('edit_email').value = user.Email;
            document.getElementById('edit_address').value = user.Address || '';
            document.getElementById('edit_contact').value = user.ContactNo || '';
            document.getElementById('edit_status').value = user.Status;
        }

        // Simple escape function to prevent XSS
        function escapeHtml(unsafe) {
            return unsafe.replace(/[&<>"']/g, function(m) {
                if(m === '&') return '&amp;';
                if(m === '<') return '&lt;';
                if(m === '>') return '&gt;';
                if(m === '"') return '&quot;';
                if(m === "'") return '&#039;';
                return m;
            });
        }

        // Auto-hide alerts
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>