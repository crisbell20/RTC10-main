<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../api/config/connection-pdo.php';

echo "=== COURSE MODULES MIGRATION ===\n\n";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tbl_module` (
        `Module_ID` int(11) NOT NULL AUTO_INCREMENT,
        `Course_ID` int(11) NOT NULL,
        `Module_Name` varchar(200) NOT NULL,
        `Module_Code` varchar(50) DEFAULT NULL,
        `Description` text DEFAULT NULL,
        `Sort_Order` int(11) NOT NULL DEFAULT 0,
        `Date_Created` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`Module_ID`),
        KEY `idx_module_course` (`Course_ID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "OK: tbl_module table\n";

    try {
        $pdo->exec('ALTER TABLE `tbl_subject` ADD COLUMN `Module_ID` int(11) DEFAULT NULL AFTER `Course_ID`');
        echo "OK: tbl_subject.Module_ID column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "SKIP: Module_ID column already exists\n";
        } else {
            throw $e;
        }
    }

    $courses = $pdo->query('SELECT Course_ID, Course_Name FROM tbl_course ORDER BY Course_ID')->fetchAll(PDO::FETCH_ASSOC);
    $moduleMap = [];

    foreach ($courses as $course) {
        $courseId = (int)$course['Course_ID'];
        $stmt = $pdo->prepare('SELECT Module_ID FROM tbl_module WHERE Course_ID = ? ORDER BY Sort_Order, Module_ID LIMIT 1');
        $stmt->execute([$courseId]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $moduleMap[$courseId] = (int)$existingId;
            echo "SKIP: default module exists for course {$courseId}\n";
            continue;
        }

        $ins = $pdo->prepare('INSERT INTO tbl_module (Course_ID, Module_Name, Sort_Order) VALUES (?, ?, 1)');
        $ins->execute([$courseId, 'Module 1']);
        $moduleMap[$courseId] = (int)$pdo->lastInsertId();
        echo "OK: created Module 1 for course {$courseId} ({$course['Course_Name']})\n";
    }

    $subjects = $pdo->query('SELECT Subject_ID, Course_ID, Module_ID FROM tbl_subject')->fetchAll(PDO::FETCH_ASSOC);
    $updated = 0;
    foreach ($subjects as $subject) {
        if (!empty($subject['Module_ID'])) {
            continue;
        }
        $courseId = (int)$subject['Course_ID'];
        $moduleId = $moduleMap[$courseId] ?? null;
        if (!$moduleId) {
            echo "WARN: no module for subject {$subject['Subject_ID']} (course {$courseId})\n";
            continue;
        }
        $upd = $pdo->prepare('UPDATE tbl_subject SET Module_ID = ? WHERE Subject_ID = ?');
        $upd->execute([$moduleId, $subject['Subject_ID']]);
        $updated++;
    }
    echo "OK: backfilled Module_ID on {$updated} subject(s)\n";

    try {
        $pdo->exec('ALTER TABLE `tbl_subject` MODIFY `Module_ID` int(11) NOT NULL');
        echo "OK: Module_ID set NOT NULL\n";
    } catch (PDOException $e) {
        echo "WARN: could not set Module_ID NOT NULL (subjects may still be missing Module_ID)\n";
    }

    try {
        $pdo->exec('ALTER TABLE `tbl_subject` DROP INDEX `uq_subject_code`');
        echo "OK: dropped global uq_subject_code\n";
    } catch (PDOException $e) {
        echo "SKIP: uq_subject_code drop — " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec('ALTER TABLE `tbl_subject` ADD UNIQUE KEY `uq_subject_code_module` (`Module_ID`, `Subject_Code`)');
        echo "OK: added uq_subject_code_module\n";
    } catch (PDOException $e) {
        echo "SKIP: uq_subject_code_module — " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec('ALTER TABLE `tbl_subject`
            ADD CONSTRAINT `fk_subject_module` FOREIGN KEY (`Module_ID`)
            REFERENCES `tbl_module` (`Module_ID`) ON DELETE RESTRICT ON UPDATE CASCADE');
        echo "OK: fk_subject_module\n";
    } catch (PDOException $e) {
        echo "SKIP: fk_subject_module — " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec('ALTER TABLE `tbl_module`
            ADD CONSTRAINT `fk_module_course` FOREIGN KEY (`Course_ID`)
            REFERENCES `tbl_course` (`Course_ID`) ON DELETE CASCADE ON UPDATE CASCADE');
        echo "OK: fk_module_course\n";
    } catch (PDOException $e) {
        echo "SKIP: fk_module_course — " . $e->getMessage() . "\n";
    }

    echo "\nMigration completed.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
