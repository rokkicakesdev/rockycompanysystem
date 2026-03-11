<?php
// config/config.php
// ──────────────────────────────────────────────────────────────
// Global application configuration
// Loads values from .env where possible, with sane defaults
// ──────────────────────────────────────────────────────────────

// ── Load .env if not already done (from database.php or bootstrap)
// This is optional here — usually loaded earlier in database.php
if (!function_exists('getenv') || !getenv('APP_ENV')) {
    $envPath = __DIR__ . '/../.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) continue;
            if (strpos($line, '=') === false) continue;

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            $value = trim($value, '"\'');

            putenv("$key=$value");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// ── Application Basics ───────────────────────────────────────────
define('APP_NAME',      getenv('APP_NAME')      ?: 'Rocky Company System');
define('APP_VERSION',   getenv('APP_VERSION')   ?: '1.0.0');
define('APP_ENV',       getenv('APP_ENV')       ?: 'development'); // development | production

// ── URLs & Paths ─────────────────────────────────────────────────
define('BASE_URL',      rtrim(getenv('BASE_URL') ?: 'http://localhost/rocky-company-system', '/'));
define('ASSETS_URL',    BASE_URL . '/assets');

// ── Company Info ─────────────────────────────────────────────────
define('COMPANY_NAME',    getenv('COMPANY_NAME')    ?: 'Rocky Company');
define('COMPANY_ADDRESS', getenv('COMPANY_ADDRESS') ?: 'Paranaque City, Metro Manila');
define('WORKING_DAYS',    (int)(getenv('WORKING_DAYS') ?: 22));
define('WORK_HOURS', (int) (getenv('WORK_HOURS') ?: 8));
define('RECORDS_PER_PAGE',(int)(getenv('RECORDS_PER_PAGE') ?: 15));

// ── Roles (used in auth/role checks) ─────────────────────────────
define('ROLE_ADMIN',      'admin');
define('ROLE_MANAGEMENT', 'management');

// ── Employee Status Options ──────────────────────────────────────
define('EMPLOYEE_STATUS', [
    'active'    => 'Active',
    'inactive'  => 'Inactive',
    'resigned'  => 'Resigned',
    'terminated'=> 'Terminated',
    'on_leave'  => 'On Leave',
]);

// ── Employment Types ─────────────────────────────────────────────
define('EMPLOYMENT_TYPES', [
    'regular'    => 'Regular',
    'probationary'=> 'Probationary',
    'contractual' => 'Contractual',
    'part_time'   => 'Part-Time',
]);

// ── Leave Types (used in dropdowns & computations) ───────────────
define('LEAVE_TYPES', [
    'sick'              => 'Sick Leave',
    'vacation'          => 'Vacation Leave',
    'bereavement'       => 'Bereavement Leave',
    'emergency'         => 'Emergency Leave',
    'sil'               => 'Service Incentive Leave',
    'maternity'         => 'Maternity Leave',
    'paternity'         => 'Paternity Leave',
    'solo_parent'       => 'Solo Parent Leave',
    'vawc'              => 'VAWC Leave',
    'magna_carta'       => 'Magna Carta Leave',
    'unpaid'            => 'Unpaid Leave',
]);

// ── Leave Balance Field Mapping (used in reviewLeaveRequest) ─────
define('LEAVE_BALANCE_FIELDS', [
    'sick'              => 'sick_leave_balance',
    'vacation'          => 'vacation_leave_balance',
    'bereavement'       => 'bereavement_leave_balance',
    'emergency'         => 'emergency_leave_balance',
    'sil'               => 'sil_balance',
    'maternity'         => 'maternity_leave_balance',
    'paternity'         => 'paternity_leave_balance',
    'solo_parent'       => 'solo_parent_leave_balance',
    'vawc'              => 'vawc_leave_balance',
    'magna_carta'       => 'magna_carta_leave_balance',
]);

// ── Security / Misc ──────────────────────────────────────────────
define('SESSION_TIMEOUT_MINUTES', 30); // auto-logout after inactivity

// ── Error Reporting ───────────────────────────────────────────────
if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);                    // still log everything
    ini_set('log_errors', '1');                // just don't show on screen
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// ── Safety Check (dev only) ──────────────────────────────────────
if (APP_ENV === 'development' && (empty(BASE_URL) || empty(APP_NAME))) {
    die('Critical configuration missing. Check .env or config/config.php');
}