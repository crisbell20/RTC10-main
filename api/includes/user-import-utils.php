<?php

// User import

require_once __DIR__ . '/batch-enrollment-utils.php';
require_once __DIR__ . '/user-identity-utils.php';
require_once __DIR__ . '/password-utils.php';
require_once __DIR__ . '/exam-code-utils.php';
require_once __DIR__ . '/audit-log-utils.php';
require_once __DIR__ . '/validation-utils.php';
require_once __DIR__ . '/person-name-utils.php';

/**
 * Normalize a spreadsheet row into import fields.
 */
function normalizeBulkExamineeImportRow(array $row): array
{
    $get = static function (array $source, array $keys): string {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }
            $value = trim((string)$source[$key]);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    };

    $parts = resolveImportNameParts($row);

    return [
        'firstname' => $parts['firstname'],
        'middlename' => $parts['middlename'] ?? '',
        'lastname' => $parts['lastname'],
        'fullname' => formatPersonName($parts['firstname'], $parts['middlename'], $parts['lastname']),
        'badge_number' => normalizeBadgeNumber($get($row, [
            'Badge_Number', 'Badge Number', 'badge_number', 'Badge No', 'Badge No.',
            'Academic_Number', 'Academic Number', 'academic_number', 'Academic No', 'Academic No.',
        ])),
        'email' => $get($row, ['Email', 'email']),
        'personnel_rank' => normalizePersonnelRank($get($row, ['Personnel_Rank', 'Personnel Rank', 'personnel_rank', 'Rank'])),
    ];
}

function validateBulkExamineeName(array $normalized): ?string
{
    return validatePersonNameParts(
        $normalized['firstname'] ?? '',
        $normalized['lastname'] ?? '',
        $normalized['middlename'] ?? ''
    );
}

function userBatchTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'tbl_user_batch'");
        $exists = $stmt && $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

function getExamineeRoleId(PDO $pdo): ?int
{
    static $roleId = null;
    if ($roleId !== null) {
        return $roleId;
    }

    $stmt = $pdo->prepare('SELECT Role_ID FROM tbl_role WHERE Role_Name = ? LIMIT 1');
    $stmt->execute(['Examinee']);
    $value = $stmt->fetchColumn();

    $roleId = $value ? (int)$value : null;
    return $roleId;
}

function findUserIdByBadgeNumber(PDO $pdo, ?string $badgeNumber): ?int
{
    $normalized = normalizeBadgeNumber($badgeNumber);
    if ($normalized === null) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT User_ID FROM tbl_user WHERE Badge_Number = ? LIMIT 1');
    $stmt->execute([$normalized]);
    $id = $stmt->fetchColumn();

    return $id ? (int)$id : null;
}

function emailExists(PDO $pdo, ?string $email, ?int $excludeUserId = null): bool
{
    if ($email === null || $email === '' || isExamineePlaceholderEmail($email)) {
        return false;
    }

    $sql = 'SELECT User_ID FROM tbl_user WHERE Email = ?';
    $params = [$email];
    if ($excludeUserId > 0) {
        $sql .= ' AND User_ID != ?';
        $params[] = $excludeUserId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (bool)$stmt->fetch();
}

function userAssignedToSection(PDO $pdo, int $userId, int $sectionId): bool
{
    if (!userBatchTableExists($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare('
        SELECT 1
        FROM tbl_user_batch ub
        INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
        WHERE ub.User_ID = ? AND b.Section_ID = ?
        LIMIT 1
    ');
    $stmt->execute([$userId, $sectionId]);

    return (bool)$stmt->fetch();
}

function countImportRowsNeedingSectionSlot(array $rows): int
{
    $count = 0;
    foreach ($rows as $row) {
        if (($row['status'] ?? '') === 'error') {
            continue;
        }
        if (($row['action'] ?? '') === 'create_and_assign' || ($row['action'] ?? '') === 'assign_only') {
            $count++;
        }
    }

    return $count;
}

/**
 * Preview bulk examinee import rows for a target section.
 */
function previewBulkExamineeImport(PDO $pdo, array $rows, int $sectionId): array
{
    if ($sectionId <= 0) {
        return [
            'success' => false,
            'message' => 'Section ID is required',
        ];
    }

    if (!userBatchTableExists($pdo)) {
        return [
            'success' => false,
            'message' => 'Enrollment table not available. Please run the database migration.',
        ];
    }

    $sectionStmt = $pdo->prepare('SELECT Section_ID, Section_Name, Capacity FROM tbl_academic_section WHERE Section_ID = ? LIMIT 1');
    $sectionStmt->execute([$sectionId]);
    $section = $sectionStmt->fetch(PDO::FETCH_ASSOC);
    if (!$section) {
        return [
            'success' => false,
            'message' => 'Section not found',
        ];
    }

    $enrollmentBatchId = getEnrollmentBatchId($pdo, $sectionId);
    if (!$enrollmentBatchId) {
        return [
            'success' => false,
            'message' => 'Unable to resolve enrollment batch for section',
        ];
    }

    $examineeRoleId = getExamineeRoleId($pdo);
    if (!$examineeRoleId) {
        return [
            'success' => false,
            'message' => 'Trainee role not found',
        ];
    }

    $seenBadge = [];
    $reservedBadge = [];
    $previewRows = [];

    foreach ($rows as $index => $rawRow) {
        if (!is_array($rawRow)) {
            continue;
        }

        $rowNumber = (int)($rawRow['_row'] ?? ($index + 2));
        $normalized = normalizeBulkExamineeImportRow($rawRow);
        $errors = [];
        $warnings = [];
        $action = 'create_and_assign';
        $status = 'ready';
        $existingUserId = null;

        $nameError = validateBulkExamineeName($normalized);
        if ($nameError !== null) {
            $errors[] = $nameError;
        }

        $badgeError = validateBadgeNumberFormat($normalized['badge_number']);
        if ($badgeError !== null) {
            $errors[] = $badgeError;
        }

        if ($normalized['badge_number'] !== null && empty($errors)) {
            $badgeKey = $normalized['badge_number'];
            if (isset($seenBadge[$badgeKey])) {
                $errors[] = 'Duplicate badge number in file (row ' . $seenBadge[$badgeKey] . ')';
            } else {
                $seenBadge[$badgeKey] = $rowNumber;
            }
        }

        if ($normalized['email'] !== '' && !filter_var($normalized['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        $rankError = validatePersonnelRank($normalized['personnel_rank']);
        if ($rankError !== null) {
            $errors[] = $rankError;
        }

        if (empty($errors) && $normalized['badge_number'] !== null) {
            $existingUserId = findUserIdByBadgeNumber($pdo, $normalized['badge_number']);
            if ($existingUserId) {
                $roleStmt = $pdo->prepare('
                    SELECT r.Role_Name
                    FROM tbl_user u
                    INNER JOIN tbl_role r ON u.Role_ID = r.Role_ID
                    WHERE u.User_ID = ?
                    LIMIT 1
                ');
                $roleStmt->execute([$existingUserId]);
                $existingRole = (string)($roleStmt->fetchColumn() ?: '');

                if ($existingRole !== 'Examinee') {
                    $errors[] = 'Badge number belongs to a non-trainee account';
                } else {
                    $action = 'assign_only';
                    $warnings[] = 'This trainee is already in the system; will assign to section only';
                    if ($normalized['personnel_rank'] !== null) {
                        $warnings[] = 'Personnel rank will be updated';
                    }

                    if (emailExists($pdo, $normalized['email'], $existingUserId)) {
                        $errors[] = 'Email already belongs to another account';
                    }
                }
            } else {
                if (emailExists($pdo, $normalized['email'])) {
                    $errors[] = 'Email already registered.';
                }
            }
        }

        if (empty($errors) && $existingUserId && userAssignedToSection($pdo, $existingUserId, $sectionId)) {
            $action = 'skip';
            $status = 'skip';
            $warnings[] = 'Already assigned to this section — will skip';
        } elseif (empty($errors) && $existingUserId) {
            $activeEnrollment = getExamineeActiveEnrollment($pdo, $existingUserId);
            if ($activeEnrollment !== null) {
                $errors[] = 'Already assigned to ' . formatExamineeEnrollmentLabel($activeEnrollment)
                    . '. Remove from that assignment first.';
                $status = 'error';
                $action = 'none';
            }
        }

        if (!empty($errors)) {
            $status = 'error';
            $action = 'none';
        }

        if (empty($errors) && $action === 'create_and_assign' && $normalized['badge_number'] !== null) {
            $reservedBadge[] = $normalized['badge_number'];
        }

        $previewRows[] = [
            'row' => $rowNumber,
            'firstname' => $normalized['firstname'],
            'middlename' => $normalized['middlename'],
            'lastname' => $normalized['lastname'],
            'fullname' => $normalized['fullname'],
            'badge_number' => $normalized['badge_number'],
            'email' => $normalized['email'],
            'personnel_rank' => $normalized['personnel_rank'],
            'existing_user_id' => $existingUserId,
            'status' => $status,
            'action' => $action,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    $assignedStmt = $pdo->prepare('
        SELECT COUNT(DISTINCT ub.User_ID) AS assigned_count
        FROM tbl_user_batch ub
        INNER JOIN tbl_batch b ON ub.Batch_ID = b.Batch_ID
        WHERE b.Section_ID = ?
    ');
    $assignedStmt->execute([$sectionId]);
    $assignedCount = (int)($assignedStmt->fetch(PDO::FETCH_ASSOC)['assigned_count'] ?? 0);
    $capacity = (int)$section['Capacity'];
    $newAssignments = countImportRowsNeedingSectionSlot($previewRows);
    $availableSlots = max(0, $capacity - $assignedCount);
    $capacityExceeded = $newAssignments > $availableSlots;

    $readyCount = 0;
    $skipCount = 0;
    $errorCount = 0;
    foreach ($previewRows as $previewRow) {
        if ($previewRow['status'] === 'error') {
            $errorCount++;
        } elseif ($previewRow['status'] === 'skip') {
            $skipCount++;
        } else {
            $readyCount++;
        }
    }

    return [
        'success' => true,
        'rows' => $previewRows,
        'summary' => [
            'total' => count($previewRows),
            'ready' => $readyCount,
            'skip' => $skipCount,
            'error' => $errorCount,
            'new_assignments' => $newAssignments,
        ],
        'capacity' => [
            'section_id' => (int)$section['Section_ID'],
            'section_name' => $section['Section_Name'],
            'capacity' => $capacity,
            'assigned_count' => $assignedCount,
            'available_slots' => $availableSlots,
            'exceeded' => $capacityExceeded,
        ],
        'enrollment_batch_id' => $enrollmentBatchId,
        'examinee_role_id' => $examineeRoleId,
    ];
}

function createExamineeFromImportRow(PDO $pdo, array $row, int $examineeRoleId): array
{
    $firstname = trim((string)($row['firstname'] ?? ''));
    $lastname = trim((string)($row['lastname'] ?? ''));
    $middlename = normalizeMiddleName($row['middlename'] ?? '');
    $badgeNumber = normalizeBadgeNumber($row['badge_number'] ?? null);
    $email = resolveStoredEmail('Examinee', $row['email'] ?? '', $badgeNumber);
    $personnelRank = !empty($row['personnel_rank']) ? $row['personnel_rank'] : null;
    $passwordHash = password_hash(DEFAULT_EXAMINEE_PASSWORD, PASSWORD_BCRYPT);
    $hasRank = personnelRankColumnExists($pdo);

    if ($hasRank) {
        $stmt = $pdo->prepare('INSERT INTO tbl_user (Role_ID, Firstname, Middlename, Lastname, Email, Password_Hash, Must_Change_Password, Badge_Number, Personnel_Rank, Date_Created, Status) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, NOW(), ?)');
        $stmt->execute([$examineeRoleId, $firstname, $middlename, $lastname, $email, $passwordHash, $badgeNumber, $personnelRank, 'Active']);
    } else {
        $stmt = $pdo->prepare('INSERT INTO tbl_user (Role_ID, Firstname, Middlename, Lastname, Email, Password_Hash, Must_Change_Password, Badge_Number, Date_Created, Status) VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW(), ?)');
        $stmt->execute([$examineeRoleId, $firstname, $middlename, $lastname, $email, $passwordHash, $badgeNumber, 'Active']);
    }

    return [
        'user_id' => (int)$pdo->lastInsertId(),
        'fullname' => formatPersonName($firstname, $middlename, $lastname),
    ];
}

function updateExamineeRankFromImport(PDO $pdo, int $userId, ?string $personnelRank): void
{
    if (!personnelRankColumnExists($pdo) || $personnelRank === null) {
        return;
    }

    $stmt = $pdo->prepare('UPDATE tbl_user SET Personnel_Rank = ? WHERE User_ID = ?');
    $stmt->execute([$personnelRank, $userId]);
}

function assignImportUserToSection(PDO $pdo, int $userId, int $enrollmentBatchId): bool
{
    $singleEnrollmentError = validateExamineeSingleEnrollment($pdo, $userId, $enrollmentBatchId);
    if ($singleEnrollmentError !== null) {
        throw new RuntimeException($singleEnrollmentError);
    }

    $existing = $pdo->prepare('SELECT User_Batch_ID, Status FROM tbl_user_batch WHERE User_ID = ? AND Batch_ID = ? LIMIT 1');
    $existing->execute([$userId, $enrollmentBatchId]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if (($row['Status'] ?? '') === 'Active') {
            return false;
        }

        $upd = $pdo->prepare('UPDATE tbl_user_batch SET Status = ?, Date_Enrolled = NOW() WHERE User_Batch_ID = ?');
        $upd->execute(['Active', (int)$row['User_Batch_ID']]);
        return true;
    }

    $stmt = $pdo->prepare('INSERT INTO tbl_user_batch (User_ID, Batch_ID, Status, Date_Enrolled) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$userId, $enrollmentBatchId, 'Active']);

    return true;
}

/**
 * Commit bulk examinee import for rows validated by preview.
 */
function commitBulkExamineeImport(PDO $pdo, array $rows, int $sectionId): array
{
    $preview = previewBulkExamineeImport($pdo, $rows, $sectionId);
    if (empty($preview['success'])) {
        return $preview;
    }

    if (!empty($preview['capacity']['exceeded'])) {
        return [
            'success' => false,
            'message' => 'Section capacity exceeded. Maximum: ' . $preview['capacity']['capacity']
                . ', Currently assigned: ' . $preview['capacity']['assigned_count']
                . ', Available: ' . $preview['capacity']['available_slots']
                . ', Import needs: ' . $preview['summary']['new_assignments'],
            'capacity' => $preview['capacity'],
        ];
    }

    $commitRows = array_values(array_filter($preview['rows'], static function (array $row): bool {
        return in_array($row['action'], ['create_and_assign', 'assign_only'], true);
    }));

    if (empty($commitRows)) {
        return [
            'success' => false,
            'message' => 'No valid rows to import',
            'summary' => $preview['summary'],
        ];
    }

    $examineeRoleId = (int)$preview['examinee_role_id'];
    $enrollmentBatchId = (int)$preview['enrollment_batch_id'];

    $created = 0;
    $assigned = 0;
    $skipped = (int)$preview['summary']['skip'];
    $failed = 0;
    $errors = [];

    $pdo->beginTransaction();

    try {
        foreach ($commitRows as $row) {
            try {
                $userId = (int)($row['existing_user_id'] ?? 0);

                if ($row['action'] === 'create_and_assign') {
                    $createdUser = createExamineeFromImportRow($pdo, $row, $examineeRoleId);
                    $userId = $createdUser['user_id'];
                    $created++;
                    auditFromSession($pdo, 'USER', 'CREATE_USER', 'Bulk import created user ' . $createdUser['fullname'], 'SUCCESS', 'user', $userId);
                } elseif ($row['action'] === 'assign_only') {
                    updateExamineeRankFromImport($pdo, $userId, $row['personnel_rank'] ?? null);
                }

                if ($userId <= 0) {
                    throw new RuntimeException('Unable to resolve user for row ' . $row['row']);
                }

                if (assignImportUserToSection($pdo, $userId, $enrollmentBatchId)) {
                    $assigned++;
                    $userLabel = auditUserLabel($pdo, $userId);
                    $batchLabel = auditBatchLabel($pdo, $enrollmentBatchId);
                    auditFromSession($pdo, 'BATCH', 'ASSIGN_USER_BATCH', "{$userLabel} assigned to batch {$batchLabel} (bulk import)", 'SUCCESS', 'batch', $enrollmentBatchId);
                }
            } catch (RuntimeException $e) {
                $failed++;
                $errors[] = [
                    'row' => $row['row'],
                    'badge_number' => $row['badge_number'],
                    'message' => $e->getMessage(),
                ];
            } catch (Throwable $e) {
                error_log('Bulk import row error: ' . $e->getMessage());
                $failed++;
                $errors[] = [
                    'row' => $row['row'],
                    'badge_number' => $row['badge_number'],
                    'message' => 'An unexpected error occurred. Please try again later.',
                ];
            }
        }

        if ($created === 0 && $assigned === 0 && $failed > 0) {
            $pdo->rollBack();
            return [
                'success' => false,
                'message' => $errors[0]['message'] ?? 'An unexpected error occurred. Please try again later.',
                'created' => 0,
                'assigned' => 0,
                'skipped' => $skipped,
                'failed' => $failed,
                'errors' => $errors,
            ];
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Bulk import failed: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An unexpected error occurred. Please try again later.',
        ];
    }

    return [
        'success' => true,
        'message' => "Import complete: {$created} created, {$assigned} assigned, {$skipped} skipped, {$failed} failed",
        'created' => $created,
        'assigned' => $assigned,
        'skipped' => $skipped,
        'failed' => $failed,
        'errors' => $errors,
        'summary' => $preview['summary'],
        'capacity' => $preview['capacity'],
    ];
}
