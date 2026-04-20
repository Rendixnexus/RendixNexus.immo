<?php
declare(strict_types=1);

require_once 'auth_middleware.php';
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

try {

    // =========================
    // LOGIN CHECK (aus Middleware)
    // =========================
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$user_id) {
        http_response_code(401);
        exit(json_encode([
            'success' => false,
            'message' => 'Nicht eingeloggt'
        ]));
    }

    // =========================
    // RATE LIMIT
    // =========================
    rateLimit($pdo, 'create_listing', 30, 60);

    // =========================
    // ONLY POST
    // =========================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Nur POST erlaubt");
    }

    // =========================
    // INPUT (FORMDATA)
    // =========================
    $required = [
        'title','description','country',
        'city','street','plz','price'
    ];

    $data = [];

    foreach ($required as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception("Pflichtfeld fehlt: $field");
        }

        $data[$field] = htmlspecialchars(
            trim($_POST[$field]),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    // =========================
    // BOOL FIELDS
    // =========================
    $data['pets'] = ($_POST['pets'] ?? 'Nein') === 'Ja' ? 'Ja' : 'Nein';
    $data['lift'] = ($_POST['lift'] ?? 'Nein') === 'Ja' ? 'Ja' : 'Nein';
    $data['wheelchair'] = ($_POST['wheelchair'] ?? 'Nein') === 'Ja' ? 'Ja' : 'Nein';

    // =========================
    // IMAGE UPLOAD
    // =========================
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
            throw new Exception("Max $maxFiles Bilder erlaubt");
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        foreach ($_FILES['images']['tmp_name'] as $i => $tmpName) {

            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($_FILES['images']['size'][$i] > $maxSize) continue;

            $mime = finfo_file($finfo, $tmpName);

            if (!in_array($mime, $allowed, true)) continue;

            $ext = match($mime) {
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                default => 'bin'
            };

            $filename = bin2hex(random_bytes(16)) . '.' . $ext;

            if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                $images[] = '/uploads/' . $filename;
            }
        }

        finfo_close($finfo);
    }

    // =========================
    // DB INSERT
    // =========================
    $stmt = $pdo->prepare("
        INSERT INTO listings
        (user_id,title,description,country,city,street,plz,price,pets,lift,wheelchair,images,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())
    ");

    $stmt->execute([
        $user_id,
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
        json_encode($images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Listing erstellt'
    ]);

} catch (Exception $e) {

    error_log('Create Listing: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Serverfehler'
    ]);
}