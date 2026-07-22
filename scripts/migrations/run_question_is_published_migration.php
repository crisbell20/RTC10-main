<?php
/**
 * Run the Is_Published migration for exam draft questions.
 */

require_once __DIR__ . '/../../api/config/connection-pdo.php';

try {
    echo "Adding Is_Published column to tbl_question_bank...\n";
    $pdo->exec("ALTER TABLE `tbl_question_bank`
        ADD COLUMN `Is_Published` tinyint(1) NOT NULL DEFAULT 1
        COMMENT '0 = exam draft only, promoted to bank on exam publish'
        AFTER `Question_Type`");
    echo "Column added successfully.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column Is_Published already exists. Migration skipped.\n";
    } else {
        echo 'Error: ' . $e->getMessage() . "\n";
        exit(1);
    }
}
