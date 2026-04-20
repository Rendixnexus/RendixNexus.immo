<?php
declare(strict_types=1);

require_once 'db.php';

function get_user_ip(): string {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * RATE LIMIT (stable production version)
 */
function rate_limit(string $action, int $maxRequests, int $seconds): void {
    global $pdo;

    $ip = get_user_ip();

    try {

        // 🔥 FIX: sichere Zeitberechnung in PHP statt SQL INTERVAL ?
        $cutoff = date('Y-m-d H:i:s', time() - $seconds);

        // Alte Einträge löschen (safe)
        $stmt = $pdo->prepare("
            DELETE FROM rate_limits 
            WHERE created_at < ?
        ");
        $stmt->execute([$cutoff]);

        // Requests zählen
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS count 
            FROM rate_limits 
            WHERE ip = ? AND action = ?
        ");
        $stmt->execute([$ip, $action]);

        $count = (int)($stmt->fetch()['count'] ?? 0);

        if ($count >= $maxRequests) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Zu viele Anfragen. Bitte später erneut versuchen.'
            ]);
            exit;
        }

        // neuen Request loggen
        $stmt = $pdo->prepare("
            INSERT INTO rate_limits (ip, action, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$ip, $action]);

    } catch (Throwable $e) {
        // 🔥 WICHTIG: Rate Limit darf NIE deine Seite crashen
        error_log('RateLimit error: ' . $e->getMessage());
        return; // fallback: allow request
    }
}