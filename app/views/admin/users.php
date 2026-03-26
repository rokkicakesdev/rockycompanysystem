<?php
$pageTitle = 'User Management';
require_once __DIR__ . '/../layouts/admin_header.php';
// Only admin
if ($_SESSION['role'] !== ROLE_ADMIN) { header('Location: dashboard.php'); exit; }

$msg = '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token. Please refresh and try again.</div>";
    } else {
        require_once __DIR__ . '/../../../core/Validator.php';
        $v = new Validator($_POST);
        $v->required('name', 'Full name')->maxLen('name', 100, 'Full name')
          ->required('username', 'Username')->maxLen('username', 50, 'Username')
          ->required('email', 'Email')->email('email', 'Email')
          ->required('password', 'Password')->minLen('password', 8, 'Password')
          ->inList('role', ['admin', 'management', 'employee'], 'Role');
        if ($v->fails()) {
            $msg = $v->errorHtml();
        } elseif (Model::createUser([
            'name'       => trim($_POST['name']),
            'username'   => trim($_POST['username']),
            'email'      => trim($_POST['email']),
            'password'   => $_POST['password'],
            'role'       => $_POST['role'],
            'status'     => 'active',
            'created_by' => $_SESSION['user_id'],
        ])) {
            Model::log($_SESSION['user_id'], 'CREATE_USER', "Created user: " . $_POST['username']);
            $msg = "<div class='alert alert-success alert-auto-dismiss'>User created successfully.</div>";
        } else {
            $msg = "<div class='alert alert-danger'>Failed to create user. Username or email may already exist.</div>";
        }
    }
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token. Please refresh and try again.</div>";
    } else {
        require_once __DIR__ . '/../../../core/Validator.php';
        $v = new Validator($_POST);
        $v->required('name', 'Full name')->maxLen('name', 100, 'Full name')
          ->required('email', 'Email')->email('email', 'Email')
          ->inList('role', ['admin', 'management', 'employee'], 'Role')
          ->inList('status', ['active', 'inactive'], 'Status');
        if (!empty($_POST['new_password'])) {
            $v->minLen('new_password', 8, 'New password');
        }
        if ($v->fails()) {
            $msg = $v->errorHtml();
        } else {
            Model::updateUser((int)$_POST['user_id'], [
                'name'   => trim($_POST['name']),
                'email'  => trim($_POST['email']),
                'role'   => $_POST['role'],
                'status' => $_POST['status'],
            ]);
            if (!empty($_POST['new_password'])) {
                Model::updateUserPassword((int)$_POST['user_id'], $_POST['new_password']);
            }
            Model::log($_SESSION['user_id'], 'UPDATE_USER', "Updated user ID:" . $_POST['user_id']);
            $msg = "<div class='alert alert-success alert-auto-dismiss'>User updated successfully.</div>";
        }
    }
}

// Handle toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token. Please refresh and try again.</div>";
    } else {
        $toggleId  = (int)($_POST['user_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';
        if ($toggleId && in_array($newStatus, ['active', 'inactive'], true) && $toggleId !== (int)$_SESSION['user_id']) {
            Model::updateUserStatus($toggleId, $newStatus);
            $label = ucfirst($newStatus);
            Model::log($_SESSION['user_id'], 'TOGGLE_USER_STATUS', "Set user ID:{$toggleId} to {$label}");
            $msg = "<div class='alert alert-success alert-auto-dismiss'>User status updated to {$label}.</div>";
        }
    }
}

$users = Model::getAllUsers();
?>

<!-- ─── Page header ──────────────────────────────────────────────── -->
<div class="page-title-bar">
  <i class="fas fa-user-shield text-primary"></i>
  <h1>User Management</h1>
  <button class="btn btn-sm btn-primary ml-auto" data-toggle="modal" data-target="#createUserModal">
    <i class="fas fa-plus mr-1"></i> Add User
  </button>
</div>

<?= $msg ?>

<!-- ─── Table card ───────────────────────────────────────────────── -->
<div class="um-card um-card-mb">
  <div class="um-table-scroll">
    <table class="um-table">
      <thead>
        <tr>
          <th class="col-name">Name</th>
          <th class="col-username">Username</th>
          <th class="col-email">Email</th>
          <th class="col-role">Role</th>
          <th class="col-status">Status</th>
          <th class="col-created">Created</th>
          <th class="col-actions">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <!-- Name -->
          <td class="col-name">
            <span class="um-name"><?= htmlspecialchars($u['name']) ?></span>
          </td>

          <!-- Username -->
          <td class="col-username">
            <span class="um-username"><?= htmlspecialchars($u['username']) ?></span>
          </td>

          <!-- Email -->
          <td class="col-email um-email">
            <?= htmlspecialchars($u['email']) ?>
          </td>

          <!-- Role -->
          <td class="col-role">
            <?php
              $roleClass = match($u['role']) {
                'admin'      => 'role-admin',
                'management' => 'role-management',
                default      => 'role-employee',
              };
            ?>
            <span class="role-badge <?= $roleClass ?>"><?= ucfirst($u['role']) ?></span>
          </td>

          <!-- Status -->
          <td class="col-status">
            <span class="status-pill status-<?= $u['status'] ?>">
              <?= ucfirst($u['status']) ?>
            </span>
          </td>

          <!-- Created -->
          <td class="col-created">
            <span class="um-date"><?= date('M d, Y', strtotime($u['created_at'])) ?></span>
          </td>

          <!-- Actions -->
          <td class="col-actions">
            <?php if ($u['id'] != $_SESSION['user_id']): ?>
            <div class="um-actions">

              <!-- Edit button -->
              <button type="button" class="um-btn um-btn-edit edit-user-btn"
                data-id="<?= $u['id'] ?>"
                data-name="<?= htmlspecialchars($u['name'],   ENT_QUOTES, 'UTF-8') ?>"
                data-email="<?= htmlspecialchars($u['email'],  ENT_QUOTES, 'UTF-8') ?>"
                data-role="<?= htmlspecialchars($u['role'],   ENT_QUOTES, 'UTF-8') ?>"
                data-status="<?= htmlspecialchars($u['status'], ENT_QUOTES, 'UTF-8') ?>">
                <i class="fas fa-pen"></i> Edit
              </button>

              <!-- Activate / Deactivate button -->
              <form method="POST" class="d-inline m-0 p-0">
                <input type="hidden" name="toggle_status" value="1">
                <input type="hidden" name="csrf_token"   value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="user_id"      value="<?= $u['id'] ?>">
                <input type="hidden" name="new_status"   value="<?= $u['status'] === 'active' ? 'inactive' : 'active' ?>">
                <?php if ($u['status'] === 'active'): ?>
                  <button type="submit" class="um-btn um-btn-deactivate"
                    onclick="return confirm('Deactivate this user?')">
                    <i class="fas fa-ban"></i> Deactivate
                  </button>
                <?php else: ?>
                  <button type="submit" class="um-btn um-btn-activate"
                    onclick="return confirm('Activate this user?')">
                    <i class="fas fa-check-circle"></i> Activate
                  </button>
                <?php endif; ?>
              </form>

            </div>
            <?php else: ?>
              <span class="you-label"><i class="fas fa-user mr-1"></i>You</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Row count footer -->
  <div class="um-table-footer">
    <?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?> total
  </div>
</div>


<!-- ─── Create User Modal ────────────────────────────────────────── -->
<div class="modal fade" id="createUserModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="create_user" value="1">
        <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-user-plus mr-2 text-primary"></i>Create User Account</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Juan dela Cruz" required>
          </div>
          <div class="form-row">
            <div class="col form-group">
              <label>Username <span class="text-danger">*</span></label>
              <input type="text" name="username" class="form-control" placeholder="e.g. juan.emp" required>
            </div>
            <div class="col form-group">
              <label>Role <span class="text-danger">*</span></label>
              <select name="role" class="form-control">
                <option value="admin">Admin</option>
                <option value="management">Management</option>
                <option value="employee" selected>Employee</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>Email <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="e.g. juan@rocky.com" required>
          </div>
          <div class="form-group mb-0">
            <label>Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" placeholder="Minimum 8 characters" required minlength="8">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- ─── Edit User Modal ──────────────────────────────────────────── -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="update_user" value="1">
        <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="user_id"     id="editUserId">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-user-edit mr-2 text-warning"></i>Edit User</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="editUserName" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Email <span class="text-danger">*</span></label>
            <input type="email" name="email" id="editUserEmail" class="form-control" required>
          </div>
          <div class="form-row">
            <div class="col form-group">
              <label>Role</label>
              <select name="role" id="editUserRole" class="form-control">
                <option value="admin">Admin</option>
                <option value="management">Management</option>
                <option value="employee">Employee</option>
              </select>
            </div>
            <div class="col form-group">
              <label>Status</label>
              <select name="status" id="editUserStatus" class="form-control">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
          <div class="form-group mb-0">
            <label>New Password <small class="text-muted font-weight-normal">(leave blank to keep current)</small></label>
            <input type="password" name="new_password" class="form-control" placeholder="Minimum 8 characters" minlength="8">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>


<?php
// $extraJs — runs AFTER jQuery loads in admin_footer.php
$extraJs = <<<JS
\$(document).ready(function () {
    \$(document).on('click', '.edit-user-btn', function () {
        var btn = \$(this);
        \$('#editUserId').val(btn.data('id'));
        \$('#editUserName').val(btn.data('name'));
        \$('#editUserEmail').val(btn.data('email'));
        \$('#editUserRole').val(btn.data('role')).trigger('change');
        \$('#editUserStatus').val(btn.data('status')).trigger('change');
        \$('#editUserModal').modal('show');
    });
});
JS;
?>
<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>