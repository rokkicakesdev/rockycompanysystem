<?php
// app/views/employee/dashboard.php

session_start();

$pageTitle = 'My Dashboard';
require_once __DIR__ . '/../layouts/employee_header.php';

$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$employee   = $employeeId ? Model::findEmployeeById($employeeId) : null;

// Recent payroll (last 3)
$payrollRecords = $employeeId ? Model::getPayrollRecordsByEmployee($employeeId) : [];
$recentPayroll  = array_slice($payrollRecords, 0, 3);
$latestPayroll  = $payrollRecords[0] ?? null;

// Leave requests
$leaveRequests = $employeeId ? Model::getLeaveRequestsByEmployee($employeeId) : [];
$pendingLeaves = array_filter($leaveRequests, fn($l) => $l['status'] === 'pending');

// Attendance this month
$thisMonth      = date('Y-m');
$attendance     = $employeeId ? Model::getAttendanceSummary($employeeId, $thisMonth) : [];

// Announcements
$announcements  = Model::getActiveAnnouncements();
$announcements  = array_slice($announcements, 0, 5);
?>

<div class="page-title-bar">
  <i class="fas fa-tachometer-alt text-primary"></i>
  <h1>My Dashboard</h1>
  <small class="text-muted ml-auto"><?= date('l, F j, Y') ?></small>
</div>

<!-- Welcome Banner -->
<div class="card mb-4" style="background: linear-gradient(135deg,#1e3a5f,#1a6e4a); color:#fff; border:none;">
  <div class="card-body d-flex align-items-center justify-content-between py-3 px-4">
    <div>
      <h5 class="mb-0 font-weight-bold">Welcome back, <?= htmlspecialchars(explode(' ', $employee['name'] ?? $_SESSION['name'])[0]) ?>! 👋</h5>
      <small style="opacity:.8;">
        <?= htmlspecialchars($employee['position'] ?? '') ?>
        <?php if (!empty($employee['department'])): ?> &mdash; <?= htmlspecialchars($employee['department']) ?><?php endif; ?>
      </small>
    </div>
    <div class="text-right ml-auto">
      <small style="opacity:.7; font-size:.75rem; letter-spacing:.5px; text-transform:uppercase;">Employee No.</small><br>
      <strong style="font-size:1.1rem; letter-spacing:.5px;"><?= htmlspecialchars($employee['employee_no'] ?? '—') ?></strong>
    </div>
  </div>
</div>

<!-- Stat Cards -->
<div class="row mb-4">
  <!-- Net Pay Last Period -->
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="stat-card">
      <div class="icon-box" style="background:#dbeafe;color:#1d4ed8;"><i class="fas fa-peso-sign"></i></div>
      <div class="stat-info">
        <div class="stat-value">₱<?= $latestPayroll ? number_format($latestPayroll['net_pay'], 0) : '—' ?></div>
        <div class="stat-label">Last Net Pay</div>
      </div>
    </div>
  </div>
  <!-- Days Present This Month -->
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="stat-card">
      <div class="icon-box" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-calendar-check"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= $attendance['days_present'] ?? 0 ?></div>
        <div class="stat-label">Days Present (<?= date('M') ?>)</div>
      </div>
    </div>
  </div>
  <!-- Pending Leaves -->
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="stat-card">
      <div class="icon-box" style="background:#fef3c7;color:#d97706;"><i class="fas fa-hourglass-half"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= count($pendingLeaves) ?></div>
        <div class="stat-label">Pending Leave Requests</div>
      </div>
    </div>
  </div>
  <!-- Vacation Leave Balance -->
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="stat-card">
      <div class="icon-box" style="background:#f3e8ff;color:#7c3aed;"><i class="fas fa-umbrella-beach"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= $employee['vacation_leave_balance'] ?? 0 ?> days</div>
        <div class="stat-label">Vacation Leave Balance</div>
      </div>
    </div>
  </div>
</div>

<div class="row">

  <!-- Recent Payroll -->
  <div class="col-lg-6 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title mb-0"><i class="fas fa-file-invoice-dollar mr-2 text-primary"></i>Recent Payslips</span>
        <a href="my_payslips.php" class="btn btn-sm btn-outline-primary ml-auto">View All</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentPayroll)): ?>
          <div class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>No payroll records yet.</div>
        <?php else: ?>
          <table class="table table-hover mb-0">
            <thead><tr><th>Period</th><th>Gross Pay</th><th>Net Pay</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($recentPayroll as $p): ?>
              <tr>
                <td><?= htmlspecialchars(date('M Y', strtotime($p['period'] . '-01'))) ?></td>
                <td>₱<?= number_format($p['gross_pay'], 2) ?></td>
                <td class="font-weight-bold text-success">₱<?= number_format($p['net_pay'], 2) ?></td>
                <td>
                  <span class="badge badge-<?= $p['status'] === 'released' ? 'success' : 'warning' ?>">
                    <?= ucfirst($p['status']) ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Leave Balances -->
  <div class="col-lg-6 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title mb-0"><i class="fas fa-calendar-minus mr-2 text-warning"></i>Leave Balances</span>
        <a href="my_leaves.php" class="btn btn-sm btn-outline-warning ml-auto">File Leave</a>
      </div>
      <div class="card-body p-0">
        <?php if ($employee):
          $leaveBalances = [
            'Sick Leave'      => $employee['sick_leave_balance'],
            'Vacation Leave'  => $employee['vacation_leave_balance'],
            'Emergency Leave' => $employee['emergency_leave_balance'],
            'SIL'             => $employee['sil_balance'],
          ];
        ?>
          <table class="table table-hover mb-0">
            <thead><tr><th>Leave Type</th><th class="text-center">Balance (days)</th></tr></thead>
            <tbody>
              <?php foreach ($leaveBalances as $type => $bal): ?>
              <tr>
                <td><?= $type ?></td>
                <td class="text-center">
                  <span class="badge badge-<?= $bal > 0 ? 'success' : 'secondary' ?> px-3"><?= (float)$bal ?></span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="text-center text-muted py-4">No employee data found.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Announcements -->
  <div class="col-12 mb-4">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title mb-0"><i class="fas fa-bullhorn mr-2 text-danger"></i>Announcements</span>
        <a href="announcements.php" class="btn btn-sm btn-outline-danger ml-auto">View All</a>
      </div>
      <div class="card-body">
        <?php if (empty($announcements)): ?>
          <p class="text-muted text-center mb-0">No active announcements.</p>
        <?php else: ?>
          <?php foreach ($announcements as $ann):
            $typeColors = ['urgent' => 'danger', 'payroll' => 'primary', 'leave' => 'warning', 'holiday' => 'success', 'general' => 'secondary'];
            $color = $typeColors[$ann['type']] ?? 'secondary';
          ?>
          <div class="d-flex align-items-start mb-3 pb-3 border-bottom">
            <span class="badge badge-<?= $color ?> mr-3 mt-1" style="min-width:70px;text-align:center;"><?= ucfirst($ann['type']) ?></span>
            <div>
              <strong><?= htmlspecialchars($ann['title']) ?></strong>
              <?php if ($ann['is_pinned']): ?><i class="fas fa-thumbtack text-warning ml-1" title="Pinned"></i><?php endif; ?>
              <p class="mb-0 text-muted small"><?= nl2br(htmlspecialchars(mb_strimwidth($ann['content'], 0, 150, '...'))) ?></p>
              <small class="text-muted"><?= date('M d, Y', strtotime($ann['created_at'])) ?> — <?= htmlspecialchars($ann['posted_by_name'] ?? 'Admin') ?></small>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../layouts/employee_footer.php'; ?>