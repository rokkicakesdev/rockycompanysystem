<?php
$pageTitle = 'Activity Logs';
require_once __DIR__ . '/../layouts/admin_header.php';
if ($_SESSION['role'] !== ROLE_ADMIN) { header('Location: dashboard.php'); exit; }

$perPage    = RECORDS_PER_PAGE;
$totalLogs  = Model::countActivityLogs();
$totalPages = (int) ceil($totalLogs / $perPage);
$curPage    = max(1, min((int)($_GET['page'] ?? 1), max(1, $totalPages)));
$offset     = ($curPage - 1) * $perPage;
$logs       = Model::getActivityLogsPaginated($perPage, $offset);
$actionColors = [
    'LOGIN'             => '#22c55e',
    'LOGOUT'            => '#94a3b8',
    'CREATE_EMPLOYEE'   => '#2563eb',
    'UPDATE_EMPLOYEE'   => '#f59e0b',
    'TOGGLE_STATUS'     => '#8b5cf6',
    'SAVE_ATTENDANCE'   => '#06b6d4',
    'CREATE_LEAVE'      => '#10b981',
    'APPROVED_LEAVE'    => '#22c55e',
    'REJECTED_LEAVE'    => '#ef4444',
    'CREATE_JOB_POSTING'=> '#f97316',
    'ADD_APPLICANT'     => '#ec4899',
    'UPDATE_APPLICANT'  => '#d97706',
    'CREATE_USER'       => '#6366f1',
    'UPDATE_USER'       => '#a855f7',
];
?>

<div class="page-title-bar">
    <i class="fas fa-history text-primary"></i>
    <h1>Activity Logs</h1>
    <small class="text-muted ml-auto">Showing <?= number_format(($curPage-1)*$perPage+1) ?>–<?= number_format(min($curPage*$perPage,$totalLogs)) ?> of <?= number_format($totalLogs) ?> entries</small>
  </div>

<div class="card">
      <div class="card-body p-0">
        <div class="table-responsive activity-table-wrap">
          <table class="table table-hover table-sm mb-0">
            <thead >
              <tr>
                <th>Time</th>
                <th>User</th>
                <th>Role</th>
                <th>Action</th>
                <th>Description</th>
                <th>IP Address</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($logs as $log):
                $color = $actionColors[$log['action']] ?? '#64748b';
              ?>
              <tr>
                <td><small><?= date('M d H:i:s', strtotime($log['created_at'])) ?></small></td>
                <td><strong class="activity-user-name"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></strong></td>
                <td><small><?= ucfirst($log['role'] ?? '') ?></small></td>
                <td>
                  <span class="activity-action-badge log-action-<?= htmlspecialchars(strtolower($log['action'])) ?>">
                    <?= str_replace('_', ' ', $log['action']) ?>
                  </span>
                </td>
                <td><small><?= htmlspecialchars($log['description'] ?? '') ?></small></td>
                <td><small class="text-muted"><?= $log['ip_address'] ?? '—' ?></small></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
<?php if ($totalPages > 1): ?>
<div class="d-flex justify-content-center mt-3">
  <nav>
    <ul class="pagination pagination-sm mb-0">
      <li class="page-item <?= $curPage <= 1 ? 'disabled' : '' ?>">
        <a class="page-link" href="?page=<?= $curPage-1 ?>">«</a>
      </li>
      <?php
        $start = max(1, $curPage - 2);
        $end   = min($totalPages, $curPage + 2);
        for ($i = $start; $i <= $end; $i++): ?>
        <li class="page-item <?= $i === $curPage ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
      <li class="page-item <?= $curPage >= $totalPages ? 'disabled' : '' ?>">
        <a class="page-link" href="?page=<?= $curPage+1 ?>">»</a>
      </li>
    </ul>
  </nav>
</div>
<?php endif; ?>
</div>
</div>
<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>