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
    // Validate CSRF token
    if (!validateCSRFToken()) {
        $message = "Security token validation failed. Please try again.";
        $messageType = "error";
    } else {
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
                // Enhanced validation with rate limiting
                $rateLimitErrors = validateTransferWithRateLimits(
                    $sender['id'], 
                    $sender['email'], 
                    $recipient['id'], 
                    $recipientEmail, 
                    $amount, 
                    $sender['balance'], 
                    $pdo
                );
                
                if (!empty($rateLimitErrors)) {
                    $message = implode(" ", $rateLimitErrors);
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
                        
                        // Enhanced logging for rate limiting - include recipient ID in description
                        logActivity($sender['id'], 'withdraw', "Transferred $" . number_format($amount, 2) . " to " . $recipient['name'] . " (ID: " . $recipient['id'] . ") - to recipient ID: " . $recipient['id'], $pdo);
                        logActivity($recipient['id'], 'deposit', "Received $" . number_format($amount, 2) . " from " . $sender['name'] . " (ID: " . $sender['id'] . ")", $pdo);
                        
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

// Get current transfer limits for display
$currentDailyTotal = getDailyTransferTotal($_SESSION['user_id'], $pdo);
$dailyLimit = 5000.00;
$remainingDaily = $dailyLimit - $currentDailyTotal;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css" type="text/css">
    <title>Transfer Money - My ATM</title>
</head>
<<body class="bg-light">
    <div class="container-fluid">
        <!-- Navigation Header -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
            <div class="container-fluid">
                <div class="navbar-brand d-flex align-items-center">
                    <span class="fs-2 me-2">฿</span>
                    <span class="fw-bold">My ATM</span>
                </div>
                <div class="navbar-nav ms-auto d-flex flex-row">
                    <a class="nav-link me-3" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                    <a class="nav-link me-3" href="transaction.php">
                        <i class="fas fa-exchange-alt me-1"></i>Transaction
                    </a>
                    <a class="nav-link me-3" href="history.php">
                        <i class="fas fa-history me-1"></i>History
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
            </div>
        </nav>

        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-6">
                <!-- Transfer Card -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h1 class="card-title mb-0 fs-3">
                            <i class="fas fa-paper-plane me-2"></i>Transfer Money
                        </h1>
                    </div>
                    <div class="card-body">
                        <!-- Current Balance -->
                        <div class="alert alert-info d-flex align-items-center">
                            <i class="fas fa-wallet me-2"></i>
                            <strong>Your Balance: $<?php echo number_format($currentBalance, 2); ?></strong>
                        </div>

                        <!-- Transfer Limits Info -->
                        <div class="card mb-3 border-info">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="fas fa-shield-alt me-2"></i>Daily Transfer Limits
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-6"><strong>Daily Limit:</strong></div>
                                    <div class="col-6 text-end">$<?php echo number_format($dailyLimit, 2); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-6"><strong>Used Today:</strong></div>
                                    <div class="col-6 text-end">$<?php echo number_format($currentDailyTotal, 2); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-6"><strong>Remaining:</strong></div>
                                    <div class="col-6 text-end">
                                        <span class="<?php echo $remainingDaily <= 0 ? 'text-danger fw-bold' : 'text-success fw-bold'; ?>">
                                            $<?php echo number_format(max(0, $remainingDaily), 2); ?>
                                        </span>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php if ($remainingDaily <= 0): ?>
                                        <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                        You have reached your daily transfer limit of $<?php echo number_format($dailyLimit, 2); ?>.
                                    <?php else: ?>
                                        <i class="fas fa-info-circle text-info me-1"></i>
                                        Recipients are limited to 5 transfers per hour from the same sender.
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>

                        <!-- Messages -->
                        <?php if (!empty($message)): ?>
                            <div class="alert <?php echo $messageType === 'success' ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show">
                                <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Transfer Form -->
                        <form method="POST" action="transfer.php">
                            <?php echo getCSRFTokenField(); ?>
                            <div class="mb-3">
                                <label for="recipient_email" class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Recipient Email Address
                                </label>
                                <input type="email" 
                                       class="form-control form-control-lg" 
                                       name="recipient_email" 
                                       id="recipient_email" 
                                       placeholder="Enter recipient's email address" 
                                       value="<?php echo isset($recipientEmail) ? htmlspecialchars($recipientEmail) : ''; ?>"
                                       required>
                                <div class="form-text">Enter the email address of the person you want to send money to.</div>
                            </div>

                            <div class="mb-4">
                                <label for="amount" class="form-label">
                                    <i class="fas fa-dollar-sign me-2"></i>Amount ($)
                                </label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text">$</span>
                                    <input type="number" 
                                           class="form-control" 
                                           name="amount" 
                                           id="amount" 
                                           step="0.01" 
                                           min="0.01" 
                                           max="<?php echo min($currentBalance, $remainingDaily); ?>"
                                           placeholder="Enter amount to transfer" 
                                           value="<?php echo isset($amount) ? htmlspecialchars($amount) : ''; ?>"
                                           required>
                                </div>
                                <div class="form-text">
                                    Maximum: $<?php echo number_format(min($currentBalance, max(0, $remainingDaily)), 2); ?>
                                    (<?php echo $remainingDaily < $currentBalance ? 'Daily limit' : 'Current balance'; ?>)
                                </div>
                            </div>

                            <!-- Transfer Summary -->
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-clipboard-list me-2"></i>Transfer Summary
                                    </h5>
                                    <div class="row mb-2">
                                        <div class="col-sm-4"><strong>From:</strong></div>
                                        <div class="col-sm-8"><?php echo htmlspecialchars($userName); ?> (<?php echo htmlspecialchars($userEmail); ?>)</div>
                                    </div>
                                    <div class="row">
                                        <div class="col-sm-4"><strong>Current Balance:</strong></div>
                                        <div class="col-sm-8">$<?php echo number_format($currentBalance, 2); ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="dashboard.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Send Money
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Add some client-side validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.transaction-form');
            const recipientEmailInput = document.getElementById('recipient_email');
            const amountInput = document.getElementById('amount');
            const currentBalance = <?php echo $currentBalance; ?>;
            const remainingDaily = <?php echo max(0, $remainingDaily); ?>;
            const userEmail = '<?php echo addslashes($userEmail); ?>';

            form.addEventListener('submit', function(e) {
                // Check if trying to transfer to self
                if (recipientEmailInput.value.toLowerCase() === userEmail.toLowerCase()) {
                    e.preventDefault();
                    alert('You cannot transfer money to yourself.');
                    return;
                }

                // Check amount against balance and daily limit
                const amount = parseFloat(amountInput.value);
                const maxAllowed = Math.min(currentBalance, remainingDaily);
                
                if (amount > currentBalance) {
                    e.preventDefault();
                    alert('Transfer amount cannot exceed your current balance of $' + currentBalance.toFixed(2));
                    return;
                }
                
                if (amount > remainingDaily) {
                    e.preventDefault();
                    alert('Transfer amount would exceed your daily transfer limit. Remaining daily limit: $' + remainingDaily.toFixed(2));
                    return;
                }
                
                if (remainingDaily <= 0) {
                    e.preventDefault();
                    alert('You have reached your daily transfer limit of $5,000.00. Please try again tomorrow.');
                    return;
                }

                // Confirm transfer
                const confirmMessage = `Are you sure you want to transfer $${amount.toFixed(2)} to ${recipientEmailInput.value}?\n\nThis will leave you with a remaining daily limit of $${(remainingDaily - amount).toFixed(2)}.`;
                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                }
            });

            // Update max amount dynamically as user types in recipient email
            amountInput.addEventListener('focus', function() {
                const maxAmount = Math.min(currentBalance, remainingDaily);
                if (maxAmount <= 0) {
                    alert('No transfers available. Either insufficient balance or daily limit reached.');
                    this.blur();
                }
            });
        });
    </script>
</body>
</html>