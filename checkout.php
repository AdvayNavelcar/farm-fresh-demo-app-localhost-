<?php
session_start();
// *FIXED PATH:* Use _DIR_ for safe inclusion
require_once _DIR_ . '/includes/db_connect.php'; 
require_once _DIR_ . '/includes/auth_check.php';
require_once _DIR_ . '/includes/cart_utils.php'; // For header

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_location = $_SESSION['location'];
$message = '';
$order_processed = false;

// 1. Fetch Cart Items (omitted for brevity, assume logic from previous response)
$location_column = "available_" . strtolower($user_location);
$stmt = $conn->prepare("
    SELECT 
        ci.product_id, ci.quantity, p.name, p.price_per_unit, p.unit_type, p.stock_quantity, p.{$location_column} as is_available
    FROM 
        cart_items ci
    JOIN 
        products p ON ci.product_id = p.id
    WHERE 
        ci.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_amount = 0;
$checkout_errors = [];

// 2. Validate Cart and Calculate Total (omitted for brevity, assume logic from previous response)
if (empty($cart_items)) {
    $checkout_errors[] = "Your cart is empty. Nothing to checkout.";
} else {
    foreach ($cart_items as $item) {
        $subtotal = $item['price_per_unit'] * $item['quantity'];
        $total_amount += $subtotal;

        if (!$item['is_available']) {
            $checkout_errors[] = htmlspecialchars($item['name']) . " is no longer available in " . htmlspecialchars($user_location) . ". Please remove it from your cart.";
        }
        if ($item['stock_quantity'] < $item['quantity']) {
            $checkout_errors[] = htmlspecialchars($item['name']) . " quantity (" . number_format($item['quantity'], 3) . " kg) exceeds current stock. Only " . number_format($item['stock_quantity'], 3) . " kg remaining.";
        }
    }
}


// 3. Process Order (omitted for brevity, assume logic from previous response)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order']) && empty($checkout_errors) && !empty($cart_items)) {
    
    $conn->begin_transaction();
    
    try {
        $delivery_fee = 30.00;
        $final_total = $total_amount + $delivery_fee;
        $status = 'Pending';
        
        $stmt_order = $conn->prepare("INSERT INTO orders (user_id, total_amount, delivery_location, status) VALUES (?, ?, ?, ?)");
        $stmt_order->bind_param("idss", $user_id, $final_total, $user_location, $status);
        $stmt_order->execute();
        $order_id = $conn->insert_id;
        $stmt_order->close();
        
        foreach ($cart_items as $item) {
            $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)");
            $stmt_item->bind_param("iidd", $order_id, $item['product_id'], $item['quantity'], $item['price_per_unit']);
            $stmt_item->execute();
            $stmt_item->close();

            $stmt_stock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
            $stmt_stock->bind_param("di", $item['quantity'], $item['product_id']);
            $stmt_stock->execute();
            $stmt_stock->close();
        }
        
        $stmt_clear = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
        $stmt_clear->bind_param("i", $user_id);
        $stmt_clear->execute();
        $stmt_clear->close();
        
        $conn->commit();
        $order_processed = true;
        $message = "Order #$order_id placed successfully! Total amount: ₹" . number_format($final_total, 2);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Order processing failed for user $user_id: " . $e->getMessage());
        $checkout_errors[] = "An error occurred while processing your order. Please try again. (Ref: " . $e->getMessage() . ")";
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout - Farm Fresh</title>
    <link rel="stylesheet" href="style.css">
    <!-- Include Font Awesome for the cart icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php 
    // IMPORTANT: Changed from header_nav.html to header_nav.php
    include 'includes/header_nav.php'; 
    ?>

    <div class="container">
        <h2>Complete Your Order</h2>
        
        <?php if (!empty($message)): ?>
            <p class="message success"><?php echo $message; ?></p>
            <?php if ($order_processed): ?>
                <p><a href="home.php" class="btn-secondary">Continue Shopping</a></p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($checkout_errors)): ?>
            <p class="message error">Order could not be processed due to the following issues:</p>
            <ul>
                <?php foreach ($checkout_errors as $err): ?>
                    <li class="error-item"><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
            <p>Please return to <a href="cart.php">Your Cart</a> to resolve these items.</p>
        <?php elseif (!$order_processed): ?>
            
            <h3>Order Summary</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price/Unit</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td>₹<?php echo number_format($item['price_per_unit'], 2); ?> / <?php echo $item['unit_type'] == 'kg' ? 'Kg' : 'Unit'; ?></td>
                            <td><?php echo number_format($item['quantity'], 3); ?> Kg</td>
                            <td>₹<?php echo number_format($item['price_per_unit'] * $item['quantity'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="order-totals">
                <p>Cart Subtotal: <strong>₹<?php echo number_format($total_amount, 2); ?></strong></p>
                <p>Delivery Fee (<?php echo htmlspecialchars($user_location); ?>): <strong>₹30.00</strong></p>
                <?php $final_total = $total_amount + 30.00; ?>
                <p class="final-total">Order Total: <strong>₹<?php echo number_format($final_total, 2); ?></strong></p>
            </div>

            <!-- Payment Simulation / Confirmation -->
            <div class="payment-simulation">
                <h4>Payment Method: (Simulated)</h4>
                <p>Assuming Cash on Delivery or saved method. Press button to confirm and place order.</p>
                
                <form method="POST" action="checkout.php">
                    <input type="hidden" name="place_order" value="1">
                    <button type="submit" class="btn-primary" name="place_order">Place Order & Pay Total (₹<?php echo number_format($final_total, 2); ?>)</button>
                </form>
            </div>

        <?php endif; ?>
    </div>
    <?php include 'footer.php'; ?>

</body>
</html>