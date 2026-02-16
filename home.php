<?php
session_start();
// **CORRECTED PATH:** Use require_once __DIR__ to guarantee the include path is relative to the current file's directory.
require_once __DIR__ . '/includes/db_connect.php'; 

// --- PERSISTENT CART LOGIC START ---
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Handle Add to Cart (Now updates the persistent database cart)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    if (!$user_id) {
        header("Location: login.php");
        exit();
    }
    
    $product_id = intval($_POST['product_id']);
    $quantity = floatval($_POST['quantity']); // Quantity in KG (0.1 kg, 0.25 kg, etc.)
    $location = strtolower($_SESSION['location']);
    $location_col = 'available_' . $location;

    // Find product details and check stock/availability in one query
    $stmt = $conn->prepare("SELECT name, price_per_unit, unit_type, stock_quantity, $location_col FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product_details = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($product_details) {
        $is_available = $product_details[$location_col];
        $current_stock = $product_details['stock_quantity'];

        if (!$is_available) {
             $cart_message = "Error: Product not available in " . $_SESSION['location'] . ".";
        } elseif ($current_stock < $quantity) {
             $cart_message = "Error: Only " . number_format($current_stock, 3) . " units of " . $product_details['name'] . " remaining.";
        } else {
            // --- DATABASE CART INSERT/UPDATE LOGIC ---
            
            // 1. Check if product already exists in cart_items for this user
            $stmt_check = $conn->prepare("SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
            $stmt_check->bind_param("ii", $user_id, $product_id);
            $stmt_check->execute();
            $current_cart_item = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            if ($current_cart_item) {
                // Item exists, update quantity
                $stmt_update = $conn->prepare("UPDATE cart_items SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?");
                $stmt_update->bind_param("dii", $quantity, $user_id, $product_id);
                $stmt_update->execute();
                $stmt_update->close();
            } else {
                // Item does not exist, insert new row
                $stmt_insert = $conn->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt_insert->bind_param("iid", $user_id, $product_id, $quantity);
                $stmt_insert->execute();
                $stmt_insert->close();
            }

            $cart_message = $product_details['name'] . " added to persistent cart!";
        }
    }
}

// --- FETCH PRODUCTS FOR DISPLAY ---
$products_result = $conn->query("SELECT id, name, category, price_per_unit, unit_type, image_path, stock_quantity, available_margao, available_panjim, available_vasco FROM products WHERE is_available = 1");
$products = $products_result ? $products_result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home - Farm Fresh</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Minimal styling for the search bar */
        .search-bar-container {
            margin-bottom: 25px;
            padding: 10px;
            background-color: #f7f7f7;
            border-radius: 6px;
        }
        .search-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        /* Ensure cards are visible when JS sets display: block */
            display: block; 
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: transform 0.2s;
            padding: 15px; /* Added padding for better look */
        }
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .product-image-container img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        .product-card h3 {
            margin-top: 0;
            margin-bottom: 5px;
            font-size: 1.4em;
        }
        .product-card .price {
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header_nav.php'; // Corrected file name ?>

    <div class="container product-grid">
        <?php if (isset($_SESSION['location'])): ?>
            <h2 class="location-header">Fresh Produce for: <span class="location-tag"><?php echo htmlspecialchars($_SESSION['location']); ?></span></h2>
        <?php else: ?>
            <h2 class="location-header">Fresh Produce <span class="location-tag" style="font-size:0.8em;font-weight:400;">(Login to check availability)</span></h2>
        <?php endif; ?>

        <?php if (isset($cart_message)) echo "<div class='message " . (strpos($cart_message, 'Error') !== false ? 'error' : 'success') . "'>$cart_message</div>"; ?>

        <div class="search-bar-container">
            <input type="text" id="product-search" placeholder="Search for fresh produce..." class="search-input">
        </div>

        <?php foreach ($products as $product): ?>
            <?php
            // Location and Stock Check Logic for Display
            $is_logged_in = isset($_SESSION['user_id']);
            $is_available_at_location = false;
            $stock_qty = $product['stock_quantity']; // Always in KG equivalent
            $is_in_stock = $stock_qty > 0;
            
            if ($is_logged_in) {
                $user_location_col = 'available_' . strtolower($_SESSION['location']);
                $is_available_at_location = $product[$user_location_col]; 
            }
            $can_add_to_cart = $is_logged_in && $is_available_at_location && $is_in_stock;

            // Determines the visibility class for JS to read
            $initial_visibility_class = 'is-unavailable';
            if ($is_logged_in && $is_available_at_location && $is_in_stock) {
                $initial_visibility_class = 'is-available';
            }

            // Consistent unit display logic
            $price_unit_display = $product['unit_type'] == 'kg' ? 'Kg' : '100g';
            $stock_unit_display = $product['unit_type'] == 'kg' ? 'kg' : 'grams';
            $stock_display_qty = $product['unit_type'] == 'kg' ? $stock_qty : $stock_qty * 1000; // Convert to grams for display if unit is 'g'
            ?>

            <div class="product-card <?php echo $initial_visibility_class; ?>" 
                data-name="<?php echo strtolower($product['name']); ?>" 
                data-category="<?php echo $product['category']; ?>" 
                data-unit="<?php echo $product['unit_type']; ?>">
                <div class="product-image-container">
                    <img src="<?php echo $product['image_path']; ?>" alt="<?php echo $product['name']; ?>">
                    <?php if ($stock_qty < 0.5 && $is_in_stock): ?>
                        <span class="product-flag low-stock">Low Stock</span>
                    <?php endif; ?>
                    <?php if ($product['id'] > (count($products) - 3)): ?>
                        <span class="product-flag new">New</span>
                    <?php endif; ?>
                </div>
                <h3><?php echo $product['name']; ?></h3>
                <div class="price">₹<?php echo $product['price_per_unit']; ?> <span class="unit">per <?php echo $price_unit_display; ?></span></div>
                <div class="availability-status">
                    <?php if (!$is_logged_in): ?>
                        <p class="error">Log in to check availability!</p>
                    <?php elseif (!$is_available_at_location): ?>
                        <p class="error">❌ Not available in <?php echo htmlspecialchars($_SESSION['location']); ?></p>
                    <?php elseif (!$is_in_stock): ?>
                        <p class="error">🔴 Out of Stock!</p>
                    <?php else: ?>
                        <p class="success">✅ Available in <?php echo htmlspecialchars($_SESSION['location']); ?></p>
                        <p class="stock">Stock: <b><?php echo number_format($stock_display_qty, $product['unit_type'] == 'kg' ? 3 : 0); ?></b> <?php echo $stock_unit_display; ?></p>
                    <?php endif; ?>
                </div>
                <form method="POST" action="home.php">
                    <input type="hidden" name="add_to_cart" value="1">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <label for="qty-<?php echo $product['id']; ?>">Quantity:</label>
                    <select id="qty-<?php echo $product['id']; ?>" name="quantity" class="quantity-select" required></select>
                    <button type="submit" class="btn-add-to-cart" <?php echo $can_add_to_cart ? '' : 'disabled'; ?>>
                        <?php echo $can_add_to_cart ? 'Add to Cart' : 'Not Available'; ?>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('product-search');
        const productCards = Array.from(document.querySelectorAll('.product-card'));
        
        // --- Quantity Select Logic ---
        const setupQuantitySelectors = () => {
            productCards.forEach(card => {
                const unitType = card.dataset.unit;
                const selectElement = card.querySelector('.quantity-select');
                if (!selectElement) return;

                let options = [];
                let displayUnit = '';
                
                if (unitType === 'kg') {
                    // Values are in KG (used directly for backend)
                    options = [0.1, 0.25, 0.5, 1, 2, 5];
                    displayUnit = ' kg';
                } else if (unitType === 'g') {
                    // Values are in KG (used for backend) but displayed in grams
                    options = [0.05, 0.1, 0.25, 0.5]; // 50g, 100g, 250g, 500g
                    displayUnit = ' grams';
                }

                selectElement.innerHTML = ''; 
                
                const defaultOption = document.createElement('option');
                defaultOption.value = "";
                defaultOption.textContent = "Select Quantity";
                defaultOption.disabled = true;
                defaultOption.selected = true;
                selectElement.appendChild(defaultOption);

                options.forEach(value => {
                    const option = document.createElement('option');
                    option.value = value;
                    
                    let textContent = value;
                    if (unitType === 'g') {
                        // Display in grams (e.g., 0.1 * 1000 = 100 grams)
                        textContent = (value * 1000); 
                    }
                    option.textContent = textContent + displayUnit;
                    
                    selectElement.appendChild(option);
                });
            });
        };

        // --- Filtering Logic (New) ---
        const filterProducts = (query) => {
            const lowerQuery = query.toLowerCase().trim();

            productCards.forEach(card => {
                const name = card.dataset.name;
                const isAvailableDefault = card.classList.contains('is-available');
                
                let showCard = false;

                if (lowerQuery.length === 0) {
                    // Default view: Only show if marked as available by PHP
                    showCard = isAvailableDefault;
                } else {
                    // Search mode: Show if the name matches, regardless of availability
                    if (name.includes(lowerQuery)) {
                        showCard = true;
                    }
                }

                // Toggle visibility
                card.style.display = showCard ? 'block' : 'none';
            });
        };

        // 1. Initialize Quantity Selects
        setupQuantitySelectors();
        
        // 2. Initial Filter: Hide all unavailable products on page load
        filterProducts(''); 

        // 3. Add event listener for search input
        if (searchInput) {
            searchInput.addEventListener('input', (event) => {
                filterProducts(event.target.value);
            });
        }
    });
    </script>
    <?php include 'footer.php'; ?>

</body>
</html>