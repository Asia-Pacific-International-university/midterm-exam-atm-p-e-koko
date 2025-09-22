<?php
session_start(); // start session at the top
include "includes/helpers.php";

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$message = ""; // store error messages

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Clean input
    $email = cleanInput($_POST['email']);
    $pin   = cleanInput($_POST['pin']);
    
    // Basic input check (not showing specific errors for security)
    if (!empty($email) && !empty($pin)) {
        require "includes/auth.php"; // check login logic
    } else {
        $message = "Please enter both email and PIN.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <title>Login</title>
</head>
<body>
    <form action="login.php" method="POST">
        <h2>Login</h2>
        <?php if ($message != ""): ?>
            <p style="color: red; text-align:center;"><?php echo $message; ?></p>
        <?php endif; ?>
        
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>

        <label for="pin">PIN:</label>
        <input type="password" id="pin" name="pin" required>

        <button type="submit">Login</button>
        
        <p style="text-align: center; margin-top: 15px;">
            Don't have an account? <a href="register.php">Register here</a>
        </p>
    </form>
</body>
</html>
