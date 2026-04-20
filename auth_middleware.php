<?php
declare(strict_types=1);

// =========================
// SESSION SECURITY
// =========================
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '0'); // ⚠️ 1 nur mit HTTPS
ini_set('session.cookie_samesite', 'Strict');

session_start();

// =========================
// HEADERS
// =========================
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

// =========================
// DB
// =========================
require_once 'db.php';

// =========================
// HELPERS
// =========================
function getIP(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// =========================
// READ JSON BODY (WICHTIG)
// =========================
function getJsonBody(): array {
    $data = json_decode(file_get_contents("php://input"), true);
    return is_array($data) ? $data : [];
}

// =========================
// SESSION CHECK
// =========================
function requireLogin(): void {

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        exit(json_encode([
            'success' => false,
            'message' => 'Nicht eingeloggt'
        ]));
    }

    // IP CHECK
    if (isset($_SESSION['ip']) && $_SESSION['ip'] !== getIP()) {
        session_destroy();
        http_response_code(403);
        exit(json_encode([
            'success' => false,
            'message' => 'Session ungültig'
        ]));
    }

    // USER AGENT CHECK
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        session_destroy();
        http_response_code(403);
        exit(json_encode([
            'success' => false,
            'message' => 'Session manipuliert'
        ]));
    }
}

// =========================
// USER LOAD
// =========================
function getUser(PDO $pdo): array {

    $stmt = $pdo->prepare("SELECT id, role, is_verified FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        http_response_code(401);
        exit(json_encode([
            'success' => false,
            'message' => 'User nicht gefunden'
        ]));
    }

    if ((int)$user['is_verified'] !== 1) {
        http_response_code(403);
        exit(json_encode([
            'success' => false,
            'message' => 'Email nicht bestätigt'
        ]));
    }

    return $user;
}

// =========================
// CSRF CHECK (JSON READY)
// =========================
function checkCSRF(): void {

    if (!in_array($_SERVER['REQUEST_METHOD'], ['POST','PUT','DELETE'])) {
        return;
    }

    $data = getJsonBody();
    $csrf = $data['csrf_token'] ?? '';

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        http_response_code(403);
        exit(json_encode([
            'success' => false,
            'message' => 'CSRF Fehler'
        ]));
    }
}

// =========================
// RATE LIMIT (FIXED)
// =========================
function rateLimit(PDO $pdo, string $action, int $max, int $seconds): void {

    $ip = getIP();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM rate_limits 
        WHERE ip=? 
        AND action=? 
        AND created_at > (NOW() - INTERVAL $seconds SECOND)
    ");

    $stmt->execute([$ip, $action]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= $max) {
        http_response_code(429);
        exit(json_encode([
            'success' => false,
            'message' => 'Zu viele Anfragen'
        ]));
    }

    $stmt = $pdo->prepare("INSERT INTO rate_limits (ip, action) VALUES (?, ?)");
    $stmt->execute([$ip, $action]);
}
?>