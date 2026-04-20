<?php
declare(strict_types=1);

/**
 * STRING SANITIZER
 */
function sanitize_string(string $input, int $maxLength = 5000): string {
    $input = trim($input);
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return mb_substr($input, 0, $maxLength);
}

/**
 * NUMBER SAFE (kein Exception Crash!)
 */
function sanitize_number($input): float {
    if (!is_numeric($input)) {
        return 0.0; // safe fallback
    }
    return (float)$input;
}

/**
 * INT SAFE
 */
function sanitize_int($input): int {
    if (!is_numeric($input)) {
        return 0;
    }
    return (int)$input;
}

/**
 * EMAIL SAFE
 */
function sanitize_email(string $email): ?string {
    $email = trim($email);

    $email = filter_var($email, FILTER_VALIDATE_EMAIL);

    if (!$email) {
        return null; // safe instead of crash
    }

    return $email;
}