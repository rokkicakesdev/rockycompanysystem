<?php
// ============================================================
//  reset_password.php — Debug/recovery tool (localhost only)
// ============================================================

// SECURITY: Block all non-local access immediately
$_allowed = ['127.0.0.1', '::1'];
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $_allowed, true)) {
    http_response_code(403);
    die('403 Forbidden');
}
unset($_allowed);

// Load config and core classes (not loaded via router when accessed directly)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Database.php';

$newPassword = 'admin123';
$hash        = password_hash($newPassword, PASSWORD_BCRYPT);

$pdo  = Database::getInstance();
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username IN ('admin1','admin2','admin3','management1','juan.emp','ana.emp','pedro.emp','luz.emp','marco.emp','elena.emp','jose.emp','carla.emp','ricardo.emp','marites.emp')");
$ok   = $stmt->execute([$hash]);

$check = $pdo->query("SELECT username, role, password FROM users ORDER BY role, username")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Password Reset — Rocky HRIS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
</head>
<body class="hold-transition" style="background:#f4f6f9; padding:40px">
<div class="card" style="max-width:640px; margin:auto">
  <div class="card-header <?= $ok ? 'bg-success' : 'bg-danger' ?> text-white">
    <h4 class="card-title mb-0">
      <?= $ok ? '&#10003; Passwords Reset Successfully' : '&#10007; Reset Failed' ?>
    </h4>
  </div>
  <div class="card-body">
    <p><strong>Password set to:</strong> <code><?= htmlspecialchars($newPassword) ?></code></p>
    <p><strong>Hash generated:</strong></p>
    <code style="word-break:break-all; font-size:.8rem"><?= htmlspecialchars($hash) ?></code>

    <hr>
    <p><strong>Verification — all users:</strong></p>
    <table class="table table-sm table-bordered">
      <thead><tr><th>Username</th><th>Role</th><th>Result</th></tr></thead>
      <tbody>
        <?php foreach ($check as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= htmlspecialchars($u['role']) ?></td>
          <td>
            <?php if (password_verify($newPassword, $u['password'])): ?>
              <span class="badge badge-success">&#10003; OK</span>
            <?php else: ?>
              <span class="badge badge-danger">&#10007; FAILED</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="alert alert-warning mt-3">
      <i class="fas fa-exclamation-triangle"></i>
      <strong>Reminder:</strong> This file is localhost-only protected. Do not deploy to production without removing it.
    </div>

    <a href="index.php" class="btn btn-primary">Go to Login</a>
  </div>
</div>
</body>
</html>