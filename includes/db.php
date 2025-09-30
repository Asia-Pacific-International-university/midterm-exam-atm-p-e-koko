<?php
/**
 * Database Configuration and Connection
 * 
 * This file establishes a secure PDO connection to the MySQL database
 * with optimized settings for performance and security.
 * 
 * @author ATM System
 * @version 1.0
 */

// Database configuration constants
$host = "localhost";        // Database host
$db   = "midterm_project"; // Database name
$user = "root";            // Database username
$pass = "";                // Database password

try {
    // Create PDO connection with MySQL-specific options for optimal performance
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        // Set error mode to exceptions for better error handling
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        
        // Use prepared statements emulation for better compatibility
        PDO::ATTR_EMULATE_PREPARES => false,
        
        // Set default fetch mode to associative arrays
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        
        // Enable persistent connections for better performance
        PDO::ATTR_PERSISTENT => true,
        
        // Set timeout to prevent hanging connections
        PDO::ATTR_TIMEOUT => 30
    ]);
    
} catch (PDOException $e) {
    // Log the error for debugging (in production, log to file instead of displaying)
    error_log("Database connection failed: " . $e->getMessage());
    
    // Show user-friendly error message
    die("Database connection failed. Please try again later.");
}
?>
