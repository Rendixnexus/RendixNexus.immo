<?php
declare(strict_types=1);

require 'db.php';
require 'security.php';

header('Content-Type: application/json; charset=utf-8');

try {

    // 🔒 CSRF nur wenn wirklich vorhanden (kein Hard-Fail Crash)
    if (function_exists('verify_csrf')) {
        try {
            verify_csrf();
        } catch (Throwable $e) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security error']);
            exit;
        }
    }

    // 🔥 SAFE JSON INPUT
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        throw new Exception("Invalid request");
    }

    $email = trim($data['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => true]); // kein leak
        exit;
    }

    // USER CHECK
    $stmt = $pdo->prepare("SELECT id, firstname FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => true]);
        exit;
    }

    // 🔑 Token + Ablauf
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);

    $stmt = $pdo->prepare("
        UPDATE users 
        SET reset_token = ?, reset_expires = ?
        WHERE id = ?
    ");
    $stmt->execute([$token, $expires, $user['id']]);

    // 🔥 MAIL SAFE (kein Crash wenn Mail kaputt)
    try {
        $resetLink = "https://rendixnexus.immo/api/reset_password.php?token=" . $token;

        $subject = "Passwort zurücksetzen";
        $message = "Hallo {$user['firstname']},\n\nReset Link:\n$resetLink\n\nGültig 1 Stunde.";

        $headers = "From: noreply@rendixnexus.immo\r\n";

        mail($email, $subject, $message, $headers);

    } catch (Throwable $e) {
        error_log("MAIL ERROR: " . $e->getMessage());
    }

    echo json_encode(['success' => true, 'message' => 'Wenn Account existiert, wurde E-Mail gesendet']);

} catch (Throwable $e) {

    http_response_code(500);

    error_log("RESET REQUEST ERROR: " . $e->getMessage());

    // 🔒 nie echte Fehler zurückgeben
    echo json_encode([
        'success' => false,
        'message' => 'Serverfehler'
    ]);
}