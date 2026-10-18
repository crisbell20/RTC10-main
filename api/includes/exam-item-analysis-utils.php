<?php

require_once __DIR__ . '/exam-session-utils.php';

/**
 * Interpret difficulty index (p-value).
 */
function interpretDifficultyIndex(float $index): string
{
    if ($index >= 0.90) {
        return 'Very Easy';
    }
    if ($index >= 0.70) {
        return 'Easy';
    }
    if ($index >= 0.30) {
        return 'Moderate';
    }
    if ($index >= 0.10) {
        return 'Difficult';
    }

    return 'Very Difficult';
}

/**
 * Interpret discrimination index.
 */
function interpretDiscriminationIndex(float $index): string
{
    if ($index < 0) {
        return 'Review Question';
    }
    if ($index >= 0.40) {
        return 'Excellent';
    }
    if ($index >= 0.30) {
        return 'Good';
    }
    if ($index >= 0.20) {
        return 'Fair';
    }

    return 'Poor';
}

/**
 * Resolve upper/lower group size using the 27% rule.
 */
function resolveDiscriminationGroupSize(int $totalExaminees): int
{
    if ($totalExaminees <= 0) {
        return 0;
    }

    $groupSize = max(1, (int) round($totalExaminees * 0.27));

    if ($totalExaminees >= 4) {
        $groupSize = max(2, $groupSize);
        $groupSize = min($groupSize, (int) floor($totalExaminees / 2));
    }

    return $groupSize;
}

/**
 * Rank finished sessions for discrimination grouping (highest score first).
 */
function sortSessionsForDiscrimination(array &$sessions): void
{
    usort($sessions, static function (array $a, array $b): int {
        if ($a['score'] !== $b['score']) {
            return $b['score'] <=> $a['score'];
        }

        if ($a['grade_percent'] !== $b['grade_percent']) {
            return $b['grade_percent'] <=> $a['grade_percent'];
        }

        $timeA = (string)($a['time_ended'] ?? '');
        $timeB = (string)($b['time_ended'] ?? '');
        if ($timeA !== $timeB) {
            return strcmp($timeA, $timeB);
        }

        return $a['session_id'] <=> $b['session_id'];
    });
}

/**
 * @return array{upper: array<int, true>, lower: array<int, true>}
 */
function buildDiscriminationGroups(array $sessions, int $groupSize): array
{
    $upperSessionIds = [];
    $lowerSessionIds = [];

    foreach (array_slice($sessions, 0, $groupSize) as $session) {
        $upperSessionIds[(int)$session['session_id']] = true;
    }

    foreach (array_slice($sessions, -$groupSize) as $session) {
        $lowerSessionIds[(int)$session['session_id']] = true;
    }

    return [
        'upper' => $upperSessionIds,
        'lower' => $lowerSessionIds,
    ];
}

function finishedAttemptIsNewer(array $candidate, array $current): bool
{
    $candidateKey = (string)($candidate['submission_date'] ?? $candidate['time_ended'] ?? '');
    $currentKey = (string)($current['submission_date'] ?? $current['time_ended'] ?? '');
    if ($candidateKey !== $currentKey) {
        return $candidateKey > $currentKey;
    }

    $candidateId = (int)($candidate['result_id'] ?? $candidate['session_id'] ?? 0);
    $currentId = (int)($current['result_id'] ?? $current['session_id'] ?? 0);

    return $candidateId > $currentId;
}

/**
 * Build item analysis report for a completed exam.
 * Each trainee contributes only their latest completed sitting.
 *
 * @return array{
 *   total_examinees:int,
 *   assigned_count:int,
 *   not_submitted_count:int,
 *   all_completed:bool,
 *   group_size:int,
 *   items:array<int, array>
 * }
 */
function buildExamItemAnalysis(PDO $pdo, int $examId, int $assignedCount = 0): array
{
    $stmt = $pdo->prepare('
        SELECT
            eq.Question_ID,
            qb.Question_Text
        FROM tbl_exam_question eq
        INNER JOIN tbl_question_bank qb ON eq.Question_ID = qb.Question_ID
        WHERE eq.Exam_ID = ?
        ORDER BY eq.Question_ID
    ');
    $stmt->execute([$examId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalItems = count($questions);

    $sessionStmt = $pdo->prepare('
        SELECT es.Session_ID, es.User_ID, es.Time_Ended, r.Submission_Date, r.Result_ID
        FROM tbl_exam_session es
        INNER JOIN tbl_result r ON r.Session_ID = es.Session_ID
        WHERE es.Exam_ID = ?
          AND es.Time_Ended IS NOT NULL
    ');
    $sessionStmt->execute([$examId]);
    $sessionRows = $sessionStmt->fetchAll(PDO::FETCH_ASSOC);

    $sessions = [];
    foreach ($sessionRows as $row) {
        $sessionId = (int)$row['Session_ID'];
        if (isVoidCompletedSession($pdo, $sessionId)) {
            continue;
        }

        $live = resolveLiveExamSessionScore($pdo, $sessionId, $examId, $totalItems);
        $sessions[] = [
            'session_id' => $sessionId,
            'user_id' => (int)$row['User_ID'],
            'score' => (int)$live['score'],
            'grade_percent' => $totalItems > 0 ? round(($live['score'] / $totalItems) * 100, 4) : 0.0,
            'time_ended' => $row['Time_Ended'] ?? null,
            'submission_date' => $row['Submission_Date'] ?? null,
            'result_id' => (int)($row['Result_ID'] ?? 0),
        ];
    }

    $latestByUser = [];
    foreach ($sessions as $session) {
        $sessionUserId = (int)$session['user_id'];
        $current = $latestByUser[$sessionUserId] ?? null;
        if ($current === null || finishedAttemptIsNewer($session, $current)) {
            $latestByUser[$sessionUserId] = $session;
        }
    }
    $sessions = array_values($latestByUser);

    $totalExaminees = count($sessions);
    $notSubmittedCount = max(0, $assignedCount - $totalExaminees);

    if ($totalExaminees === 0 || empty($questions)) {
        return [
            'total_examinees' => 0,
            'assigned_count' => $assignedCount,
            'not_submitted_count' => $notSubmittedCount,
            'all_completed' => $assignedCount > 0 && $notSubmittedCount === 0,
            'group_size' => 0,
            'items' => [],
        ];
    }

    sortSessionsForDiscrimination($sessions);

    $groupSize = resolveDiscriminationGroupSize($totalExaminees);
    $discriminationGroups = buildDiscriminationGroups($sessions, $groupSize);
    $upperSessionIds = $discriminationGroups['upper'];
    $lowerSessionIds = $discriminationGroups['lower'];

    $submittedSql = examAnswerIsSubmittedSql('a');
    $answerStmt = $pdo->prepare("
        SELECT
            a.Session_ID,
            a.Question_ID,
            a.Choice_ID,
            a.Is_Correct
        FROM tbl_answer a
        INNER JOIN tbl_exam_question eq ON eq.Question_ID = a.Question_ID AND eq.Exam_ID = ?
        INNER JOIN tbl_exam_session es ON es.Session_ID = a.Session_ID AND es.Exam_ID = ?
        WHERE es.Time_Ended IS NOT NULL
          AND {$submittedSql}
    ");
    $answerStmt->execute([$examId, $examId]);

    $answersBySessionQuestion = [];
    foreach ($answerStmt->fetchAll(PDO::FETCH_ASSOC) as $answerRow) {
        $sessionId = (int)$answerRow['Session_ID'];
        $questionId = (int)$answerRow['Question_ID'];
        $answersBySessionQuestion[$sessionId][$questionId] = [
            'choice_id' => $answerRow['Choice_ID'] !== null ? (int)$answerRow['Choice_ID'] : null,
            'is_correct' => (int)($answerRow['Is_Correct'] ?? 0) === 1,
        ];
    }

    $items = [];
    $questionNumber = 0;

    foreach ($questions as $question) {
        $questionNumber++;
        $questionId = (int)$question['Question_ID'];

        $choiceStmt = $pdo->prepare('
            SELECT Choice_ID, Choice_Text, Is_Correct
            FROM tbl_choice
            WHERE Question_ID = ?
            ORDER BY Choice_ID
        ');
        $choiceStmt->execute([$questionId]);
        $choices = $choiceStmt->fetchAll(PDO::FETCH_ASSOC);

        $choiceLabels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $correctAnswerText = '';
        $correctAnswerLabel = '';

        $distractorMap = [];
        foreach ($choices as $index => $choice) {
            $label = $choiceLabels[$index] ?? chr(65 + $index);
            $choiceId = (int)$choice['Choice_ID'];
            $isCorrect = (int)($choice['Is_Correct'] ?? 0) === 1;

            if ($isCorrect) {
                $correctAnswerText = (string)$choice['Choice_Text'];
                $correctAnswerLabel = $label;
            }

            $distractorMap[$choiceId] = [
                'choice_id' => $choiceId,
                'choice_label' => $label,
                'choice_text' => (string)$choice['Choice_Text'],
                'is_correct' => $isCorrect,
                'count' => 0,
            ];
        }

        $numberCorrect = 0;
        $upperCorrect = 0;
        $lowerCorrect = 0;

        foreach ($sessions as $session) {
            $sessionId = (int)$session['session_id'];
            $answer = $answersBySessionQuestion[$sessionId][$questionId] ?? null;
            $isCorrect = $answer !== null && !empty($answer['is_correct']);

            if ($isCorrect) {
                $numberCorrect++;
                if (isset($upperSessionIds[$sessionId])) {
                    $upperCorrect++;
                }
                if (isset($lowerSessionIds[$sessionId])) {
                    $lowerCorrect++;
                }
            }

            if ($answer !== null && !empty($answer['choice_id']) && isset($distractorMap[$answer['choice_id']])) {
                $distractorMap[$answer['choice_id']]['count']++;
            }
        }

        $numberWrong = $totalExaminees - $numberCorrect;
        $difficultyIndex = round($numberCorrect / $totalExaminees, 4);
        $discriminationIndex = round(($upperCorrect - $lowerCorrect) / $groupSize, 4);

        $distractors = [];
        foreach ($distractorMap as $distractor) {
            $count = (int)$distractor['count'];
            $percentage = round(($count / $totalExaminees) * 100, 1);
            $distractors[] = [
                'choice_id' => $distractor['choice_id'],
                'choice_label' => $distractor['choice_label'],
                'choice_text' => $distractor['choice_text'],
                'count' => $count,
                'percentage' => $percentage,
                'is_correct' => $distractor['is_correct'],
                'non_functioning' => !$distractor['is_correct'] && $count === 0,
            ];
        }

        $items[] = [
            'question_number' => $questionNumber,
            'question_id' => $questionId,
            'question_text' => (string)$question['Question_Text'],
            'correct_answer' => $correctAnswerText,
            'correct_answer_label' => $correctAnswerLabel,
            'number_correct' => $numberCorrect,
            'number_wrong' => $numberWrong,
            'difficulty_index' => $difficultyIndex,
            'difficulty_interpretation' => interpretDifficultyIndex($difficultyIndex),
            'discrimination_index' => $discriminationIndex,
            'discrimination_interpretation' => interpretDiscriminationIndex($discriminationIndex),
            'distractors' => $distractors,
        ];
    }

    return [
        'total_examinees' => $totalExaminees,
        'assigned_count' => $assignedCount,
        'not_submitted_count' => $notSubmittedCount,
        'all_completed' => $assignedCount > 0 && $notSubmittedCount === 0,
        'group_size' => $groupSize,
        'items' => $items,
    ];
}
