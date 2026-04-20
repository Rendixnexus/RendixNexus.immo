<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// 🔒 Session leeren
$_SESSION = [];

// 🔒 Session Cookie löschen (WICHTIG)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 🔒 Session zerstören
session_destroy();

echo json_encode([
    'success' => true,
    'message' => 'Logout erfolgreich'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);