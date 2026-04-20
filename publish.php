<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0'); 
ini_set('log_errors', '1');
error_reporting(E_ALL);

session_start();

try {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/security.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server initialisation failed'
    ]);
    exit;
}