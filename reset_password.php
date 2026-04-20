<?php
declare(strict_types=1);

require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

function get_ip(): string {
    return $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
}

try {

    $email = trim($_POST['email'] ?? '');

    // 🔒 immer gleiche Antwort (kein User leak)
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => true]);
        exit;
    }

    // optional: simple rate limit light (kein DB crash risk)
    $ip = get_ip();

    try {
        $stmt = $pdo->prepare("INSERT INTO rate_limits (ip, action, created_at) VALUES (?, 'reset', NOW())");
        $stmt->execute([$ip]);
    } catch (Throwable $e) {
        error_log("RateLimit reset failed: " . $e->getMessage());
    }

    // USER CHECK
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {

        $token = bin2hex(random_bytes(32));

        $stmt = $pdo->prepare("
            UPDATE users 
            SET verify_token = ?
            WHERE id = ?
        ");
        $stmt->execute([$token, $user['id']]);

        // 🔥 MAIL (optional später)
        // mail($email, "Reset", "https://rendixnexus.immo/reset.php?token=$token");
    }

    // 🔒 IMMER gleiche Antwort (Security best practice)
    echo json_encode(['success' => true]);

} catch (Throwable $e) {

    http_response_code(500);
    error_log("RESET ERROR: " . $e->getMessage());

    // trotzdem keine Info leaken
    echo json_encode(['success' => true]);
}