<?php
// index.php — Login page entry point
// ─────────────────────────────────────────────────────────────
// All authentication logic lives in AuthController.
// This file bootstraps dependencies, delegates to the controller,
// then renders the login HTML view.
// ─────────────────────────────────────────────────────────────

// Start session FIRST — must be before any output or headers
session_start();

// Load configuration and core classes
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Model.php';
require_once __DIR__ . '/core/Controller.php';
require_once __DIR__ . '/app/controllers/AuthController.php';

$auth = new AuthController();

// Handle POST login submission — delegate fully to AuthController
// AuthController::login() handles CSRF, validation, session setup, and redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->login();
    exit;
}

// Handle GET — check timeout, redirect if already logged in, set error/success vars
$auth->loginPage();

// Read variables set by loginPage() via $GLOBALS
$error   = $GLOBALS['login_error']   ?? null;
$success = $GLOBALS['login_success'] ?? null;

// Generate CSRF token for the login form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In | <?= APP_NAME ?></title>
  <!-- AdminLTE 3 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Google Fonts -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">

  <link rel="stylesheet" href="assets/css/index.css">
</head>
<body>
  <div class="bg-shape"></div>
  <div class="bg-shape"></div>

  <div class="login-wrapper">

    <!-- Logo -->
    <div class="login-logo">
      <div class="logo-circle">
        <i class="fas fa-coins fa-2x" style="color:#fff"></i>
      </div>
      <h1>Rocky Company</h1>
      <p>Payroll System &mdash; v<?= APP_VERSION ?></p>
    </div>

    <!-- Card -->
    <div class="login-card">
      <h5>Welcome back 👋</h5>
      <p class="subtitle">Sign in to access your dashboard</p>

      <?php if ($error): ?>
        <div class="alert-box alert-error">
          <i class="fas fa-exclamation-circle"></i>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert-box alert-success">
          <i class="fas fa-check-circle"></i>
          <?= htmlspecialchars($success) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="index.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="form-group">
          <label>Username</label>
          <div class="input-wrap">
            <i class="fas fa-user input-icon"></i>
            <input type="text" name="username" placeholder="Enter your username"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="username">
          </div>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="input-wrap">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="password" id="passwordField" placeholder="Enter your password" required autocomplete="current-password">
            <button type="button" class="toggle-pw" onclick="togglePassword()">
              <i class="fas fa-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-login">
          <i class="fas fa-sign-in-alt mr-2"></i> Sign In
        </button>
        <div style="text-align:center;margin-top:14px;">
          <a href="forgot_password.php" style="font-size:.85rem;color:#64748b;text-decoration:none;">
            <i class="fas fa-key mr-1"></i>Forgot your password?
          </a>
        </div>
      </form>
    </div>

    <div class="login-footer">
      &copy; <?= date('Y') ?> Rocky Company &mdash; All rights reserved
    </div>
  </div>

<script>
function togglePassword() {
  const field = document.getElementById('passwordField');
  const icon  = document.getElementById('eyeIcon');
  if (field.type === 'password') {
    field.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    field.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
}
</script>
</body>
</html>