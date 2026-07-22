<?php
/**
 * Renumber all examinees to A001-style academic numbers (one letter + 3 digits).
 *
 * Usage: php scripts/migrations/run_academic_number_format_migration.php
 */

require_once __DIR__ . '/../../api/config/connection-pdo.php';
require_once __DIR__ . '/../../api/includes/user-identity-utils.php';

echo "=== Academic Number Format Migration ===\n\n";

try {
    $stmt = $pdo->query("
        SELECT u.User_ID, u.Fullname, u.Email, u.Academic_Number
        FROM tbl_user u
        INNER JOIN tbl_role r ON u.Role_ID = r.Role_ID
        WHERE r.Role_Name = 'Examinee'
        ORDER BY u.User_ID ASC
    ");
    $examinees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($examinees)) {
        echo "No examinee accounts found. Nothing to do.\n";
        exit(0);
    }

    $pdo->beginTransaction();

    $sequence = 0;
    $mappings = [];

    foreach ($examinees as $examinee) {
        $sequence++;
        $letterIndex = intdiv($sequence - 1, 999);
        if ($letterIndex > 25) {
            throw new RuntimeException('Too many examinees for A001-Z999 numbering range');
        }

        $letter = chr(ord('A') + $letterIndex);
        $num = (($sequence - 1) % 999) + 1;
        $newNumber = formatAcademicNumber($letter, $num);
        $oldNumber = $examinee['Academic_Number'] ?? '';
        $userId = (int)$examinee['User_ID'];

        $update = $pdo->prepare('UPDATE tbl_user SET Academic_Number = ? WHERE User_ID = ?');
        $update->execute([$newNumber, $userId]);

        if (isExamineePlaceholderEmail($examinee['Email'] ?? null)) {
            $emailUpdate = $pdo->prepare('UPDATE tbl_user SET Email = ? WHERE User_ID = ?');
            $emailUpdate->execute([examineePlaceholderEmail($newNumber), $userId]);
        }

        $mappings[] = [
            'user_id' => $userId,
            'fullname' => $examinee['Fullname'],
            'old' => $oldNumber,
            'new' => $newNumber,
        ];
    }

    $pdo->commit();

    echo 'Renumbered ' . count($mappings) . " examinee(s):\n\n";
    foreach ($mappings as $map) {
        echo sprintf(
            "  User #%d %s: %s -> %s\n",
            $map['user_id'],
            $map['fullname'],
            $map['old'] !== '' ? $map['old'] : '(none)',
            $map['new']
        );
    }

    echo "\nMigration complete.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
