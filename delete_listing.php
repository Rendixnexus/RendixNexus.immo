<?php
declare(strict_types=1);

require_once 'auth_middleware.php';
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

try {

    // =========================
    // RATE LIMIT
    // =========================
    rateLimit('delete_listing', 5, 60);

    // =========================
    // METHOD CHECK
    // =========================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception("Nur POST erlaubt");
    }

    // =========================
    // CSRF CHECK (WICHTIG)
    // =========================
    $csrf = $_POST['csrf_token'] ?? '';

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        http_response_code(403);
        throw new Exception("CSRF Fehler");
    }

    // =========================
    // INPUT VALIDATION
    // =========================
    $listing_id = (int)($_POST['id'] ?? 0);

    if ($listing_id <= 0) {
        http_response_code(400);
        throw new Exception("Ungültige ID");
    }

    // =========================
    // LISTING HOLEN
    // =========================
    $stmt = $pdo->prepare("SELECT user_id FROM listings WHERE id = ? LIMIT 1");
    $stmt->execute([$listing_id]);

    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing) {
        http_response_code(404);
        throw new Exception("Listing nicht gefunden");
    }

    // =========================
    // USER AUS SESSION (SICHER)
    // =========================
    $user_id = $_SESSION['user_id'] ?? 0;
    $role = $_SESSION['role'] ?? 'user';

    // =========================
    // PERMISSION CHECK
    // =========================
    if ($role !== 'admin' && (int)$listing['user_id'] !== (int)$user_id) {
        http_response_code(403);
        throw new Exception("Keine Berechtigung");
    }

    // =========================
    // DELETE
    // =========================
    $stmt = $pdo->prepare("DELETE FROM listings WHERE id = ?");
    $stmt->execute([$listing_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Listing gelöscht'
    ]);

} catch (Exception $e) {

    error_log('Delete Listing: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}