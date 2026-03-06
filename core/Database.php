<?php
// core/Database.php

declare(strict_types=1);

final class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    /**
     * Prevent unserialization of the singleton (security + correctness)
     */
    public function __wakeup()
    {
        throw new Exception('Cannot unserialize Database singleton');
    }

    /**
     * Get the single PDO instance (lazy-loaded).
     *
     * @throws RuntimeException If connection fails
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $required = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET'];
            foreach ($required as $const) {
                if (!defined($const)) {
                    throw new RuntimeException("Missing required constant: $const");
                }
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                $message = sprintf(
                    'Database connection failed: %s (Code: %s)',
                    $e->getMessage(),
                    $e->getCode()
                );

                if (defined('APP_ENV') && APP_ENV === 'development') {
                    $message .= sprintf(
                        "\nDSN: %s\nUser: %s",
                        htmlspecialchars($dsn),
                        htmlspecialchars(DB_USER)
                    );
                }

                throw new RuntimeException($message, (int)$e->getCode(), $e);
            }
        }

        return self::$instance;
    }

    public static function close(): void
    {
        self::$instance = null;
    }
}