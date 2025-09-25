<?php
// Include admin authentication
include "auth_admin.php";

// Get system statistics
$stats = getSystemStats($pdo);

// Handle any success/error messages
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
        default:
            $message = 'Action completed successfully.';
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'access_denied':
            $error = 'Access denied. Admin privileges required.';
            break;
        case 'user_not_found':
            $error = 'User not found.';
            break;
        case 'action_failed':
            $error = 'Action failed. Please try again.';
            break;
        default:
            $error = 'An error occurred.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ATM System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #0d6efd;
        }
        
        .stat-icon {
            font-size: 2rem;
            opacity: 0.3;
            position: absolute;
            top: 15px;
            right: 15px;
        }
    </style>
</head>
<body>
    <!-- Admin Header -->
    <div class="admin-header py-5 mb-4">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1 class="display-4 mb-2"><i class="fas fa-tachometer-alt me-3"></i>Admin Dashboard</h1>
                    <p class="lead mb-0">Welcome back, <?php echo htmlspecialchars($currentAdmin['name']); ?>!</p>
                </div>
            </div>
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
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
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

        <!-- System Statistics -->
        <div class="row g-4 mb-5">
            <div class="col-md-6 col-xl-2">
                <div class="card border-0 shadow-sm h-100 position-relative">
                    <div class="card-body text-center">
                        <div class="stat-number mb-2"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="text-muted small text-uppercase">Total Users</div>
                        <i class="fas fa-users stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-xl-2">
                <div class="card border-0 shadow-sm h-100 position-relative">
                    <div class="card-body text-center">
                        <div class="stat-number mb-2"><?php echo number_format($stats['total_admins']); ?></div>
                        <div class="text-muted small text-uppercase">Total Admins</div>
                        <i class="fas fa-user-shield stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-xl-2">
                <div class="card border-0 shadow-sm h-100 position-relative">
                    <div class="card-body text-center">
                        <div class="stat-number mb-2 text-danger"><?php echo number_format($stats['locked_accounts']); ?></div>
                        <div class="text-muted small text-uppercase">Locked Accounts</div>
                        <i class="fas fa-lock stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100 position-relative">
                    <div class="card-body text-center">
                        <div class="stat-number mb-2 text-success"><?php echo number_format($stats['transactions_today']); ?></div>
                        <div class="text-muted small text-uppercase">Transactions Today</div>
                        <i class="fas fa-exchange-alt stat-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-12 col-xl-3">
                <div class="card border-0 shadow-sm h-100 position-relative">
                    <div class="card-body text-center">
                        <div class="stat-number mb-2 text-info">$<?php echo number_format($stats['total_balance'], 2); ?></div>
                        <div class="text-muted small text-uppercase">Total System Balance</div>
                        <i class="fas fa-dollar-sign stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-auto">
                                <a href="users.php" class="btn btn-primary">
                                    <i class="fas fa-users me-2"></i>Manage Users
                                </a>
                            </div>
                            <div class="col-auto">
                                <a href="users.php?view=locked" class="btn btn-danger">
                                    <i class="fas fa-lock me-2"></i>View Locked Accounts
                                </a>
                            </div>
                            <div class="col-auto">
                                <a href="users.php?view=recent" class="btn btn-success">
                                    <i class="fas fa-user-plus me-2"></i>Recent Registrations
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-0 py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>System Overview
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong class="text-success">System Status:</strong>
                            <span class="badge bg-success ms-2">Operational</span>
                        </div>
                        <div class="mb-3">
                            <strong>Last Update:</strong><br>
                            <small class="text-muted"><?php echo date('Y-m-d H:i:s'); ?></small>
                        </div>
                        <div class="mb-0">
                            <strong>Your Session:</strong><br>
                            <small class="text-muted">Started at <?php echo date('Y-m-d H:i:s'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>