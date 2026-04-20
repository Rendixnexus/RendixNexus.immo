<?php
declare(strict_types=1);

session_start();

require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

// =========================
// DEFAULT RESPONSE
// =========================
$response = [
    'success' => false,
    'verified' => false
];

// =========================
// CHECK LOGIN
// =========================
if (!isset($_SESSION['user_id'])) {
    echo json_encode($response);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// =========================
// GET USER
// =========================
try {

    $stmt = $pdo->prepare("
        SELECT is_verified 
        FROM users 
        WHERE id = ? 
        LIMIT 1
    ");

    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // User existiert nicht mehr → Session löschen
    if (!$user) {
        session_destroy();
        echo json_encode($response);
        exit;
    }

    $response = [
        'success' => true,
        'verified' => ((int)$user['is_verified'] === 1)
    ];

} catch (Exception $e) {

    error_log("check_verified error: " . $e->getMessage());

    session_destroy();

    $response = [
        'success' => false,
        'verified' => false
    ];
}

echo json_encode($response);