<?php
// Helper functions for validation and security

// Clean and sanitize input data
if (!function_exists('cleanInput')) {
function cleanInput($data) {
    $data = trim($data); // remove extra spaces
    $data = stripslashes($data); // remove backslashes
    $data = htmlspecialchars($data); // convert special characters
    return $data;
}
}

// Validate email format
if (!function_exists('validateEmail')) {
function validateEmail($email) {
    $email = cleanInput($email);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return true;
    }
    return false;
}
}

// Validate PIN format (4-6 digits only)
if (!function_exists('validatePin')) {
function validatePin($pin) {
    $pin = cleanInput($pin);
    // Check if PIN is 4-6 digits only
    if (preg_match('/^[0-9]{4,6}$/', $pin)) {
        return true;
    }
    return false;
}
}

// Validate name (letters and spaces only, 2-50 characters)
if (!function_exists('validateName')) {
function validateName($name) {
    $name = cleanInput($name);
    if (strlen($name) >= 2 && strlen($name) <= 50 && preg_match('/^[a-zA-Z\s]+$/', $name)) {
        return true;
    }
    return false;
}
}

// Check if email already exists in database
if (!function_exists('emailExists')) {
function emailExists($email, $pdo) {
    $sql = "SELECT id FROM users WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    
    if ($stmt->fetch()) {
        return true; // email exists
    }
    return false; // email doesn't exist
}
}

// Generate error messages for validation
if (!function_exists('getValidationErrors')) {
function getValidationErrors($name, $email, $pin) {
    $errors = array();
    
    if (!validateName($name)) {
        $errors[] = "Name must be 2-50 characters and contain only letters and spaces.";
    }
    
    if (!validateEmail($email)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (!validatePin($pin)) {
        $errors[] = "PIN must be 4-6 digits only.";
    }
    
    return $errors;
}
}
?>