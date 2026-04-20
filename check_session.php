<?php
declare(strict_types=1);

require_once 'db.php';
require_once 'auth_middleware.php';

header('Content-Type: application/json');

// =========================
// DEFAULT RESPONSE
// =========================
$response = [
    'loggedIn' => false
];

// =========================
// SESSION CHECK
// =========================
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode($response);
    exit;
}

// =========================
// USER LOAD
// =========================
try {

    $stmt = $pdo->prepare("
        SELECT id, email, role, firstname, lastname, is_verified
        FROM users 
        WHERE id = ? 
        LIMIT 1
    ");

    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        echo json_encode($response);
        exit;
    }

    // =========================
    // EMAIL VERIFICATION CHECK
    // =========================
    if ((int)$user['is_verified'] !== 1) {
        session_destroy();
        echo json_encode($response);
        exit;
    }

    // =========================
    // SESSION FIXATION PROTECTION
    // =========================
    session_regenerate_id(true);

    // =========================
    // OPTIONAL FINGERPRINT CHECK
    // =========================
    $fingerprint_valid = true;

    if (isset($_SESSION['fingerprint'])) {
        $current_fp = hash('sha256',
            ($_SERVER['REMOTE_ADDR'] ?? '') .
            ($_SERVER['HTTP_USER_AGENT'] ?? '')
        );

        if (!hash_equals($_SESSION['fingerprint'], $current_fp)) {
            session_destroy();
            echo json_encode(['loggedIn' => false]);
            exit;
        }
    }

    // =========================
    // RESPONSE
    // =========================
    $response = [
        'loggedIn' => true,
        'user' => [
            'id' => (int)$user['id'],
            'email' => htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'),
            'role' => $user['role'],
            'firstname' => htmlspecialchars($user['firstname'], ENT_QUOTES, 'UTF-8'),
            'lastname' => htmlspecialchars($user['lastname'], ENT_QUOTES, 'UTF-8')
        ],
        'fingerprintValid' => $fingerprint_valid,
        'csrf_token' => $_SESSION['csrf_token'] ?? null
    ];

} catch (Exception $e) {

    error_log('check-login error: ' . $e->getMessage());
    session_destroy();

    $response = [
        'loggedIn' => false
    ];
}

echo json_encode($response);