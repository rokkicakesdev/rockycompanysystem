<?php
// config/database.php

/**
 * Database Configuration
 * Loads credentials securely from .env file in project root
 * Uses getenv() with safe fallbacks
 * Includes basic validation to fail early in development
 */

// ── Load .env file (simple parser - zero dependencies) ────────────────
$envPath = __DIR__ . '/../.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Remove surrounding quotes if present
        $value = trim($value, '"\'');

        putenv("$key=" . $value);
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value; // optional fallback
    }
}

// ── Define connection constants ──────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: '');
define('DB_USER',    getenv('DB_USER')    ?: '');
define('DB_PASS', '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// ── Basic validation (fail early in development) ─────────────────────
if (empty(DB_NAME) || empty(DB_USER)) {
    $isDev = (getenv('APP_ENV') === 'development') || defined('STDIN');
    if ($isDev) {
        die('Database configuration is incomplete. Please check your .env file in the project root.');
    } else {
        // Production: log error silently, don't expose details
        error_log('Missing database configuration in ' . __FILE__);
        header('HTTP/1.1 500 Internal Server Error');
        exit('Service unavailable');
    }
}

// You can keep your existing PDO connection code below this point if any
// Example (uncomment/adapt if needed):
/*
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
*/