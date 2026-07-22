<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../api/config/connection-pdo.php';
require_once __DIR__ . '/../../api/includes/exam-participant-utils.php';

echo "=== EXAM PARTICIPANTS MIGRATION ===\n\n";

try {
    $sql = file_get_contents(__DIR__ . '/add_exam_participants.sql');
    if ($sql === false) {
        throw new RuntimeException('Could not read add_exam_participants.sql');
    }

    $pdo->exec($sql);
    echo "OK: tbl_exam_participant table\n";

    $exams = $pdo->query('SELECT Exam_ID FROM tbl_exam ORDER BY Exam_ID')->fetchAll(PDO::FETCH_COLUMN);
    $synced = 0;
    foreach ($exams as $examId) {
        $batchCount = $pdo->prepare('SELECT COUNT(*) FROM tbl_exam_batch WHERE Exam_ID = ?');
        $batchCount->execute([(int)$examId]);
        if ((int)$batchCount->fetchColumn() === 0) {
            continue;
        }

        $result = syncExamParticipantsFromSections($pdo, (int)$examId);
        if ($result['total'] > 0) {
            $synced++;
            echo "OK: exam {$examId} — {$result['total']} participant(s)\n";
        }
    }

    echo "\nDone. Backfilled participants for {$synced} exam(s).\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
