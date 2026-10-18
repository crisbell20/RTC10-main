<?php

// Helpers for proctoring
// cheating log display

function cheatingEventLabels(): array {
    return [
        'FULLSCREEN_EXIT' => 'Exited fullscreen',
        'TAB_SWITCH' => 'Switched tab or window',
        'VISIBILITY_HIDDEN' => 'Exam page hidden',
        'APP_BACKGROUND' => 'App moved to background',
        'COPY_ATTEMPT' => 'Copy attempt',
        'PASTE_ATTEMPT' => 'Paste attempt',
        'CONTEXT_MENU' => 'Right-click attempt',
    ];
}

function cheatingLegacyEventLabels(): array {
    return [
        'FULLSCREEN_DENIED' => 'Fullscreen not entered',
        'WINDOW_BLUR' => 'Window lost focus',
        'CUT_ATTEMPT' => 'Cut attempt',
    ];
}

function cheatingAllowedEventTypes(): array {
    return array_keys(cheatingEventLabels());
}

/** Types that create or refresh a row in the admin cheating incident queue. */
function cheatingIncidentTriggerTypes(): array {
    return [
        'FULLSCREEN_EXIT',
        'TAB_SWITCH',
        'VISIBILITY_HIDDEN',
        'APP_BACKGROUND',
        'COPY_ATTEMPT',
        'PASTE_ATTEMPT',
        'CONTEXT_MENU',
    ];
}


function parseCheatingLogType(string $storedType): array {
    $storedType = trim($storedType);
    if ($storedType === '') {
        return ['code' => 'UNKNOWN', 'detail' => ''];
    }

    $known = array_keys(cheatingEventLabels() + cheatingLegacyEventLabels());
    if (in_array($storedType, $known, true)) {
        return ['code' => $storedType, 'detail' => ''];
    }

    $colonPos = strpos($storedType, ':');
    if ($colonPos !== false) {
        $code = trim(substr($storedType, 0, $colonPos));
        $detail = trim(substr($storedType, $colonPos + 1));
        if (in_array($code, $known, true)) {
            return ['code' => $code, 'detail' => $detail];
        }
    }

    foreach ($known as $code) {
        if (stripos($storedType, $code) === 0) {
            $detail = trim(substr($storedType, strlen($code)));
            $detail = ltrim($detail, ': ');
            return ['code' => $code, 'detail' => $detail];
        }
    }

    return ['code' => 'UNKNOWN', 'detail' => $storedType];
}

function formatCheatingEventLabel(string $storedType): string {
    $parsed = parseCheatingLogType($storedType);
    $labels = cheatingEventLabels() + cheatingLegacyEventLabels();
    return $labels[$parsed['code']] ?? $parsed['code'];
}

function formatCheatingIncidentLatestLabel(string $reason): string {
    $reason = trim($reason);
    if ($reason === '') {
        return 'Suspicious activity';
    }

    if (preg_match('/^Latest:\s*(.+?)\s*\|\s*Total/i', $reason, $matches)) {
        $latest = trim($matches[1]);
        $dashPos = strpos($latest, ' — ');
        if ($dashPos !== false) {
            $candidate = trim(substr($latest, 0, $dashPos));
            $knownLabels = array_values(cheatingEventLabels() + cheatingLegacyEventLabels());
            if (in_array($candidate, $knownLabels, true)) {
                return $candidate;
            }
        }
        return $latest;
    }

    return $reason;
}

function formatCheatingProctorLine(string $badgeNumber, string $fullname, string $eventLabel): string {
    $badge = trim($badgeNumber) !== '' ? trim($badgeNumber) : '—';
    $name = trim($fullname) !== '' ? trim($fullname) : 'Trainee';
    $label = trim($eventLabel) !== '' ? trim($eventLabel) : 'Suspicious activity';

    return $badge . ', ' . $name . ' — ' . $label;
}

function enrichCheatingLogRow(array $row): array {
    $parsed = parseCheatingLogType((string)($row['Type'] ?? ''));
    $row['event_code'] = $parsed['code'];
    $row['event_detail'] = $parsed['detail'];
    $row['event_label'] = formatCheatingEventLabel((string)($row['Type'] ?? ''));
    return $row;
}

function cheatingLogTableExists(PDO $pdo): bool {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'tbl_cheating_log'");
        return $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function cheatingIncidentTableExists(PDO $pdo): bool {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'tbl_cheating_incident'");
        return $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function userBatchTableExists(PDO $pdo): bool {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
        return $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function cheatingTraineeBatchNameSql(string $userAlias = 'u', string $courseAlias = 'c'): string {
    return "(
        SELECT COALESCE(pb.Batch_Name, b.Batch_Name)
        FROM tbl_user_batch ub
        INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
        LEFT JOIN tbl_academic_section s ON b.Section_ID = s.Section_ID
        LEFT JOIN tbl_batch pb ON s.Batch_ID = pb.Batch_ID
        WHERE ub.User_ID = {$userAlias}.User_ID
          AND ub.Status = 'Active'
          AND b.Course_ID = {$courseAlias}.Course_ID
        ORDER BY ub.Date_Enrolled DESC
        LIMIT 1
    )";
}


function cheatingTraineeSectionNameSql(string $userAlias = 'u', string $courseAlias = 'c'): string {
    return "(
        SELECT s.Section_Name
        FROM tbl_user_batch ub
        INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
        LEFT JOIN tbl_academic_section s ON b.Section_ID = s.Section_ID
        WHERE ub.User_ID = {$userAlias}.User_ID
          AND ub.Status = 'Active'
          AND b.Course_ID = {$courseAlias}.Course_ID
        ORDER BY ub.Date_Enrolled DESC
        LIMIT 1
    )";
}
