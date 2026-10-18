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

if (!moduleSchemaEnabled($pdo)) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Module schema is not installed. Import scripts/migrations/add_course_modules.sql on the database.',
    ]);
    exit;
}

try {
    if ($request_method === 'GET' && $action === 'list') {
        $course_id = $_GET['course_id'] ?? null;
        $sql = 'SELECT Module_ID, Course_ID, Module_Name, Description, Sort_Order, Date_Created
                FROM tbl_module WHERE 1=1';
        $params = [];
        if (!empty($course_id)) {
            $sql .= ' AND Course_ID = ?';
            $params[] = $course_id;
        }
        $sql .= ' ORDER BY Course_ID, Sort_Order, Module_Name';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($request_method === 'GET' && $action === 'tree') {
        backfillCascadeArchives($pdo);

        $course_id = $_GET['course_id'] ?? null;
        $courseSql = 'SELECT Course_ID, Course_Name FROM tbl_course WHERE ' . activeArchiveCondition($pdo, 'tbl_course');
        $courseParams = [];
        if (!empty($course_id)) {
            $courseSql .= ' AND Course_ID = ?';
            $courseParams[] = $course_id;
        }
        $courseSql .= ' ORDER BY Course_Name';
        $courseStmt = $pdo->prepare($courseSql);
        $courseStmt->execute($courseParams);
        $courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);

        $moduleActive = activeArchiveCondition($pdo, 'tbl_module');
        $moduleStmt = $pdo->prepare("
            SELECT Module_ID, Course_ID, Module_Name, Description, Sort_Order
            FROM tbl_module
            WHERE Course_ID = ? AND {$moduleActive}
            ORDER BY Sort_Order, Module_Name
        ");
        $subjectActive = activeArchiveCondition($pdo, 'tbl_subject');
        $subjectStmt = $pdo->prepare("
            SELECT Subject_ID, Module_ID, Subject_Name, Description
            FROM tbl_subject
            WHERE Module_ID = ? AND {$subjectActive}
            ORDER BY Subject_Name
        ");

        foreach ($courses as &$course) {
            $moduleStmt->execute([$course['Course_ID']]);
            $modules = $moduleStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($modules as &$module) {
                $subjectStmt->execute([$module['Module_ID']]);
                $module['subjects'] = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($module);
            $course['modules'] = $modules;
        }
        unset($course);

        echo json_encode(['success' => true, 'data' => $courses]);
        exit;
    }

    if ($request_method === 'POST' && $action === 'add') {
        $course_id = (int)($input['course_id'] ?? $_POST['course_id'] ?? 0);
        $name = trim($input['module_name'] ?? $_POST['module_name'] ?? '');
        $desc = $input['description'] ?? $_POST['description'] ?? null;
        $sort = (int)($input['sort_order'] ?? $_POST['sort_order'] ?? 0);

        if ($course_id <= 0 || $name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Course and module name are required']);
            exit;
        }

        if (moduleNameExistsInCourseSafe($pdo, $course_id, $name)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'A module with this name already exists in this course']);
            exit;
        }

        if ($sort <= 0) {
            $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(Sort_Order), 0) FROM tbl_module WHERE Course_ID = ?');
            $maxStmt->execute([$course_id]);
            $sort = (int)$maxStmt->fetchColumn() + 1;
        }

        $stmt = $pdo->prepare('INSERT INTO tbl_module (Course_ID, Module_Name, Description, Sort_Order) VALUES (?, ?, ?, ?)');
        $stmt->execute([$course_id, $name, $desc, $sort]);
        $moduleId = (int)$pdo->lastInsertId();

        auditFromSession($pdo, 'COURSE', 'CREATE_MODULE', "Module \"{$name}\" created", 'SUCCESS', 'module', $moduleId);
        echo json_encode(['success' => true, 'message' => 'Module added', 'module_id' => $moduleId]);
        exit;
    }

    if ($request_method === 'POST' && $action === 'update') {
        $module_id = (int)($input['module_id'] ?? $_POST['module_id'] ?? 0);
        $name = trim($input['module_name'] ?? $_POST['module_name'] ?? '');
        $hasDescription = array_key_exists('description', $input) || array_key_exists('description', $_POST);
        $desc = $hasDescription ? ($input['description'] ?? $_POST['description'] ?? null) : null;
        $sort = isset($input['sort_order']) ? (int)$input['sort_order'] : null;

        if ($module_id <= 0 || $name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Module ID and name are required']);
            exit;
        }

        $courseStmt = $pdo->prepare('SELECT Course_ID FROM tbl_module WHERE Module_ID = ?');
        $courseStmt->execute([$module_id]);
        $courseId = (int)$courseStmt->fetchColumn();
        if ($courseId > 0 && moduleNameExistsInCourseSafe($pdo, $courseId, $name, $module_id)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'A module with this name already exists in this course']);
            exit;
        }

        if ($sort !== null && $hasDescription) {
            $stmt = $pdo->prepare('UPDATE tbl_module SET Module_Name = ?, Description = ?, Sort_Order = ? WHERE Module_ID = ?');
            $stmt->execute([$name, $desc, $sort, $module_id]);
        } elseif ($sort !== null) {
            $stmt = $pdo->prepare('UPDATE tbl_module SET Module_Name = ?, Sort_Order = ? WHERE Module_ID = ?');
            $stmt->execute([$name, $sort, $module_id]);
        } elseif ($hasDescription) {
            $stmt = $pdo->prepare('UPDATE tbl_module SET Module_Name = ?, Description = ? WHERE Module_ID = ?');
            $stmt->execute([$name, $desc, $module_id]);
        } else {
            $stmt = $pdo->prepare('UPDATE tbl_module SET Module_Name = ? WHERE Module_ID = ?');
            $stmt->execute([$name, $module_id]);
        }

        auditFromSession($pdo, 'COURSE', 'UPDATE_MODULE', "Module \"{$name}\" updated", 'SUCCESS', 'module', $module_id);
        echo json_encode(['success' => true, 'message' => 'Module updated']);
        exit;
    }

    if ($request_method === 'POST' && $action === 'archive') {
        $module_id = (int)($input['module_id'] ?? $_POST['module_id'] ?? 0);
        if ($module_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Module ID is required']);
            exit;
        }

        $subjectIds = subjectIdsForModule($pdo, $module_id);
        $activeSessions = countActiveExamSessionsForSubjects($pdo, $subjectIds);
        if ($activeSessions > 0) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => "Cannot archive module while {$activeSessions} trainee session(s) are still in progress.",
            ]);
            exit;
        }

        $result = archiveModuleWithSubjects($pdo, $module_id);
        if (!$result['module']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unable to archive module. Run the archive migration first.']);
            exit;
        }

        $label = auditModuleLabel($pdo, $module_id);
        $auditOutcome = "Module \"{$label}\" archived";
        if ($result['subjects'] > 0) {
            $auditOutcome .= " ({$result['subjects']} subject(s)";
            if (!empty($result['exams'])) {
                $auditOutcome .= ", {$result['exams']} exam(s)";
            }
            $auditOutcome .= ' also archived)';
        }
        auditFromSession($pdo, 'COURSE', 'ARCHIVE_MODULE', $auditOutcome, 'SUCCESS', 'module', $module_id);
        echo json_encode([
            'success' => true,
            'message' => formatCascadeArchiveMessage('module', $result),
            'cascade' => $result,
        ]);
        exit;
    }

    if ($request_method === 'POST' && $action === 'restore') {
        $module_id = (int)($input['module_id'] ?? $_POST['module_id'] ?? 0);
        if ($module_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Module ID is required']);
            exit;
        }

        $metaStmt = $pdo->prepare('SELECT Module_ID, Course_ID FROM tbl_module WHERE Module_ID = ? LIMIT 1');
        $metaStmt->execute([$module_id]);
        $meta = $metaStmt->fetch(PDO::FETCH_ASSOC);

        if (!$meta) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Module not found']);
            exit;
        }

        $result = restoreModuleWithSubjects($pdo, $module_id);
        if (!$result['module']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => restoreFailureMessage($pdo, 'tbl_module', 'Module_ID', $module_id, 'module')]);
            exit;
        }

        $label = auditModuleLabel($pdo, $module_id);
        auditFromSession($pdo, 'COURSE', 'RESTORE_MODULE', "Module \"{$label}\" restored", 'SUCCESS', 'module', $module_id);

        echo json_encode([
            'success' => true,
            'message' => formatCascadeRestoreMessage('module', $result),
            'cascade' => $result,
        ]);
        exit;
    }

    if ($request_method === 'POST' && $action === 'delete') {
        archiveDeletionBlockedResponse();
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Module already exists for this course']);
        exit;
    }
    error_log('modules.php DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.']);
} catch (Exception $e) {
    error_log('modules.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.']);
}
