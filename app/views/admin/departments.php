<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../config/config.php';
// Only admin
if ($_SESSION['role'] !== ROLE_ADMIN) { header('Location: ' . BASE_URL . '/index.php'); exit; }

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
            $deptName = trim($_POST['dept_name']);
            Model::createDepartment($deptName);
            Model::log($_SESSION['user_id'], 'CREATE_DEPARTMENT', 'Created: ' . $deptName);
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
            $positionName = trim($_POST['position_name']);
            Model::createPosition((int)$_POST['position_dept_id'], $positionName);
            Model::log($_SESSION['user_id'], 'CREATE_POSITION', 'Created: ' . $positionName);
            $msg = "<div class='alert alert-success alert-auto-dismiss'>Position created.</div>";
        }
    } elseif (isset($_POST['delete_dept'])) {
        $deptId = (int)($_POST['dept_id'] ?? 0);
        if ($deptId) {
            $active = Model::countEmployeesInDepartment($deptId);
            if ($active > 0) {
                $msg = "<div class='alert alert-danger'>Cannot delete: {$active} active employee(s) are assigned to this department.</div>";
            } else {
                $dept = Model::findDepartmentById($deptId);
                Model::deleteDepartment($deptId);
                Model::log($_SESSION['user_id'], 'DELETE_DEPARTMENT', 'Deleted: ' . ($dept['name'] ?? "ID:{$deptId}"));
                $msg = "<div class='alert alert-success alert-auto-dismiss'>Department deleted.</div>";
            }
        }
    } elseif (isset($_POST['delete_position'])) {
        $posId = (int)($_POST['position_id'] ?? 0);
        if ($posId) {
            $active = Model::countEmployeesInPosition($posId);
            if ($active > 0) {
                $msg = "<div class='alert alert-danger'>Cannot delete: {$active} active employee(s) hold this position.</div>";
            } else {
                Model::deletePosition($posId);
                Model::log($_SESSION['user_id'], 'DELETE_POSITION', "Deleted position ID:{$posId}");
                $msg = "<div class='alert alert-success alert-auto-dismiss'>Position deleted.</div>";
            }
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
    <i class="fas fa-sitemap text-primary"></i>
    <h1>Departments & Positions</h1>
  </div>

<?= $msg ?>

    <div class="row">
      <!-- Departments -->
      <div class="col-md-5">
        <div class="card">
          <div class="card-header d-flex align-items-center">
            <span class="flex-grow-1"><i class="fas fa-building mr-2"></i>Departments</span>
            <button class="btn btn-sm btn-primary ml-auto" data-toggle="modal" data-target="#newDeptModal">
              <i class="fas fa-plus mr-1"></i>Add
            </button>
          </div>
          <div class="card-body p-0">
            <table class="table table-hover mb-0">
              <thead><tr><th>Name</th><th>Employees</th><th class="text-right"></th></tr></thead>
              <tbody>
                <?php foreach ($departments as $d):
                  $count = count($posByDept[$d['id']] ?? []);
                ?>
                <tr>
                  <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
                  <td><span class="badge badge-secondary"><?= count(Model::getEmployeesByDepartment($d['id'])) ?> active</span></td>
                  <td class="text-right dept-actions-cell">
                    <button class="btn btn-xs btn-outline-warning edit-dept-btn"
                      data-id="<?= $d['id'] ?>"
                      data-name="<?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?>">
                      <i class="fas fa-edit"></i>
                    </button>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="delete_dept" value="1">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                      <input type="hidden" name="dept_id" value="<?= $d['id'] ?>">
                      <button type="submit" class="btn btn-xs btn-outline-danger"
                        onclick="return confirm('Delete department: <?= htmlspecialchars($d['name'], ENT_QUOTES) ?>?\nThis cannot be undone.')">
                        <i class="fas fa-trash"></i>
                      </button>
                    </form>
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
          <div class="card-header d-flex align-items-center">
            <span class="flex-grow-1"><i class="fas fa-user-tag mr-2"></i>Positions</span>
            <button class="btn btn-sm btn-primary ml-auto" data-toggle="modal" data-target="#newPositionModal">
              <i class="fas fa-plus mr-1"></i>Add
            </button>
          </div>
          <div class="card-body p-0 dept-positions-scroll">
            <?php foreach ($departments as $d): ?>
              <?php if (!empty($posByDept[$d['id']])): ?>
              <div class="px-3 pt-3 pb-1">
                <h6 class="dept-pos-section-label">
                  <?= htmlspecialchars($d['name']) ?>
                </h6>
              </div>
              <?php foreach ($posByDept[$d['id']] as $pos): ?>
              <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                <span class="dept-pos-name"><?= htmlspecialchars($pos['name']) ?></span>
                <form method="POST" class="d-inline ml-2">
                  <input type="hidden" name="delete_position" value="1">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                  <input type="hidden" name="position_id" value="<?= $pos['id'] ?>">
                  <button type="submit" class="btn btn-xs btn-outline-danger"
                    onclick="return confirm('Delete position: <?= htmlspecialchars($pos['name'], ENT_QUOTES) ?>?\nThis cannot be undone.')">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
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

<?php
// $extraJs runs AFTER jQuery is loaded in admin_footer.php
$extraJs = <<<JS
\$(document).ready(function () {
    \$(document).on('click', '.edit-dept-btn', function () {
        var btn = \$(this);
        \$('#editDeptId').val(btn.attr('data-id'));
        \$('#editDeptName').val(btn.attr('data-name'));
        \$('#editDeptModal').modal('show');
    });
});
JS;
?>
<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>