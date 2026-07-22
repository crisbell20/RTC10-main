<?php
/**
 * Exam type helpers — PRE_TEST, MODULAR, COMPREHENSIVE, POST_TEST.
 */

const EXAM_TYPES = ['PRE_TEST', 'MODULAR', 'COMPREHENSIVE', 'POST_TEST'];

/**
 * Whether tbl_exam.Exam_Type exists.
 */
function examTypeColumnExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM tbl_exam LIKE 'Exam_Type'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

/**
 * Human-readable labels for UI dropdowns.
 */
function examTypeLabels(): array
{
    return [
        'PRE_TEST' => 'Pre-Test',
        'MODULAR' => 'Modular Examination',
        'COMPREHENSIVE' => 'Comprehensive Examination',
        'POST_TEST' => 'Post-Test',
    ];
}

/**
 * Label for a stored exam type value.
 */
function examTypeLabel(string $examType): string
{
    $labels = examTypeLabels();
    return $labels[$examType] ?? $examType;
}

/**
 * Normalize API/form input to a valid exam type constant.
 */
function normalizeExamTypeInput(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $value = strtoupper(trim($value));
    $aliases = [
        'PRE-TEST' => 'PRE_TEST',
        'PRE TEST' => 'PRE_TEST',
        'POST-TEST' => 'POST_TEST',
        'POST TEST' => 'POST_TEST',
    ];
    $value = $aliases[$value] ?? str_replace('-', '_', $value);

    return in_array($value, EXAM_TYPES, true) ? $value : null;
}

/**
 * Infer exam type from legacy Title text (migration / backward compatibility).
 */
function inferExamTypeFromTitle(?string $title): string
{
    $title = trim((string)$title);
    if ($title === '') {
        return 'MODULAR';
    }

    $lower = strtolower($title);
    if (strpos($lower, 'pre-test') !== false || strpos($lower, 'pretest') !== false || strpos($lower, 'pre test') !== false) {
        return 'PRE_TEST';
    }
    if (strpos($lower, 'post-test') !== false || strpos($lower, 'posttest') !== false || strpos($lower, 'post test') !== false) {
        return 'POST_TEST';
    }
    if (strpos($lower, 'comprehensive') !== false) {
        return 'COMPREHENSIVE';
    }
    if (strpos($lower, 'modular') !== false) {
        return 'MODULAR';
    }

    return 'MODULAR';
}

/**
 * Validate exam type for create/update; returns error message or null.
 */
function validateExamTypeValue(?string $examType): ?string
{
    if ($examType === null || $examType === '') {
        return 'Exam type is required';
    }

    if (!in_array($examType, EXAM_TYPES, true)) {
        return 'Invalid exam type';
    }

    return null;
}

/**
 * Resolve exam type filter from request (empty = all types).
 */
function resolveExamTypeFilter(array $get): ?string
{
    return null;
}

/**
 * Resolve exam type for create/update (column kept for legacy data; not user-facing).
 *
 * @return array{exam_type:string,error:?string}
 */
function resolveExamTypeForSave(PDO $pdo, array $input, string $title, ?int $examId = null): array
{
    if (!examTypeColumnExists($pdo)) {
        return ['exam_type' => 'MODULAR', 'error' => null];
    }

    return ['exam_type' => inferExamTypeFromTitle($title), 'error' => null];
}

/**
 * Whether analytics should use student-level pass/fail for the selected exam type.
 */
function usesStudentLevelPassFail(?string $examType): bool
{
    return in_array($examType, ['POST_TEST', 'COMPREHENSIVE'], true);
}

/**
 * Whether at-risk rules should use type-specific scoring thresholds.
 */
function usesTypeSpecificAtRisk(?string $examType): bool
{
    return in_array($examType, ['POST_TEST', 'COMPREHENSIVE'], true);
}
