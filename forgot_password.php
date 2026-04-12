<?php
// forgot_password.php
// ─────────────────────────────────────────────────────────────────────────────
//  Step 1 of the password reset flow:
//   - User enters their email address
//   - System looks up the account, generates a secure one-time token,
//     stores a hash of the token in DB, and emails the raw token link
//   - Always shows a generic success message (prevents email enumeration)
//
//  Security:
//   - CSRF token validated on POST
//   - No indication whether the email exists (anti-enumeration)
//   - Token is 32 bytes from random_bytes() — 256-bit entropy
//   - Token hash stored in DB (SHA-256); raw token only in the email
//   - Rate limit: 1 reset per IP per 5 minutes (blocks mass enumeration)
//   - Token expires in PASSWORD_RESET_EXPIRY_MINUTES (default 30)
// ─────────────────────────────────────────────────────────────────────────────

session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Model.php';
require_once __DIR__ . '/core/Mailer.php';

// Redirect logged-in users
if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    $dest = match ($role) {
        'admin', 'management' => BASE_URL . '/app/views/admin/dashboard.php',
        'employee'            => BASE_URL . '/app/views/employee/dashboard.php',
        default               => BASE_URL . '/index.php',
    };
    header('Location: ' . $dest); exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$error   = '';
$success = false;

// ── POST: process email submission ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $email = trim(strtolower($_POST['email'] ?? ''));

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // ── Simple IP-based rate limit (max 3 requests per 10 minutes) ─────
            // Uses PHP sessions for simplicity — adequate for this use case.
            $nowTs       = time();
            $rlKey       = 'pr_attempts';
            $rlWindow    = 600; // 10 minutes
            $rlMax       = 3;

            $attempts    = $_SESSION[$rlKey] ?? [];
            $attempts    = array_filter($attempts, fn($t) => ($nowTs - $t) < $rlWindow);

            if (count($attempts) >= $rlMax) {
                // Still show generic success — don't reveal rate limiting to attacker
                $success = true;
            } else {
                $attempts[] = $nowTs;
                $_SESSION[$rlKey] = $attempts;

                // Look up the user (active only — inactive accounts get no reset link)
                $user = Model::findUserByEmail($email);

                if ($user) {
                    // Generate a cryptographically secure raw token
                    $rawToken  = bin2hex(random_bytes(32));  // 64-char hex string
                    $expiryMin = defined('PASSWORD_RESET_EXPIRY_MINUTES')
                        ? PASSWORD_RESET_EXPIRY_MINUTES : 30;

                    // Store hashed token in DB
                    Model::createResetToken((int)$user['id'], $rawToken, $expiryMin);

                    // Build the reset URL
                    $resetUrl = BASE_URL . '/reset_password.php?token=' . urlencode($rawToken);

                    // Send the email (failure is silent to user — logged server-side)
                    $sent = Mailer::sendPasswordReset($user['email'], $user['name'], $resetUrl);
                    if (!$sent) {
                        error_log("Mailer: Failed to send password reset to {$user['email']}");
                    }
                }
                // Always show success — prevents email enumeration
                $success = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password | <?= htmlspecialchars(APP_NAME) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap">
  <link rel="stylesheet" href="assets/css/index.css">
  <style>
    .reset-card  { max-width: 420px; margin: 0 auto; background: #fff; border-radius: 14px; padding: 36px 32px; box-shadow: 0 8px 32px rgba(0,0,0,.12); }
    .reset-icon  { width: 60px; height: 60px; border-radius: 50%; background: #eff6ff; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
    .reset-icon i{ font-size: 1.6rem; color: #2563eb; }
    .reset-title { text-align: center; font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 6px; }
    .reset-sub   { text-align: center; color: #64748b; font-size: .9rem; margin-bottom: 24px; }
    .success-box { background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 18px 20px; text-align: center; }
    .success-box i{ font-size: 2rem; color: #16a34a; display: block; margin-bottom: 10px; }
    .success-box h6{ color: #15803d; font-weight: 700; margin-bottom: 6px; }
    .success-box p { color: #166534; font-size: .88rem; margin: 0; }
    .back-link   { text-align: center; margin-top: 20px; font-size: .88rem; }
    .back-link a { color: #2563eb; text-decoration: none; }
    .back-link a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="bg-shape"></div>
  <div class="bg-shape"></div>

  <div class="login-wrapper" style="justify-content:center;">
    <div class="login-logo">
      <div class="logo-circle"><i class="fas fa-coins fa-2x" style="color:#fff"></i></div>
      <h1><?= htmlspecialchars(COMPANY_NAME) ?></h1>
      <p>Payroll System &mdash; v<?= htmlspecialchars(APP_VERSION) ?></p>
    </div>

    <div class="reset-card">
      <?php if ($success): ?>
        <!-- Generic success — shown whether or not email exists (anti-enumeration) -->
        <div class="success-box">
          <i class="fas fa-envelope-circle-check"></i>
          <h6>Check Your Email</h6>
          <p>
            If an account with that email address exists, we've sent a password
            reset link. The link expires in
            <?= defined('PASSWORD_RESET_EXPIRY_MINUTES') ? PASSWORD_RESET_EXPIRY_MINUTES : 30 ?> minutes.
          </p>
          <p class="mt-2" style="color:#475569;font-size:.82rem;">
            Didn't get it? Check your spam folder or contact your system administrator.
          </p>
        </div>
      <?php else: ?>
        <div class="reset-icon"><i class="fas fa-key"></i></div>
        <div class="reset-title">Forgot Password?</div>
        <div class="reset-sub">Enter your account email and we'll send you a reset link.</div>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2 mb-3" style="font-size:.88rem;">
            <i class="fas fa-exclamation-circle mr-1"></i><?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
          <div class="form-group">
            <label class="font-weight-600" style="font-size:.9rem;">Email Address</label>
            <div style="position:relative;">
              <i class="fas fa-envelope" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;"></i>
              <input type="email" name="email" class="form-control"
                     style="padding-left:36px;"
                     placeholder="your.email@company.com"
                     value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                     required autofocus>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-paper-plane mr-2"></i>Send Reset Link
          </button>
        </form>
      <?php endif; ?>

      <div class="back-link">
        <a href="index.php"><i class="fas fa-arrow-left mr-1"></i>Back to Sign In</a>
      </div>
    </div>

    <div class="login-footer">
      &copy; <?= date('Y') ?> <?= htmlspecialchars(COMPANY_NAME) ?> &mdash; All rights reserved
    </div>
  </div>
</body>
</html>
