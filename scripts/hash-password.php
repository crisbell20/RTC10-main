<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line');
}

// Get email and password from command line arguments
if ($argc !== 3) {
    die("Usage: php hash_password.php <email> <new_password>\n");
}

$email = $argv[1];
$password = $argv[2];

// Include database connection
require_once __DIR__ . '/../api/config/connection-pdo.php';

try {
    // Hash the password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Update the password in the database
    $sql = "UPDATE tbl_user SET Password_Hash = :password_hash WHERE Email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':password_hash', $passwordHash);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo "Password successfully updated for user with email: $email\n";
    } else {
        echo "No user found with email: $email\n";
    }
} catch (PDOException $e) {
    echo "Error updating password: " . $e->getMessage() . "\n";
}