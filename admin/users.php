<?php
// Include admin authentication
include "auth_admin.php";

// Handle POST actions (lock/unlock users)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        $action = $_POST['action'];
        
        // Verify user exists and is not an admin
        $targetUser = getUserById($pdo, $userId);
        
        if (!$targetUser) {
            header("Location: users.php?error=user_not_found");
            exit;
        }
        
        if ($targetUser['role'] === 'admin') {
            header("Location: users.php?error=cannot_modify_admin");
            exit;
        }
        
        $success = false;
        $successMessage = '';
        
        if ($action === 'lock') {
            $success = toggleUserLock($pdo, $userId, true);
            $successMessage = 'user_locked';
        } elseif ($action === 'unlock') {
            $success = toggleUserLock($pdo, $userId, false);
            $successMessage = 'user_unlocked';
        }
        
        if ($success) {
            header("Location: users.php?success=$successMessage");
        } else {
            header("Location: users.php?error=action_failed");
        }
        exit;
    }
}

// Get filter parameters
$view = $_GET['view'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query based on filters
$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (name LIKE :search OR email LIKE :search)";
    $params[':search'] = "%$search%";
}

switch ($view) {
    case 'locked':
        $whereClause .= " AND lock_until IS NOT NULL AND lock_until > NOW()";
        break;
    case 'recent':
        $whereClause .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'admins':
        $whereClause .= " AND role = 'admin'";
        break;
    case 'users':
        $whereClause .= " AND role = 'user'";
        break;
}

// Get users with applied filters
$sql = "SELECT id, name, email, balance, role, failed_attempts, lock_until, created_at 
        FROM users 
        $whereClause 
        ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle user detail view
$viewUser = null;
$userTransactions = null;
if (isset($_GET['user_id'])) {
    $userId = (int)$_GET['user_id'];
    $viewUser = getUserById($pdo, $userId);
    
    if ($viewUser) {
        $userTransactions = getUserTransactions($pdo, $userId, 20);
    }
}

// Handle success/error messages
$message = '';
$error = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'user_locked':
            $message = 'User account has been successfully locked.';
            break;
        case 'user_unlocked':
            $message = 'User account has been successfully unlocked.';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'user_not_found':
            $error = 'User not found.';
            break;
        case 'cannot_modify_admin':
            $error = 'Cannot modify admin accounts.';
            break;
        case 'action_failed':
            $error = 'Action failed. Please try again.';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .status-badge {
            font-size: 0.75rem;
        }
        
        .amount-positive {
            color: #198754;
            font-weight: bold;
        }
        
        .amount-negative {
            color: #dc3545;
            font-weight: bold;
        }
        
        .user-info-grid .card {
            height: 100%;
        }
    </style>
</head>
<body>
    <!-- Admin Header -->
    <div class="admin-header py-5 mb-4">
        <div class="container">
            <h1 class="display-4 mb-2"><i class="fas fa-users me-3"></i>User Management</h1>
            <p class="lead mb-0">Manage user accounts and view transaction histories</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-shield-alt me-2"></i>Admin Panel
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="users.php">
                            <i class="fas fa-users me-2"></i>User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">
                            <i class="fas fa-eye me-2"></i>User View
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- User Detail View -->
        <?php if ($viewUser): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 py-3 d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <i class="fas fa-user me-2"></i>User Details: <?php echo htmlspecialchars($viewUser['name']); ?>
                    </h4>
                    <a href="users.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-2"></i>Back to List
                    </a>
                </div>
                <div class="card-body">
                    <!-- User Information Grid -->
                    <div class="row g-3 user-info-grid mb-4">
                        <div class="col-md-6 col-lg-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title text-muted mb-2">
                                        <i class="fas fa-user me-2"></i>Name
                                    </h6>
                                    <p class="card-text"><?php echo htmlspecialchars($viewUser['name']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title text-muted mb-2">
                                        <i class="fas fa-envelope me-2"></i>Email
                                    </h6>
                                    <p class="card-text"><?php echo htmlspecialchars($viewUser['email']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title text-muted mb-2">
                                        <i class="fas fa-wallet me-2"></i>Current Balance
                                    </h6>
                                    <p class="card-text text-success fw-bold">$<?php echo number_format($viewUser['balance'], 2); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title text-muted mb-2">
                                        <i class="fas fa-shield-alt me-2"></i>Role
                                    </h6>
                                    <p class="card-text">
                                        <span class="badge bg-<?php echo $viewUser['role'] === 'admin' ? 'primary' : 'success'; ?> status-badge">
                                            <?php echo ucfirst($viewUser['role']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title text-muted mb-2">
                                        <i class="fas fa-info-circle me-2"></i>Account Status
                                    </h6>
                                    <p class="card-text">
                                        <?php if (isUserLocked($viewUser)): ?>
                                            <span class="badge bg-danger status-badge">Locked</span>
                                            <small class="d-block text-muted mt-1">
                                                Until: <?php echo date('Y-m-d H:i:s', strtotime($viewUser['lock_until'])); ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="badge bg-success status-badge">Active</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title text-muted mb-2">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Failed Attempts
                                    </h6>
                                    <p class="card-text">
                                        <span class="badge bg-warning text-dark"><?php echo $viewUser['failed_attempts']; ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title text-muted mb-2">
                                        <i class="fas fa-calendar-alt me-2"></i>Member Since
                                    </h6>
                                    <p class="card-text"><?php echo date('Y-m-d H:i:s', strtotime($viewUser['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title text-muted mb-2">
                                        <i class="fas fa-cogs me-2"></i>Actions
                                    </h6>
                                    <div class="card-text">
                                        <?php if ($viewUser['role'] !== 'admin'): ?>
                                            <?php if (isUserLocked($viewUser)): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $viewUser['id']; ?>">
                                                    <input type="hidden" name="action" value="unlock">
                                                    <button type="submit" class="btn btn-success btn-sm" 
                                                            onclick="return confirm('Are you sure you want to unlock this account?')">
                                                        <i class="fas fa-unlock me-1"></i>Unlock
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $viewUser['id']; ?>">
                                                    <input type="hidden" name="action" value="lock">
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Are you sure you want to lock this account?')">
                                                        <i class="fas fa-lock me-1"></i>Lock
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <em class="text-muted">Cannot modify admin accounts</em>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction History -->
                    <h5 class="mb-3"><i class="fas fa-history me-2"></i>Recent Transaction History</h5>
                    <?php if ($userTransactions && count($userTransactions) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th><i class="fas fa-calendar me-2"></i>Date</th>
                                        <th><i class="fas fa-exchange-alt me-2"></i>Type</th>
                                        <th><i class="fas fa-dollar-sign me-2"></i>Amount</th>
                                        <th><i class="fas fa-wallet me-2"></i>Balance After</th>
                                        <th><i class="fas fa-sticky-note me-2"></i>Memo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userTransactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($transaction['created_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $transaction['type'] === 'withdraw' ? 'danger' : ($transaction['type'] === 'transfer' ? 'warning' : 'success'); ?>">
                                                    <?php echo ucfirst($transaction['type']); ?>
                                                </span>
                                            </td>
                                            <td class="<?php echo $transaction['type'] === 'withdraw' ? 'amount-negative' : 'amount-positive'; ?>">
                                                <?php echo ($transaction['type'] === 'withdraw' ? '-' : '+') . '$' . number_format($transaction['amount'], 2); ?>
                                            </td>
                                            <td class="fw-bold">$<?php echo number_format($transaction['balance_after'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['memo'] ?? '—'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No transactions found for this user.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-filter me-2"></i>Filter Users
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="view" class="form-label fw-bold">View:</label>
                            <select name="view" id="view" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $view === 'all' ? 'selected' : ''; ?>>All Users</option>
                                <option value="users" <?php echo $view === 'users' ? 'selected' : ''; ?>>Regular Users</option>
                                <option value="admins" <?php echo $view === 'admins' ? 'selected' : ''; ?>>Administrators</option>
                                <option value="locked" <?php echo $view === 'locked' ? 'selected' : ''; ?>>Locked Accounts</option>
                                <option value="recent" <?php echo $view === 'recent' ? 'selected' : ''; ?>>Recent Registrations</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="search" class="form-label fw-bold">Search:</label>
                            <input type="text" name="search" id="search" class="form-control" 
                                   placeholder="Name or email..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                            
                            <?php if ($search || $view !== 'all'): ?>
                                <a href="users.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list me-2"></i>Users List
                        <small class="text-muted">(<?php echo count($users); ?> users found)</small>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th><i class="fas fa-user me-2"></i>Name</th>
                                    <th><i class="fas fa-envelope me-2"></i>Email</th>
                                    <th><i class="fas fa-shield-alt me-2"></i>Role</th>
                                    <th><i class="fas fa-wallet me-2"></i>Balance</th>
                                    <th><i class="fas fa-info-circle me-2"></i>Status</th>
                                    <th><i class="fas fa-exclamation-triangle me-2"></i>Failed</th>
                                    <th><i class="fas fa-calendar me-2"></i>Joined</th>
                                    <th><i class="fas fa-cogs me-2"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'primary' : 'success'; ?> status-badge">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold text-success">$<?php echo number_format($user['balance'], 2); ?></td>
                                            <td>
                                                <?php if (isUserLocked($user)): ?>
                                                    <span class="badge bg-danger status-badge">Locked</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success status-badge">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning text-dark"><?php echo $user['failed_attempts']; ?></span>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="users.php?user_id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-eye me-1"></i>View
                                                    </a>
                                                    
                                                    <?php if ($user['role'] !== 'admin'): ?>
                                                        <?php if (isUserLocked($user)): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <input type="hidden" name="action" value="unlock">
                                                                <button type="submit" class="btn btn-outline-success btn-sm" 
                                                                        onclick="return confirm('Unlock this account?')">
                                                                    <i class="fas fa-unlock me-1"></i>Unlock
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <input type="hidden" name="action" value="lock">
                                                                <button type="submit" class="btn btn-outline-danger btn-sm" 
                                                                        onclick="return confirm('Lock this account?')">
                                                                    <i class="fas fa-lock me-1"></i>Lock
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="fas fa-users fa-3x mb-3"></i>
                                            <br>No users found matching your criteria.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>