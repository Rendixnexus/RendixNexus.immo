<?php
declare(strict_types=1);

session_start();

require_once 'security.php';
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

// 🔒 CSRF nur bei schreibenden Requests (WICHTIG FIX)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    verify_csrf();
}

// 🔒 Session check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode([
        'success' => false,
        'message' => 'Nicht eingeloggt'
    ]));
}

try {

    $user_id = (int) $_SESSION['user_id'];

    // 🔒 User Role holen
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        http_response_code(404);
        exit(json_encode([
            'success' => false,
            'message' => 'User nicht gefunden'
        ]));
    }

    $role = $user['role'];

    // 🔒 Listings laden
    if ($role === 'admin') {

        $stmt = $pdo->query("
            SELECT 
                l.id,
                l.title,
                l.created_at,
                u.firstname,
                u.lastname
            FROM listings l
            JOIN users u ON l.user_id = u.id
            ORDER BY l.created_at DESC
        ");

    } else {

        $stmt = $pdo->prepare("
            SELECT 
                id,
                title,
                created_at
            FROM listings
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");

        $stmt->execute([$user_id]);
    }

    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 🔒 XSS Protection (Output sanitizing)
    foreach ($listings as &$l) {
        $l['title'] = htmlspecialchars($l['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    echo json_encode([
        'success' => true,
        'count' => count($listings),
        'listings' => $listings
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {

    http_response_code(500);

    error_log('Listings Error: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Serverfehler'
    ]);
}