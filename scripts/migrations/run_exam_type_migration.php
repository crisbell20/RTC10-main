<?php
/**
 * Add Exam_Type to tbl_exam and backfill from existing Title values.
 */

require_once __DIR__ . '/../../api/config/connection-pdo.php';
require_once __DIR__ . '/../../api/includes/exam-type-utils.php';

try {
    echo "Adding Exam_Type column to tbl_exam...\n";
    $pdo->exec("ALTER TABLE `tbl_exam`
        ADD COLUMN `Exam_Type` ENUM('PRE_TEST','MODULAR','COMPREHENSIVE','POST_TEST') NOT NULL DEFAULT 'MODULAR'
        AFTER `Title`");
    echo "Column added.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') === false) {
        echo 'Error: ' . $e->getMessage() . "\n";
        exit(1);
    }
    echo "Exam_Type column already exists.\n";
}

if (!examTypeColumnExists($pdo)) {
    echo "Exam_Type column is not available after migration.\n";
    exit(1);
}

$rows = $pdo->query('SELECT Exam_ID, Title, Exam_Type FROM tbl_exam')->fetchAll(PDO::FETCH_ASSOC);
$update = $pdo->prepare('UPDATE tbl_exam SET Exam_Type = ? WHERE Exam_ID = ?');
$updated = 0;

foreach ($rows as $row) {
    $inferred = inferExamTypeFromTitle($row['Title'] ?? '');
    if ($inferred !== ($row['Exam_Type'] ?? 'MODULAR')) {
        $update->execute([$inferred, $row['Exam_ID']]);
        $updated++;
    }
}

echo "Backfilled Exam_Type on {$updated} exam(s).\n";
echo "Migration complete.\n";
