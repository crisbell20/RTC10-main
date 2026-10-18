<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This migration must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../../api/includes/person-name-utils.php';

$checkOnly = in_array('--check', $argv, true);
$dumpPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db_rtc10.sql';

function sqlStringLiteral(?string $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
}

/**
 * @return list<string|null>
 */
function parseSqlValueList(string $tuple): array
{
    $values = [];
    $length = strlen($tuple);
    $i = 0;

    while ($i < $length) {
        while ($i < $length && ctype_space($tuple[$i])) {
            $i++;
        }
        if ($i >= $length) {
            break;
        }

        if (substr($tuple, $i, 4) === 'NULL' && ($i + 4 >= $length || $tuple[$i + 4] === ',')) {
            $values[] = null;
            $i += 4;
        } elseif ($tuple[$i] === "'") {
            $i++;
            $buffer = '';
            while ($i < $length) {
                if ($tuple[$i] === '\\' && $i + 1 < $length) {
                    $buffer .= $tuple[$i + 1];
                    $i += 2;
                    continue;
                }
                if ($tuple[$i] === "'") {
                    if ($i + 1 < $length && $tuple[$i + 1] === "'") {
                        $buffer .= "'";
                        $i += 2;
                        continue;
                    }
                    $i++;
                    break;
                }
                $buffer .= $tuple[$i];
                $i++;
            }
            $values[] = $buffer;
        } else {
            $start = $i;
            while ($i < $length && $tuple[$i] !== ',') {
                $i++;
            }
            $values[] = trim(substr($tuple, $start, $i - $start));
        }

        while ($i < $length && ctype_space($tuple[$i])) {
            $i++;
        }
        if ($i < $length && $tuple[$i] === ',') {
            $i++;
        }
    }

    return $values;
}

/**
 * @return list<string>
 */
function extractSqlTuples(string $sql): array
{
    $tuples = [];
    $length = strlen($sql);
    $i = 0;
    while ($i < $length) {
        if ($sql[$i] !== '(') {
            $i++;
            continue;
        }
        $depth = 0;
        $start = $i;
        $inString = false;
        while ($i < $length) {
            $char = $sql[$i];
            if ($inString) {
                if ($char === '\\' && $i + 1 < $length) {
                    $i += 2;
                    continue;
                }
                if ($char === "'") {
                    if ($i + 1 < $length && $sql[$i + 1] === "'") {
                        $i += 2;
                        continue;
                    }
                    $inString = false;
                }
                $i++;
                continue;
            }
            if ($char === "'") {
                $inString = true;
                $i++;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $tuples[] = substr($sql, $start + 1, $i - $start - 1);
                    $i++;
                    break;
                }
            }
            $i++;
        }
    }

    return $tuples;
}

/**
 * @return array{ok:bool,checked:int,messages:list<string>}
 */
function checkDumpNames(string $dumpPath): array
{
    $dump = file_get_contents($dumpPath);
    if ($dump === false) {
        return ['ok' => false, 'checked' => 0, 'messages' => ['Unable to read ' . $dumpPath]];
    }

    $insertAt = strpos($dump, 'INSERT INTO `tbl_user`');
    $start = $insertAt === false ? false : strpos($dump, 'VALUES', $insertAt);
    $end = $start === false ? false : strpos($dump, ";\n", $start);
    if ($start === false || $end === false) {
        return ['ok' => false, 'checked' => 0, 'messages' => ['tbl_user insert was not found in the dump']];
    }

    $block = substr($dump, $start, $end - $start);
    $tuples = extractSqlTuples($block);
    $messages = [];
    $checked = 0;
    $alreadySplit = strpos($dump, 'INSERT INTO `tbl_user` (`User_ID`, `Role_ID`, `Firstname`') !== false;

    foreach ($tuples as $tuple) {
        $values = parseSqlValueList($tuple);
        $checked++;
        if ($alreadySplit) {
            if (count($values) !== 14) {
                $messages[] = 'Unexpected split user row';
                continue;
            }
            continue;
        }
        if (count($values) < 3 || !is_string($values[2])) {
            $messages[] = 'Could not read a name from a user row';
            continue;
        }
        $original = $values[2];
        $parts = splitStoredFullname($original);
        $display = formatPersonName($parts['firstname'], $parts['middlename'], $parts['lastname']);
        if ($display !== trim($original)) {
            $messages[] = 'Display would change: ' . $original . ' => ' . $display;
        }
        foreach (['firstname' => $parts['firstname'], 'lastname' => $parts['lastname'], 'middlename' => (string)$parts['middlename']] as $label => $part) {
            if (personNameLength($part) > 100) {
                $messages[] = $label . ' longer than 100 characters: ' . $original;
            }
        }
    }

    return ['ok' => $messages === [], 'checked' => $checked, 'messages' => $messages];
}

function rewriteDump(string $dumpPath): int
{
    $dump = file_get_contents($dumpPath);
    if ($dump === false) {
        throw new RuntimeException('Unable to read the SQL dump');
    }

    $createFrom = "  `Role_ID` int(11) NOT NULL,\n  `Fullname` varchar(200) NOT NULL,\n  `Email` varchar(200) NOT NULL,";
    $createTo = "  `Role_ID` int(11) NOT NULL,\n  `Firstname` varchar(100) NOT NULL,\n  `Middlename` varchar(100) DEFAULT NULL,\n  `Lastname` varchar(100) NOT NULL,\n  `Email` varchar(200) NOT NULL,";
    if (strpos($dump, $createFrom) === false) {
        if (strpos($dump, '`Firstname` varchar(100) NOT NULL') !== false) {
            return 0;
        }
        throw new RuntimeException('tbl_user.Fullname column was not found in the dump');
    }
    $dump = str_replace($createFrom, $createTo, $dump);

    $insertFrom = 'INSERT INTO `tbl_user` (`User_ID`, `Role_ID`, `Fullname`, `Email`, `Password_Hash`, `Badge_Number`, `Personnel_Rank`, `Must_Change_Password`, `Date_Created`, `Status`, `Is_Archived`, `Archived_At`) VALUES';
    $insertTo = 'INSERT INTO `tbl_user` (`User_ID`, `Role_ID`, `Firstname`, `Middlename`, `Lastname`, `Email`, `Password_Hash`, `Badge_Number`, `Personnel_Rank`, `Must_Change_Password`, `Date_Created`, `Status`, `Is_Archived`, `Archived_At`) VALUES';
    if (strpos($dump, $insertFrom) === false) {
        throw new RuntimeException('tbl_user insert column list was not found');
    }

    $start = strpos($dump, $insertFrom);
    $valuesAt = $start === false ? false : strpos($dump, 'VALUES', $start);
    $end = $valuesAt === false ? false : strpos($dump, ";\n", $valuesAt);
    $start = $valuesAt === false ? false : $valuesAt;
    if ($end === false) {
        throw new RuntimeException('tbl_user insert did not end');
    }

    $block = substr($dump, $start, $end - $start);
    $tuples = extractSqlTuples($block);
    $rebuilt = [];
    foreach ($tuples as $tuple) {
        $values = parseSqlValueList($tuple);
        if (count($values) !== 12 || !is_string($values[2])) {
            throw new RuntimeException('Unexpected tbl_user row shape while rewriting the dump');
        }
        $parts = splitStoredFullname($values[2]);
        $display = formatPersonName($parts['firstname'], $parts['middlename'], $parts['lastname']);
        if ($display !== trim($values[2])) {
            throw new RuntimeException('Refusing to rewrite dump; display would change for ' . $values[2]);
        }
        $encoded = [];
        foreach ($values as $index => $value) {
            if ($index === 2) {
                $encoded[] = sqlStringLiteral($parts['firstname']);
                $encoded[] = sqlStringLiteral($parts['middlename']);
                $encoded[] = sqlStringLiteral($parts['lastname']);
                continue;
            }
            if ($value === null) {
                $encoded[] = 'NULL';
            } elseif (is_string($value) && !preg_match('/^-?\d+$/', $value)) {
                $encoded[] = sqlStringLiteral($value);
            } elseif (is_string($value) && ($index === 0 || $index === 1 || $index === 7 || $index === 10)) {
                $encoded[] = $value;
            } else {
                $encoded[] = sqlStringLiteral((string)$value);
            }
        }
        $line = '(' . implode(', ', $encoded) . ')';
        $check = parseSqlValueList(substr($line, 1, -1));
        $expected = [
            $values[0], $values[1], $parts['firstname'], $parts['middlename'], $parts['lastname'],
            $values[3], $values[4], $values[5], $values[6], $values[7], $values[8], $values[9], $values[10], $values[11],
        ];
        if ($check !== $expected) {
            throw new RuntimeException('Dump rewrite changed a non-name field for user ' . $values[0]);
        }
        $rebuilt[] = $line;
    }

    $newBlock = $insertTo . "\n" . implode(",\n", $rebuilt);
    $dump = substr($dump, 0, $start) . $newBlock . substr($dump, $end);
    if (file_put_contents($dumpPath, $dump) === false) {
        throw new RuntimeException('Unable to write the SQL dump');
    }

    return count($rebuilt);
}

function columnExists(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare('SHOW COLUMNS FROM `tbl_user` LIKE ?');
    $stmt->execute([$column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

$dumpCheck = checkDumpNames($dumpPath);
echo 'Dump names checked: ' . $dumpCheck['checked'] . PHP_EOL;
if (!$dumpCheck['ok']) {
    fwrite(STDERR, implode(PHP_EOL, $dumpCheck['messages']) . PHP_EOL);
    exit(1);
}
echo "Dump names round-trip.\n";

if ($checkOnly) {
    exit(0);
}

require_once __DIR__ . '/../../api/config/connection-pdo.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "Database connection was not created.\n");
    exit(1);
}

try {
    $hasFullname = columnExists($pdo, 'Fullname');
    $hasFirstname = columnExists($pdo, 'Firstname');

    if (!$hasFullname && $hasFirstname) {
        echo "Database already uses separate name columns.\n";
    } elseif (!$hasFullname) {
        throw new RuntimeException('tbl_user has neither Fullname nor Firstname');
    } else {
        if (!$hasFirstname) {
            $pdo->exec("
                ALTER TABLE `tbl_user`
                  ADD COLUMN `Firstname` varchar(100) NOT NULL DEFAULT '' AFTER `Role_ID`,
                  ADD COLUMN `Middlename` varchar(100) DEFAULT NULL AFTER `Firstname`,
                  ADD COLUMN `Lastname` varchar(100) NOT NULL DEFAULT '' AFTER `Middlename`
            ");
        }

        $rows = $pdo->query('SELECT `User_ID`, `Fullname` FROM `tbl_user`')->fetchAll(PDO::FETCH_ASSOC);
        $update = $pdo->prepare('UPDATE `tbl_user` SET `Firstname` = ?, `Middlename` = ?, `Lastname` = ? WHERE `User_ID` = ?');
        foreach ($rows as $row) {
            $original = trim((string)$row['Fullname']);
            $parts = splitStoredFullname($original);
            $display = formatPersonName($parts['firstname'], $parts['middlename'], $parts['lastname']);
            if ($display !== $original) {
                throw new RuntimeException('Refusing to drop Fullname; display would change for user ' . $row['User_ID']);
            }
            $update->execute([
                $parts['firstname'],
                $parts['middlename'],
                $parts['lastname'],
                (int)$row['User_ID'],
            ]);
        }

        $nameSql = personNameSql();
        $verify = $pdo->query('SELECT `User_ID`, `Fullname`, ' . $nameSql . ' AS display_name FROM `tbl_user`');
        foreach ($verify as $row) {
            if (trim((string)$row['Fullname']) !== (string)$row['display_name']) {
                throw new RuntimeException('Database display check failed for user ' . $row['User_ID']);
            }
        }

        $pdo->exec('ALTER TABLE `tbl_user` DROP COLUMN `Fullname`');
        echo 'Database migrated: ' . count($rows) . " users.\n";
    }

    $rewritten = rewriteDump($dumpPath);
    if ($rewritten === 0) {
        echo "SQL dump already uses separate name columns.\n";
    } else {
        echo 'SQL dump rewritten: ' . $rewritten . " users.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
