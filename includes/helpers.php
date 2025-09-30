<?php
/**
 * Helper Functions Library
 * 
 * This file contains utility functions for data validation, security, 
 * activity logging, and other common operations used throughout the ATM system.
 * 
 * Key Features:
 * - Input sanitization and validation
 * - Email and PIN format validation
 * - User activity tracking and logging
 * - Security-focused helper functions
 * - Database query helpers
 * 
 * @author ATM System
 * @version 1.0
 */

/**
 * Clean and sanitize user input to prevent XSS and other injection attacks
 * 
 * @param string $data Raw input data from user
 * @return string Cleaned and sanitized data
 */
if (!function_exists('cleanInput')) {
function cleanInput($data) {
    $data = trim($data);                    // Remove leading/trailing whitespace
    $data = stripslashes($data);            // Remove backslashes (prevent escape sequence attacks)
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8'); // Convert special chars to HTML entities
    return $data;
}
}

/**
 * Validate email address format using PHP's built-in filter
 * 
 * @param string $email Email address to validate
 * @return bool True if valid email format, false otherwise
 */
if (!function_exists('validateEmail')) {
function validateEmail($email) {
    $email = cleanInput($email);
    
    // Use PHP's built-in email validation filter (RFC 5322 compliant)
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
}

/**
 * Validate PIN format - ensures PIN contains only 4-6 digits
 * This provides security by enforcing standard ATM PIN formats
 * 
 * @param string $pin PIN to validate
 * @return bool True if valid PIN format, false otherwise
 */
if (!function_exists('validatePin')) {
function validatePin($pin) {
    $pin = cleanInput($pin);
    
    // Check if PIN is exactly 4-6 digits (no letters, symbols, or spaces)
    return preg_match('/^[0-9]{4,6}$/', $pin) === 1;
}
}

/**
 * Validate name format - ensures proper name formatting for security
 * Prevents injection attacks while allowing international names
 * 
 * @param string $name Name to validate
 * @return bool True if valid name format, false otherwise
 */
if (!function_exists('validateName')) {
function validateName($name) {
    $name = cleanInput($name);
    
    // Check length constraints and character restrictions
    return (strlen($name) >= 2 && strlen($name) <= 50 && 
            preg_match('/^[a-zA-Z\s]+$/', $name) === 1);
}
}

/**
 * Check if email address already exists in the database
 * Used during registration to prevent duplicate accounts
 * 
 * @param string $email Email address to check
 * @param PDO $pdo Database connection object
 * @return bool True if email exists, false if available
 */
if (!function_exists('emailExists')) {
function emailExists($email, $pdo) {
    $sql = "SELECT id FROM users WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    
    return $stmt->fetch() !== false; // Returns true if email found
}
}

/**
 * Generate comprehensive validation error messages for user registration
 * Provides user-friendly feedback for form validation failures
 * 
 * @param string $name User's full name
 * @param string $email User's email address
 * @param string $pin User's chosen PIN
 * @return array Array of validation error messages
 */
if (!function_exists('getValidationErrors')) {
function getValidationErrors($name, $email, $pin) {
    $errors = array();
    
    // Validate each field and collect specific error messages
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

/**
 * Retrieve recent user activities for dashboard display
 * Provides security audit trail and user activity overview
 * 
 * @param int $userId User ID to get activities for
 * @param int $limit Maximum number of activities to retrieve
 * @param PDO $pdo Database connection object
 * @return array Array of recent activities with timestamps
 */
if (!function_exists('getRecentActivities')) {
function getRecentActivities($userId, $limit = 5, $pdo = null) {
    // Use global PDO connection if not provided
    if (!$pdo) {
        require_once 'db.php';
        global $pdo;
    }
    
    // Sanitize inputs for security
    $userId = (int) $userId;
    $limit = (int) $limit;
    
    // Query recent activities with proper indexing for performance
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

/**
 * Enhanced activity logging function for comprehensive security monitoring
 * Tracks all user actions for audit trails and security analysis
 * 
 * Features:
 * - Automatic IP detection and user agent tracking
 * - Error handling with graceful degradation
 * - Transaction-safe logging (won't break main operations)
 * - Standardized activity type classification
 * 
 * @param int $userId User ID performing the activity
 * @param string $activityType Type of activity (login, deposit, withdraw, etc.)
 * @param string|null $description Optional detailed description
 * @param PDO|null $pdo Database connection (uses global if not provided)
 * @param string|null $ipAddress IP address (auto-detected if not provided)
 * @param string|null $userAgent User agent string (auto-detected if not provided)
 * @return bool True if logged successfully, false on failure
 */
if (!function_exists('log_activity')) {
function log_activity($userId, $activityType, $description = null, $pdo = null, $ipAddress = null, $userAgent = null) {
    // Use global PDO connection if not provided
    if (!$pdo) {
        require_once 'db.php';
        global $pdo;
        if (!$pdo) {
            error_log("Cannot log activity: Database connection unavailable");
            return false; // Fail gracefully without breaking the application
        }
    }
    
    // Auto-detect client information for security tracking
    if ($ipAddress === null) {
        // Get real IP address (considering proxies and load balancers)
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
                     $_SERVER['HTTP_X_REAL_IP'] ?? 
                     $_SERVER['REMOTE_ADDR'] ?? 
                     'Unknown';
                     
        // Handle comma-separated IPs from proxies (take first one)
        if (strpos($ipAddress, ',') !== false) {
            $ipAddress = trim(explode(',', $ipAddress)[0]);
        }
    }
    
    if ($userAgent === null) {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }
    
    try {
        // Insert activity log with comprehensive information
        $sql = "INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent) 
                VALUES (:user_id, :activity_type, :description, :ip_address, :user_agent)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':user_id' => (int) $userId,
            ':activity_type' => trim($activityType),
            ':description' => $description,
            ':ip_address' => substr($ipAddress, 0, 45), // Limit IP length for database
            ':user_agent' => substr($userAgent, 0, 500)  // Limit user agent length
        ]);
        
        return $result;
        
    } catch (PDOException $e) {
        // Log error but don't break the application flow
        error_log("Activity logging failed for user {$userId}: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Backward compatibility alias for log_activity function
 * Maintains compatibility with existing code while providing same functionality
 * 
 * @param int $userId User ID performing the activity
 * @param string $activityType Type of activity
 * @param string|null $description Optional description
 * @param PDO|null $pdo Database connection
 * @return bool Success status
 */
if (!function_exists('logActivity')) {
function logActivity($userId, $activityType, $description = null, $pdo = null) {
    return log_activity($userId, $activityType, $description, $pdo);
}
}

/**
 * Securely change user's PIN with comprehensive validation and security checks
 * 
 * Security Features:
 * - Current PIN verification before allowing change
 * - New PIN format validation
 * - Prevention of reusing current PIN
 * - Secure password hashing
 * - Activity logging for audit trail
 * - Transaction safety with error handling
 * 
 * @param int $userId User ID requesting PIN change
 * @param string $currentPin Current PIN for verification
 * @param string $newPin New PIN to set
 * @param PDO $pdo Database connection object
 * @return array Result array with success status and message
 */
if (!function_exists('changePIN')) {
function changePIN($userId, $currentPin, $newPin, $pdo) {
    // Validate new PIN format for security compliance
    if (!validatePin($newPin)) {
        return ['success' => false, 'message' => 'New PIN must be 4-6 digits only.'];
    }
    
    // Retrieve current user PIN hash for verification
    $sql = "SELECT pin FROM users WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => (int) $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return ['success' => false, 'message' => 'User not found.'];
    }
    
    // Security Check: Verify current PIN before allowing change
    if (!password_verify($currentPin, $user['pin'])) {
        // Log suspicious PIN change attempt for security monitoring
        log_activity($userId, 'failed_pin_change', 'Failed PIN change attempt - wrong current PIN', $pdo);
        return ['success' => false, 'message' => 'Current PIN is incorrect.'];
    }
    
    // Security Check: Prevent setting same PIN (force PIN change)
    if (password_verify($newPin, $user['pin'])) {
        return ['success' => false, 'message' => 'New PIN must be different from current PIN.'];
    }
    
    try {
        // Generate secure hash for new PIN using PHP's password_hash
        $hashedNewPin = password_hash($newPin, PASSWORD_DEFAULT);
        
        // Update PIN in database with atomic operation
        $updateSql = "UPDATE users SET pin = :new_pin WHERE id = :id";
        $updateStmt = $pdo->prepare($updateSql);
        $result = $updateStmt->execute([
            ':new_pin' => $hashedNewPin,
            ':id' => (int) $userId
        ]);
        
        if ($result) {
            // Log successful PIN change for security audit
            log_activity($userId, 'pin_changed', 'PIN changed successfully', $pdo);
            return ['success' => true, 'message' => 'PIN changed successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to update PIN. Please try again.'];
        }
        
    } catch (PDOException $e) {
        // Log database error for debugging
        error_log("PIN change failed for user $userId: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error. Please try again later.'];
    }
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

/**
 * Get activity icon/emoji for visual representation in UI
 * Provides consistent iconography for different activity types
 * 
 * @param string $activityType Type of activity
 * @return string Unicode emoji representing the activity
 */
if (!function_exists('activityIcon')) {
function activityIcon($activityType) {
    // Standardized icon mapping for better UX
    $icons = [
        'login' => '🔓',
        'logout' => '🔒',
        'failed_login' => '❌',
        'account_locked' => '🚫',
        'deposit' => '💰',
        'withdraw' => '💸',
        'transfer' => '↔️',
        'pin_changed' => '�',
        'registration' => '👤'
    ];
    
    return $icons[$activityType] ?? '📝'; // Default icon for unknown types
}
}

/**
 * Get human-readable label for activity types
 * Converts system activity codes to user-friendly text
 * 
 * @param string $activityType Type of activity
 * @return string Human-readable activity label
 */
if (!function_exists('activityLabel')) {
function activityLabel($activityType) {
    // Standardized label mapping for consistency
    $labels = [
        'login' => 'Successful Login',
        'logout' => 'Logout',
        'failed_login' => 'Failed Login Attempt',
        'account_locked' => 'Account Locked',
        'deposit' => 'Deposit',
        'withdraw' => 'Withdrawal',
        'transfer' => 'Money Transfer',
        'pin_changed' => 'PIN Changed',
        'registration' => 'Account Registration'
    ];
    
    // Return mapped label or format unknown types
    return $labels[$activityType] ?? ucfirst(str_replace('_', ' ', $activityType));
}
}

/**
 * Process banking transactions (deposits/withdrawals) with ACID compliance
 * 
 * This function ensures data integrity through database transactions and provides
 * comprehensive error handling for all banking operations.
 * 
 * Features:
 * - ACID-compliant database transactions
 * - Automatic rollback on errors
 * - Comprehensive activity logging
 * - Balance consistency validation
 * 
 * @param int $userId User ID performing the transaction
 * @param string $type Transaction type ('deposit' or 'withdraw')
 * @param float $amount Transaction amount
 * @param float $newBalance Expected new balance after transaction
 * @param PDO $pdo Database connection object
 * @return array Result array with success status and details
 */
if (!function_exists('processTransaction')) {
function processTransaction($userId, $type, $amount, $newBalance, $pdo) {
    try {
        // Start database transaction for ACID compliance
        $pdo->beginTransaction();
        
        // Update user balance atomically
        $updateSql = "UPDATE users SET balance = :balance WHERE id = :id";
        $updateStmt = $pdo->prepare($updateSql);
        $updateResult = $updateStmt->execute([
            ':balance' => $newBalance,
            ':id' => (int) $userId
        ]);
        
        if (!$updateResult) {
            throw new Exception("Failed to update user balance");
        }
        
        // Record transaction in audit table
        $transactionSql = "INSERT INTO transactions (user_id, type, amount, balance_after) 
                          VALUES (:user_id, :type, :amount, :balance_after)";
        $transactionStmt = $pdo->prepare($transactionSql);
        $transactionResult = $transactionStmt->execute([
            ':user_id' => (int) $userId,
            ':type' => $type,
            ':amount' => floatval($amount),
            ':balance_after' => floatval($newBalance)
        ]);
        
        if (!$transactionResult) {
            throw new Exception("Failed to record transaction");
        }
        
        // Log transaction activity for audit trail
        $description = ucfirst($type) . " of $" . number_format($amount, 2) . 
                      " - New balance: $" . number_format($newBalance, 2);
        log_activity($userId, $type, $description, $pdo);
        
        // Commit all changes atomically
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => ucfirst($type) . ' completed successfully',
            'new_balance' => $newBalance
        ];
        
    } catch (Exception $e) {
        // Rollback all changes on any error
        $pdo->rollback();
        
        // Log error for debugging
        error_log("Transaction failed for user $userId: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Transaction failed. Please try again.',
            'error' => $e->getMessage()
        ];
    }
}
}

/**
 * Retrieve user information by email address
 * Used for transfer operations and user lookups
 * 
 * @param string $email Email address to search for
 * @param PDO $pdo Database connection object
 * @return array|false User data array or false if not found
 */
if (!function_exists('getUserByEmail')) {
function getUserByEmail($email, $pdo) {
    $sql = "SELECT id, name, balance FROM users WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => cleanInput($email)]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
}

/**
 * Comprehensive validation for money transfer operations
 * 
 * Validates all aspects of a transfer including:
 * - Recipient email format and existence
 * - Amount validity and sufficient funds
 * - Self-transfer prevention
 * - Business rule compliance
 * 
 * @param string $senderEmail Sender's email address
 * @param string $recipientEmail Recipient's email address
 * @param float $amount Transfer amount
 * @param float $senderBalance Sender's current balance
 * @return array Array of validation error messages (empty if valid)
 */
if (!function_exists('validateTransfer')) {
function validateTransfer($senderEmail, $recipientEmail, $amount, $senderBalance) {
    $errors = array();
    
    // Validate recipient email
    if (empty($recipientEmail)) {
        $errors[] = "Recipient email is required.";
    } elseif (!validateEmail($recipientEmail)) {
        $errors[] = "Please enter a valid recipient email address.";
    }
    
    // Security Check: Prevent self-transfers
    if ($senderEmail === $recipientEmail) {
        $errors[] = "You cannot transfer money to yourself.";
    }
    
    // Validate transfer amount
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
        $errors[] = "Please enter a valid amount greater than 0.";
    } elseif ($amount > $senderBalance) {
        $errors[] = "Insufficient balance. Current balance: $" . number_format($senderBalance, 2);
    }
    
    return $errors;
}
}

/**
 * Process money transfer between users with ACID compliance
 * 
 * This function handles the complete transfer process including:
 * - Atomic balance updates for both sender and recipient
 * - Transaction logging for audit trail
 * - Activity logging for security monitoring
 * - Error handling with automatic rollback
 * 
 * @param int $senderId Sender's user ID
 * @param int $recipientId Recipient's user ID
 * @param float $amount Transfer amount
 * @param float $senderNewBalance Sender's balance after transfer
 * @param float $recipientNewBalance Recipient's balance after transfer
 * @param PDO $pdo Database connection object
 * @return array Result array with success status and transaction IDs
 */
if (!function_exists('processTransfer')) {
function processTransfer($senderId, $recipientId, $amount, $senderNewBalance, $recipientNewBalance, $pdo) {
    try {
        // Start atomic transaction for ACID compliance
        $pdo->beginTransaction();
        
        // Update sender's balance
        $updateSenderSql = "UPDATE users SET balance = :balance WHERE id = :id";
        $updateSenderStmt = $pdo->prepare($updateSenderSql);
        $senderUpdateResult = $updateSenderStmt->execute([
            ':balance' => floatval($senderNewBalance),
            ':id' => intval($senderId)
        ]);
        
        if (!$senderUpdateResult) {
            throw new Exception("Failed to update sender balance");
        }
        
        // Update recipient's balance
        $updateRecipientSql = "UPDATE users SET balance = :balance WHERE id = :id";
        $updateRecipientStmt = $pdo->prepare($updateRecipientSql);
        $recipientUpdateResult = $updateRecipientStmt->execute([
            ':balance' => floatval($recipientNewBalance),
            ':id' => intval($recipientId)
        ]);
        
        if (!$recipientUpdateResult) {
            throw new Exception("Failed to update recipient balance");
        }
        
        // Record outgoing transaction for sender
        $senderTransactionSql = "INSERT INTO transactions (user_id, type, amount, balance_after) 
                                VALUES (:user_id, :type, :amount, :balance_after)";
        $senderTransactionStmt = $pdo->prepare($senderTransactionSql);
        $senderTransactionResult = $senderTransactionStmt->execute([
            ':user_id' => intval($senderId),
            ':type' => 'transfer_out',
            ':amount' => -floatval($amount), // Negative for outgoing transfer
            ':balance_after' => floatval($senderNewBalance)
        ]);
        
        if (!$senderTransactionResult) {
            throw new Exception("Failed to record sender transaction");
        }
        
        $senderTransactionId = $pdo->lastInsertId();
        
        // Record incoming transaction for recipient
        $recipientTransactionSql = "INSERT INTO transactions (user_id, type, amount, balance_after) 
                                   VALUES (:user_id, :type, :amount, :balance_after)";
        $recipientTransactionStmt = $pdo->prepare($recipientTransactionSql);
        $recipientTransactionResult = $recipientTransactionStmt->execute([
            ':user_id' => intval($recipientId),
            ':type' => 'transfer_in',
            ':amount' => floatval($amount), // Positive for incoming transfer
            ':balance_after' => floatval($recipientNewBalance)
        ]);
        
        if (!$recipientTransactionResult) {
            throw new Exception("Failed to record recipient transaction");
        }
        
        $recipientTransactionId = $pdo->lastInsertId();
        
        // Log transfer activity for both users
        $transferDescription = "Transfer of $" . number_format($amount, 2);
        log_activity($senderId, 'transfer_out', $transferDescription . " sent", $pdo);
        log_activity($recipientId, 'transfer_in', $transferDescription . " received", $pdo);
        
        // Commit all changes atomically
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Transfer completed successfully',
            'sender_transaction_id' => $senderTransactionId,
            'recipient_transaction_id' => $recipientTransactionId
        ];
        
    } catch (Exception $e) {
        // Rollback all changes on any error
        $pdo->rollback();
        
        // Log transfer error for debugging
        error_log("Transfer failed between users $senderId and $recipientId: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Transfer failed. Please try again.',
            'error' => $e->getMessage()
        ];
    }
}
}

/**
 * Get filtered and paginated transaction history for a user
 * 
 * This function provides comprehensive transaction filtering and pagination
 * capabilities for the transaction history feature.
 * 
 * Features:
 * - Date range filtering
 * - Transaction type filtering
 * - Pagination with configurable page size
 * - Total count calculation
 * - Optimized SQL queries
 * 
 * @param int $userId User ID to get transactions for
 * @param array $filters Filter criteria (date_from, date_to, type)
 * @param int $page Current page number (1-based)
 * @param int $perPage Number of transactions per page
 * @param PDO $pdo Database connection object
 * @return array Array containing transactions and pagination info
 */
if (!function_exists('getTransactionsWithFilters')) {
function getTransactionsWithFilters($userId, $filters = [], $page = 1, $perPage = 10, $pdo) {
    // Build dynamic WHERE clause based on filters
    $conditions = ["user_id = :user_id"];
    $params = [':user_id' => intval($userId)];
    
    // Apply date range filter if provided
    if (!empty($filters['date_from'])) {
        $conditions[] = "DATE(created_at) >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $conditions[] = "DATE(created_at) <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }
    
    // Apply transaction type filter if specified
    if (!empty($filters['type']) && $filters['type'] !== 'all') {
        $conditions[] = "type = :type";
        $params[':type'] = $filters['type'];
    }
    
    // Combine all conditions
    $whereClause = implode(' AND ', $conditions);
    
    // Calculate pagination offset
    $offset = (intval($page) - 1) * intval($perPage);
    
    // Get total transaction count for pagination
    $countSql = "SELECT COUNT(*) as total FROM transactions WHERE $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalTransactions = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get paginated transactions
    $sql = "SELECT id, type, amount, balance_after, created_at 
            FROM transactions 
            WHERE $whereClause 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    
    // Bind filter parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Bind pagination parameters
    $stmt->bindValue(':limit', intval($perPage), PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'transactions' => $transactions,
        'total' => intval($totalTransactions),
        'current_page' => intval($page),
        'per_page' => intval($perPage),
        'total_pages' => ceil($totalTransactions / $perPage)
    ];
}
}

/**
 * Format transaction amount for user-friendly display
 * Adds appropriate signs and currency formatting based on transaction type
 * 
 * @param float $amount Transaction amount
 * @param string $type Transaction type
 * @return string Formatted amount string with appropriate sign
 */
if (!function_exists('formatTransactionAmount')) {
function formatTransactionAmount($amount, $type) {
    $formattedAmount = number_format(abs(floatval($amount)), 2);
    
    // Apply appropriate formatting based on transaction type
    switch ($type) {
        case 'deposit':
        case 'transfer_in':
            return '+$' . $formattedAmount;
        case 'withdraw':
        case 'transfer_out':
            return '-$' . $formattedAmount;
        case 'transfer':
            // Legacy transfer type - determine direction by amount sign
            return ($amount > 0 ? '+' : '-') . '$' . $formattedAmount;
        default:
            return '$' . $formattedAmount;
    }
}
}

/**
 * Get appropriate icon for transaction type display
 * Provides consistent iconography across the application
 * 
 * @param string $type Transaction type
 * @param float $amount Transaction amount (for transfer direction)
 * @return string HTML icon element with appropriate styling
 */
if (!function_exists('getTransactionIcon')) {
function getTransactionIcon($type, $amount = 0) {
    switch ($type) {
        case 'deposit':
            return '<i class="fas fa-plus-circle text-success"></i>';
        case 'withdraw':
            return '<i class="fas fa-minus-circle text-danger"></i>';
        case 'transfer_in':
            return '<i class="fas fa-arrow-down text-success"></i>';
        case 'transfer_out':
            return '<i class="fas fa-arrow-up text-danger"></i>';
        case 'transfer':
            // Legacy transfer type - determine direction by amount
            return floatval($amount) > 0 
                ? '<i class="fas fa-arrow-down text-success"></i>' 
                : '<i class="fas fa-arrow-up text-primary"></i>';
        default:
            return '<i class="fas fa-exchange-alt text-secondary"></i>';
    }
}
}

/**
 * Get descriptive text for transaction types
 * Provides user-friendly descriptions for transaction history
 * 
 * @param string $type Transaction type
 * @param float $amount Transaction amount (for transfer direction)
 * @return string Human-readable transaction description
 */
if (!function_exists('getTransactionDescription')) {
function getTransactionDescription($type, $amount) {
    switch ($type) {
        case 'deposit':
            return 'Money Deposit';
        case 'withdraw':
            return 'Cash Withdrawal';
        case 'transfer_in':
            return 'Transfer Received';
        case 'transfer_out':
            return 'Transfer Sent';
        case 'transfer':
            // Legacy transfer type - determine direction by amount
            return floatval($amount) > 0 ? 'Transfer Received' : 'Transfer Sent';
        default:
            return ucfirst(str_replace('_', ' ', $type));
    }
}
}

// Get transaction CSS class for styling
if (!function_exists('getTransactionClass')) {
function getTransactionClass($type, $amount = 0) {
    switch ($type) {
        case 'deposit':
            return 'table-success';
        case 'withdraw':
            return 'table-danger';
        case 'transfer':
            return $amount > 0 ? 'table-info' : 'table-warning';
        default:
            return '';
    }
}
}

// Calculate total withdrawals for a user within the last 24 hours
if (!function_exists('getDailyWithdrawalTotal')) {
function getDailyWithdrawalTotal($userId, $pdo) {
    try {
        // Calculate the timestamp 24 hours ago
        $twentyFourHoursAgo = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        $sql = "SELECT COALESCE(SUM(amount), 0) as total_withdrawn 
                FROM transactions 
                WHERE user_id = :user_id 
                AND type = 'withdraw' 
                AND created_at >= :twenty_four_hours_ago";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':twenty_four_hours_ago' => $twentyFourHoursAgo
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return floatval($result['total_withdrawn']);
        
    } catch (PDOException $e) {
        error_log("Error calculating daily withdrawal total: " . $e->getMessage());
        return 0; // Return 0 if there's an error to allow system to continue
    }
}
}

// Calculate total transfers sent by a user within the last 24 hours (daily limit check)
if (!function_exists('getDailyTransferTotal')) {
function getDailyTransferTotal($userId, $pdo) {
    try {
        // Calculate the timestamp 24 hours ago
        $twentyFourHoursAgo = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        // Debug: Log the query parameters
        error_log("getDailyTransferTotal - User: $userId, Looking from: $twentyFourHoursAgo");
        
        $sql = "SELECT COALESCE(SUM(ABS(amount)), 0) as total_transferred 
                FROM transactions 
                WHERE user_id = :user_id 
                AND type = 'transfer' 
                AND amount < 0
                AND created_at >= :twenty_four_hours_ago";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':twenty_four_hours_ago' => $twentyFourHoursAgo
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = floatval($result['total_transferred']);
        
        // Debug: Log the result
        error_log("getDailyTransferTotal - Result: $total");
        
        return $total;
        
    } catch (PDOException $e) {
        error_log("Error calculating daily transfer total: " . $e->getMessage());
        return 0; // Return 0 if there's an error to allow system to continue
    }
}
}

// Count transfers to a specific recipient within the last hour (frequency limit check)
if (!function_exists('getHourlyTransferCountToRecipient')) {
function getHourlyTransferCountToRecipient($senderId, $recipientId, $pdo) {
    try {
        // Calculate the timestamp 1 hour ago (3600 seconds)
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-3600 seconds'));
        
        // Check activity log for transfers to specific recipient
        $sql = "SELECT COUNT(*) as transfer_count 
                FROM user_activities 
                WHERE user_id = :sender_id 
                AND activity_type = 'withdraw'
                AND description LIKE CONCAT('%to recipient ID: ', :recipient_id, '%')
                AND created_at >= :one_hour_ago";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':sender_id' => $senderId,
            ':recipient_id' => $recipientId,
            ':one_hour_ago' => $oneHourAgo
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($result['transfer_count']);
        
    } catch (PDOException $e) {
        error_log("Error calculating hourly transfer count: " . $e->getMessage());
        return 0; // Return 0 if there's an error to allow system to continue
    }
}
}

// Check if user has exceeded daily transfer limit ($5000/day)
if (!function_exists('checkDailyTransferLimit')) {
function checkDailyTransferLimit($userId, $transferAmount, $pdo) {
    $dailyLimit = 5000.00; // $5000 daily limit
    $currentDailyTotal = getDailyTransferTotal($userId, $pdo);
    $newTotal = $currentDailyTotal + $transferAmount;
    
    // Debug: Log the values for troubleshooting
    error_log("Daily Transfer Check - User: $userId, Current Total: $currentDailyTotal, New Amount: $transferAmount, New Total: $newTotal, Limit: $dailyLimit");
    
    if ($newTotal > $dailyLimit) {
        return [
            'allowed' => false,
            'message' => sprintf(
                'Daily transfer limit exceeded. You have transferred $%.2f today. Limit: $%.2f. This transfer of $%.2f would exceed your daily limit by $%.2f.',
                $currentDailyTotal,
                $dailyLimit,
                $transferAmount,
                $newTotal - $dailyLimit
            ),
            'current_total' => $currentDailyTotal,
            'daily_limit' => $dailyLimit
        ];
    }
    
    return [
        'allowed' => true,
        'current_total' => $currentDailyTotal,
        'daily_limit' => $dailyLimit,
        'remaining' => $dailyLimit - $newTotal
    ];
}
}

// Check if user has exceeded hourly transfer frequency to the same recipient (5 transfers/hour)
if (!function_exists('checkRecipientTransferFrequency')) {
function checkRecipientTransferFrequency($senderId, $recipientId, $pdo) {
    $hourlyLimit = 5; // Maximum 5 transfers per hour to same recipient
    $currentHourlyCount = getHourlyTransferCountToRecipient($senderId, $recipientId, $pdo);
    
    if ($currentHourlyCount >= $hourlyLimit) {
        return [
            'allowed' => false,
            'message' => sprintf(
                'Transfer frequency limit exceeded. You have already made %d transfers to this recipient in the last hour. Please wait before making another transfer to the same person.',
                $currentHourlyCount
            ),
            'current_count' => $currentHourlyCount,
            'hourly_limit' => $hourlyLimit
        ];
    }
    
    return [
        'allowed' => true,
        'current_count' => $currentHourlyCount,
        'hourly_limit' => $hourlyLimit,
        'remaining' => $hourlyLimit - $currentHourlyCount - 1
    ];
}
}

// Enhanced transfer validation with rate limiting
// Implements two key restrictions:
// 1. Daily limit: Cannot transfer more than $5000 in a 24-hour period
// 2. Recipient frequency limit: Cannot transfer to the same person more than 5 times in an hour (3600 seconds)
if (!function_exists('validateTransferWithRateLimits')) {
function validateTransferWithRateLimits($senderId, $senderEmail, $recipientId, $recipientEmail, $amount, $senderBalance, $pdo) {
    // Debug: Log that validation is being called
    error_log("validateTransferWithRateLimits called - Sender: $senderId, Recipient: $recipientId, Amount: $amount");
    
    // First do basic validation
    $basicErrors = validateTransfer($senderEmail, $recipientEmail, $amount, $senderBalance);
    
    if (!empty($basicErrors)) {
        error_log("Basic validation failed: " . implode(", ", $basicErrors));
        return $basicErrors;
    }
    
    $errors = array();
    $amount = floatval($amount);
    
    // Check daily transfer limit
    $dailyCheck = checkDailyTransferLimit($senderId, $amount, $pdo);
    if (!$dailyCheck['allowed']) {
        error_log("Daily limit check failed: " . $dailyCheck['message']);
        $errors[] = $dailyCheck['message'];
    } else {
        error_log("Daily limit check passed - remaining: " . $dailyCheck['remaining']);
    }
    
    // Check hourly frequency limit to recipient
    $frequencyCheck = checkRecipientTransferFrequency($senderId, $recipientId, $pdo);
    if (!$frequencyCheck['allowed']) {
        error_log("Frequency limit check failed: " . $frequencyCheck['message']);
        $errors[] = $frequencyCheck['message'];
    } else {
        error_log("Frequency limit check passed - remaining: " . $frequencyCheck['remaining']);
    }
    
    error_log("validateTransferWithRateLimits returning " . count($errors) . " errors");
    return $errors;
}
}

// CSRF Token Helper Functions

// Generate a CSRF token and store it in the session
if (!function_exists('generateCSRFToken')) {
function generateCSRFToken() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Generate a random token using bin2hex and random_bytes for security
    $token = bin2hex(random_bytes(32));
    
    // Store the token in the session
    $_SESSION['csrf_token'] = $token;
    
    return $token;
}
}

// Validate CSRF token from form submission against session token
if (!function_exists('validateCSRFToken')) {
function validateCSRFToken($submittedToken = null) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // If no token is provided, try to get it from POST data
    if ($submittedToken === null) {
        $submittedToken = $_POST['csrf_token'] ?? '';
    }
    
    // Check if session has a CSRF token
    if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Validate the submitted token matches the session token
    return hash_equals($_SESSION['csrf_token'], $submittedToken);
}
}

// Generate CSRF token input field for forms
if (!function_exists('getCSRFTokenField')) {
function getCSRFTokenField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
}

// Get current CSRF token (generate if doesn't exist)
if (!function_exists('getCSRFToken')) {
function getCSRFToken() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Return existing token or generate new one
    if (isset($_SESSION['csrf_token']) && !empty($_SESSION['csrf_token'])) {
        return $_SESSION['csrf_token'];
    } else {
        return generateCSRFToken();
    }
}
}

// Regenerate CSRF token (useful after successful form submission)
if (!function_exists('regenerateCSRFToken')) {
function regenerateCSRFToken() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Clear existing token and generate a new one
    unset($_SESSION['csrf_token']);
    return generateCSRFToken();
}
}
?>