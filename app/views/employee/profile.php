<?php
// app/views/employee/profile.php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$pageTitle  = 'My Profile';
$employeeId = (int)($_SESSION['employee_id'] ?? 0);

require_once __DIR__ . '/../layouts/employee_header.php';
require_once __DIR__ . '/../../../core/Model.php';

$msg = '';

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ── POST: Update contact info ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Invalid security token.</div>";
    } else {
        require_once __DIR__ . '/../../../core/Validator.php';
        $v = new Validator($_POST);
        $v->phone('phone', 'Phone number')
          ->maxLen('address', 300, 'Address')
          ->maxLen('emergency_contact_name', 100, 'Emergency contact name')
          ->phone('emergency_contact_phone', 'Emergency contact phone')
          ->maxLen('emergency_contact_relation', 50, 'Relationship');
        if ($v->fails()) {
            $msg = $v->errorHtml();
        } else {
            $ok = Model::updateEmployeeProfile($employeeId, [
            'phone'                      => trim($_POST['phone']                      ?? ''),
            'address'                    => trim($_POST['address']                    ?? ''),
            'emergency_contact_name'     => trim($_POST['emergency_contact_name']     ?? ''),
            'emergency_contact_phone'    => trim($_POST['emergency_contact_phone']    ?? ''),
            'emergency_contact_relation' => trim($_POST['emergency_contact_relation'] ?? ''),
        ]);
        if ($ok) {
            Model::log($_SESSION['user_id'], 'UPDATE_PROFILE', "Employee ID:{$employeeId} updated their profile");
            $msg = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>Profile updated successfully.</div>";
        } else {
            $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Failed to update profile. Please try again.</div>";
            }
        }
    }
}

// ── POST: Change password ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Invalid security token.</div>";
    } else {
        $currentPw  = $_POST['current_password']  ?? '';
        $newPw      = $_POST['new_password']       ?? '';
        $confirmPw  = $_POST['confirm_password']   ?? '';

        // Fetch current user to verify password
        $user = Model::findUserById($_SESSION['user_id']);

        if (!$user || !password_verify($currentPw, $user['password'])) {
            $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Current password is incorrect.</div>";
        } elseif (strlen($newPw) < 8) {
            $msg = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle mr-2'></i>New password must be at least 8 characters.</div>";
        } elseif (!hash_equals($newPw, $confirmPw)) {
            $msg = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle mr-2'></i>New passwords do not match.</div>";
        } else {
            Model::updateUserPassword($_SESSION['user_id'], $newPw);
            Model::log($_SESSION['user_id'], 'CHANGE_PASSWORD', "Employee changed their password");
            $msg = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>Password changed successfully.</div>";
        }
    }
}

// Reload fresh employee data
$employee = $employeeId ? Model::findEmployeeById($employeeId) : null;

// Leave balances map
$leaveBalances = [
    'Sick Leave'       => $employee['sick_leave_balance']         ?? 0,
    'Vacation Leave'   => $employee['vacation_leave_balance']     ?? 0,
    'Emergency Leave'  => $employee['emergency_leave_balance']    ?? 0,
    'SIL'              => $employee['sil_balance']                ?? 0,
    'Bereavement'      => $employee['bereavement_leave_balance']  ?? 0,
];
?>

<div class="page-title-bar">
  <i class="fas fa-user-circle text-primary"></i>
  <h1>My Profile</h1>
</div>

<?= $msg ?>

<div class="row">

  <!-- ── LEFT COLUMN ─────────────────────────────────────── -->
  <div class="col-lg-4">

    <!-- Profile Card -->
    <div class="card text-center mb-4">
      <div class="card-body py-4">
        <!-- Avatar -->
        <div class="employee-avatar mx-auto mb-3 emp-profile-avatar">
          <?= strtoupper(mb_substr($employee['name'] ?? 'E', 0, 1)) ?>
        </div>
        <h5 class="font-weight-bold mb-0"><?= htmlspecialchars($employee['name'] ?? '—') ?></h5>
        <p class="text-muted mb-1 emp-profile-position"><?= htmlspecialchars($employee['position'] ?? '—') ?></p>
        <span class="badge badge-primary px-3 py-1"><?= htmlspecialchars($employee['department'] ?? '—') ?></span>
        <hr>
        <div class="text-left emp-profile-info-block">
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted">Employee No.</span>
            <strong><code><?= htmlspecialchars($employee['employee_no'] ?? '—') ?></code></strong>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted">Employment</span>
            <strong><?= ucfirst(str_replace('_', ' ', $employee['employment_type'] ?? '—')) ?></strong>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted">Date Start</span>
            <strong><?= ($employee['date_start'] ?? $employee['date_hired'] ?? null) ? date('M d, Y', strtotime($employee['date_start'] ?? $employee['date_hired'])) : '—' ?></strong>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted">Date Hired</span>
            <strong><?= $employee['date_hired'] ? date('M d, Y', strtotime($employee['date_hired'])) : '—' ?></strong>
          </div>
          <div class="d-flex justify-content-between py-1">
            <span class="text-muted">Status</span>
            <span class="status-badge badge-<?= $employee['status'] ?? 'active' ?>"><?= ucfirst($employee['status'] ?? 'Active') ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Leave Balances -->
    <div class="card mb-4">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-calendar-check mr-2 text-warning"></i>Leave Balances</h3>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <?php foreach ($leaveBalances as $label => $bal): ?>
          <tr>
            <td class="emp-profile-table-cell"><?= $label ?></td>
            <td class="text-right">
              <span class="font-weight-bold <?= $bal > 0 ? 'text-success' : 'text-muted' ?>">
                <?= number_format((float)$bal, 1) ?> days
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>

  </div><!-- /.col-lg-4 -->

  <!-- ── RIGHT COLUMN ────────────────────────────────────── -->
  <div class="col-lg-8">

    <!-- Personal Information (read-only) -->
    <div class="card mb-4">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-id-card mr-2 text-primary"></i>Personal Information</h3>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <?php
            $infoLeft = [
              'Full Name'    => $employee['name']         ?? '—',
              'Gender'       => ucfirst($employee['gender'] ?? '—'),
              'Civil Status' => ucfirst(str_replace('_', ' ', $employee['civil_status'] ?? '—')),
              'Birthdate'    => $employee['birthdate'] ? date('F d, Y', strtotime($employee['birthdate'])) : '—',
            ];
            foreach ($infoLeft as $label => $val): ?>
            <div class="form-group mb-2">
              <label class="text-muted mb-0 emp-profile-field-label"><?= $label ?></label>
              <p class="mb-0 font-weight-bold emp-profile-field-value"><?= htmlspecialchars($val) ?></p>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="col-md-6">
            <?php
            $infoRight = [
              'SSS No.'        => $employee['sss_no']        ?? '—',
              'PhilHealth No.' => $employee['philhealth_no'] ?? '—',
              'Pag-IBIG No.'   => $employee['pagibig_no']    ?? '—',
              'TIN No.'        => $employee['tin_no']        ?? '—',
            ];
            foreach ($infoRight as $label => $val): ?>
            <div class="form-group mb-2">
              <label class="text-muted mb-0 emp-profile-field-label"><?= $label ?></label>
              <p class="mb-0 font-weight-bold emp-profile-field-value"><?= htmlspecialchars($val) ?></p>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group mb-0 mt-2">
          <label class="text-muted mb-0 emp-profile-field-label">Email</label>
          <p class="mb-0 font-weight-bold emp-profile-field-value"><?= htmlspecialchars($employee['email'] ?? '—') ?></p>
        </div>
        <small class="text-muted mt-2 d-block"><i class="fas fa-lock mr-1"></i>Personal details can only be updated by HR/Admin.</small>
      </div>
    </div>

    <!-- Editable Contact Info -->
    <div class="card mb-4">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-address-book mr-2 text-info"></i>Contact &amp; Emergency Info
          <small class="text-muted ml-2 emp-profile-editable-note">You can update these</small>
        </h3>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="update_profile" value="1">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" class="form-control"
                  value="<?= htmlspecialchars($employee['phone'] ?? '') ?>"
                  placeholder="e.g. 09171234567"
                  maxlength="15" autocomplete="off">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Home Address</label>
                <input type="text" name="address" class="form-control"
                  value="<?= htmlspecialchars($employee['address'] ?? '') ?>"
                  placeholder="Street, City, Province"
                  maxlength="300" autocomplete="off">
              </div>
            </div>
          </div>

          <hr class="my-3">
          <h6 class="text-muted mb-3 emp-profile-section-heading">
            <i class="fas fa-phone-alt mr-1"></i>Emergency Contact
          </h6>

          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Name</label>
                <input type="text" name="emergency_contact_name" class="form-control"
                  value="<?= htmlspecialchars($employee['emergency_contact_name'] ?? '') ?>"
                  placeholder="Full name"
                  maxlength="150" autocomplete="off">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Phone</label>
                <input type="tel" name="emergency_contact_phone" class="form-control"
                  value="<?= htmlspecialchars($employee['emergency_contact_phone'] ?? '') ?>"
                  placeholder="Contact number"
                  maxlength="15" autocomplete="off">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Relationship</label>
                <input type="text" name="emergency_contact_relation" class="form-control"
                  value="<?= htmlspecialchars($employee['emergency_contact_relation'] ?? '') ?>"
                  placeholder="e.g. Spouse, Parent"
                  maxlength="50" autocomplete="off">
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save mr-1"></i> Save Changes
          </button>
        </form>
      </div>
    </div>

    <!-- Change Password -->
    <div class="card mb-4">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-lock mr-2 text-danger"></i>Change Password</h3>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="change_password" value="1">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" class="form-control" required placeholder="••••••••" maxlength="128" autocomplete="current-password">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" id="newPw" class="form-control" required
                  minlength="8" maxlength="128" placeholder="Min. 8 characters" autocomplete="new-password">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirmPw" class="form-control" required placeholder="Repeat new password" maxlength="128" autocomplete="new-password">
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-key mr-1"></i> Change Password
          </button>
        </form>
      </div>
    </div>

  </div><!-- /.col-lg-8 -->
</div><!-- /.row -->

<script>
// Confirm password match visual feedback
document.getElementById('newPw').addEventListener('input', function() {
  const ok = this.value.length >= 8;
  this.classList.toggle('is-invalid', !ok && this.value.length > 0);
  this.classList.toggle('is-valid',   ok);
});
document.getElementById('confirmPw').addEventListener('input', function() {
  const match = this.value === document.getElementById('newPw').value;
  this.classList.toggle('is-invalid', !match && this.value.length > 0);
  this.classList.toggle('is-valid',   match  && this.value.length > 0);
});
</script>

<?php require_once __DIR__ . '/../layouts/employee_footer.php'; ?>