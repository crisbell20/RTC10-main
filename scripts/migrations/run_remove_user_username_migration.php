<?php
/**
 * Drop Username column from tbl_user.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$root = dirname(__DIR__, 2);
require_once $root . '/api/config/connection-pdo.php';

echo "=== Remove User Username Migration ===\n\n";

function userUsernameColumnExists(PDO $pdo): bool
{
    $stmt = $pdo->query("SHOW COLUMNS FROM tbl_user LIKE 'Username'");
    return $stmt && $stmt->rowCount() > 0;
}

try {
    if (!userUsernameColumnExists($pdo)) {
        echo "Column Username already removed. Nothing to do.\n";
        exit(0);
    }

    $indexStmt = $pdo->query("SHOW INDEX FROM tbl_user WHERE Key_name = 'uq_user_username'");
    if ($indexStmt && $indexStmt->rowCount() > 0) {
        $pdo->exec('ALTER TABLE `tbl_user` DROP INDEX `uq_user_username`');
        echo "Dropped index uq_user_username.\n";
    }

    $pdo->exec('ALTER TABLE `tbl_user` DROP COLUMN `Username`');
    echo "Dropped column Username.\n";
    echo "\nMigration completed successfully.\n";
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
