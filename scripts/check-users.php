<?php
/**
 * Check and create default users
 */

require_once __DIR__ . '/../api/config/connection-pdo.php';

echo "Checking users in database...\n\n";

// Check if users exist
$stmt = $pdo->query("SELECT User_ID, Email, Fullname FROM tbl_user");
$users = $stmt->fetchAll();

if (count($users) == 0) {
    echo "No users found! Creating default users...\n\n";
    
    // Create default password hash for "password123"
    $passwordHash = password_hash('password123', PASSWORD_DEFAULT);
    
    // Insert default users
    $sql = "INSERT INTO `tbl_user` (`User_ID`, `Role_ID`, `Fullname`, `Email`, `Password_Hash`, `Status`) VALUES
    (1, 1, 'Administrator', 'admin@rtc.com', ?, 'Active'),
    (2, 2, 'CCMD Officer', 'ccmd@rtc.com', ?, 'Active'),
    (3, 3, 'Examinee User', 'examinee@rtc.com', ?, 'Active')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$passwordHash, $passwordHash, $passwordHash]);
    
    echo "✓ Default users created!\n\n";
} else {
    echo "Found " . count($users) . " user(s):\n";
    foreach ($users as $user) {
        echo "  - {$user['Email']} ({$user['Fullname']})\n";
    }
    echo "\n";
}

// Test password verification
echo "Testing password hash...\n";
$stmt = $pdo->prepare("SELECT Password_Hash FROM tbl_user WHERE Email = 'admin@rtc.com'");
$stmt->execute();
$admin = $stmt->fetch();

if ($admin) {
    $testPassword = 'password123';
    $isValid = password_verify($testPassword, $admin['Password_Hash']);
    
    if ($isValid) {
        echo "✓ Password 'password123' is VALID for admin@rtc.com\n";
    } else {
        echo "✗ Password verification FAILED. Updating password...\n";
        
        // Update all users with new password hash
        $newHash = password_hash('password123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE tbl_user SET Password_Hash = ?");
        $stmt->execute([$newHash]);
        
        echo "✓ All user passwords updated to 'password123'\n";
    }
} else {
    echo "✗ Admin user not found!\n";
}

echo "\n=== Login Credentials ===\n";
echo "Admin:    admin@rtc.com / password123\n";
echo "CCMD:     ccmd@rtc.com / password123\n";
echo "Examinee: examinee@rtc.com / password123\n";
