<?php
$pageTitle = 'Management Dashboard';
require_once __DIR__ . '/../layouts/admin_header.php';

$stats         = Model::getDashboardStats();
$headcount     = Model::getHeadcountByDepartment();
$announcements = Model::getActiveAnnouncements();
$periods       = Model::getPayrollPeriods();
$latestPeriod  = $periods[0] ?? date('Y-m');
$latestPayroll = Model::getPayrollByPeriod($latestPeriod);
$typeColors    = ['general'=>'#6366f1','payroll'=>'#2563eb','leave'=>'#d97706','holiday'=>'#16a34a','urgent'=>'#dc2626'];
?>

<div class="page-title-bar">
  <i class="fas fa-chart-line" style="color:var(--accent);"></i>
  <h1>Management Dashboard</h1>
  <small class="text-muted ml-auto"><?= date('l, F j, Y') ?></small>
</div>

<!-- Stats Row -->
<div class="row mb-4">
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="stat-card">
      <div class="icon-box" style="background:#dbeafe;color:#1d4ed8;"><i class="fas fa-users"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= $stats['total_employees'] ?></div>
        <div class="stat-label">Active Employees</div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="stat-card">
      <div class="icon-box" style="background:#fef3c7;color:#d97706;"><i class="fas fa-calendar-minus"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= $stats['pending_leaves'] ?></div>
        <div class="stat-label">Pending Leaves</div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="stat-card">
      <div class="icon-box" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-peso-sign"></i></div>
      <div class="stat-info">
        <div class="stat-value">&#8369;<?= number_format($stats['last_month_payroll'] / 1000, 1) ?>k</div>
        <div class="stat-label">Last Month Payroll</div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-md-6 mb-3">
    <div class="stat-card">
      <div class="icon-box" style="background:#f3e8ff;color:#7c3aed;"><i class="fas fa-briefcase"></i></div>
      <div class="stat-info">
        <div class="stat-value"><?= $stats['open_jobs'] ?></div>
        <div class="stat-label">Open Job Postings</div>
      </div>
    </div>
  </div>
</div>

<div class="row">

  <!-- Headcount by Department -->
  <div class="col-md-5 mb-4">
    <div class="card h-100">
      <div class="card-header">
        <i class="fas fa-sitemap mr-2 text-primary"></i>Headcount by Department
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>Department</th><th class="text-center">Employees</th></tr>
          </thead>
          <tbody>
            <?php foreach ($headcount as $dept): ?>
            <tr>
              <td><?= htmlspecialchars($dept['department']) ?></td>
              <td class="text-center"><strong><?= $dept['count'] ?></strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Latest Payroll -->
  <div class="col-md-7 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-money-check-alt mr-2 text-success"></i>Latest Payroll &mdash; <?= htmlspecialchars($latestPeriod) ?></span>
        <a href="payroll.php" class="btn btn-xs btn-outline-primary ml-auto">Full Report</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive" style="max-height:300px; overflow-y:auto;">
          <table class="table table-sm table-hover mb-0">
            <thead>
              <tr><th>Employee</th><th>Gross</th><th>Net Pay</th><th class="text-center">Status</th></tr>
            </thead>
            <tbody>
              <?php if (empty($latestPayroll)): ?>
                <tr><td colspan="4" class="text-center text-muted py-3">No payroll records for this period.</td></tr>
              <?php endif; ?>
              <?php foreach ($latestPayroll as $pr): ?>
              <tr>
                <td><?= htmlspecialchars($pr['employee_name']) ?></td>
                <td>&#8369;<?= number_format($pr['gross_pay'], 2) ?></td>
                <td><strong class="text-success">&#8369;<?= number_format($pr['net_pay'], 2) ?></strong></td>
                <td class="text-center">
                  <span class="status-badge badge-<?= $pr['status'] ?>"><?= ucfirst($pr['status']) ?></span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Announcements -->
  <div class="col-12 mb-4">
    <div class="card">
      <div class="card-header">
        <i class="fas fa-bullhorn mr-2 text-danger"></i>Announcements
      </div>
      <div class="card-body">
        <?php if (empty($announcements)): ?>
          <p class="text-muted text-center py-2 mb-0">No active announcements.</p>
        <?php else: ?>
          <div class="row">
            <?php foreach ($announcements as $ann):
              $color = $typeColors[$ann['type']] ?? '#6366f1';
            ?>
            <div class="col-md-4 mb-3">
              <div style="border-left:4px solid <?= $color ?>; padding:12px; background:#f8fafc; border-radius:0 8px 8px 0;">
                <strong style="font-size:.85rem;"><?= htmlspecialchars($ann['title']) ?></strong>
                <?php if ($ann['is_pinned']): ?>
                  <i class="fas fa-thumbtack ml-1" style="color:#d97706; font-size:.75rem;" title="Pinned"></i>
                <?php endif; ?>
                <p style="font-size:.78rem; color:#64748b; margin:6px 0 0;">
                  <?= nl2br(htmlspecialchars(mb_strimwidth($ann['content'], 0, 100, '...'))) ?>
                </p>
                <small class="text-muted"><?= date('M d', strtotime($ann['created_at'])) ?></small>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div><!-- /.row -->

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>