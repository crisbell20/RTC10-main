<?php
/**
 * Course module schema helpers (Course → Module → Subject).
 */

function moduleTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'tbl_module'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

function subjectModuleColumnExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tbl_subject LIKE 'Module_ID'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

function moduleSchemaEnabled(PDO $pdo): bool
{
    return moduleTableExists($pdo) && subjectModuleColumnExists($pdo);
}

function auditModuleLabel(PDO $pdo, int $moduleId): string
{
    if (!moduleTableExists($pdo)) {
        return "Module {$moduleId}";
    }

    $stmt = $pdo->prepare('SELECT Module_Name FROM tbl_module WHERE Module_ID = ?');
    $stmt->execute([$moduleId]);
    $name = $stmt->fetchColumn();
    return $name ? (string)$name : "Module {$moduleId}";
}

/**
 * SQL fragments for joining subject to course (with optional module layer).
 */
function subjectCurriculumJoin(PDO $pdo): array
{
    if (moduleSchemaEnabled($pdo)) {
        return [
            'join' => 'INNER JOIN tbl_module m ON s.Module_ID = m.Module_ID
                       INNER JOIN tbl_course c ON m.Course_ID = c.Course_ID',
            'course_filter' => 'm.Course_ID',
            'module_filter' => 's.Module_ID',
            'course_id_select' => 'm.Course_ID',
            'module_select' => 'm.Module_ID, m.Module_Name,',
        ];
    }

    return [
        'join' => 'INNER JOIN tbl_course c ON s.Course_ID = c.Course_ID',
        'course_filter' => 's.Course_ID',
        'module_filter' => null,
        'course_id_select' => 's.Course_ID',
        'module_select' => 'NULL AS Module_ID, NULL AS Module_Name,',
    ];
}
