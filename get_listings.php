<?php
declare(strict_types=1);

require_once 'auth_middleware.php';
require_once 'db.php';
require_once 'i18n.php';

header('Content-Type: application/json; charset=utf-8');

try {

    // =========================
    // RATE LIMIT
    // =========================
    rateLimit('get_listings', 50, 60);

    // =========================
    // SESSION DATA
    // =========================
    $user_id = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role'] ?? 'user';

    if (!$user_id) {
        http_response_code(401);
        exit(json_encode([
            'success' => false,
            'message' => 'Nicht eingeloggt'
        ]));
    }

    // =========================
    // INPUT
    // =========================
    $lang = $_GET['lang'] ?? DEFAULT_LANG;
    $limit = max(1, min((int)($_GET['limit'] ?? 10), 50));
    $offset = max(0, (int)($_GET['offset'] ?? 0));

    // =========================
    // QUERY
    // =========================
    if ($role === 'admin') {

        $stmt = $pdo->prepare("
            SELECT l.id, l.user_id, l.price, l.country, l.city, l.street, l.plz, l.images, l.created_at,
                   COALESCE(lt.title, l.title) AS title,
                   COALESCE(lt.description, l.description) AS description,
                   u.firstname, u.lastname
            FROM listings l
            LEFT JOIN listings_translations lt
              ON lt.listing_id = l.id AND lt.language = ?
            JOIN users u ON l.user_id = u.id
            ORDER BY l.created_at DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->bindValue(1, $lang, PDO::PARAM_STR);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);

    } else {

        $stmt = $pdo->prepare("
            SELECT l.id, l.user_id, l.price, l.country, l.city, l.street, l.plz, l.images, l.created_at,
                   COALESCE(lt.title, l.title) AS title,
                   COALESCE(lt.description, l.description) AS description
            FROM listings l
            LEFT JOIN listings_translations lt
              ON lt.listing_id = l.id AND lt.language = ?
            WHERE l.user_id = ?
            ORDER BY l.created_at DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->bindValue(1, $lang, PDO::PARAM_STR);
        $stmt->bindValue(2, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->bindValue(4, $offset, PDO::PARAM_INT);
    }

    $stmt->execute();
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // =========================
    // IMAGE SAFE PARSE
    // =========================
    foreach ($listings as &$l) {

        if (!empty($l['images'])) {

            $decoded = json_decode($l['images'], true);

            $safeImages = [];

            if (is_array($decoded)) {
                foreach ($decoded as $img) {
                    if (is_string($img) && str_starts_with($img, '/uploads/')) {
                        $safeImages[] = $img;
                    }
                }
            }

            $l['images'] = $safeImages;

        } else {
            $l['images'] = [];
        }
    }

    echo json_encode([
        'success' => true,
        'count' => count($listings),
        'limit' => $limit,
        'offset' => $offset,
        'listings' => $listings
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {

    error_log('Get Listings Error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Serverfehler'
    ]);
}