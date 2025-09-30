<?php
/**
 * Authentication Functions and User Session Management
 * 
 * This file contains all authentication-related functions including login validation,
 * session management, role-based access control, and account security features.
 * 
 * Features:
 * - User login/logout functionality
 * - Session validation and management
 * - Role-based access control (admin/user)
 * - Account lockout protection against brute force attacks
 * - Activity logging for security monitoring
 * 
 * @author ATM System
 * @version 1.0
 */

// Include required dependencies
include "db.php";      // Database connection
include "helpers.php"; // Helper functions for validation and utilities

/**
 * Check if a user is currently logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require user authentication - redirects to login if not authenticated
 * 
 * @param string $redirectTo URL to redirect to if not logged in
 * @return void
 */
function requireLogin($redirectTo = 'login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirectTo");
        exit;
    }
}

/**
 * Get current authenticated user's data from database
 * 
 * @param PDO $pdo Database connection object
 * @return array|null User data array or null if not found/logged in
 */
function getCurrentUser($pdo) {
    if (!isLoggedIn()) {
        return null;
    }
    
    $sql = "SELECT id, name, email, balance, role FROM users WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Check if current user has admin role
 * 
 * @param PDO $pdo Database connection object
 * @return bool True if user is admin, false otherwise
 */
function isAdmin($pdo) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user = getCurrentUser($pdo);
    return $user && $user['role'] === 'admin';
}

/**
 * Require admin access - redirects if user is not admin
 * 
 * @param PDO $pdo Database connection object
 * @param string $redirectTo URL to redirect to if access denied
 * @return void
 */
function requireAdmin($pdo, $redirectTo = '../login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirectTo");
        exit;
    }
    
    if (!isAdmin($pdo)) {
        header("Location: ../dashboard.php?error=access_denied");
        exit;
    }
}

/**
 * Verify that the user's session is still valid by checking database
 * This ensures users are logged out if their account is deleted/disabled
 * 
 * @param PDO $pdo Database connection object
 * @return bool True if session is valid, false otherwise
 */
function verifyUserSession($pdo) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user = getCurrentUser($pdo);
    if (!$user) {
        // User no longer exists in database, destroy session for security
        session_destroy();
        return false;
    }
    
    return true;
}

/**
 * ========================================================================
 * LOGIN PROCESSING LOGIC
 * ========================================================================
 * 
 * This section handles user authentication when login form is submitted.
 * Features:
 * - Email and PIN validation
 * - Account lockout protection (brute force prevention)
 * - Failed attempt tracking and automatic unlocking
 * - Comprehensive activity logging for security monitoring
 * - Session management and secure redirects
 */

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['pin'])) {
    // Sanitize and validate input data
    $email = cleanInput($_POST['email']);
    $pin   = cleanInput($_POST['pin']);

    // Fetch user account data including security information
    $sql  = "SELECT id, name, email, pin, failed_attempts, lock_until FROM users WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Security Check: Verify if account is currently locked due to failed attempts
        if ($user['lock_until'] && new DateTime() < new DateTime($user['lock_until'])) {
            // Calculate remaining lock time for user feedback
            $lockTimeRemaining = (new DateTime($user['lock_until']))->diff(new DateTime())->s;
            $message = "Account is locked. Please wait " . $lockTimeRemaining . " seconds before trying again.";
            
            // Log the attempted login on locked account for security monitoring
            logActivity($user['id'], 'account_locked', 'Attempted login while account locked', $pdo);
        } 
        // Authenticate: Verify the provided PIN against stored hash
        else if (password_verify($pin, $user['pin'])) {
            // SUCCESS: Authentication successful - reset security counters
            $resetSql = "UPDATE users SET failed_attempts = 0, lock_until = NULL WHERE id = :id";
            $resetStmt = $pdo->prepare($resetSql);
            $resetStmt->execute([':id' => $user['id']]);
            
            // Establish secure user session
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            
            // Log successful authentication for security audit trail
            logActivity($user['id'], 'login', 'Successful login from ' . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown IP'), $pdo);
            
            // Redirect to dashboard after successful authentication
            header("Location: dashboard.php");
            exit; // Prevent further execution after redirect
            
        } else {
            // FAILED: Wrong PIN provided - implement brute force protection
            $failedAttempts = $user['failed_attempts'] + 1;
            
            // Security: Lock account after 5 consecutive failed attempts
            if ($failedAttempts >= 5) {
                // Calculate lock expiration time (10 seconds from now)
                $lockUntil = (new DateTime())->add(new DateInterval('PT10S'))->format('Y-m-d H:i:s');
                
                // Update database with lock information
                $updateSql = "UPDATE users SET failed_attempts = :failed_attempts, lock_until = :lock_until WHERE id = :id";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([
                    ':failed_attempts' => $failedAttempts,
                    ':lock_until' => $lockUntil,
                    ':id' => $user['id']
                ]);
                
                $message = "Too many failed attempts. Account locked for 10 seconds.";
                
                // Log account lockout for security monitoring
                logActivity($user['id'], 'account_locked', 'Account locked after 5 failed login attempts', $pdo);
                
            } else {
                // Increment failed attempts counter without locking
                $updateSql = "UPDATE users SET failed_attempts = :failed_attempts WHERE id = :id";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([
                    ':failed_attempts' => $failedAttempts,
                    ':id' => $user['id']
                ]);
                
                // Provide generic error message for security (don't reveal account exists)
                $message = "Wrong email or pin";
            }
            
            // Log all failed login attempts for security monitoring
            logActivity($user['id'], 'failed_login', 'Failed login attempt with wrong PIN', $pdo);
        }
        
    } else {
        // Security: Don't reveal whether email exists - use generic error message
        $message = "Wrong username or password.";
        
        // Optional: Log attempted login with non-existent email for security monitoring
        // Note: We don't have a user ID in this case, so this would need different logging approach
    }
}
?>