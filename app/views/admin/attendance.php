<?php
$pageTitle = 'Attendance';
require_once __DIR__ . '/../layouts/admin_header.php';

// Handle form submissions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $saved = 0; $errors = 0;
    foreach ($_POST['attendance'] as $empId => $record) {
        $ok = Model::saveAttendance([
            'employee_id'    => (int)$empId,
            'date'           => $_POST['att_date'],
            'time_in'        => $record['time_in']       ?? null,
            'time_out'       => $record['time_out']      ?? null,
            'status'         => $record['status']        ?? 'present',
            'leave_type'     => $record['leave_type']    ?? null,
            'remarks'        => $record['remarks']       ?? null,
            'hours_worked'   => $record['hours_worked']  ?? null,
            'overtime_hours' => $record['overtime_hours'] ?? 0,
            'is_overtime'    => !empty($record['overtime_hours']) ? 1 : 0,
            'created_by'     => $_SESSION['user_id'],
        ]);
        $ok ? $saved++ : $errors++;
    }
    Model::log($_SESSION['user_id'], 'SAVE_ATTENDANCE', "Saved attendance for {$saved} employees on " . $_POST['att_date']);
    $msg = "<div class='alert alert-success alert-auto-dismiss'>Attendance saved for {$saved} employee(s).</div>";
}

$selectedDate  = $_GET['date']   ?? date('Y-m-d');
$selectedMonth = $_GET['month']  ?? date('Y-m');
$viewMode      = $_GET['view']   ?? 'daily'; // daily | monthly

$employees     = Model::getAllEmployees('active');
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
    <i class="fas fa-clock" class="text-primary"></i>
    <h1>Attendance Management</h1>
  </div>

<?= $msg ?>

    <!-- Controls -->
    <div class="card mb-4">
      <div class="card-body py-3">
        <form method="GET" class="form-inline flex-gap-2">
          <div class="mr-3" style="display:inline-flex; gap:8px;">
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
      </div>
      <div class="card-body p-0">
        <form method="POST">
          <input type="hidden" name="att_date" value="<?= $selectedDate ?>">
          <input type="hidden" name="save_attendance" value="1">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Department</th>
                  <th style="width:120px;">Status</th>
                  <th style="width:95px;">Time In</th>
                  <th style="width:95px;">Time Out</th>
                  <th style="width:80px;">OT Hrs</th>
                  <th style="width:150px;">Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($employees as $emp):
                  $rec = $existingRecords[$emp['id']] ?? null;
                  $status = $rec['status'] ?? 'present';
                ?>
                <tr>
                  <td>
                    <strong style="font-size:.85rem;"><?= htmlspecialchars($emp['name']) ?></strong><br>
                    <small class="text-muted"><?= $emp['employee_no'] ?></small>
                  </td>
                  <td><small><?= htmlspecialchars($emp['department']) ?></small></td>
                  <td>
                    <select name="attendance[<?= $emp['id'] ?>][status]" class="form-control form-control-sm att-status" data-empid="<?= $emp['id'] ?>">
                      <?php foreach ($statusOptions as $val => $opt): ?>
                        <option value="<?= $val ?>" <?= $status===$val?'selected':'' ?>
                          style="color:<?= $opt['color'] ?>;"><?= $opt['label'] ?></option>
                      <?php endforeach; ?>
                    </select>
                    <select name="attendance[<?= $emp['id'] ?>][leave_type]" class="form-control form-control-sm mt-1 leave-type-select-<?= $emp['id'] ?>"
                      style="display:<?= $status==='on_leave'?'block':'none' ?>; font-size:.75rem;">
                      <option value="">-- Leave Type --</option>
                      <?php foreach (LEAVE_TYPES as $lk => $lv): ?>
                        <option value="<?= $lk ?>" <?= ($rec['leave_type']??'')===$lk?'selected':'' ?>><?= $lv ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td><input type="time" name="attendance[<?= $emp['id'] ?>][time_in]" value="<?= $rec['time_in'] ?? '08:00' ?>" class="form-control form-control-sm"></td>
                  <td><input type="time" name="attendance[<?= $emp['id'] ?>][time_out]" value="<?= $rec['time_out'] ?? '17:00' ?>" class="form-control form-control-sm"></td>
                  <td><input type="number" step="0.5" min="0" max="12" name="attendance[<?= $emp['id'] ?>][overtime_hours]" value="<?= $rec['overtime_hours'] ?? 0 ?>" class="form-control form-control-sm"></td>
                  <td><input type="text" name="attendance[<?= $emp['id'] ?>][remarks]" value="<?= htmlspecialchars($rec['remarks'] ?? '') ?>" class="form-control form-control-sm" placeholder="Optional"></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="card-footer">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Attendance</button>
            <span class="text-muted ml-3" style="font-size:.8rem;"><?= count($employees) ?> employees</span>
          </div>
        </form>
      </div>
    </div>

    <?php else: ?>
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
                  <small class="text-muted"><?= $emp['employee_no'] ?> — <?= htmlspecialchars($emp['department']) ?></small>
                </td>
                <td class="text-center"><span class="badge" class="badge-present"><?= $summary['days_present'] ?? 0 ?></span></td>
                <td class="text-center"><span class="badge" class="badge-absent"><?= $summary['days_absent'] ?? 0 ?></span></td>
                <td class="text-center"><span class="badge" class="badge-late"><?= $summary['days_late'] ?? 0 ?></span></td>
                <td class="text-center"><span class="badge" class="badge-on_leave"><?= $summary['days_on_leave'] ?? 0 ?></span></td>
                <td class="text-center"><span class="badge" class="badge-half_day"><?= $summary['days_half'] ?? 0 ?></span></td>
                <td class="text-center"><span class="badge" class="badge-holiday"><?= $summary['days_holiday'] ?? 0 ?></span></td>
                <td class="text-center"><strong><?= number_format((float)($summary['total_overtime'] ?? 0), 1) ?></strong></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
document.querySelectorAll('.att-status').forEach(sel => {
  sel.addEventListener('change', function() {
    const empId = this.dataset.empid;
    const leaveSel = document.querySelector('.leave-type-select-' + empId);
    if (leaveSel) leaveSel.style.display = this.value === 'on_leave' ? 'block' : 'none';
  });
});
</script>
<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>