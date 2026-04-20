<?php
declare(strict_types=1);

require_once 'auth_middleware.php';
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

try {

    rateLimit('update_listing', 30, 60);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Nur POST erlaubt");
    }

    $listing_id = (int)($_POST['id'] ?? 0);
    if ($listing_id <= 0) {
        throw new Exception("Ungültige Listing ID");
    }

    // 🔒 Listing holen
    $stmt = $pdo->prepare("SELECT user_id, images FROM listings WHERE id=? LIMIT 1");
    $stmt->execute([$listing_id]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$listing) {
        throw new Exception("Listing nicht gefunden");
    }

    // 🔒 Rechte prüfen (Fallback safe)
    if (!isset($role, $user_id)) {
        throw new Exception("Auth Context fehlt");
    }

    if ($role !== 'admin' && (int)$listing['user_id'] !== (int)$user_id) {
        http_response_code(403);
        throw new Exception("Keine Berechtigung");
    }

    $required = ['title','description','country','city','street','plz','price'];
    $data = [];

    foreach ($required as $f) {
        if (empty($_POST[$f])) {
            throw new Exception("Pflichtfeld {$f} fehlt");
        }

        $data[$f] = htmlspecialchars(
            trim((string)$_POST[$f]),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    $data['pets'] = in_array($_POST['pets'] ?? 'Nein', ['Ja','Nein'], true) ? $_POST['pets'] : 'Nein';
    $data['lift'] = in_array($_POST['lift'] ?? 'Nein', ['Ja','Nein'], true) ? $_POST['lift'] : 'Nein';
    $data['wheelchair'] = in_array($_POST['wheelchair'] ?? 'Nein', ['Ja','Nein'], true) ? $_POST['wheelchair'] : 'Nein';

    // 🔒 Alte Bilder
    $existingImages = [];

    if (!empty($listing['images'])) {
        $decoded = json_decode($listing['images'], true);
        if (is_array($decoded)) {
            $existingImages = $decoded;
        }
    }

    // 🔒 Neue Bilder
    $images = [];

    if (!empty($_FILES['images']['name'][0])) {

        $uploadDir = __DIR__ . '/uploads/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $maxFiles = 5;
        $maxSize = 5 * 1024 * 1024;
        $allowed = ['image/jpeg','image/png','image/webp'];

        if (count($_FILES['images']['name']) > $maxFiles) {
            throw new Exception("Max {$maxFiles} Bilder erlaubt");
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {

            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($_FILES['images']['size'][$i] > $maxSize) continue;

            $mime = finfo_file($finfo, $tmpName);

            if (!in_array($mime, $allowed, true)) continue;

            $ext = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                default       => 'bin'
            };

            $filename = bin2hex(random_bytes(16)) . '.' . $ext;

            if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                $images[] = '/uploads/' . $filename;
            }
        }

        if ($finfo) finfo_close($finfo);
    }

    $finalImages = array_slice(array_merge($existingImages, $images), 0, 10);

    // 🔒 Update
    $stmt = $pdo->prepare("
        UPDATE listings
        SET title=?, description=?, country=?, city=?, street=?, plz=?, price=?,
            pets=?, lift=?, wheelchair=?, images=?
        WHERE id=?
    ");

    $stmt->execute([
        $data['title'],
        $data['description'],
        $data['country'],
        $data['city'],
        $data['street'],
        $data['plz'],
        $data['price'],
        $data['pets'],
        $data['lift'],
        $data['wheelchair'],
        json_encode($finalImages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $listing_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Listing aktualisiert'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    http_response_code(400);

    error_log('Update Listing: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Serverfehler'
    ]);
}