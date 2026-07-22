<?php
/**
 * Enforce unique Academic_Number for examinee login.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$root = dirname(__DIR__, 2);
require_once $root . '/api/config/connection-pdo.php';
require_once $root . '/api/includes/user-identity-utils.php';

echo "=== Academic Number Unique Migration ===\n\n";

try {
    if (academicNumberUniqueIndexExists($pdo)) {
        echo "Index uq_user_academic_number already exists. Nothing to do.\n";
        exit(0);
    }

    $dupStmt = $pdo->query("
        SELECT Academic_Number, COUNT(*) AS cnt
        FROM tbl_user
        WHERE Academic_Number IS NOT NULL AND TRIM(Academic_Number) != ''
        GROUP BY Academic_Number
        HAVING cnt > 1
    ");
    $duplicates = $dupStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($duplicates)) {
        echo "ERROR: Duplicate academic numbers found. Resolve these before running migration:\n";
        foreach ($duplicates as $row) {
            echo "  - {$row['Academic_Number']} ({$row['cnt']} rows)\n";
        }
        exit(1);
    }

    $pdo->exec("
        UPDATE tbl_user
        SET Academic_Number = UPPER(TRIM(Academic_Number))
        WHERE Academic_Number IS NOT NULL AND TRIM(Academic_Number) != ''
    ");
    echo "Normalized academic numbers to uppercase.\n";

    $pdo->exec("
        UPDATE tbl_user u
        INNER JOIN tbl_role r ON u.Role_ID = r.Role_ID
        SET u.Email = CONCAT(LOWER(u.Academic_Number), '@rtc.examinee.local')
        WHERE r.Role_Name = 'Examinee'
          AND u.Academic_Number IS NOT NULL
          AND TRIM(u.Academic_Number) != ''
          AND (u.Email IS NULL OR TRIM(u.Email) = '')
    ");
    echo "Assigned placeholder emails for examinees missing email.\n";

    $pdo->exec('ALTER TABLE `tbl_user` ADD UNIQUE INDEX `uq_user_academic_number` (`Academic_Number`)');
    echo "Added unique index uq_user_academic_number.\n";
    echo "\nMigration completed successfully.\n";
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
