<?php
$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';
require_once __DIR__ . '/../layouts/admin_header.php';

// CSRF token for inline leave approve/reject forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$stats        = Model::getDashboardStats();
$headcount    = Model::getHeadcountByDepartment();
$announcements = Model::getActiveAnnouncements();
$recentLeaves = Model::getAllLeaveRequests('pending');
$recentActivity = Model::getActivityLogs(10);
$currentPeriod  = date('Y-m');
$currentPayroll = Model::getPayrollByPeriod($currentPeriod);
?>

<!-- Content Wrapper -->
<div class="page-title-bar">
  <i class="fas fa-tachometer-alt text-primary"></i>
  <h1>Dashboard</h1>
  <small class="text-muted ml-auto"><?= date('l, F j, Y') ?></small>
</div>

<!-- STAT CARDS ROW -->
<div class="row mb-4">
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="stat-card">
      <div class="icon-box icon-box-blue"><i class="fas fa-users"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= number_format($stats['total_employees']) ?></div>
        <div class="stat-label">Active Employees</div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="stat-card">
      <div class="icon-box icon-box-yellow"><i class="fas fa-calendar-minus"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= number_format($stats['pending_leaves']) ?></div>
        <div class="stat-label">Pending Leave Requests</div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="stat-card">
      <div class="icon-box icon-box-green"><i class="fas fa-briefcase"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= number_format($stats['open_jobs']) ?></div>
        <div class="stat-label">Open Job Postings</div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="stat-card">
      <div class="icon-box icon-box-purple"><i class="fas fa-peso-sign"></i></div>
      <div class="stat-info">
        <div class="stat-value">₱<?= number_format($stats['last_month_payroll'], 0) ?></div>
        <div class="stat-label">Last Month Net Payroll</div>
      </div>
    </div>
  </div>
</div>

<div class="row">

  <!-- HEADCOUNT BY DEPT -->
  <div class="col-lg-5 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title mb-0"><i class="fas fa-sitemap mr-2 text-primary"></i>Headcount by Department</span>
        <a href="employees.php" class="btn btn-sm btn-outline-primary ml-auto">View All</a>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead><tr><th>Department</th><th class="text-center">Employees</th><th>Distribution</th></tr></thead>
          <tbody>
            <?php
            $totalEmp = array_sum(array_column($headcount, 'count')) ?: 1;
            foreach ($headcount as $dept):
              $pct = round($dept['count'] / $totalEmp * 100);
            ?>
            <tr>
              <td><?= htmlspecialchars($dept['department']) ?></td>
              <td class="text-center"><strong><?= $dept['count'] ?></strong></td>
              <td class="dash-progress-cell">
                <div class="dashboard-progress-bar-bg">
                  <div class="dash-progress-fill" style="width:<?= $pct ?>%"></div>
                </div>
                <small class="text-muted"><?= $pct ?>%</small>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- PENDING LEAVES -->
  <div class="col-lg-7 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title mb-0"><i class="fas fa-calendar-minus mr-2 dash-pending-icon"></i>Pending Leave Requests</span>
        <a href="leave.php" class="btn btn-sm btn-outline-warning ml-auto">Manage</a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($recentLeaves)): ?>
          <div class="text-center py-4 text-muted"><i class="fas fa-check-circle fa-2x mb-2 d-block dash-no-pending-icon"></i>No pending leave requests</div>
        <?php else: ?>
          <table class="table table-hover mb-0">
            <thead><tr><th>Employee</th><th>Type</th><th>Duration</th><th>Filed</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach (array_slice($recentLeaves, 0, 5) as $leave):
                $leaveTypes = LEAVE_TYPES;
              ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($leave['employee_name']) ?></strong><br>
                  <small class="text-muted"><?= htmlspecialchars($leave['department']) ?></small>
                </td>
                <td><?= $leaveTypes[$leave['leave_type']] ?? $leave['leave_type'] ?></td>
                <td>
                  <?= $leave['days_applied'] ?> day(s)<br>
                  <small class="text-muted"><?= date('M d', strtotime($leave['date_from'])) ?> – <?= date('M d', strtotime($leave['date_to'])) ?></small>
                </td>
                <td><small><?= date('M d, Y', strtotime($leave['filed_at'])) ?></small></td>
                <td>
                  <div class="action-btn-group">
                    <form method="POST" action="leave.php" class="action-form-inline"
                      onsubmit="return confirm('Approve this leave request?')">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="leave_id"   value="<?= $leave['id'] ?>">
                      <input type="hidden" name="action"     value="approved">
                      <button type="submit" class="btn btn-xs btn-success">
                        <i class="fas fa-check"></i> Approve
                      </button>
                    </form>
                    <form method="POST" action="leave.php" class="action-form-inline"
                      onsubmit="return confirm('Reject this leave request?')">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="leave_id"   value="<?= $leave['id'] ?>">
                      <input type="hidden" name="action"     value="rejected">
                      <button type="submit" class="btn btn-xs btn-danger">
                        <i class="fas fa-times"></i> Reject
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ANNOUNCEMENTS -->
  <div class="col-lg-5 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title mb-0"><i class="fas fa-bullhorn mr-2 dash-announcements-icon"></i>Announcements</span>
        <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
          <a href="announcements.php" class="btn btn-sm btn-outline-secondary ml-auto">Manage</a>
        <?php endif; ?>
      </div>
      <div class="card-body dashboard-card-scroll">
        <?php if (empty($announcements)): ?>
          <p class="text-muted text-center py-3">No active announcements</p>
        <?php else: ?>
          <?php
          $typeColors = [
            'general' => '#6366f1','payroll' => '#2563eb','leave' => '#d97706',
            'holiday' => '#16a34a','urgent' => '#dc2626'
          ];
          foreach ($announcements as $ann):
            $color = $typeColors[$ann['type']] ?? '#6366f1';
          ?>
          <div class="dash-announcement-border" style="border-left:3px solid <?= $color ?>">
            <?php if ($ann['is_pinned']): ?>
              <i class="fas fa-thumbtack dash-pin-icon" title="Pinned"></i>
            <?php endif; ?>
            <strong class="dash-ann-title"><?= htmlspecialchars($ann['title']) ?></strong>
            <p class="dash-ann-excerpt"><?= nl2br(htmlspecialchars(substr($ann['content'], 0, 120))) ?>...</p>
            <small class="text-muted"><?= date('M d', strtotime($ann['created_at'])) ?></small>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- RECENT ACTIVITY -->
  <div class="col-lg-7 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title mb-0"><i class="fas fa-history mr-2 dash-activity-icon"></i>Recent Activity</span>
        <a href="activity_log.php" class="btn btn-sm btn-outline-secondary ml-auto">View All</a>
      </div>
      <div class="card-body p-0 dashboard-card-scroll">
        <table class="table table-sm mb-0">
          <thead><tr><th>User</th><th>Action</th><th>Time</th></tr></thead>
          <tbody>
            <?php foreach ($recentActivity as $log): ?>
            <tr>
              <td>
                <span class="dash-activity-name"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></span>
                <?php if ($log['role'] ?? null): ?>
                  <span class="badge badge-secondary ml-1 dash-activity-role"><?= ucfirst($log['role']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <span class="dash-activity-action"><?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?></span><br>
                <small class="text-muted"><?= htmlspecialchars(substr($log['description'] ?? '', 0, 50)) ?></small>
              </td>
              <td><small class="text-muted"><?= date('M d H:i', strtotime($log['created_at'])) ?></small></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- /.row -->

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>