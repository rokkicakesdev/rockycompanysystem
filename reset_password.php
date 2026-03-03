<?php
// ============================================================
//  reset_password.php  — Run this ONCE to fix all passwords
//  Place in: rocky-payroll/reset_password.php
//  Open in browser: http://localhost:8000/reset_password.php
//  DELETE this file after use!
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$newPassword = 'password123';
$hash        = password_hash($newPassword, PASSWORD_BCRYPT);

$pdo  = Database::connect();
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username IN ('admin1','admin2','management1')");
$ok   = $stmt->execute([$hash]);

// Also verify it works immediately
$check = $pdo->query("SELECT username, password FROM users")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Password Reset</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
</head>
<body class="hold-transition" style="background:#f4f6f9; padding:40px">
<div class="card" style="max-width:600px; margin:auto">
  <div class="card-header <?= $ok ? 'bg-success' : 'bg-danger' ?> text-white">
    <h4 class="card-title mb-0">
      <?= $ok ? '✅ Passwords Updated Successfully' : '❌ Update Failed' ?>
    </h4>
  </div>
  <div class="card-body">
    <p><strong>New hash generated:</strong></p>
    <code style="word-break:break-all; font-size:.8rem"><?= htmlspecialchars($hash) ?></code>

    <hr>
    <p><strong>Verify — password_verify() test:</strong></p>
    <table class="table table-sm table-bordered">
      <thead><tr><th>Username</th><th>Verify Result</th></tr></thead>
      <tbody>
      <?php foreach($check as $u): ?>
        <tr>
          <td><?= $u['username'] ?></td>
          <td>
            <?php if(password_verify($newPassword, $u['password'])): ?>
              <span class="badge badge-success">✓ password123 works</span>
            <?php else: ?>
              <span class="badge badge-danger">✗ FAILED</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <div class="alert alert-warning mt-3">
      <i class="fas fa-exclamation-triangle"></i>
      <strong>Important:</strong> Delete <code>reset_password.php</code> from your server after this is done!
    </div>

    <a href="index.php" class="btn btn-primary">Go to Login Page</a>
  </div>
</div>
</body>
</html>
