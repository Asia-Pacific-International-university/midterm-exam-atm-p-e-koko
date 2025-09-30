<?php
session_start(); // start session for error handling
include "includes/db.php";
include "includes/helpers.php";

$message = ""; // store messages
$errors = array(); // store validation errors

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!validateCSRFToken()) {
        $errors[] = "Security token validation failed. Please try again.";
    } else {
        // Get and clean input data
        $name  = cleanInput($_POST['name']);
        $email = cleanInput($_POST['email']);
        $pin   = cleanInput($_POST['pin']);
        
        // Validate all inputs
        $errors = getValidationErrors($name, $email, $pin);
    
    // Check if email already exists
    if (empty($errors) && emailExists($email, $pdo)) {
        $errors[] = "Email address is already registered.";
    }
    
    // If no validation errors, proceed with registration
    if (empty($errors)) {
        $sql = "INSERT INTO users (name, email, pin, balance) 
                VALUES (:name, :email, :pin, :balance)";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([
            ':name'    => $name,
            ':email'   => $email,
            ':pin'     => password_hash($pin, PASSWORD_DEFAULT),
            ':balance' => 0.00
        ])) {
            $message = "✅ Registration successful! You can now login.";
        } else {
            $errors[] = "❌ Registration failed. Please try again.";
        }
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
    <title>Register - My ATM</title>
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow border-0">
                    <div class="card-body p-4">
                        <!-- Logo/Brand -->
                        <div class="text-center mb-4">
                            <h2 class="h3 fw-bold text-primary">
                                <i class="fas fa-university"></i> My ATM
                            </h2>
                            <p class="text-muted">Create your account</p>
                        </div>

                        <!-- Success Message -->
                        <?php if ($message != ""): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Error Messages -->
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Registration Form -->
                        <form action="register.php" method="POST">
                            <?php echo getCSRFTokenField(); ?>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label fw-semibold">
                                    <i class="fas fa-user text-primary"></i> Full Name
                                </label>
                                <input type="text" 
                                       id="name" 
                                       name="name" 
                                       class="form-control form-control-lg" 
                                       placeholder="Enter your full name"
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                       required>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> Use your real name for account verification
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label fw-semibold">
                                    <i class="fas fa-envelope text-primary"></i> Email Address
                                </label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       class="form-control form-control-lg" 
                                       placeholder="Enter your email address"
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                       required>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> We'll use this for account recovery and notifications
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="pin" class="form-label fw-semibold">
                                    <i class="fas fa-key text-primary"></i> PIN
                                </label>
                                <input type="password" 
                                       id="pin" 
                                       name="pin" 
                                       class="form-control form-control-lg" 
                                       placeholder="Create a 4-6 digit PIN"
                                       minlength="4" 
                                       maxlength="6" 
                                       pattern="\d{4,6}"
                                       title="PIN must be 4-6 digits"
                                       required>
                                <div class="form-text">
                                    <i class="fas fa-shield-alt"></i> Choose a secure 4-6 digit PIN for transactions
                                </div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-success btn-lg fw-semibold">
                                    <i class="fas fa-user-plus"></i> Create Account
                                </button>
                            </div>
                        </form>

                        <!-- Login Link -->
                        <div class="text-center">
                            <p class="mb-0 text-muted">
                                Already have an account? 
                                <a href="login.php" class="text-decoration-none fw-semibold">
                                    Sign In
                                </a>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Security Notice -->
                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="fas fa-lock"></i> 
                        Your information is encrypted and secure
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
