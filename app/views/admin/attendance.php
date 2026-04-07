<?php
$pageTitle = 'Attendance';
require_once __DIR__ . '/../layouts/admin_header.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$msg = '';

// Helper: decode attendance notes JSON array from remarks column
function decodeAttNotes(string $remarks): array {
    if (empty($remarks)) return [];
    $decoded = json_decode($remarks, true);
    if (is_array($decoded)) return $decoded;
    return [['id' => 'legacy', 'note' => $remarks, 'by' => 'System', 'at' => '']];
}
function encodeAttNotes(array $notes): string {
    return json_encode(array_values($notes), JSON_UNESCAPED_UNICODE);
}

// ── SAVE BULK ATTENDANCE ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger alert-dismissible fade show">Invalid security token.<button type="button" class="close" data-dismiss="alert"><span>×</span></button></div>';
    } else {
        $saved = 0; $errors = 0; $errorDetails = [];
        $checkedIds = $_POST['checked_employees'] ?? [];
        foreach ($_POST['attendance'] as $empId => $record) {
            if (!in_array((string)$empId, $checkedIds)) continue;
            $timeIn  = $record['time_in']  ?? null;
            $timeOut = $record['time_out'] ?? null;
            $hoursWorked   = null;
            $overtimeHours = min(12, max(0, (float)($record['overtime_hours'] ?? 0)));
            $notes = substr(strip_tags(trim($record['notes'] ?? '')), 0, 255);
            if ($timeIn && $timeOut) {
                $in  = strtotime($timeIn); $out = strtotime($timeOut);
                if ($out > $in) { $hoursWorked = round(($out - $in) / 3600, 2); }
                else { $errors++; $errorDetails[] = "Employee ID $empId: Time out before time in"; continue; }
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
            $ok ? $saved++ : ($errors++ && $errorDetails[] = "Employee ID $empId: DB save failed");
        }
        Model::log($_SESSION['user_id'] ?? null, 'SAVE_ATTENDANCE', "Saved {$saved} employees on " . ($_POST['att_date'] ?? 'unknown') . " | Errors: {$errors}");
        $msgClass = $errors === 0 ? 'success' : 'warning';
        $msgText  = "Attendance saved for {$saved} employee(s)." . ($errors > 0 ? " {$errors} failed." : '');
        $msg = "<div class=\"alert alert-{$msgClass} alert-dismissible fade show\">{$msgText}<button type=\"button\" class=\"close\" data-dismiss=\"alert\"><span>×</span></button></div>";
    }
}

// ── UPDATE SINGLE ATTENDANCE ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_single_attendance'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger alert-dismissible fade show">Invalid security token.<button type="button" class="close" data-dismiss="alert"><span>×</span></button></div>';
    } else {
        $empId     = (int)($_POST['upd_emp_id'] ?? 0);
        $updDate   = $_POST['upd_date']   ?? '';
        $timeIn    = $_POST['upd_time_in']  ?? null;
        $timeOut   = $_POST['upd_time_out'] ?? null;
        $updStatus = $_POST['upd_status']   ?? 'present';
        $updNotes  = substr(strip_tags(trim($_POST['upd_notes'] ?? '')), 0, 500);
        $updOt     = min(12, max(0, (float)($_POST['upd_overtime_hours'] ?? 0)));

        if (empty($updNotes)) {
            $msg = '<div class="alert alert-danger alert-dismissible fade show">Notes are required when updating attendance.<button type="button" class="close" data-dismiss="alert"><span>×</span></button></div>';
        } elseif ($empId && $updDate) {
            $hoursWorked = null;
            if ($timeIn && $timeOut) {
                $in = strtotime($timeIn); $out = strtotime($timeOut);
                if ($out > $in) $hoursWorked = round(($out - $in) / 3600, 2);
            }
            // Load existing record to append note
            $existing    = Model::getAttendanceByEmployee($empId, substr($updDate, 0, 7));
            $existingRec = null;
            foreach ($existing as $er) {
                if ($er['date'] === $updDate) { $existingRec = $er; break; }
            }
            $notesList = decodeAttNotes($existingRec['remarks'] ?? '');
            $byName    = $_SESSION['name'] ?? ('User #' . ($_SESSION['user_id'] ?? 0));
            $notesList[] = ['id' => uniqid(), 'note' => $updNotes, 'by' => $byName, 'at' => date('Y-m-d H:i')];
            $ok = Model::saveAttendance([
                'employee_id'    => $empId,
                'date'           => $updDate,
                'time_in'        => $timeIn ?: null,
                'time_out'       => $timeOut ?: null,
                'status'         => $updStatus,
                'leave_type'     => $_POST['upd_leave_type'] ?? null,
                'remarks'        => encodeAttNotes($notesList),
                'hours_worked'   => $hoursWorked,
                'overtime_hours' => $updOt,
                'is_overtime'    => $updOt > 0 ? 1 : 0,
                'created_by'     => $_SESSION['user_id'] ?? null,
            ]);
            if ($ok) {
                Model::log($_SESSION['user_id'] ?? null, 'UPDATE_ATTENDANCE', "Updated ID:{$empId} on {$updDate} | note: {$updNotes}");
                $msg = '<div class="alert alert-success alert-dismissible fade show">Attendance updated successfully.<button type="button" class="close" data-dismiss="alert"><span>×</span></button></div>';
            } else {
                $msg = '<div class="alert alert-danger alert-dismissible fade show">Failed to update attendance.<button type="button" class="close" data-dismiss="alert"><span>×</span></button></div>';
            }
        }
    }
}

// ── EDIT ATTENDANCE NOTE ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_att_note'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger alert-dismissible fade show">Invalid token.<button type="button" class="close" data-dismiss="alert"><span>×</span></button></div>';
    } else {
        $empId    = (int)($_POST['ann_emp_id']   ?? 0);
        $annDate  = $_POST['ann_date']   ?? '';
        $noteId   = $_POST['ann_note_id'] ?? '';
        $noteText = substr(strip_tags(trim($_POST['ann_note_text'] ?? '')), 0, 500);
        if ($empId && $annDate && $noteId && $noteText) {
            $existing = Model::getAttendanceByEmployee($empId, substr($annDate, 0, 7));
            $existingRec = null;
            foreach ($existing as $er) { if ($er['date'] === $annDate) { $existingRec = $er; break; } }
            $notesList = decodeAttNotes($existingRec['remarks'] ?? '');
            foreach ($notesList as &$n) { if (($n['id'] ?? '') === $noteId) { $n['note'] = $noteText; break; } }
            unset($n);
            $ok = Model::saveAttendance([
                'employee_id'    => $empId,
                'date'           => $annDate,
                'time_in'        => $existingRec['time_in']        ?? null,
                'time_out'       => $existingRec['time_out']       ?? null,
                'status'         => $existingRec['status']         ?? 'present',
                'leave_type'     => $existingRec['leave_type']     ?? null,
                'remarks'        => encodeAttNotes($notesList),
                'hours_worked'   => $existingRec['hours_worked']   ?? null,
                'overtime_hours' => $existingRec['overtime_hours'] ?? 0,
                'is_overtime'    => $existingRec['is_overtime']    ?? 0,
                'created_by'     => $_SESSION['user_id'] ?? null,
            ]);
            $msg = $ok
                ? '<div class="alert alert-success alert-dismissible fade show">Note updated.<button type="button" class="close" data-dismiss="alert"><span>×</span></button></div>'
                : '<div class="alert alert-danger alert-dismissible fade show">Failed to update note.<button type="button" class="close" data-dismiss="alert"><span>×</span></button></div>';
        }
    }
}

// ── DELETE ATTENDANCE NOTE ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_att_note'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger alert-dismissible fade show">Invalid token.<button type="button" class="close" data-dismiss="alert"><span>×</span></button></div>';
    } else {
        $empId   = (int)($_POST['dan_emp_id'] ?? 0);
        $danDate = $_POST['dan_date']    ?? '';
        $noteId  = $_POST['dan_note_id'] ?? '';
        if ($empId && $danDate && $noteId) {
            $existing = Model::getAttendanceByEmployee($empId, substr($danDate, 0, 7));
            $existingRec = null;
            foreach ($existing as $er) { if ($er['date'] === $danDate) { $existingRec = $er; break; } }
            if ($existingRec) {
                $notesList = decodeAttNotes($existingRec['remarks'] ?? '');
                $notesList = array_values(array_filter($notesList, fn($n) => ($n['id'] ?? '') !== $noteId));
                $ok = Model::saveAttendance([
                    'employee_id'    => $empId,
                    'date'           => $danDate,
                    'time_in'        => $existingRec['time_in']        ?? null,
                    'time_out'       => $existingRec['time_out']       ?? null,
                    'status'         => $existingRec['status']         ?? 'present',
                    'leave_type'     => $existingRec['leave_type']     ?? null,
                    'remarks'        => encodeAttNotes($notesList),
                    'hours_worked'   => $existingRec['hours_worked']   ?? null,
                    'overtime_hours' => $existingRec['overtime_hours'] ?? 0,
                    'is_overtime'    => $existingRec['is_overtime']    ?? 0,
                    'created_by'     => $_SESSION['user_id'] ?? null,
                ]);
                $msg = $ok
                    ? '<div class="alert alert-success alert-dismissible fade show">Note deleted.<button type="button" class="close" data-dismiss="alert"><span>×</span></button></div>'
                    : '<div class="alert alert-danger alert-dismissible fade show">Failed to delete note.<button type="button" class="close" data-dismiss="alert"><span>×</span></button></div>';
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

if ($filterDept !== '') {
    $allEmployees = array_values(array_filter($allEmployees, fn($e) => (int)$e['department_id'] === (int)$filterDept));
}

if ($viewMode === 'daily') {
    $employees = array_values(array_filter($allEmployees, function($e) use ($selectedDate) {
        $startDate = !empty($e['date_start']) ? $e['date_start'] : ($e['date_hired'] ?? '');
        return !empty($startDate) && $startDate <= $selectedDate;
    }));
    $recs = Model::getAttendanceByMonth(substr($selectedDate, 0, 7));
    foreach ($recs as $r) {
        if ($r['date'] === $selectedDate) $existingRecords[$r['employee_id']] = $r;
    }
} else {
    $lastDayOfMonth = date('Y-m-t', strtotime($selectedMonth . '-01'));
    $employees = array_values(array_filter($allEmployees, function($e) use ($lastDayOfMonth) {
        $startDate = !empty($e['date_start']) ? $e['date_start'] : ($e['date_hired'] ?? '');
        return !empty($startDate) && $startDate <= $lastDayOfMonth;
    }));
    $recs = Model::getAttendanceByMonth($selectedMonth);
    foreach ($recs as $r) $existingRecords[$r['employee_id'] . '_' . $r['date']] = $r;
}

$holidayInfo = ($viewMode === 'daily') ? Model::isHoliday($selectedDate) : null;
$isWeekend   = ($viewMode === 'daily') && in_array((int)date('w', strtotime($selectedDate)), [0, 6]);

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
    All employees pre-set to <strong>Holiday</strong>. You can override below.
  </div>
  <?php endif; ?>
  <?php if ($isWeekend): ?>
  <div class="alert att-weekend-banner mb-0 border-0 rounded-0">
    <i class="fas fa-moon mr-2 att-weekend-icon"></i>
    <strong><?= date('l', strtotime($selectedDate)) ?></strong> is a weekend. All employees pre-set to <strong>Rest Day</strong>.
  </div>
  <?php endif; ?>
  <div class="card-body p-0">
    <form method="POST" id="attendanceForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="hidden" name="att_date"   value="<?= $selectedDate ?>">
      <input type="hidden" name="save_attendance" value="1">
      <div class="table-responsive">
        <table class="table table-hover mb-0 att-daily-table">
          <thead>
            <tr>
              <th class="att-col-check"><input type="checkbox" id="checkAll" title="Select All"></th>
              <th class="att-col-employee">Employee</th>
              <th class="att-col-dept">Department</th>
              <th class="att-col-status">Status / Leave</th>
              <th class="att-col-timein">Time In</th>
              <th class="att-col-timein">Time Out</th>
              <th class="att-col-ot">OT Hrs</th>
              <th class="att-col-saved">Saved</th>
              <th class="att-col-notes">Notes</th>
              <th class="att-col-action">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $emp):
              $rec    = $existingRecords[$emp['id']] ?? null;
              $hasAtt = $rec !== null;
              if ($rec)            { $status = $rec['status']; }
              elseif ($holidayInfo){ $status = 'holiday'; }
              elseif ($isWeekend)  { $status = 'rest_day'; }
              else                 { $status = 'present'; }
              // Decode notes for display
              $rawRemarks = $rec['remarks'] ?? '';
              $notesList  = [];
              if (!empty($rawRemarks)) {
                  $decoded   = json_decode($rawRemarks, true);
                  $notesList = is_array($decoded)
                      ? $decoded
                      : [['id'=>'legacy','note'=>$rawRemarks,'by'=>'System','at'=>'']];
              }
              $notesCount = count($notesList);
            ?>
            <tr>
              <td class="att-col-check">
                <input type="checkbox" name="checked_employees[]" value="<?= $emp['id'] ?>" class="emp-row-check" checked>
              </td>
              <td class="att-col-employee">
                <strong class="att-emp-name"><?= htmlspecialchars($emp['name']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($emp['employee_no']) ?></small>
              </td>
              <td class="att-col-dept"><small><?= htmlspecialchars($emp['department']) ?></small></td>
              <td class="att-col-status">
                <select name="attendance[<?= $emp['id'] ?>][status]" class="form-control form-control-sm att-status" data-empid="<?= $emp['id'] ?>">
                  <?php foreach ($statusOptions as $val => $opt): ?>
                    <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                  <?php endforeach; ?>
                </select>
                <select name="attendance[<?= $emp['id'] ?>][leave_type]" class="form-control form-control-sm mt-1 att-leave-select leave-type-select-<?= $emp['id'] ?>">
                  <option value="">-- Leave Type --</option>
                  <?php foreach (LEAVE_TYPES as $lk => $lv): ?>
                    <option value="<?= $lk ?>" <?= ($rec['leave_type'] ?? '') === $lk ? 'selected' : '' ?>><?= $lv ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="att-col-timein">
                <input type="time" name="attendance[<?= $emp['id'] ?>][time_in]"
                       value="<?= $rec['time_in']  ?? '08:00' ?>"
                       class="form-control form-control-sm">
              </td>
              <td class="att-col-timein">
                <input type="time" name="attendance[<?= $emp['id'] ?>][time_out]"
                       value="<?= $rec['time_out'] ?? '17:00' ?>"
                       class="form-control form-control-sm">
              </td>
              <td class="att-col-ot">
                <input type="number" step="0.5" min="0" max="12"
                       name="attendance[<?= $emp['id'] ?>][overtime_hours]"
                       value="<?= $rec['overtime_hours'] ?? 0 ?>"
                       class="form-control form-control-sm"
                       oninput="this.value=this.value.slice(0,4)">
              </td>
              <td class="att-col-saved text-center">
                <?php if ($hasAtt): ?>
                  <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Saved</span>
                <?php else: ?>
                  <span class="badge badge-light text-muted"><i class="fas fa-minus mr-1"></i>New</span>
                <?php endif; ?>
              </td>
              <td class="att-col-notes">
                <button type="button" class="btn btn-sm btn-outline-info notes-icon-btn"
                  data-empid="<?= $emp['id'] ?>"
                  data-empname="<?= htmlspecialchars($emp['name'], ENT_QUOTES) ?>"
                  data-date="<?= $selectedDate ?>"
                  data-notes="<?= htmlspecialchars(json_encode($notesList, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>"
                  title="View Notes">
                  <i class="fas fa-sticky-note"></i>
                  <?php if ($notesCount > 0): ?>
                    <span class="badge badge-info ml-1"><?= $notesCount ?></span>
                  <?php endif; ?>
                </button>
                <input type="hidden" name="attendance[<?= $emp['id'] ?>][notes]"
                       id="notes-field-<?= $emp['id'] ?>"
                       value="<?= htmlspecialchars($rawRemarks, ENT_QUOTES) ?>">
              </td>
              <td class="att-col-action">
                <button type="button" class="btn btn-sm btn-warning update-att-btn"
                  data-empid="<?= $emp['id'] ?>"
                  data-empname="<?= htmlspecialchars($emp['name'], ENT_QUOTES) ?>"
                  data-date="<?= $selectedDate ?>"
                  data-timein="<?= htmlspecialchars(substr($rec['time_in']  ?? '08:00', 0, 5), ENT_QUOTES) ?>"
                  data-timeout="<?= htmlspecialchars(substr($rec['time_out'] ?? '17:00', 0, 5), ENT_QUOTES) ?>"
                  data-status="<?= htmlspecialchars($status, ENT_QUOTES) ?>"
                  data-leavetype="<?= htmlspecialchars($rec['leave_type'] ?? '', ENT_QUOTES) ?>"
                  data-ot="<?= (float)($rec['overtime_hours'] ?? 0) ?>"
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
        <button type="button" class="btn btn-primary"
                onclick="showAttendanceConfirm(<?= count($employees) ?>, '<?= date('M j, Y', strtotime($selectedDate)) ?>')">
          <i class="fas fa-save mr-1"></i>Save Checked Attendance
        </button>
        <span class="text-muted"><?= count($employees) ?> employee(s) | <?= date('M j, Y', strtotime($selectedDate)) ?></span>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ── Notes List Modal ─────────────────────────────────────────────────────── -->
<div class="modal fade" id="notesModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info">
        <h5 class="modal-title text-white">
          <i class="fas fa-sticky-note mr-2"></i>Notes — <span id="notesModalEmpName"></span>
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="text-muted text-center py-3" id="notesModalEmpty">
          <i class="fas fa-sticky-note fa-2x mb-2 d-block"></i>
          No notes yet. Notes are added when attendance is updated via the <strong>Update</strong> button.
        </p>
        <div id="notesModalList"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          <i class="fas fa-times mr-1"></i>Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── Edit Attendance Note Modal ──────────────────────────────────────────── -->
<div class="modal fade" id="editAttNoteModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" id="editAttNoteForm">
        <input type="hidden" name="csrf_token"    value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="edit_att_note" value="1">
        <input type="hidden" name="ann_emp_id"    id="annEmpId">
        <input type="hidden" name="ann_date"      id="annDate">
        <input type="hidden" name="ann_note_id"   id="annNoteId">
        <div class="modal-header bg-warning">
          <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Note</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="form-group mb-0">
            <label class="font-weight-bold">Note Text <span class="text-danger">*</span></label>
            <textarea name="ann_note_text" id="annNoteText" class="form-control"
                      rows="4" required maxlength="500"
                      placeholder="Update the note text..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i>Cancel
          </button>
          <button type="submit" class="btn btn-warning">
            <i class="fas fa-save mr-1"></i>Save Note
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Hidden Delete Note Form ─────────────────────────────────────────────── -->
<form method="POST" id="deleteAttNoteForm" style="display:none;">
  <input type="hidden" name="csrf_token"       value="<?= htmlspecialchars($csrf_token) ?>">
  <input type="hidden" name="delete_att_note"  value="1">
  <input type="hidden" name="dan_emp_id"       id="danEmpId">
  <input type="hidden" name="dan_date"         id="danDate">
  <input type="hidden" name="dan_note_id"      id="danNoteId">
</form>

<!-- ── Update Attendance Modal ─────────────────────────────────────────────── -->
<div class="modal fade" id="updateAttModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" id="updateAttForm">
        <input type="hidden" name="csrf_token"               value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="update_single_attendance" value="1">
        <input type="hidden" name="upd_emp_id"               id="updEmpId">
        <input type="hidden" name="upd_date"                 value="<?= $selectedDate ?>">
        <div class="modal-header bg-warning">
          <h5 class="modal-title">
            <i class="fas fa-edit mr-2"></i>Update Attendance — <span id="updEmpName"></span>
          </h5>
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
            <div class="col-md-6" id="updLeaveTypeGroup">
              <div class="form-group">
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
                <input type="number" step="0.5" min="0" max="12"
                       name="upd_overtime_hours" id="updOt"
                       class="form-control" value="0">
              </div>
            </div>
            <div class="col-12">
              <div class="form-group mb-0">
                <label>Notes <span class="text-danger">*</span>
                  <small class="text-muted font-weight-normal">(Required — reason for update)</small>
                </label>
                <textarea name="upd_notes" id="updNotes" class="form-control"
                          rows="3" maxlength="500" required
                          placeholder="Enter reason for this update..."></textarea>
                <small class="text-muted"><span id="updNotesCount">0</span>/500</small>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i>Cancel
          </button>
          <button type="submit" class="btn btn-warning">
            <i class="fas fa-save mr-1"></i>Update Attendance
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Save Confirm Modal ───────────────────────────────────────────────────── -->
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
            <p class="mb-1 font-weight-600" id="attConfirmMsg">Save attendance?</p>
            <p class="text-muted mb-0"><small>Only checked employees will be saved.</small></p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          <i class="fas fa-times mr-1"></i>Cancel
        </button>
        <button type="button" class="btn btn-primary" id="attConfirmSaveBtn">
          <i class="fas fa-save mr-1"></i>Confirm Save
        </button>
      </div>
    </div>
  </div>
</div>

<?php if ($viewMode !== 'daily'): ?>
<!-- MONTHLY SUMMARY -->
<div class="card">
  <div class="card-header">
    <i class="fas fa-calendar-alt mr-2"></i>Monthly Summary — <?= date('F Y', strtotime($selectedMonth . '-01')) ?>
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
$extraJs = <<<'JS'
// ── Check all / uncheck all ─────────────────────────────────────────────────
document.getElementById('checkAll') && document.getElementById('checkAll').addEventListener('change', function() {
    document.querySelectorAll('.emp-row-check').forEach(function(cb) { cb.checked = this.checked; }.bind(this));
});

// ── Show/hide leave type dropdown ───────────────────────────────────────────
document.querySelectorAll('.att-status').forEach(function(sel) {
    sel.addEventListener('change', function() {
        var leaveSel = document.querySelector('.leave-type-select-' + this.dataset.empid);
        if (leaveSel) {
            leaveSel.classList.toggle('att-leave-visible', this.value === 'on_leave');
            leaveSel.classList.toggle('att-leave-hidden',  this.value !== 'on_leave');
        }
    });
    sel.dispatchEvent(new Event('change'));
});

// ── Notes modal (list view) ─────────────────────────────────────────────────
function attEsc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.querySelectorAll('.notes-icon-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var empId   = this.dataset.empid;
        var empName = this.dataset.empname;
        var date    = this.dataset.date;
        var notes   = [];
        try { notes = JSON.parse(this.dataset.notes || '[]'); } catch(e) {}

        document.getElementById('notesModalEmpName').textContent = empName;
        var emptyEl = document.getElementById('notesModalEmpty');
        var listEl  = document.getElementById('notesModalList');

        if (!notes || notes.length === 0) {
            emptyEl.style.display = '';
            listEl.innerHTML = '';
        } else {
            emptyEl.style.display = 'none';
            var html = '<div class="list-group">';
            notes.forEach(function(n) {
                html += '<div class="list-group-item px-2 py-2">'
                      + '<div class="d-flex justify-content-between align-items-start">'
                      + '<div style="flex:1;min-width:0;">'
                      + '<p class="mb-1">' + attEsc(n.note) + '</p>'
                      + '<small class="text-muted">'
                      + '<i class="fas fa-user mr-1"></i>' + attEsc(n.by || 'System')
                      + (n.at ? ' &mdash; <i class="fas fa-clock ml-1 mr-1"></i>' + attEsc(n.at) : '')
                      + '</small>'
                      + '</div>'
                      + '<div class="ml-2 flex-shrink-0">'
                      + '<button type="button" class="btn btn-xs btn-warning mr-1 att-edit-note-btn"'
                      +   ' data-emp-id="' + attEsc(empId) + '"'
                      +   ' data-date="' + attEsc(date) + '"'
                      +   ' data-note-id="' + attEsc(n.id) + '"'
                      +   ' data-note-text="' + attEsc(n.note) + '">'
                      +   '<i class="fas fa-edit"></i></button>'
                      + '<button type="button" class="btn btn-xs btn-danger att-del-note-btn"'
                      +   ' data-emp-id="' + attEsc(empId) + '"'
                      +   ' data-date="' + attEsc(date) + '"'
                      +   ' data-note-id="' + attEsc(n.id) + '">'
                      +   '<i class="fas fa-trash"></i></button>'
                      + '</div>'
                      + '</div>'
                      + '</div>';
            });
            html += '</div>';
            listEl.innerHTML = html;

            listEl.querySelectorAll('.att-edit-note-btn').forEach(function(b) {
                b.addEventListener('click', function() {
                    document.getElementById('annEmpId').value    = this.dataset.empId;
                    document.getElementById('annDate').value     = this.dataset.date;
                    document.getElementById('annNoteId').value   = this.dataset.noteId;
                    document.getElementById('annNoteText').value = this.dataset.noteText;
                    $('#notesModal').modal('hide');
                    setTimeout(function(){ $('#editAttNoteModal').modal('show'); }, 350);
                });
            });
            listEl.querySelectorAll('.att-del-note-btn').forEach(function(b) {
                b.addEventListener('click', function() {
                    if (!confirm('Delete this note? This cannot be undone.')) return;
                    document.getElementById('danEmpId').value  = this.dataset.empId;
                    document.getElementById('danDate').value   = this.dataset.date;
                    document.getElementById('danNoteId').value = this.dataset.noteId;
                    document.getElementById('deleteAttNoteForm').submit();
                });
            });
        }
        $('#notesModal').modal('show');
    });
});

// ── Update Attendance modal ─────────────────────────────────────────────────
document.querySelectorAll('.update-att-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('updEmpId').value         = this.dataset.empid;
        document.getElementById('updEmpName').textContent = this.dataset.empname;
        document.getElementById('updStatus').value        = this.dataset.status   || 'present';
        document.getElementById('updLeaveType').value     = this.dataset.leavetype || '';
        document.getElementById('updTimeIn').value        = this.dataset.timein   || '08:00';
        document.getElementById('updTimeOut').value       = this.dataset.timeout  || '17:00';
        document.getElementById('updOt').value            = this.dataset.ot       || 0;
        document.getElementById('updNotes').value         = '';
        document.getElementById('updNotesCount').textContent = '0';
        var lvGrp = document.getElementById('updLeaveTypeGroup');
        if (lvGrp) lvGrp.style.display = this.dataset.status === 'on_leave' ? '' : 'none';
        $('#updateAttModal').modal('show');
    });
});
document.getElementById('updNotes') && document.getElementById('updNotes').addEventListener('input', function() {
    document.getElementById('updNotesCount').textContent = this.value.length;
});
document.getElementById('updStatus') && document.getElementById('updStatus').addEventListener('change', function() {
    var lvGrp = document.getElementById('updLeaveTypeGroup');
    if (lvGrp) lvGrp.style.display = this.value === 'on_leave' ? '' : 'none';
});

// ── Save confirm modal ──────────────────────────────────────────────────────
window.showAttendanceConfirm = function(empCount, dateLabel) {
    var checked = document.querySelectorAll('.emp-row-check:checked').length;
    var msgEl   = document.getElementById('attConfirmMsg');
    if (msgEl) msgEl.textContent = 'Save attendance for ' + checked + ' checked employee(s) on ' + dateLabel + '?';
    $('#attendanceConfirmModal').modal('show');
};
document.getElementById('attConfirmSaveBtn') && document.getElementById('attConfirmSaveBtn').addEventListener('click', function() {
    $('#attendanceConfirmModal').modal('hide');
    document.getElementById('attendanceForm').submit();
});

// ── Weekend auto-set ────────────────────────────────────────────────────────
var datePicker = document.querySelector('input[name="date"]');
if (datePicker) {
    datePicker.addEventListener('change', function() {
        var dow = new Date(this.value + 'T00:00:00').getDay();
        if (dow === 0 || dow === 6) {
            document.querySelectorAll('.att-status').forEach(function(s) { s.value = 'rest_day'; s.dispatchEvent(new Event('change')); });
        } else {
            document.querySelectorAll('.att-status').forEach(function(s) {
                if (s.value === 'rest_day') { s.value = 'present'; s.dispatchEvent(new Event('change')); }
            });
        }
    });
}
JS;
?>
<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>