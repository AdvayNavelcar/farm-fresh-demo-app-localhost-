<?php
// PHP logic to connect to DB and fetch products will go here.
// For now, using a placeholder array
$products = [
    ['id' => 1, 'name' => 'Organic Tomatoes', 'category' => 'vegetable', 'unit_type' => 'kg', 'placeholder_text' => 'VEG - TOMATO'],
    ['id' => 2, 'name' => 'Green Apples', 'category' => 'fruit', 'unit_type' => 'kg', 'placeholder_text' => 'FRUIT - APPLE'],
    ['id' => 3, 'name' => 'Fresh Basil', 'category' => 'herb', 'unit_type' => 'g', 'placeholder_text' => 'HERB - BASIL']
];

session_start();
// Check if user is logged in... redirect if not
// if (!isset($_SESSION['user_id'])) { header('Location: login_signup.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Fresh - Shop</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <h1>🌱 Farm Fresh Shop</h1>
        <nav>
            <a href="customer.php">Home</a>
            <a href="profile.php">Profile</a>
            <a href="cart.php">🛒 Cart</a>
            <a href="login_signup.php?logout=true">Logout</a>
        </nav>
    </header>

    <main style="padding: 20px; display: flex; flex-wrap: wrap;">
        <h2>🛒 Seasonal Picks</h2>
        <?php foreach ($products as $product): ?>
            <div class="product-card">
                <div class="product-image-placeholder">
                    <?php echo htmlspecialchars($product['placeholder_text']); ?>
                </div>

                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                
                <form action="cart.php" method="POST" class="add-to-cart-form">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <label for="qty_<?php echo $product['id']; ?>">Quantity (<?php echo $product['unit_type']; ?>):</label>
                    
                    <select name="quantity" id="qty_<?php echo $product['id']; ?>" required>
                        <?php if ($product['unit_type'] === 'kg'): ?>
                            <option value="0.25">250 g</option>
                            <option value="0.50">500 g</option>
                            <option value="0.75">750 g</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> kg</option>
                            <?php endfor; ?>
                        <?php else: ?>
                            <option value="25">25 g</option>
                            <option value="50">50 g</option>
                            <option value="75">75 g</option>
                            <option value="100">100 g</option>
                        <?php endif; ?>
                    </select>

                    <button type="submit" class="btn-primary" style="margin-top: 10px;">Add to Cart</button>
                </form>
            </div>
        <?php endforeach; ?>
    </main>
    <script src="script.js"></script>
    <?php include 'footer.php'; ?>

</body>
</html>