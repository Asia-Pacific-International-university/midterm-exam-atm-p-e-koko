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
    $recipientEmail = cleanInput($_POST['recipient_email']);
    $amount = cleanInput($_POST['amount']);
    
    // Get sender information
    $senderSql = "SELECT id, email, balance, name FROM users WHERE id = :id LIMIT 1";
    $senderStmt = $pdo->prepare($senderSql);
    $senderStmt->execute([':id' => $_SESSION['user_id']]);
    $sender = $senderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sender) {
        $message = "Error: Unable to retrieve sender information.";
        $messageType = "error";
    } else {
        // Validate transfer parameters
        $validationErrors = validateTransfer($sender['email'], $recipientEmail, $amount, $sender['balance']);
        
        if (!empty($validationErrors)) {
            $message = implode(" ", $validationErrors);
            $messageType = "error";
        } else {
            $amount = floatval($amount);
            
            // Check if recipient exists
            $recipient = getUserByEmail($recipientEmail, $pdo);
            
            if (!$recipient) {
                $message = "Error: Recipient email address not found.";
                $messageType = "error";
            } else {
                // Calculate new balances
                $senderNewBalance = $sender['balance'] - $amount;
                $recipientNewBalance = $recipient['balance'] + $amount;
                
                // Process the transfer
                $transferResult = processTransfer(
                    $sender['id'], 
                    $recipient['id'], 
                    $amount, 
                    $senderNewBalance, 
                    $recipientNewBalance, 
                    $pdo
                );
                
                if ($transferResult['success']) {
                    $message = "Successfully transferred $" . number_format($amount, 2) . " to " . htmlspecialchars($recipient['name']) . " (" . htmlspecialchars($recipientEmail) . "). New balance: $" . number_format($senderNewBalance, 2);
                    $messageType = "success";
                    
                    // Log activities for both users
                    logActivity($sender['id'], 'withdraw', "Transferred $" . number_format($amount, 2) . " to " . $recipient['name'], $pdo);
                    logActivity($recipient['id'], 'deposit', "Received $" . number_format($amount, 2) . " from " . $sender['name'], $pdo);
                    
                    // Clear form data on success
                    $recipientEmail = '';
                    $amount = '';
                    
                } else {
                    $message = "Transfer failed. Please try again. Error: " . ($transferResult['error'] ?? 'Unknown error');
                    $messageType = "error";
                }
            }
        }
    }
}

// Get current user balance and name
$sql = "SELECT balance, name, email FROM users WHERE id = :id LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$currentBalance = $user['balance'];
$userName = $user['name'];
$userEmail = $user['email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css" type="text/css">
    <title>Transfer Money - My ATM</title>
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
                <a href="transaction.php">Transaction</a>
                <a href="history.php">History</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <main class="transaction-main">
            <div class="transaction-card">
                <h1>Transfer Money</h1>
                <div class="current-balance">
                    <h3>Your Balance: $<?php echo number_format($currentBalance, 2); ?></h3>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="message <?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form class="transaction-form" method="POST" action="transfer.php">
                    <div class="form-group">
                        <label for="recipient_email">Recipient Email Address:</label>
                        <input type="email" 
                               name="recipient_email" 
                               id="recipient_email" 
                               placeholder="Enter recipient's email address" 
                               value="<?php echo isset($recipientEmail) ? htmlspecialchars($recipientEmail) : ''; ?>"
                               required>
                        <small class="form-help">Enter the email address of the person you want to send money to.</small>
                    </div>

                    <div class="form-group">
                        <label for="amount">Amount ($):</label>
                        <input type="number" 
                               name="amount" 
                               id="amount" 
                               step="0.01" 
                               min="0.01" 
                               max="<?php echo $currentBalance; ?>"
                               placeholder="Enter amount to transfer" 
                               value="<?php echo isset($amount) ? htmlspecialchars($amount) : ''; ?>"
                               required>
                        <small class="form-help">Maximum: $<?php echo number_format($currentBalance, 2); ?></small>
                    </div>

                    <div class="transfer-summary">
                        <h4>Transfer Summary</h4>
                        <div class="summary-row">
                            <span class="summary-label">From:</span>
                            <span class="summary-value"><?php echo htmlspecialchars($userName); ?> (<?php echo htmlspecialchars($userEmail); ?>)</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Current Balance:</span>
                            <span class="summary-value">$<?php echo number_format($currentBalance, 2); ?></span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Send Money</button>
                        <a href="dashboard.php" class="btn-secondary">Cancel</a>
                    </div>
                </form>

        
            </div>
        </main>
    </div>

    <script>
        // Add some client-side validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.transaction-form');
            const recipientEmailInput = document.getElementById('recipient_email');
            const amountInput = document.getElementById('amount');
            const currentBalance = <?php echo $currentBalance; ?>;
            const userEmail = '<?php echo addslashes($userEmail); ?>';

            form.addEventListener('submit', function(e) {
                // Check if trying to transfer to self
                if (recipientEmailInput.value.toLowerCase() === userEmail.toLowerCase()) {
                    e.preventDefault();
                    alert('You cannot transfer money to yourself.');
                    return;
                }

                // Check amount
                const amount = parseFloat(amountInput.value);
                if (amount > currentBalance) {
                    e.preventDefault();
                    alert('Transfer amount cannot exceed your current balance of $' + currentBalance.toFixed(2));
                    return;
                }

                // Confirm transfer
                const confirmMessage = `Are you sure you want to transfer $${amount.toFixed(2)} to ${recipientEmailInput.value}?`;
                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>