<?php
// ── Bootstrap — load config, DB and Model FIRST so all handlers can use them ─
if (!defined('ROLE_ADMIN')) require_once __DIR__ . '/../../../config/config.php';
if (!defined('DB_HOST'))    require_once __DIR__ . '/../../../config/database.php';
if (!class_exists('Database')) require_once __DIR__ . '/../../../core/Database.php';
if (!class_exists('Model'))    require_once __DIR__ . '/../../../core/Model.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$msg = '';

// ── Handle toggle status ──────────────────────────────────────────────────────
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $emp   = Model::findEmployeeById((int)$_GET['toggle']);
    $newSt = ($emp && $emp['status'] === 'active') ? 'inactive' : 'active';
    Model::toggleEmployeeStatus((int)$_GET['toggle'], $newSt);
    Model::log($_SESSION['user_id'], 'TOGGLE_STATUS', "Set ID {$_GET['toggle']} to {$newSt}");
    header('Location: employees.php');
    exit;
}

$pageTitle = 'Employees';
require_once __DIR__ . '/../../../core/FileUploadService.php';

// ── Define upload base path ────────────────────────────────────────────────────
// Stored relative to the project root — one level up from this file's htdocs sub-path.
// UPLOAD_BASE_DIR = absolute path to /uploads inside the project root.
// UPLOAD_BASE_REL = relative path stored in DB (served via a download handler, not direct URL).
if (!defined('UPLOAD_BASE_DIR')) {
    define('UPLOAD_BASE_DIR', rtrim(dirname(__DIR__, 3), '/') . '/uploads');
}

// ── Handle: Upload employee document ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Invalid security token.</div>';
    } else {
        $docEmpId   = (int)($_POST['doc_emp_id'] ?? 0);
        $docType    = trim($_POST['document_type'] ?? '');
        $docTitle   = trim($_POST['document_title'] ?? '');
        $docExpiry  = trim($_POST['expiry_date'] ?? '') ?: null;
        $docNotes   = trim($_POST['doc_notes'] ?? '');

        $allowedDocTypes = [
            'resume','nbi_clearance','police_clearance','birth_certificate',
            'sss_id','philhealth_id','pagibig_id','tin_id','valid_id',
            'diploma','transcript','certificate','contract','other',
        ];

        if (!$docEmpId || !in_array($docType, $allowedDocTypes, true) || $docTitle === '') {
            $msg = '<div class="alert alert-danger">Please fill in all required document fields.</div>';
        } elseif (empty($_FILES['document_file']['name'])) {
            $msg = '<div class="alert alert-danger">Please select a file to upload.</div>';
        } else {
            $destDir = UPLOAD_BASE_DIR . '/employee_docs/' . $docEmpId;
            $result  = FileUploadService::upload(
                $_FILES['document_file'],
                FileUploadService::EMPLOYEE_DOC,
                $destDir,
                'uploads/employee_docs/' . $docEmpId
            );

            if (!$result->ok) {
                $msg = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>'
                     . htmlspecialchars($result->error) . '</div>';
            } else {
                Model::createDocument([
                    'employee_id'   => $docEmpId,
                    'document_type' => $docType,
                    'title'         => $docTitle,
                    'file_path'     => $result->relativePath,
                    'expiry_date'   => $docExpiry,
                    'notes'         => $docNotes ?: null,
                    'uploaded_by'   => $_SESSION['user_id'],
                ]);
                Model::log($_SESSION['user_id'], 'UPLOAD_DOCUMENT',
                    "Uploaded document '{$docTitle}' ({$docType}) for employee ID:{$docEmpId}");
                header('Location: employees.php?view_id=' . $docEmpId . '&doc_tab=1&updated=1');
                exit;
            }
        }
    }
}

// ── Handle: Delete employee document ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Invalid security token.</div>';
    } else {
        $delDocId  = (int)($_POST['doc_id']     ?? 0);
        $delEmpId  = (int)($_POST['doc_emp_id'] ?? 0);
        // Fetch doc to get file path for deletion
        $docRecord = Model::findDocumentById($delDocId);
        if ($docRecord && (int)$docRecord['employee_id'] === $delEmpId) {
            // Delete physical file if it exists
            if (!empty($docRecord['file_path'])) {
                $absPath = rtrim(dirname(__DIR__, 3), '/') . '/' . ltrim($docRecord['file_path'], '/');
                if (file_exists($absPath)) {
                    @unlink($absPath);
                }
            }
            Model::deleteDocument($delDocId);
            Model::log($_SESSION['user_id'], 'DELETE_DOCUMENT',
                "Deleted document ID:{$delDocId} for employee ID:{$delEmpId}");
        }
        header('Location: employees.php?view_id=' . $delEmpId . '&doc_tab=1');
        exit;
    }
}

// ── Handle: Record Promotion / Demotion / Role Change / Salary Adjustment ────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_change'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger">Invalid security token.</div>';
    } else {
        $rcEmpId       = (int)($_POST['rc_emp_id']       ?? 0);
        $changeType    = $_POST['rc_change_type']        ?? '';
        $newPositionId = (int)($_POST['rc_new_position_id'] ?? 0) ?: null;
        $newBasic      = (float)($_POST['rc_new_basic']  ?? 0);
        $newAllowance  = (float)($_POST['rc_new_allowance'] ?? 0);
        $effectiveDate = $_POST['rc_effective_date']     ?? date('Y-m-d');
        $reason        = trim($_POST['rc_reason']        ?? '');

        $allowedTypes = ['salary_increase','salary_decrease','promotion','demotion','role_change','other'];

        if (!$rcEmpId || !in_array($changeType, $allowedTypes, true) || !$effectiveDate) {
            $msg = '<div class="alert alert-danger">Please fill in all required fields.</div>';
        } else {
            $rcEmp = Model::findEmployeeById($rcEmpId);
            if ($rcEmp) {
                $oldBasic      = (float)($rcEmp['basic_salary'] ?? 0);
                $oldAllowance  = (float)($rcEmp['allowance']    ?? 0);
                $oldPositionId = (int)($rcEmp['position_id']    ?? 0) ?: null;

                // Determine final salary — for position-only changes keep current salary unless overridden
                $finalBasic     = ($newBasic > 0)     ? $newBasic     : $oldBasic;
                $finalAllowance = ($newAllowance >= 0) ? $newAllowance : $oldAllowance;
                $finalPosition  = $newPositionId       ? $newPositionId : $oldPositionId;

                // Record history entry
                Model::createSalaryHistory([
                    'employee_id'      => $rcEmpId,
                    'old_basic_salary' => $oldBasic,
                    'new_basic_salary' => $finalBasic,
                    'old_allowance'    => $oldAllowance,
                    'new_allowance'    => $finalAllowance,
                    'reason'           => $reason ?: ucwords(str_replace('_', ' ', $changeType)),
                    'change_type'      => $changeType,
                    'old_position_id'  => $oldPositionId,
                    'new_position_id'  => $finalPosition,
                    'effective_date'   => $effectiveDate,
                    'approved_by'      => $_SESSION['user_id'] ?? null,
                ]);

                // Update employee record with new salary / position
                $updateData = [
                    'basic_salary' => $finalBasic,
                    'allowance'    => $finalAllowance,
                    'position_id'  => $finalPosition ?? $oldPositionId,
                    'updated_by'   => $_SESSION['user_id'] ?? null,
                ];
                Model::updateEmployeePartial($rcEmpId, $updateData);

                Model::log($_SESSION['user_id'], 'EMPLOYEE_CHANGE',
                    ucwords(str_replace('_', ' ', $changeType)) . " recorded for employee ID:{$rcEmpId} effective {$effectiveDate}");
            }
            header('Location: employees.php?view_id=' . $rcEmpId . '&sal_tab=1&updated=1');
            exit;
        }
    }
}

require_once __DIR__ . '/../layouts/admin_header.php';

// ── Handle create / update ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            Invalid security token. Please refresh and try again.
            <button type="button" class="close" data-dismiss="alert"><span>×</span></button>
        </div>';
    } else {
        require_once __DIR__ . '/../../../core/Validator.php';
        $v = new Validator($_POST);
        $v->required('name', 'Full name')->maxLen('name', 150, 'Full name')
          ->required('date_hired', 'Date hired')->date('date_hired', 'Date hired')
          ->required('department_id', 'Department')
          ->required('position_id', 'Position')
          ->required('basic_salary', 'Basic salary')->positiveNumber('basic_salary', 'Basic salary')
          ->nonNegative('allowance', 'Allowance')
          ->email('email', 'Email')
          ->phone('phone', 'Phone');
        if (!empty($_POST['birthdate'])) $v->date('birthdate', 'Birthdate');
        if ($v->fails()) {
            $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                 . $v->errorHtml()
                 . '<button type="button" class="close" data-dismiss="alert"><span>×</span></button></div>';
        } else {
        $data = [
            'name'                       => trim($_POST['name'] ?? ''),
            'gender'                     => $_POST['gender']        ?? null,
            'civil_status'               => $_POST['civil_status']  ?? null,
            'birthdate'                  => !empty($_POST['birthdate']) ? $_POST['birthdate'] : null,
            'address'                    => $_POST['address']        ?? null,
            'email'                      => $_POST['email']          ?? null,
            'phone'                      => $_POST['phone']          ?? null,
            'sss_no'                     => $_POST['sss_no']         ?? null,
            'philhealth_no'              => $_POST['philhealth_no']  ?? null,
            'pagibig_no'                 => $_POST['pagibig_no']     ?? null,
            'tin_no'                     => $_POST['tin_no']         ?? null,
            'department_id'              => (int)($_POST['department_id']  ?? 0),
            'position_id'                => (int)($_POST['position_id']    ?? 0),
            'basic_salary'               => (float)($_POST['basic_salary'] ?? 0),
            'allowance'                  => (float)($_POST['allowance']    ?? 0),
            'date_hired'                 => $_POST['date_hired']           ?? null,
            'date_start'                 => !empty($_POST['date_start']) ? $_POST['date_start'] : ($_POST['date_hired'] ?? null),
            'employment_type'            => $_POST['employment_type']      ?? 'regular',
            'status'                     => $_POST['status']               ?? 'active',
            'sick_leave_balance'         => (float)($_POST['sick_leave_balance']        ?? 10),
            'vacation_leave_balance'     => (float)($_POST['vacation_leave_balance']    ?? 10),
            'bereavement_leave_balance'  => (float)($_POST['bereavement_leave_balance'] ?? 5),
            'emergency_leave_balance'    => (float)($_POST['emergency_leave_balance']   ?? 5),
            'sil_balance'                => (float)($_POST['sil_balance']               ?? 5),
            'maternity_leave_balance'    => (float)($_POST['maternity_leave_balance']   ?? 105),
            'paternity_leave_balance'    => (float)($_POST['paternity_leave_balance']   ?? 7),
            'solo_parent_leave_balance'  => (float)($_POST['solo_parent_leave_balance'] ?? 7),
            'vawc_leave_balance'         => (float)($_POST['vawc_leave_balance']        ?? 10),
            'magna_carta_leave_balance'  => (float)($_POST['magna_carta_leave_balance'] ?? 60),
            'emergency_contact_name'     => $_POST['emergency_contact_name']     ?? null,
            'emergency_contact_phone'    => $_POST['emergency_contact_phone']    ?? null,
            'emergency_contact_relation' => $_POST['emergency_contact_relation'] ?? null,
            'updated_by'                 => $_SESSION['user_id'] ?? null,
        ];

        if (!empty($_POST['emp_id'])) {
            $empId = (int)$_POST['emp_id'];
            Model::updateEmployee($empId, $data);
            Model::log($_SESSION['user_id'], 'UPDATE_EMPLOYEE', "Updated employee ID:{$empId}");
            // If we came from a 201 view, redirect back to it with a success flag
            $returnView = (int)($_POST['return_view_id'] ?? 0);
            if ($returnView > 0) {
                header('Location: employees.php?view_id=' . $returnView . '&updated=1');
                exit;
            }
            $msg = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                Employee updated successfully.
                <button type="button" class="close" data-dismiss="alert"><span>×</span></button>
            </div>';
        } else {
            // ── Auto-generate employee number ─────────────────────────────
            // generateNextEmployeeNo() scans MAX(employee_no) and increments
            $data['employee_no'] = Model::generateNextEmployeeNo();

            // ── Create the employee record ────────────────────────────────
            Model::createEmployee($data);
            $newEmpId = EmployeeModel::getLastInsertId();

            // ── Auto-create linked user account ───────────────────────────
            // Format:   username = firstname.emp  (unique, suffixed if taken)
            // Password: cryptographically random temp — must be changed on first login.
            $autoUsername = Model::generateEmployeeUsername($data['name']);
            $userCreated  = false;
            $tempPassword = '';
            if ($newEmpId > 0) {
                // Readable temp password: letters+digits+specials, no ambiguous chars
                $chars    = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#';
                $tempPass = '';
                for ($i = 0; $i < 12; $i++) {
                    $tempPass .= $chars[random_int(0, strlen($chars) - 1)];
                }
                $tempPassword = substr($tempPass, 0, 4) . '-' . substr($tempPass, 4, 4) . '-' . substr($tempPass, 8, 4);

                $userCreated = Model::createUser([
                    'name'                 => $data['name'],
                    'username'             => $autoUsername,
                    'email'                => $data['email'] ?? ($autoUsername . '@rocky.com'),
                    'password'             => $tempPassword,
                    'role'                 => 'employee',
                    'employee_id'          => $newEmpId,
                    'status'               => 'active',
                    'must_change_password' => 1,
                    'created_by'           => $_SESSION['user_id'] ?? null,
                ]);
            }

            Model::log(
                $_SESSION['user_id'],
                'CREATE_EMPLOYEE',
                "Created employee: {$data['name']} ({$data['employee_no']})" .
                ($userCreated ? " | User account: {$autoUsername}" : '')
            );

            $userNote = $userCreated
                ? "<br><small><i class=\"fas fa-user-check mr-1\"></i>
                   User account created &mdash;
                   Username: <strong>{$autoUsername}</strong> &nbsp;|&nbsp;
                   Temporary password: <strong style=\"font-family:monospace;letter-spacing:1px;\">{$tempPassword}</strong>
                   &nbsp;<span class=\"badge badge-warning\">Must change on first login</span></small>"
                : '';

            $msg = "<div class=\"alert alert-success alert-dismissible fade show\" role=\"alert\">
                <i class=\"fas fa-check-circle mr-1\"></i>
                Employee <strong>" . htmlspecialchars($data['name']) . "</strong>
                created successfully with ID <strong>{$data['employee_no']}</strong>.
                {$userNote}
                <button type=\"button\" class=\"close\" data-dismiss=\"alert\"><span>×</span></button>
            </div>";
        }
        } // end validation else
    }
}

// ── Fetch data ────────────────────────────────────────────────────────────────
$search       = $_GET['q']      ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterDept   = $_GET['dept']   ?? '';
$allEmployees = $search ? Model::searchEmployees($search) : Model::getAllEmployees($filterStatus);
// Apply department filter
if ($filterDept !== '') {
    $allEmployees = array_filter($allEmployees, fn($e) => (int)$e['department_id'] === (int)$filterDept);
    $allEmployees = array_values($allEmployees);
}
$departments  = Model::getAllDepartments();
$positions    = Model::getAllPositions();

// Pagination
$perPage      = RECORDS_PER_PAGE;
$totalEmps    = count($allEmployees);
$totalPages   = (int) ceil($totalEmps / $perPage);
$curPage      = max(1, min((int)($_GET['page'] ?? 1), max(1, $totalPages)));
$employees    = array_slice($allEmployees, ($curPage - 1) * $perPage, $perPage);

// ── Edit mode ─────────────────────────────────────────────────────────────────
$editEmp = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editEmp = Model::findEmployeeById((int)$_GET['edit']);
}

// ── 201 File view mode ────────────────────────────────────────────────────────
$viewEmp = null;
if (isset($_GET['view_id']) && is_numeric($_GET['view_id'])) {
    $vid            = (int)$_GET['view_id'];
    $viewEmp        = Model::findEmployeeById($vid);
    $viewDocs       = Model::getDocumentsByEmployee($vid);
    $viewLeaves     = Model::getLeaveRequestsByEmployee($vid);
    $viewPayroll    = Model::getPayrollByEmployee($vid);
    $viewSalaryH    = Model::getSalaryHistory($vid);
    $viewAttendance = Model::getAttendanceByEmployee($vid, date('Y-m'));
}
?>

<!-- Page Title Bar -->
<div class="page-title-bar">
  <i class="fas fa-users text-primary"></i>
  <h1>Employees Management</h1>
  <?php if (!$viewEmp && !$editEmp): ?>
    <div class="ml-auto">
      <button type="button" class="btn btn-primary btn-sm" onclick="openAddModal()">
        <i class="fas fa-plus mr-1"></i> Add Employee
      </button>
    </div>
  <?php endif; ?>
</div>

<?= $msg ?>
<?php if (isset($_GET['updated']) && $_GET['updated'] == '1'): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <i class="fas fa-check-circle mr-1"></i>Employee profile updated successfully.
  <button type="button" class="close" data-dismiss="alert"><span>×</span></button>
</div>
<?php endif; ?>

<?php if ($viewEmp): ?>
<!-- 201 FILE VIEW (unchanged) -->
<div class="card mb-3">
  <div class="card-body py-2 d-flex align-items-center">
    <a href="employees.php" class="btn btn-outline-secondary btn-sm mr-3">
      <i class="fas fa-arrow-left mr-1"></i> Back to List
    </a>
    <span class="font-weight-600 emp-view-name">
      Employee 201 File: <strong><?= htmlspecialchars($viewEmp['name']) ?></strong>
    </span>
    <span class="badge badge-<?= $viewEmp['status'] === 'active' ? 'success' : 'danger' ?> ml-2">
      <?= ucfirst($viewEmp['status']) ?>
    </span>
    <button type="button" class="btn btn-warning btn-sm ml-auto"
      onclick="openEditModal(<?= htmlspecialchars(json_encode($viewEmp), ENT_QUOTES) ?>, <?= (int)$viewEmp['id'] ?>)">
      <i class="fas fa-edit mr-1"></i> Edit Profile
    </button>
  </div>
</div>

<div class="row">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-body text-center">
        <div class="employee-avatar mb-3">
          <?= strtoupper(substr($viewEmp['name'], 0, 1)) ?>
        </div>
        <h5 class="mb-1 font-weight-700"><?= htmlspecialchars($viewEmp['name']) ?></h5>
        <p class="text-muted mb-1 emp-view-no"><?= htmlspecialchars($viewEmp['employee_no'] ?? 'N/A') ?></p>
        <p class="mb-3">
          <strong class="emp-view-position"><?= htmlspecialchars($viewEmp['position'] ?? 'N/A') ?></strong><br>
          <small class="text-muted"><?= htmlspecialchars($viewEmp['department'] ?? 'N/A') ?></small>
        </p>
        <table class="table table-sm mb-0 text-left">
          <tr>
            <td class="text-muted emp-view-table-label">Employment</td>
            <td class="text-right font-weight-600 emp-view-table-label"><?= ucfirst($viewEmp['employment_type'] ?? 'Regular') ?></td>
          </tr>
          <tr>
            <td class="text-muted emp-view-table-label">Date Hired</td>
            <td class="text-right font-weight-600 emp-view-table-label"><?= $viewEmp['date_hired'] ? date('M d, Y', strtotime($viewEmp['date_hired'])) : '—' ?></td>
          </tr>
          <tr>
            <td class="text-muted emp-view-table-label">Date Start</td>
            <td class="text-right font-weight-600 emp-view-table-label"><?= !empty($viewEmp['date_start']) ? date('M d, Y', strtotime($viewEmp['date_start'])) : '—' ?></td>
          </tr>
          <tr>
            <td class="text-muted emp-view-table-label">Basic Salary</td>
            <td class="text-right font-weight-600 emp-view-table-label">₱<?= number_format($viewEmp['basic_salary'], 2) ?></td>
          </tr>
          <tr>
            <td class="text-muted emp-view-table-label">Allowance</td>
            <td class="text-right font-weight-600 emp-view-table-label">₱<?= number_format($viewEmp['allowance'], 2) ?></td>
          </tr>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <i class="fas fa-calendar-check mr-2 text-info"></i>Leave Balances
      </div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <?php
          $leaveBalances = [
            'Sick Leave'     => $viewEmp['sick_leave_balance']       ?? 0,
            'Vacation Leave' => $viewEmp['vacation_leave_balance']   ?? 0,
            'Bereavement'    => $viewEmp['bereavement_leave_balance'] ?? 0,
            'Emergency'      => $viewEmp['emergency_leave_balance']  ?? 0,
            'SIL'            => $viewEmp['sil_balance']              ?? 0,
          ];
          foreach ($leaveBalances as $lname => $bal): ?>
          <tr>
            <td class="emp-leave-type-cell"><?= $lname ?></td>
            <td class="text-right">
              <strong class="<?= $bal > 0 ? 'leave-balance-positive' : 'leave-balance-zero' ?>">
                <?= $bal ?>
              </strong>
              <small class="text-muted"> days</small>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs px-3 pt-2" id="employeeTabs" role="tablist">
          <li class="nav-item">
            <a class="nav-link <?= !isset($_GET['doc_tab']) ? 'active' : '' ?>" data-toggle="pill" href="#tabPersonal">
              <i class="fas fa-id-card mr-1"></i>Personal
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#tabPayroll">
              <i class="fas fa-money-bill-wave mr-1"></i>Payroll
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="pill" href="#tabLeaves">
              <i class="fas fa-umbrella-beach mr-1"></i>Leaves
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= isset($_GET['sal_tab']) ? 'active' : '' ?>" data-toggle="pill" href="#tabSalary">
              <i class="fas fa-chart-line mr-1"></i>Salary History
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= isset($_GET['doc_tab']) ? 'active' : '' ?>" data-toggle="pill" href="#tabDocuments">
              <i class="fas fa-folder-open mr-1"></i>Documents
              <?php if (!empty($viewDocs)): ?>
                <span class="badge badge-secondary ml-1"><?= count($viewDocs) ?></span>
              <?php endif; ?>
            </a>
          </li>
        </ul>
      </div>
      <div class="card-body">
        <div class="tab-content">
          <!-- Personal Info -->
          <div class="tab-pane fade <?= !isset($_GET['doc_tab']) ? 'show active' : '' ?>" id="tabPersonal">
            <div class="row">
              <div class="col-md-6">
                <p><strong>Gender:</strong> <?= ucfirst($viewEmp['gender'] ?? '—') ?></p>
                <p><strong>Civil Status:</strong> <?= ucfirst($viewEmp['civil_status'] ?? '—') ?></p>
                <p><strong>Birthdate:</strong> <?= $viewEmp['birthdate'] ? date('M d, Y', strtotime($viewEmp['birthdate'])) : '—' ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($viewEmp['email'] ?? '—') ?></p>
                <p><strong>Phone:</strong> <?= htmlspecialchars($viewEmp['phone'] ?? '—') ?></p>
              </div>
              <div class="col-md-6">
                <p><strong>Address:</strong><br><?= nl2br(htmlspecialchars($viewEmp['address'] ?? '—')) ?></p>
              </div>
              <div class="col-12 mt-3">
                <p class="section-divider-label mb-2">Government IDs</p>
                <hr class="mt-0 mb-2">
                <div class="row">
                  <div class="col-sm-6"><p><strong>SSS No.:</strong> <?= htmlspecialchars($viewEmp['sss_no'] ?? '—') ?></p></div>
                  <div class="col-sm-6"><p><strong>PhilHealth No.:</strong> <?= htmlspecialchars($viewEmp['philhealth_no'] ?? '—') ?></p></div>
                  <div class="col-sm-6"><p><strong>Pag-IBIG No.:</strong> <?= htmlspecialchars($viewEmp['pagibig_no'] ?? '—') ?></p></div>
                  <div class="col-sm-6"><p><strong>TIN No.:</strong> <?= htmlspecialchars($viewEmp['tin_no'] ?? '—') ?></p></div>
                </div>
              </div>
              <div class="col-12 mt-2">
                <p class="section-divider-label mb-2">Emergency Contact</p>
                <hr class="mt-0 mb-2">
                <div class="row">
                  <div class="col-sm-4"><p><strong>Name:</strong> <?= htmlspecialchars($viewEmp['emergency_contact_name'] ?? '—') ?></p></div>
                  <div class="col-sm-4"><p><strong>Phone:</strong> <?= htmlspecialchars($viewEmp['emergency_contact_phone'] ?? '—') ?></p></div>
                  <div class="col-sm-4"><p><strong>Relation:</strong> <?= htmlspecialchars($viewEmp['emergency_contact_relation'] ?? '—') ?></p></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Payroll History -->
          <div class="tab-pane fade" id="tabPayroll">
            <div class="table-responsive">
              <table class="table table-sm table-bordered mb-0">
                <thead>
                  <tr>
                    <th>Period</th>
                    <th class="text-right">Gross Pay</th>
                    <th class="text-right">Deductions</th>
                    <th class="text-right">Net Pay</th>
                    <th class="text-center">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($viewPayroll)): ?>
                    <?php foreach ($viewPayroll as $pr): ?>
                    <tr>
                      <td><?= htmlspecialchars($pr['period']) ?></td>
                      <td class="text-right">₱<?= number_format($pr['gross_pay'] ?? 0, 2) ?></td>
                      <td class="text-right">₱<?= number_format($pr['total_deductions'] ?? 0, 2) ?></td>
                      <td class="text-right"><strong>₱<?= number_format($pr['net_pay'] ?? 0, 2) ?></strong></td>
                      <td class="text-center">
                        <span class="badge badge-<?= $pr['status'] === 'released' ? 'success' : 'warning' ?>">
                          <?= ucfirst($pr['status']) ?>
                        </span>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">No payroll records found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Leave History -->
          <div class="tab-pane fade" id="tabLeaves">
            <div class="table-responsive">
              <table class="table table-sm table-bordered mb-0">
                <thead>
                  <tr>
                    <th>Type</th>
                    <th>Period</th>
                    <th class="text-center">Days</th>
                    <th class="text-center">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($viewLeaves)): ?>
                    <?php foreach ($viewLeaves as $lr): ?>
                    <tr>
                      <td><?= htmlspecialchars(LEAVE_TYPES[$lr['leave_type']] ?? ucfirst($lr['leave_type'])) ?></td>
                      <td><?= date('M d', strtotime($lr['date_from'])) ?> – <?= date('M d, Y', strtotime($lr['date_to'])) ?></td>
                      <td class="text-center"><?= $lr['days_applied'] ?></td>
                      <td class="text-center">
                        <span class="badge badge-<?= $lr['status'] === 'approved' ? 'success' : ($lr['status'] === 'pending' ? 'warning' : 'danger') ?>">
                          <?= ucfirst($lr['status']) ?>
                        </span>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">No leave requests found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Salary History -->
          <div class="tab-pane fade <?= isset($_GET['sal_tab']) ? 'show active' : '' ?>" id="tabSalary">

            <!-- Record Change Form -->
            <form method="POST" class="mb-4" id="recordChangeForm">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
              <input type="hidden" name="record_change" value="1">
              <input type="hidden" name="rc_emp_id"    value="<?= (int)$viewEmp['id'] ?>">

              <div class="card border-success mb-0">
                <div class="card-header bg-success text-white py-2">
                  <i class="fas fa-exchange-alt mr-2"></i>Record Promotion / Demotion / Role Change / Salary Adjustment
                </div>
                <div class="card-body pb-2">
                  <div class="row">
                    <div class="col-md-3">
                      <div class="form-group">
                        <label class="font-weight-600">Change Type <span class="text-danger">*</span></label>
                        <select name="rc_change_type" id="rcChangeType" class="form-control form-control-sm" required>
                          <option value="">-- Select --</option>
                          <option value="promotion">🏆 Promotion</option>
                          <option value="demotion">🔽 Demotion</option>
                          <option value="role_change">🔄 Role Change</option>
                          <option value="salary_increase">📈 Salary Increase</option>
                          <option value="salary_decrease">📉 Salary Decrease</option>
                          <option value="other">📝 Other</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="form-group">
                        <label class="font-weight-600">Effective Date <span class="text-danger">*</span></label>
                        <input type="date" name="rc_effective_date" class="form-control form-control-sm"
                               value="<?= date('Y-m-d') ?>" required>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="form-group">
                        <label class="font-weight-600">New Position <small class="text-muted">(if changing)</small></label>
                        <select name="rc_new_position_id" class="form-control form-control-sm">
                          <option value="">-- Keep Current --</option>
                          <?php foreach ($positions as $pos): ?>
                            <option value="<?= $pos['id'] ?>"
                              <?= (int)$pos['id'] === (int)($viewEmp['position_id'] ?? 0) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($pos['department_name'] . ' › ' . $pos['name']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="form-group">
                        <label class="font-weight-600">Reason / Notes</label>
                        <input type="text" name="rc_reason" class="form-control form-control-sm"
                               placeholder="e.g. Excellent performance Q1 2026" maxlength="255">
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="form-group">
                        <label class="font-weight-600">New Basic Salary <small class="text-muted">(leave 0 to keep current)</small></label>
                        <div class="input-group input-group-sm">
                          <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                          <input type="number" step="0.01" min="0" name="rc_new_basic"
                                 class="form-control form-control-sm"
                                 value="<?= number_format((float)($viewEmp['basic_salary'] ?? 0), 2, '.', '') ?>"
                                 placeholder="<?= number_format((float)($viewEmp['basic_salary'] ?? 0), 2) ?>">
                        </div>
                        <small class="text-muted">Current: ₱<?= number_format((float)($viewEmp['basic_salary'] ?? 0), 2) ?></small>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="form-group">
                        <label class="font-weight-600">New Allowance <small class="text-muted">(leave 0 to keep current)</small></label>
                        <div class="input-group input-group-sm">
                          <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                          <input type="number" step="0.01" min="0" name="rc_new_allowance"
                                 class="form-control form-control-sm"
                                 value="<?= number_format((float)($viewEmp['allowance'] ?? 0), 2, '.', '') ?>"
                                 placeholder="<?= number_format((float)($viewEmp['allowance'] ?? 0), 2) ?>">
                        </div>
                        <small class="text-muted">Current: ₱<?= number_format((float)($viewEmp['allowance'] ?? 0), 2) ?></small>
                      </div>
                    </div>
                  </div>
                  <button type="submit" class="btn btn-success btn-sm">
                    <i class="fas fa-save mr-1"></i>Save Change Record
                  </button>
                </div>
              </div>
            </form>

            <!-- History Table -->
            <div class="table-responsive">
              <table class="table table-sm table-bordered mb-0">
                <thead class="thead-dark">
                  <tr>
                    <th>Effective Date</th>
                    <th>Change Type</th>
                    <th>Position Change</th>
                    <th class="text-right">Old Basic</th>
                    <th class="text-right">New Basic</th>
                    <th>Reason</th>
                    <th>Approved By</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($viewSalaryH)):
                    $changeTypeBadge = [
                      'promotion'       => ['success', '🏆 Promotion'],
                      'demotion'        => ['danger',  '🔽 Demotion'],
                      'role_change'     => ['info',    '🔄 Role Change'],
                      'salary_increase' => ['primary', '📈 Increase'],
                      'salary_decrease' => ['warning', '📉 Decrease'],
                      'other'           => ['secondary','📝 Other'],
                    ];
                    foreach ($viewSalaryH as $sh):
                      $ct   = $sh['change_type'] ?? 'salary_increase';
                      $badge = $changeTypeBadge[$ct] ?? ['secondary', ucfirst(str_replace('_',' ',$ct))];
                      $salDiff = (float)$sh['new_basic_salary'] - (float)$sh['old_basic_salary'];
                  ?>
                  <tr>
                    <td><?= date('M d, Y', strtotime($sh['effective_date'])) ?></td>
                    <td><span class="badge badge-<?= $badge[0] ?>"><?= $badge[1] ?></span></td>
                    <td class="text-xs">
                      <?php if (!empty($sh['old_position_name']) || !empty($sh['new_position_name'])): ?>
                        <?= htmlspecialchars($sh['old_position_name'] ?? '—') ?>
                        <?php if (!empty($sh['new_position_name']) && $sh['new_position_name'] !== ($sh['old_position_name'] ?? '')): ?>
                          <i class="fas fa-arrow-right mx-1 text-muted"></i>
                          <strong><?= htmlspecialchars($sh['new_position_name']) ?></strong>
                        <?php endif; ?>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-right">₱<?= number_format($sh['old_basic_salary'], 2) ?></td>
                    <td class="text-right <?= $salDiff > 0 ? 'text-success' : ($salDiff < 0 ? 'text-danger' : '') ?>">
                      <strong>₱<?= number_format($sh['new_basic_salary'], 2) ?></strong>
                      <?php if ($salDiff != 0): ?>
                        <small>(<?= $salDiff > 0 ? '+' : '' ?><?= number_format($salDiff, 2) ?>)</small>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($sh['reason'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($sh['approved_by_name'] ?? '—') ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">No change history found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Documents Tab — MUST be inside tab-content, not outside -->
          <div class="tab-pane fade <?= isset($_GET['doc_tab']) ? 'show active' : '' ?>" id="tabDocuments">

            <!-- Upload form -->
            <form method="POST" enctype="multipart/form-data" class="mb-4" id="docUploadForm">
              <input type="hidden" name="csrf_token"   value="<?= htmlspecialchars($csrf_token) ?>">
              <input type="hidden" name="upload_document" value="1">
              <input type="hidden" name="doc_emp_id"   value="<?= (int)$viewEmp['id'] ?>">

              <div class="card border-primary mb-0">
                <div class="card-header bg-primary text-white py-2">
                  <i class="fas fa-cloud-upload-alt mr-2"></i>Upload New Document
                </div>
                <div class="card-body pb-2">
                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-group">
                        <label class="font-weight-600">Document Type <span class="text-danger">*</span></label>
                        <select name="document_type" class="form-control form-control-sm" required>
                          <option value="">-- Select Type --</option>
                          <?php
                          $docTypes = [
                            'resume'           => 'Resume / CV',
                            'nbi_clearance'    => 'NBI Clearance',
                            'police_clearance' => 'Police Clearance',
                            'birth_certificate'=> 'Birth Certificate',
                            'sss_id'           => 'SSS ID / Card',
                            'philhealth_id'    => 'PhilHealth ID',
                            'pagibig_id'       => 'Pag-IBIG ID',
                            'tin_id'           => 'TIN ID / Card',
                            'valid_id'         => 'Valid Government ID',
                            'diploma'          => 'Diploma',
                            'transcript'       => 'Transcript of Records',
                            'certificate'      => 'Certificate',
                            'contract'         => 'Employment Contract',
                            'other'            => 'Other',
                          ];
                          foreach ($docTypes as $dv => $dl): ?>
                            <option value="<?= $dv ?>"><?= $dl ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label class="font-weight-600">Document Title <span class="text-danger">*</span></label>
                        <input type="text" name="document_title" class="form-control form-control-sm"
                               placeholder="e.g. NBI Clearance 2026" maxlength="200" required>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label class="font-weight-600">Expiry Date <small class="text-muted">(optional)</small></label>
                        <input type="date" name="expiry_date" class="form-control form-control-sm">
                      </div>
                    </div>
                    <div class="col-md-8">
                      <div class="form-group">
                        <label class="font-weight-600">File <span class="text-danger">*</span>
                          <small class="text-muted font-weight-normal ml-1">PDF, JPG, PNG, WEBP — max 10 MB</small>
                        </label>
                        <div class="custom-file">
                          <input type="file" class="custom-file-input" id="docFileInput"
                                 name="document_file" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                          <label class="custom-file-label" for="docFileInput">Choose file…</label>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label class="font-weight-600">Notes <small class="text-muted">(optional)</small></label>
                        <input type="text" name="doc_notes" class="form-control form-control-sm"
                               placeholder="Additional info…" maxlength="500">
                      </div>
                    </div>
                  </div>
                  <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-upload mr-1"></i>Upload Document
                  </button>
                </div>
              </div>
            </form>

            <!-- Document list -->
            <?php if (empty($viewDocs)): ?>
              <div class="text-center text-muted py-4">
                <i class="fas fa-folder-open fa-2x mb-2 d-block"></i>
                No documents uploaded yet.
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                  <thead class="thead-dark">
                    <tr>
                      <th>Type</th>
                      <th>Title</th>
                      <th>Uploaded</th>
                      <th>Expiry</th>
                      <th>Notes</th>
                      <th class="text-center no-print">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($viewDocs as $doc):
                      $expiryDate   = $doc['expiry_date'] ?? null;
                      $isExpired    = $expiryDate && strtotime($expiryDate) < time();
                      $expiresInDays= $expiryDate ? (int)floor((strtotime($expiryDate) - time()) / 86400) : null;
                    ?>
                    <tr class="<?= $isExpired ? 'table-danger' : ($expiresInDays !== null && $expiresInDays <= 30 ? 'table-warning' : '') ?>">
                      <td>
                        <span class="badge badge-secondary">
                          <?= htmlspecialchars($docTypes[$doc['document_type']] ?? ucfirst(str_replace('_',' ',$doc['document_type']))) ?>
                        </span>
                      </td>
                      <td>
                        <?= htmlspecialchars($doc['title']) ?>
                        <?php if (!empty($doc['file_path'])): ?>
                          <br>
                          <a href="<?= BASE_URL ?>/download.php?type=doc&id=<?= (int)$doc['id'] ?>"
                             class="btn btn-xs btn-outline-secondary mt-1" title="Download File">
                            <i class="fas fa-download mr-1"></i><?= htmlspecialchars(basename($doc['file_path'])) ?>
                          </a>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?= date('M j, Y', strtotime($doc['created_at'])) ?><br>
                        <small class="text-muted"><?= htmlspecialchars($doc['uploaded_by_name'] ?? '—') ?></small>
                      </td>
                      <td>
                        <?php if ($expiryDate): ?>
                          <?= date('M j, Y', strtotime($expiryDate)) ?>
                          <?php if ($isExpired): ?>
                            <span class="badge badge-danger ml-1">Expired</span>
                          <?php elseif ($expiresInDays <= 30): ?>
                            <span class="badge badge-warning ml-1"><?= $expiresInDays ?>d left</span>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td><small><?= htmlspecialchars($doc['notes'] ?? '—') ?></small></td>
                      <td class="text-center no-print">
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('Delete this document? This cannot be undone.')">
                          <input type="hidden" name="csrf_token"      value="<?= htmlspecialchars($csrf_token) ?>">
                          <input type="hidden" name="delete_document" value="1">
                          <input type="hidden" name="doc_id"          value="<?= (int)$doc['id'] ?>">
                          <input type="hidden" name="doc_emp_id"      value="<?= (int)$viewEmp['id'] ?>">
                          <button type="submit" class="btn btn-danger btn-xs">
                            <i class="fas fa-trash"></i>
                          </button>
                        </form>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div><!-- /#tabDocuments -->

        </div><!-- /.tab-content -->
      </div><!-- /.card-body -->
    </div><!-- /.card -->
  </div><!-- /.col-lg-8 -->
</div><!-- /.row -->

<?php else: ?>
<!-- EMPLOYEE LIST -->

<!-- Search & Filter card -->
<div class="card mb-4">
  <div class="card-body py-3">
    <form method="GET" class="form-inline flex-gap-2">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
             class="form-control form-control-sm emp-filter-select"
             placeholder="Search name, employee no, email…">
      <select name="status" class="form-control form-control-sm">
        <option value="">All Statuses</option>
        <?php foreach (EMPLOYEE_STATUS as $k => $v): ?>
          <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
      <select name="dept" class="form-control form-control-sm">
        <option value="">All Departments</option>
        <?php foreach ($departments as $dept): ?>
          <option value="<?= $dept['id'] ?>" <?= ($filterDept ?? '') == $dept['id'] ? 'selected' : '' ?>><?= htmlspecialchars($dept['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-sm btn-primary">
        <i class="fas fa-search mr-1"></i>Filter
      </button>
      <a href="employees.php" class="btn btn-sm btn-outline-secondary">Reset</a>
    </form>
  </div>
</div>

<!-- Employee table card -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-list mr-2"></i>Employee List</span>
    <span class="badge badge-primary ml-auto"><?= count($employees) ?> records</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table id="employeesTable" class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Department / Position</th>
            <th>Employment</th>
            <!-- Removed text-center as requested -->
            <th>Basic Salary</th>
            <th>Date Hired</th>
            <th>Date Start</th>
            <th class="text-center">Status</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($employees)): ?>
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">
                <i class="fas fa-user-slash fa-2x mb-2 d-block emp-empty-icon"></i>
                No employees found.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($employees as $emp): ?>
            <tr>
              <td>
                <strong class="text-sm2"><?= htmlspecialchars($emp['name']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($emp['employee_no'] ?? '—') ?></small>
              </td>
              <td>
                <?= htmlspecialchars($emp['department'] ?? '—') ?><br>
                <small class="text-muted"><?= htmlspecialchars($emp['position'] ?? '—') ?></small>
              </td>
              <td><?= ucfirst($emp['employment_type'] ?? 'Regular') ?></td>
              <!-- Removed class="text-center" and all inline styles -->
              <td>
                ₱<?= number_format($emp['basic_salary'] ?? 0, 2) ?>
              </td>
              <td><?= $emp['date_hired'] ? date('M d, Y', strtotime($emp['date_hired'])) : '—' ?></td>
              <td><?= !empty($emp['date_start']) ? date('M d, Y', strtotime($emp['date_start'])) : '—' ?></td>
              <td class="text-center">
                <span class="badge badge-<?= $emp['status'] ?>">
                  <?= ucfirst($emp['status']) ?>
                </span>
              </td>
              <td class="text-center text-nowrap">
                <div class="action-btn-group">
                  <a href="employees.php?view_id=<?= $emp['id'] ?>"
                     class="btn btn-sm btn-info" data-toggle="tooltip" title="View 201 File">
                    <i class="fas fa-eye"></i>
                  </a>
                  <button type="button"
                     class="btn btn-sm btn-warning"
                     data-toggle="tooltip" title="Edit"
                     onclick="openEditModal(<?= htmlspecialchars(json_encode($emp), ENT_QUOTES) ?>)">
                    <i class="fas fa-edit"></i>
                  </button>
                  <a href="employees.php?toggle=<?= $emp['id'] ?>"
                     class="btn btn-sm <?= $emp['status'] === 'active' ? 'btn-secondary' : 'btn-success' ?>"
                     data-toggle="tooltip"
                     title="<?= $emp['status'] === 'active' ? 'Deactivate' : 'Activate' ?>"
                     onclick="return confirm('Change status to <?= $emp['status'] === 'active' ? 'inactive' : 'active' ?>?')">
                    <i class="fas fa-<?= $emp['status'] === 'active' ? 'ban' : 'check' ?>"></i>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer d-flex justify-content-between align-items-center flex-wrap emp-footer-actions">
    <span class="text-muted emp-view-table-label">
      Showing <?= number_format(($curPage-1)*$perPage+1) ?>–<?= number_format(min($curPage*$perPage,$totalEmps)) ?> of <?= number_format($totalEmps) ?> employee(s)
    </span>
    <?php if ($totalPages > 1): ?>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $curPage <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $curPage-1])) ?>">«</a>
        </li>
        <?php
          $start = max(1, $curPage - 2);
          $end   = min($totalPages, $curPage + 2);
          for ($i = $start; $i <= $end; $i++): ?>
          <li class="page-item <?= $i === $curPage ? 'active' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $curPage >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $curPage+1])) ?>">»</a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
</div>

<?php endif; ?>

<!-- ADD / EDIT MODAL (unchanged) -->
<div class="modal fade" id="employeeModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <form method="POST" id="employeeForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="emp_id" id="empId">
        <input type="hidden" name="return_view_id" id="returnViewId" value="">

        <div class="modal-header">
          <h5 class="modal-title" id="empModalTitle">
            <i class="fas fa-user-plus mr-2"></i>Add Employee
          </h5>
          <button type="button" class="close" data-dismiss="modal">
            <span>×</span>
          </button>
        </div>

        <div class="modal-body">
          <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tabBasic">Basic Info</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabGovt">Government IDs</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabLeaveB">Leave Balances</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabEmergency">Emergency Contact</a></li>
          </ul>

          <div class="tab-content">
            <!-- Basic Info -->
            <div class="tab-pane fade show active" id="tabBasic">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="fName" class="form-control" required maxlength="150" autocomplete="off">
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" id="fGender" class="form-control">
                      <option value="">-- Select --</option>
                      <option value="male">Male</option>
                      <option value="female">Female</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label>Civil Status</label>
                    <select name="civil_status" id="fCivilStatus" class="form-control">
                      <option value="">-- Select --</option>
                      <option value="single">Single</option>
                      <option value="married">Married</option>
                      <option value="widowed">Widowed</option>
                      <option value="separated">Separated</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Birthdate</label>
                    <input type="date" name="birthdate" id="fBirthdate" class="form-control">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="fEmail" class="form-control" maxlength="150" autocomplete="off">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" id="fPhone" class="form-control" maxlength="15" placeholder="e.g. 09XXXXXXXXX">
                  </div>
                </div>
                <div class="col-12">
                  <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="fAddress" class="form-control" rows="2" maxlength="300"></textarea>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Department <span class="text-danger">*</span></label>
                    <select name="department_id" id="fDeptId" class="form-control" required>
                      <option value="">-- Select Department --</option>
                      <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Position <span class="text-danger">*</span></label>
                    <select name="position_id" id="fPositionId" class="form-control" required>
                      <option value="">-- Select Position --</option>
                      <?php foreach ($positions as $pos): ?>
                        <option value="<?= $pos['id'] ?>" data-dept="<?= $pos['department_id'] ?>">
                          <?= htmlspecialchars($pos['name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Basic Salary <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                      <input type="number" step="0.01" min="0" name="basic_salary" id="fBasicSalary" class="form-control" required>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Allowance</label>
                    <div class="input-group">
                      <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                      <input type="number" step="0.01" min="0" name="allowance" id="fAllowance" class="form-control" value="0">
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Date Hired <span class="text-danger">*</span></label>
                    <input type="date" name="date_hired" id="fDateHired" class="form-control" required>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Date Start
                      <i class="fas fa-info-circle text-muted ml-1" title="Actual first working day. Leave blank to use Date Hired. Used for payslip proration when hired mid-cutoff."></i>
                    </label>
                    <input type="date" name="date_start" id="fDateStart" class="form-control">
                    <small class="text-muted">Leave blank to use Date Hired</small>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Employment Type</label>
                    <select name="employment_type" id="fEmploymentType" class="form-control">
                      <?php foreach (EMPLOYMENT_TYPES as $k => $v): ?>
                        <option value="<?= $k ?>"><?= $v ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="fStatus" class="form-control">
                      <?php foreach (EMPLOYEE_STATUS as $k => $v): ?>
                        <option value="<?= $k ?>"><?= $v ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <!-- Government IDs -->
            <div class="tab-pane fade" id="tabGovt">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>SSS No.</label>
                    <input type="text" name="sss_no" id="fSssNo" class="form-control" placeholder="XX-XXXXXXX-X" maxlength="12">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>PhilHealth No.</label>
                    <input type="text" name="philhealth_no" id="fPhilhealthNo" class="form-control" placeholder="XX-XXXXXXXXX-X" maxlength="14">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Pag-IBIG No.</label>
                    <input type="text" name="pagibig_no" id="fPagibigNo" class="form-control" placeholder="XXXX-XXXX-XXXX" maxlength="14">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>TIN No.</label>
                    <input type="text" name="tin_no" id="fTinNo" class="form-control" placeholder="XXX-XXX-XXX-XXX" maxlength="15">
                  </div>
                </div>
              </div>
            </div>

            <!-- Leave Balances -->
            <div class="tab-pane fade" id="tabLeaveB">
              <div class="row">
                <?php
                // gender: 'all' = no restriction, 'female' = female only, 'male' = male only
                $leaveFields = [
                  'sick_leave_balance'        => ['label' => 'Sick Leave',         'default' => 10,  'gender' => 'all'],
                  'vacation_leave_balance'    => ['label' => 'Vacation Leave',     'default' => 10,  'gender' => 'all'],
                  'bereavement_leave_balance' => ['label' => 'Bereavement Leave',  'default' => 5,   'gender' => 'all'],
                  'emergency_leave_balance'   => ['label' => 'Emergency Leave',    'default' => 5,   'gender' => 'all'],
                  'sil_balance'               => ['label' => 'Service Incentive',  'default' => 5,   'gender' => 'all'],
                  'solo_parent_leave_balance' => ['label' => 'Solo Parent Leave',  'default' => 7,   'gender' => 'all'],
                  'maternity_leave_balance'   => ['label' => 'Maternity Leave',    'default' => 105, 'gender' => 'female'],
                  'vawc_leave_balance'        => ['label' => 'VAWC Leave',         'default' => 10,  'gender' => 'female'],
                  'magna_carta_leave_balance' => ['label' => 'Magna Carta Leave',  'default' => 60,  'gender' => 'female'],
                  'paternity_leave_balance'   => ['label' => 'Paternity Leave',    'default' => 7,   'gender' => 'male'],
                ];
                foreach ($leaveFields as $field => $info): ?>
                <div class="col-md-4 leave-field-wrap" data-gender="<?= $info['gender'] ?>">
                  <div class="form-group">
                    <label>
                      <?= $info['label'] ?>
                      <?php if ($info['gender'] === 'female'): ?>
                        <span class="badge badge-pink ml-1" title="Female employees only"><i class="fas fa-venus"></i> Female</span>
                      <?php elseif ($info['gender'] === 'male'): ?>
                        <span class="badge badge-blue ml-1" title="Male employees only"><i class="fas fa-mars"></i> Male</span>
                      <?php endif; ?>
                    </label>
                    <input type="number" step="0.5" min="0" name="<?= $field ?>"
                           id="f_<?= $field ?>" class="form-control leave-gender-field"
                           value="<?= $info['default'] ?>">
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <div id="leaveGenderNotice" class="alert alert-info mt-2 py-2 emp-gender-notice">
                <i class="fas fa-info-circle mr-1"></i>
                <span id="leaveGenderNoticeText"></span>
                Gender-restricted leave fields are grayed out and set to 0.
              </div>
            </div>

            <!-- Emergency Contact -->
            <div class="tab-pane fade" id="tabEmergency">
              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Contact Name</label>
                    <input type="text" name="emergency_contact_name" id="fEcName" class="form-control" maxlength="150" autocomplete="off">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Contact Phone</label>
                    <input type="tel" name="emergency_contact_phone" id="fEcPhone" class="form-control" maxlength="15" placeholder="e.g. 09XXXXXXXXX">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Relationship</label>
                    <input type="text" name="emergency_contact_relation" id="fEcRelation" class="form-control" placeholder="e.g. Spouse, Parent" maxlength="50">
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer d-flex justify-content-between align-items-center">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i>Cancel
          </button>
          <button type="submit" class="btn btn-primary" id="empSubmitBtn">
            <i class="fas fa-save mr-1"></i>Save Employee
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
// Build positions JSON for JS — avoids PHP inside heredoc
$positionsJson = json_encode(array_map(fn($p) => [
    'id'            => $p['id'],
    'department_id' => $p['department_id'],
    'name'          => $p['name'],
], $positions));
?>
<?php
$extraJs = 'var POSITIONS_DATA = ' . $positionsJson . ';' . PHP_EOL;
$extraJs .= <<<'JS'
$(document).ready(function () {
  $('[data-toggle="tooltip"]').tooltip();

  window.openEditModal = function(emp, returnViewId) {
    document.getElementById('empId').value              = emp.id;
    document.getElementById('returnViewId').value       = returnViewId || '';
    document.getElementById('empModalTitle').innerHTML  = '<i class="fas fa-user-edit mr-2"></i>Edit Employee';
    document.getElementById('empSubmitBtn').innerHTML   = '<i class="fas fa-save mr-1"></i>Update Employee';

    document.getElementById('fName').value              = emp.name             ?? '';
    document.getElementById('fGender').value            = emp.gender           ?? '';
    document.getElementById('fCivilStatus').value       = emp.civil_status     ?? '';
    document.getElementById('fBirthdate').value         = emp.birthdate        ?? '';
    document.getElementById('fEmail').value             = emp.email            ?? '';
    document.getElementById('fPhone').value             = emp.phone            ?? '';
    document.getElementById('fAddress').value           = emp.address          ?? '';
    document.getElementById('fBasicSalary').value       = emp.basic_salary     ?? 0;
    document.getElementById('fAllowance').value         = emp.allowance        ?? 0;
    document.getElementById('fDateHired').value         = emp.date_hired       ?? '';
    document.getElementById('fDateStart').value         = emp.date_start       ?? '';
    document.getElementById('fEmploymentType').value    = emp.employment_type  ?? 'regular';
    document.getElementById('fStatus').value            = emp.status           ?? 'active';
    document.getElementById('fDeptId').value            = emp.department_id    ?? '';
    filterPositions(emp.department_id, emp.position_id);

    document.getElementById('fSssNo').value             = emp.sss_no           ?? '';
    document.getElementById('fPhilhealthNo').value      = emp.philhealth_no    ?? '';
    document.getElementById('fPagibigNo').value         = emp.pagibig_no       ?? '';
    document.getElementById('fTinNo').value             = emp.tin_no           ?? '';

    const leaveMap = {
      'sick_leave_balance':        emp.sick_leave_balance,
      'vacation_leave_balance':    emp.vacation_leave_balance,
      'bereavement_leave_balance': emp.bereavement_leave_balance,
      'emergency_leave_balance':   emp.emergency_leave_balance,
      'sil_balance':               emp.sil_balance,
      'maternity_leave_balance':   emp.maternity_leave_balance,
      'paternity_leave_balance':   emp.paternity_leave_balance,
      'solo_parent_leave_balance': emp.solo_parent_leave_balance,
      'vawc_leave_balance':        emp.vawc_leave_balance,
      'magna_carta_leave_balance': emp.magna_carta_leave_balance,
    };
    for (const [key, val] of Object.entries(leaveMap)) {
      const el = document.getElementById('f_' + key);
      if (el) el.value = val ?? 0;
    }

    document.getElementById('fEcName').value            = emp.emergency_contact_name     ?? '';
    document.getElementById('fEcPhone').value           = emp.emergency_contact_phone    ?? '';
    document.getElementById('fEcRelation').value        = emp.emergency_contact_relation ?? '';

    // Apply gender restrictions after populating
    applyLeaveGenderRules(emp.gender ?? '');

    $('#employeeModal .nav-tabs a:first').tab('show');
    $('#employeeModal').modal('show');
  };

  window.openAddModal = function() {
    document.getElementById('employeeForm').reset();
    document.getElementById('empId').value             = '';
    document.getElementById('empModalTitle').innerHTML = '<i class="fas fa-user-plus mr-2"></i>Add Employee';
    document.getElementById('empSubmitBtn').innerHTML  = '<i class="fas fa-save mr-1"></i>Save Employee';
    filterPositions('', '');
    applyLeaveGenderRules('');  // Reset — no gender selected yet
    $('#employeeModal .nav-tabs a:first').tab('show');
    $('#employeeModal').modal('show');
  };

  // ── Gender-based leave restriction ──────────────────────────────────────────
  // female-only: maternity, vawc, magna_carta
  // male-only:   paternity
  // all:         everything else
  const LEAVE_DEFAULTS = {
    'maternity_leave_balance':   105,
    'vawc_leave_balance':        10,
    'magna_carta_leave_balance': 60,
    'paternity_leave_balance':   7,
  };

  function applyLeaveGenderRules(gender) {
    const notice     = document.getElementById('leaveGenderNotice');
    const noticeText = document.getElementById('leaveGenderNoticeText');

    document.querySelectorAll('.leave-field-wrap').forEach(function(wrap) {
      const fieldGender = wrap.getAttribute('data-gender');
      const input       = wrap.querySelector('input');

      if (fieldGender === 'all') return; // always enabled

      let disabled = false;
      if (gender === 'male'   && fieldGender === 'female') disabled = true;
      if (gender === 'female' && fieldGender === 'male')   disabled = true;

      if (disabled) {
        input.value    = 0;
        input.disabled = true;
        wrap.classList.add('leave-field-disabled');
        wrap.title     = 'Not applicable for selected gender';
      } else {
        // Restore default value only if currently 0 or empty (don't overwrite edited values)
        if (!input.value || parseFloat(input.value) === 0) {
          input.value = LEAVE_DEFAULTS[input.name] ?? input.value;
        }
        input.disabled = false;
        wrap.classList.remove('leave-field-disabled');
        wrap.title     = '';
      }
    });

    // Show notice banner only when a gender is selected
    if (gender === 'male' || gender === 'female') {
      const label = gender === 'male' ? 'Male' : 'Female';
      noticeText.textContent = label + ' selected. ';
      notice.classList.add('emp-gender-notice-visible');
      notice.classList.remove('emp-gender-notice-hidden');
    } else {
      notice.classList.remove('emp-gender-notice-visible');
      notice.classList.add('emp-gender-notice-hidden');
    }
  }

  // Live: update leave fields when gender dropdown changes
  document.getElementById('fGender').addEventListener('change', function() {
    applyLeaveGenderRules(this.value);
  });

  $('#fDeptId').on('change', function () {
    filterPositions($(this).val(), '');
  });

  function filterPositions(deptId, selectedPositionId) {
    const $sel = $('#fPositionId');
    $sel.html('<option value="">-- Select Position --</option>');
    if (!deptId) return;
    POSITIONS_DATA.forEach(function(pos) {
      if (pos.department_id == deptId) {
        const opt = new Option(pos.name, pos.id, false, pos.id == selectedPositionId);
        $sel.append(opt);
      }
    });
  }
});
JS;

require_once __DIR__ . '/../layouts/admin_footer.php';
?>