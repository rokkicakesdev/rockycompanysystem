<?php
$pageTitle = 'Employees';
require_once __DIR__ . '/../layouts/admin_header.php';

$msg = '';

// Handle toggle status
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $emp    = Model::findEmployeeById((int)$_GET['toggle']);
    $newSt  = ($emp && $emp['status'] === 'active') ? 'inactive' : 'active';
    Model::toggleEmployeeStatus((int)$_GET['toggle'], $newSt);
    Model::log($_SESSION['user_id'], 'TOGGLE_STATUS', "Set ID {$_GET['toggle']} to {$newSt}");
    header('Location: employees.php'); exit;
}

// Handle create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'                       => trim($_POST['name']),
        'gender'                     => $_POST['gender']                    ?? null,
        'civil_status'               => $_POST['civil_status']              ?? null,
        'birthdate'                  => !empty($_POST['birthdate'])          ? $_POST['birthdate'] : null,
        'address'                    => $_POST['address']                   ?? null,
        'email'                      => $_POST['email']                     ?? null,
        'phone'                      => $_POST['phone']                     ?? null,
        'sss_no'                     => $_POST['sss_no']                    ?? null,
        'philhealth_no'              => $_POST['philhealth_no']             ?? null,
        'pagibig_no'                 => $_POST['pagibig_no']                ?? null,
        'tin_no'                     => $_POST['tin_no']                    ?? null,
        'department_id'              => (int)$_POST['department_id'],
        'position_id'                => (int)$_POST['position_id'],
        'basic_salary'               => (float)$_POST['basic_salary'],
        'allowance'                  => (float)($_POST['allowance'] ?? 0),
        'date_hired'                 => $_POST['date_hired'],
        'employment_type'            => $_POST['employment_type']           ?? 'regular',
        'status'                     => $_POST['status']                    ?? 'active',
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
        'emergency_contact_name'      => $_POST['emergency_contact_name']    ?? null,
        'emergency_contact_phone'     => $_POST['emergency_contact_phone']   ?? null,
        'emergency_contact_relation'  => $_POST['emergency_contact_relation'] ?? null,
        'updated_by'                  => $_SESSION['user_id'],
    ];

    if (!empty($_POST['emp_id'])) {
        $empId = (int)$_POST['emp_id'];
        Model::updateEmployee($empId, $data);
        Model::log($_SESSION['user_id'], 'UPDATE_EMPLOYEE', "Updated employee ID:{$empId}");
        $msg = "<div class='alert alert-success alert-auto-dismiss'>Employee updated successfully.</div>";
    } else {
        Model::createEmployee($data);
        $newId = Database::connect()->lastInsertId();
        Model::log($_SESSION['user_id'], 'CREATE_EMPLOYEE', "Created employee: " . $data['name']);
        $msg = "<div class='alert alert-success alert-auto-dismiss'>Employee created successfully.</div>";
    }
}

$search      = $_GET['q'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$employees   = $search ? Model::searchEmployees($search) : Model::getAllEmployees($filterStatus);
$departments = Model::getAllDepartments();
$positions   = Model::getAllPositions();

// Edit mode
$editEmp = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editEmp = Model::findEmployeeById((int)$_GET['edit']);
}
// View 201
$viewEmp = null;
if (isset($_GET['view_id']) && is_numeric($_GET['view_id'])) {
    $viewEmp = Model::findEmployeeById((int)$_GET['view_id']);
    $viewDocs    = Model::getDocumentsByEmployee((int)$_GET['view_id']);
    $viewLeaves  = Model::getLeaveRequestsByEmployee((int)$_GET['view_id']);
    $viewPayroll = Model::getPayrollByEmployee((int)$_GET['view_id']);
    $viewSalaryH = Model::getSalaryHistory((int)$_GET['view_id']);
    $viewAttendance = Model::getAttendanceByEmployee((int)$_GET['view_id'], date('Y-m'));
}
?>

<div class="page-title-bar">
    <i class="fas fa-users" class="text-primary"></i>
    <h1>Employees</h1>
    <button class="btn btn-sm btn-primary ml-auto" data-toggle="modal" data-target="#employeeModal" onclick="resetForm()">
      <i class="fas fa-plus mr-1"></i>Add Employee
    </button>
  </div>

<?= $msg ?>

    <!-- Search & Filter -->
    <div class="card mb-3">
      <div class="card-body" style="padding: 16px 20px;">
        <form method="GET">
          <div class="row align-items-center" style="row-gap: 10px;">
            <div class="col-12 col-sm-5">
              <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search name, employee no, department...">
            </div>
            <div class="col-12 col-sm-3">
              <select name="status" class="form-control">
                <option value="">All Status</option>
                <?php foreach (EMPLOYEE_STATUS as $k => $v): ?>
                  <option value="<?= $k ?>" <?= $filterStatus===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-sm-4">
              <div class="d-flex align-items-center">
                <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-search mr-1"></i>Search</button>
                <a href="employees.php" class="btn btn-outline-secondary flex-fill ml-2">Reset</a>
                <small class="text-muted ml-3 text-nowrap d-none d-sm-inline"><?= count($employees) ?> employee(s)</small>
              </div>
            </div>
          </div>
          <div class="text-right d-sm-none mt-2">
            <small class="text-muted"><?= count($employees) ?> employee(s)</small>
          </div>
        </form>
      </div>
    </div>

    <!-- Employee List or 201 View -->
    <?php if ($viewEmp): ?>
    <!-- 201 FILE VIEW -->
    <div class="d-flex align-items-center mb-3">
      <a href="employees.php" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-arrow-left"></i></a>
      <h5 class="mb-0">Employee 201 File: <strong><?= htmlspecialchars($viewEmp['name']) ?></strong></h5>
      <span class="status-badge badge-<?= $viewEmp['status'] ?> ml-2"><?= ucfirst($viewEmp['status']) ?></span>
    </div>
    <div class="row">
      <!-- Profile Card -->
      <div class="col-lg-4 mb-4">
        <div class="card">
          <div class="card-body text-center">
            <div class="employee-avatar">
              <?= strtoupper(substr($viewEmp['name'], 0, 1)) ?>
            </div>
            <h6><?= htmlspecialchars($viewEmp['name']) ?></h6>
            <div class="text-muted" style="font-size:.8rem;"><?= $viewEmp['employee_no'] ?></div>
            <div><?= htmlspecialchars($viewEmp['position']) ?></div>
            <div class="text-muted"><?= htmlspecialchars($viewEmp['department']) ?></div>
            <hr>
            <table class="table table-sm text-left mb-0">
              <tr><td class="text-muted">Employment</td><td><?= ucfirst($viewEmp['employment_type'] ?? 'regular') ?></td></tr>
              <tr><td class="text-muted">Date Hired</td><td><?= date('M d, Y', strtotime($viewEmp['date_hired'])) ?></td></tr>
              <tr><td class="text-muted">Basic Salary</td><td>₱<?= number_format($viewEmp['basic_salary'], 2) ?></td></tr>
              <tr><td class="text-muted">Allowance</td><td>₱<?= number_format($viewEmp['allowance'], 2) ?></td></tr>
            </table>
            <a href="employees.php?edit=<?= $viewEmp['id'] ?>" class="btn btn-sm btn-primary w-100 mt-3">
              <i class="fas fa-edit mr-1"></i>Edit Profile
            </a>
          </div>
        </div>
        <!-- Leave Balances -->
        <div class="card mt-3">
          <div class="card-header">Leave Balances</div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <?php
              $leaveBalances = [
                'Sick Leave'       => $viewEmp['sick_leave_balance'],
                'Vacation Leave'   => $viewEmp['vacation_leave_balance'],
                'Bereavement'      => $viewEmp['bereavement_leave_balance'],
                'Emergency'        => $viewEmp['emergency_leave_balance'],
                'SIL'              => $viewEmp['sil_balance'],
              ];
              foreach ($leaveBalances as $name => $bal):
              ?>
              <tr>
                <td style="font-size:.8rem;"><?= $name ?></td>
                <td class="text-right">
                  <strong style="color:<?= $bal > 0 ? '#16a34a' : '#dc2626' ?>;"><?= $bal ?></strong>
                  <small class="text-muted">days</small>
                </td>
              </tr>
              <?php endforeach; ?>
            </table>
          </div>
        </div>
      </div>

      <!-- Tabs: Details, Payroll, Documents -->
      <div class="col-lg-8 mb-4">
        <div class="card">
          <div class="card-header p-0">
            <ul class="nav nav-tabs card-header-tabs ml-0 px-3 pt-2">
              <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tabPersonal">Personal Info</a></li>
              <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabPayroll">Payroll History</a></li>
              <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabLeaves">Leave History</a></li>
              <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabSalary">Salary History</a></li>
            </ul>
          </div>
          <div class="card-body tab-content">
            <!-- Personal -->
            <div class="tab-pane fade show active" id="tabPersonal">
              <div class="row">
                <div class="col-6"><p class="mb-2"><small class="text-muted">Gender</small><br><?= ucfirst($viewEmp['gender'] ?? '—') ?></p></div>
                <div class="col-6"><p class="mb-2"><small class="text-muted">Civil Status</small><br><?= ucfirst($viewEmp['civil_status'] ?? '—') ?></p></div>
                <div class="col-6"><p class="mb-2"><small class="text-muted">Birthdate</small><br><?= $viewEmp['birthdate'] ? date('M d, Y', strtotime($viewEmp['birthdate'])) : '—' ?></p></div>
                <div class="col-6"><p class="mb-2"><small class="text-muted">Email</small><br><?= htmlspecialchars($viewEmp['email'] ?? '—') ?></p></div>
                <div class="col-6"><p class="mb-2"><small class="text-muted">Phone</small><br><?= htmlspecialchars($viewEmp['phone'] ?? '—') ?></p></div>
                <div class="col-12"><p class="mb-2"><small class="text-muted">Address</small><br><?= htmlspecialchars($viewEmp['address'] ?? '—') ?></p></div>
                <div class="col-12"><hr><h6 style="font-size:.8rem;text-transform:uppercase;letter-spacing:1px;color:#64748b;">Government IDs</h6></div>
                <div class="col-6"><p class="mb-2"><small class="text-muted">SSS No.</small><br><?= $viewEmp['sss_no'] ?? '—' ?></p></div>
                <div class="col-6"><p class="mb-2"><small class="text-muted">PhilHealth No.</small><br><?= $viewEmp['philhealth_no'] ?? '—' ?></p></div>
                <div class="col-6"><p class="mb-2"><small class="text-muted">Pag-IBIG No.</small><br><?= $viewEmp['pagibig_no'] ?? '—' ?></p></div>
                <div class="col-6"><p class="mb-2"><small class="text-muted">TIN No.</small><br><?= $viewEmp['tin_no'] ?? '—' ?></p></div>
                <div class="col-12"><hr><h6 style="font-size:.8rem;text-transform:uppercase;letter-spacing:1px;color:#64748b;">Emergency Contact</h6></div>
                <div class="col-4"><p class="mb-2"><small class="text-muted">Name</small><br><?= htmlspecialchars($viewEmp['emergency_contact_name'] ?? '—') ?></p></div>
                <div class="col-4"><p class="mb-2"><small class="text-muted">Phone</small><br><?= $viewEmp['emergency_contact_phone'] ?? '—' ?></p></div>
                <div class="col-4"><p class="mb-2"><small class="text-muted">Relation</small><br><?= htmlspecialchars($viewEmp['emergency_contact_relation'] ?? '—') ?></p></div>
              </div>
            </div>
            <!-- Payroll -->
            <div class="tab-pane fade" id="tabPayroll">
              <table class="table table-sm">
                <thead><tr><th>Period</th><th>Gross Pay</th><th>Deductions</th><th>Net Pay</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($viewPayroll as $pr): ?>
                  <tr>
                    <td><?= $pr['period'] ?></td>
                    <td>₱<?= number_format($pr['gross_pay'],2) ?></td>
                    <td>₱<?= number_format($pr['total_deductions'],2) ?></td>
                    <td><strong>₱<?= number_format($pr['net_pay'],2) ?></strong></td>
                    <td><span class="status-badge badge-<?= $pr['status'] ?>"><?= ucfirst($pr['status']) ?></span></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($viewPayroll)): ?><tr><td colspan="5" class="text-center text-muted">No payroll records yet.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
            <!-- Leaves -->
            <div class="tab-pane fade" id="tabLeaves">
              <table class="table table-sm">
                <thead><tr><th>Type</th><th>Period</th><th>Days</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($viewLeaves as $lr): ?>
                  <tr>
                    <td><?= LEAVE_TYPES[$lr['leave_type']] ?? $lr['leave_type'] ?></td>
                    <td><?= date('M d', strtotime($lr['date_from'])) ?> – <?= date('M d, Y', strtotime($lr['date_to'])) ?></td>
                    <td><?= $lr['days_applied'] ?></td>
                    <td><span class="status-badge badge-<?= $lr['status'] ?>"><?= ucfirst($lr['status']) ?></span></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($viewLeaves)): ?><tr><td colspan="4" class="text-center text-muted">No leave requests.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
            <!-- Salary History -->
            <div class="tab-pane fade" id="tabSalary">
              <table class="table table-sm">
                <thead><tr><th>Effective Date</th><th>Old Basic</th><th>New Basic</th><th>Reason</th><th>By</th></tr></thead>
                <tbody>
                  <?php foreach ($viewSalaryH as $sh): ?>
                  <tr>
                    <td><?= date('M d, Y', strtotime($sh['effective_date'])) ?></td>
                    <td>₱<?= number_format($sh['old_basic_salary'],2) ?></td>
                    <td class="text-success"><strong>₱<?= number_format($sh['new_basic_salary'],2) ?></strong></td>
                    <td><?= htmlspecialchars($sh['reason'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($sh['approved_by_name'] ?? '—') ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($viewSalaryH)): ?><tr><td colspan="5" class="text-center text-muted">No salary changes.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php else: ?>
    <!-- EMPLOYEE TABLE -->
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Department / Position</th>
                <th>Employment</th>
                <th>Basic Salary</th>
                <th>Date Hired</th>
                <th>Status</th>
                <th class="text-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($employees)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">No employees found.</td></tr>
              <?php endif; ?>
              <?php foreach ($employees as $emp): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($emp['name']) ?></strong><br>
                  <small class="text-muted"><?= $emp['employee_no'] ?></small>
                </td>
                <td>
                  <?= htmlspecialchars($emp['department']) ?><br>
                  <small class="text-muted"><?= htmlspecialchars($emp['position']) ?></small>
                </td>
                <td><small><?= ucfirst($emp['employment_type'] ?? 'regular') ?></small></td>
                <td>₱<?= number_format($emp['basic_salary'], 2) ?></td>
                <td><?= date('M d, Y', strtotime($emp['date_hired'])) ?></td>
                <td><span class="status-badge badge-<?= $emp['status'] ?>"><?= ucfirst($emp['status']) ?></span></td>
                <td class="text-nowrap">
                  <div class="action-btn-group">
                    <a href="employees.php?view_id=<?= $emp['id'] ?>" class="btn btn-xs btn-info" title="View 201 File"><i class="fas fa-eye"></i></a>
                    <a href="employees.php?edit=<?= $emp['id'] ?>" class="btn btn-xs btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                    <a href="employees.php?toggle=<?= $emp['id'] ?>" class="btn btn-xs <?= $emp['status']==='active'?'btn-secondary':'btn-success' ?>"
                       title="<?= $emp['status']==='active'?'Deactivate':'Activate' ?>"
                       onclick="return confirm('Change status?')">
                      <i class="fas fa-<?= $emp['status']==='active'?'ban':'check' ?>"></i>
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>
</div>

<!-- Employee Add/Edit Modal -->
<div class="modal fade" id="employeeModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="emp_id" id="empId">
        <div class="modal-header">
          <h5 class="modal-title" id="empModalTitle">Add Employee</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <ul class="nav nav-tabs mb-3">
            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tabBasic">Basic Info</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabGovt">Government IDs</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabLeaveB">Leave Balances</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabEmergency">Emergency Contact</a></li>
          </ul>
          <div class="tab-content">
            <!-- Basic Info Tab -->
            <div class="tab-pane fade show active" id="tabBasic">
              <div class="row">
                <div class="col-md-6"><div class="form-group"><label>Full Name *</label><input type="text" name="name" id="fName" class="form-control" required></div></div>
                <div class="col-md-3"><div class="form-group"><label>Gender</label><select name="gender" id="fGender" class="form-control"><option value="">-- Select --</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div></div>
                <div class="col-md-3"><div class="form-group"><label>Civil Status</label><select name="civil_status" id="fCivilStatus" class="form-control"><option value="">-- Select --</option><option value="single">Single</option><option value="married">Married</option><option value="widowed">Widowed</option><option value="separated">Separated</option></select></div></div>
                <div class="col-md-4"><div class="form-group"><label>Birthdate</label><input type="date" name="birthdate" id="fBirthdate" class="form-control"></div></div>
                <div class="col-md-4"><div class="form-group"><label>Email</label><input type="email" name="email" id="fEmail" class="form-control"></div></div>
                <div class="col-md-4"><div class="form-group"><label>Phone</label><input type="text" name="phone" id="fPhone" class="form-control"></div></div>
                <div class="col-md-12"><div class="form-group"><label>Address</label><textarea name="address" id="fAddress" class="form-control" rows="2"></textarea></div></div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Department *</label>
                    <select name="department_id" id="fDeptId" class="form-control" required>
                      <option value="">-- Select --</option>
                      <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Position *</label>
                    <select name="position_id" id="fPositionId" class="form-control" required>
                      <option value="">-- Select Department First --</option>
                      <?php foreach ($positions as $p): ?>
                        <option value="<?= $p['id'] ?>" data-dept="<?= $p['department_id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-4"><div class="form-group"><label>Basic Salary *</label><input type="number" step="0.01" name="basic_salary" id="fBasicSalary" class="form-control" required></div></div>
                <div class="col-md-4"><div class="form-group"><label>Allowance</label><input type="number" step="0.01" name="allowance" id="fAllowance" class="form-control" value="0"></div></div>
                <div class="col-md-4"><div class="form-group"><label>Date Hired *</label><input type="date" name="date_hired" id="fDateHired" class="form-control" required></div></div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label>Employment Type</label>
                    <select name="employment_type" id="fEmpType" class="form-control">
                      <?php foreach (EMPLOYMENT_TYPES as $k => $v): ?>
                        <option value="<?= $k ?>"><?= $v ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-4">
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
            <!-- Govt IDs Tab -->
            <div class="tab-pane fade" id="tabGovt">
              <div class="row">
                <div class="col-md-6"><div class="form-group"><label>SSS Number</label><input type="text" name="sss_no" id="fSssNo" class="form-control" placeholder="XX-XXXXXXX-X"></div></div>
                <div class="col-md-6"><div class="form-group"><label>PhilHealth Number</label><input type="text" name="philhealth_no" id="fPhilhealthNo" class="form-control" placeholder="XX-XXXXXXXXX-X"></div></div>
                <div class="col-md-6"><div class="form-group"><label>Pag-IBIG (HDMF) Number</label><input type="text" name="pagibig_no" id="fPagibigNo" class="form-control" placeholder="XXXX-XXXX-XXXX"></div></div>
                <div class="col-md-6"><div class="form-group"><label>TIN Number</label><input type="text" name="tin_no" id="fTinNo" class="form-control" placeholder="XXX-XXX-XXX-XXX"></div></div>
              </div>
            </div>
            <!-- Leave Balances Tab -->
            <div class="tab-pane fade" id="tabLeaveB">
              <div class="row">
                <div class="col-md-3"><div class="form-group"><label>Sick Leave</label><input type="number" step="0.5" name="sick_leave_balance" id="fSL" class="form-control" value="10"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Vacation Leave</label><input type="number" step="0.5" name="vacation_leave_balance" id="fVL" class="form-control" value="10"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Bereavement</label><input type="number" step="0.5" name="bereavement_leave_balance" id="fBL" class="form-control" value="5"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Emergency</label><input type="number" step="0.5" name="emergency_leave_balance" id="fEL" class="form-control" value="5"></div></div>
                <div class="col-md-3"><div class="form-group"><label>SIL</label><input type="number" step="0.5" name="sil_balance" id="fSIL" class="form-control" value="5"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Maternity</label><input type="number" step="0.5" name="maternity_leave_balance" id="fML" class="form-control" value="105"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Paternity</label><input type="number" step="0.5" name="paternity_leave_balance" id="fPL" class="form-control" value="7"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Solo Parent</label><input type="number" step="0.5" name="solo_parent_leave_balance" id="fSPL" class="form-control" value="7"></div></div>
              </div>
            </div>
            <!-- Emergency Contact Tab -->
            <div class="tab-pane fade" id="tabEmergency">
              <div class="row">
                <div class="col-md-5"><div class="form-group"><label>Contact Name</label><input type="text" name="emergency_contact_name" id="fECName" class="form-control"></div></div>
                <div class="col-md-4"><div class="form-group"><label>Contact Phone</label><input type="text" name="emergency_contact_phone" id="fECPhone" class="form-control"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Relationship</label><input type="text" name="emergency_contact_relation" id="fECRelation" class="form-control" placeholder="e.g. Spouse"></div></div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="empSubmitBtn">Save Employee</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetForm() {
  document.getElementById('empId').value = '';
  document.getElementById('empModalTitle').textContent = 'Add Employee';
  document.getElementById('empSubmitBtn').textContent  = 'Save Employee';
  document.querySelector('#employeeModal form').reset();
}

// Dept -> Position filter
document.getElementById('fDeptId').addEventListener('change', function() {
  const deptId = this.value;
  const posSelect = document.getElementById('fPositionId');
  posSelect.innerHTML = '<option value="">-- Select --</option>';
  document.querySelectorAll('#fPositionId option[data-dept]').forEach(() => {});
  <?php foreach ($positions as $p): ?>
  (function() {
    if ('<?= $p['department_id'] ?>' === deptId) {
      const opt = document.createElement('option');
      opt.value = '<?= $p['id'] ?>';
      opt.textContent = '<?= addslashes($p['name']) ?>';
      posSelect.appendChild(opt);
    }
  })();
  <?php endforeach; ?>
});

<?php if ($editEmp): ?>
// Pre-fill edit form
window.addEventListener('load', function() {
  document.getElementById('empId').value        = '<?= $editEmp['id'] ?>';
  document.getElementById('empModalTitle').textContent = 'Edit Employee';
  document.getElementById('empSubmitBtn').textContent  = 'Update Employee';
  document.getElementById('fName').value        = '<?= addslashes($editEmp['name']) ?>';
  document.getElementById('fGender').value      = '<?= $editEmp['gender'] ?? '' ?>';
  document.getElementById('fCivilStatus').value = '<?= $editEmp['civil_status'] ?? '' ?>';
  document.getElementById('fBirthdate').value   = '<?= $editEmp['birthdate'] ?? '' ?>';
  document.getElementById('fEmail').value       = '<?= addslashes($editEmp['email'] ?? '') ?>';
  document.getElementById('fPhone').value       = '<?= $editEmp['phone'] ?? '' ?>';
  document.getElementById('fAddress').value     = '<?= addslashes($editEmp['address'] ?? '') ?>';
  document.getElementById('fDeptId').value      = '<?= $editEmp['department_id'] ?>';
  document.getElementById('fDeptId').dispatchEvent(new Event('change'));
  setTimeout(() => { document.getElementById('fPositionId').value = '<?= $editEmp['position_id'] ?>'; }, 100);
  document.getElementById('fBasicSalary').value = '<?= $editEmp['basic_salary'] ?>';
  document.getElementById('fAllowance').value   = '<?= $editEmp['allowance'] ?>';
  document.getElementById('fDateHired').value   = '<?= $editEmp['date_hired'] ?>';
  document.getElementById('fEmpType').value     = '<?= $editEmp['employment_type'] ?? 'regular' ?>';
  document.getElementById('fStatus').value      = '<?= $editEmp['status'] ?>';
  document.getElementById('fSssNo').value       = '<?= $editEmp['sss_no'] ?? '' ?>';
  document.getElementById('fPhilhealthNo').value= '<?= $editEmp['philhealth_no'] ?? '' ?>';
  document.getElementById('fPagibigNo').value   = '<?= $editEmp['pagibig_no'] ?? '' ?>';
  document.getElementById('fTinNo').value       = '<?= $editEmp['tin_no'] ?? '' ?>';
  document.getElementById('fSL').value  = '<?= $editEmp['sick_leave_balance'] ?>';
  document.getElementById('fVL').value  = '<?= $editEmp['vacation_leave_balance'] ?>';
  document.getElementById('fBL').value  = '<?= $editEmp['bereavement_leave_balance'] ?>';
  document.getElementById('fEL').value  = '<?= $editEmp['emergency_leave_balance'] ?>';
  document.getElementById('fSIL').value = '<?= $editEmp['sil_balance'] ?>';
  document.getElementById('fML').value  = '<?= $editEmp['maternity_leave_balance'] ?>';
  document.getElementById('fPL').value  = '<?= $editEmp['paternity_leave_balance'] ?>';
  document.getElementById('fSPL').value = '<?= $editEmp['solo_parent_leave_balance'] ?>';
  document.getElementById('fECName').value     = '<?= addslashes($editEmp['emergency_contact_name'] ?? '') ?>';
  document.getElementById('fECPhone').value    = '<?= $editEmp['emergency_contact_phone'] ?? '' ?>';
  document.getElementById('fECRelation').value = '<?= addslashes($editEmp['emergency_contact_relation'] ?? '') ?>';
  $('#employeeModal').modal('show');
});
<?php endif; ?>
</script>
<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>