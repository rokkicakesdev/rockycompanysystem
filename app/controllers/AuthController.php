<?php
// app/controllers/AuthController.php
// ─────────────────────────────────────────────────────────────────────────────
//  Handles authentication flow: login page, login POST, logout, role redirect.
//  All logic mirrors index.php exactly — this controller is the canonical
//  auth handler and can be used if the project is ever routed through a
//  front controller instead of index.php directly.
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/Model.php';
require_once __DIR__ . '/../../config/config.php';

class AuthController extends Controller
{
    // ── Session timeout in seconds (matches index.php and config) ────────────
    private const TIMEOUT_SECONDS = SESSION_TIMEOUT_MINUTES * 60;

    // ─────────────────────────────────────────────────────────────────────────
    //  loginPage() — show the login form (GET)
    //  Redirects already-authenticated users to their dashboard.
    // ─────────────────────────────────────────────────────────────────────────
    public function loginPage(): void
    {
        $this->enforceTimeout();

        if ($this->isLoggedIn()) {
            $this->redirectByRole($_SESSION['role'] ?? '');
        }

        // Pass any error/success message codes from URL to the view
        $error   = null;
        $success = null;

        $errorParam = $_GET['error'] ?? null;
        $msgParam   = $_GET['msg']   ?? null;

        $error = match ($errorParam) {
            'empty'         => 'Please enter both username and password.',
            'invalid'       => 'Invalid username or password.',
            'inactive'      => 'Your account has been deactivated. Please contact your administrator.',
            'unauthorized'  => 'You are not authorized to access this system.',
            'not_logged_in' => 'Please sign in to continue.',
            'no_employee'   => 'Employee account is not properly linked to an employee record. Contact admin.',
            default         => null,
        };

        if ($msgParam === 'loggedout') {
            $success = 'You have been successfully signed out.';
        } elseif ($msgParam === 'timeout') {
            $error = 'Session timed out due to inactivity. Please sign in again.';
        }

        $this->view('login', [
            'error'   => $error,
            'success' => $success,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  login() — process login form submission (POST)
    // ─────────────────────────────────────────────────────────────────────────
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('index.php');
        }

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        // ── Empty field check ────────────────────────────────────────────────
        if (empty($username) || empty($password)) {
            $this->redirect('index.php?error=empty');
        }

        // ── Find user ────────────────────────────────────────────────────────
        $user = Model::findUserByUsername($username);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->redirect('index.php?error=invalid');
        }

        // ── Account status check ─────────────────────────────────────────────
        if ($user['status'] !== 'active') {
            $this->redirect('index.php?error=inactive');
        }

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
                // Unlink the session — don't let a dangling employee account in
                session_unset();
                session_destroy();
                $this->redirect('index.php?error=no_employee');
            }

            $_SESSION['employee_id'] = (int)$employeeId;
        }

        // ── Role-based redirect ──────────────────────────────────────────────
        $this->redirectByRole($user['role']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  logout() — destroy session and redirect to login
    //  Delegates to logout.php which handles cookie cleanup properly.
    // ─────────────────────────────────────────────────────────────────────────
    public function logout(): void
    {
        // Use the dedicated logout.php which handles cookie invalidation cleanly
        $this->redirect('logout.php');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  enforceTimeout() — destroy session if inactive too long
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

        // Refresh the activity timestamp on every authenticated request
        if ($this->isLoggedIn()) {
            $_SESSION['last_activity'] = time();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  redirectByRole() — send user to their role-appropriate dashboard
    // ─────────────────────────────────────────────────────────────────────────
    private function redirectByRole(string $role): void
    {
        match ($role) {
            ROLE_ADMIN      => $this->redirect('app/views/admin/dashboard.php'),
            ROLE_MANAGEMENT => $this->redirect('app/views/management/dashboard.php'),
            'employee'      => $this->redirect('app/views/employee/my_payslips.php'),
            default         => $this->redirect('index.php?error=unauthorized'),
        };
    }
}