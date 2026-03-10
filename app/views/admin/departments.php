<?php
$pageTitle = 'Departments & Positions';
require_once __DIR__ . '/../layouts/admin_header.php';

$msg = '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle Department actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token. Please refresh and try again.</div>";
    } elseif (isset($_POST['new_dept'])) {
        require_once __DIR__ . '/../../../core/Validator.php';
        $v = new Validator($_POST);
        $v->required('dept_name', 'Department name')->maxLen('dept_name', 100, 'Department name');
        if ($v->fails()) {
            $msg = $v->errorHtml();
        } else {
            Model::createDepartment(trim($_POST['dept_name']));
            Model::log($_SESSION['user_id'], 'CREATE_DEPARTMENT', "Created: " . $_POST['dept_name']);
            $msg = "<div class='alert alert-success alert-auto-dismiss'>Department created.</div>";
        }
    } elseif (isset($_POST['edit_dept'])) {
        require_once __DIR__ . '/../../../core/Validator.php';
        $v = new Validator($_POST);
        $v->required('dept_name', 'Department name')->maxLen('dept_name', 100, 'Department name');
        if ($v->fails()) {
            $msg = $v->errorHtml();
        } else {
            Model::updateDepartment((int)$_POST['dept_id'], trim($_POST['dept_name']));
            $msg = "<div class='alert alert-success alert-auto-dismiss'>Department updated.</div>";
        }
    } elseif (isset($_POST['new_position'])) {
        require_once __DIR__ . '/../../../core/Validator.php';
        $v = new Validator($_POST);
        $v->required('position_name', 'Position name')->maxLen('position_name', 100, 'Position name')
          ->required('position_dept_id', 'Department');
        if ($v->fails()) {
            $msg = $v->errorHtml();
        } else {
            Model::createPosition((int)$_POST['position_dept_id'], trim($_POST['position_name']));
            Model::log($_SESSION['user_id'], 'CREATE_POSITION', "Created: " . $_POST['position_name']);
            $msg = "<div class='alert alert-success alert-auto-dismiss'>Position created.</div>";
        }
    }
}

$departments = Model::getAllDepartments();
$positions   = Model::getAllPositions();

// Group positions by dept
$posByDept = [];
foreach ($positions as $p) $posByDept[$p['department_id']][] = $p;
?>

<div class="page-title-bar">
    <i class="fas fa-sitemap" class="text-primary"></i>
    <h1>Departments & Positions</h1>
  </div>

<?= $msg ?>

    <div class="row">
      <!-- Departments -->
      <div class="col-md-5">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-building mr-2"></i>Departments</span>
            <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#newDeptModal">
              <i class="fas fa-plus mr-1"></i>Add
            </button>
          </div>
          <div class="card-body p-0">
            <table class="table table-hover mb-0">
              <thead><tr><th>#</th><th>Name</th><th>Employees</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($departments as $d):
                  $count = count($posByDept[$d['id']] ?? []);
                ?>
                <tr>
                  <td><?= $d['id'] ?></td>
                  <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
                  <td><span class="badge badge-secondary"><?= count(Model::getEmployeesByDepartment($d['id'])) ?> active</span></td>
                  <td>
                    <button class="btn btn-xs btn-outline-warning" data-toggle="modal" data-target="#editDeptModal"
                      data-id="<?= $d['id'] ?>" data-name="<?= htmlspecialchars($d['name']) ?>">
                      <i class="fas fa-edit"></i>
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Positions -->
      <div class="col-md-7">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-user-tag mr-2"></i>Positions</span>
            <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#newPositionModal">
              <i class="fas fa-plus mr-1"></i>Add
            </button>
          </div>
          <div class="card-body p-0" class="dept-positions-scroll">
            <?php foreach ($departments as $d): ?>
              <?php if (!empty($posByDept[$d['id']])): ?>
              <div class="px-3 pt-3 pb-1">
                <h6 style="font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:#64748b;">
                  <?= htmlspecialchars($d['name']) ?>
                </h6>
              </div>
              <?php foreach ($posByDept[$d['id']] as $pos): ?>
              <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                <span style="font-size:.85rem;"><?= htmlspecialchars($pos['name']) ?></span>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
</div>

<!-- New Dept Modal -->
<div class="modal fade" id="newDeptModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="POST">
      <input type="hidden" name="new_dept" value="1">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <div class="modal-header"><h5 class="modal-title">Add Department</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
      <div class="modal-body">
        <div class="form-group"><label>Department Name *</label><input type="text" name="dept_name" class="form-control" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Create</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Edit Dept Modal -->
<div class="modal fade" id="editDeptModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="POST">
      <input type="hidden" name="edit_dept" value="1">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="hidden" name="dept_id" id="editDeptId">
      <div class="modal-header"><h5 class="modal-title">Edit Department</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
      <div class="modal-body">
        <div class="form-group"><label>Department Name *</label><input type="text" name="dept_name" id="editDeptName" class="form-control" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div></div>
</div>

<!-- New Position Modal -->
<div class="modal fade" id="newPositionModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="POST">
      <input type="hidden" name="new_position" value="1">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <div class="modal-header"><h5 class="modal-title">Add Position</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
      <div class="modal-body">
        <div class="form-group">
          <label>Department *</label>
          <select name="position_dept_id" class="form-control" required>
            <option value="">-- Select --</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Position Name *</label><input type="text" name="position_name" class="form-control" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Create</button>
      </div>
    </form>
  </div></div>
</div>

<script>
$('#editDeptModal').on('show.bs.modal', function(e) {
  const btn = $(e.relatedTarget);
  $('#editDeptId').val(btn.data('id'));
  $('#editDeptName').val(btn.data('name'));
});
</script>
<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>