<?php
declare(strict_types=1);

// 🔒 BASIC SECURITY HEADERS
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// 🔥 CSP SAFE VERSION (REAL-WORLD COMPATIBLE)
header("Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline'; "
    . "style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data: https:; "
    . "connect-src 'self' https:; "
    . "font-src 'self' https:; "
    . "frame-ancestors 'none'; "
    . "form-action 'self'; "
    . "base-uri 'self';"
);

// 🔒 HSTS (ONLY HTTPS ENVIRONMENT)
if (!empty($_SERVER['HTTPS'])) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}