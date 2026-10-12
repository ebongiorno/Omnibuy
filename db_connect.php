<?php

// Grab database configs from environment variables
$host = getenv('DB_HOST');
$port = (int) getenv('DB_PORT');
$database = getenv('DB_NAME');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

// Establish connection between PHP and database
$dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // Log the details for developers, but never show them to visitors:
    // they include the database host, user and driver error.
    error_log(
        'Database connection failed: ' . $e->getMessage()
    );

    // Command-line scripts (tests) must exit non-zero so the failure is noticed.
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Database connection failed.\n");
        exit(1);
    }

    http_response_code(503);
    exit('OmniBuy is temporarily unavailable. Please try again in a few minutes.');
}

?>