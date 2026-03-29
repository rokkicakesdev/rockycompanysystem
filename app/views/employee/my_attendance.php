<?php
// app/views/employee/my_attendance.php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$pageTitle = 'My Attendance';
require_once __DIR__ . '/../layouts/employee_header.php';

$employeeId  = (int)($_SESSION['employee_id'] ?? 0);
$filterMonth = $_GET['month'] ?? date('Y-m');

$attendance = $employeeId ? Model::getAttendanceByEmployee($employeeId, $filterMonth) : [];
$summary    = $employeeId ? Model::getAttendanceSummary($employeeId, $filterMonth) : [];
$employee   = $employeeId ? Model::findEmployeeById($employeeId) : null;

// Build month options from hire date up to current month
$monthOptions = [];
$hireDate     = $employee['date_hired'] ?? null;
$startYm      = $hireDate ? date('Y-m', strtotime($hireDate)) : date('Y-m', strtotime('-24 months'));
$currentYm    = date('Y-m');

$cursor = $currentYm;
while ($cursor >= $startYm) {
    $label              = date('F Y', strtotime($cursor . '-01'));
    $monthOptions[$cursor] = $label;
    $cursor = date('Y-m', strtotime($cursor . '-01 -1 month'));
}

$statusLabels = [
    'present'  => ['label' => 'Present',  'color' => 'success'],
    'absent'   => ['label' => 'Absent',   'color' => 'danger'],
    'late'     => ['label' => 'Late',      'color' => 'warning'],
    'half_day' => ['label' => 'Half Day', 'color' => 'info'],
    'on_leave' => ['label' => 'On Leave', 'color' => 'primary'],
    'holiday'  => ['label' => 'Holiday',  'color' => 'secondary'],
    'rest_day' => ['label' => 'Rest Day', 'color' => 'light'],
];
?>

<div class="page-title-bar">
  <i class="fas fa-clock text-info"></i>
  <h1>My Attendance</h1>
  <form method="GET" action="my_attendance.php" class="ml-auto d-flex align-items-center">
    <select name="month" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
      <?php foreach ($monthOptions as $val => $label): ?>
        <option value="<?= $val ?>" <?= $filterMonth === $val ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
  <div class="col-xl-2 col-md-4 col-6 mb-2">
    <div class="card text-center py-3 border-success">
      <h3 class="mb-0 text-success"><?= $summary['days_present'] ?? 0 ?></h3>
      <small class="text-muted">Present</small>
    </div>
  </div>
  <div class="col-xl-2 col-md-4 col-6 mb-2">
    <div class="card text-center py-3 border-danger">
      <h3 class="mb-0 text-danger"><?= $summary['days_absent'] ?? 0 ?></h3>
      <small class="text-muted">Absent</small>
    </div>
  </div>
  <div class="col-xl-2 col-md-4 col-6 mb-2">
    <div class="card text-center py-3 border-warning">
      <h3 class="mb-0 text-warning"><?= $summary['days_late'] ?? 0 ?></h3>
      <small class="text-muted">Late</small>
    </div>
  </div>
  <div class="col-xl-2 col-md-4 col-6 mb-2">
    <div class="card text-center py-3 border-info">
      <h3 class="mb-0 text-info"><?= $summary['days_half'] ?? 0 ?></h3>
      <small class="text-muted">Half Day</small>
    </div>
  </div>
  <div class="col-xl-2 col-md-4 col-6 mb-2">
    <div class="card text-center py-3 border-primary">
      <h3 class="mb-0 text-primary"><?= $summary['days_on_leave'] ?? 0 ?></h3>
      <small class="text-muted">On Leave</small>
    </div>
  </div>
  <div class="col-xl-2 col-md-4 col-6 mb-2">
    <div class="card text-center py-3">
      <h3 class="mb-0 text-dark"><?= number_format((float)($summary['total_overtime'] ?? 0), 1) ?>h</h3>
      <small class="text-muted">Overtime</small>
    </div>
  </div>
</div>

<!-- Attendance Table -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fas fa-list mr-2"></i>
      Attendance Records — <?= date('F Y', strtotime($filterMonth . '-01')) ?>
    </h3>
  </div>
  <div class="card-body table-responsive p-0">
    <?php if (empty($attendance)): ?>
      <div class="text-center text-muted py-5">
        <i class="fas fa-calendar-times fa-3x mb-3 d-block emp-att-empty-icon"></i>
        No attendance records for this month.
      </div>
    <?php else: ?>
      <table class="table table-hover table-bordered mb-0">
        <thead class="thead-light">
          <tr>
            <th>Date</th>
            <th>Day</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th class="text-center">Hours</th>
            <th class="text-center">Overtime</th>
            <th class="text-center">Status</th>
            <th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($attendance as $rec):
            $sl = $statusLabels[$rec['status']] ?? ['label' => ucfirst($rec['status']), 'color' => 'secondary'];
          ?>
          <tr>
            <td><?= date('M d, Y', strtotime($rec['date'])) ?></td>
            <td><?= date('D', strtotime($rec['date'])) ?></td>
            <td><?= $rec['time_in'] ? date('h:i A', strtotime($rec['time_in'])) : '—' ?></td>
            <td><?= $rec['time_out'] ? date('h:i A', strtotime($rec['time_out'])) : '—' ?></td>
            <td class="text-center"><?= $rec['hours_worked'] !== null ? number_format((float)$rec['hours_worked'], 2) : '—' ?></td>
            <td class="text-center">
              <?php if ($rec['is_overtime'] && $rec['overtime_hours'] > 0): ?>
                <span class="badge badge-warning"><?= number_format((float)$rec['overtime_hours'], 2) ?>h</span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="text-center">
              <span class="badge badge-<?= $sl['color'] ?>"><?= $sl['label'] ?></span>
              <?php if ($rec['status'] === 'on_leave' && $rec['leave_type']): ?>
                <small class="text-muted d-block"><?= ucfirst(str_replace('_', ' ', $rec['leave_type'])) ?></small>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($rec['remarks'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/employee_footer.php'; ?>