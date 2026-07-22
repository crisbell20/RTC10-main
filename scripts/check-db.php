<?php
require_once __DIR__ . '/../api/config/connection-pdo.php';

echo "=== DATABASE CHECK ===\n\n";

// Check users
echo "Users in database:\n";
$stmt = $pdo->query("SELECT User_ID, Email, Fullname, Role_ID, Status, LENGTH(Password_Hash) as hash_len FROM tbl_user");
$users = $stmt->fetchAll();

foreach ($users as $user) {
    echo "  ID: {$user['User_ID']}\n";
    echo "  Email: {$user['Email']}\n";
    echo "  Fullname: {$user['Fullname']}\n";
    echo "  Role_ID: {$user['Role_ID']}\n";
    echo "  Status: {$user['Status']}\n";
    echo "  Password Hash Length: {$user['hash_len']}\n";
    echo "  ---\n";
}

// Check roles
echo "\nRoles in database:\n";
$stmt = $pdo->query("SELECT * FROM tbl_role");
$roles = $stmt->fetchAll();

foreach ($roles as $role) {
    echo "  ID: {$role['Role_ID']} - {$role['Role_Name']}\n";
}

// Test login
echo "\n=== TESTING LOGIN ===\n";
$email = 'admin@rtc.com';
$password = 'password123';

$stmt = $pdo->prepare("SELECT * FROM tbl_user WHERE Email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    echo "User found: {$user['Email']}\n";
    echo "Password hash: " . substr($user['Password_Hash'], 0, 30) . "...\n";
    
    $verify = password_verify($password, $user['Password_Hash']);
    echo "Password verify: " . ($verify ? "SUCCESS ✓" : "FAILED ✗") . "\n";
    
    if (!$verify) {
        echo "\nFixing password...\n";
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE tbl_user SET Password_Hash = ? WHERE Email = ?");
        $stmt->execute([$newHash, $email]);
        echo "Password updated!\n";
    }
} else {
    echo "User NOT found!\n";
}
