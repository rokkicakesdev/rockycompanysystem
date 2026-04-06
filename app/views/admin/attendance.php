<?php
$pageTitle = 'Attendance';
require_once __DIR__ . '/../layouts/admin_header.php';

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle form submissions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            Invalid security token. Please refresh the page and try again.
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">×</span>
            </button>
        </div>';
    } else {
        $saved = 0;
        $errors = 0;
        $errorDetails = [];

        // Only process checked employees
        $checkedIds = $_POST['checked_employees'] ?? [];

        foreach ($_POST['attendance'] as $empId => $record) {
            // Skip unchecked employees
            if (!in_array((string)$empId, $checkedIds)) {
                continue;
            }

            $timeIn  = $record['time_in']  ?? null;
            $timeOut = $record['time_out'] ?? null;
            $hoursWorked = null;
            $overtimeHours = min(12, max(0, (float)($record['overtime_hours'] ?? 0)));
            $notes = substr(strip_tags(trim($record['notes'] ?? '')), 0, 255);

            if ($timeIn && $timeOut) {
                $in  = strtotime($timeIn);
                $out = strtotime($timeOut);
                if ($out > $in) {
                    $hoursWorked = round(($out - $in) / 3600, 2);
                } else {
                    $errors++;
                    $errorDetails[] = "Employee ID $empId: Time out is before or equal to time in";
                    continue;
                }
            }

            $ok = Model::saveAttendance([
                'employee_id'    => (int)$empId,
                'date'           => $_POST['att_date'],
                'time_in'        => $timeIn,
                'time_out'       => $timeOut,
                'status'         => $record['status']     ?? 'present',
                'leave_type'     => $record['leave_type'] ?? null,
                'remarks'        => $notes,
                'hours_worked'   => $hoursWorked,
                'overtime_hours' => $overtimeHours,
                'is_overtime'    => $overtimeHours > 0 ? 1 : 0,
                'created_by'     => $_SESSION['user_id'] ?? null,
            ]);

            if ($ok) {
                $saved++;
            } else {
                $errors++;
                $errorDetails[] = "Employee ID $empId: Database save failed";
            }
        }

        Model::log(
            $_SESSION['user_id'] ?? null,
            'SAVE_ATTENDANCE',
            "Saved attendance for $saved employees on " . ($_POST['att_date'] ?? 'unknown') . " | Errors: $errors"
        );

        $msgClass = $errors === 0 ? 'success' : 'warning';
        $msgText = "Attendance saved for {$saved} employee(s).";
        if ($errors > 0) {
            $msgText .= " {$errors} failed.";
            if (!empty($errorDetails)) {
                $msgText .= '<br><small>' . implode('<br>', $errorDetails) . '</small>';
            }
        }

        $msgText = htmlspecialchars($msgText, ENT_QUOTES, 'UTF-8');
        $msgText = str_replace(
            ['&lt;br&gt;', '&lt;small&gt;', '&lt;/small&gt;'],
            ['<br>', '<small>', '</small>'],
            $msgText
        );

        $msg = "<div class=\"alert alert-{$msgClass} alert-dismissible fade show\" role=\"alert\">
            {$msgText}
            <button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">
                <span aria-hidden=\"true\">×</span>
            </button>
        </div>";
    }
}

// Handle individual attendance update (from Update modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_single_attendance'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger alert-dismissible fade show">Invalid security token.<button type="button" class="close" data-dismiss="alert"><span>×</span></button></div>';
    } else {
        $empId = (int)($_POST['upd_emp_id'] ?? 0);
        $updDate = $_POST['upd_date'] ?? '';
        $timeIn  = $_POST['upd_time_in']  ?? null;
        $timeOut = $_POST['upd_time_out'] ?? null;
        $updStatus = $_POST['upd_status'] ?? 'present';
        $updNotes  = substr(strip_tags(trim($_POST['upd_notes'] ?? '')), 0, 255);
        $updOt     = min(12, max(0, (float)($_POST['upd_overtime_hours'] ?? 0)));

        if (empty($updNotes)) {
            $msg = '<div class="alert alert-danger alert-dismissible fade show">Notes are required when updating attendance.<button type="button" class="close" data-dismiss="alert"><span>×</span></button></div>';
        } elseif ($empId && $updDate) {
            $hoursWorked = null;
            if ($timeIn && $timeOut) {
                $in = strtotime($timeIn); $out = strtotime($timeOut);
                if ($out > $in) $hoursWorked = round(($out - $in) / 3600, 2);
            }
            $ok = Model::saveAttendance([
                'employee_id'    => $empId,
                'date'           => $updDate,
                'time_in'        => $timeIn ?: null,
                'time_out'       => $timeOut ?: null,
                'status'         => $updStatus,
                'leave_type'     => $_POST['upd_leave_type'] ?? null,
                'remarks'        => $updNotes,
                'hours_worked'   => $hoursWorked,
                'overtime_hours' => $updOt,
                'is_overtime'    => $updOt > 0 ? 1 : 0,
                'created_by'     => $_SESSION['user_id'] ?? null,
            ]);
            if ($ok) {
                Model::log($_SESSION['user_id'] ?? null, 'UPDATE_ATTENDANCE', "Updated attendance for employee ID:{$empId} on {$updDate} | notes: {$updNotes}");
                $msg = '<div class="alert alert-success alert-dismissible fade show">Attendance updated successfully.<button type="button" class="close" data-dismiss="alert"><span>×</span></button></div>';
            } else {
                $msg = '<div class="alert alert-danger alert-dismissible fade show">Failed to update attendance.<button type="button" class="close" data-dismiss="alert"><span>×</span></button></div>';
            }
        }
    }
}

$selectedDate  = $_GET['date']   ?? date('Y-m-d');
$selectedMonth = $_GET['month']  ?? date('Y-m');
$viewMode      = $_GET['view']   ?? 'daily';
$filterDept    = $_GET['dept']   ?? '';

$allEmployees    = Model::getAllEmployees('active');
$departments     = Model::getAllDepartments();
$existingRecords = [];

// Filter by department
if ($filterDept !== '') {
    $allEmployees = array_filter($allEmployees, fn($e) => (int)$e['department_id'] === (int)$filterDept);
    $allEmployees = array_values($allEmployees);
}

// Filter by date_start: employee should not appear if date_start > selected date
if ($viewMode === 'daily') {
    $employees = array_filter($allEmployees, function($e) use ($selectedDate) {
        // Use date_start if set, otherwise date_hired
        $startDate = !empty($e['date_start']) ? $e['date_start'] : ($e['date_hired'] ?? '');
        return !empty($startDate) && $startDate <= $selectedDate;
    });
    $employees = array_values($employees);
    $recs = Model::getAttendanceByMonth(substr($selectedDate, 0, 7));
    foreach ($recs as $r) {
        if ($r['date'] === $selectedDate) $existingRecords[$r['employee_id']] = $r;
    }
} else {
    $lastDayOfMonth = date('Y-m-t', strtotime($selectedMonth . '-01'));
    $employees = array_filter($allEmployees, function($e) use ($lastDayOfMonth) {
        $startDate = !empty($e['date_start']) ? $e['date_start'] : ($e['date_hired'] ?? '');
        return !empty($startDate) && $startDate <= $lastDayOfMonth;
    });
    $employees = array_values($employees);
    $recs = Model::getAttendanceByMonth($selectedMonth);
    foreach ($recs as $r) $existingRecords[$r['employee_id'] . '_' . $r['date']] = $r;
}

// ── Holiday detection for selected date ──────────────────────────
$holidayInfo = ($viewMode === 'daily') ? Model::isHoliday($selectedDate) : null;

// ── Weekend detection — Saturday (6) or Sunday (0) ───────────────
$isWeekend = ($viewMode === 'daily') && in_array((int)date('w', strtotime($selectedDate)), [0, 6]);

$statusOptions = [
    'present'  => ['label' => 'Present',  'color' => '#22c55e'],
    'absent'   => ['label' => 'Absent',   'color' => '#ef4444'],
    'late'     => ['label' => 'Late',     'color' => '#f59e0b'],
    'half_day' => ['label' => 'Half Day', 'color' => '#a855f7'],
    'on_leave' => ['label' => 'On Leave', 'color' => '#3b82f6'],
    'holiday'  => ['label' => 'Holiday',  'color' => '#14b8a6'],
    'rest_day' => ['label' => 'Rest Day', 'color' => '#94a3b8'],
];
?>

<div class="page-title-bar">
  <i class="fas fa-clock text-primary"></i>
  <h1>Attendance Management</h1>
</div>

<?= $msg ?>

<!-- Controls -->
<div class="card mb-4">
  <div class="card-body py-3">
    <form method="GET" class="form-inline flex-gap-2">
      <div class="mr-3 att-view-toggle">
        <a href="?view=daily&date=<?= $selectedDate ?>&dept=<?= urlencode($filterDept) ?>" class="btn btn-sm <?= $viewMode==='daily' ? 'btn-primary' : 'btn-outline-primary' ?>">Daily View</a>
        <a href="?view=monthly&month=<?= $selectedMonth ?>&dept=<?= urlencode($filterDept) ?>" class="btn btn-sm <?= $viewMode==='monthly' ? 'btn-primary' : 'btn-outline-primary' ?>">Monthly Summary</a>
      </div>

      <?php if ($viewMode === 'daily'): ?>
        <input type="hidden" name="view" value="daily">
        <label class="mr-2 font-weight-600">Date:</label>
        <input type="date" name="date" value="<?= $selectedDate ?>" class="form-control form-control-sm mr-2">
      <?php else: ?>
        <input type="hidden" name="view" value="monthly">
        <label class="mr-2 font-weight-600">Month:</label>
        <input type="month" name="month" value="<?= $selectedMonth ?>" class="form-control form-control-sm mr-2">
      <?php endif; ?>

      <label class="mr-2 font-weight-600">Department:</label>
      <select name="dept" class="form-control form-control-sm mr-2">
        <option value="">All Departments</option>
        <?php foreach ($departments as $dept): ?>
          <option value="<?= $dept['id'] ?>" <?= (string)$filterDept === (string)$dept['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($dept['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter mr-1"></i>Apply</button>
    </form>
  </div>
</div>

<?php if ($viewMode === 'daily'): ?>
<!-- DAILY ENTRY FORM -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-clipboard-list mr-2"></i>
    Daily Attendance — <?= date('l, F j, Y', strtotime($selectedDate)) ?>
    <span class="badge badge-primary ml-2"><?= count($employees) ?> employees</span>
    <?php if ($holidayInfo): ?>
      <span class="badge badge-teal ml-2"><i class="fas fa-calendar-day mr-1"></i><?= htmlspecialchars($holidayInfo['name']) ?></span>
    <?php endif; ?>
  </div>
  <?php if ($holidayInfo): ?>
  <div class="alert att-holiday-banner mb-0 border-0 rounded-0">
    <i class="fas fa-calendar-day mr-2 att-holiday-icon"></i>
    <strong><?= htmlspecialchars($holidayInfo['name']) ?></strong> is a
    <strong><?= $holidayInfo['type'] === 'regular' ? 'Regular Holiday' : ($holidayInfo['type'] === 'special_non_working' ? 'Special Non-Working Holiday' : 'Special Working Holiday') ?></strong>.
    All employees have been pre-set to <strong>Holiday</strong>. You can still override individual records below.
  </div>
  <?php endif; ?>
  <?php if ($isWeekend): ?>
  <div class="alert att-weekend-banner mb-0 border-0 rounded-0">
    <i class="fas fa-moon mr-2 att-weekend-icon"></i>
    <strong><?= date('l', strtotime($selectedDate)) ?></strong> is a weekend.
    All employees have been pre-set to <strong>Rest Day</strong>. You can still override individual records below.
  </div>
  <?php endif; ?>
  <div class="card-body p-0">
    <form method="POST" id="attendanceForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="hidden" name="att_date" value="<?= $selectedDate ?>">
      <input type="hidden" name="save_attendance" value="1">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th style="width:40px;">
                <input type="checkbox" id="checkAll" title="Select All">
              </th>
              <th>Employee</th>
              <th>Department</th>
              <th class="att-col-status">Status</th>
              <th class="att-col-timein">Time In</th>
              <th class="att-col-timein">Time Out</th>
              <th class="att-col-ot">OT Hrs</th>
              <th>Status</th>
              <th>Notes</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $emp):
              $rec    = $existingRecords[$emp['id']] ?? null;
              $hasAttendance = $rec !== null;
              // Priority: existing record → holiday → weekend → present
              if ($rec) {
                  $status = $rec['status'];
              } elseif ($holidayInfo) {
                  $status = 'holiday';
              } elseif ($isWeekend) {
                  $status = 'rest_day';
              } else {
                  $status = 'present';
              }
            ?>
            <tr>
              <td>
                <input type="checkbox" name="checked_employees[]" value="<?= $emp['id'] ?>" class="emp-row-check" checked>
              </td>
              <td>
                <strong class="att-emp-name"><?= htmlspecialchars($emp['name']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($emp['employee_no']) ?></small>
              </td>
              <td><small><?= htmlspecialchars($emp['department']) ?></small></td>
              <td>
                <select name="attendance[<?= $emp['id'] ?>][status]" class="form-control form-control-sm att-status" data-empid="<?= $emp['id'] ?>">
                  <?php foreach ($statusOptions as $val => $opt): ?>
                    <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?> class="status-select-option">
                      <?= $opt['label'] ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <select name="attendance[<?= $emp['id'] ?>][leave_type]" class="form-control form-control-sm mt-1 att-leave-select leave-type-select-<?= $emp['id'] ?>"
                  class="att-on-leave-panel<?= $status === 'on_leave' ? ' visible' : '' ?>">
                  <option value="">-- Leave Type --</option>
                  <?php foreach (LEAVE_TYPES as $lk => $lv): ?>
                    <option value="<?= $lk ?>" <?= ($rec['leave_type'] ?? '') === $lk ? 'selected' : '' ?>><?= $lv ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="time" name="attendance[<?= $emp['id'] ?>][time_in]"  value="<?= $rec['time_in']  ?? '08:00' ?>" class="form-control form-control-sm"></td>
              <td><input type="time" name="attendance[<?= $emp['id'] ?>][time_out]" value="<?= $rec['time_out'] ?? '17:00' ?>" class="form-control form-control-sm"></td>
              <td><input type="number" step="0.5" min="0" max="12" name="attendance[<?= $emp['id'] ?>][overtime_hours]" value="<?= $rec['overtime_hours'] ?? 0 ?>" class="form-control form-control-sm" maxlength="2" oninput="this.value=this.value.slice(0,4)"></td>
              <td>
                <?php if ($hasAttendance): ?>
                  <span class="badge badge-success" title="Attendance already saved"><i class="fas fa-check mr-1"></i>Attended</span>
                <?php else: ?>
                  <span class="badge badge-secondary"><i class="fas fa-minus mr-1"></i>Not yet</span>
                <?php endif; ?>
              </td>
              <td>
                <button type="button" class="btn btn-sm btn-outline-secondary notes-icon-btn"
                  data-toggle="modal" data-target="#notesModal"
                  data-empid="<?= $emp['id'] ?>"
                  data-empname="<?= htmlspecialchars($emp['name']) ?>"
                  data-notes="<?= htmlspecialchars($rec['remarks'] ?? '') ?>"
                  title="View/Edit Notes">
                  <i class="fas fa-sticky-note"></i>
                  <?php if (!empty($rec['remarks'])): ?>
                    <span class="badge badge-info ml-1">1</span>
                  <?php endif; ?>
                </button>
                <input type="hidden" name="attendance[<?= $emp['id'] ?>][notes]" id="notes-field-<?= $emp['id'] ?>" value="<?= htmlspecialchars($rec['remarks'] ?? '') ?>">
              </td>
              <td>
                <button type="button" class="btn btn-sm btn-warning update-att-btn"
                  data-empid="<?= $emp['id'] ?>"
                  data-empname="<?= htmlspecialchars($emp['name']) ?>"
                  data-date="<?= $selectedDate ?>"
                  data-timein="<?= $rec['time_in'] ?? '08:00' ?>"
                  data-timeout="<?= $rec['time_out'] ?? '17:00' ?>"
                  data-status="<?= $status ?>"
                  data-leavetype="<?= $rec['leave_type'] ?? '' ?>"
                  data-ot="<?= $rec['overtime_hours'] ?? 0 ?>"
                  data-notes="<?= htmlspecialchars($rec['remarks'] ?? '') ?>"
                  title="Update Attendance">
                  <i class="fas fa-edit"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <button type="button" class="btn btn-primary" onclick="showAttendanceConfirm(<?= count($employees) ?>, '<?= date('M j, Y', strtotime($selectedDate)) ?>')">
          <i class="fas fa-save mr-1"></i>Save Checked Attendance
        </button>
        <span class="text-muted"><?= count($employees) ?> employees | <?= date('M j, Y', strtotime($selectedDate)) ?></span>
      </div>
    </form>
  </div>
</div>

<?php endif; ?>

<!-- Notes Modal -->
<div class="modal fade" id="notesModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info">
        <h5 class="modal-title text-white"><i class="fas fa-sticky-note mr-2"></i>Notes — <span id="notesModalEmpName"></span></h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Notes</label>
          <textarea class="form-control" id="notesModalText" rows="4" maxlength="255" placeholder="Enter notes for this employee's attendance..."></textarea>
          <small class="text-muted"><span id="notesCharCount">0</span>/255 characters</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancel</button>
        <button type="button" class="btn btn-primary" id="saveNotesBtn"><i class="fas fa-save mr-1"></i>Save Notes</button>
      </div>
    </div>
  </div>
</div>

<!-- Update Attendance Modal -->
<div class="modal fade" id="updateAttModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" id="updateAttForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="update_single_attendance" value="1">
        <input type="hidden" name="upd_emp_id" id="updEmpId">
        <input type="hidden" name="upd_date" value="<?= $selectedDate ?>">
        <div class="modal-header bg-warning">
          <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Update Attendance — <span id="updEmpName"></span></h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Status</label>
                <select name="upd_status" id="updStatus" class="form-control">
                  <?php foreach ($statusOptions as $val => $opt): ?>
                    <option value="<?= $val ?>"><?= $opt['label'] ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group" id="updLeaveTypeGroup">
                <label>Leave Type</label>
                <select name="upd_leave_type" id="updLeaveType" class="form-control">
                  <option value="">-- Leave Type --</option>
                  <?php foreach (LEAVE_TYPES as $lk => $lv): ?>
                    <option value="<?= $lk ?>"><?= $lv ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Time In</label>
                <input type="time" name="upd_time_in" id="updTimeIn" class="form-control">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Time Out</label>
                <input type="time" name="upd_time_out" id="updTimeOut" class="form-control">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>OT Hours</label>
                <input type="number" step="0.5" min="0" max="12" name="upd_overtime_hours" id="updOt" class="form-control" value="0">
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label>Notes <span class="text-danger">*</span> <small class="text-muted">(Required when updating)</small></label>
                <textarea name="upd_notes" id="updNotes" class="form-control" rows="3" maxlength="255" placeholder="Enter reason for update..." required></textarea>
                <small class="text-muted"><span id="updNotesCount">0</span>/255 characters</small>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancel</button>
          <button type="submit" class="btn btn-warning"><i class="fas fa-save mr-1"></i>Update Attendance</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Attendance Confirm Modal (AdminLTE) — rendered always, works for daily view -->
<div class="modal fade" id="attendanceConfirmModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary">
        <h5 class="modal-title text-white"><i class="fas fa-save mr-2"></i>Save Attendance</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-start">
          <i class="fas fa-clipboard-check fa-2x text-primary mr-3 mt-1"></i>
          <div>
            <p class="mb-1 font-weight-600" id="attConfirmMsg">Save attendance for checked employees?</p>
            <p class="text-muted mb-0 att-confirm-sub">Only checked employees will be saved.</p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          <i class="fas fa-times mr-1"></i>Cancel
        </button>
        <button type="button" class="btn btn-primary" id="attConfirmSaveBtn">
          <i class="fas fa-save mr-1"></i>Save Attendance
        </button>
      </div>
    </div>
  </div>
</div>

<?php if ($viewMode !== 'daily'): ?>
<!-- MONTHLY SUMMARY -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-calendar-alt mr-2"></i>
    Monthly Summary — <?= date('F Y', strtotime($selectedMonth . '-01')) ?>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Employee</th>
            <th class="text-center">Present</th>
            <th class="text-center">Absent</th>
            <th class="text-center">Late</th>
            <th class="text-center">On Leave</th>
            <th class="text-center">Half Day</th>
            <th class="text-center">Holiday</th>
            <th class="text-center">OT Hrs</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($employees as $emp):
            $summary = Model::getAttendanceSummary($emp['id'], $selectedMonth);
          ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($emp['name']) ?></strong><br>
              <small class="text-muted"><?= htmlspecialchars($emp['employee_no']) ?> — <?= htmlspecialchars($emp['department']) ?></small>
            </td>
            <td class="text-center"><span class="badge bg-success"><?= $summary['days_present']  ?? 0 ?></span></td>
            <td class="text-center"><span class="badge bg-danger"><?=  $summary['days_absent']   ?? 0 ?></span></td>
            <td class="text-center"><span class="badge bg-warning"><?= $summary['days_late']     ?? 0 ?></span></td>
            <td class="text-center"><span class="badge bg-info"><?=    $summary['days_on_leave'] ?? 0 ?></span></td>
            <td class="text-center"><span class="badge bg-purple"><?=  $summary['days_half']     ?? 0 ?></span></td>
            <td class="text-center"><span class="badge bg-teal"><?=    $summary['days_holiday']  ?? 0 ?></span></td>
            <td class="text-center"><strong><?= number_format((float)($summary['total_overtime'] ?? 0), 1) ?></strong></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
$extraJs = <<<JS
// Check all / uncheck all
document.getElementById('checkAll') && document.getElementById('checkAll').addEventListener('change', function() {
    document.querySelectorAll('.emp-row-check').forEach(function(cb) { cb.checked = this.checked; }.bind(this));
});

// Show/hide leave type dropdown based on status selection
document.querySelectorAll('.att-status').forEach(function(sel) {
    sel.addEventListener('change', function() {
        var empId    = this.dataset.empid;
        var leaveSel = document.querySelector('.leave-type-select-' + empId);
        if (leaveSel) {
            leaveSel.classList.toggle('att-leave-visible', this.value === 'on_leave');
            leaveSel.classList.toggle('att-leave-hidden',  this.value !== 'on_leave');
        }
    });
    sel.dispatchEvent(new Event('change'));
});

// Notes modal logic
var currentNotesEmpId = null;
document.querySelectorAll('.notes-icon-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        currentNotesEmpId = this.dataset.empid;
        document.getElementById('notesModalEmpName').textContent = this.dataset.empname;
        var txt = document.getElementById('notesModalText');
        txt.value = this.dataset.notes || '';
        document.getElementById('notesCharCount').textContent = txt.value.length;
    });
});
document.getElementById('notesModalText') && document.getElementById('notesModalText').addEventListener('input', function() {
    document.getElementById('notesCharCount').textContent = this.value.length;
});
document.getElementById('saveNotesBtn') && document.getElementById('saveNotesBtn').addEventListener('click', function() {
    if (currentNotesEmpId) {
        var txt = document.getElementById('notesModalText').value;
        var field = document.getElementById('notes-field-' + currentNotesEmpId);
        var btn   = document.querySelector('[data-empid="' + currentNotesEmpId + '"].notes-icon-btn');
        if (field) field.value = txt;
        if (btn) {
            btn.dataset.notes = txt;
            var badge = btn.querySelector('.badge');
            if (txt) {
                if (!badge) { badge = document.createElement('span'); badge.className = 'badge badge-info ml-1'; btn.appendChild(badge); }
                badge.textContent = '1';
            } else {
                if (badge) badge.remove();
            }
        }
    }
    $('#notesModal').modal('hide');
});

// Update attendance modal
document.querySelectorAll('.update-att-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('updEmpId').value        = this.dataset.empid;
        document.getElementById('updEmpName').textContent = this.dataset.empname;
        document.getElementById('updStatus').value       = this.dataset.status || 'present';
        document.getElementById('updLeaveType').value    = this.dataset.leavetype || '';
        document.getElementById('updTimeIn').value       = this.dataset.timein || '08:00';
        document.getElementById('updTimeOut').value      = this.dataset.timeout || '17:00';
        document.getElementById('updOt').value           = this.dataset.ot || 0;
        document.getElementById('updNotes').value        = '';
        document.getElementById('updNotesCount').textContent = '0';
        $('#updateAttModal').modal('show');
    });
});
document.getElementById('updNotes') && document.getElementById('updNotes').addEventListener('input', function() {
    document.getElementById('updNotesCount').textContent = this.value.length;
});
document.getElementById('updStatus') && document.getElementById('updStatus').addEventListener('change', function() {
    document.getElementById('updLeaveTypeGroup').style.display = this.value === 'on_leave' ? '' : 'none';
});

// Attendance confirm modal
window.showAttendanceConfirm = function(empCount, dateLabel) {
    var checked = document.querySelectorAll('.emp-row-check:checked').length;
    var msg = document.getElementById('attConfirmMsg');
    if (msg) msg.textContent = 'Save attendance for ' + checked + ' checked employee(s) on ' + dateLabel + '?';
    $('#attendanceConfirmModal').modal('show');
};
document.getElementById('attConfirmSaveBtn') && document.getElementById('attConfirmSaveBtn').addEventListener('click', function() {
    $('#attendanceConfirmModal').modal('hide');
    document.getElementById('attendanceForm').submit();
});

// Auto-set all statuses to Rest Day when date picker changes to Saturday/Sunday.
var datePicker = document.querySelector('input[name="date"]');
if (datePicker) {
    datePicker.addEventListener('change', function() {
        var d   = new Date(this.value + 'T00:00:00');
        var dow = d.getDay();
        if (dow === 0 || dow === 6) {
            document.querySelectorAll('.att-status').forEach(function(sel) {
                sel.value = 'rest_day';
                sel.dispatchEvent(new Event('change'));
            });
        } else {
            document.querySelectorAll('.att-status').forEach(function(sel) {
                if (sel.value === 'rest_day') {
                    sel.value = 'present';
                    sel.dispatchEvent(new Event('change'));
                }
            });
        }
    });
}
JS;
?>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>