<?php
include "db.php"; // connect to database
include "helpers.php"; // include helper functions

$email = cleanInput($_POST['email']);
$pin   = cleanInput($_POST['pin']);

// Fetch user by email
$sql  = "SELECT id, name, email, pin FROM users WHERE email = :email LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($pin, $user['pin'])) {
    // Login success → store user in session
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];

    header("Location: dashboard.php");
    exit; // stop execution after redirect
} else {
    //Wrong email or PIN
    $message = "Wrong username or password.";
    // return control back to login.php without redirect
}
