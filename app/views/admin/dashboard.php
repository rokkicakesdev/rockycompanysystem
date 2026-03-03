<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../layouts/admin_header.php';

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
    <i class="fas fa-tachometer-alt" class="text-primary"></i>
    <h1>Dashboard</h1>
    <small class="text-muted ml-auto"><?= date('l, F j, Y') ?></small>
  </div>



    <!-- STAT CARDS ROW -->
    <div class="row mb-4">
      <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
          <div class="icon-box" style="background:#dbeafe;color:#1d4ed8;"><i class="fas fa-users"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= number_format($stats['total_employees']) ?></div>
            <div class="stat-label">Active Employees</div>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
          <div class="icon-box" style="background:#fef3c7;color:#d97706;"><i class="fas fa-calendar-minus"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= number_format($stats['pending_leaves']) ?></div>
            <div class="stat-label">Pending Leave Requests</div>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
          <div class="icon-box" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-briefcase"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= number_format($stats['open_jobs']) ?></div>
            <div class="stat-label">Open Job Postings</div>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
          <div class="icon-box" style="background:#f3e8ff;color:#7c3aed;"><i class="fas fa-peso-sign"></i></div>
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
            <span><i class="fas fa-sitemap mr-2" class="text-primary"></i>Headcount by Department</span>
            <a href="employees.php" class="btn btn-sm btn-outline-primary">View All</a>
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
                  <td style="width:120px;">
                    <div class="dashboard-progress-bar-bg">
                      <div style="width:<?= $pct ?>%;height:100%;background:var(--accent);border-radius:4px;"></div>
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
            <span><i class="fas fa-calendar-minus mr-2" style="color:#d97706;"></i>Pending Leave Requests</span>
            <a href="leave.php" class="btn btn-sm btn-outline-warning">Manage</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($recentLeaves)): ?>
              <div class="text-center py-4 text-muted"><i class="fas fa-check-circle fa-2x mb-2 d-block" style="color:#86efac;"></i>No pending leave requests</div>
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
                      <div class="action-btn-group"><a href="leave.php?action=review&id=<?= $leave['id'] ?>" class="btn btn-xs btn-success">Approve</a><a href="leave.php?action=reject&id=<?= $leave['id'] ?>" class="btn btn-xs btn-danger">Reject</a></div>
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
            <span><i class="fas fa-bullhorn mr-2" style="color:#7c3aed;"></i>Announcements</span>
            <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
              <a href="announcements.php" class="btn btn-sm btn-outline-secondary">Manage</a>
            <?php endif; ?>
          </div>
          <div class="card-body" class="dashboard-card-scroll">
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
              <div style="border-left:3px solid <?= $color ?>;padding:10px 12px;margin-bottom:10px;background:#f8fafc;border-radius:0 8px 8px 0;">
                <?php if ($ann['is_pinned']): ?>
                  <i class="fas fa-thumbtack" style="color:#d97706;font-size:.7rem;" title="Pinned"></i>
                <?php endif; ?>
                <strong style="font-size:.85rem;"><?= htmlspecialchars($ann['title']) ?></strong>
                <p style="font-size:.78rem;color:#64748b;margin:4px 0 0;"><?= nl2br(htmlspecialchars(substr($ann['content'], 0, 120))) ?>...</p>
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
            <span><i class="fas fa-history mr-2" style="color:#64748b;"></i>Recent Activity</span>
            <a href="activity_log.php" class="btn btn-sm btn-outline-secondary">View All</a>
          </div>
          <div class="card-body p-0" class="dashboard-card-scroll">
            <table class="table table-sm mb-0">
              <thead><tr><th>User</th><th>Action</th><th>Time</th></tr></thead>
              <tbody>
                <?php foreach ($recentActivity as $log): ?>
                <tr>
                  <td>
                    <span style="font-weight:600;font-size:.82rem;"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></span>
                    <?php if ($log['role'] ?? null): ?>
                      <span class="badge badge-secondary ml-1" style="font-size:.65rem;"><?= ucfirst($log['role']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span style="font-size:.8rem;color:#374151;"><?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?></span><br>
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
  </div><!-- /.content -->
</div>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>