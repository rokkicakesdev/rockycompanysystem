<?php
$pageTitle = 'Activity Logs';
require_once __DIR__ . '/../layouts/admin_header.php';
if ($_SESSION['role'] !== ROLE_ADMIN) { header('Location: dashboard.php'); exit; }

$logs = Model::getActivityLogs(200);
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
    <i class="fas fa-history" class="text-primary"></i>
    <h1>Activity Logs</h1>
    <small class="text-muted ml-auto">Last 200 entries</small>
  </div>

<div class="card">
      <div class="card-body p-0">
        <div class="table-responsive" class="activity-table-wrap">
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
                <td><strong style="font-size:.82rem;"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></strong></td>
                <td><small><?= ucfirst($log['role'] ?? '') ?></small></td>
                <td>
                  <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:.7rem;font-weight:600;background:<?= $color ?>18;color:<?= $color ?>;">
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
  </div>
</div>
<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>