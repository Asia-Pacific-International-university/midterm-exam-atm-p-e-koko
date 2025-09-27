<?php
    session_start(); // start session to access session variables
    
    // Include required files
    require_once "includes/db.php";
    require_once "includes/helpers.php";
    
    // Log logout activity if user was logged in
    if (isset($_SESSION['user_id'])) {
        log_activity($_SESSION['user_id'], 'logout', 'User logged out', $pdo);
    }

    // Clear all session data
    session_unset(); // remove all session variables
    session_destroy(); // destroy the session

    // Redirect to login page
    header("Location: login.php");
    exit; // stop execution after redirect
?>
