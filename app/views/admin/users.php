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

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token. Please refresh and try again.</div>";
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

$users = Model::getAllUsers();
?>

<div class="page-title-bar">
    <i class="fas fa-user-shield" class="text-primary"></i>
    <h1>User Management</h1>
    <button class="btn btn-sm btn-primary ml-auto" data-toggle="modal" data-target="#createUserModal">
      <i class="fas fa-plus mr-1"></i>Add User
    </button>
  </div>

<?= $msg ?>
    <div class="card">
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
              <td><code><?= htmlspecialchars($u['username']) ?></code></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><span class="badge <?= $u['role']==='admin'?'badge-primary':'badge-info' ?>"><?= ucfirst($u['role']) ?></span></td>
              <td><span class="status-badge badge-<?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span></td>
              <td><small><?= date('M d, Y', strtotime($u['created_at'])) ?></small></td>
              <td>
                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                <div class="action-btn-group"><button class="btn btn-xs btn-warning" data-toggle="modal" data-target="#editUserModal"
                  data-id="<?= $u['id'] ?>" data-name="<?= htmlspecialchars($u['name']) ?>"
                  data-email="<?= htmlspecialchars($u['email']) ?>"
                  data-role="<?= $u['role'] ?>" data-status="<?= $u['status'] ?>">
                  <i class="fas fa-edit"></i> Edit
                </button></div>
                <?php else: ?>
                <span class="text-muted" style="font-size:.75rem;">(You)</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="POST">
      <input type="hidden" name="create_user" value="1">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <div class="modal-header"><h5 class="modal-title">Create User Account</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
      <div class="modal-body">
        <div class="form-group"><label>Full Name *</label><input type="text" name="name" class="form-control" required></div>
        <div class="form-group"><label>Username *</label><input type="text" name="username" class="form-control" required></div>
        <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" required></div>
        <div class="form-group">
          <label>Role *</label>
          <select name="role" class="form-control">
            <option value="admin">Admin</option>
            <option value="management">Management</option>
          </select>
        </div>
        <div class="form-group"><label>Password *</label><input type="password" name="password" class="form-control" required minlength="8"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Create User</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="POST">
      <input type="hidden" name="update_user" value="1">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="hidden" name="user_id" id="editUserId">
      <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
      <div class="modal-body">
        <div class="form-group"><label>Full Name *</label><input type="text" name="name" id="editUserName" class="form-control" required></div>
        <div class="form-group"><label>Email *</label><input type="email" name="email" id="editUserEmail" class="form-control" required></div>
        <div class="form-group">
          <label>Role</label>
          <select name="role" id="editUserRole" class="form-control">
            <option value="admin">Admin</option>
            <option value="management">Management</option>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" id="editUserStatus" class="form-control">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div class="form-group"><label>New Password <small class="text-muted">(leave blank to keep current)</small></label><input type="password" name="new_password" class="form-control" minlength="8"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Update User</button>
      </div>
    </form>
  </div></div>
</div>

<script>
$('#editUserModal').on('show.bs.modal', function(e) {
  const btn = $(e.relatedTarget);
  $('#editUserId').val(btn.data('id'));
  $('#editUserName').val(btn.data('name'));
  $('#editUserEmail').val(btn.data('email'));
  $('#editUserRole').val(btn.data('role'));
  $('#editUserStatus').val(btn.data('status'));
});
</script>
<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>