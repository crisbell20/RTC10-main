<?php

require_once __DIR__ . '/batch-enrollment-utils.php';
require_once __DIR__ . '/user-identity-utils.php';
require_once __DIR__ . '/password-utils.php';
require_once __DIR__ . '/exam-code-utils.php';
require_once __DIR__ . '/audit-log-utils.php';

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

    return [
        'fullname' => $get($row, ['Fullname', 'Full Name', 'full_name', 'fullname', 'Name']),
        'academic_number' => normalizeAcademicNumber($get($row, ['Academic_Number', 'Academic Number', 'academic_number', 'Academic No', 'Academic No.'])),
        'email' => $get($row, ['Email', 'email']),
        'personnel_rank' => normalizePersonnelRank($get($row, ['Personnel_Rank', 'Personnel Rank', 'personnel_rank', 'Rank'])),
    ];
}

function validateBulkExamineeFullname(?string $fullname): ?string
{
    $fullname = trim((string)$fullname);
    if ($fullname === '') {
        return 'Full name is required';
    }
    if (!preg_match("/^[a-zA-Z\s.\-']+$/", $fullname)) {
        return 'Full name must contain only letters, spaces, dots, hyphens, and apostrophes';
    }
    if (strlen($fullname) < 2 || strlen($fullname) > 100) {
        return 'Full name must be between 2 and 100 characters';
    }

    return null;
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

function findUserIdByAcademicNumber(PDO $pdo, ?string $academicNumber): ?int
{
    $normalized = normalizeAcademicNumber($academicNumber);
    if ($normalized === null) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT User_ID FROM tbl_user WHERE Academic_Number = ? LIMIT 1');
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
            'message' => 'Examinee role not found',
        ];
    }

    $seenAcademic = [];
    $reservedAcademic = [];
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

        $fullnameError = validateBulkExamineeFullname($normalized['fullname']);
        if ($fullnameError !== null) {
            $errors[] = $fullnameError;
        }

        if ($normalized['academic_number'] === null && empty($errors)) {
            $resolvedAcademic = resolveExamineeAcademicNumber($pdo, null, $reservedAcademic);
            if ($resolvedAcademic['error'] !== null) {
                $errors[] = $resolvedAcademic['error'];
            } else {
                $normalized['academic_number'] = $resolvedAcademic['number'];
                $warnings[] = 'Auto-assigned academic number';
            }
        } else {
            $academicError = validateAcademicNumberFormat($normalized['academic_number']);
            if ($academicError !== null) {
                $errors[] = $academicError;
            }
        }

        if ($normalized['academic_number'] !== null && empty($errors)) {
            $academicKey = $normalized['academic_number'];
            if (isset($seenAcademic[$academicKey])) {
                $errors[] = 'Duplicate academic number in file (row ' . $seenAcademic[$academicKey] . ')';
            } else {
                $seenAcademic[$academicKey] = $rowNumber;
            }
        }

        if ($normalized['email'] !== '' && !filter_var($normalized['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        $rankError = validatePersonnelRank($normalized['personnel_rank']);
        if ($rankError !== null) {
            $errors[] = $rankError;
        }

        if (empty($errors) && $normalized['academic_number'] !== null) {
            $existingUserId = findUserIdByAcademicNumber($pdo, $normalized['academic_number']);
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
                    $errors[] = 'Academic number belongs to a non-examinee account';
                } else {
                    $action = 'assign_only';
                    $warnings[] = 'This student is already in the system; will assign to section only';
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
            $warnings[] = 'Already enrolled in this section — will skip';
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

        if (empty($errors) && $action === 'create_and_assign' && $normalized['academic_number'] !== null) {
            $reservedAcademic[] = $normalized['academic_number'];
        }

        $previewRows[] = [
            'row' => $rowNumber,
            'fullname' => $normalized['fullname'],
            'academic_number' => $normalized['academic_number'],
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
    $fullname = trim((string)$row['fullname']);
    $academicNumber = normalizeAcademicNumber($row['academic_number'] ?? null);
    $email = resolveStoredEmail('Examinee', $row['email'] ?? '', $academicNumber);
    $personnelRank = !empty($row['personnel_rank']) ? $row['personnel_rank'] : null;
    $passwordHash = password_hash(DEFAULT_EXAMINEE_PASSWORD, PASSWORD_BCRYPT);
    $hasRank = personnelRankColumnExists($pdo);

    if ($hasRank) {
        $stmt = $pdo->prepare('INSERT INTO tbl_user (Role_ID, Fullname, Email, Password_Hash, Must_Change_Password, Academic_Number, Personnel_Rank, Date_Created, Status) VALUES (?, ?, ?, ?, 1, ?, ?, NOW(), ?)');
        $stmt->execute([$examineeRoleId, $fullname, $email, $passwordHash, $academicNumber, $personnelRank, 'Active']);
    } else {
        $stmt = $pdo->prepare('INSERT INTO tbl_user (Role_ID, Fullname, Email, Password_Hash, Must_Change_Password, Academic_Number, Date_Created, Status) VALUES (?, ?, ?, ?, 1, ?, NOW(), ?)');
        $stmt->execute([$examineeRoleId, $fullname, $email, $passwordHash, $academicNumber, 'Active']);
    }

    return [
        'user_id' => (int)$pdo->lastInsertId(),
        'fullname' => $fullname,
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

    $existing = $pdo->prepare('SELECT User_Batch_ID FROM tbl_user_batch WHERE User_ID = ? AND Batch_ID = ?');
    $existing->execute([$userId, $enrollmentBatchId]);
    if ($existing->fetch()) {
        return false;
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
            } catch (Throwable $e) {
                $failed++;
                $errors[] = [
                    'row' => $row['row'],
                    'academic_number' => $row['academic_number'],
                    'message' => $e->getMessage(),
                ];
            }
        }

        if ($created === 0 && $assigned === 0 && $failed > 0) {
            $pdo->rollBack();
            return [
                'success' => false,
                'message' => $errors[0]['message'] ?? 'Import failed',
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

        return [
            'success' => false,
            'message' => 'Import failed: ' . $e->getMessage(),
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
