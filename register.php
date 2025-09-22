<?php
session_start(); // start session for error handling
include "includes/db.php";
include "includes/helpers.php";

$message = ""; // store messages
$errors = array(); // store validation errors

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <title>Registration Form</title>
</head>
<body>
    <form action="register.php" method="POST">
        <h2>Register</h2>
        <?php if ($message != ""): ?>
            <p style="color: green; text-align:center;"><?php echo $message; ?></p>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div style="color: red; text-align:center; margin-bottom: 15px;">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" required>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>

        <label for="pin">PIN:</label>
        <input type="password" id="pin" name="pin" required minlength="4" maxlength="6" pattern="\d{4,6}" 
               title="PIN must be 4–6 digits">

        <button type="submit">Register</button>
        
        <p style="text-align: center; margin-top: 15px;">
            Already have an account? <a href="login.php">Login here</a>
        </p>
    </form>
</body>
</html>
