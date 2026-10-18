<?php

function personNameLength(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}

function normalizeMiddleName(?string $middlename): ?string
{
    $middlename = trim((string)$middlename);
    return $middlename === '' ? null : $middlename;
}

/**
 * @return array{firstname:string,middlename:?string,lastname:string}
 */
function splitStoredFullname(string $fullname): array
{
    $fullname = trim($fullname);
    if ($fullname === '') {
        return ['firstname' => '', 'middlename' => null, 'lastname' => ''];
    }

    $commaPos = strpos($fullname, ',');
    if ($commaPos === false) {
        return ['firstname' => $fullname, 'middlename' => null, 'lastname' => ''];
    }

    $lastname = trim(substr($fullname, 0, $commaPos));
    $rest = trim(substr($fullname, $commaPos + 1));
    if ($rest === '') {
        return ['firstname' => '', 'middlename' => null, 'lastname' => $lastname];
    }

    $spacePos = strpos($rest, ' ');
    if ($spacePos === false) {
        return ['firstname' => $rest, 'middlename' => null, 'lastname' => $lastname];
    }

    $firstname = trim(substr($rest, 0, $spacePos));
    $middlename = trim(substr($rest, $spacePos + 1));

    return [
        'firstname' => $firstname,
        'middlename' => $middlename !== '' ? $middlename : null,
        'lastname' => $lastname,
    ];
}

function formatPersonName(?string $firstname, ?string $middlename, ?string $lastname): string
{
    $first = trim((string)$firstname);
    $middle = trim((string)$middlename);
    $last = trim((string)$lastname);
    $given = trim($first . ($middle !== '' ? ' ' . $middle : ''));

    if ($last === '') {
        return $given;
    }
    if ($given === '') {
        return $last;
    }

    return $last . ', ' . $given;
}

function personNameSql(string $alias = ''): string
{
    if ($alias !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) !== 1) {
        throw new InvalidArgumentException('Invalid name column alias');
    }

    $prefix = $alias !== '' ? '`' . $alias . '`.' : '';
    $first = 'TRIM(' . $prefix . '`Firstname`)';
    $middle = 'TRIM(IFNULL(' . $prefix . '`Middlename`, \'\'))';
    $last = 'TRIM(' . $prefix . '`Lastname`)';
    $given = 'TRIM(CONCAT(' . $first . ', IF(' . $middle . ' = \'\', \'\', CONCAT(\' \', ' . $middle . '))))';

    return '(CASE WHEN ' . $last . ' = \'\' THEN ' . $given
        . ' WHEN ' . $given . ' = \'\' THEN ' . $last
        . ' ELSE CONCAT(' . $last . ', \', \', ' . $given . ') END)';
}

function personNameCharacterError(string $value, string $label): ?string
{
    if (!preg_match('/^[\p{L}\s.,\-]+$/u', $value)) {
        return $label . ' must contain only letters, spaces, dots, commas, and hyphens';
    }
    if (!preg_match('/\p{L}/u', $value)) {
        return $label . ' must contain at least one letter';
    }
    if (personNameLength($value) > 100) {
        return $label . ' must be at most 100 characters';
    }

    return null;
}

function validatePersonNameParts(?string $firstname, ?string $lastname, ?string $middlename): ?string
{
    $firstname = trim((string)$firstname);
    $lastname = trim((string)$lastname);
    $middlename = trim((string)$middlename);

    if ($firstname === '') {
        return 'First name is required';
    }
    if ($lastname === '') {
        return 'Last name is required';
    }
    if (personNameLength($firstname) < 2) {
        return 'First name must be at least 2 characters';
    }
    if (personNameLength($lastname) < 2) {
        return 'Last name must be at least 2 characters';
    }

    $firstError = personNameCharacterError($firstname, 'First name');
    if ($firstError !== null) {
        return $firstError;
    }

    $lastError = personNameCharacterError($lastname, 'Last name');
    if ($lastError !== null) {
        return $lastError;
    }

    if ($middlename === '') {
        return null;
    }
    if (personNameLength($middlename) < 1) {
        return 'Middle name must contain at least one letter';
    }

    return personNameCharacterError($middlename, 'Middle name');
}

/**
 * @param array<string,mixed> $row
 * @return array{firstname:string,middlename:?string,lastname:string}
 */
function resolveImportNameParts(array $row): array
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

    $firstname = $get($row, ['Firstname', 'First Name', 'first_name', 'firstname']);
    $lastname = $get($row, ['Lastname', 'Last Name', 'last_name', 'lastname']);
    $middlename = $get($row, ['Middlename', 'Middle Name', 'middle_name', 'middlename', 'Middle']);
    $legacy = $get($row, ['Fullname', 'Full Name', 'full_name', 'fullname', 'Name']);

    if ($firstname === '' && $lastname === '' && $middlename === '' && $legacy !== '') {
        return splitStoredFullname($legacy);
    }

    return [
        'firstname' => $firstname,
        'middlename' => normalizeMiddleName($middlename),
        'lastname' => $lastname,
    ];
}
