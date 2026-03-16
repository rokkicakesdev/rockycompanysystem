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

        foreach ($_POST['attendance'] as $empId => $record) {
            $timeIn  = $record['time_in']  ?? null;
            $timeOut = $record['time_out'] ?? null;
            $hoursWorked = null;

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
                'status'         => $record['status']        ?? 'present',
                'leave_type'     => $record['leave_type']    ?? null,
                'remarks'        => $record['remarks']       ?? null,
                'hours_worked'   => $hoursWorked,
                'overtime_hours' => (float)($record['overtime_hours'] ?? 0),
                'is_overtime'    => !empty($record['overtime_hours']) ? 1 : 0,
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

        // Escape for safety, then restore allowed tags
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

$selectedDate  = $_GET['date']   ?? date('Y-m-d');
$selectedMonth = $_GET['month']  ?? date('Y-m');
$viewMode      = $_GET['view']   ?? 'daily';

$employees       = Model::getAllEmployees('active');
$existingRecords = [];

if ($viewMode === 'daily') {
    $recs = Model::getAttendanceByMonth(substr($selectedDate, 0, 7));
    foreach ($recs as $r) {
        if ($r['date'] === $selectedDate) $existingRecords[$r['employee_id']] = $r;
    }
} else {
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
        <a href="?view=daily&date=<?= $selectedDate ?>" class="btn btn-sm <?= $viewMode==='daily' ? 'btn-primary' : 'btn-outline-primary' ?>">Daily View</a>
        <a href="?view=monthly&month=<?= $selectedMonth ?>" class="btn btn-sm <?= $viewMode==='monthly' ? 'btn-primary' : 'btn-outline-primary' ?>">Monthly Summary</a>
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
    <form method="POST" onsubmit="return confirm('Save attendance changes for all <?= count($employees) ?> employees on <?= date('M j, Y', strtotime($selectedDate)) ?>?');">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="hidden" name="att_date" value="<?= $selectedDate ?>">
      <input type="hidden" name="save_attendance" value="1">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Department</th>
              <th class="att-col-status">Status</th>
              <th class="att-col-timein">Time In</th>
              <th class="att-col-timein">Time Out</th>
              <th class="att-col-ot">OT Hrs</th>
              <th class="att-col-remarks">Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $emp):
              $rec    = $existingRecords[$emp['id']] ?? null;
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
                <strong class="att-emp-name"><?= htmlspecialchars($emp['name']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($emp['employee_no']) ?></small>
              </td>
              <td><small><?= htmlspecialchars($emp['department']) ?></small></td>
              <td>
                <select name="attendance[<?= $emp['id'] ?>][status]" class="form-control form-control-sm att-status" data-empid="<?= $emp['id'] ?>">
                  <?php foreach ($statusOptions as $val => $opt): ?>
                    <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?> style="color:<?= $opt['color'] ?>;">
                      <?= $opt['label'] ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <select name="attendance[<?= $emp['id'] ?>][leave_type]" class="form-control form-control-sm mt-1 att-leave-select leave-type-select-<?= $emp['id'] ?>"
                  style="display:<?= $status === 'on_leave' ? 'block' : 'none' ?>;">
                  <option value="">-- Leave Type --</option>
                  <?php foreach (LEAVE_TYPES as $lk => $lv): ?>
                    <option value="<?= $lk ?>" <?= ($rec['leave_type'] ?? '') === $lk ? 'selected' : '' ?>><?= $lv ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="time" name="attendance[<?= $emp['id'] ?>][time_in]"  value="<?= $rec['time_in']  ?? '08:00' ?>" class="form-control form-control-sm"></td>
              <td><input type="time" name="attendance[<?= $emp['id'] ?>][time_out]" value="<?= $rec['time_out'] ?? '17:00' ?>" class="form-control form-control-sm"></td>
              <td><input type="number" step="0.5" min="0" max="12" name="attendance[<?= $emp['id'] ?>][overtime_hours]" value="<?= $rec['overtime_hours'] ?? 0 ?>" class="form-control form-control-sm"></td>
              <td><input type="text" name="attendance[<?= $emp['id'] ?>][remarks]"  value="<?= htmlspecialchars($rec['remarks'] ?? '') ?>" class="form-control form-control-sm" placeholder="Optional remarks"></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Attendance</button>
        <span class="text-muted"><?= count($employees) ?> employees | <?= date('M j, Y', strtotime($selectedDate)) ?></span>
      </div>
    </form>
  </div>
</div>

<?php else: ?>
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
// Show/hide leave type dropdown based on status selection
document.querySelectorAll('.att-status').forEach(function(sel) {
    sel.addEventListener('change', function() {
        var empId    = this.dataset.empid;
        var leaveSel = document.querySelector('.leave-type-select-' + empId);
        if (leaveSel) leaveSel.style.display = this.value === 'on_leave' ? 'block' : 'none';
    });
    sel.dispatchEvent(new Event('change'));
});

// Auto-set all statuses to Rest Day when date picker changes to Saturday/Sunday.
// Mirrors the server-side logic so the form reflects the correct default immediately
// without a page reload — user can still override individual rows before saving.
var datePicker = document.querySelector('input[name="date"]');
if (datePicker) {
    datePicker.addEventListener('change', function() {
        var d   = new Date(this.value + 'T00:00:00');
        var dow = d.getDay(); // 0=Sunday, 6=Saturday
        if (dow === 0 || dow === 6) {
            document.querySelectorAll('.att-status').forEach(function(sel) {
                sel.value = 'rest_day';
                sel.dispatchEvent(new Event('change'));
            });
        } else {
            // Weekday — reset back to present as the default
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