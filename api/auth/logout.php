<?php
session_start();

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/audit-log-utils.php';

$logoutUserId = $_SESSION['user_id'] ?? null;
$logoutRole = $_SESSION['user_role'] ?? null;
$logoutName = $_SESSION['user_name'] ?? null;

if ($logoutUserId && auditLogTableExists($pdo)) {
    writeAuditLog($pdo, [
        'user_id' => (int)$logoutUserId,
        'user_role' => $logoutRole,
        'module' => 'AUTH',
        'action' => 'LOGOUT',
        'outcome' => ($logoutName ? "User {$logoutName} logged out" : 'User logged out'),
        'status' => 'SUCCESS',
        'entity_type' => 'user',
        'entity_id' => (int)$logoutUserId,
    ]);
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// If accessed directly via browser (GET), redirect to login page
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: ../../login.php');
    exit;
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully'
]);
?>
