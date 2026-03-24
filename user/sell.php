<?php
// user/sell.php
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
    error_log("Sell book DB connection error: " . $e->getMessage());
    die("Database connection error");
}

$user_id = $_SESSION['user_id'];

// Check if user is a seller
$stmt = $pdo->prepare("SELECT SellerID FROM seller WHERE UserID = ? AND Status = 'A'");
$stmt->execute([$user_id]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seller) {
    header('Location: become-seller.php');
    exit();
}

$seller_id = $seller['SellerID'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_title = trim($_POST['book_title'] ?? '');
    $category   = trim($_POST['category'] ?? '');
    $price      = floatval($_POST['price'] ?? 0);
    $stock      = intval($_POST['stock'] ?? 1);

    // Validate required fields
    if (empty($book_title) || empty($category) || $price <= 0 || $stock <= 0) {
        $error = 'Please fill in all required fields with valid values.';
    } else {
        // Handle image upload
        $image_path = '';
        if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = $_FILES['book_image']['type'];
            if (in_array($file_type, $allowed)) {
                $ext = pathinfo($_FILES['book_image']['name'], PATHINFO_EXTENSION);
                $filename = 'book_' . $seller_id . '_' . time() . '.' . $ext;
                $upload_dir = '../uploads/products/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                if (move_uploaded_file($_FILES['book_image']['tmp_name'], $upload_dir . $filename)) {
                    $image_path = 'uploads/products/' . $filename;
                } else {
                    $error = 'Failed to upload image.';
                }
            } else {
                $error = 'Invalid image format. Only JPG, PNG, GIF, WEBP allowed.';
            }
        } else {
            $error = 'Please select a book cover image.';
        }

        if (empty($error)) {
            try {
                // Insert product with Status = 'P' (pending approval)
                $stmt = $pdo->prepare("
                    INSERT INTO product 
                        (ProductName, Category, Price, StockQuantity, image, SellerID, Status)
                    VALUES 
                        (?, ?, ?, ?, ?, ?, 'P')
                ");
                $stmt->execute([
                    $book_title,
                    $category,
                    $price,
                    $stock,
                    $image_path,
                    $seller_id
                ]);

                $success = 'Your book has been submitted for admin review. You will be notified once approved.';
                // Redirect after a short delay
                header('refresh:2;url=my-books.php');
            } catch (PDOException $e) {
                error_log("Sell book insert error: " . $e->getMessage());
                $error = 'Failed to list book. Please try again.';
            }
        }
    }
}

// Fetch existing categories for the datalist (optional)
$categories = $pdo->query("SELECT DISTINCT Category FROM product WHERE Category IS NOT NULL ORDER BY Category")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell a Book - BUP Book Resale</title>
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
        .container { max-width: 800px; }
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
        .card-header h2 { font-weight: 800; margin-bottom: 5px; }
        .card-body { padding: 40px; }
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
        .form-label { font-weight: 600; color: var(--bup-blue); }
        .form-control, .form-select {
            border: 2px solid #e1e1e1;
            border-radius: 12px;
            padding: 12px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--bup-orange);
            box-shadow: none;
        }
        .alert { border-radius: 12px; }
        .image-preview {
            max-width: 150px;
            max-height: 150px;
            margin-top: 10px;
            border-radius: 10px;
            border: 2px dashed var(--bup-orange);
            padding: 5px;
        }
        .note {
            background: #f0f7ff;
            border-left: 5px solid var(--bup-orange);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2><i class="bi bi-plus-circle me-2"></i>Sell a Book</h2>
                <p class="mb-0">List your book for sale</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <div class="note">
                    <i class="bi bi-info-circle me-2"></i>
                    Fill in the details below. Books with clear photos sell faster.
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Book Title <span class="text-danger">*</span></label>
                        <input type="text" name="book_title" class="form-control" required value="<?php echo htmlspecialchars($_POST['book_title'] ?? ''); ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                <input type="text" name="category" class="form-control" list="categoryList" required value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>">
                                <datalist id="categoryList">
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Price ($) <span class="text-danger">*</span></label>
                                <input type="number" name="price" class="form-control" step="0.01" min="0.01" required value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="stock" class="form-control" min="1" value="1" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Book Cover Image <span class="text-danger">*</span></label>
                        <input type="file" name="book_image" class="form-control" accept="image/*" required onchange="previewImage(this)">
                        <img id="imagePreview" class="image-preview" src="#" alt="Preview" style="display: none;">
                        <small class="text-muted">Max size: 2MB. Allowed: JPG, PNG, GIF, WEBP</small>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-cloud-upload me-2"></i>List Book for Sale
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
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