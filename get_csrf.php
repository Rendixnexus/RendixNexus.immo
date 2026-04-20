<?php
declare(strict_types=1);
session_start();
require 'security.php';
verify_csrf();

// 🔒 Sicherheits-Header
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

// 🔒 CSRF-Token erzeugen, falls noch nicht vorhanden
if(!isset($_SESSION['csrf_token'])){
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo json_encode([
    'success' => true,
    'csrf_token' => $_SESSION['csrf_token']
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>