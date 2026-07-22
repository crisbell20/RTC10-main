<?php
/**
 * Helpers for proctoring / cheating log display and normalization.
 */

function cheatingEventLabels(): array {
    return [
        'FULLSCREEN_EXIT' => 'Exited fullscreen',
        'FULLSCREEN_DENIED' => 'Fullscreen not entered',
        'TAB_SWITCH' => 'Switched tab or window',
        'WINDOW_BLUR' => 'Window lost focus',
        'VISIBILITY_HIDDEN' => 'Exam page hidden',
        'APP_BACKGROUND' => 'App moved to background',
        'COPY_ATTEMPT' => 'Copy attempt',
        'PASTE_ATTEMPT' => 'Paste attempt',
        'CUT_ATTEMPT' => 'Cut attempt',
        'CONTEXT_MENU' => 'Right-click attempt',
    ];
}

function cheatingAllowedEventTypes(): array {
    return array_keys(cheatingEventLabels());
}

/** Types that create or refresh a row in the admin cheating incident queue. */
function cheatingIncidentTriggerTypes(): array {
    return [
        'FULLSCREEN_EXIT',
        'FULLSCREEN_DENIED',
        'TAB_SWITCH',
        'VISIBILITY_HIDDEN',
        'APP_BACKGROUND',
        'COPY_ATTEMPT',
        'PASTE_ATTEMPT',
        'CUT_ATTEMPT',
        'CONTEXT_MENU',
    ];
}

/**
 * Split stored Type column into code + optional detail (legacy rows used "TYPE: detail").
 */
function parseCheatingLogType(string $storedType): array {
    $storedType = trim($storedType);
    if ($storedType === '') {
        return ['code' => 'UNKNOWN', 'detail' => ''];
    }

    $known = cheatingAllowedEventTypes();
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
    $labels = cheatingEventLabels();
    return $labels[$parsed['code']] ?? $parsed['code'];
}

/** Proctor-facing one-line summary (legacy incident Reason strings supported). */
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
            $knownLabels = array_values(cheatingEventLabels());
            if (in_array($candidate, $knownLabels, true)) {
                return $candidate;
            }
        }
        return $latest;
    }

    return $reason;
}

function formatCheatingProctorLine(string $academicNumber, string $fullname, string $eventLabel): string {
    $academic = trim($academicNumber) !== '' ? trim($academicNumber) : '—';
    $name = trim($fullname) !== '' ? trim($fullname) : 'Trainee';
    $label = trim($eventLabel) !== '' ? trim($eventLabel) : 'Suspicious activity';

    return $academic . ', ' . $name . ' — ' . $label;
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
