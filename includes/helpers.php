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

// Enhanced log_activity function for non-transactional events
if (!function_exists('log_activity')) {
function log_activity($userId, $activityType, $description = null, $pdo = null, $ipAddress = null, $userAgent = null) {
    // Use global PDO if not provided
    if (!$pdo) {
        require_once 'db.php';
        global $pdo;
        if (!$pdo) {
            return false; // Cannot log without database connection
        }
    }
    
    // Auto-detect IP and User Agent if not provided
    if ($ipAddress === null) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'Unknown';
    }
    
    if ($userAgent === null) {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }
    
    try {
        $sql = "INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent) 
                VALUES (:user_id, :activity_type, :description, :ip_address, :user_agent)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':user_id' => $userId,
            ':activity_type' => $activityType,
            ':description' => $description,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent
        ]);
        
        return $result;
        
    } catch (PDOException $e) {
        // Log error but don't break the application
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}
}

// Alias for backward compatibility - logActivity calls log_activity
if (!function_exists('logActivity')) {
function logActivity($userId, $activityType, $description = null, $pdo = null) {
    return log_activity($userId, $activityType, $description, $pdo);
}
}

// Change user PIN securely
if (!function_exists('changePIN')) {
function changePIN($userId, $currentPin, $newPin, $pdo) {
    // Validate new PIN format
    if (!validatePin($newPin)) {
        return ['success' => false, 'message' => 'New PIN must be 4-6 digits only.'];
    }
    
    // Get current user data to verify current PIN
    $sql = "SELECT pin FROM users WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return ['success' => false, 'message' => 'User not found.'];
    }
    
    // Verify current PIN
    if (!password_verify($currentPin, $user['pin'])) {
        // Log failed PIN change attempt
        log_activity($userId, 'failed_login', 'Failed PIN change attempt - wrong current PIN', $pdo);
        return ['success' => false, 'message' => 'Current PIN is incorrect.'];
    }
    
    // Check if new PIN is same as current PIN
    if (password_verify($newPin, $user['pin'])) {
        return ['success' => false, 'message' => 'New PIN must be different from current PIN.'];
    }
    
    try {
        // Hash new PIN
        $hashedNewPin = password_hash($newPin, PASSWORD_DEFAULT);
        
        // Update PIN in database
        $updateSql = "UPDATE users SET pin = :new_pin WHERE id = :id";
        $updateStmt = $pdo->prepare($updateSql);
        $result = $updateStmt->execute([
            ':new_pin' => $hashedNewPin,
            ':id' => $userId
        ]);
        
        if ($result) {
            // Log successful PIN change
            log_activity($userId, 'login', 'PIN changed successfully', $pdo);
            return ['success' => true, 'message' => 'PIN changed successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to update PIN. Please try again.'];
        }
        
    } catch (PDOException $e) {
        // Log error
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

// Get user ID by email
if (!function_exists('getUserByEmail')) {
function getUserByEmail($email, $pdo) {
    $sql = "SELECT id, name, balance FROM users WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
}

// Validate transfer parameters
if (!function_exists('validateTransfer')) {
function validateTransfer($senderEmail, $recipientEmail, $amount, $senderBalance) {
    $errors = array();
    
    // Check if recipient email is provided and valid
    if (empty($recipientEmail)) {
        $errors[] = "Recipient email is required.";
    } elseif (!validateEmail($recipientEmail)) {
        $errors[] = "Please enter a valid recipient email address.";
    }
    
    // Check if sender is trying to transfer to themselves
    if ($senderEmail === $recipientEmail) {
        $errors[] = "You cannot transfer money to yourself.";
    }
    
    // Validate amount
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
        $errors[] = "Please enter a valid amount greater than 0.";
    } elseif ($amount > $senderBalance) {
        $errors[] = "Insufficient balance. Current balance: $" . number_format($senderBalance, 2);
    }
    
    return $errors;
}
}

// Process transfer between users with atomic transaction
if (!function_exists('processTransfer')) {
function processTransfer($senderId, $recipientId, $amount, $senderNewBalance, $recipientNewBalance, $pdo) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Update sender balance
        $updateSenderSql = "UPDATE users SET balance = :balance WHERE id = :id";
        $updateSenderStmt = $pdo->prepare($updateSenderSql);
        $updateSenderStmt->execute([
            ':balance' => $senderNewBalance,
            ':id' => $senderId
        ]);
        
        // Update recipient balance
        $updateRecipientSql = "UPDATE users SET balance = :balance WHERE id = :id";
        $updateRecipientStmt = $pdo->prepare($updateRecipientSql);
        $updateRecipientStmt->execute([
            ':balance' => $recipientNewBalance,
            ':id' => $recipientId
        ]);
        
        // Record transaction for sender (transfer out)
        $senderTransactionSql = "INSERT INTO transactions (user_id, type, amount, balance_after) VALUES (:user_id, :type, :amount, :balance_after)";
        $senderTransactionStmt = $pdo->prepare($senderTransactionSql);
        $senderTransactionStmt->execute([
            ':user_id' => $senderId,
            ':type' => 'transfer',
            ':amount' => -$amount, // Negative for outgoing transfer
            ':balance_after' => $senderNewBalance
        ]);
        
        // Get the sender transaction ID
        $senderTransactionId = $pdo->lastInsertId();
        
        // Record transaction for recipient (transfer in)
        $recipientTransactionSql = "INSERT INTO transactions (user_id, type, amount, balance_after) VALUES (:user_id, :type, :amount, :balance_after)";
        $recipientTransactionStmt = $pdo->prepare($recipientTransactionSql);
        $recipientTransactionStmt->execute([
            ':user_id' => $recipientId,
            ':type' => 'transfer',
            ':amount' => $amount, // Positive for incoming transfer
            ':balance_after' => $recipientNewBalance
        ]);
        
        // Get the recipient transaction ID
        $recipientTransactionId = $pdo->lastInsertId();
        
        // Commit transaction
        $pdo->commit();
        return array('success' => true, 'sender_transaction_id' => $senderTransactionId, 'recipient_transaction_id' => $recipientTransactionId);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        return array('success' => false, 'error' => $e->getMessage());
    }
}
}

// Get transactions with filtering and pagination
if (!function_exists('getTransactionsWithFilters')) {
function getTransactionsWithFilters($userId, $filters = [], $page = 1, $perPage = 10, $pdo) {
    $conditions = ["user_id = :user_id"];
    $params = [':user_id' => $userId];
    
    // Add date range filter
    if (!empty($filters['date_from'])) {
        $conditions[] = "DATE(created_at) >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $conditions[] = "DATE(created_at) <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }
    
    // Add transaction type filter
    if (!empty($filters['type']) && $filters['type'] !== 'all') {
        $conditions[] = "type = :type";
        $params[':type'] = $filters['type'];
    }
    
    // Build WHERE clause
    $whereClause = implode(' AND ', $conditions);
    
    // Calculate offset for pagination
    $offset = ($page - 1) * $perPage;
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM transactions WHERE $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalTransactions = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get transactions with pagination
    $sql = "SELECT id, type, amount, balance_after, created_at 
            FROM transactions 
            WHERE $whereClause 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    
    // Bind all parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'transactions' => $transactions,
        'total' => $totalTransactions,
        'current_page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($totalTransactions / $perPage)
    ];
}
}

// Format transaction amount for display
if (!function_exists('formatTransactionAmount')) {
function formatTransactionAmount($amount, $type) {
    $formattedAmount = number_format(abs($amount), 2);
    
    switch ($type) {
        case 'deposit':
            return '+$' . $formattedAmount;
        case 'withdraw':
            return '-$' . $formattedAmount;
        case 'transfer':
            return ($amount > 0 ? '+' : '-') . '$' . $formattedAmount;
        default:
            return '$' . $formattedAmount;
    }
}
}

// Get transaction icon for display
if (!function_exists('getTransactionIcon')) {
function getTransactionIcon($type, $amount = 0) {
    switch ($type) {
        case 'deposit':
            return '<i class="fas fa-plus-circle text-success"></i>';
        case 'withdraw':
            return '<i class="fas fa-minus-circle text-danger"></i>';
        case 'transfer':
            return $amount > 0 
                ? '<i class="fas fa-arrow-down text-success"></i>' 
                : '<i class="fas fa-arrow-up text-primary"></i>';
        default:
            return '<i class="fas fa-exchange-alt text-secondary"></i>';
    }
}
}

// Get transaction description
if (!function_exists('getTransactionDescription')) {
function getTransactionDescription($type, $amount) {
    switch ($type) {
        case 'deposit':
            return 'Money Deposit';
        case 'withdraw':
            return 'Cash Withdrawal';
        case 'transfer':
            return $amount > 0 ? 'Transfer Received' : 'Transfer Sent';
        default:
            return ucfirst($type);
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
?>