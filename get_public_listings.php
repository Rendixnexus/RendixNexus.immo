<?php
declare(strict_types=1);

require 'db.php';
require 'i18n.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

try {
    // 🔒 Pagination & Limits
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $limit = max(1, min($limit, 50));
    $offset = max(0, $offset);

    $lang = $_GET['lang'] ?? DEFAULT_LANG;

    $stmt = $pdo->prepare("
        SELECT l.id, l.price, l.city, l.street, l.plz, l.images, l.created_at,
               COALESCE(lt.title, l.title) AS title,
               COALESCE(lt.description, l.description) AS description
        FROM listings l
        LEFT JOIN listings_translations lt
          ON lt.listing_id = l.id AND lt.language = ?
        ORDER BY l.created_at DESC
        LIMIT ? OFFSET ?
    ");

    $stmt->bindValue(1, $lang, PDO::PARAM_STR);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();

    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($listings as &$l) {
        $l['title'] = htmlspecialchars($l['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $l['description'] = htmlspecialchars($l['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $decoded = json_decode($l['images'] ?? '', true);

        $l['images'] = [];
        if (is_array($decoded)) {
            foreach ($decoded as $img) {
                if (is_string($img) && str_starts_with($img, '/uploads/')) {
                    $l['images'][] = $img;
                }
            }
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
    http_response_code(500);
    error_log('Get Public Listings Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Serverfehler'
    ]);
}