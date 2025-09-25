<?php
session_start();

// Include the main authentication functions and database connection
include "../includes/db.php";
include "../includes/auth.php";
include "../includes/helpers.php";

// Check if user is logged in and has admin role
requireAdmin($pdo);

// Get current admin user data
$currentAdmin = getCurrentUser($pdo);

if (!$currentAdmin) {
    // Something went wrong with session, redirect to login
    header("Location: ../login.php");
    exit;
}

// Set admin session variables if not set
if (!isset($_SESSION['admin_logged_in'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $currentAdmin['id'];
    $_SESSION['admin_name'] = $currentAdmin['name'];
}

// Function to get all users (admin function)
function getAllUsers($pdo) {
    $sql = "SELECT id, name, email, balance, role, failed_attempts, lock_until, created_at 
            FROM users 
            ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get user by ID (admin function)
function getUserById($pdo, $userId) {
    $sql = "SELECT id, name, email, balance, role, failed_attempts, lock_until, created_at, updated_at 
            FROM users 
            WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get user's transactions (admin function)
function getUserTransactions($pdo, $userId, $limit = 50) {
    $sql = "SELECT id, type, amount, balance_after, memo, created_at 
            FROM transactions 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to lock/unlock user account (admin function)
function toggleUserLock($pdo, $userId, $lock = true) {
    if ($lock) {
        // Lock account for 24 hours
        $lockUntil = (new DateTime())->add(new DateInterval('PT24H'))->format('Y-m-d H:i:s');
        $sql = "UPDATE users SET lock_until = :lock_until WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([':lock_until' => $lockUntil, ':id' => $userId]);
        
        if ($result) {
            // Log admin action
            logActivity($userId, 'account_locked', 'Account manually locked by admin', $pdo);
        }
        
        return $result;
    } else {
        // Unlock account
        $sql = "UPDATE users SET lock_until = NULL, failed_attempts = 0 WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([':id' => $userId]);
        
        if ($result) {
            // Log admin action
            logActivity($userId, 'account_unlocked', 'Account manually unlocked by admin', $pdo);
        }
        
        return $result;
    }
}

// Function to check if user account is locked
function isUserLocked($user) {
    return $user['lock_until'] && new DateTime() < new DateTime($user['lock_until']);
}

// Function to get total system statistics (admin function)
function getSystemStats($pdo) {
    $stats = [];
    
    // Total users
    $sql = "SELECT COUNT(*) as total_users FROM users WHERE role = 'user'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];
    
    // Total admins
    $sql = "SELECT COUNT(*) as total_admins FROM users WHERE role = 'admin'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats['total_admins'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_admins'];
    
    // Locked accounts
    $sql = "SELECT COUNT(*) as locked_accounts FROM users WHERE lock_until IS NOT NULL AND lock_until > NOW()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats['locked_accounts'] = $stmt->fetch(PDO::FETCH_ASSOC)['locked_accounts'];
    
    // Total transactions today
    $sql = "SELECT COUNT(*) as transactions_today FROM transactions WHERE DATE(created_at) = CURDATE()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats['transactions_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['transactions_today'];
    
    // Total system balance
    $sql = "SELECT SUM(balance) as total_balance FROM users";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats['total_balance'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_balance'] ?? 0;
    
    return $stats;
}
?>