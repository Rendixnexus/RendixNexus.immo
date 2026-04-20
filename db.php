<?php
declare(strict_types=1);

$host = 'localhost';
$db   = 'realestate_db';
$user = 'root';
$pass = '';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        $options
    );

} catch (PDOException $e) {

    // 🔒 Server log statt echo (Production safe)
    error_log('DB ERROR: ' . $e->getMessage());

    http_response_code(500);
    exit(json_encode([
        'success' => false,
        'message' => 'Database error'
    ]));
}