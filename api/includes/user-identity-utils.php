<?php
/**
 * User identity helpers — badge number login for examinees, email for staff.
 */

require_once __DIR__ . '/person-name-utils.php';

const EXAMINEE_EMAIL_DOMAIN = '@rtc.examinee.local';
const BADGE_NUMBER_FORMAT_MESSAGE = 'Badge number must be at least 6 digits';
const BADGE_NUMBER_START = 'A001';

function normalizeBadgeNumber(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $normalized = strtoupper(trim($value));
    return $normalized === '' ? null : $normalized;
}

function isValidBadgeNumberFormat(?string $value): bool
{
    $normalized = normalizeBadgeNumber($value);

    return $normalized !== null && preg_match('/^[0-9]{6,}$/', $normalized) === 1;
}

function validateBadgeNumberFormat(?string $value, bool $allowEmpty = false): ?string
{
    $normalized = normalizeBadgeNumber($value);
    if ($normalized === null) {
        return $allowEmpty ? null : 'Badge number is required';
    }

    if (!isValidBadgeNumberFormat($normalized)) {
        return BADGE_NUMBER_FORMAT_MESSAGE;
    }

    return null;
}

/**
 * @return array{letter:string,num:int}|null
 */
function parseBadgeNumber(string $value): ?array
{
    $normalized = normalizeBadgeNumber($value);
    if ($normalized === null || !isValidBadgeNumberFormat($normalized)) {
        return null;
    }

    return [
        'letter' => $normalized[0],
        'num' => (int)substr($normalized, 1),
    ];
}

function formatBadgeNumber(string $letter, int $num): string
{
    $letter = strtoupper($letter);
    if (!preg_match('/^[A-Z]$/', $letter)) {
        throw new InvalidArgumentException('Badge number letter must be A-Z');
    }

    if ($num < 1 || $num > 999) {
        throw new InvalidArgumentException('Badge number sequence must be between 001 and 999');
    }

    return $letter . str_pad((string)$num, 3, '0', STR_PAD_LEFT);
}

function compareBadgeNumbers(string $a, string $b): int
{
    $parsedA = parseBadgeNumber($a);
    $parsedB = parseBadgeNumber($b);

    if ($parsedA === null || $parsedB === null) {
        return strcmp($a, $b);
    }

    if ($parsedA['letter'] !== $parsedB['letter']) {
        return strcmp($parsedA['letter'], $parsedB['letter']);
    }

    return $parsedA['num'] <=> $parsedB['num'];
}

function incrementBadgeNumber(string $value): string
{
    $parsed = parseBadgeNumber($value);
    if ($parsed === null) {
        return BADGE_NUMBER_START;
    }

    if ($parsed['num'] < 999) {
        return formatBadgeNumber($parsed['letter'], $parsed['num'] + 1);
    }

    if ($parsed['letter'] === 'Z') {
        throw new RuntimeException('Badge number capacity reached (Z999)');
    }

    return formatBadgeNumber(chr(ord($parsed['letter']) + 1), 1);
}

/**
 * @return list<string>
 */
function getUsedBadgeNumbers(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT Badge_Number
        FROM tbl_user
        WHERE Badge_Number IS NOT NULL AND TRIM(Badge_Number) != ''
    ");

    $used = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $number) {
        $normalized = normalizeBadgeNumber((string)$number);
        if ($normalized !== null && isValidBadgeNumberFormat($normalized)) {
            $used[$normalized] = true;
        }
    }

    return array_keys($used);
}

function getNextBadgeNumber(PDO $pdo, array $reserved = []): string
{
    $used = getUsedBadgeNumbers($pdo);

    foreach ($reserved as $number) {
        $normalized = normalizeBadgeNumber((string)$number);
        if ($normalized !== null && isValidBadgeNumberFormat($normalized)) {
            $used[] = $normalized;
        }
    }

    $used = array_values(array_unique($used));
    if (empty($used)) {
        return BADGE_NUMBER_START;
    }

    usort($used, 'compareBadgeNumbers');

    return incrementBadgeNumber((string)end($used));
}

/**
 * Resolve an examinee badge number — auto-assign when empty, validate when provided.
 *
 * @return array{number:?string,error:?string}
 */
function resolveExamineeBadgeNumber(PDO $pdo, ?string $value, array $reserved = []): array
{
    $normalized = normalizeBadgeNumber($value);

    if ($normalized === null) {
        try {
            return ['number' => getNextBadgeNumber($pdo, $reserved), 'error' => null];
        } catch (RuntimeException $e) {
            return ['number' => null, 'error' => $e->getMessage()];
        }
    }

    $formatError = validateBadgeNumberFormat($normalized);
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

function examineePlaceholderEmail(string $badgeNumber): string
{
    return strtolower($badgeNumber) . EXAMINEE_EMAIL_DOMAIN;
}

function isExamineePlaceholderEmail(?string $email): bool
{
    $email = strtolower(trim((string)$email));
    if ($email === '') {
        return true;
    }

    return str_ends_with($email, EXAMINEE_EMAIL_DOMAIN);
}

function displayUserEmail(?string $email): string
{
    return isExamineePlaceholderEmail($email) ? '' : (string)$email;
}

function badgeNumberUniqueIndexExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW INDEX FROM tbl_user WHERE Key_name = 'uq_user_badge_number'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

/**
 * @return string|null Error message if badge number is taken.
 */
function ensureBadgeNumberAvailable(PDO $pdo, ?string $badgeNumber, ?int $excludeUserId = null): ?string
{
    $normalized = normalizeBadgeNumber($badgeNumber);
    if ($normalized === null) {
        return null;
    }

    $sql = 'SELECT User_ID FROM tbl_user WHERE Badge_Number = ?';
    $params = [$normalized];

    if ($excludeUserId > 0) {
        $sql .= ' AND User_ID != ?';
        $params[] = $excludeUserId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->fetch()) {
        return "This trainee is already in the system (Badge Number: {$normalized}).";
    }

    return null;
}

/**
 * Find a user by badge number or email (login identifier).
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
        WHERE BINARY u.Badge_Number = ?
           OR BINARY u.Email = ?
        LIMIT 1
    ');
    $stmt->execute([$trimmed, $trimmed]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return null;
    }

    if (array_key_exists('Firstname', $user) || array_key_exists('Lastname', $user)) {
        $user['Fullname'] = formatPersonName(
            $user['Firstname'] ?? '',
            $user['Middlename'] ?? '',
            $user['Lastname'] ?? ''
        );
    }

    return $user;
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
function resolveStoredEmail(?string $roleName, ?string $email, ?string $badgeNumber): ?string
{
    $trimmedEmail = trim((string)$email);

    if (isExamineeRoleName($roleName)) {
        if ($trimmedEmail !== '' && filter_var($trimmedEmail, FILTER_VALIDATE_EMAIL)) {
            return $trimmedEmail;
        }

        $normalized = normalizeBadgeNumber($badgeNumber);
        return $normalized ? examineePlaceholderEmail($normalized) : null;
    }

    return $trimmedEmail !== '' ? $trimmedEmail : null;
}
