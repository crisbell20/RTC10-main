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
require_once __DIR__ . '/../includes/module-utils.php';
require_once __DIR__ . '/../includes/archive-utils.php';
require_once __DIR__ . '/../includes/validation-utils.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');
$hasModules = moduleSchemaEnabled($pdo);

function buildAutoSubjectCode(string $name): string {
    $slug = preg_replace('/[^A-Za-z0-9]+/', '', $name);
    $slug = strtoupper(substr($slug, 0, 24));
    return $slug !== '' ? $slug : 'SUB';
}

function ensureUniqueSubjectCode(PDO $pdo, string $baseCode, ?int $moduleId, ?int $excludeSubjectId = null): string {
    $candidate = $baseCode;
    $suffix = 1;
    while (true) {
        if ($moduleId) {
            $sql = 'SELECT Subject_ID FROM tbl_subject WHERE Module_ID = ? AND Subject_Code = ?';
            $params = [$moduleId, $candidate];
        } else {
            $sql = 'SELECT Subject_ID FROM tbl_subject WHERE Subject_Code = ?';
            $params = [$candidate];
        }
        if ($excludeSubjectId) {
            $sql .= ' AND Subject_ID <> ?';
            $params[] = $excludeSubjectId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $candidate;
        }
        $candidate = substr($baseCode, 0, 40) . $suffix;
        $suffix++;
    }
}

function resolveSubjectCodeForSave(PDO $pdo, string $name, string $requestedCode, ?int $moduleId, ?int $subjectId = null): string {
    $requestedCode = trim($requestedCode);
    if ($requestedCode !== '') {
        return ensureUniqueSubjectCode($pdo, $requestedCode, $moduleId, $subjectId);
    }
    if ($subjectId) {
        $stmt = $pdo->prepare('SELECT Subject_Code FROM tbl_subject WHERE Subject_ID = ? LIMIT 1');
        $stmt->execute([$subjectId]);
        $existing = trim((string)($stmt->fetchColumn() ?: ''));
        if ($existing !== '') {
            return $existing;
        }
    }
    $base = buildAutoSubjectCode($name);
    return ensureUniqueSubjectCode($pdo, $base, $moduleId, $subjectId);
}

try {
    if ($request_method === 'GET' && in_array($action, ['list', 'tree'], true)) {
        backfillCascadeArchives($pdo);
    }

    if ($request_method === 'GET' && $action === 'list') {
        $course_id = $_GET['course_id'] ?? null;
        $module_id = $_GET['module_id'] ?? null;
        $search = trim($_GET['search'] ?? '');

        if ($hasModules) {
            $subjectActive = activeArchiveCondition($pdo, 'tbl_subject', 's');
            $moduleActive = activeArchiveCondition($pdo, 'tbl_module', 'm');
            $courseActive = activeArchiveCondition($pdo, 'tbl_course', 'c');
            $sql = "SELECT s.Subject_ID, s.Module_ID, s.Subject_Name, s.Subject_Code, s.Description,
                           m.Module_Name, m.Course_ID, c.Course_Name
                    FROM tbl_subject s
                    INNER JOIN tbl_module m ON s.Module_ID = m.Module_ID
                    INNER JOIN tbl_course c ON m.Course_ID = c.Course_ID
                    WHERE {$subjectActive} AND {$moduleActive} AND {$courseActive}";
            $params = [];

            if (!empty($course_id)) {
                $sql .= ' AND m.Course_ID = ?';
                $params[] = $course_id;
            }
            if (!empty($module_id)) {
                $sql .= ' AND s.Module_ID = ?';
                $params[] = $module_id;
            }
            if ($search !== '') {
                $sql .= ' AND (s.Subject_Name LIKE ? OR s.Subject_Code LIKE ? OR s.Description LIKE ? OR m.Module_Name LIKE ?)';
                $like = '%' . $search . '%';
                $params = array_merge($params, [$like, $like, $like, $like]);
            }
            $sql .= ' ORDER BY c.Course_Name, m.Sort_Order, m.Module_Name, s.Subject_Name';
        } else {
            $subjectActive = activeArchiveCondition($pdo, 'tbl_subject');
            $sql = "SELECT Subject_ID, Course_ID, Subject_Name, Subject_Code, Description,
                           NULL AS Module_ID, NULL AS Module_Name, NULL AS Course_Name
                    FROM tbl_subject WHERE {$subjectActive}";
            $params = [];

            if (!empty($course_id)) {
                $sql .= ' AND Course_ID = ?';
                $params[] = $course_id;
            }
            if ($search !== '') {
                $sql .= ' AND (Subject_Name LIKE ? OR Subject_Code LIKE ? OR Description LIKE ?)';
                $like = '%' . $search . '%';
                $params = array_merge($params, [$like, $like, $like]);
            }
            $sql .= ' ORDER BY Subject_Name';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'modules_enabled' => $hasModules]);
        exit;
    }

    if ($request_method === 'POST' && $action === 'add') {
        $module_id = (int)($input['module_id'] ?? $_POST['module_id'] ?? 0);
        $course_id = (int)($input['course_id'] ?? $_POST['course_id'] ?? 0);
        $name = trim($input['subject_name'] ?? $_POST['subject_name'] ?? '');
        $code = trim($input['subject_code'] ?? $_POST['subject_code'] ?? '');
        $desc = $input['description'] ?? $_POST['description'] ?? null;

        if ($hasModules) {
            if ($module_id <= 0 || $name === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Module and subject name are required']);
                exit;
            }

            $code = resolveSubjectCodeForSave($pdo, $name, $code, $module_id);

            $modStmt = $pdo->prepare('SELECT Course_ID FROM tbl_module WHERE Module_ID = ?');
            $modStmt->execute([$module_id]);
            $modRow = $modStmt->fetch(PDO::FETCH_ASSOC);
            if (!$modRow) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Module not found']);
                exit;
            }
            $course_id = (int)$modRow['Course_ID'];

            $stmt = $pdo->prepare('INSERT INTO tbl_subject (Course_ID, Module_ID, Subject_Name, Subject_Code, Description) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$course_id, $module_id, $name, $code, $desc]);
        } else {
            if ($course_id <= 0 || $name === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Course and subject name are required']);
                exit;
            }

            $code = resolveSubjectCodeForSave($pdo, $name, $code, null);

            $stmt = $pdo->prepare('INSERT INTO tbl_subject (Course_ID, Subject_Name, Subject_Code, Description) VALUES (?, ?, ?, ?)');
            $stmt->execute([$course_id, $name, $code, $desc]);
        }

        $subjectId = (int)$pdo->lastInsertId();
        auditFromSession($pdo, 'SUBJECT', 'CREATE_SUBJECT', "Subject {$name} created", 'SUCCESS', 'subject', $subjectId);
        echo json_encode(['success' => true, 'message' => 'Subject added', 'subject_id' => $subjectId]);
        exit;
    }

    if ($request_method === 'POST' && $action === 'update') {
        $subject_id = (int)($input['subject_id'] ?? $_POST['subject_id'] ?? 0);
        $module_id = (int)($input['module_id'] ?? $_POST['module_id'] ?? 0);
        $name = trim($input['subject_name'] ?? $_POST['subject_name'] ?? '');
        $code = trim($input['subject_code'] ?? $_POST['subject_code'] ?? '');
        $desc = $input['description'] ?? $_POST['description'] ?? null;

        if ($subject_id <= 0 || $name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Subject ID and name are required']);
            exit;
        }

        if ($hasModules) {
            if ($module_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Module is required']);
                exit;
            }

            $modStmt = $pdo->prepare('SELECT Course_ID FROM tbl_module WHERE Module_ID = ?');
            $modStmt->execute([$module_id]);
            $modRow = $modStmt->fetch(PDO::FETCH_ASSOC);
            if (!$modRow) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Module not found']);
                exit;
            }

            $code = resolveSubjectCodeForSave($pdo, $name, $code, $module_id, $subject_id);

            $stmt = $pdo->prepare('UPDATE tbl_subject SET Course_ID = ?, Module_ID = ?, Subject_Name = ?, Subject_Code = ?, Description = ? WHERE Subject_ID = ?');
            $stmt->execute([(int)$modRow['Course_ID'], $module_id, $name, $code, $desc, $subject_id]);
        } else {
            $code = resolveSubjectCodeForSave($pdo, $name, $code, null, $subject_id);
            $stmt = $pdo->prepare('UPDATE tbl_subject SET Subject_Name = ?, Subject_Code = ?, Description = ? WHERE Subject_ID = ?');
            $stmt->execute([$name, $code, $desc, $subject_id]);
        }

        auditFromSession($pdo, 'SUBJECT', 'UPDATE_SUBJECT', "Subject {$name} updated", 'SUCCESS', 'subject', $subject_id);
        echo json_encode(['success' => true, 'message' => 'Subject updated']);
        exit;
    }

    if ($request_method === 'POST' && $action === 'archive') {
        $subject_id = (int)($input['subject_id'] ?? $_POST['subject_id'] ?? 0);
        if ($subject_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Subject ID is required']);
            exit;
        }

        $linkedExams = subjectHasLinkedExams($pdo, $subject_id);
        if ($linkedExams > 0) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => "Cannot archive subject while {$linkedExams} exam(s) still reference it. Archive or reassign those exams first.",
            ]);
            exit;
        }

        if (!archiveRecord($pdo, 'tbl_subject', 'Subject_ID', $subject_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unable to archive subject. Run the archive migration first.']);
            exit;
        }

        $subjectLabel = auditSubjectLabel($pdo, $subject_id);
        auditFromSession($pdo, 'SUBJECT', 'ARCHIVE_SUBJECT', "Subject {$subjectLabel} archived", 'SUCCESS', 'subject', $subject_id);
        echo json_encode(['success' => true, 'message' => 'Subject archived']);
        exit;
    }

    if ($request_method === 'POST' && $action === 'restore') {
        $subject_id = (int)($input['subject_id'] ?? $_POST['subject_id'] ?? 0);
        if ($subject_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Subject ID is required']);
            exit;
        }

        if ($hasModules) {
            $metaStmt = $pdo->prepare('
                SELECT s.Subject_ID, s.Module_ID, m.Course_ID
                FROM tbl_subject s
                INNER JOIN tbl_module m ON s.Module_ID = m.Module_ID
                WHERE s.Subject_ID = ?
                LIMIT 1
            ');
        } else {
            $metaStmt = $pdo->prepare('SELECT Subject_ID, Course_ID, NULL AS Module_ID FROM tbl_subject WHERE Subject_ID = ? LIMIT 1');
        }
        $metaStmt->execute([$subject_id]);
        $meta = $metaStmt->fetch(PDO::FETCH_ASSOC);

        if (!$meta) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Subject not found']);
            exit;
        }

        if (!restoreRecord($pdo, 'tbl_subject', 'Subject_ID', $subject_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => restoreFailureMessage($pdo, 'tbl_subject', 'Subject_ID', $subject_id, 'subject')]);
            exit;
        }

        $ancestors = restoreSubjectAncestors($pdo, (int)($meta['Module_ID'] ?? 0), (int)($meta['Course_ID'] ?? 0));

        $subjectLabel = auditSubjectLabel($pdo, $subject_id);
        auditFromSession($pdo, 'SUBJECT', 'RESTORE_SUBJECT', "Subject {$subjectLabel} restored", 'SUCCESS', 'subject', $subject_id);

        $message = 'Subject restored';
        if (!empty($ancestors)) {
            $message = 'Subject restored successfully (parent ' . implode(' and ', $ancestors) . ' also restored). Other subjects in this module remain archived until restored individually.';
        } else {
            $message = 'Subject restored successfully. Other subjects in this module remain archived until restored individually.';
        }

        echo json_encode(['success' => true, 'message' => $message]);
        exit;
    }

    if ($request_method === 'POST' && $action === 'delete') {
        archiveDeletionBlockedResponse();
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (PDOException $e) {
    error_log('Database error in subjects.php: ' . $e->getMessage());
    if ($e->getCode() == 23000) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Subject code already exists in this module']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    error_log('Error in subjects.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
