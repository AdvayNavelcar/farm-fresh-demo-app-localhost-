<?php
session_start();
include 'includes/db_connect.php';

// 1. Authorization Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_details = [];
$order_history = [];
$message = '';
$is_update_mode = false;

// --- Handle Profile Update Submission ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $new_username = trim($_POST['new_username']);
    // $new_email is deliberately ignored for update as requested.
    $new_location = strtolower(trim($_POST['new_location']));

    $allowed_locations = ['margao', 'panjim', 'vasco'];
    
    // Validation
    if (!in_array($new_location, $allowed_locations)) {
        $message = "<span class='error'>Update Failed: Location must be Margao, Panjim, or Vasco.</span>";
        $is_update_mode = true; // Stay in update mode
    } elseif (empty($new_username)) { // Check only username
        $message = "<span class='error'>Update Failed: Username cannot be empty.</span>";
        $is_update_mode = true;
    } else {
        // Prepare update statement. ONLY updating username and location.
        $stmt_update = $conn->prepare("UPDATE users SET username = ?, location = ? WHERE id = ?");
        $stmt_update->bind_param("ssi", $new_username, $new_location, $user_id);

        if ($stmt_update->execute()) {
            // Update session variables immediately
            $_SESSION['username'] = $new_username;
            $_SESSION['location'] = $new_location;
            
            $message = "<span class='success'>Profile updated successfully!</span>";
        } else {
            // Check for unique constraint violation (username already exists)
            if ($conn->errno == 1062) {
                // 1062 error here suggests username conflict, since email is not being updated.
                $message = "<span class='error'>Update Failed: Username already taken.</span>";
            } else {
                $message = "<span class='error'>Update Failed: Database error.</span>";
            }
            $is_update_mode = true;
        }
    }
}

// --- Fetch User Details (Run after update attempt to get fresh data) ---
$stmt_user = $conn->prepare("SELECT username, email, location, user_type FROM users WHERE id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
if ($result_user->num_rows > 0) {
    $user_details = $result_user->fetch_assoc();
} else {
    header("Location: logout.php"); 
    exit();
}

// --- Check if the user clicked the 'Update' button to show the form ---
if (isset($_POST['action']) && $_POST['action'] == 'show_update_form') {
    $is_update_mode = true;
}

// --- Fetch Order History (same as before) ---
$sql_orders = "
    SELECT 
        o.order_date, p.name AS product_name, o.quantity, o.total_price
    FROM orders o JOIN products p ON o.product_id = p.id
    WHERE o.user_id = ?
    ORDER BY o.order_date DESC
";
$stmt_orders = $conn->prepare($sql_orders);
$stmt_orders->bind_param("i", $user_id);
$stmt_orders->execute();
$result_orders = $stmt_orders->get_result();

while ($row = $result_orders->fetch_assoc()) {
    $order_history[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile & Orders</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .update-form-container {
            margin-top: 20px;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        /* Style the readonly email field to look clearly disabled */
        .update-form-container input[readonly] {
            background-color: #eee;
            cursor: not-allowed;
            color: #666;
        }
    </style>
</head>
<body>
    <?php include 'includes/header_nav.php'; ?>

    <div class="container profile-page">
        <h2>Welcome, <?php echo htmlspecialchars($user_details['username']); ?></h2>
        
        <?php 
        // Display message box based on content
        if ($message) {
            $class = (strpos($message, 'success') !== false) ? 'success-box' : 'error-box';
            echo "<p class='message-box {$class}'>{$message}</p>"; 
        }
        ?>

        <div class="profile-details form-card">
            <h3>Account Information</h3>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($user_details['username']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user_details['email']); ?></p>
            <p><strong>Location:</strong> <?php echo htmlspecialchars(ucfirst($user_details['location'])); ?></p>
            <p><strong>Account Type:</strong> <?php echo ucfirst(htmlspecialchars($user_details['user_type'])); ?></p>
            
            <?php if (!$is_update_mode): ?>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="action" value="show_update_form">
                    <button type="submit" class="btn-update">Update Profile</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($is_update_mode): ?>
        <div class="update-form-container">
            <h3>Update Details</h3>
            <form method="POST" action="profile.php">
                <input type="hidden" name="action" value="update_profile">

                <label for="new_username">Username:</label>
                <input type="text" id="new_username" name="new_username" 
                        value="<?php echo htmlspecialchars($user_details['username']); ?>" required>

                <!-- 
                    Email field is now READONLY and does not submit a new value 
                    (removed 'name' attribute) to prevent accidental updates.
                -->
                <label for="new_email">Email:</label>
                <input type="email" id="new_email" 
                        value="<?php echo htmlspecialchars($user_details['email']); ?>" required readonly>

                <label for="new_location">Location (Margao, Panjim, Vasco):</label>
                <input type="text" id="new_location" name="new_location" 
                        value="<?php echo htmlspecialchars(ucfirst($user_details['location'])); ?>" required>

                <button type="submit">Save Changes</button>
            </form>
        </div>
        <?php endif; ?>

        <hr>

        <h3>Recent Order History</h3>
        <?php if (!empty($order_history)): ?>
            <table class="order-table cart-table">
                <thead>
                    <tr>
                        <th>Date</th><th>Product</th><th>Quantity</th><th>Total Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_history as $order): ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($order['order_date'])); ?></td>
                            <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                            <td><?php echo htmlspecialchars(number_format($order['quantity'], 3)); ?></td>
                            <td>₹<?php echo htmlspecialchars(number_format($order['total_price'], 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>You have not placed any orders yet. Start shopping now!</p>
        <?php endif; ?>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html> 