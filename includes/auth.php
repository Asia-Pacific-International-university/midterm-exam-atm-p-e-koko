<?php
include "db.php"; // connect to database
include "helpers.php"; // include helper functions

$email = cleanInput($_POST['email']);
$pin   = cleanInput($_POST['pin']);

// Fetch user by email including lock information
$sql  = "SELECT id, name, email, pin, failed_attempts, lock_until FROM users WHERE email = :email LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // Check if account is currently locked
    if ($user['lock_until'] && new DateTime() < new DateTime($user['lock_until'])) {
        $lockTimeRemaining = (new DateTime($user['lock_until']))->diff(new DateTime())->s;
        $message = "Account is locked. Please wait " . $lockTimeRemaining . " seconds before trying again.";
        
        // Log the locked account attempt
        logActivity($user['id'], 'account_locked', 'Attempted login while account locked', $pdo);
    } 
    // Verify PIN
    else if (password_verify($pin, $user['pin'])) {
        // Login success → reset failed attempts and store user in session
        $resetSql = "UPDATE users SET failed_attempts = 0, lock_until = NULL WHERE id = :id";
        $resetStmt = $pdo->prepare($resetSql);
        $resetStmt->execute([':id' => $user['id']]);
        
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        
        // Log successful login
        logActivity($user['id'], 'login', 'Successful login from ' . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown IP'), $pdo);

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
            logActivity($user['id'], 'account_locked', 'Account locked after 5 failed login attempts', $pdo);
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
        
        // Log failed login attempt
        logActivity($user['id'], 'failed_login', 'Failed login attempt with wrong PIN', $pdo);
    }
} else {
    // Email not found
    $message = "Wrong username or password.";
}
