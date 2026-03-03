<?php
// ============================================================
//  Rocky Company Payroll System — Database Configuration
//  File: config/database.php
// ============================================================

// ── Connection credentials ───────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'rocky_payroll');
define('DB_USER',    'root');        // ← change to your MySQL username
define('DB_PASS',    '');            // ← change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

// ── PDO Singleton ────────────────────────────────────────────
class Database {

    private static ?PDO $instance = null;

    /**
     * Get the shared PDO instance (singleton).
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // In production, log this instead of displaying it
                error_log('DB Connection Error: ' . $e->getMessage());
                die(self::errorPage($e->getMessage()));
            }
        }

        return self::$instance;
    }

    /**
     * Shorthand — returns the PDO instance.
     * Usage: $pdo = Database::connect();
     */
    public static function connect(): PDO {
        return self::getInstance();
    }

    /**
     * Friendly error page shown if connection fails.
     */
    private static function errorPage(string $msg): string {
        return '<!DOCTYPE html>
<html><head><title>Database Error</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
</head><body class="hold-transition login-page" style="background:#1a1f2e">
<div class="login-box" style="margin:auto;padding-top:80px">
  <div class="card">
    <div class="card-header bg-danger text-white">
      <h5 class="card-title mb-0">⚠️ Database Connection Failed</h5>
    </div>
    <div class="card-body">
      <p>Could not connect to the MySQL database. Please check your credentials in <code>config/database.php</code>.</p>
      <pre style="background:#f4f6f9;padding:12px;border-radius:4px;font-size:.8rem">' . htmlspecialchars($msg) . '</pre>
      <p class="mb-0 text-muted" style="font-size:.8rem">Check that MySQL is running and the database <strong>' . DB_NAME . '</strong> exists.</p>
    </div>
  </div>
</div></body></html>';
    }

    // Prevent cloning / unserialization
    private function __clone() {}
    public function __wakeup() { throw new \Exception('Cannot unserialize singleton.'); }
}
