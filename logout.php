<?php
// logout.php
// ───────────────────────────────────────────────────────────────
// IMPORTANT: NO whitespace, blank lines, echo, or text BEFORE <?php
// ───────────────────────────────────────────────────────────────

ob_start(); // Buffer any accidental output (safety net)

require_once __DIR__ . '/config/config.php';

// Start session (required to access/destroy it)
session_start();

// Clear all session variables
$_SESSION = [];

// Delete the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Fully destroy the session
session_destroy();

// Redirect to login with success message
header('Location: ' . BASE_URL . '/index.php?msg=loggedout');

// Stop script execution immediately after redirect
exit;