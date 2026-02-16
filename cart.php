<?php
session_start();
// Include the database connection
include 'includes/db_connect.php';

// Enable error reporting for MySQLi for better debugging, especially with transactions
// This assumes $conn is a mysqli object
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$cart_message = '';
$cart_items = [];
$cart_total = 0;

// --- CART MANAGEMENT LOGIC ---

// 1. Handle Update Quantity
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_quantity'])) {
    $product_id = intval($_POST['product_id']);
    // Quantity received is always the KG equivalent (float)
    $new_quantity = floatval($_POST['quantity']); 

    if ($new_quantity <= 0.001) { // Treat very small quantities as removal
        // Remove item if quantity is set to zero or less
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $cart_message = "Item removed from cart.";
        $stmt->close();
    } else {
        // Update quantity (stored as double)
        $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?");
        // Using 'd' for double (float) quantity
        $stmt->bind_param("dii", $new_quantity, $user_id, $product_id); 
        $stmt->execute();
        $cart_message = "Cart quantity updated!";
        $stmt->close();
    }
}

// 2. Handle Remove Item
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_item'])) {
    $product_id = intval($_POST['product_id']);

    $stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $cart_message = "Item removed from cart.";
    $stmt->close();
}

// 3. Handle Payment Simulation (Checkout)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout'])) {
    $payment_code = trim($_POST['payment_code'] ?? '');

    if (empty($payment_code)) {
        $cart_message = "⚠️ Please enter the payment code to proceed.";
    } elseif ($payment_code === '2004') {
        // --- START SUCCESSFUL PAYMENT LOGIC: STOCK DECREMENT & CART CLEAR ---
        
        // A. Fetch current cart items and their quantities (in KG equivalent)
        $cart_to_process = [];
        $fetch_cart_stmt = $conn->prepare("SELECT product_id, quantity FROM cart_items WHERE user_id = ?");
        $fetch_cart_stmt->bind_param("i", $user_id);
        $fetch_cart_stmt->execute();
        $result_process = $fetch_cart_stmt->get_result();

        while ($item = $result_process->fetch_assoc()) {
            $cart_to_process[] = $item;
        }
        $fetch_cart_stmt->close();

        if (empty($cart_to_process)) {
            $cart_message = "⚠️ Payment successful, but your cart was already empty. Nothing was ordered.";
        } else {
            // B. Begin Transaction for Atomic Update
            $conn->begin_transaction();
            $success = true;

            try {
                // C. Decrement stock for each ordered item
                $update_stock_stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");

                foreach ($cart_to_process as $item) {
                    $product_id = $item['product_id'];
                    $quantity_ordered = $item['quantity']; // Quantity is stored in KG equivalent

                    // Bind and execute the update
                    $update_stock_stmt->bind_param("di", $quantity_ordered, $product_id);
                    if (!$update_stock_stmt->execute()) {
                        throw new Exception("Stock update failed for product ID: " . $product_id);
                    }
                }
                $update_stock_stmt->close();

                // D. Clear the user's cart
                $delete_cart_stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
                $delete_cart_stmt->bind_param("i", $user_id);
                
                if (!$delete_cart_stmt->execute()) {
                    throw new Exception("Failed to clear cart after successful stock update.");
                }
                $delete_cart_stmt->close();

                // E. Commit the transaction if everything succeeded
                $conn->commit();
                $cart_message = "✅ Payment successful! Your order has been placed and stock has been updated.";

            } catch (Exception $e) {
                // F. Rollback on any failure
                $conn->rollback();
                // Log error for developer, show generic message to user
                error_log("Transaction failed: " . $e->getMessage()); 
                $cart_message = "❌ Payment failed due to a processing error. Please check your cart and try again. (Ref: Stock/Cart Issue)";
                $success = false;
            }
        }
        // --- END SUCCESSFUL PAYMENT LOGIC ---

    } else {
        $cart_message = "❌ Payment failed. Invalid code entered.";
    }
}


// --- FETCH CART ITEMS ---
// This section runs AFTER the POST handling, so if the cart was cleared, 
// $cart_items will be an empty array, fulfilling the requirement to show the empty cart message.

// Fetch cart items from the database, joining with products to get details
$query = "
    SELECT 
        ci.product_id, 
        ci.quantity,
        p.name, 
        p.price_per_unit, 
        p.unit_type, 
        p.image_path
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    WHERE ci.user_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($item = $result->fetch_assoc()) {
        
        // Determine the price per KG equivalent for calculation
        if ($item['unit_type'] == 'g') {
             // If priced per 100g, multiply the price by 10 to get the price per KG
             $price_per_kg_equivalent = $item['price_per_unit'] * 10;
        } else {
             // If priced per kg, use the price directly
             $price_per_kg_equivalent = $item['price_per_unit'];
        }
        
        // Subtotal calculation: Quantity (in kg equivalent) * Price per KG equivalent
        $item_subtotal = $item['quantity'] * $price_per_kg_equivalent; 
        $cart_total += $item_subtotal;
        
        // Set display variables for the table
        $item['subtotal'] = $item_subtotal;
        
        $cart_items[] = $item;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Shopping Cart</title>
    <!-- Assuming style.css provides global styles, but custom styles are below -->
    <link rel="stylesheet" href="style.css"> 
    <style>
        /* Re-defining colors locally in case style.css is not complete */
        :root {
            --primary-color: #38761d; 
            --secondary-green: #6aa84f;
            --danger-color: #cc0000;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        h2 {
            border-bottom: 2px solid var(--secondary-green);
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: var(--primary-color);
        }
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .cart-table th, .cart-table td {
            padding: 15px;
            text-align: left;
            /* Use a very light border color for separation */
            border-bottom: 1px solid #ddd; 
        }
        .cart-table th {
            background-color: #f7f7f7;
            font-weight: bold;
            color: #333;
        }
        .cart-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #eee;
        }
        .quantity-input {
            width: 80px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            text-align: center;
        }
        .cart-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .total-summary {
            margin-top: 30px;
            padding: 20px;
            /* Match the light green background seen in product edit box */
            background-color: #f9fff9; 
            /* Match the strong green border seen in the cart summary in screenshot */
            border-top: 2px solid var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            text-align: right;
        }
        .total-summary h3 {
            margin: 0;
            font-size: 1.5em;
            color: var(--primary-color);
            font-weight: bold;
        }
        /* --- Payment Input Styling --- */
        .payment-input-group {
            display: flex;
            justify-content: flex-end; /* Align to the right */
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            margin-top: 15px;
        }
        .payment-input-group label {
            font-weight: 500;
            color: #555;
        }
        .payment-input {
            width: 150px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        /* --- Pay Button Styling --- */
        .pay-button {
            padding: 15px 30px;
            font-size: 1.2em;
            width: 100%;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
            font-weight: bold;
        }
        .pay-button:hover {
            background-color: var(--secondary-green);
        }
        .unit-display {
            min-width: 40px; 
            text-align: left;
        }
    </style>
</head>
<body>
    <?php include 'includes/header_nav.php'; ?>
<div class="page-wrapper"><div class="container">
        <h2>Your Shopping Cart</h2>

        <?php 
        // Display messages with dynamic styling based on content
        if ($cart_message) {
            $class = (strpos($cart_message, 'successful') !== false) ? 'message' : 
                     ((strpos($cart_message, 'failed') !== false || strpos($cart_message, 'enter') !== false) ? 'message error' : 'message');
            echo "<div class='" . $class . "'>" . htmlspecialchars($cart_message) . "</div>"; 
        }
        ?>

        <?php if (empty($cart_items)): ?>
            <p class="info-message">Your persistent shopping cart is currently empty. Start adding some fresh produce!</p>
            <p><a href="home.php" class="btn" style="width: fit-content; display: inline-block;">Continue Shopping</a></p>
        <?php else: ?>
            
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price/Unit</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_items as $item): ?>
                        <?php 
                            // Display quantity calculation
                            $display_unit = ($item['unit_type'] == 'g') ? 'grams (KG eq.)' : 'kg';
                            $display_qty_value = $item['quantity']; // Display the raw KG equivalent value for input
                        ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center;">
                                    <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="cart-item-image">
                                    <span style="margin-left: 15px;"><?php echo htmlspecialchars($item['name']); ?></span>
                                </div>
                            </td>
                            <td>₹<?php echo number_format($item['price_per_unit'], 2); ?> / 
                                <?php echo ($item['unit_type'] == 'g') ? '100g' : 'Kg'; ?>
                            </td>
                            <td>
                                <!-- Form for quantity update -->
                                <form method="POST" action="cart.php" class="cart-actions">
                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                    <input type="hidden" name="update_quantity" value="1">
                                    
                                    <!-- Input value is the DB quantity (kg equivalent) -->
                                    <input type="number" 
                                            name="quantity" 
                                            value="<?php echo number_format($display_qty_value, 3, '.', ''); ?>" 
                                            step="0.05" 
                                            min="0.05"
                                            class="quantity-input"
                                            required>
                                    
                                    <!-- Display unit (grams/kg) for context -->
                                    <span class="unit-display"><?php echo $display_unit; ?></span>
                                    
                                    <button type="submit" class="btn-small">Update</button>
                                </form>
                            </td>
                            <td>₹<?php echo number_format($item['subtotal'], 2); ?></td>
                            <td>
                                <!-- Form for removal -->
                                <form method="POST" action="cart.php">
                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                    <input type="hidden" name="remove_item" value="1">
                                    <button type="submit" class="btn-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Total Summary and Payment Logic -->
            <div class="total-summary">
                <h3>Cart Total: ₹<?php echo number_format($cart_total, 2); ?></h3>
                
                <form method="POST" action="cart.php">
                    <input type="hidden" name="checkout" value="1">

                    <!-- NEW: Payment Code Input Field -->
                    <div class="payment-input-group">
                        <label for="payment_code">Enter Payment Code (2004)</label>
                        <input type="text" 
                               id="payment_code" 
                               name="payment_code" 
                               class="payment-input" 
                               placeholder="e.g., 2004"
                               required>
                    </div>
                    
                    <!-- Button text changed to Pay -->
                    <button type="submit" class="pay-button">Pay</button>
                </form>
            </div>

        <?php endif; ?>
    </div></div>
    
    <?php include 'footer.php'; ?>
</body>
</html>