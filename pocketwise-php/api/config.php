<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'pocketwise');
define('DB_USER', 'root');       // Change to your MySQL username
define('DB_PASS', '');           // Change to your MySQL password
define('DB_PORT', '3306');

// JWT secret
define('JWT_SECRET', 'secret');

// Anthropic API Key
define('ANTHROPIC_API_KEY', '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');

// App settings
define('APP_ENV', 'development');
define('CORS_ORIGIN', 'http://localhost'); // XAMPP

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function generateId(): string {
    return (string)(int)(microtime(true) * 1000) . rand(100, 999);
}
