<?php
// app/controllers/AuthController.php
// ─────────────────────────────────────────────────────────────────────────────
//  Handles authentication flow: login page, login POST, logout, role redirect.
//
//  SECURITY IMPROVEMENTS (vs original):
//   - Brute-force protection is now DB-backed (login_attempts table), keyed by
//     BOTH IP address AND username. Session-based lockouts are bypassable by
//     clearing cookies — DB-backed ones are not.
//   - All failed login attempts are logged to login_attempts for audit trail.
//   - Successful logins are also logged (was_successful = 1).
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Model.php';
require_once __DIR__ . '/../../config/config.php';

class AuthController extends Controller
{
    // ── Constants ─────────────────────────────────────────────────────────────
    private const TIMEOUT_SECONDS = SESSION_TIMEOUT_MINUTES * 60;
    private const MAX_ATTEMPTS    = 5;
    private const LOCKOUT_MINUTES = 15;
    private const LOCKOUT_SECONDS = self::LOCKOUT_MINUTES * 60;

    // ─────────────────────────────────────────────────────────────────────────
    //  loginPage() — show the login form (GET)
    // ─────────────────────────────────────────────────────────────────────────
    public function loginPage(): void
    {
        $this->enforceTimeout();

        if ($this->isLoggedIn()) {
            $this->redirectByRole($_SESSION['role'] ?? '');
        }

        $error   = null;
        $success = null;

        $errorParam = $_GET['error'] ?? null;
        $msgParam   = $_GET['msg']   ?? null;

        $wait      = (int)($_GET['wait']      ?? self::LOCKOUT_MINUTES);
        $remaining = (int)($_GET['remaining'] ?? 0);

        $error = match ($errorParam) {
            'empty'         => 'Please enter both username and password.',
            'invalid'       => $remaining > 0
                                ? "Invalid username or password. {$remaining} attempt(s) remaining before lockout."
                                : 'Invalid username or password.',
            'locked'        => "Too many failed attempts. Please wait {$wait} minute(s) before trying again.",
            'inactive'      => 'Your account has been deactivated. Please contact your administrator.',
            'unauthorized'  => 'You are not authorized to access this system.',
            'not_logged_in' => 'Please sign in to continue.',
            'no_employee'   => 'Employee account is not properly linked to an employee record. Contact admin.',
            'invalid_token' => 'Invalid security token. Please refresh the page and try again.',
            'access_denied' => 'Access denied. Please sign in with the correct account.',
            default         => null,
        };

        if ($msgParam === 'loggedout') {
            $success = 'You have been successfully signed out.';
        } elseif ($msgParam === 'timeout') {
            $error = 'Session timed out due to inactivity. Please sign in again.';
        }

        $GLOBALS['login_error']   = $error;
        $GLOBALS['login_success'] = $success;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  login() — process login form submission (POST)
    // ─────────────────────────────────────────────────────────────────────────
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php');
        }

        // CSRF validation
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
            $this->redirect('index.php?error=invalid_token');
        }

        $username  = trim($_POST['username'] ?? '');
        // Never trim() passwords — password_hash() preserves whitespace
        $password  = $_POST['password'] ?? '';
        $ipAddress = $this->getClientIp();

        // ── Empty field check ────────────────────────────────────────────────
        if (empty($username) || empty($password)) {
            $this->redirect('index.php?error=empty');
        }

        // ── DB-backed brute force check (keyed by IP + username) ─────────────
        // Cannot be bypassed by clearing cookies like session-based lockouts.
        if ($this->isLockedOut($username, $ipAddress)) {
            $wait = $this->getLockoutWaitMinutes($username, $ipAddress);
            $this->redirect("index.php?error=locked&wait={$wait}");
        }

        // ── Find user ────────────────────────────────────────────────────────
        $user = Model::findUserByUsername($username);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->recordAttempt($username, $ipAddress, false);
            $remaining = $this->getRemainingAttempts($username, $ipAddress);
            if ($remaining <= 0) {
                $this->redirect('index.php?error=locked&wait=' . self::LOCKOUT_MINUTES);
            }
            $this->redirect("index.php?error=invalid&remaining={$remaining}");
        }

        // ── Account status check ─────────────────────────────────────────────
        if ($user['status'] !== 'active') {
            $this->recordAttempt($username, $ipAddress, false);
            $this->redirect('index.php?error=inactive');
        }

        // ── Successful login ──────────────────────────────────────────────────
        $this->recordAttempt($username, $ipAddress, true);

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        // Fresh CSRF token after session ID regeneration
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // ── Set core session variables ───────────────────────────────────────
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['name']          = $user['name'];
        $_SESSION['user']          = $user;
        $_SESSION['last_activity'] = time();

        // ── Employee role: require a linked employee record ──────────────────
        if ($user['role'] === 'employee') {
            $employeeId = $user['employee_id'] ?? null;
            if (empty($employeeId)) {
                session_unset();
                session_destroy();
                $this->redirect('index.php?error=no_employee');
            }
            $_SESSION['employee_id'] = (int)$employeeId;
        }

        // ── Log the successful login to activity_logs ────────────────────────
        Model::log($user['id'], 'LOGIN', "User '{$username}' logged in from {$ipAddress}");

        // ── Role-based redirect ──────────────────────────────────────────────
        $this->redirectByRole($user['role']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  logout()
    // ─────────────────────────────────────────────────────────────────────────
    public function logout(): void
    {
        $this->redirect('logout.php');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  enforceTimeout()
    // ─────────────────────────────────────────────────────────────────────────
    private function enforceTimeout(): void
    {
        if (isset($_SESSION['last_activity']) &&
            (time() - $_SESSION['last_activity']) > self::TIMEOUT_SECONDS
        ) {
            session_unset();
            session_destroy();
            $this->redirect('index.php?msg=timeout');
        }

        if ($this->isLoggedIn()) {
            $_SESSION['last_activity'] = time();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  redirectByRole()
    // ─────────────────────────────────────────────────────────────────────────
    private function redirectByRole(string $role): void
    {
        match ($role) {
            ROLE_ADMIN      => $this->redirect('app/views/admin/dashboard.php'),
            ROLE_MANAGEMENT => $this->redirect('app/views/management/dashboard.php'),
            'employee'      => $this->redirect('app/views/employee/dashboard.php'),
            default         => $this->redirect('index.php?error=unauthorized'),
        };
    }

    // =========================================================================
    //  DB-BACKED BRUTE FORCE HELPERS
    // =========================================================================

    /**
     * Returns true if the username or IP has exceeded MAX_ATTEMPTS
     * within the LOCKOUT_SECONDS window.
     */
    private function isLockedOut(string $username, string $ip): bool
    {
        try {
            $db    = Database::getInstance();
            $since = date('Y-m-d H:i:s', time() - self::LOCKOUT_SECONDS);
            $stmt  = $db->prepare('
                SELECT COUNT(*) FROM login_attempts
                WHERE was_successful = 0
                  AND attempted_at >= ?
                  AND (username = ? OR ip_address = ?)
            ');
            $stmt->execute([$since, $username, $ip]);
            return (int)$stmt->fetchColumn() >= self::MAX_ATTEMPTS;
        } catch (Exception $e) {
            // Table may not exist yet — fail open so auth still works
            error_log('AuthController::isLockedOut - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Returns minutes remaining in the current lockout window.
     */
    private function getLockoutWaitMinutes(string $username, string $ip): int
    {
        try {
            $db    = Database::getInstance();
            $since = date('Y-m-d H:i:s', time() - self::LOCKOUT_SECONDS);
            $stmt  = $db->prepare('
                SELECT MIN(attempted_at) FROM login_attempts
                WHERE was_successful = 0
                  AND attempted_at >= ?
                  AND (username = ? OR ip_address = ?)
            ');
            $stmt->execute([$since, $username, $ip]);
            $oldest = $stmt->fetchColumn();
            if (!$oldest) {
                return self::LOCKOUT_MINUTES;
            }
            $unlockAt = strtotime($oldest) + self::LOCKOUT_SECONDS;
            return (int)max(1, ceil(($unlockAt - time()) / 60));
        } catch (Exception $e) {
            return self::LOCKOUT_MINUTES;
        }
    }

    /**
     * Returns how many attempts remain before lockout triggers.
     */
    private function getRemainingAttempts(string $username, string $ip): int
    {
        try {
            $db    = Database::getInstance();
            $since = date('Y-m-d H:i:s', time() - self::LOCKOUT_SECONDS);
            $stmt  = $db->prepare('
                SELECT COUNT(*) FROM login_attempts
                WHERE was_successful = 0
                  AND attempted_at >= ?
                  AND (username = ? OR ip_address = ?)
            ');
            $stmt->execute([$since, $username, $ip]);
            $count = (int)$stmt->fetchColumn();
            return max(0, self::MAX_ATTEMPTS - $count);
        } catch (Exception $e) {
            return self::MAX_ATTEMPTS;
        }
    }

    /**
     * Record a login attempt to the database.
     */
    private function recordAttempt(string $username, string $ip, bool $success): void
    {
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare('
                INSERT INTO login_attempts (username, ip_address, was_successful)
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$username, $ip, $success ? 1 : 0]);
        } catch (Exception $e) {
            // Log but never crash — auth must work even if logging fails
            error_log('AuthController::recordAttempt - ' . $e->getMessage());
        }
    }

    /**
     * Get the real client IP, accounting for common proxy headers.
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}