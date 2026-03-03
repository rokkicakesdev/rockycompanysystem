<?php
require_once __DIR__ . '/config/config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === ROLE_ADMIN) {
        header('Location: app/views/admin/dashboard.php');
        exit;
    } elseif ($role === ROLE_MANAGEMENT) {
        header('Location: app/views/management/dashboard.php');
        exit;
    }
}

// Handle login form submission
$error   = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/core/Model.php';

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $user = Model::findUserByUsername($username);
        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Invalid username or password.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account has been deactivated. Please contact your administrator.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['user']    = $user;
            $dest = $user['role'] === ROLE_ADMIN
                ? 'app/views/admin/dashboard.php'
                : 'app/views/management/dashboard.php';
            header('Location: ' . $dest);
            exit;
        }
    }
}

$msgParam = $_GET['msg'] ?? null;
if ($msgParam === 'loggedout') $success = 'You have been successfully signed out.';
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

  <style>
    * { box-sizing: border-box; }
    body {
      font-family: 'Inter', sans-serif;
      background: #1a1f2e;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
    }

    /* Animated background shapes */
    body::before {
      content: '';
      position: fixed; inset: 0;
      background:
        radial-gradient(ellipse at 20% 20%, rgba(60,141,188,.18) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 80%, rgba(0,166,90,.12) 0%, transparent 60%);
      pointer-events: none;
    }
    .bg-shape {
      position: fixed; border-radius: 50%;
      background: rgba(60,141,188,.07);
      animation: float 8s ease-in-out infinite;
    }
    .bg-shape:nth-child(1) { width:400px;height:400px;top:-100px;right:-100px;animation-delay:0s; }
    .bg-shape:nth-child(2) { width:300px;height:300px;bottom:-80px;left:-80px;animation-delay:3s; }
    @keyframes float {
      0%,100% { transform: translateY(0) scale(1); }
      50%      { transform: translateY(-20px) scale(1.05); }
    }

    .login-wrapper {
      width: 100%;
      max-width: 420px;
      padding: 20px;
      position: relative;
      z-index: 10;
      animation: slideUp .5s ease;
    }
    @keyframes slideUp {
      from { opacity:0; transform: translateY(30px); }
      to   { opacity:1; transform: translateY(0); }
    }

    .login-logo {
      text-align: center;
      margin-bottom: 24px;
    }
    .logo-circle {
      width: 72px; height: 72px;
      background: linear-gradient(135deg, #3c8dbc, #00a65a);
      border-radius: 18px;
      display: inline-flex; align-items: center; justify-content: center;
      margin-bottom: 14px;
      box-shadow: 0 8px 24px rgba(60,141,188,.35);
    }
    .login-logo h1 {
      color: #fff;
      font-size: 1.3rem; font-weight: 700;
      margin: 0 0 4px;
      line-height: 1.2;
    }
    .login-logo p {
      color: #7a8bb5;
      font-size: .8rem;
      margin: 0;
    }

    .login-card {
      background: rgba(255,255,255,.04);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 16px;
      padding: 36px;
      box-shadow: 0 20px 60px rgba(0,0,0,.4);
    }

    .login-card h5 {
      color: #fff;
      font-size: .95rem; font-weight: 600;
      margin-bottom: 6px;
    }
    .login-card .subtitle {
      color: #7a8bb5;
      font-size: .78rem;
      margin-bottom: 28px;
    }

    .form-group label {
      color: #a0aec0;
      font-size: .78rem;
      font-weight: 500;
      letter-spacing: .04em;
      text-transform: uppercase;
      margin-bottom: 6px;
    }
    .input-wrap {
      position: relative;
    }
    .input-wrap .input-icon {
      position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
      color: #4a5568; font-size: .9rem;
    }
    .input-wrap input {
      width: 100%;
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 8px;
      color: #e2e8f0;
      padding: 11px 14px 11px 40px;
      font-family: 'Inter', sans-serif;
      font-size: .875rem;
      transition: all .2s;
    }
    .input-wrap input::placeholder { color: #4a5568; }
    .input-wrap input:focus {
      outline: none;
      background: rgba(255,255,255,.1);
      border-color: #3c8dbc;
      box-shadow: 0 0 0 3px rgba(60,141,188,.2);
    }
    .toggle-pw {
      position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
      background: none; border: none;
      color: #4a5568; cursor: pointer; font-size: .9rem;
      transition: color .2s;
    }
    .toggle-pw:hover { color: #3c8dbc; }

    .alert-box {
      border-radius: 8px; padding: 10px 14px;
      font-size: .82rem; margin-bottom: 18px;
      display: flex; align-items: center; gap: 10px;
    }
    .alert-error   { background: rgba(220,53,69,.15); border: 1px solid rgba(220,53,69,.3); color: #f8a8a8; }
    .alert-success { background: rgba(40,167,69,.15);  border: 1px solid rgba(40,167,69,.3);  color: #7edba7; }

    .btn-login {
      width: 100%;
      background: linear-gradient(135deg, #3c8dbc, #2a6f99);
      border: none;
      color: #fff;
      padding: 12px;
      border-radius: 8px;
      font-family: 'Inter', sans-serif;
      font-size: .9rem; font-weight: 600;
      cursor: pointer;
      margin-top: 8px;
      transition: all .2s;
      position: relative; overflow: hidden;
    }
    .btn-login:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 20px rgba(60,141,188,.4);
    }
    .btn-login:active { transform: translateY(0); }

    .login-footer {
      text-align: center;
      margin-top: 24px;
      color: #4a5568;
      font-size: .75rem;
    }

    .demo-accounts {
      background: rgba(255,255,255,.03);
      border: 1px solid rgba(255,255,255,.07);
      border-radius: 8px;
      padding: 12px 16px;
      margin-top: 20px;
    }
    .demo-accounts p {
      color: #7a8bb5; font-size: .72rem;
      text-transform: uppercase; letter-spacing: .06em;
      margin-bottom: 8px; font-weight: 600;
    }
    .demo-row {
      display: flex; justify-content: space-between;
      color: #a0aec0; font-size: .78rem;
      padding: 3px 0;
      border-bottom: 1px solid rgba(255,255,255,.04);
    }
    .demo-row:last-child { border-bottom: 0; }
    .demo-row code { color: #7fc8f8; font-size: .78rem; }
    .role-pill {
      font-size: .65rem; padding: 1px 7px;
      border-radius: 10px; font-weight: 600;
    }
    .pill-admin { background: rgba(60,141,188,.25); color: #7fc8f8; }
    .pill-mgmt  { background: rgba(0,166,90,.25);   color: #7edba7; }
  </style>
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