<?php
declare(strict_types=1);

require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

try {

    $token = trim($_GET['token'] ?? '');

    if ($token === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Token fehlt'
        ]);
        exit;
    }

    // 🔒 Token holen
    $stmt = $pdo->prepare("SELECT id FROM users WHERE verify_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Ungültiger Token'
        ]);
        exit;
    }

    // 🔒 Update
    $stmt = $pdo->prepare("
        UPDATE users 
        SET is_verified = 1,
            verify_token = NULL
        WHERE id = ?
    ");

    $stmt->execute([(int)$user['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Email verifiziert'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    http_response_code(500);

    error_log('Verify Error: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Serverfehler'
    ]);
}