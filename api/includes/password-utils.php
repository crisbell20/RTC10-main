<?php

const DEFAULT_EXAMINEE_PASSWORD = 'PNP@rtc10';

const PASSWORD_POLICY_MESSAGE = 'Password must be at least 8 characters and include upper, lower, number, and special character';

/**
 * Validate password against the system policy.
 * Returns null if valid, or an error message string.
 */
function validatePasswordPolicy(string $password): ?string
{
    if (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/\d/', $password) ||
        !preg_match('/[^A-Za-z0-9]/', $password)
    ) {
        return PASSWORD_POLICY_MESSAGE;
    }

    return null;
}
