<?php
session_start();
require "includes/db.php";

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css" type="text/css">
    <title>Dashboard</title>
</head>
<body>
    <div class="dashboard-container">
        <div class="card">
            <h2 class="card-title">Welcome, <?php echo $name; ?> 👋</h2>
            <p class="card-balance">Your Balance</p>
            <h1 class="balance-amount">฿<?php echo $balance; ?></h1>
        </div>

        <div class="menu">
            <a href="transaction.php" class="btn">💰 Make Transaction</a>
            <a href="history.php" class="btn">📜 View History</a>
            <a href="logout.php" class="btn btn-danger">🚪 Logout</a>
        </div>
    </div>
</body>
</html>
