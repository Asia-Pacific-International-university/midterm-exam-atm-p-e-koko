<?php
    session_start(); // start session to access session variables

    // Clear all session data
    session_unset(); // remove all session variables
    session_destroy(); // destroy the session

    // Redirect to login page
    header("Location: login.php");
    exit; // stop execution after redirect
?>
