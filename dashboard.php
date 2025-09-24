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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css" type="text/css">
    <title>Dashboard</title>
</head>
<body class="dashboard-page">
    <div class="dashboard-wrapper">
        <header class="dashboard-header">
            <div class="brand">
                <div class="brand-logo">฿</div>
                <div class="brand-name">My ATM</div>
            </div>
            <div class="user-info">
                <div class="avatar" aria-hidden="true"><?php echo $initial; ?></div>
                <div class="user-meta">
                    <div class="hello">Welcome back,</div>
                    <div class="user-name"><?php echo $name; ?></div>
                </div>
                <a href="logout.php" class="header-link">Logout</a>
            </div>
        </header>

        <main class="dashboard-main">
            <section class="dashboard-grid">
                <!-- Balance & Actions -->
                <div class="card balance-card">
                    <div class="balance-top">
                        <div class="balance-label">Current Balance</div>
                        <div class="balance-amount-lg">฿<?php echo $balance; ?></div>
                    </div>
                    <div class="actions">
                        <a href="transaction.php" class="action-btn">
                            <span class="icon">💰</span>
                            <span class="label">Make Transaction</span>
                        </a>
                        <a href="history.php" class="action-btn">
                            <span class="icon">📜</span>
                            <span class="label">View History</span>
                        </a>
                        <a href="logout.php" class="action-btn danger">
                            <span class="icon">🚪</span>
                            <span class="label">Logout</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="card activities-card">
                    <div class="card-head">
                        <h3>Recent Activities</h3>
                        <span class="muted">Last 5 events</span>
                    </div>
                    <?php if (empty($recentActivities)): ?>
                        <p class="empty-state">No recent activities</p>
                    <?php else: ?>
                        <div class="activity-list">
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="activity-row">
                                    <div class="activity-icon" title="<?php echo htmlspecialchars($activity['activity_type']); ?>"><?php echo activityIcon($activity['activity_type']); ?></div>
                                    <div class="activity-info">
                                        <div class="activity-title"><?php echo activityLabel($activity['activity_type']); ?></div>
                                        <?php if ($activity['description']): ?>
                                            <div class="activity-desc"><?php echo htmlspecialchars($activity['description']); ?></div>
                                        <?php endif; ?>
                                        <div class="activity-time">
                                            <?php 
                                            $activityTime = new DateTime($activity['created_at']);
                                            echo $activityTime->format('M j, Y g:i A');
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
