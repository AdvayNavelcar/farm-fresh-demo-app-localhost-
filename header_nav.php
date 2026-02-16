<?php 
// This block must be included at the top of every protected page (home.php, admin.php, etc.)
// Make sure session_start() has been called in the parent file.

// Ensure the necessary files are included by the calling script
// This file assumes that $conn and $user_id are available from the calling script (e.g., home.php)
if (!isset($conn) || !isset($user_id)) {
    // If not included, we assume a path error or missing context.
    $db_connect_path = __DIR__ . '/db_connect.php';
    if (file_exists($db_connect_path)) {
        require_once $db_connect_path;
        $conn = $conn ?? $conn;
    }

    // Set user_id if session is active
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $user_id = $_SESSION['user_id'] ?? null;
}

// Ensure the utility function is available (if not included by the parent script)
if (!function_exists('get_cart_item_count')) {
    $cart_utils_path = __DIR__ . '/cart_utils.php';
    if (file_exists($cart_utils_path)) {
        require_once $cart_utils_path;
    }
    // Define a placeholder function if utility function is missing
    if (!function_exists('get_cart_item_count')) {
        function get_cart_item_count($conn, $userId) {
            return 0; 
        }
    }
}

// Fetch the current cart count
$cart_count = 0;
if ($user_id && isset($conn) && function_exists('get_cart_item_count')) {
    $cart_count = get_cart_item_count($conn, $user_id);
}
?>

<style>
    /* Theme Colors based on screenshots */
    :root {
        --primary-color: #38761d; /* Dark Green */
        --secondary-green: #6aa84f; /* Lighter Green for accents/hover */
        --text-color: #333;
        --nav-text-color: #ffffff;
        --logo-color: #f1c232; /* Yellow/Orange for logo */
        --bg-color: #f4f4f4;
    }

    /* Navigation Bar Styling */
    .nav-bar {
        background: linear-gradient(90deg, var(--primary-color) 80%, var(--secondary-green) 100%);
        color: var(--nav-text-color);
        padding: 14px 32px 12px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 18px rgba(56, 118, 29, 0.10);
        border-radius: 0 0 18px 18px;
    }
    .nav-bar .logo {
        font-size: 1.7em;
        font-weight: bold;
        color: var(--logo-color);
        display: flex;
        align-items: center;
        gap: 7px;
        letter-spacing: 0.5px;
        text-shadow: 0 2px 8px rgba(241, 194, 50, 0.08);
    }
    .nav-bar .logo .emoji {
        font-size: 1.5em;
    }
    
    /* *** FIX: Ensure the navigation links are aligned to the right side *** */
    .nav-bar nav {
        display: flex; /* Enable flex container for navigation links */
        align-items: center;
        margin-left: auto; /* Push the navigation block to the far right */
    }
    
    .nav-bar nav a {
        color: var(--nav-text-color);
        text-decoration: none;
        margin-left: 22px;
        padding: 7px 16px;
        border-radius: 6px;
        transition: background 0.18s, color 0.18s, transform 0.16s;
        font-weight: 500;
        font-size: 1.08em;
        position: relative;
        display: inline-block;
    }
    .nav-bar nav a:hover {
        background: rgba(255,255,255,0.13);
        color: var(--logo-color);
        transform: scale(1.07);
        text-shadow: 0 2px 8px rgba(241, 194, 50, 0.08);
    }
    
    /* Cart link styling */
    .nav-bar .cart-icon-link {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    /* General Button Styles (for common use) */
    .btn {
        padding: 8px 15px;
        font-size: 0.9em;
        font-weight: bold;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s;
        text-decoration: none;
        text-align: center;
        color: white;
        background-color: var(--primary-color);
    }
    .btn:hover {
        background-color: var(--secondary-green);
    }
    .btn-small {
        padding: 6px 12px;
        font-size: 0.8em;
        background-color: var(--secondary-green);
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .btn-small:hover {
        background-color: #558a3d; 
    }
    .btn-danger {
        /* Keeping it green for consistency with the cart UI, as per prior discussion */
        background-color: var(--secondary-green);
        color: white;
        padding: 6px 12px;
        font-size: 0.8em;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .btn-danger:hover {
        background-color: #558a3d;
    }
    
</style>

<div class="nav-bar">
    <div class="logo">
        <span class="emoji">🌱</span> Farm Fresh
    </div>
    <nav>
        <a href="home.php">Home</a>
        
        <a href="cart.php" class="cart-icon-link">
            🛒 Cart (<?php echo $cart_count; ?>) 
        </a>
        
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>