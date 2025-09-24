<?php
// Helper functions for validation and security

// Clean and sanitize input data
if (!function_exists('cleanInput')) {
function cleanInput($data) {
    $data = trim($data); // remove extra spaces
    $data = stripslashes($data); // remove backslashes
    $data = htmlspecialchars($data); // convert special characters
    return $data;
}
}

// Validate email format
if (!function_exists('validateEmail')) {
function validateEmail($email) {
    $email = cleanInput($email);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return true;
    }
    return false;
}
}

// Validate PIN format (4-6 digits only)
if (!function_exists('validatePin')) {
function validatePin($pin) {
    $pin = cleanInput($pin);
    // Check if PIN is 4-6 digits only
    if (preg_match('/^[0-9]{4,6}$/', $pin)) {
        return true;
    }
    return false;
}
}

// Validate name (letters and spaces only, 2-50 characters)
if (!function_exists('validateName')) {
function validateName($name) {
    $name = cleanInput($name);
    if (strlen($name) >= 2 && strlen($name) <= 50 && preg_match('/^[a-zA-Z\s]+$/', $name)) {
        return true;
    }
    return false;
}
}

// Check if email already exists in database
if (!function_exists('emailExists')) {
function emailExists($email, $pdo) {
    $sql = "SELECT id FROM users WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    
    if ($stmt->fetch()) {
        return true; // email exists
    }
    return false; // email doesn't exist
}
}

// Generate error messages for validation
if (!function_exists('getValidationErrors')) {
function getValidationErrors($name, $email, $pin) {
    $errors = array();
    
    if (!validateName($name)) {
        $errors[] = "Name must be 2-50 characters and contain only letters and spaces.";
    }
    
    if (!validateEmail($email)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (!validatePin($pin)) {
        $errors[] = "PIN must be 4-6 digits only.";
    }
    
    return $errors;
}
}

// Log user activity
if (!function_exists('logActivity')) {
function logActivity($userId, $activityType, $description = null, $pdo = null) {
    if (!$pdo) {
        require_once 'db.php';
        global $pdo;
    }
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $sql = "INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent) 
            VALUES (:user_id, :activity_type, :description, :ip_address, :user_agent)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':activity_type' => $activityType,
        ':description' => $description,
        ':ip_address' => $ipAddress,
        ':user_agent' => $userAgent
    ]);
}
}

// Get recent activities for a user
if (!function_exists('getRecentActivities')) {
function getRecentActivities($userId, $limit = 5, $pdo = null) {
    if (!$pdo) {
        require_once 'db.php';
        global $pdo;
    }
    
    $sql = "SELECT activity_type, description, ip_address, created_at 
            FROM user_activities 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}

// Format activity type for display
if (!function_exists('formatActivityType')) {
function formatActivityType($activityType) {
    switch($activityType) {
        case 'login':
            return '🔓 Successful Login';
        case 'logout':
            return '🔒 Logout';
        case 'failed_login':
            return '❌ Failed Login Attempt';
        case 'account_locked':
            return '🚫 Account Locked';
        case 'deposit':
            return '💰 Deposit';
        case 'withdraw':
            return '💸 Withdrawal';
        default:
            return '📝 ' . ucfirst(str_replace('_', ' ', $activityType));
    }
}
}

// Return only an emoji/icon for an activity type
if (!function_exists('activityIcon')) {
function activityIcon($activityType) {
    switch($activityType) {
        case 'login': return '🔓';
        case 'logout': return '🔒';
        case 'failed_login': return '❌';
        case 'account_locked': return '🚫';
        case 'deposit': return '💰';
        case 'withdraw': return '💸';
        default: return '📝';
    }
}
}

// Return only a clean text label for an activity type
if (!function_exists('activityLabel')) {
function activityLabel($activityType) {
    switch($activityType) {
        case 'login': return 'Successful Login';
        case 'logout': return 'Logout';
        case 'failed_login': return 'Failed Login Attempt';
        case 'account_locked': return 'Account Locked';
        case 'deposit': return 'Deposit';
        case 'withdraw': return 'Withdrawal';
        default: return ucfirst(str_replace('_', ' ', $activityType));
    }
}
}

// Process transaction (deposit or withdraw)
if (!function_exists('processTransaction')) {
function processTransaction($userId, $type, $amount, $newBalance, $pdo) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Update user balance
        $updateSql = "UPDATE users SET balance = :balance WHERE id = :id";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            ':balance' => $newBalance,
            ':id' => $userId
        ]);
        
        // Record transaction in transactions table
        $transactionSql = "INSERT INTO transactions (user_id, type, amount, balance_after) VALUES (:user_id, :type, :amount, :balance_after)";
        $transactionStmt = $pdo->prepare($transactionSql);
        $transactionStmt->execute([
            ':user_id' => $userId,
            ':type' => $type,
            ':amount' => $amount,
            ':balance_after' => $newBalance
        ]);
        
        // Commit transaction
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        return false;
    }
}
}
?>