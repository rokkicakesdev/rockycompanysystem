<?php
// change_password.php
// ─────────────────────────────────────────────────────────────────────────────
//  Forced password change page — shown when must_change_password = 1.
//
//  This page intentionally has NO sidebar/navbar so the user cannot navigate
//  away without completing the password change. Both headers enforce a redirect
//  to this page when must_change_password = 1 on the user's session.
//
//  Password requirements (Philippines data-privacy best practice 2026):
//    • Minimum 8 characters
//    • At least 1 uppercase letter
//    • At least 1 lowercase letter
//    • At least 1 number
//    • At least 1 special character
//    • Cannot be the same as the current password
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Model.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Must be logged in to reach this page
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// If they've already changed their password, redirect them home
if (empty($_SESSION['must_change_password'])) {
    $role = $_SESSION['role'] ?? '';
    $redirect = match ($role) {
        ROLE_ADMIN, ROLE_MANAGEMENT => BASE_URL . '/app/views/admin/dashboard.php',
        'employee'                  => BASE_URL . '/app/views/employee/dashboard.php',
        default                     => BASE_URL . '/index.php',
    };
    header('Location: ' . $redirect);
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$error      = '';
$success    = '';

// ── Password strength validator ───────────────────────────────────────────────
function meetsPasswordPolicy(string $pw): array
{
    $errors = [];
    if (strlen($pw) < 8)                          $errors[] = 'At least 8 characters';
    if (!preg_match('/[A-Z]/', $pw))              $errors[] = 'At least one uppercase letter (A–Z)';
    if (!preg_match('/[a-z]/', $pw))              $errors[] = 'At least one lowercase letter (a–z)';
    if (!preg_match('/[0-9]/', $pw))              $errors[] = 'At least one number (0–9)';
    if (!preg_match('/[\W_]/', $pw))              $errors[] = 'At least one special character (!@#$%^&* etc.)';
    return $errors;
}

// ── POST handler ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page.';
    } else {
        $currentPw  = $_POST['current_password'] ?? '';
        $newPw      = $_POST['new_password']      ?? '';
        $confirmPw  = $_POST['confirm_password']  ?? '';
        $userId     = (int)$_SESSION['user_id'];

        $user = Model::findUserById($userId);

        if (!$user || !password_verify($currentPw, $user['password'])) {
            $error = 'Your current password is incorrect.';
        } elseif ($newPw !== $confirmPw) {
            $error = 'New passwords do not match.';
        } elseif (password_verify($newPw, $user['password'])) {
            $error = 'Your new password cannot be the same as your current password.';
        } else {
            $policyErrors = meetsPasswordPolicy($newPw);
            if (!empty($policyErrors)) {
                $error = 'Password does not meet requirements: ' . implode('; ', $policyErrors) . '.';
            } else {
                // Save the new password — updatePassword() clears must_change_password and records timestamp
                Model::updateUserPassword($userId, $newPw);
                Model::log($userId, 'FORCED_PASSWORD_CHANGE', 'User completed mandatory password change.');

                // Clear the session flag
                unset($_SESSION['must_change_password']);

                // Redirect to their dashboard
                $role     = $_SESSION['role'] ?? '';
                $redirect = match ($role) {
                    ROLE_ADMIN, ROLE_MANAGEMENT => BASE_URL . '/app/views/admin/dashboard.php',
                    'employee'                  => BASE_URL . '/app/views/employee/dashboard.php',
                    default                     => BASE_URL . '/index.php',
                };
                header('Location: ' . $redirect . '?msg=password_changed');
                exit;
            }
        }
    }
}

$userName = htmlspecialchars($_SESSION['name'] ?? 'User');
$appName  = defined('COMPANY_NAME') ? COMPANY_NAME : 'Rocky Company';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Change Password | <?= htmlspecialchars($appName) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Inter', sans-serif;
      background: #1a1f2e;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    body::before {
      content: '';
      position: fixed; inset: 0;
      background:
        radial-gradient(ellipse at 20% 20%, rgba(60,141,188,.18) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 80%, rgba(220,38,38,.10) 0%, transparent 60%);
      pointer-events: none;
    }

    .wrapper {
      width: 100%; max-width: 460px;
      position: relative; z-index: 10;
      animation: slideUp .45s ease both;
    }
    @keyframes slideUp {
      from { opacity: 0; transform: translateY(24px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* Logo / header */
    .page-logo {
      text-align: center;
      margin-bottom: 22px;
    }
    .logo-circle {
      width: 60px; height: 60px;
      background: linear-gradient(135deg, #3c8dbc, #00a65a);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 12px;
      font-size: 1.5rem; color: #fff;
      box-shadow: 0 4px 20px rgba(60,141,188,.4);
    }
    .page-logo h1 { color: #fff; font-size: 1.3rem; font-weight: 700; }
    .page-logo p  { color: #94a3b8; font-size: .82rem; margin-top: 3px; }

    /* Card */
    .card {
      background: #fff;
      border-radius: 16px;
      padding: 32px 32px 28px;
      box-shadow: 0 20px 60px rgba(0,0,0,.35);
    }

    /* Mandatory notice banner */
    .notice-banner {
      background: #fef3c7;
      border: 1.5px solid #f59e0b;
      border-radius: 10px;
      padding: 12px 16px;
      margin-bottom: 22px;
      display: flex;
      gap: 10px;
      align-items: flex-start;
    }
    .notice-banner i { color: #d97706; margin-top: 2px; font-size: 1rem; flex-shrink: 0; }
    .notice-banner strong { display: block; color: #92400e; font-size: .88rem; margin-bottom: 2px; }
    .notice-banner p  { color: #78350f; font-size: .8rem; line-height: 1.45; margin: 0; }

    h2 {
      font-size: 1.15rem; font-weight: 700;
      color: #1e293b; margin-bottom: 6px;
    }
    .subtitle { color: #64748b; font-size: .83rem; margin-bottom: 22px; }

    /* Alert */
    .alert {
      border-radius: 8px; padding: 10px 14px;
      font-size: .84rem; margin-bottom: 16px;
      display: flex; align-items: center; gap: 8px;
    }
    .alert-danger  { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }

    /* Form */
    .form-group { margin-bottom: 18px; }
    label {
      display: block; font-size: .82rem;
      font-weight: 600; color: #374151;
      margin-bottom: 6px;
    }
    .input-wrap {
      position: relative;
    }
    .input-wrap i.input-icon {
      position: absolute; left: 12px; top: 50%;
      transform: translateY(-50%);
      color: #94a3b8; font-size: .85rem; pointer-events: none;
    }
    .input-wrap input {
      width: 100%; padding: 10px 40px 10px 36px;
      border: 1.5px solid #e2e8f0; border-radius: 9px;
      font-size: .9rem; font-family: inherit;
      transition: border-color .15s, box-shadow .15s;
      outline: none;
    }
    .input-wrap input:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59,130,246,.12);
    }
    .toggle-pw {
      position: absolute; right: 10px; top: 50%;
      transform: translateY(-50%);
      background: none; border: none;
      color: #94a3b8; cursor: pointer; padding: 4px;
      font-size: .85rem;
    }
    .toggle-pw:hover { color: #475569; }

    /* Password requirements checklist */
    .pw-rules {
      margin-top: 10px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 10px 14px;
      list-style: none;
    }
    .pw-rules li {
      font-size: .78rem; color: #64748b;
      padding: 2px 0;
      display: flex; align-items: center; gap: 7px;
    }
    .pw-rules li i { font-size: .7rem; width: 12px; }
    .pw-rules li.met     { color: #16a34a; }
    .pw-rules li.met i   { color: #16a34a; }
    .pw-rules li.unmet i { color: #cbd5e1; }

    /* Strength bar */
    .strength-bar-wrap {
      margin-top: 8px; height: 5px;
      background: #e2e8f0; border-radius: 3px; overflow: hidden;
    }
    .strength-bar {
      height: 100%; border-radius: 3px;
      transition: width .25s, background .25s;
      width: 0;
    }

    /* Submit button */
    .btn-submit {
      width: 100%; padding: 12px;
      background: linear-gradient(135deg, #1e3a5f, #1a6e4a);
      color: #fff; border: none; border-radius: 10px;
      font-size: .95rem; font-weight: 600;
      cursor: pointer; font-family: inherit;
      transition: opacity .15s, transform .1s;
      margin-top: 4px;
    }
    .btn-submit:hover   { opacity: .92; transform: translateY(-1px); }
    .btn-submit:active  { transform: translateY(0); }

    .logout-link {
      text-align: center; margin-top: 16px;
      font-size: .8rem; color: #94a3b8;
    }
    .logout-link a { color: #64748b; text-decoration: none; }
    .logout-link a:hover { color: #1e293b; text-decoration: underline; }
  </style>
</head>
<body>

<div class="wrapper">

  <!-- Logo -->
  <div class="page-logo">
    <div class="logo-circle"><i class="fas fa-shield-alt"></i></div>
    <h1><?= htmlspecialchars($appName) ?></h1>
    <p>Payroll System &mdash; Security Update Required</p>
  </div>

  <!-- Card -->
  <div class="card">

    <!-- Mandatory notice -->
    <div class="notice-banner">
      <i class="fas fa-exclamation-triangle"></i>
      <div>
        <strong>Password Change Required</strong>
        <p>Hello, <?= $userName ?>. Your account requires a new password before you can continue.
           This is required for all new and reset accounts to protect your payroll data.</p>
      </div>
    </div>

    <h2><i class="fas fa-key mr-2" style="color:#3b82f6;"></i>Set Your New Password</h2>
    <p class="subtitle">Choose a strong password that you haven't used before.</p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off" id="cpForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

      <div class="form-group">
        <label for="current_password">Current Password</label>
        <div class="input-wrap">
          <i class="fas fa-lock input-icon"></i>
          <input type="password" id="current_password" name="current_password"
                 placeholder="Enter your current password" required autocomplete="current-password">
          <button type="button" class="toggle-pw" onclick="togglePw('current_password','eye1')">
            <i class="fas fa-eye" id="eye1"></i>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label for="new_password">New Password</label>
        <div class="input-wrap">
          <i class="fas fa-lock-open input-icon"></i>
          <input type="password" id="new_password" name="new_password"
                 placeholder="Choose a strong new password" required autocomplete="new-password"
                 oninput="checkStrength(this.value)">
          <button type="button" class="toggle-pw" onclick="togglePw('new_password','eye2')">
            <i class="fas fa-eye" id="eye2"></i>
          </button>
        </div>
        <!-- Strength bar -->
        <div class="strength-bar-wrap"><div class="strength-bar" id="strengthBar"></div></div>
        <!-- Requirements checklist -->
        <ul class="pw-rules" id="pwRules">
          <li id="r-len"  class="unmet"><i class="fas fa-circle"></i>At least 8 characters</li>
          <li id="r-up"   class="unmet"><i class="fas fa-circle"></i>At least one uppercase letter</li>
          <li id="r-lo"   class="unmet"><i class="fas fa-circle"></i>At least one lowercase letter</li>
          <li id="r-num"  class="unmet"><i class="fas fa-circle"></i>At least one number</li>
          <li id="r-spec" class="unmet"><i class="fas fa-circle"></i>At least one special character</li>
        </ul>
      </div>

      <div class="form-group">
        <label for="confirm_password">Confirm New Password</label>
        <div class="input-wrap">
          <i class="fas fa-check-circle input-icon"></i>
          <input type="password" id="confirm_password" name="confirm_password"
                 placeholder="Re-enter your new password" required autocomplete="new-password">
          <button type="button" class="toggle-pw" onclick="togglePw('confirm_password','eye3')">
            <i class="fas fa-eye" id="eye3"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-submit">
        <i class="fas fa-shield-alt mr-2"></i>Set New Password &amp; Continue
      </button>
    </form>

    <div class="logout-link">
      <a href="logout.php"><i class="fas fa-sign-out-alt mr-1"></i>Sign out instead</a>
    </div>
  </div>
</div>

<script>
function togglePw(fieldId, iconId) {
  var f = document.getElementById(fieldId);
  var i = document.getElementById(iconId);
  if (f.type === 'password') {
    f.type = 'text';
    i.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    f.type = 'password';
    i.classList.replace('fa-eye-slash', 'fa-eye');
  }
}

function checkStrength(pw) {
  var rules = {
    'r-len':  pw.length >= 8,
    'r-up':   /[A-Z]/.test(pw),
    'r-lo':   /[a-z]/.test(pw),
    'r-num':  /[0-9]/.test(pw),
    'r-spec': /[\W_]/.test(pw),
  };
  var passed = 0;
  for (var id in rules) {
    var li = document.getElementById(id);
    var ico = li.querySelector('i');
    if (rules[id]) {
      li.className = 'met';
      ico.className = 'fas fa-check-circle';
      passed++;
    } else {
      li.className = 'unmet';
      ico.className = 'fas fa-circle';
    }
  }
  var bar   = document.getElementById('strengthBar');
  var pct   = (passed / 5) * 100;
  var color = passed <= 1 ? '#ef4444' : passed <= 2 ? '#f59e0b' : passed <= 3 ? '#eab308' : passed === 4 ? '#22c55e' : '#16a34a';
  bar.style.width      = pct + '%';
  bar.style.background = color;
}
</script>

</body>
</html>
