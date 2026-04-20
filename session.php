<?php
declare(strict_types=1);

session_start();

require_once 'db.php';
require_once 'security.php';

// 🔒 CSRF nur prüfen wenn vorhanden (sonst kein Hard-Fail bei GET / falscher Call)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    verify_csrf();
}

// 🔒 Security Headers (minimal + stabil)
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

try {

    // 🔒 Session Check (safe fallback)
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Nicht eingeloggt',
            'listings' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];

    // 🔒 Role laden (nur wenn wirklich nötig)
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'User nicht gefunden',
            'listings' => []
        ]);
        exit;
    }

    $role = $user['role'] ?? 'user';

    // 🔒 Query
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

        $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {

        $stmt = $pdo->prepare("
            SELECT id, title, created_at
            FROM listings
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");

        $stmt->execute([$user_id]);
        $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 🔒 Safe Output (kein doppeltes htmlspecialchars Overkill)
    foreach ($listings as &$l) {
        $l['title'] = htmlspecialchars($l['title'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    echo json_encode([
        'success' => true,
        'listings' => $listings
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {

    http_response_code(500);

    error_log("Listings Error: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Serverfehler',
        'listings' => []
    ]);
}