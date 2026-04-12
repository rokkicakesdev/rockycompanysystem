<?php
// reset_password.php — Step 2: validate token + set new password
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Model.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$rawToken = trim($_GET['token'] ?? '');
$error    = '';
$success  = false;

$tokenValid = false;
if ($rawToken !== '') {
    try { $tokenValid = Model::isResetTokenValid($rawToken); } catch (\Throwable $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postToken = trim($_POST['reset_token'] ?? '');
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } elseif ($postToken === '') {
        $error = 'Missing reset token. Please use the link from your email.';
    } else {
        $newPw     = $_POST['new_password']     ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';
        if (strlen($newPw) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/[a-zA-Z]/', $newPw)) {
            $error = 'Password must contain at least one letter.';
        } elseif (!preg_match('/[0-9]/', $newPw)) {
            $error = 'Password must contain at least one number.';
        } elseif ($newPw !== $confirmPw) {
            $error = 'Passwords do not match.';
        } else {
            try { $userRow = Model::consumeResetToken($postToken); }
            catch (\Throwable $e) { $userRow = null; }

            if (!$userRow) {
                $error = 'This reset link is invalid or has already been used. Please request a new one.';
            } else {
                Model::updateUserPassword((int)$userRow['user_id'], $newPw);
                Model::log((int)$userRow['user_id'], 'PASSWORD_RESET',
                    "Password reset via email link for user '{$userRow['username']}'");
                session_regenerate_id(true);
                $_SESSION = [];
                $success  = true;
            }
        }
    }
}
$expMin = defined('PASSWORD_RESET_EXPIRY_MINUTES') ? PASSWORD_RESET_EXPIRY_MINUTES : 30;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password | <?= htmlspecialchars(APP_NAME) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap">
  <link rel="stylesheet" href="assets/css/index.css">
  <style>
    .reset-card{max-width:440px;margin:0 auto;background:#fff;border-radius:14px;padding:36px 32px;box-shadow:0 8px 32px rgba(0,0,0,.12);}
    .reset-icon{width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;}
    .reset-title{text-align:center;font-size:1.25rem;font-weight:700;color:#1e293b;margin-bottom:6px;}
    .reset-sub{text-align:center;color:#64748b;font-size:.9rem;margin-bottom:24px;}
    .success-box{background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:22px 20px;text-align:center;}
    .success-box i{font-size:2.2rem;color:#16a34a;display:block;margin-bottom:12px;}
    .success-box h6{color:#15803d;font-weight:700;margin-bottom:8px;}
    .success-box p{color:#166534;font-size:.88rem;margin:0;}
    .pw-req{font-size:.78rem;color:#64748b;margin-top:3px;}
    .pw-req.met{color:#16a34a;}
    .pw-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;}
    .back-link{text-align:center;margin-top:20px;font-size:.88rem;}
    .back-link a{color:#2563eb;text-decoration:none;}
  </style>
</head>
<body>
  <div class="bg-shape"></div><div class="bg-shape"></div>
  <div class="login-wrapper" style="justify-content:center;">
    <div class="login-logo">
      <div class="logo-circle"><i class="fas fa-coins fa-2x" style="color:#fff"></i></div>
      <h1><?= htmlspecialchars(COMPANY_NAME) ?></h1>
      <p>Payroll System &mdash; v<?= htmlspecialchars(APP_VERSION) ?></p>
    </div>
    <div class="reset-card">
      <?php if ($success): ?>
        <div class="success-box">
          <i class="fas fa-shield-check"></i>
          <h6>Password Updated!</h6>
          <p>Your password has been changed successfully.</p>
          <a href="index.php" class="btn btn-success btn-sm mt-3">
            <i class="fas fa-sign-in-alt mr-1"></i>Sign In Now
          </a>
        </div>
      <?php elseif ($rawToken === '' || !$tokenValid): ?>
        <div class="reset-icon" style="background:#fef2f2;">
          <i class="fas fa-link-slash" style="color:#dc2626;font-size:1.5rem;"></i>
        </div>
        <div class="reset-title">Link Invalid or Expired</div>
        <div class="reset-sub">
          This password reset link is invalid, has already been used, or has expired
          (links expire after <?= $expMin ?> minutes).
        </div>
        <a href="forgot_password.php" class="btn btn-primary btn-block">
          <i class="fas fa-redo mr-2"></i>Request a New Link
        </a>
      <?php else: ?>
        <div class="reset-icon" style="background:#eff6ff;">
          <i class="fas fa-lock-open" style="color:#2563eb;font-size:1.5rem;"></i>
        </div>
        <div class="reset-title">Set New Password</div>
        <div class="reset-sub">Choose a strong password for your account.</div>
        <?php if ($error): ?>
          <div class="alert alert-danger py-2 mb-3" style="font-size:.88rem;">
            <i class="fas fa-exclamation-circle mr-1"></i><?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>
        <form method="POST" action="reset_password.php" id="resetForm">
          <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf_token) ?>">
          <input type="hidden" name="reset_token" value="<?= htmlspecialchars($rawToken) ?>">
          <div class="form-group">
            <label class="font-weight-600" style="font-size:.9rem;">New Password <span class="text-danger">*</span></label>
            <div style="position:relative;">
              <i class="fas fa-lock" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;"></i>
              <input type="password" name="new_password" id="newPw" class="form-control"
                     style="padding-left:36px;padding-right:40px;"
                     placeholder="Min 8 chars with a letter and number" required minlength="8" autocomplete="new-password">
              <button type="button" class="pw-toggle" onclick="togglePw('newPw','eye1')"><i class="fas fa-eye" id="eye1"></i></button>
            </div>
            <div class="pw-req" id="lenReq">✗ At least 8 characters</div>
            <div class="pw-req" id="letReq">✗ At least one letter</div>
            <div class="pw-req" id="numReq">✗ At least one number</div>
          </div>
          <div class="form-group">
            <label class="font-weight-600" style="font-size:.9rem;">Confirm Password <span class="text-danger">*</span></label>
            <div style="position:relative;">
              <i class="fas fa-lock" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;"></i>
              <input type="password" name="confirm_password" id="confirmPw" class="form-control"
                     style="padding-left:36px;padding-right:40px;"
                     placeholder="Re-enter your new password" required autocomplete="new-password">
              <button type="button" class="pw-toggle" onclick="togglePw('confirmPw','eye2')"><i class="fas fa-eye" id="eye2"></i></button>
            </div>
            <div class="pw-req" id="matchReq">✗ Passwords must match</div>
          </div>
          <button type="submit" class="btn btn-primary btn-block" id="submitBtn" disabled>
            <i class="fas fa-shield-alt mr-2"></i>Update Password
          </button>
        </form>
      <?php endif; ?>
      <div class="back-link"><a href="index.php"><i class="fas fa-arrow-left mr-1"></i>Back to Sign In</a></div>
    </div>
    <div class="login-footer">&copy; <?= date('Y') ?> <?= htmlspecialchars(COMPANY_NAME) ?> &mdash; All rights reserved</div>
  </div>
<script>
function togglePw(f,i){var el=document.getElementById(f),ic=document.getElementById(i);el.type=el.type==='password'?'text':'password';ic.className=el.type==='password'?'fas fa-eye':'fas fa-eye-slash';}
(function(){
  var np=document.getElementById('newPw'),cp=document.getElementById('confirmPw'),sb=document.getElementById('submitBtn');
  if(!np)return;
  function chk(id,ok,msg){var el=document.getElementById(id);if(!el)return;el.className='pw-req'+(ok?' met':'');el.textContent=(ok?'✓':'✗')+' '+msg;}
  function run(){
    var pw=np.value,cpw=cp.value;
    var l=pw.length>=8,lt=/[a-zA-Z]/.test(pw),n=/[0-9]/.test(pw),m=pw===cpw&&cpw.length>0;
    chk('lenReq',l,'At least 8 characters');
    chk('letReq',lt,'At least one letter');
    chk('numReq',n,'At least one number');
    chk('matchReq',m,'Passwords must match');
    if(sb)sb.disabled=!(l&&lt&&n&&m);
  }
  np.addEventListener('input',run);cp.addEventListener('input',run);
})();
</script>
</body>
</html>
