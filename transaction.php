<?php
session_start();
require "includes/db.php";
require "includes/helpers.php";
require "includes/auth.php";

// Require login - will redirect to login page if not logged in
requireLogin();

// Verify user session is still valid
if (!verifyUserSession($pdo)) {
    header("Location: login.php");
    exit;
}

$message = "";
$messageType = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transactionType = cleanInput($_POST['transaction_type']);
    $amount = cleanInput($_POST['amount']);
    
    // Validate amount
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
        $message = "Please enter a valid amount greater than 0.";
        $messageType = "error";
    } else {
        $amount = floatval($amount);
        
        // Get current balance
        $sql = "SELECT balance FROM users WHERE id = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $currentBalance = $user['balance'];
            
            if ($transactionType === 'deposit') {
                // Process deposit with atomic transaction
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
                    
                    // Record transaction in transactions table
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
                    
                    $message = "Successfully deposited $" . number_format($amount, 2) . ". New balance: $" . number_format($newBalance, 2);
                    $messageType = "success";
                    
                    // Log activity
                    logActivity($_SESSION['user_id'], 'deposit', "Deposited $" . number_format($amount, 2), $pdo);
                    
                } catch (Exception $e) {
                    // ROLLBACK TRANSACTION on error
                    $pdo->rollback();
                    $message = "Transaction failed. Please try again.";
                    $messageType = "error";
                }
                
            } elseif ($transactionType === 'withdraw') {
                // Check if sufficient balance
                if ($currentBalance < $amount) {
                    $message = "Insufficient balance. Current balance: $" . number_format($currentBalance, 2);
                    $messageType = "error";
                } else {
                    // Process withdrawal with atomic transaction
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
                        
                        // Record transaction in transactions table
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
                        
                        $message = "Successfully withdrew $" . number_format($amount, 2) . ". New balance: $" . number_format($newBalance, 2);
                        $messageType = "success";
                        
                        // Log activity
                        logActivity($_SESSION['user_id'], 'withdraw', "Withdrew $" . number_format($amount, 2), $pdo);
                        
                    } catch (Exception $e) {
                        // ROLLBACK TRANSACTION on error
                        $pdo->rollback();
                        $message = "Transaction failed. Please try again.";
                        $messageType = "error";
                    }
                }
            }
        }
    }
}

// Get current user balance
$sql = "SELECT balance, name FROM users WHERE id = :id LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$currentBalance = $user['balance'];
$userName = $user['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css" type="text/css">
    <title>Transaction - My ATM</title>
</head>
<body class="transaction-page">
    <div class="container">
        <header class="page-header">
            <div class="brand">
                <div class="brand-logo">฿</div>
                <div class="brand-name">My ATM</div>
            </div>
            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="history.php">History</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <main class="transaction-main">
            <div class="transaction-card">
                <h1>Make a Transaction</h1>
                <div class="current-balance">
                    <h3>Current Balance: $<?php echo number_format($currentBalance, 2); ?></h3>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="message <?php echo $messageType; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form class="transaction-form" method="POST" action="transaction.php">
                    <div class="form-group">
                        <label for="transaction_type">Transaction Type:</label>
                        <select name="transaction_type" id="transaction_type" required>
                            <option value="">Select Transaction Type</option>
                            <option value="deposit">Deposit</option>
                            <option value="withdraw">Withdraw</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="amount">Amount ($):</label>
                        <input type="number" 
                               name="amount" 
                               id="amount" 
                               step="0.01" 
                               min="0.01" 
                               placeholder="Enter amount" 
                               required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Process Transaction</button>
                        <a href="dashboard.php" class="btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
