<?php
// PHP SCRIPT START

// IMPORTANT: Do NOT call session_start() yet. We need to check for the persistent cookie first.

// **FIXED PATH:** Use __DIR__ to guarantee the include path is relative to the current file's directory.
require_once __DIR__ . '/includes/db_connect.php';

$message = '';

// ----------------------------------------------------
// 1. AUTO-LOGIN CHECK (If the persistent cookie exists but the session doesn't)
// This block must run BEFORE any redirects or content is sent.

// Start the session (must be after all cookie settings/checks)
session_start();

if (!isset($_SESSION['user_id']) && isset($_COOKIE['auth_token'])) {
    $cookie_token = $_COOKIE['auth_token'];
    
    // Look up the user by the persistent token
    $stmt = $conn->prepare("SELECT id, username, user_type, location FROM users WHERE auth_token = ?");
    if ($stmt === false) {
        error_log("Failed to prepare auto-login query: " . $conn->error);
    } else {
        $stmt->bind_param("s", $cookie_token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            // Token validated successfully! Restore the session.
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['location'] = $user['location'];

            // Redirect the user to their default page immediately
            $redirect = ($user['user_type'] == 'admin') ? 'admin.php' : 'home.php';
            header("Location: $redirect");
            exit();
        } else {
            // Token is invalid (e.g., expired or tampered with). Delete the bad cookie.
            setcookie('auth_token', '', time() - 3600, "/");
        }
    }
}

// ----------------------------------------------------
// 2. LOGIC FOR FORM SUBMISSIONS (LOGIN/SIGNUP)
// The rest of your existing logic goes here
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    if ($action == 'login') {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT id, password, user_type, location FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                // Successful Login: Set Session Variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['location'] = $user['location'];

                // Handle 'Remember Me' (Persistent Cookie)
                if (isset($_POST['remember'])) {
                    $cookie_token = bin2hex(random_bytes(32));
                    $expire_time = time() + (86400 * 7); // 7 days
                    
                    // 1. Set the persistent cookie in the browser
                    setcookie('auth_token', $cookie_token, [
                        'expires' => $expire_time,
                        'path' => '/',
                        'secure' => false, // Set to true if you are using HTTPS
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                    
                    // 2. Save the token to the database
                    $update_stmt = $conn->prepare("UPDATE users SET auth_token = ? WHERE id = ?");
                    if ($update_stmt === false) {
                           // Log error if the query preparation fails
                           error_log("Failed to prepare token update query: " . $conn->error); 
                           $message = "Login failed due to a server error. Please try again later.";
                    } else {
                        $update_stmt->bind_param("si", $cookie_token, $user['id']);
                        $update_stmt->execute();
                    }
                }

                $redirect = ($user['user_type'] == 'admin') ? 'admin.php' : 'home.php';
                header("Location: $redirect");
                exit();
            } else {
                $message = "Invalid username or password.";
            }
        } else {
            $message = "Invalid username or password.";
        }
    } 
    
    else if ($action == 'signup') {
        $username = $_POST['username'];
        $email = $_POST['email'];
        
        // --- NEW PASSWORD VALIDATION: Server-side check ---
        $raw_password = $_POST['password'];

        // 1. Check minimum length (6 characters)
        if (strlen($raw_password) < 6) {
            $message = "Signup failed: Password must be at least 6 characters long.";
            goto end_signup;
        }
        
        // 2. Check characters (letters and/or numbers only)
        if (!preg_match("/^[a-zA-Z0-9]+$/", $raw_password)) {
            $message = "Signup failed: Password can only contain letters and numbers.";
            goto end_signup;
        }
        // --- END NEW PASSWORD VALIDATION ---

        $password = password_hash($raw_password, PASSWORD_DEFAULT); // Hash the validated password
        
        $location_raw = trim($_POST['location']);
        $location = strtolower($location_raw);

        // Validation for allowed locations
        $allowed_locations = ['margao', 'panjim', 'vasco'];
        if (!in_array($location, $allowed_locations)) {
             $message = "Signup failed: Location must be Margao, Panjim, or Vasco.";
             goto end_signup;
        }

        $admin_code = trim($_POST['admin_code']);
        $user_type = 'customer';

        // Admin Code Check
        if ($admin_code === '1234') {
            $user_type = 'admin';
        }

        // --- FIXED SIGNUP QUERY: Added 'auth_token' column and bound the NULL value
        $null_token = NULL; 
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, location, user_type, auth_token) VALUES (?, ?, ?, ?, ?, ?)");
        
        if ($stmt === false) {
            error_log("Failed to prepare signup query: " . $conn->error);
            $message = "Signup failed due to a server error.";
        } else {
            $stmt->bind_param("ssssss", $username, $email, $password, $location, $user_type, $null_token);

            if ($stmt->execute()) {
                $message = "Signup successful! You are now a " . $user_type . ". Please log in.";
            } else {
                $message = "Signup failed. Username or email may already be taken. (" . $conn->error . ")";
            }
        }
        end_signup:
    }
}


// --- HTML VIEW ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login / Signup</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="auth-container">
        <h2>Farm Fresh - Access</h2>
        <?php if ($message) echo "<p class='message'>$message</p>"; ?>

        <!-- LOGIN FORM -->
        <form action="login.php" method="POST" class="form-card">
            <h3>Login</h3>
            <input type="hidden" name="action" value="login">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <label><input type="checkbox" name="remember"> Remember Me (7 days)</label>
            <button type="submit">Log In</button>
        </form>

        <!-- SIGN UP FORM -->
        <form action="login.php" method="POST" class="form-card">
            <h3>Sign Up</h3>
            <input type="hidden" name="action" value="signup">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            
            <!-- --- Client-side validation: Min 6 characters, letters/numbers only --- -->
            <input type="password" 
                   name="password" 
                   placeholder="Password (Min 6 chars, letters/numbers only)" 
                   required
                   minlength="6"
                   pattern="[a-zA-Z0-9]+"
                   title="Password must be at least 6 characters long and contain only letters (A-Z, a-z) and numbers (0-9)."
            >
            
            <input type="text" name="location" placeholder="Your Delivery Location (Margao, Panjim, Vasco)" required>
            <input type="password" name="admin_code" placeholder="Optional Admin Code (1234)" pattern="^$|^1234$">
            <button type="submit">Sign Up</button>
        </form>
    </div>
</body>
</html>