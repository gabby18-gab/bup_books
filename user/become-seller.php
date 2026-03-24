<?php
// user/become-seller.php
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
    error_log("Become seller DB error: " . $e->getMessage());
    die("Database connection error");
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Check if user already has a seller account
$stmt = $pdo->prepare("SELECT SellerID FROM seller WHERE UserID = ?");
$stmt->execute([$user_id]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    // Already a seller, redirect to sell page
    $_SESSION['seller_id'] = $existing['SellerID'];
    header('Location: sell.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $store_name = trim($_POST['store_name'] ?? '');
    $contact_info = trim($_POST['contact_info'] ?? '');
    $pricing_policy = $_POST['pricing_policy'] ?? 'Negotiable';

    // Basic validation
    if (empty($store_name) || empty($contact_info)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            // Handle logo upload
            $logo_path = 'default-logo.png'; // default
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['image/jpeg', 'image/png', 'image/gif'];
                $file_type = $_FILES['logo']['type'];
                if (in_array($file_type, $allowed)) {
                    $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                    $filename = 'seller_' . $user_id . '_' . time() . '.' . $ext;
                    $upload_dir = '../uploads/sellers/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $filename)) {
                        $logo_path = 'uploads/sellers/' . $filename;
                    }
                }
            }

            // Insert seller record
            $stmt = $pdo->prepare("
                INSERT INTO seller (Name, ContactInfo, AssignPrice, logo, UserID, Status, CreatedAt)
                VALUES (?, ?, ?, ?, ?, 'A', NOW())
            ");
            $stmt->execute([$store_name, $contact_info, $pricing_policy, $logo_path, $user_id]);

            $seller_id = $pdo->lastInsertId();
            $_SESSION['seller_id'] = $seller_id; // Store in session

            $success = 'Congratulations! You are now a seller. You can start listing your books.';
            // Redirect after a short delay
            header('refresh:2;url=sell.php');
        } catch (PDOException $e) {
            error_log("Become seller insert error: " . $e->getMessage());
            $error = 'Failed to create seller account. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Seller - BUP Book Resale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --bup-blue: #0A3143;
            --bup-orange: #FF914D;
            --bup-yellow: #FFC107;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            padding: 40px 0;
        }
        .container {
            max-width: 700px;
        }
        .card {
            border-radius: 20px;
            border: none;
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card-header {
            background: var(--bup-blue);
            color: white;
            padding: 30px;
            text-align: center;
            border-bottom: 5px solid var(--bup-orange);
        }
        .card-header h2 {
            font-weight: 800;
            margin-bottom: 5px;
        }
        .card-body {
            padding: 40px;
        }
        .btn-primary {
            background: linear-gradient(145deg, var(--bup-orange), var(--bup-yellow));
            border: none;
            color: var(--bup-blue);
            font-weight: 700;
            padding: 12px 30px;
            border-radius: 50px;
            box-shadow: 0 5px 0 #C7511E;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(3px);
            box-shadow: 0 2px 0 #C7511E;
        }
        .form-label {
            font-weight: 600;
            color: var(--bup-blue);
        }
        .form-control, .form-select {
            border: 2px solid #e1e1e1;
            border-radius: 12px;
            padding: 12px;
        }
        .form-control:focus {
            border-color: var(--bup-orange);
            box-shadow: none;
        }
        .alert {
            border-radius: 12px;
        }
        .logo-preview {
            max-width: 100px;
            max-height: 100px;
            margin-top: 10px;
            border-radius: 10px;
            border: 2px dashed var(--bup-orange);
            padding: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2><i class="bi bi-shop me-2"></i>Become a Seller</h2>
                <p class="mb-0">Start selling your books to the BUP community</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Store Name <span class="text-danger">*</span></label>
                        <input type="text" name="store_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['store_name'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Information <span class="text-danger">*</span></label>
                        <input type="text" name="contact_info" class="form-control" placeholder="Phone number, email, or social media" required value="<?php echo htmlspecialchars($_POST['contact_info'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pricing Policy</label>
                        <select name="pricing_policy" class="form-select">
                            <option value="Negotiable" selected>Negotiable</option>
                            <option value="Fixed">Fixed Price</option>
                            <option value="Contact for price">Contact for price</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Store Logo (optional)</label>
                        <input type="file" name="logo" class="form-control" accept="image/*" onchange="previewLogo(this)">
                        <img id="logoPreview" class="logo-preview" src="#" alt="Logo preview" style="display: none;">
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle me-2"></i>Become a Seller
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewLogo(input) {
            const preview = document.getElementById('logoPreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>