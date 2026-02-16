<?php
// includes/cart_utils.php

/**
 * Fetches the total number of unique items in the user's cart.
 *
 * @param mysqli $conn Database connection object.
 * @param int $user_id The ID of the currently logged-in user.
 * @return int The count of unique items in the cart (0 if none).
 */
function get_cart_item_count($conn, $user_id) {
    if (!$user_id) {
        return 0;
    }
    
    // Select the count of unique rows (items) in the cart for the user
    $stmt = $conn->prepare("SELECT COUNT(product_id) AS item_count FROM cart_items WHERE user_id = ?");
    if ($stmt === false) {
        // Log the error and return 0
        error_log("Failed to prepare cart item count query: " . $conn->error);
        return 0;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $count = (int) $row['item_count'];
    } else {
        $count = 0;
    }
    
    $stmt->close();
    return $count;
}