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
    header('Location: employees.php');
    exit;
}

// Handle create/update (your existing code)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'                       => trim($_POST['name'] ?? ''),
        'gender'                     => $_POST['gender'] ?? null,
        'civil_status'               => $_POST['civil_status'] ?? null,
        'birthdate'                  => !empty($_POST['birthdate']) ? $_POST['birthdate'] : null,
        'address'                    => $_POST['address'] ?? null,
        'email'                      => $_POST['email'] ?? null,
        'phone'                      => $_POST['phone'] ?? null,
        'sss_no'                     => $_POST['sss_no'] ?? null,
        'philhealth_no'              => $_POST['philhealth_no'] ?? null,
        'pagibig_no'                 => $_POST['pagibig_no'] ?? null,
        'tin_no'                     => $_POST['tin_no'] ?? null,
        'department_id'              => (int)($_POST['department_id'] ?? 0),
        'position_id'                => (int)($_POST['position_id'] ?? 0),
        'basic_salary'               => (float)($_POST['basic_salary'] ?? 0),
        'allowance'                  => (float)($_POST['allowance'] ?? 0),
        'date_hired'                 => $_POST['date_hired'] ?? null,
        'employment_type'            => $_POST['employment_type'] ?? 'regular',
        'status'                     => $_POST['status'] ?? 'active',
        'sick_leave_balance'         => (float)($_POST['sick_leave_balance'] ?? 10),
        'vacation_leave_balance'     => (float)($_POST['vacation_leave_balance'] ?? 10),
        'bereavement_leave_balance'  => (float)($_POST['bereavement_leave_balance'] ?? 5),
        'emergency_leave_balance'    => (float)($_POST['emergency_leave_balance'] ?? 5),
        'sil_balance'                => (float)($_POST['sil_balance'] ?? 5),
        'maternity_leave_balance'    => (float)($_POST['maternity_leave_balance'] ?? 105),
        'paternity_leave_balance'    => (float)($_POST['paternity_leave_balance'] ?? 7),
        'solo_parent_leave_balance'  => (float)($_POST['solo_parent_leave_balance'] ?? 7),
        'vawc_leave_balance'         => (float)($_POST['vawc_leave_balance'] ?? 10),
        'magna_carta_leave_balance'  => (float)($_POST['magna_carta_leave_balance'] ?? 60),
        'emergency_contact_name'     => $_POST['emergency_contact_name'] ?? null,
        'emergency_contact_phone'    => $_POST['emergency_contact_phone'] ?? null,
        'emergency_contact_relation' => $_POST['emergency_contact_relation'] ?? null,
        'updated_by'                 => $_SESSION['user_id'] ?? null,
    ];

    if (!empty($_POST['emp_id'])) {
        $empId = (int)$_POST['emp_id'];
        Model::updateEmployee($empId, $data);
        Model::log($_SESSION['user_id'], 'UPDATE_EMPLOYEE', "Updated employee ID:{$empId}");
        $msg = "<div class='alert alert-success alert-dismissible fade show' role='alert'>Employee updated successfully.<button type='button' class='close' data-dismiss='alert'>×</button></div>";
    } else {
        Model::createEmployee($data);
        $newId = Database::getInstance()->lastInsertId();
        Model::log($_SESSION['user_id'], 'CREATE_EMPLOYEE', "Created employee: " . $data['name']);
        $msg = "<div class='alert alert-success alert-dismissible fade show' role='alert'>Employee created successfully.<button type='button' class='close' data-dismiss='alert'>×</button></div>";
    }
}

// Fetch data
$search       = $_GET['q'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$employees    = $search ? Model::searchEmployees($search) : Model::getAllEmployees($filterStatus);
$departments  = Model::getAllDepartments();
$positions    = Model::getAllPositions();

// Edit / View mode
$editEmp = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editEmp = Model::findEmployeeById((int)$_GET['edit']);
}

$viewEmp = null;
if (isset($_GET['view_id']) && is_numeric($_GET['view_id'])) {
    $viewEmp = Model::findEmployeeById((int)$_GET['view_id']);
    $viewDocs     = Model::getDocumentsByEmployee((int)$_GET['view_id']);
    $viewLeaves   = Model::getLeaveRequestsByEmployee((int)$_GET['view_id']);
    $viewPayroll  = Model::getPayrollByEmployee((int)$_GET['view_id']);
    $viewSalaryH  = Model::getSalaryHistory((int)$_GET['view_id']);
    $viewAttendance = Model::getAttendanceByEmployee((int)$_GET['view_id'], date('Y-m'));
}
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
  <!-- Content Header -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Employees Management</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard">Dashboard</a></li>
            <li class="breadcrumb-item active">Employees</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">

      <?= $msg ?>

      <?php if ($viewEmp): ?>
        <!-- 201 File View Mode -->
        <div class="d-flex align-items-center mb-4">
          <a href="employees.php" class="btn btn-outline-secondary mr-3">
            <i class="fas fa-arrow-left mr-1"></i> Back to List
          </a>
          <h4 class="mb-0">Employee 201 File: <strong><?= htmlspecialchars($viewEmp['name']) ?></strong></h4>
          <span class="badge badge-<?= $viewEmp['status'] === 'active' ? 'success' : 'danger' ?> ml-3">
            <?= ucfirst($viewEmp['status']) ?>
          </span>
        </div>

        <div class="row">
          <!-- Profile Card -->
          <div class="col-lg-4">
            <div class="card card-primary card-outline">
              <div class="card-body box-profile text-center">
                <div class="employee-avatar mb-3">
                  <?= strtoupper(substr($viewEmp['name'], 0, 1)) ?>
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($viewEmp['name']) ?></h5>
                <p class="text-muted"><?= htmlspecialchars($viewEmp['employee_no'] ?? 'N/A') ?></p>
                <p><strong><?= htmlspecialchars($viewEmp['position'] ?? 'N/A') ?></strong><br>
                   <small><?= htmlspecialchars($viewEmp['department'] ?? 'N/A') ?></small></p>

                <ul class="list-group list-group-unbordered mb-3">
                  <li class="list-group-item">
                    <b>Employment Type</b> <span class="float-right"><?= ucfirst($viewEmp['employment_type'] ?? 'Regular') ?></span>
                  </li>
                  <li class="list-group-item">
                    <b>Date Hired</b> <span class="float-right"><?= $viewEmp['date_hired'] ? date('M d, Y', strtotime($viewEmp['date_hired'])) : '—' ?></span>
                  </li>
                  <li class="list-group-item">
                    <b>Basic Salary</b> <span class="float-right">₱<?= number_format($viewEmp['basic_salary'], 2) ?></span>
                  </li>
                  <li class="list-group-item">
                    <b>Allowance</b> <span class="float-right">₱<?= number_format($viewEmp['allowance'], 2) ?></span>
                  </li>
                </ul>

                <a href="employees.php?edit=<?= $viewEmp['id'] ?>" class="btn btn-primary btn-block">
                  <i class="fas fa-edit mr-1"></i> Edit Profile
                </a>
              </div>
            </div>

            <!-- Leave Balances -->
            <div class="card mt-3">
              <div class="card-header bg-info">
                <h3 class="card-title">Leave Balances</h3>
              </div>
              <div class="card-body p-0">
                <table class="table table-sm mb-0">
                  <?php
                  $leaveBalances = [
                    'Sick Leave'       => $viewEmp['sick_leave_balance'] ?? 0,
                    'Vacation Leave'   => $viewEmp['vacation_leave_balance'] ?? 0,
                    'Bereavement'      => $viewEmp['bereavement_leave_balance'] ?? 0,
                    'Emergency'        => $viewEmp['emergency_leave_balance'] ?? 0,
                    'SIL'              => $viewEmp['sil_balance'] ?? 0,
                  ];
                  foreach ($leaveBalances as $name => $bal):
                  ?>
                    <tr>
                      <td style="width:70%; font-size:0.9rem;"><?= $name ?></td>
                      <td class="text-right">
                        <strong style="color:<?= $bal > 0 ? '#28a745' : '#dc3545' ?>;"><?= $bal ?></strong>
                        <small class="text-muted">days</small>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </table>
              </div>
            </div>
          </div>

          <!-- Tabs: Personal, Payroll, Leaves, Salary History -->
          <div class="col-lg-8">
            <div class="card card-primary card-outline">
              <div class="card-header p-0 border-bottom-0">
                <ul class="nav nav-tabs" id="employeeTabs" role="tablist">
                  <li class="nav-item">
                    <a class="nav-link active" id="personal-tab" data-toggle="pill" href="#personal" role="tab">Personal Info</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" id="payroll-tab" data-toggle="pill" href="#payroll" role="tab">Payroll History</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" id="leaves-tab" data-toggle="pill" href="#leaves" role="tab">Leave History</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" id="salary-tab" data-toggle="pill" href="#salary" role="tab">Salary History</a>
                  </li>
                </ul>
              </div>
              <div class="card-body">
                <div class="tab-content" id="employeeTabsContent">
                  <!-- Personal Info -->
                  <div class="tab-pane fade show active" id="personal" role="tabpanel">
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
                      <div class="col-12 mt-4">
                        <h6 class="text-uppercase text-muted">Government IDs</h6>
                        <hr class="my-2">
                        <p><strong>SSS No.:</strong> <?= $viewEmp['sss_no'] ?? '—' ?></p>
                        <p><strong>PhilHealth No.:</strong> <?= $viewEmp['philhealth_no'] ?? '—' ?></p>
                        <p><strong>Pag-IBIG No.:</strong> <?= $viewEmp['pagibig_no'] ?? '—' ?></p>
                        <p><strong>TIN No.:</strong> <?= $viewEmp['tin_no'] ?? '—' ?></p>
                      </div>
                      <div class="col-12 mt-4">
                        <h6 class="text-uppercase text-muted">Emergency Contact</h6>
                        <hr class="my-2">
                        <p><strong>Name:</strong> <?= htmlspecialchars($viewEmp['emergency_contact_name'] ?? '—') ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($viewEmp['emergency_contact_phone'] ?? '—') ?></p>
                        <p><strong>Relation:</strong> <?= htmlspecialchars($viewEmp['emergency_contact_relation'] ?? '—') ?></p>
                      </div>
                    </div>
                  </div>

                  <!-- Payroll History -->
                  <div class="tab-pane fade" id="payroll" role="tabpanel">
                    <table class="table table-sm table-bordered">
                      <thead class="thead-light">
                        <tr>
                          <th>Period</th>
                          <th>Gross Pay</th>
                          <th>Deductions</th>
                          <th>Net Pay</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (!empty($viewPayroll)): ?>
                          <?php foreach ($viewPayroll as $pr): ?>
                            <tr>
                              <td><?= htmlspecialchars($pr['period']) ?></td>
                              <td>₱<?= number_format($pr['gross_pay'] ?? 0, 2) ?></td>
                              <td>₱<?= number_format($pr['total_deductions'] ?? 0, 2) ?></td>
                              <td><strong>₱<?= number_format($pr['net_pay'] ?? 0, 2) ?></strong></td>
                              <td>
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

                  <!-- Leave History -->
                  <div class="tab-pane fade" id="leaves" role="tabpanel">
                    <table class="table table-sm table-bordered">
                      <thead class="thead-light">
                        <tr>
                          <th>Type</th>
                          <th>Period</th>
                          <th>Days</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (!empty($viewLeaves)): ?>
                          <?php foreach ($viewLeaves as $lr): ?>
                            <tr>
                              <td><?= htmlspecialchars(LEAVE_TYPES[$lr['leave_type']] ?? ucfirst($lr['leave_type'])) ?></td>
                              <td><?= date('M d', strtotime($lr['date_from'])) ?> – <?= date('M d, Y', strtotime($lr['date_to'])) ?></td>
                              <td><?= $lr['days_applied'] ?></td>
                              <td>
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

                  <!-- Salary History -->
                  <div class="tab-pane fade" id="salary" role="tabpanel">
                    <table class="table table-sm table-bordered">
                      <thead class="thead-light">
                        <tr>
                          <th>Effective Date</th>
                          <th>Old Basic</th>
                          <th>New Basic</th>
                          <th>Reason</th>
                          <th>Approved By</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (!empty($viewSalaryH)): ?>
                          <?php foreach ($viewSalaryH as $sh): ?>
                            <tr>
                              <td><?= date('M d, Y', strtotime($sh['effective_date'])) ?></td>
                              <td>₱<?= number_format($sh['old_basic_salary'], 2) ?></td>
                              <td class="text-success"><strong>₱<?= number_format($sh['new_basic_salary'], 2) ?></strong></td>
                              <td><?= htmlspecialchars($sh['reason'] ?? '—') ?></td>
                              <td><?= htmlspecialchars($sh['approved_by_name'] ?? '—') ?></td>
                            </tr>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <tr><td colspan="5" class="text-center text-muted py-3">No salary history found.</td></tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      <?php else: ?>
        <!-- Main Employee List -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Employee List</h3>
            <div class="card-tools">
              <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#employeeModal" onclick="resetForm()">
                <i class="fas fa-plus mr-1"></i> Add Employee
              </button>
            </div>
          </div>

          <div class="card-body">
            <!-- Search & Filter -->
            <form method="GET" class="mb-4">
              <div class="row">
                <div class="col-md-5">
                  <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search by name, employee no, email...">
                </div>
                <div class="col-md-3">
                  <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <?php foreach (EMPLOYEE_STATUS as $k => $v): ?>
                      <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <button type="submit" class="btn btn-primary"><i class="fas fa-search mr-1"></i> Filter</button>
                  <a href="employees.php" class="btn btn-outline-secondary ml-2">Reset</a>
                </div>
              </div>
            </form>

            <!-- Table -->
            <div class="table-responsive">
              <table id="employeesTable" class="table table-bordered table-hover table-striped">
                <thead class="thead-dark">
                  <tr>
                    <th>Employee</th>
                    <th>Department / Position</th>
                    <th>Employment</th>
                    <th class="text-right">Basic Salary</th>
                    <th>Date Hired</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($employees)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No employees found.</td></tr>
                  <?php else: ?>
                    <?php foreach ($employees as $emp): ?>
                      <tr>
                        <td>
                          <strong><?= htmlspecialchars($emp['name']) ?></strong><br>
                          <small class="text-muted"><?= htmlspecialchars($emp['employee_no'] ?? '—') ?></small>
                        </td>
                        <td>
                          <?= htmlspecialchars($emp['department'] ?? '—') ?><br>
                          <small class="text-muted"><?= htmlspecialchars($emp['position'] ?? '—') ?></small>
                        </td>
                        <td><?= ucfirst($emp['employment_type'] ?? 'Regular') ?></td>
                        <td class="text-right">₱<?= number_format($emp['basic_salary'] ?? 0, 2) ?></td>
                        <td><?= $emp['date_hired'] ? date('M d, Y', strtotime($emp['date_hired'])) : '—' ?></td>
                        <td>
                          <span class="badge badge-<?= $emp['status'] === 'active' ? 'success' : 'danger' ?>">
                            <?= ucfirst($emp['status']) ?>
                          </span>
                        </td>
                        <td class="text-center">
                          <div class="btn-group btn-group-sm">
                            <a href="employees.php?view_id=<?= $emp['id'] ?>" 
                               class="btn btn-info me-1" 
                               data-toggle="tooltip" 
                               title="View 201 File">
                              <i class="fas fa-eye"></i>
                            </a>
                            <a href="employees.php?edit=<?= $emp['id'] ?>" 
                               class="btn btn-warning me-1" 
                               data-toggle="tooltip" 
                               title="Edit">
                              <i class="fas fa-edit"></i>
                            </a>
                            <a href="employees.php?toggle=<?= $emp['id'] ?>" 
                               class="btn <?= $emp['status'] === 'active' ? 'btn-secondary' : 'btn-success' ?> me-1"
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
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="employeeModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <form method="POST" id="employeeForm">
        <input type="hidden" name="emp_id" id="empId">
        <div class="modal-header">
          <h5 class="modal-title" id="empModalTitle">Add Employee</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <ul class="nav nav-tabs mb-3">
            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tabBasic">Basic Info</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabGovt">Government IDs</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabLeaveB">Leave Balances</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabEmergency">Emergency Contact</a></li>
          </ul>
          <div class="tab-content">
            <!-- Your existing tab content - kept as-is -->
            <!-- ... paste your original tab panes here ... -->
            <!-- For brevity, I'm not repeating the full form fields again -->
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

<!-- Scripts -->
<script>
$(document).ready(function() {
  // Initialize DataTables
  $('#employeesTable').DataTable({
    responsive: true,
    pageLength: 10,
    lengthChange: false,
    searching: true,
    ordering: true,
    info: true,
    autoWidth: false,
    language: { search: "Filter employees:" }
  });

  // Enable tooltips
  $('[data-toggle="tooltip"]').tooltip();

  // Reset modal for new entry
  $('#employeeModal').on('show.bs.modal', function (e) {
    if (!$(e.relatedTarget).hasClass('btn-warning')) {
      resetForm();
    }
  });
});

function resetForm() {
  document.getElementById('employeeForm').reset();
  document.getElementById('empId').value = '';
  document.getElementById('empModalTitle').textContent = 'Add Employee';
  document.getElementById('empSubmitBtn').textContent = 'Save Employee';
}

// Position filter based on department (your existing logic)
$('#fDeptId').change(function() {
  const deptId = $(this).val();
  $('#fPositionId').html('<option value="">-- Select --</option>');
  // Your existing population logic...
});
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>