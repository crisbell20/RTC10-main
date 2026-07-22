<?php
/**
 * User identity helpers — academic number login for examinees, email for staff.
 */

const EXAMINEE_EMAIL_DOMAIN = '@rtc.examinee.local';
const ACADEMIC_NUMBER_FORMAT_MESSAGE = 'Academic number must be one letter followed by 3 numbers (e.g. A001)';
const ACADEMIC_NUMBER_START = 'A001';

function normalizeAcademicNumber(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $normalized = strtoupper(trim($value));
    return $normalized === '' ? null : $normalized;
}

function isValidAcademicNumberFormat(?string $value): bool
{
    $normalized = normalizeAcademicNumber($value);

    return $normalized !== null && preg_match('/^[A-Z][0-9]{3}$/', $normalized) === 1;
}

function validateAcademicNumberFormat(?string $value, bool $allowEmpty = false): ?string
{
    $normalized = normalizeAcademicNumber($value);
    if ($normalized === null) {
        return $allowEmpty ? null : 'Academic number is required';
    }

    if (!isValidAcademicNumberFormat($normalized)) {
        return ACADEMIC_NUMBER_FORMAT_MESSAGE;
    }

    return null;
}

/**
 * @return array{letter:string,num:int}|null
 */
function parseAcademicNumber(string $value): ?array
{
    $normalized = normalizeAcademicNumber($value);
    if ($normalized === null || !isValidAcademicNumberFormat($normalized)) {
        return null;
    }

    return [
        'letter' => $normalized[0],
        'num' => (int)substr($normalized, 1),
    ];
}

function formatAcademicNumber(string $letter, int $num): string
{
    $letter = strtoupper($letter);
    if (!preg_match('/^[A-Z]$/', $letter)) {
        throw new InvalidArgumentException('Academic number letter must be A-Z');
    }

    if ($num < 1 || $num > 999) {
        throw new InvalidArgumentException('Academic number sequence must be between 001 and 999');
    }

    return $letter . str_pad((string)$num, 3, '0', STR_PAD_LEFT);
}

function compareAcademicNumbers(string $a, string $b): int
{
    $parsedA = parseAcademicNumber($a);
    $parsedB = parseAcademicNumber($b);

    if ($parsedA === null || $parsedB === null) {
        return strcmp($a, $b);
    }

    if ($parsedA['letter'] !== $parsedB['letter']) {
        return strcmp($parsedA['letter'], $parsedB['letter']);
    }

    return $parsedA['num'] <=> $parsedB['num'];
}

function incrementAcademicNumber(string $value): string
{
    $parsed = parseAcademicNumber($value);
    if ($parsed === null) {
        return ACADEMIC_NUMBER_START;
    }

    if ($parsed['num'] < 999) {
        return formatAcademicNumber($parsed['letter'], $parsed['num'] + 1);
    }

    if ($parsed['letter'] === 'Z') {
        throw new RuntimeException('Academic number capacity reached (Z999)');
    }

    return formatAcademicNumber(chr(ord($parsed['letter']) + 1), 1);
}

/**
 * @return list<string>
 */
function getUsedAcademicNumbers(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT Academic_Number
        FROM tbl_user
        WHERE Academic_Number IS NOT NULL AND TRIM(Academic_Number) != ''
    ");

    $used = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $number) {
        $normalized = normalizeAcademicNumber((string)$number);
        if ($normalized !== null && isValidAcademicNumberFormat($normalized)) {
            $used[$normalized] = true;
        }
    }

    return array_keys($used);
}

function getNextAcademicNumber(PDO $pdo, array $reserved = []): string
{
    $used = getUsedAcademicNumbers($pdo);

    foreach ($reserved as $number) {
        $normalized = normalizeAcademicNumber((string)$number);
        if ($normalized !== null && isValidAcademicNumberFormat($normalized)) {
            $used[] = $normalized;
        }
    }

    $used = array_values(array_unique($used));
    if (empty($used)) {
        return ACADEMIC_NUMBER_START;
    }

    usort($used, 'compareAcademicNumbers');

    return incrementAcademicNumber((string)end($used));
}

/**
 * Resolve an examinee academic number — auto-assign when empty, validate when provided.
 *
 * @return array{number:?string,error:?string}
 */
function resolveExamineeAcademicNumber(PDO $pdo, ?string $value, array $reserved = []): array
{
    $normalized = normalizeAcademicNumber($value);

    if ($normalized === null) {
        try {
            return ['number' => getNextAcademicNumber($pdo, $reserved), 'error' => null];
        } catch (RuntimeException $e) {
            return ['number' => null, 'error' => $e->getMessage()];
        }
    }

    $formatError = validateAcademicNumberFormat($normalized);
    if ($formatError !== null) {
        return ['number' => null, 'error' => $formatError];
    }

    return ['number' => $normalized, 'error' => null];
}

function isStaffRoleName(?string $roleName): bool
{
    return in_array($roleName, ['Admin', 'CCMD'], true);
}

function isExamineeRoleName(?string $roleName): bool
{
    return $roleName === 'Examinee';
}

function examineePlaceholderEmail(string $academicNumber): string
{
    return strtolower($academicNumber) . EXAMINEE_EMAIL_DOMAIN;
}

function isExamineePlaceholderEmail(?string $email): bool
{
    if ($email === null || $email === '') {
        return true;
    }

    return str_ends_with(strtolower($email), EXAMINEE_EMAIL_DOMAIN);
}

function displayUserEmail(?string $email): string
{
    return isExamineePlaceholderEmail($email) ? '' : (string)$email;
}

function academicNumberUniqueIndexExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW INDEX FROM tbl_user WHERE Key_name = 'uq_user_academic_number'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

/**
 * @return string|null Error message if academic number is taken.
 */
function ensureAcademicNumberAvailable(PDO $pdo, ?string $academicNumber, ?int $excludeUserId = null): ?string
{
    $normalized = normalizeAcademicNumber($academicNumber);
    if ($normalized === null) {
        return null;
    }

    $sql = 'SELECT User_ID FROM tbl_user WHERE Academic_Number = ?';
    $params = [$normalized];

    if ($excludeUserId > 0) {
        $sql .= ' AND User_ID != ?';
        $params[] = $excludeUserId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetch()) {
        return "This student is already in the system (Academic Number: {$normalized}).";
    }

    return null;
}

/**
 * Find a user by academic number or email (login identifier).
 * Matching is case-sensitive for both fields.
 */
function findUserByLoginIdentifier(PDO $pdo, string $identifier): ?array
{
    $trimmed = trim($identifier);
    if ($trimmed === '') {
        return null;
    }

    $stmt = $pdo->prepare('
        SELECT u.*, r.Role_Name
        FROM tbl_user u
        LEFT JOIN tbl_role r ON u.Role_ID = r.Role_ID
        WHERE BINARY u.Academic_Number = ?
           OR BINARY u.Email = ?
        LIMIT 1
    ');
    $stmt->execute([$trimmed, $trimmed]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

/**
 * Resolve role name from role ID.
 */
function resolveRoleName(PDO $pdo, $roleId): ?string
{
    if (empty($roleId) || !is_numeric($roleId)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT Role_Name FROM tbl_role WHERE Role_ID = ?');
    $stmt->execute([(int)$roleId]);
    $name = $stmt->fetchColumn();

    return $name ? (string)$name : null;
}

/**
 * Build stored email for a user row based on role.
 */
function resolveStoredEmail(?string $roleName, ?string $email, ?string $academicNumber): ?string
{
    $trimmedEmail = trim((string)$email);

    if (isExamineeRoleName($roleName)) {
        if ($trimmedEmail !== '' && filter_var($trimmedEmail, FILTER_VALIDATE_EMAIL)) {
            return $trimmedEmail;
        }

        $normalized = normalizeAcademicNumber($academicNumber);
        return $normalized ? examineePlaceholderEmail($normalized) : null;
    }

    return $trimmedEmail !== '' ? $trimmedEmail : null;
}
