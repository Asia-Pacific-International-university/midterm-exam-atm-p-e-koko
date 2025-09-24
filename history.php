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

// Get current user info
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

// Handle filtering parameters
$filters = [
    'type' => $_GET['type'] ?? 'all',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Handle pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;

// Get filtered transactions
$transactionData = getTransactionsWithFilters($_SESSION['user_id'], $filters, $page, $perPage, $pdo);
$transactions = $transactionData['transactions'];
$totalTransactions = $transactionData['total'];
$totalPages = $transactionData['total_pages'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - My ATM</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <a class="nav-link active" href="history.php">
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
        <!-- Account Summary -->
        <div class="card bg-primary text-white mb-4 shadow">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h2 class="card-title mb-1">Transaction History</h2>
                        <p class="card-text mb-0">Welcome back, <?php echo htmlspecialchars($userName); ?>!</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h4 class="mb-1">Current Balance</h4>
                        <h2 class="mb-0">$<?php echo number_format($currentBalance, 2); ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body bg-light">
                <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="type" class="form-label">
                        <i class="fas fa-filter"></i> Transaction Type
                    </label>
                    <select name="type" id="type" class="form-select">
                        <option value="all" <?php echo $filters['type'] === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="deposit" <?php echo $filters['type'] === 'deposit' ? 'selected' : ''; ?>>Deposits</option>
                        <option value="withdraw" <?php echo $filters['type'] === 'withdraw' ? 'selected' : ''; ?>>Withdrawals</option>
                        <option value="transfer" <?php echo $filters['type'] === 'transfer' ? 'selected' : ''; ?>>Transfers</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="date_from" class="form-label">
                        <i class="fas fa-calendar-alt"></i> From Date
                    </label>
                    <input type="date" name="date_from" id="date_from" class="form-control" 
                           value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="date_to" class="form-label">
                        <i class="fas fa-calendar-alt"></i> To Date
                    </label>
                    <input type="date" name="date_to" id="date_to" class="form-control" 
                           value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                </div>
            </form>
            
            <?php if ($filters['type'] !== 'all' || !empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                <div class="mt-3">
                    <a href="history.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                    <small class="text-muted ms-2">
                        Showing <?php echo $totalTransactions; ?> transaction<?php echo $totalTransactions !== 1 ? 's' : ''; ?>
                        <?php if ($filters['type'] !== 'all'): ?>
                            of type "<?php echo ucfirst($filters['type']); ?>"
                        <?php endif; ?>
                        <?php if (!empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                            <?php if (!empty($filters['date_from']) && !empty($filters['date_to'])): ?>
                                between <?php echo $filters['date_from']; ?> and <?php echo $filters['date_to']; ?>
                            <?php elseif (!empty($filters['date_from'])): ?>
                                from <?php echo $filters['date_from']; ?> onwards
                            <?php elseif (!empty($filters['date_to'])): ?>
                                up to <?php echo $filters['date_to']; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </small>
                </div>
            <?php endif; ?>
            </div>
        </div>

        <!-- Transaction Table -->
        <div class="card shadow">
            <?php if (empty($transactions)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No transactions found</h4>
                    <p class="text-muted">
                        <?php if ($filters['type'] !== 'all' || !empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                            Try adjusting your filters or 
                            <a href="history.php" class="text-decoration-none">clear all filters</a>
                        <?php else: ?>
                            You haven't made any transactions yet.
                        <?php endif; ?>
                    </p>
                    <div class="mt-4">
                        <a href="transaction.php" class="btn btn-primary me-2">
                            <i class="fas fa-plus"></i> Make Transaction
                        </a>
                        <a href="transfer.php" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i> Transfer Money
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th><i class="fas fa-calendar"></i> Date & Time</th>
                                <th><i class="fas fa-tag"></i> Type</th>
                                <th><i class="fas fa-info-circle"></i> Description</th>
                                <th class="text-end"><i class="fas fa-dollar-sign"></i> Amount</th>
                                <th class="text-end"><i class="fas fa-wallet"></i> Balance After</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr class="<?php echo getTransactionClass($transaction['type'], $transaction['amount']); ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php echo getTransactionIcon($transaction['type'], $transaction['amount']); ?>
                                            <div class="ms-2">
                                                <div class="fw-semibold">
                                                    <?php echo date('M j, Y', strtotime($transaction['created_at'])); ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date('g:i A', strtotime($transaction['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($transaction['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo getTransactionDescription($transaction['type'], $transaction['amount']); ?>
                                    </td>
                                    <td class="text-end">
                                        <span class="<?php echo $transaction['amount'] >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold'; ?>">
                                            <?php echo formatTransactionAmount($transaction['amount'], $transaction['type']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-muted fw-semibold">
                                            $<?php echo number_format($transaction['balance_after'], 2); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center p-3 bg-light border-top">
                        <div class="text-muted small">
                            Showing <?php echo (($page - 1) * $perPage) + 1; ?> to 
                            <?php echo min($page * $perPage, $totalTransactions); ?> of 
                            <?php echo $totalTransactions; ?> transactions
                        </div>
                        
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <!-- Previous Page -->
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">
                                            <i class="fas fa-chevron-left"></i>
                                        </span>
                                    </li>
                                <?php endif; ?>

                                <!-- Page Numbers -->
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                if ($startPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                                    </li>
                                    <?php if ($startPage > 2): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif;
                                endif;

                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor;

                                if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>">
                                            <?php echo $totalPages; ?>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <!-- Next Page -->
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">
                                            <i class="fas fa-chevron-right"></i>
                                        </span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-plus-circle fa-2x text-success mb-2"></i>
                        <h6 class="card-title">Make Deposit</h6>
                        <a href="transaction.php" class="btn btn-success btn-sm">Go to Transaction</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-paper-plane fa-2x text-primary mb-2"></i>
                        <h6 class="card-title">Transfer Money</h6>
                        <a href="transfer.php" class="btn btn-primary btn-sm">Send Money</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-tachometer-alt fa-2x text-info mb-2"></i>
                        <h6 class="card-title">Dashboard</h6>
                        <a href="dashboard.php" class="btn btn-info btn-sm">View Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Auto-set max date for date inputs to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const dateFromInput = document.getElementById('date_from');
            const dateToInput = document.getElementById('date_to');
            
            dateFromInput.setAttribute('max', today);
            dateToInput.setAttribute('max', today);
            
            // Ensure date_from is not greater than date_to
            dateFromInput.addEventListener('change', function() {
                if (dateToInput.value && this.value > dateToInput.value) {
                    dateToInput.value = this.value;
                }
                dateToInput.setAttribute('min', this.value);
            });
            
            dateToInput.addEventListener('change', function() {
                if (dateFromInput.value && this.value < dateFromInput.value) {
                    dateFromInput.value = this.value;
                }
                dateFromInput.setAttribute('max', this.value);
            });
        });
    </script>
</body>
</html>
