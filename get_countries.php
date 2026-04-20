<?php
declare(strict_types=1);

require_once '../db.php';
require_once 'auth_middleware.php';
require_once 'security.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

try {

    // =========================
    // LOGIN CHECK (aus Middleware)
    // =========================
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Nicht eingeloggt'
        ]);
        exit;
    }

    $role = $_SESSION['role'] ?? 'user';

    // =========================
    // DB QUERY
    // =========================
    if ($role === 'admin') {
        $stmt = $pdo->prepare("
            SELECT id, name, iso_code, currency_code, language_code, timezone
            FROM countries
            ORDER BY name ASC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT id, name, iso_code, currency_code, language_code, timezone
            FROM countries
            WHERE active = 1
            ORDER BY name ASC
        ");
    }

    $stmt->execute();
    $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count' => count($countries),
        'countries' => $countries
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {

    error_log('Get Countries Error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Serverfehler'
    ]);
}