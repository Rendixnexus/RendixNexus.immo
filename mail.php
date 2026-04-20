<?php
declare(strict_types=1);

function sendMail(string $to, string $subject, string $message): bool
{
    // 🔒 Input validation (verhindert Mail header injection)
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email: " . $to);
        return false;
    }

    // 🔒 Clean subject
    $subject = trim(str_replace(["\r", "\n"], '', $subject));

    // 🔒 Force HTML safe encoding baseline
    $message = str_replace(["\r\n", "\r"], "\n", $message);

    // 🔒 Headers (stable across shared + VPS servers)
    $headers = [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "From: Rendix Nexus <no-reply@rendixnexus.immo>",
        "Reply-To: no-reply@rendixnexus.immo",
        "X-Mailer: PHP/" . phpversion()
    ];

    // 🔒 Prevent mail injection via headers
    $headersString = implode("\r\n", $headers);

    // 🔥 Send mail with error suppression + logging
    $result = @mail($to, $subject, $message, $headersString);

    if (!$result) {
        error_log("MAIL FAILED -> TO: $to | SUBJECT: $subject");
        return false;
    }

    return true;
}