<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '0'); // ⚠️ auf 1 NUR mit HTTPS
ini_set('session.cookie_samesite', 'Strict');

session_start();
require_once 'db.php';

header('Content-Type: application/json');

// =========================
// INPUT (JSON)
// =========================
$data = json_decode(file_get_contents("php://input"), true);

$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$csrf = $data['csrf_token'] ?? '';

// =========================
// BASIC CHECK
// =========================
if (!$email || !$password) {
    echo json_encode([
        'success' => false,
        'message' => 'Fehlende Daten'
    ]);
    exit;
}

// =========================
// CSRF CHECK (optional streng)
// =========================
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($csrf && !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'CSRF Fehler'
    ]);
    exit;
}

// =========================
// USER CHECK
// =========================
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$fakeHash = password_hash("fake_password", PASSWORD_DEFAULT);
$hash = $user['password'] ?? $fakeHash;

$valid = password_verify($password, $hash);

// =========================
// FAIL
// =========================
if (!$user || !$valid) {
    echo json_encode([
        'success' => false,
        'message' => 'Login fehlgeschlagen'
    ]);
    exit;
}

// =========================
// SUCCESS
// =========================
session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $user['role'] ?? 'user';
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

echo json_encode([
    'success' => true,
    'message' => 'Login erfolgreich',
    'csrf_token' => $_SESSION['csrf_token']
]);