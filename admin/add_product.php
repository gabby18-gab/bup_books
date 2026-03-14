<?php
// admin/add_product.php - Add New Product
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=bup_books", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get admin info
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

// Get all active sellers for dropdown
$stmt = $pdo->query("
    SELECT s.SellerID, s.Name as StoreName, u.Name as OwnerName 
    FROM seller s 
    JOIN users u ON s.UserID = u.UserID 
    WHERE s.Status = 'A' 
    ORDER BY s.Name
");
$sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories
$stmt = $pdo->query("SELECT DISTINCT Category FROM product WHERE Category IS NOT NULL ORDER BY Category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Add new product
        if ($_POST['action'] == 'add_product') {
            $product_name = trim($_POST['product_name']);
            $seller_id = $_POST['seller_id'];
            $category = trim($_POST['category']);
            $price = floatval($_POST['price']);
            $stock_quantity = intval($_POST['stock_quantity']);
            $description = trim($_POST['description']);
            $isbn = trim($_POST['isbn']);
            $author = trim($_POST['author']);
            $publisher = trim($_POST['publisher']);
            $publication_year = intval($_POST['publication_year']);
            $language = trim($_POST['language']);
            $format = $_POST['format'];
            $pages = intval($_POST['pages']);
            $status = $_POST['status'];

            // Handle image upload
            $image_path = '';
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['product_image']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $new_filename = uniqid() . '.' . $ext;
                    $upload_path = '../uploads/products/' . $new_filename;
                    
                    // Create directory if it doesn't exist
                    if (!is_dir('../uploads/products/')) {
                        mkdir('../uploads/products/', 0777, true);
                    }
                    
                    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                        $image_path = 'uploads/products/' . $new_filename;
                    }
                }
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO product (SellerID, ProductName, Category, Price, StockQuantity, 
                                       Description, ISBN, Author, Publisher, PublicationYear, 
                                       Language, Format, Pages, Image, Status, CreatedAt) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $seller_id, $product_name, $category, $price, $stock_quantity,
                    $description, $isbn, $author, $publisher, $publication_year,
                    $language, $format, $pages, $image_path, $status
                ]);
                
                $product_id = $pdo->lastInsertId();
                
                // Log the action
                $stmt = $pdo->prepare("INSERT INTO admin_logs (AdminID, Action, Details, CreatedAt) VALUES (?, 'create', ?, NOW())");
                $stmt->execute([$admin_id, "Added new product ID: $product_id - $product_name"]);
                
                $success_message = "Product added successfully!";
            } catch (PDOException $e) {
                $error_message = "Error adding product: " . $e->getMessage();
            }
        }
        
        // Add stock to existing product
        elseif ($_POST['action'] == 'add_stock') {
            $product_id = $_POST['product_id'];
            $quantity_to_add = intval($_POST['quantity_to_add']);
            $reason = trim($_POST['reason']);
            
            try {
                // Get current stock
                $stmt = $pdo->prepare("SELECT StockQuantity, ProductName FROM product WHERE ProductID = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    $new_quantity = $product['StockQuantity'] + $quantity_to_add;
                    
                    // Update stock
                    $stmt = $pdo->prepare("UPDATE product SET StockQuantity = ? WHERE ProductID = ?");
                    $stmt->execute([$new_quantity, $product_id]);
                    
                    // Record stock movement
                    $stmt = $pdo->prepare("
                        INSERT INTO stock_movements (ProductID, Quantity, Type, Reason, CreatedBy, CreatedAt) 
                        VALUES (?, ?, 'add', ?, ?, NOW())
                    ");
                    $stmt->execute([$product_id, $quantity_to_add, $reason, $admin_id]);
                    
                    // Log the action
                    $stmt = $pdo->prepare("INSERT INTO admin_logs (AdminID, Action, Details, CreatedAt) VALUES (?, 'update', ?, NOW())");
                    $stmt->execute([$admin_id, "Added $quantity_to_add units to product ID: $product_id - " . $product['ProductName']]);
                    
                    $success_message = "Stock added successfully! New quantity: $new_quantity";
                }
            } catch (PDOException $e) {
                $error_message = "Error adding stock: " . $e->getMessage();
            }
        }
    }
}

// Get products for stock addition dropdown
$stmt = $pdo->query("
    SELECT p.ProductID, p.ProductName, p.StockQuantity, s.Name as SellerName 
    FROM product p 
    JOIN seller s ON p.SellerID = s.SellerID 
    WHERE p.Status = 'A' 
    ORDER BY p.ProductName
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product / Stock - BUP BOOKS</title>
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Select2 for better dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
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
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bup-offwhite);
            color: var(--bup-blue);
        }

        .main-content {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--bup-blue);
        }

        .form-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border: 1px solid var(--bup-gray-light);
            margin-bottom: 30px;
        }

        .form-card h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--bup-gray-light);
        }

        .form-label {
            font-weight: 600;
            color: var(--bup-blue);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border: 2px solid var(--bup-gray-light);
            border-radius: 12px;
            padding: 10px 15px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--bup-orange);
            box-shadow: 0 0 0 0.2rem rgba(255,145,77,0.25);
        }

        .btn-primary {
            background: var(--bup-gradient-accent);
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
            color: var(--bup-blue);
        }

        .btn-secondary {
            background: var(--bup-gray-light);
            border: none;
            color: var(--bup-blue);
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 50px;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
        }

        .alert-success {
            background: rgba(40,167,69,0.1);
            color: #28a745;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: rgba(220,53,69,0.1);
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }

        .stock-badge {
            background: var(--bup-orange);
            color: white;
            padding: 5px 10px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }

        .preview-image {
            max-width: 100px;
            max-height: 100px;
            border-radius: 10px;
            border: 2px solid var(--bup-gray-light);
            margin-top: 10px;
        }

        /* Tab Navigation */
        .nav-tabs {
            border-bottom: 2px solid var(--bup-gray-light);
            margin-bottom: 30px;
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--bup-gray);
            font-weight: 600;
            padding: 12px 25px;
            border-radius: 0;
            margin-right: 10px;
        }

        .nav-tabs .nav-link:hover {
            color: var(--bup-orange);
        }

        .nav-tabs .nav-link.active {
            color: var(--bup-orange);
            border-bottom: 3px solid var(--bup-orange);
            background: transparent;
        }

        .breadcrumb {
            background: white;
            padding: 15px 25px;
            border-radius: 50px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .breadcrumb a {
            color: var(--bup-orange);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <!-- Breadcrumb -->
    <div class="main-content">
        <div class="breadcrumb">
            <a href="index.php"><i class="bi bi-house-door me-2"></i>Dashboard</a>
            <span class="mx-2">/</span>
            <span>Add Product / Stock</span>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="bi bi-plus-circle me-3" style="color: var(--bup-orange);"></i>Product Management</h1>
            <a href="products.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Products
            </a>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs" id="productTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="add-product-tab" data-bs-toggle="tab" data-bs-target="#add-product" type="button" role="tab">
                    <i class="bi bi-book me-2"></i>Add New Product
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="add-stock-tab" data-bs-toggle="tab" data-bs-target="#add-stock" type="button" role="tab">
                    <i class="bi bi-plus-square me-2"></i>Add Stock
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="productTabsContent">
            <!-- Add New Product Tab -->
            <div class="tab-pane fade show active" id="add-product" role="tabpanel">
                <div class="form-card">
                    <h2><i class="bi bi-book me-2" style="color: var(--bup-orange);"></i>Add New Book</h2>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_product">
                        
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-md-8">
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Book Title <span class="text-danger">*</span></label>
                                        <input type="text" name="product_name" class="form-control" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Author <span class="text-danger">*</span></label>
                                        <input type="text" name="author" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Publisher</label>
                                        <input type="text" name="publisher" class="form-control">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">ISBN</label>
                                        <input type="text" name="isbn" class="form-control" placeholder="978-3-16-148410-0">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Publication Year</label>
                                        <input type="number" name="publication_year" class="form-control" min="1900" max="2024">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Language</label>
                                        <input type="text" name="language" class="form-control" value="English">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Format</label>
                                        <select name="format" class="form-select">
                                            <option value="Paperback">Paperback</option>
                                            <option value="Hardcover">Hardcover</option>
                                            <option value="E-book">E-book</option>
                                            <option value="Audio">Audio Book</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Pages</label>
                                        <input type="number" name="pages" class="form-control" min="1">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Category <span class="text-danger">*</span></label>
                                        <select name="category" class="form-select" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                            <?php endforeach; ?>
                                            <option value="Other">Other (specify below)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Image Upload -->
                            <div class="col-md-4">
                                <div class="card p-3 text-center" style="background: var(--bup-offwhite); border: 2px dashed var(--bup-gray-light);">
                                    <label class="form-label">Book Cover Image</label>
                                    <img id="imagePreview" src="https://via.placeholder.com/150?text=Cover" class="preview-image mx-auto mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                                    <input type="file" name="product_image" class="form-control" accept="image/*" onchange="previewImage(this)">
                                    <small class="text-muted mt-2">Max size: 2MB (JPG, PNG, GIF)</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Seller and Price -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Seller <span class="text-danger">*</span></label>
                                <select name="seller_id" class="form-select" required>
                                    <option value="">Select Seller</option>
                                    <?php foreach ($sellers as $seller): ?>
                                    <option value="<?php echo $seller['SellerID']; ?>">
                                        <?php echo htmlspecialchars($seller['StoreName']); ?> (<?php echo htmlspecialchars($seller['OwnerName']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Price ($) <span class="text-danger">*</span></label>
                                <input type="number" name="price" class="form-control" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                                <input type="number" name="stock_quantity" class="form-control" min="0" value="1" required>
                            </div>
                        </div>
                        
                        <!-- Status -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="A">Active</option>
                                    <option value="I">Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4"></textarea>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="text-end">
                            <button type="reset" class="btn btn-secondary me-2">
                                <i class="bi bi-eraser me-2"></i>Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Add Product
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Add Stock Tab -->
            <div class="tab-pane fade" id="add-stock" role="tabpanel">
                <div class="form-card">
                    <h2><i class="bi bi-plus-square me-2" style="color: var(--bup-orange);"></i>Add Stock to Existing Product</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="add_stock">
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Select Product <span class="text-danger">*</span></label>
                                <select name="product_id" id="productSelect" class="form-select" required>
                                    <option value="">Choose a product...</option>
                                    <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['ProductID']; ?>" data-stock="<?php echo $product['StockQuantity']; ?>">
                                        <?php echo htmlspecialchars($product['ProductName']); ?> 
                                        (Seller: <?php echo htmlspecialchars($product['SellerName']); ?>) 
                                        - Current Stock: <?php echo $product['StockQuantity']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Current Stock</label>
                                <div class="form-control bg-light" id="currentStock" readonly>Select a product</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Quantity to Add <span class="text-danger">*</span></label>
                                <input type="number" name="quantity_to_add" class="form-control" min="1" value="1" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">New Stock After Addition</label>
                                <div class="form-control bg-light" id="newStock">-</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Adding Stock</label>
                            <select name="reason" class="form-select">
                                <option value="New shipment">New shipment received</option>
                                <option value="Restock">Regular restock</option>
                                <option value="Return">Customer return</option>
                                <option value="Correction">Inventory correction</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Add Stock
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Recent Stock Movements -->
                <div class="form-card mt-4">
                    <h2><i class="bi bi-clock-history me-2" style="color: var(--bup-orange);"></i>Recent Stock Movements</h2>
                    
                    <?php
                    // Get recent stock movements
                    $stmt = $pdo->query("
                        SELECT sm.*, p.ProductName, a.Name as AdminName
                        FROM stock_movements sm
                        JOIN product p ON sm.ProductID = p.ProductID
                        JOIN admin a ON sm.CreatedBy = a.AdminID
                        ORDER BY sm.CreatedAt DESC
                        LIMIT 10
                    ");
                    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if (count($movements) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Reason</th>
                                    <th>Added By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movements as $movement): ?>
                                <tr>
                                    <td><?php echo date('M d, Y h:i A', strtotime($movement['CreatedAt'])); ?></td>
                                    <td><?php echo htmlspecialchars($movement['ProductName']); ?></td>
                                    <td><span class="stock-badge">+<?php echo $movement['Quantity']; ?></span></td>
                                    <td><?php echo htmlspecialchars($movement['Reason']); ?></td>
                                    <td><?php echo htmlspecialchars($movement['AdminName']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center py-3">No stock movements yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery and Select2 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        // Initialize Select2
        $(document).ready(function() {
            $('#productSelect').select2({
                width: '100%',
                placeholder: 'Search for a product...'
            });
        });
        
        // Image preview
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Calculate new stock
        document.querySelector('[name="quantity_to_add"]').addEventListener('input', updateNewStock);
        document.getElementById('productSelect').addEventListener('change', updateNewStock);
        
        function updateNewStock() {
            const productSelect = document.getElementById('productSelect');
            const quantity = parseInt(document.querySelector('[name="quantity_to_add"]').value) || 0;
            
            if (productSelect.selectedIndex > 0) {
                const selectedOption = productSelect.options[productSelect.selectedIndex];
                const currentStock = parseInt(selectedOption.dataset.stock) || 0;
                
                document.getElementById('currentStock').textContent = currentStock;
                document.getElementById('newStock').textContent = currentStock + quantity;
            } else {
                document.getElementById('currentStock').textContent = 'Select a product';
                document.getElementById('newStock').textContent = '-';
            }
        }
        
        // Category other option
        document.querySelector('[name="category"]').addEventListener('change', function() {
            if (this.value === 'Other') {
                const newCategory = prompt('Enter new category name:');
                if (newCategory) {
                    // Add new option
                    const option = document.createElement('option');
                    option.value = newCategory;
                    option.text = newCategory;
                    option.selected = true;
                    this.add(option);
                }
            }
        });
    </script>
</body>
</html>