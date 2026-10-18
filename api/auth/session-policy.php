<?php

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/connection-pdo.php';
require_once __DIR__ . '/../includes/system-settings-utils.php';

echo json_encode([
    'success' => true,
    'data' => [
        'idle_timeout_minutes' => getIdleTimeoutMinutes($pdo),
    ],
]);
