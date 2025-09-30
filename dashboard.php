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

// Fetch user balance from DB
$sql  = "SELECT balance, name FROM users WHERE id = :id LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: logout.php");
    exit;
}

$balance = number_format($user['balance'], 2);
$name    = htmlspecialchars($user['name']);
$initial = strtoupper(substr($name, 0, 1));

// Get recent activities
$recentActivities = getRecentActivities($_SESSION['user_id'], 5, $pdo);

// Ensure CSRF token is generated for this session
$csrfToken = generateCSRFToken();
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
    <title>Dashboard - My ATM</title>
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
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transaction.php">
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
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <div class="d-inline-flex align-items-center">
                                <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center me-2" 
                                     style="width: 32px; height: 32px; font-weight: bold;">
                                    <?php echo $initial; ?>
                                </div>
                                <?php echo $name; ?>
                            </div>
                        </a>
                        <ul class="dropdown-menu">
                            <li><h6 class="dropdown-header">Welcome back!</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="pin_change.php">
                                <i class="fas fa-key"></i> Change PIN
                            </a></li>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Message Area -->
        <div id="messageArea" class="alert alert-dismissible fade show d-none" role="alert" style="word-wrap: break-word; white-space: normal;">
            <div id="messageContent" style="margin-right: 20px; line-height: 1.5;"></div>
            <button type="button" class="btn-close" aria-label="Close" onclick="document.getElementById('messageArea').classList.add('d-none')"></button>
        </div>

        <div class="row">
            <!-- Balance and Quick Actions -->
            <div class="col-lg-8">
                <!-- Balance Card -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-wallet"></i> Account Balance
                        </h4>
                    </div>
                    <div class="card-body text-center">
                        <div class="display-4 text-primary mb-3" id="currentBalance">
                            $<?php echo $balance; ?>
                        </div>
                        <p class="text-muted">Available Balance</p>
                    </div>
                </div>

                <!-- Quick Transaction Forms -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card shadow">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-plus-circle"></i> Quick Deposit
                                </h5>
                            </div>
                            <div class="card-body">
                                <form id="depositForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="mb-3">
                                        <label for="deposit_amount" class="form-label">Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" id="deposit_amount" class="form-control" 
                                                   step="0.01" min="0.01" placeholder="Enter amount" required>
                                        </div>
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-plus"></i> Deposit Money
                                        </button>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-outline-success btn-sm" onclick="setAmount('deposit_amount', 50)">$50</button>
                                            <button type="button" class="btn btn-outline-success btn-sm" onclick="setAmount('deposit_amount', 100)">$100</button>
                                            <button type="button" class="btn btn-outline-success btn-sm" onclick="setAmount('deposit_amount', 200)">$200</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card shadow">
                            <div class="card-header bg-warning text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-minus-circle"></i> Quick Withdraw
                                </h5>
                            </div>
                            <div class="card-body">
                                <form id="withdrawForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="mb-3">
                                        <label for="withdraw_amount" class="form-label">Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" id="withdraw_amount" class="form-control" 
                                                   step="0.01" min="0.01" max="<?php echo $user['balance']; ?>" 
                                                   placeholder="Enter amount" required>
                                        </div>
                                        <div class="form-text">
                                            Max: $<?php echo number_format($user['balance'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-minus"></i> Withdraw Money
                                        </button>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="setAmount('withdraw_amount', 20)">$20</button>
                                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="setAmount('withdraw_amount', 50)">$50</button>
                                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="setAmount('withdraw_amount', 100)">$100</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Cards -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card shadow text-center">
                            <div class="card-body">
                                <i class="fas fa-paper-plane fa-3x text-primary mb-3"></i>
                                <h5 class="card-title">Transfer Money</h5>
                                <p class="card-text">Send money to other users instantly</p>
                                <a href="transfer.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-right"></i> Transfer
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow text-center">
                            <div class="card-body">
                                <i class="fas fa-exchange-alt fa-3x text-info mb-3"></i>
                                <h5 class="card-title">Make Transaction</h5>
                                <p class="card-text">Deposit or withdraw money</p>
                                <a href="transaction.php" class="btn btn-info">
                                    <i class="fas fa-arrow-right"></i> Transaction
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow text-center">
                            <div class="card-body">
                                <i class="fas fa-history fa-3x text-secondary mb-3"></i>
                                <h5 class="card-title">Transaction History</h5>
                                <p class="card-text">View your transaction history</p>
                                <a href="history.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-right"></i> History
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities Sidebar -->
            <div class="col-lg-4">
                <div class="card shadow">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-clock"></i> Recent Activities
                        </h5>
                        <small class="text-muted">Last 5 transactions</small>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentActivities)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No recent activities</p>
                                <small>Your transactions will appear here</small>
                            </div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($recentActivities as $activity): ?>
                                    <div class="d-flex mb-3 pb-3 border-bottom">
                                        <div class="flex-shrink-0">
                                            <div class="bg-light rounded-circle p-2 text-center" style="width: 40px; height: 40px;">
                                                <?php echo activityIcon($activity['activity_type']); ?>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-1"><?php echo activityLabel($activity['activity_type']); ?></h6>
                                            <?php if ($activity['description']): ?>
                                                <p class="mb-1 small text-muted">
                                                    <?php echo htmlspecialchars($activity['description']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <small class="text-muted">
                                                <?php 
                                                $activityTime = new DateTime($activity['created_at']);
                                                echo $activityTime->format('M j, Y g:i A');
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center">
                                <a href="history.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-history"></i> View All History
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        // Set amount helper function
        function setAmount(inputId, amount) {
            const input = document.getElementById(inputId);
            const currentBalance = <?php echo $user['balance']; ?>;
            
            if (inputId === 'withdraw_amount' && amount > currentBalance) {
                showMessage('Insufficient balance for this amount', 'error');
                return;
            }
            
            input.value = amount;
            input.focus();
        }

        // Form handling is done in app.js - no duplicate handlers needed here

        // Show message function for dashboard
        function showMessage(message, type) {
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
                    case 'info':
                        messageArea.classList.add('alert-primary');
                        break;
                    case 'warning':
                        messageArea.classList.add('alert-warning');
                        break;
                    default:
                        messageArea.classList.add('alert-secondary');
                }
                
                messageArea.classList.remove('d-none');
                
                if (type !== 'loading') {
                    const hideDelay = type === 'error' ? 8000 : 5000; // Show errors longer
                    setTimeout(() => {
                        messageArea.classList.add('d-none');
                    }, hideDelay);
                }
            }
        }

        // Update balance display after successful transaction
        function updateBalance(newBalance) {
            const balanceElement = document.getElementById('currentBalance');
            if (balanceElement) {
                balanceElement.textContent = '$' + newBalance;
                
                // Add success animation
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
