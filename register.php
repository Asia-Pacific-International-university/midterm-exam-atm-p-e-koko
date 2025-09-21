<?php
include "includes/db.php";

$message = ""; // store success message

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name  = $_POST['name'];
    $email = $_POST['email'];

    $sql = "INSERT INTO users (name, email, pin, balance) 
            VALUES (:name, :email, :pin, :balance)";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([
        ':name'    => $name,
        ':email'   => $email,
        ':pin'     => password_hash("1234", PASSWORD_DEFAULT),
        ':balance' => 0.00
    ])) {
        $message = "✅ Registration saved!";
    } else {
        $message = "❌ Registration failed!";
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
        
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" required>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>

        <button type="submit">Register</button>
    </form>
</body>
</html>
