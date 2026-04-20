<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'db.php';

function get_ip(): string {
    return $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
}

try {

    $ip = get_ip();

    // 🔥 RATE LIMIT (safe insert, kein Crash wenn DB spinnt)
    try {
        $stmt = $pdo->prepare("INSERT INTO rate_limits (ip, action, created_at) VALUES (?, 'register', NOW())");
        $stmt->execute([$ip]);
    } catch (Throwable $e) {
        error_log("RateLimit insert failed: " . $e->getMessage());
    }

    // INPUT
    $firstname = trim($_POST['firstname'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';

    if ($firstname === '' || $email === '' || $password === '') {
        echo json_encode(['success' => false, 'message' => 'Felder fehlen']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Email']);
        exit;
    }

    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Passwort zu kurz']);
        exit;
    }

    // USER CHECK
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email existiert bereits']);
        exit;
    }

    // HASH
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // VERIFY TOKEN
    $verify_token = bin2hex(random_bytes(32));

    // INSERT (safe)
    $stmt = $pdo->prepare("
        INSERT INTO users (firstname, email, password, package_id, verify_token)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $firstname,
        $email,
        $hash,
        1,
        $verify_token
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Registrierung erfolgreich'
    ]);

} catch (Throwable $e) {

    // 🔥 WICHTIG: KEIN SERVER CRASH MEHR
    http_response_code(500);

    error_log("REGISTER ERROR: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Serverfehler'
    ]);
}