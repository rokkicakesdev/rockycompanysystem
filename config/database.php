<?php
// ============================================================
//  Rocky Company Payroll System — Database Configuration
//  File: config/database.php
// ============================================================

// ── Connection credentials ───────────────────────────────────
$envPath = __DIR__ . '/../.env';  // root/.env

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Remove surrounding quotes if present
        $value = trim($value, '"\'');
        // Handle escaped newlines etc. if needed (rare in .env)
        $value = str_replace('\n', "\n", $value);

        putenv("$key=" . $value);
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value; // optional fallback
    }
}

// ── Connection credentials ───────────────────────────────────
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: '');
define('DB_USER',    getenv('DB_USER')    ?: '');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// Safety check - fail loudly in development if critical values missing
if (empty(DB_NAME) || empty(DB_USER)) {
    if (getenv('APP_ENV') === 'development' || defined('STDIN')) {
        die('Database configuration is incomplete. Please check your .env file.');
    } else {
        // In production → silent fail or log, don't expose to user
        error_log('Missing DB config in ' . __FILE__);
        header('HTTP/1.1 500 Internal Server Error');
        exit;
    }
}

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
