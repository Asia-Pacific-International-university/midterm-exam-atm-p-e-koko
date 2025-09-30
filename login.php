<?php
session_start(); // start session at the top
include "includes/helpers.php";
include "includes/db.php";

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$message = ""; // store error messages

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!validateCSRFToken()) {
        $message = "Security token validation failed. Please try again.";
    } else {
        // Clean input
        $email = cleanInput($_POST['email']);
        $pin   = cleanInput($_POST['pin']);
        
        // Basic input check (not showing specific errors for security)
        if (!empty($email) && !empty($pin)) {
        // Fetch user by email including lock information
        $sql = "SELECT id, name, email, pin, failed_attempts, lock_until FROM users WHERE email = :email LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Check if account is currently locked
            if ($user['lock_until'] && new DateTime() < new DateTime($user['lock_until'])) {
                $lockTimeRemaining = (new DateTime($user['lock_until']))->diff(new DateTime())->s;
                $message = "Account is locked. Please wait " . $lockTimeRemaining . " seconds before trying again.";
                
                // Log the locked account attempt using new logger
                log_activity($user['id'], 'account_locked', 'Attempted login while account locked', $pdo);
            } 
            // Verify PIN
            else if (password_verify($pin, $user['pin'])) {
                // Login success → reset failed attempts and store user in session
                $resetSql = "UPDATE users SET failed_attempts = 0, lock_until = NULL WHERE id = :id";
                $resetStmt = $pdo->prepare($resetSql);
                $resetStmt->execute([':id' => $user['id']]);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                
                // Generate CSRF token for security (ensures token is available for all forms)
                generateCSRFToken();
                
                // Log successful login using new logger
                log_activity($user['id'], 'login', 'Successful login from ' . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown IP'), $pdo);
                
                header("Location: dashboard.php");
                exit; // stop execution after redirect
            } else {
                // Wrong PIN → increment failed attempts
                $failedAttempts = $user['failed_attempts'] + 1;
                
                if ($failedAttempts >= 5) {
                    // Lock account for 10 seconds
                    $lockUntil = (new DateTime())->add(new DateInterval('PT10S'))->format('Y-m-d H:i:s');
                    $updateSql = "UPDATE users SET failed_attempts = :failed_attempts, lock_until = :lock_until WHERE id = :id";
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([
                        ':failed_attempts' => $failedAttempts,
                        ':lock_until' => $lockUntil,
                        ':id' => $user['id']
                    ]);
                    
                    $message = "Too many failed attempts. Account locked for 10 seconds.";
                    // Log account lock using new logger
                    log_activity($user['id'], 'account_locked', 'Account locked after 5 failed login attempts', $pdo);
                } else {
                    // Just increment failed attempts
                    $updateSql = "UPDATE users SET failed_attempts = :failed_attempts WHERE id = :id";
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([
                        ':failed_attempts' => $failedAttempts,
                        ':id' => $user['id']
                    ]);
                    
                    $remainingAttempts = 5 - $failedAttempts;
                    $message = "Wrong email or pin";
                }
                
                // Log failed login attempt using new logger
                log_activity($user['id'], 'failed_login', 'Failed login attempt with wrong PIN', $pdo);
            }
        } else {
            // Email not found
            $message = "Wrong username or password.";
        }
        } else {
            $message = "Please enter both email and PIN.";
        }
    }
}
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
    <title>Login - My ATM</title>
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow border-0">
                    <div class="card-body p-4">
                        <!-- Logo/Brand -->
                        <div class="text-center mb-4">
                            <h2 class="h3 fw-bold text-primary">
                                <i class="fas fa-university"></i> My ATM
                            </h2>
                            <p class="text-muted">Sign in to your account</p>
                        </div>

                        <!-- Error Message -->
                        <?php if ($message != ""): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Login Form -->
                        <form action="login.php" method="POST">
                            <?php echo getCSRFTokenField(); ?>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label fw-semibold">
                                    <i class="fas fa-envelope text-primary"></i> Email Address
                                </label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       class="form-control form-control-lg" 
                                       placeholder="Enter your email"
                                       required>
                            </div>

                            <div class="mb-4">
                                <label for="pin" class="form-label fw-semibold">
                                    <i class="fas fa-key text-primary"></i> PIN
                                </label>
                                <input type="password" 
                                       id="pin" 
                                       name="pin" 
                                       class="form-control form-control-lg" 
                                       placeholder="Enter your PIN"
                                       maxlength="6"
                                       required>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> Enter your 4-6 digit PIN
                                </div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg fw-semibold">
                                    <i class="fas fa-sign-in-alt"></i> Sign In
                                </button>
                            </div>
                        </form>

                        <!-- Register Link -->
                        <div class="text-center">
                            <p class="mb-0 text-muted">
                                Don't have an account? 
                                <a href="register.php" class="text-decoration-none fw-semibold">
                                    Create Account
                                </a>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Additional Info -->
                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt"></i> 
                        Secure banking with advanced encryption
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
