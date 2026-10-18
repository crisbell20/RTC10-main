<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';
require_once __DIR__ . '/../includes/exam-participant-utils.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? ($input['action'] ?? '');
$userRole = $_SESSION['user_role'] ?? '';

function requireExamManagerRoleForParticipants(): void {
    global $userRole;
    if ($userRole !== 'Admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden: Admin access required']);
        exit;
    }
}

try {
    if (!examParticipantTableExists($pdo)) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'Participant table is not available. Import scripts/migrations/add_exam_participants.sql on the database first.',
        ]);
        exit;
    }

    if ($request_method === 'GET' && $action === 'list') {
        $exam_id = (int)($_GET['exam_id'] ?? 0);
        if ($exam_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        $examStmt = $pdo->prepare('SELECT Exam_ID, Title FROM tbl_exam WHERE Exam_ID = ?');
        $examStmt->execute([$exam_id]);
        $exam = $examStmt->fetch(PDO::FETCH_ASSOC);
        if (!$exam) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Exam not found']);
            exit;
        }

        repairExamBatchAssignmentsFromParticipants($pdo, $exam_id);

        $eligible = getEligibleExamParticipants($pdo, $exam_id);
        $selectedIds = getExamParticipantIds($pdo, $exam_id);

        if (empty($selectedIds) && !empty($eligible)) {
            syncExamParticipantsFromSections($pdo, $exam_id);
            $selectedIds = getExamParticipantIds($pdo, $exam_id);
        } else {
            $eligible = mergeEligibleWithSavedParticipants($pdo, $eligible, $selectedIds);
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'exam_id' => $exam_id,
                'exam_title' => $exam['Title'] ?? '',
                'eligible_count' => count($eligible),
                'selected_count' => count($selectedIds),
                'selected_ids' => $selectedIds,
                'groups' => groupEligibleParticipantsBySection($eligible, $selectedIds),
            ],
        ]);
        exit;
    }

    if ($request_method === 'GET' && $action === 'list_by_sections') {
        requireExamManagerRoleForParticipants();
        $raw = $_GET['section_ids'] ?? '';
        $sectionIds = is_array($raw) ? $raw : explode(',', (string)$raw);
        $sectionIds = array_values(array_unique(array_filter(array_map('intval', $sectionIds), static function ($id) {
            return $id > 0;
        })));

        if (empty($sectionIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Assign at least one section before selecting participants']);
            exit;
        }

        $eligible = getEligibleParticipantsBySectionIds($pdo, $sectionIds);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'eligible_count' => count($eligible),
                'selected_count' => 0,
                'selected_ids' => [],
                'groups' => groupEligibleParticipantsBySection($eligible, []),
            ],
        ]);
        exit;
    }

    if ($request_method === 'POST' && $action === 'save') {
        requireExamManagerRoleForParticipants();
        $exam_id = (int)($input['exam_id'] ?? 0);
        $user_ids = $input['user_ids'] ?? [];

        if ($exam_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        if (!is_array($user_ids)) {
            $user_ids = [];
        }

        $examStmt = $pdo->prepare('SELECT Exam_ID, Title FROM tbl_exam WHERE Exam_ID = ?');
        $examStmt->execute([$exam_id]);
        $exam = $examStmt->fetch(PDO::FETCH_ASSOC);
        if (!$exam) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Exam not found']);
            exit;
        }

        $batchCount = $pdo->prepare('SELECT COUNT(*) FROM tbl_exam_batch WHERE Exam_ID = ?');
        $batchCount->execute([$exam_id]);
        if ((int)$batchCount->fetchColumn() === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Assign at least one section before selecting participants']);
            exit;
        }

        $result = saveExamParticipants($pdo, $exam_id, $user_ids);
        $examLabel = auditExamLabel($pdo, $exam_id);
        auditFromSession(
            $pdo,
            'EXAM',
            'UPDATE_EXAM_PARTICIPANTS',
            "{$result['selected']} of {$result['eligible']} participants selected for exam {$examLabel}",
            'SUCCESS',
            'exam',
            $exam_id
        );

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Participants updated',
            'selected_count' => $result['selected'],
            'eligible_count' => $result['eligible'],
        ]);
        exit;
    }

    if ($request_method === 'POST' && $action === 'sync') {
        requireExamManagerRoleForParticipants();
        $exam_id = (int)($input['exam_id'] ?? 0);
        if ($exam_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
            exit;
        }

        $result = syncExamParticipantsFromSections($pdo, $exam_id);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Participants synced from assigned sections',
            'data' => $result,
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Exception $e) {
    error_log('exam-participants.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.']);
}
