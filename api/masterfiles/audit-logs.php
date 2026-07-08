<?php
/**
 * Audit logs API — list, recent, export metadata, retention purge (Admin).
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';

$userRole = $_SESSION['user_role'] ?? '';
$requestMethod = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function sendJson(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function buildAuditListQuery(PDO $pdo, array $filters, bool $extended): array
{
    $select = $extended
        ? 'l.Log_ID, l.User_ID, l.Action, l.Module, l.Outcome, l.Status, l.Entity_Type, l.Entity_ID,
           l.IP_Address, l.User_Role, l.Timestamp,
           u.Fullname AS user_name, u.Email AS user_email, r.Role_Name AS role_name'
        : 'l.Log_ID, l.User_ID, l.Action, l.Outcome, l.Timestamp,
           u.Fullname AS user_name, u.Email AS user_email, r.Role_Name AS role_name';

    $sql = "
        SELECT {$select}
        FROM tbl_audit_log l
        LEFT JOIN tbl_user u ON l.User_ID = u.User_ID
        LEFT JOIN tbl_role r ON u.Role_ID = r.Role_ID
        WHERE 1=1
    ";
    $params = [];

    if (!empty($filters['date_from'])) {
        $sql .= ' AND l.Timestamp >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
        $sql .= ' AND l.Timestamp <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    if (!empty($filters['module']) && $extended) {
        $sql .= ' AND l.Module = ?';
        $params[] = $filters['module'];
    }
    if (!empty($filters['audit_action'])) {
        $sql .= ' AND l.Action = ?';
        $params[] = $filters['audit_action'];
    }
    if (!empty($filters['status']) && $extended) {
        $sql .= ' AND l.Status = ?';
        $params[] = strtoupper($filters['status']);
    }
    if (!empty($filters['search'])) {
        $sql .= ' AND (u.Fullname LIKE ? OR u.Email LIKE ? OR l.Action LIKE ? OR l.Outcome LIKE ?';
        if ($extended) {
            $sql .= ' OR l.Module LIKE ?';
        }
        $sql .= ')';
        $like = '%' . $filters['search'] . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        if ($extended) {
            $params[] = $like;
        }
    }

    return [$sql, $params];
}

function normalizeAuditRow(array $row, bool $extended): array
{
    if (!$extended) {
        $row['Module'] = inferAuditModuleFromAction((string)($row['Action'] ?? ''));
        $row['Status'] = str_starts_with((string)($row['Outcome'] ?? ''), '[FAILED]') ? 'FAILED' : 'SUCCESS';
        $row['Entity_Type'] = null;
        $row['Entity_ID'] = null;
        $row['IP_Address'] = null;
        $row['User_Role'] = $row['role_name'] ?? null;
    } else {
        $row['User_Role'] = $row['User_Role'] ?? ($row['role_name'] ?? null);
    }

    $row['user_display'] = $row['user_name']
        ?? ($row['User_Role'] ? ($row['User_Role'] . ' (unknown user)') : 'System / Unknown');

    return $row;
}

try {
    if (!auditLogTableExists($pdo)) {
        sendJson(['success' => false, 'message' => 'Audit log table is not available'], 503);
    }

    $extended = auditLogExtendedColumnsExist($pdo);

    if ($requestMethod === 'GET' && $action === 'meta') {
        sendJson([
            'success' => true,
            'data' => [
                'modules' => getAuditModuleOptions(),
                'actions' => getAuditActionOptions(),
                'extended_schema' => $extended,
                'retention_days_default' => 365,
            ],
        ]);
    }

    if ($requestMethod === 'GET' && $action === 'recent') {
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
        [$sql, $params] = buildAuditListQuery($pdo, [], $extended);
        $sql .= ' ORDER BY l.Timestamp DESC LIMIT ' . $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = array_map(fn($r) => normalizeAuditRow($r, $extended), $stmt->fetchAll(PDO::FETCH_ASSOC));

        sendJson(['success' => true, 'data' => $rows]);
    }

    if ($requestMethod === 'GET' && ($action === 'list' || $action === 'export')) {
        $filters = [
            'date_from' => trim($_GET['date_from'] ?? ''),
            'date_to' => trim($_GET['date_to'] ?? ''),
            'module' => trim($_GET['module'] ?? ''),
            'audit_action' => trim($_GET['audit_action'] ?? ''),
            'status' => trim($_GET['status'] ?? ''),
            'search' => trim($_GET['search'] ?? ''),
        ];

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = $action === 'export'
            ? max(1, min(10000, (int)($_GET['limit'] ?? 5000)))
            : max(1, min(100, (int)($_GET['per_page'] ?? 25)));

        [$baseSql, $params] = buildAuditListQuery($pdo, $filters, $extended);

        $countSql = 'SELECT COUNT(*) AS total FROM (' . $baseSql . ') AS audit_filtered';
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $offset = $action === 'export' ? 0 : ($page - 1) * $perPage;
        $sql = $baseSql . ' ORDER BY l.Timestamp DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = array_map(fn($r) => normalizeAuditRow($r, $extended), $stmt->fetchAll(PDO::FETCH_ASSOC));

        sendJson([
            'success' => true,
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0,
            ],
        ]);
    }

    if ($requestMethod === 'GET' && $action === 'detail') {
        $logId = (int)($_GET['log_id'] ?? 0);
        if (!$logId) {
            sendJson(['success' => false, 'message' => 'log_id is required'], 400);
        }

        [$sql, $params] = buildAuditListQuery($pdo, [], $extended);
        $sql .= ' AND l.Log_ID = ? LIMIT 1';
        $params[] = $logId;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            sendJson(['success' => false, 'message' => 'Log entry not found'], 404);
        }

        sendJson(['success' => true, 'data' => normalizeAuditRow($row, $extended)]);
    }

    if ($requestMethod === 'POST' && $action === 'log_export') {
        $raw = json_decode(file_get_contents('php://input'), true) ?? [];
        $rowCount = max(0, (int)($raw['row_count'] ?? $_POST['row_count'] ?? 0));
        auditFromSession(
            $pdo,
            'SYSTEM',
            'EXPORT_AUDIT_LOGS',
            "Exported audit logs CSV ({$rowCount} rows)",
            'SUCCESS',
            'audit_log',
            null
        );
        sendJson(['success' => true, 'logged' => true]);
    }

    if ($requestMethod === 'POST' && $action === 'purge') {
        if ($userRole !== 'Admin') {
            sendJson(['success' => false, 'message' => 'Admin access required'], 403);
        }

        $raw = json_decode(file_get_contents('php://input'), true) ?? [];
        $days = max(30, min(3650, (int)($raw['days'] ?? $_POST['days'] ?? 365)));

        $stmt = $pdo->prepare('DELETE FROM tbl_audit_log WHERE Timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)');
        $stmt->execute([$days]);
        $deleted = $stmt->rowCount();

        auditFromSession(
            $pdo,
            'SYSTEM',
            'PURGE_AUDIT_LOGS',
            "Purged {$deleted} audit entries older than {$days} days",
            'SUCCESS',
            'audit_log',
            null
        );

        sendJson([
            'success' => true,
            'message' => "Removed {$deleted} log entries older than {$days} days",
            'deleted' => $deleted,
        ]);
    }

    sendJson(['success' => false, 'message' => 'Invalid action'], 400);
} catch (Exception $e) {
    sendJson(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
