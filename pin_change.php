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

$message = '';
$messageType = 'danger'; // Bootstrap alert type (success, danger, warning, info)

// Handle PIN change form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPin = cleanInput($_POST['current_pin']);
    $newPin = cleanInput($_POST['new_pin']);
    $confirmPin = cleanInput($_POST['confirm_pin']);
    
    // Basic validation
    if (empty($currentPin) || empty($newPin) || empty($confirmPin)) {
        $message = 'All fields are required.';
    } elseif ($newPin !== $confirmPin) {
        $message = 'New PIN and confirmation do not match.';
    } elseif (strlen($newPin) < 4 || strlen($newPin) > 6) {
        $message = 'New PIN must be 4-6 digits long.';
    } elseif (!preg_match('/^[0-9]+$/', $newPin)) {
        $message = 'PIN must contain only digits.';
    } elseif ($currentPin === $newPin) {
        $message = 'New PIN must be different from current PIN.';
    } else {
        // Attempt to change PIN using helper function
        $result = changePIN($_SESSION['user_id'], $currentPin, $newPin, $pdo);
        
        if ($result['success']) {
            $message = $result['message'];
            $messageType = 'success';
            
            // Clear form on success
            $_POST = [];
        } else {
            $message = $result['message'];
        }
    }
}

// Get user info for display
$sql = "SELECT name FROM users WHERE id = :id LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: logout.php");
    exit;
}

$name = htmlspecialchars($user['name']);
$initial = strtoupper(substr($name, 0, 1));
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
    <title>Change PIN - My ATM</title>
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
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
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
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                <div class="fw-bold">
                                    <?php echo $initial; ?>
                                </div>
                                <?php echo $name; ?>
                            </div>
                        </a>
                        <ul class="dropdown-menu">
                            <li><h6 class="dropdown-header">Welcome back!</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item active" href="pin_change.php">
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
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-key me-2"></i>Change PIN
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Alert Messages -->
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="pin_change.php">
                            <div class="mb-3">
                                <label for="current_pin" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Current PIN
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="current_pin" 
                                       name="current_pin" 
                                       required 
                                       maxlength="6" 
                                       pattern="[0-9]{4,6}"
                                       placeholder="Enter your current PIN"
                                       autocomplete="current-password">
                                <div class="form-text">Enter your current 4-6 digit PIN</div>
                            </div>

                            <div class="mb-3">
                                <label for="new_pin" class="form-label">
                                    <i class="fas fa-key me-2"></i>New PIN
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="new_pin" 
                                       name="new_pin" 
                                       required 
                                       maxlength="6" 
                                       minlength="4"
                                       pattern="[0-9]{4,6}"
                                       placeholder="Enter your new PIN"
                                       autocomplete="new-password">
                                <div class="form-text">Choose a new 4-6 digit PIN (numbers only)</div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_pin" class="form-label">
                                    <i class="fas fa-check-double me-2"></i>Confirm New PIN
                                </label>
                                <input type="password" 
                                       class="form-control" 
                                       id="confirm_pin" 
                                       name="confirm_pin" 
                                       required 
                                       maxlength="6" 
                                       minlength="4"
                                       pattern="[0-9]{4,6}"
                                       placeholder="Confirm your new PIN"
                                       autocomplete="new-password">
                                <div class="form-text">Re-enter your new PIN to confirm</div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>Change PIN
                                </button>
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Security Information -->
                <div class="card mt-4 border-info">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security Tips</h6>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>Choose a PIN that's easy for you to remember but hard for others to guess</li>
                            <li>Don't use sequential numbers (1234) or repeated digits (1111)</li>
                            <li>Don't share your PIN with anyone</li>
                            <li>Change your PIN regularly for better security</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- PIN Confirmation Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const newPinInput = document.getElementById('new_pin');
            const confirmPinInput = document.getElementById('confirm_pin');
            
            function validatePinMatch() {
                if (confirmPinInput.value && newPinInput.value !== confirmPinInput.value) {
                    confirmPinInput.setCustomValidity('PINs do not match');
                } else {
                    confirmPinInput.setCustomValidity('');
                }
            }
            
            newPinInput.addEventListener('input', validatePinMatch);
            confirmPinInput.addEventListener('input', validatePinMatch);
        });
    </script>
</body>
</html>