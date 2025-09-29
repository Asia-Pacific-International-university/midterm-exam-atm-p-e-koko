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

// Get current user balance and name
$sql = "SELECT balance, name FROM users WHERE id = :id LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: logout.php");
    exit;
}

$currentBalance = $user['balance'];
$userName = $user['name'];

// Get daily withdrawal total for limit checking
$dailyWithdrawalTotal = getDailyWithdrawalTotal($_SESSION['user_id'], $pdo);
$dailyWithdrawalLimit = 1000.00;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Transaction - My ATM</title>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <span class="h3 mb-0">฿ My ATM</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="transaction.php">
                            <i class="fas fa-exchange-alt"></i> Transaction
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transfer.php">
                            <i class="fas fa-paper-plane"></i> Transfer
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="history.php">
                            <i class="fas fa-history"></i> History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <!-- Transaction Card -->
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-exchange-alt"></i> Make a Transaction
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Current Balance Display -->
                        <div class="alert alert-info d-flex align-items-center">
                            <i class="fas fa-wallet me-2"></i>
                            <div>
                                <strong>Current Balance:</strong>
                                <span id="currentBalance" class="h5 mb-0 ms-2">$<?php echo number_format($currentBalance, 2); ?></span>
                            </div>
                        </div>

                        <!-- Daily Withdrawal Limit Display -->
                        <div class="alert alert-warning d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <div>
                                <strong>Daily Withdrawal Status:</strong>
                                <span class="h6 mb-0 ms-2">
                                    $<?php echo number_format($dailyWithdrawalTotal, 2); ?> / $<?php echo number_format($dailyWithdrawalLimit, 2); ?>
                                </span>
                                <small class="d-block text-muted">
                                    <?php 
                                    $remaining = $dailyWithdrawalLimit - $dailyWithdrawalTotal;
                                    if ($remaining > 0) {
                                        echo "Remaining today: $" . number_format($remaining, 2);
                                    } else {
                                        echo "Daily limit reached. Try again tomorrow.";
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>

                        <!-- Message Area -->
                        <div id="messageArea" class="alert alert-dismissible fade show d-none" role="alert">
                            <div id="messageContent"></div>
                            <button type="button" class="btn-close" aria-label="Close" onclick="document.getElementById('messageArea').classList.add('d-none')"></button>
                        </div>

                        <!-- Transaction Form -->
                        <form id="transactionForm">
                            <?php echo getCSRFTokenField(); ?>
                            <div class="mb-3">
                                <label for="transaction_type" class="form-label">
                                    <i class="fas fa-tags"></i> Transaction Type
                                </label>
                                <select name="transaction_type" id="transaction_type" class="form-select" required>
                                    <option value="">Select Transaction Type</option>
                                    <option value="deposit">
                                        <i class="fas fa-plus-circle"></i> Deposit Money
                                    </option>
                                    <option value="withdraw">
                                        <i class="fas fa-minus-circle"></i> Withdraw Money
                                    </option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="amount" class="form-label">
                                    <i class="fas fa-dollar-sign"></i> Amount
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" 
                                           name="amount" 
                                           id="amount" 
                                           class="form-control"
                                           step="0.01" 
                                           min="0.01" 
                                           placeholder="Enter amount" 
                                           required>
                                </div>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> 
                                    Enter the amount you want to deposit or withdraw
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane"></i> Process Transaction
                                </button>
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        // Transaction-specific JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            const transactionForm = document.getElementById('transactionForm');
            const transactionType = document.getElementById('transaction_type');
            const amountInput = document.getElementById('amount');
            const currentBalance = <?php echo $currentBalance; ?>;
            const dailyWithdrawalTotal = <?php echo $dailyWithdrawalTotal; ?>;
            const dailyWithdrawalLimit = <?php echo $dailyWithdrawalLimit; ?>;

            // Form submission handler
            if (transactionForm) {
                transactionForm.addEventListener('submit', function(e) {
                    e.preventDefault(); // Always prevent default form submission
                    
                    const type = transactionType.value;
                    const amount = amountInput.value;
                    
                    // Basic client-side validation - only check for required fields
                    if (!type) {
                        showTransactionMessage('Please select a transaction type', 'error');
                        return;
                    }
                    
                    if (!amount || parseFloat(amount) <= 0) {
                        showTransactionMessage('Please enter a valid amount greater than 0', 'error');
                        return;
                    }
                    
                    // Let server handle all other validation including balance and daily limits
                    // This will show proper error messages via AJAX response
                    processTransactionAPI(type, amount);
                });
            }

            // Update amount input max value based on transaction type
            transactionType.addEventListener('change', function() {
                if (this.value === 'withdraw') {
                    const remainingDailyLimit = dailyWithdrawalLimit - dailyWithdrawalTotal;
                    const maxWithdraw = Math.min(currentBalance, remainingDailyLimit);
                    
                    // Remove restrictions - let users enter any amount and show errors via UI
                    amountInput.removeAttribute('max');
                    amountInput.disabled = false;
                    
                    if (remainingDailyLimit <= 0) {
                        amountInput.setAttribute('placeholder', 'Daily limit reached - but you can still try');
                    } else {
                        amountInput.setAttribute('placeholder', `Balance: $${currentBalance.toFixed(2)}, Daily limit remaining: $${remainingDailyLimit.toFixed(2)}`);
                    }
                } else {
                    amountInput.removeAttribute('max');
                    amountInput.setAttribute('placeholder', 'Enter amount');
                    amountInput.disabled = false;
                }
            });
        });

        // Quick transaction function
        function quickTransaction(type, amount) {
            // Set form values
            document.getElementById('transaction_type').value = type;
            document.getElementById('amount').value = amount;
            
            // Process transaction - let server handle all validation and show errors via UI
            processTransactionAPI(type, amount);
        }

        // Process transaction via API (reuse from app.js)
        function processTransactionAPI(type, amount) {
            showTransactionMessage('Processing transaction...', 'loading');
            
            // Disable form during processing
            const form = document.getElementById('transactionForm');
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            fetch('api/process_transaction.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    transaction_type: type,
                    amount: parseFloat(amount),
                    csrf_token: document.querySelector('input[name="csrf_token"]').value
                })
            })
            .then(response => {
                // Don't throw error for HTTP status codes like 400
                // Let the JSON response handle success/failure
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showTransactionMessage(data.message, 'success');
                    
                    // Update balance display
                    updateTransactionBalance(data.data.formatted_new_balance);
                    
                    // Clear form
                    document.getElementById('transactionForm').reset();
                    
                    // For withdrawal transactions, refresh the page to update daily withdrawal status
                    if (type === 'withdraw') {
                        setTimeout(() => {
                            showTransactionMessage('Refreshing page to update daily withdrawal status...', 'loading');
                            window.location.reload();
                        }, 2000);
                    }
                    
                } else {
                    // Show server-side validation errors (including daily limit errors)
                    showTransactionMessage(data.message || 'Transaction failed', 'error');
                }
            })
            .catch(error => {
                console.error('Transaction error:', error);
                showTransactionMessage('Network error. Please check your connection and try again.', 'error');
            })
            .finally(() => {
                // Re-enable form
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        }

        // Show message function for transaction page
        function showTransactionMessage(message, type) {
            const messageArea = document.getElementById('messageArea');
            const messageContent = document.getElementById('messageContent');
            
            if (messageArea && messageContent) {
                messageContent.textContent = message;
                
                messageArea.className = 'alert alert-dismissible fade show';
                
                switch(type) {
                    case 'success':
                        messageArea.classList.add('alert-success');
                        break;
                    case 'error':
                        messageArea.classList.add('alert-danger');
                        break;
                    case 'loading':
                        messageArea.classList.add('alert-info');
                        break;
                    default:
                        messageArea.classList.add('alert-secondary');
                }
                
                messageArea.classList.remove('d-none');
                
                if (type !== 'loading') {
                    setTimeout(() => {
                        messageArea.classList.add('d-none');
                    }, 5000);
                }
            }
        }

        // Update balance display
        function updateTransactionBalance(newBalance) {
            const balanceElement = document.getElementById('currentBalance');
            if (balanceElement) {
                balanceElement.textContent = '$' + newBalance;
                
                // Add animation
                balanceElement.style.color = '#28a745';
                balanceElement.style.transform = 'scale(1.05)';
                
                setTimeout(() => {
                    balanceElement.style.color = '';
                    balanceElement.style.transform = '';
                }, 500);
            }
        }
    </script>
</body>
</html>
