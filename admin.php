<?php
session_start();
// Include the database connection file
include 'includes/db_connect.php';

// Initialize variables
$message = '';
$edit_product_id = null; // ID of the product currently being edited
$products_result = null;

// Strict Admin Authorization Check
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// --- CRUD Operations ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;

    // 1. ADD Product (Create)
    if ($action == 'add') {
        $name = $_POST['name'];
        $price = $_POST['price'];
        $category = $_POST['category'];
        $unit_type = $_POST['unit_type']; // String: 'kg' or 'g'
        $stock = $_POST['stock_quantity']; // Double/Float
        $margao = isset($_POST['available_margao']) ? 1 : 0;
        $panjim = isset($_POST['available_panjim']) ? 1 : 0;
        $vasco = isset($_POST['available_vasco']) ? 1 : 0;
        // Simple image path based on category for placeholders
        $image_path = 'placeholders/' . strtolower($category) . '.png';

        // Parameters: name(s), category(s), price(d), unit_type(s), stock(d), margao(i), panjim(i), vasco(i), image_path(s)
        $stmt = $conn->prepare("INSERT INTO products (name, category, price_per_unit, unit_type, stock_quantity, available_margao, available_panjim, available_vasco, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssdsdiiis',$name, $category, $price, $unit_type, $stock, $margao, $panjim, $vasco, $image_path);
        
        if ($stmt->execute()) {
            $message = "<span class='success'>Product added successfully.</span>";
        } else {
            $message = "<span class='error'>Error adding product: " . $conn->error . "</span>";
        }
    }

    // 2. DELETE Product (Delete)
    else if ($action == 'delete' && $product_id) {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        if ($stmt->execute()) {
            $message = "<span class='success'>Product ID {$product_id} deleted successfully.</span>";
        } else {
            $message = "<span class='error'>Error deleting product: " . $conn->error . "</span>";
        }
    }

    // 3. START EDIT MODE (Read for Update)
    else if ($action == 'edit' && $product_id) {
        $edit_product_id = $product_id;
    }

    // 4. SUBMIT UPDATE (Update)
    else if ($action == 'submit_update' && $product_id) {
        $name = $_POST['name'];
        $price = $_POST['price'];
        $category = $_POST['category'];
        $unit_type = $_POST['unit_type']; // String: 'kg' or 'g'
        
        // --- Capture all fields from the POST request ---
        $stock = $_POST['stock_quantity']; // Double/Float
        $margao = isset($_POST['available_margao']) ? 1 : 0;
        $panjim = isset($_POST['available_panjim']) ? 1 : 0;
        $vasco = isset($_POST['available_vasco']) ? 1 : 0;
        // ----------------------------------------------------

        // Parameters: name(s), category(s), price(d), unit_type(s), stock(d), margao(i), panjim(i), vasco(i), product_id(i)
        $stmt = $conn->prepare("UPDATE products SET name=?, category=?, price_per_unit=?, unit_type=?, stock_quantity=?, available_margao=?, available_panjim=?, available_vasco=? WHERE id=?");
        $stmt->bind_param("ssdsdiiii", $name, $category, $price, $unit_type, $stock, $margao, $panjim, $vasco, $product_id);

        if ($stmt->execute()) {
            $message = "<span class='success'>Product ID {$product_id} updated successfully.</span>";
        } else {
            $message = "<span class='error'>Error updating product: " . $conn->error . "</span>";
        }
    }

    // 5. CHANGE PICTURE (Update Picture)
    else if ($action == 'change_pic' && $product_id && isset($_FILES['new_image']) && $_FILES['new_image']['error'] == 0) {
        $target_dir = "uploads/";
        $imageFileType = strtolower(pathinfo($_FILES["new_image"]["name"], PATHINFO_EXTENSION));
        $new_filename = $product_id . '_' . time() . '.' . $imageFileType;
        $target_file = $target_dir . $new_filename;

        // Basic image security checks (size/type checks omitted for brevity but recommended in production)

        if (move_uploaded_file($_FILES["new_image"]["tmp_name"], $target_file)) {
            $stmt = $conn->prepare("UPDATE products SET image_path = ? WHERE id = ?");
            $stmt->bind_param("si", $target_file, $product_id);
            $stmt->execute();
            $message = "<span class='success'>Image updated successfully for ID {$product_id}.</span>";
        } else {
            $message = "<span class='error'>Error uploading file.</span>";
        }
    }
}

// 6. FETCH Products for Display (Always fetch the current list of products)
$products_result = $conn->query("SELECT * FROM products ORDER BY id DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Basic Admin Styles for readability and functionality */
        body { font-family: sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 0; }
        .header { background-color: #4CAF50; color: white; padding: 15px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header nav { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; padding: 0 20px; }
        .nav-link { color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px; transition: background-color 0.3s; }
        .nav-link:hover { background-color: #45a049; }
        .logo { font-weight: bold; font-size: 1.5em; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .admin-page h2, .admin-page h3 { color: #2e8b57; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px; margin-top: 30px; }
        hr { border: 0; height: 1px; background-color: #ddd; margin: 40px 0; }

        /* Add Product Form Styles */
        .add-product-panel-wrapper { background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .form-card h3 { margin-top: 0; color: #4CAF50; }
        .form-card form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .form-card input[type="text"], .form-card input[type="number"], .form-card select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        /* ADJUSTMENT 1: Make Category/Unit selectors for ADD form take up less horizontal space (150px) */
        .form-card select[name="category"], .form-card select[name="unit_type"] {
            /* Override the grid-template-columns and set max-width */
            max-width: 150px; 
            min-width: 100px; /* Ensure minimum width */
            width: auto; /* Allow sizing based on content/max-width */
        }
        
        /* Updated Add Product Form Location Availability Title */
        .form-card h4 { 
            grid-column: 1 / -1; 
            margin: 10px 0 5px; 
            color: #4CAF50; /* Changed color to match header/submit button for distinction */
            font-size: 1em; 
            font-weight: bold;
        }
        
        /* ADJUSTMENT 2: Make the location checkboxes for ADD form narrower and inline with the label */
        .checkbox-group { 
            display: flex; 
            gap: 25px; /* Increased gap slightly for better separation */
            grid-column: 1 / -1; 
            align-items: center;
            flex-wrap: wrap; /* Allow wrapping on small screens */
        }
        .checkbox-group label { 
            display: flex; 
            align-items: center; 
            gap: 7px; 
            font-weight: normal; 
            color: #333;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
            transform: scale(1.1);
        }
        
        .btn-submit { background-color: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.3s; grid-column: 1 / -1; }
        .btn-submit:hover { background-color: #45a049; }

        /* Product Table Styles */
        .table-responsive { overflow-x: auto; }
        .admin-table { width: 100%; border-collapse: collapse; margin-top: 20px; background-color: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .admin-table th, .admin-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; vertical-align: middle; }
        .admin-table th { background-color: #f7f7f7; color: #555; font-weight: 600; }
        .product-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; margin-right: 10px; display: block; }
        
        .availability { display: inline-block; width: 20px; height: 20px; border-radius: 50%; text-align: center; line-height: 20px; font-size: 0.75em; font-weight: bold; color: white; margin-right: 5px; }
        .available { background-color: #4CAF50; }
        .unavailable { background-color: #ccc; }

        /* Actions and Edit Form Styles */
        .btn-action { padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; margin: 5px 0; display: inline-block; transition: background-color 0.3s; }
        .btn-edit { background-color: #2196F3; color: white; }
        .btn-edit:hover { background-color: #0b7dda; }
        /* .btn-delete will be styled for the modal trigger */
        .btn-delete { background-color: #f44336; color: white; }
        .btn-delete:hover { background-color: #da190b; }

        .image-upload-form { display: flex; flex-direction: column; gap: 5px; margin-top: 5px; max-width: 150px; }
        .file-input { font-size: 0.75em; padding: 5px 0; }
        .btn-small { padding: 5px 10px; font-size: 0.8em; }
        .btn-upload { background-color: #9E9E9E; color: white; }
        .btn-upload:hover { background-color: #757575; }

        /* --- Compact Edit Form Row (UPDATED) --- */
        .edit-form-container { 
            padding: 15px; /* Reduced padding */
            background-color: #fff3e0; 
            border-radius: 6px; 
            margin: 10px 0; 
            border: 1px solid #ffcc80; 
            max-width: 900px; /* Max width to stop stretching */
            margin-left: auto;
            margin-right: auto;
        }
        .edit-form-container h4 { color: #e65100; margin-bottom: 10px; font-size: 1.1em;}

        .edit-product-form { 
            display: grid; 
            /* Grid layout: 3 columns for inputs on desktop, auto-fits */
            grid-template-columns: repeat(3, minmax(180px, 1fr)); 
            gap: 10px 15px; /* Reduced gap for a tighter feel */
            align-items: flex-end; /* Align elements to the bottom */
        }
        .edit-product-form label { 
            display: block; 
            font-weight: bold; 
            flex: none; /* Removed the expansive flex property */
        }
        .edit-product-form input, .edit-product-form select { 
            margin-top: 5px; 
            width: 100%; 
            padding: 8px; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
        }
        
        /* ADJUSTMENT 3: Make Category/Unit selectors for EDIT form take up less horizontal space, overriding the 100% width above */
        .edit-product-form select {
            max-width: 150px; 
            min-width: 100px; 
            width: auto; 
        }
        
        /* Ensure the availability checkboxes and action buttons span the entire width */
        .edit-product-form .full-width { 
            grid-column: 1 / -1; 
        }
        
        /* ADJUSTMENT 4: Tweak alignment for the edit form's checkbox group for a tighter feel */
        .edit-product-form .checkbox-group {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px; /* Tighter gap */
            justify-content: flex-start;
        }
        .edit-product-form .checkbox-title { /* Custom class for the title inside the group */
            margin-right: 20px; 
            color: #e65100; /* Matching edit form's title color */
            font-size: 1em;
            font-weight: bold;
        }
        
        .form-actions { 
            display: flex; 
            gap: 10px; 
            justify-content: flex-end; 
            margin-top: 10px; /* Add margin above buttons */
        }
        .btn-save { background-color: #ff9800; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.3s; }
        .btn-save:hover { background-color: #fb8c00; }
        .btn-cancel { background-color: #607d8b; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; text-align: center; }
        .btn-cancel:hover { background-color: #546e7a; }
        /* --- End Compact Edit Form Row --- */


        /* Confirmation Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            display: none; /* Hidden by default */
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        .modal-content h3 {
            color: #f44336;
            margin-top: 0;
        }
        .modal-buttons {
            margin-top: 20px;
            display: flex;
            justify-content: space-around;
        }
        .btn-cancel-modal {
            background-color: #607d8b;
            color: white;
        }
        .btn-cancel-modal:hover {
            background-color: #546e7a;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-card form { grid-template-columns: 1fr; }
            
            /* Ensure the checkbox group aligns vertically on mobile */
            .checkbox-group { 
                flex-direction: column; 
                align-items: flex-start; 
                gap: 10px;
            }
            
            /* ADJUSTMENT 5: Reset max-width for selectors on mobile */
            .form-card select[name="category"], .form-card select[name="unit_type"] {
                max-width: 100%; 
            }
            
            .admin-table th, .admin-table td { font-size: 0.9em; }
            .admin-table, .admin-table tbody, .admin-table tr, .admin-table td { display: block; width: 100%; }
            .admin-table thead { display: none; }
            .admin-table tr { margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; padding: 10px; }
            .admin-table td { text-align: right; border-bottom: none; position: relative; padding-left: 50%; }
            .admin-table td:before { 
                content: attr(data-label); 
                font-weight: bold;
                margin-right: 10px;
                color: #555;
                position: absolute;
                left: 10px;
                width: 45%;
                text-align: left;
            }
            .admin-table td:nth-child(1):before { content: "ID"; }
            .admin-table td:nth-child(2):before { content: "Product"; }
            .admin-table td:nth-child(3):before { content: "Stock / Price"; }
            .admin-table td:nth-child(4):before { content: "Availability"; }
            .admin-table td:nth-child(5):before { content: "Image"; }
            .admin-table td:nth-child(6):before { content: "Actions"; }
            
            /* Mobile adjustment for edit form: switch to single column */
            .edit-product-form {
                grid-template-columns: 1fr; 
            }
            .edit-product-form .checkbox-group {
                /* Ensure edit form's checkbox group title and items also stack nicely on mobile */
                flex-direction: column; 
                align-items: flex-start;
            }
            
            /* ADJUSTMENT 6: Reset max-width for edit form selectors on mobile */
            .edit-product-form select {
                max-width: 100%; 
            }
            
            .modal-buttons button, .modal-buttons a { width: 45%; }
            .image-upload-form { max-width: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <nav>
            <a class="nav-link logo" href="home.php">🌱 Farm Fresh (ADMIN)</a>
            <a class="nav-link" href="logout.php">Logout</a>
        </nav>
    </div>

    <div class="container admin-page">
        <h2>Admin Panel - Product Management</h2>
        <?php if ($message) echo "<div class='message'>$message</div>"; ?>

        <div class="add-product-panel-wrapper">
            <div class="form-card">
                <h3>Add New Product</h3>
                <form action="admin.php" method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="text" name="name" placeholder="Product Name" required>
                    <input type="number" name="price" step="0.01" placeholder="Price (per unit)" required>
                    <input type="number" name="stock_quantity" step="0.001" placeholder="Initial Stock Quantity (in kg/g)" required>
                    
                    <select name="category" id="add-category-select" required>
                        <option value="vegetable">Vegetable</option>
                        <option value="fruit">Fruit</option>
                        <option value="herb">Herb</option>
                    </select>
                    <select name="unit_type" id="add-unit-type-select" required>
                        <option value="kg">KG</option>
                        <option value="g">Grams</option>
                    </select>
                    
                    <h4>Location Availability (Select where this product is available)</h4>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="available_margao" checked> Margao</label>
                        <label><input type="checkbox" name="available_panjim" checked> Panjim</label>
                        <label><input type="checkbox" name="available_vasco" checked> Vasco</label>
                    </div>
                    
                    <button type="submit" class="btn-submit">Add Product</button>
                </form>
            </div>
        </div>

        <hr>

        <h3>Current Products</h3>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name / Category</th>
                        <th>Stock / Price</th>
                        <th>Availability</th>
                        <th>Image</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                        if ($products_result && $products_result->num_rows > 0) {
                            while ($product = $products_result->fetch_assoc()): 
                    ?>
                        <?php if ($edit_product_id == $product['id']): ?>
                            <tr class="editing-row">
                                <td colspan="6">
                                    <div class="edit-form-container">
                                        <h4>Editing Product ID: <?php echo $product['id']; ?></h4>
                                        <form action="admin.php" method="POST" class="edit-product-form">
                                            <input type="hidden" name="action" value="submit_update">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            
                                            <label>Name: <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required></label>
                                            <label>Price: <input type="number" name="price" step="0.01" value="<?php echo $product['price_per_unit']; ?>" required></label>
                                            <label>Stock: <input type="number" name="stock_quantity" step="0.001" value="<?php echo $product['stock_quantity']; ?>" required></label>
                                            
                                            <label>Category: 
                                                <select name="category" id="edit-category-select-<?php echo $product['id']; ?>" required>
                                                    <option value="vegetable" <?php echo $product['category'] == 'vegetable' ? 'selected' : ''; ?>>Vegetable</option>
                                                    <option value="fruit" <?php echo $product['category'] == 'fruit' ? 'selected' : ''; ?>>Fruit</option>
                                                    <option value="herb" <?php echo $product['category'] == 'herb' ? 'selected' : ''; ?>>Herb</option>
                                                </select>
                                            </label>
                                            <label>Unit:
                                                <select name="unit_type" id="edit-unit-type-select-<?php echo $product['id']; ?>" required>
                                                    <option value="kg" <?php echo $product['unit_type'] == 'kg' ? 'selected' : ''; ?>>KG</option>
                                                    <option value="g" <?php echo $product['unit_type'] == 'g' ? 'selected' : ''; ?>>Grams</option>
                                                </select>
                                            </label>
                                            
                                            <div class="checkbox-group full-width">
                                                <span class="checkbox-title" style="margin-right: 20px;">Location Availability</span>
                                                <label><input type="checkbox" name="available_margao" <?php echo $product['available_margao'] ? 'checked' : ''; ?>> Margao</label>
                                                <label><input type="checkbox" name="available_panjim" <?php echo $product['available_panjim'] ? 'checked' : ''; ?>> Panjim</label>
                                                <label><input type="checkbox" name="available_vasco" <?php echo $product['available_vasco'] ? 'checked' : ''; ?>> Vasco</label>
                                            </div>
                                            
                                            <div class="form-actions full-width">
                                                <button type="submit" class="btn-save">Save Changes</button>
                                                <a href="admin.php" class="btn-cancel">Cancel Edit</a>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td data-label="ID"><?php echo $product['id']; ?></td>
                                <td data-label="Product">
                                    <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                                    <small>(<?php echo ucfirst($product['category']); ?>)</small>
                                </td>
                                <td data-label="Stock / Price">
                                    Stock: <?php echo number_format($product['stock_quantity'], 3) . ' ' . $product['unit_type']; ?><br>
                                    Price: ₹<?php echo $product['price_per_unit']; ?>/<?php echo $product['unit_type']; ?>
                                </td>
                                <td data-label="Availability">
                                    <span title="Margao" class="availability <?php echo $product['available_margao'] ? 'available' : 'unavailable'; ?>">M</span>
                                    <span title="Panjim" class="availability <?php echo $product['available_panjim'] ? 'available' : 'unavailable'; ?>">P</span>
                                    <span title="Vasco" class="availability <?php echo $product['available_vasco'] ? 'available' : 'unavailable'; ?>">V</span>
                                </td>
                                <td data-label="Image">
                                    <img src="<?php echo $product['image_path']; ?>" alt="Product Image" class="product-thumb" onerror="this.onerror=null; this.src='placeholders/default.png';">
                                    <form action="admin.php" method="POST" enctype="multipart/form-data" class="image-upload-form">
                                        <input type="hidden" name="action" value="change_pic">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <input type="file" name="new_image" required class="file-input">
                                        <button type="submit" class="btn-small btn-upload">Upload</button>
                                    </form>
                                </td>
                                <td data-label="Actions">
                                    <form action="admin.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" class="btn-action btn-edit">Edit</button>
                                    </form>
                                    
                                    <button type="button" 
                                        class="btn-action btn-delete" 
                                        onclick="openDeleteModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>')">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endwhile; 
                        } else {
                            echo "<tr><td colspan='6' style='text-align:center;'>No products found in the database.</td></tr>";
                        }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="deleteModal" class="modal-overlay">
        <div class="modal-content">
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to permanently delete the product:</p>
            <p style="font-weight: bold; color: #f44336;" id="productNamePlaceholder">Product Name</p>
            
            <form action="admin.php" method="POST" id="confirmDeleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="product_id" id="modalProductId">
                <div class="modal-buttons">
                    <button type="button" class="btn-action btn-cancel-modal" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn-action btn-delete">Confirm Delete</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    
    // Function to show the modal and set product data
    function openDeleteModal(productId, productName) {
        // Set the product ID into the hidden input field in the delete form
        document.getElementById('modalProductId').value = productId;
        // Display the name of the product being deleted in the modal message
        document.getElementById('productNamePlaceholder').textContent = productName;
        // Show the modal overlay
        document.getElementById('deleteModal').style.display = 'flex';
    }

    // Function to hide the modal
    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', () => {

        // --- Core Function: Unit Selection Logic ---
        const autoSelectUnit = (categorySelectId, unitSelectId) => {
            const categorySelect = document.getElementById(categorySelectId);
            const unitSelect = document.getElementById(unitSelectId);
            
            if (!categorySelect || !unitSelect) {
                // Silent failure if elements aren't present (e.g., in a non-edit row)
                return;
            }

            const category = categorySelect.value;
            
            // Set unit based on category convention
            if (category === 'fruit' || category === 'vegetable') {
                unitSelect.value = 'kg';
            } else if (category === 'herb') {
                unitSelect.value = 'g';
            }
        };

        // --- 1. Apply to ADD Product Form ---
        const addCategorySelect = document.getElementById('add-category-select');
        
        if (addCategorySelect) {
            // Initial selection on load
            autoSelectUnit('add-category-select', 'add-unit-type-select');

            // Listener for subsequent changes
            addCategorySelect.addEventListener('change', () => {
                autoSelectUnit('add-category-select', 'add-unit-type-select');
            });
        }

        // --- 2. Apply to EDIT Product Forms (using Event Delegation) ---
        document.body.addEventListener('change', (event) => {
            const target = event.target;
            // Check if the changed element is the category select in the edit form
            if (target && target.id && target.id.startsWith('edit-category-select-')) {
                // Extract the product ID from the category select's ID
                const productId = target.id.replace('edit-category-select-', '');
                
                // Construct the correct ID for the corresponding unit select
                const unitSelectId = `edit-unit-type-select-${productId}`;
                
                // Call the unit selection logic
                autoSelectUnit(target.id, unitSelectId);
            }
        });
    });
    </script>
</body>
</html>