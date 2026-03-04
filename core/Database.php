<?php
// core/Database.php

class Database {
    private static $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_CHARSET
                );

                self::$instance = new PDO(
                    $dsn,
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
            } catch (PDOException $e) {
                $isDev = (getenv('APP_ENV') === 'development') || defined('STDIN');
                if ($isDev) {
                    die("Database connection failed: " . $e->getMessage());
                } else {
                    error_log("DB connection error: " . $e->getMessage());
                    die("Service unavailable.");
                }
            }
        }
        return self::$instance;
    }
}