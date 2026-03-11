<?php
$pageTitle = 'Leave Management';
require_once __DIR__ . '/../layouts/admin_header.php';

$msg = '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token. Please refresh and try again.</div>";
    } else {
        require_once __DIR__ . '/../../../core/Validator.php';
        $v = new Validator($_POST);
        $v->required('leave_id', 'Leave request')
          ->inList('action', ['approved', 'rejected'], 'Action');
        if ($v->fails()) {
            $msg = $v->errorHtml();
        } else {
            $id     = (int)$_POST['leave_id'];
            $action = $_POST['action'];
            $notes  = trim($_POST['review_notes'] ?? '');
            Model::reviewLeaveRequest($id, $action, $_SESSION['user_id'], $notes);
            $leave = Model::findLeaveRequestById($id);
            Model::log($_SESSION['user_id'], strtoupper($action) . '_LEAVE',
                "{$action} leave request ID:{$id} for {$leave['employee_name']}");
            $msg = "<div class='alert alert-success alert-auto-dismiss'>Leave request <strong>{$action}</strong> successfully.</div>";
        }
    }
}

// Handle new leave request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_leave'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token. Please refresh and try again.</div>";
    } else {
        require_once __DIR__ . '/../../../core/Validator.php';
        $v = new Validator($_POST);
        $v->required('employee_id', 'Employee')
          ->required('leave_type', 'Leave type')
          ->required('date_from', 'Date from')->date('date_from', 'Date from')
          ->required('date_to', 'Date to')->date('date_to', 'Date to')
          ->dateAfter('date_from', 'date_to', 'Date from', 'Date to')
          ->required('days_applied', 'Days applied')->positiveNumber('days_applied', 'Days applied');
        if ($v->fails()) {
            $msg = $v->errorHtml();
        } else {
            Model::createLeaveRequest([
                'employee_id'  => (int)$_POST['employee_id'],
                'leave_type'   => $_POST['leave_type'],
                'date_from'    => $_POST['date_from'],
                'date_to'      => $_POST['date_to'],
                'days_applied' => (float)$_POST['days_applied'],
                'reason'       => trim($_POST['reason'] ?? ''),
            ]);
            Model::log($_SESSION['user_id'], 'CREATE_LEAVE', "Filed leave for employee ID:" . $_POST['employee_id']);
            $msg = "<div class='alert alert-success alert-auto-dismiss'>Leave request filed successfully.</div>";
        }
    }
}

$filterStatus = $_GET['status'] ?? '';
$allLeaves    = Model::getAllLeaveRequests($filterStatus);
$employees    = Model::getAllEmployees('active');
$leaveTypes   = LEAVE_TYPES;

$statusCounts = [
    'all'      => count(Model::getAllLeaveRequests()),
    'pending'  => count(Model::getAllLeaveRequests('pending')),
    'approved' => count(Model::getAllLeaveRequests('approved')),
    'rejected' => count(Model::getAllLeaveRequests('rejected')),
];

// Pagination
$perPage    = RECORDS_PER_PAGE;
$totalLeaves = count($allLeaves);
$totalPages  = (int) ceil($totalLeaves / $perPage);
$curPage     = max(1, min((int)($_GET['page'] ?? 1), max(1, $totalPages)));
$leaves      = array_slice($allLeaves, ($curPage - 1) * $perPage, $perPage);
?>

<div class="page-title-bar">
    <i class="fas fa-calendar-minus" class="text-primary"></i>
    <h1>Leave Management</h1>
    <button class="btn btn-sm btn-primary ml-auto" data-toggle="modal" data-target="#newLeaveModal">
      <i class="fas fa-plus mr-1"></i>File Leave Request
    </button>
  </div>

<?= $msg ?>

    <!-- Filter tabs -->
    <div class="card mb-4">
      <div class="card-body py-2 px-3">
        <div class="flex-gap-2">
          <a href="leave.php" class="btn btn-sm <?= !$filterStatus ? 'btn-primary' : 'btn-outline-secondary' ?>">
            All <span class="badge badge-light ml-1"><?= $statusCounts['all'] ?></span>
          </a>
          <a href="leave.php?status=pending" class="btn btn-sm <?= $filterStatus==='pending' ? 'btn-warning' : 'btn-outline-warning' ?>">
            Pending <span class="badge badge-light ml-1"><?= $statusCounts['pending'] ?></span>
          </a>
          <a href="leave.php?status=approved" class="btn btn-sm <?= $filterStatus==='approved' ? 'btn-success' : 'btn-outline-success' ?>">
            Approved <span class="badge badge-light ml-1"><?= $statusCounts['approved'] ?></span>
          </a>
          <a href="leave.php?status=rejected" class="btn btn-sm <?= $filterStatus==='rejected' ? 'btn-danger' : 'btn-outline-danger' ?>">
            Rejected <span class="badge badge-light ml-1"><?= $statusCounts['rejected'] ?></span>
          </a>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>Employee</th>
                <th>Leave Type</th>
                <th>Period</th>
                <th>Days</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Filed</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($leaves)): ?>
                <tr><td colspan="9" class="text-center py-4 text-muted">No leave requests found.</td></tr>
              <?php endif; ?>
              <?php foreach ($leaves as $leave): ?>
              <tr>
                <td><?= $leave['id'] ?></td>
                <td>
                  <strong><?= htmlspecialchars($leave['employee_name']) ?></strong><br>
                  <small class="text-muted"><?= $leave['employee_no'] ?> — <?= htmlspecialchars($leave['department']) ?></small>
                </td>
                <td><?= $leaveTypes[$leave['leave_type']] ?? $leave['leave_type'] ?></td>
                <td>
                  <?= date('M d', strtotime($leave['date_from'])) ?> – <?= date('M d, Y', strtotime($leave['date_to'])) ?>
                </td>
                <td class="text-center"><strong><?= $leave['days_applied'] ?></strong></td>
                <td><small><?= htmlspecialchars(substr($leave['reason'] ?? '—', 0, 50)) ?></small></td>
                <td>
                  <span class="status-badge badge-<?= $leave['status'] ?>">
                    <?= ucfirst($leave['status']) ?>
                  </span>
                </td>
                <td><small><?= date('M d, Y', strtotime($leave['filed_at'])) ?></small></td>
                <td>
                  <?php if ($leave['status'] === 'pending'): ?>
                  <div class="action-btn-group">
                  <button class="btn btn-xs btn-success" data-toggle="modal" data-target="#reviewModal"
                    data-id="<?= $leave['id'] ?>" data-action="approved"
                    data-name="<?= htmlspecialchars($leave['employee_name']) ?>">
                    <i class="fas fa-check"></i>
                  </button>
                  <button class="btn btn-xs btn-danger" data-toggle="modal" data-target="#reviewModal"
                    data-id="<?= $leave['id'] ?>" data-action="rejected"
                    data-name="<?= htmlspecialchars($leave['employee_name']) ?>">
                    <i class="fas fa-times"></i>
                  </button>
                  </div>
                  <?php else: ?>
                  <small class="text-muted">by <?= htmlspecialchars($leave['reviewed_by_name'] ?? '—') ?></small>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if ($totalPages > 1): ?>
      <div class="card-footer d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
        <span class="text-muted" style="font-size:.82rem;">
          Showing <?= number_format(($curPage-1)*$perPage+1) ?>–<?= number_format(min($curPage*$perPage,$totalLeaves)) ?> of <?= number_format($totalLeaves) ?> request(s)
        </span>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $curPage <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $curPage-1])) ?>">«</a>
            </li>
            <?php
              $start = max(1, $curPage - 2);
              $end   = min($totalPages, $curPage + 2);
              for ($i = $start; $i <= $end; $i++): ?>
              <li class="page-item <?= $i === $curPage ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= $curPage >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $curPage+1])) ?>">»</a>
            </li>
          </ul>
        </nav>
      </div>
      <?php endif; ?>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="reviewModalTitle">Review Leave Request</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
          <input type="hidden" name="leave_id" id="reviewLeaveId">
          <input type="hidden" name="action"   id="reviewAction">
          <p id="reviewDesc" class="text-muted mb-3"></p>
          <div class="form-group">
            <label>Notes / Remarks (optional)</label>
            <textarea name="review_notes" class="form-control" rows="3" placeholder="Add a note..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn" id="reviewSubmitBtn">Confirm</button>
        </div>
      </form>
    </div>
</div>

<!-- New Leave Modal -->
<div class="modal fade" id="newLeaveModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="new_leave" value="1">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="modal-header">
          <h5 class="modal-title">File Leave Request</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Employee <span class="text-danger">*</span></label>
            <select name="employee_id" class="form-control" required>
              <option value="">-- Select Employee --</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> (<?= $emp['employee_no'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Leave Type <span class="text-danger">*</span></label>
            <select name="leave_type" class="form-control" required>
              <?php foreach ($leaveTypes as $k => $v): ?>
                <option value="<?= $k ?>"><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row">
            <div class="col-6">
              <div class="form-group">
                <label>Date From <span class="text-danger">*</span></label>
                <input type="date" name="date_from" class="form-control" id="newDateFrom" required>
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label>Date To <span class="text-danger">*</span></label>
                <input type="date" name="date_to" class="form-control" id="newDateTo" required>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Number of Days <span class="text-danger">*</span></label>
            <input type="number" step="0.5" min="0.5" name="days_applied" class="form-control" id="newDays" value="1" required>
          </div>
          <div class="form-group">
            <label>Reason</label>
            <textarea name="reason" class="form-control" rows="2" placeholder="Reason for leave..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">File Leave Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = <<<JS
$('#reviewModal').on('show.bs.modal', function(e) {
  const btn    = $(e.relatedTarget);
  const id     = btn.data('id');
  const action = btn.data('action');
  const name   = btn.data('name');
  $('#reviewLeaveId').val(id);
  $('#reviewAction').val(action);
  $('#reviewDesc').text('You are about to ' + action.toUpperCase() + ' the leave request for ' + name + '.');
  const submit = $('#reviewSubmitBtn');
  if (action === 'approved') {
    submit.removeClass('btn-danger').addClass('btn-success').text('Approve');
    $('#reviewModalTitle').text('Approve Leave Request');
  } else {
    submit.removeClass('btn-success').addClass('btn-danger').text('Reject');
    $('#reviewModalTitle').text('Reject Leave Request');
  }
});

// Auto-calc days
function calcDays() {
  const from = new Date($('#newDateFrom').val());
  const to   = new Date($('#newDateTo').val());
  if (from && to && to >= from) {
    const diff = Math.round((to - from) / (1000 * 60 * 60 * 24)) + 1;
    $('#newDays').val(diff);
  }
}
$('#newDateFrom, #newDateTo').on('change', calcDays);

// Refresh badge immediately after approve/reject
$(document).ready(function() {
  if ($('.alert-success').length) {
    if (typeof refreshPendingBadge === 'function') refreshPendingBadge();
  }
});
JS;
require_once __DIR__ . '/../layouts/admin_footer.php'; ?>