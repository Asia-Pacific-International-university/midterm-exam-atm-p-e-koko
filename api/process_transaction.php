<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();
require "../includes/db.php";
require "../includes/helpers.php";
require "../includes/auth.php";

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !verifyUserSession($pdo)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in again.']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $transactionType = cleanInput($input['transaction_type'] ?? '');
    $amount = cleanInput($input['amount'] ?? '');
    
    // Validate input
    if (empty($transactionType) || empty($amount)) {
        throw new Exception('Transaction type and amount are required');
    }
    
    if (!in_array($transactionType, ['deposit', 'withdraw'])) {
        throw new Exception('Invalid transaction type');
    }
    
    if (!is_numeric($amount) || $amount <= 0) {
        throw new Exception('Please enter a valid amount greater than 0');
    }
    
    $amount = floatval($amount);
    
    // Get current user balance
    $sql = "SELECT balance, name FROM users WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    $currentBalance = $user['balance'];
    $userName = $user['name'];
    
    if ($transactionType === 'deposit') {
        // Process deposit
        $newBalance = $currentBalance + $amount;
        
        try {
            // BEGIN TRANSACTION
            $pdo->beginTransaction();
            
            // Update user balance
            $updateSql = "UPDATE users SET balance = :balance WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':balance' => $newBalance,
                ':id' => $_SESSION['user_id']
            ]);
            
            // Record transaction
            $transactionSql = "INSERT INTO transactions (user_id, type, amount, balance_after) VALUES (:user_id, :type, :amount, :balance_after)";
            $transactionStmt = $pdo->prepare($transactionSql);
            $transactionStmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':type' => 'deposit',
                ':amount' => $amount,
                ':balance_after' => $newBalance
            ]);
            
            // COMMIT TRANSACTION
            $pdo->commit();
            
            // Log activity
            logActivity($_SESSION['user_id'], 'deposit', "Deposited $" . number_format($amount, 2), $pdo);
            
            // Return success response
            echo json_encode([
                'success' => true,
                'message' => "Successfully deposited $" . number_format($amount, 2),
                'data' => [
                    'transaction_type' => 'deposit',
                    'amount' => $amount,
                    'formatted_amount' => number_format($amount, 2),
                    'previous_balance' => $currentBalance,
                    'new_balance' => $newBalance,
                    'formatted_new_balance' => number_format($newBalance, 2),
                    'user_name' => $userName,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
            
        } catch (Exception $e) {
            // ROLLBACK on error
            $pdo->rollback();
            throw new Exception('Transaction failed. Please try again.');
        }
        
    } elseif ($transactionType === 'withdraw') {
        // Check sufficient balance
        if ($currentBalance < $amount) {
            throw new Exception("Insufficient balance. Current balance: $" . number_format($currentBalance, 2));
        }
        
        // Check daily withdrawal limit
        $dailyWithdrawalTotal = getDailyWithdrawalTotal($_SESSION['user_id'], $pdo);
        $dailyWithdrawalLimit = 1000.00;
        
        $totalAfterWithdrawal = $dailyWithdrawalTotal + $amount;
        
        if ($totalAfterWithdrawal > $dailyWithdrawalLimit) {
            $remainingLimit = $dailyWithdrawalLimit - $dailyWithdrawalTotal;
            
            if ($remainingLimit <= 0) {
                throw new Exception("Daily withdrawal limit of $" . number_format($dailyWithdrawalLimit, 2) . " exceeded. You have already withdrawn $" . number_format($dailyWithdrawalTotal, 2) . " in the last 24 hours. Please try again tomorrow.");
            } else {
                throw new Exception("Daily withdrawal limit of $" . number_format($dailyWithdrawalLimit, 2) . " exceeded. You can only withdraw $" . number_format($remainingLimit, 2) . " more today (already withdrawn $" . number_format($dailyWithdrawalTotal, 2) . " in the last 24 hours).");
            }
        }
        
        // Process withdrawal
        $newBalance = $currentBalance - $amount;
        
        try {
            // BEGIN TRANSACTION
            $pdo->beginTransaction();
            
            // Update user balance
            $updateSql = "UPDATE users SET balance = :balance WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':balance' => $newBalance,
                ':id' => $_SESSION['user_id']
            ]);
            
            // Record transaction
            $transactionSql = "INSERT INTO transactions (user_id, type, amount, balance_after) VALUES (:user_id, :type, :amount, :balance_after)";
            $transactionStmt = $pdo->prepare($transactionSql);
            $transactionStmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':type' => 'withdraw',
                ':amount' => $amount,
                ':balance_after' => $newBalance
            ]);
            
            // COMMIT TRANSACTION
            $pdo->commit();
            
            // Log activity
            logActivity($_SESSION['user_id'], 'withdraw', "Withdrew $" . number_format($amount, 2), $pdo);
            
            // Return success response
            echo json_encode([
                'success' => true,
                'message' => "Successfully withdrew $" . number_format($amount, 2),
                'data' => [
                    'transaction_type' => 'withdraw',
                    'amount' => $amount,
                    'formatted_amount' => number_format($amount, 2),
                    'previous_balance' => $currentBalance,
                    'new_balance' => $newBalance,
                    'formatted_new_balance' => number_format($newBalance, 2),
                    'user_name' => $userName,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
            
        } catch (Exception $e) {
            // ROLLBACK on error
            $pdo->rollback();
            throw new Exception('Transaction failed. Please try again.');
        }
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>