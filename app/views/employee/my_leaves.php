<?php
// app/views/employee/my_leaves.php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$pageTitle = 'My Leaves';
require_once __DIR__ . '/../layouts/employee_header.php';

$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$employee   = $employeeId ? Model::findEmployeeById($employeeId) : null;
$leaveTypes = LEAVE_TYPES;

$msg = '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle new leave request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_leave'])) {

    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Invalid security token. Please refresh and try again.</div>";
    } else {
        require_once __DIR__ . '/../../../core/Validator.php';
        $v = new Validator($_POST);
        $v->required('leave_type', 'Leave type')
          ->required('date_from', 'Date from')->date('date_from', 'Date from')
          ->required('date_to', 'Date to')->date('date_to', 'Date to')
          ->dateAfter('date_from', 'date_to', 'Date from', 'Date to')
          ->required('days_applied', 'Days applied')->positiveNumber('days_applied', 'Days applied');
        if ($v->fails()) {
            $msg = $v->errorHtml();
        } else {
            $created = Model::createLeaveRequest([
                'employee_id'  => $employeeId,
                'leave_type'   => $_POST['leave_type'],
                'date_from'    => $_POST['date_from'],
                'date_to'      => $_POST['date_to'],
                'days_applied' => (float)$_POST['days_applied'],
                'reason'       => trim($_POST['reason'] ?? ''),
            ]);
            if ($created) {
                $msg = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>Leave request filed successfully. Please wait for approval.</div>";
            } else {
                $msg = "<div class='alert alert-danger alert-auto-dismiss'><i class='fas fa-exclamation-circle mr-2'></i>Failed to file leave request. Please try again.</div>";
            }
        }
    } // end CSRF else
}

$leaveRequests = $employeeId ? Model::getLeaveRequestsByEmployee($employeeId) : [];

// Gender of the logged-in employee
$empGender = strtolower($employee['gender'] ?? '');

// Leave type → gender restriction map
// 'all' = anyone, 'female' = female only, 'male' = male only
$leaveGenderMap = [
    'sick'        => 'all',
    'vacation'    => 'all',
    'bereavement' => 'all',
    'emergency'   => 'all',
    'sil'         => 'all',
    'solo_parent' => 'all',
    'unpaid'      => 'all',
    'maternity'   => 'female',
    'vawc'        => 'female',
    'magna_carta' => 'female',
    'paternity'   => 'male',
];

// Filter the full LEAVE_TYPES list down to what this employee can use
$availableLeaveTypes = array_filter($leaveTypes, function($key) use ($leaveGenderMap, $empGender) {
    $restriction = $leaveGenderMap[$key] ?? 'all';
    if ($restriction === 'all')    return true;
    if ($empGender === '')         return true; // unknown gender — show all
    return $restriction === $empGender;
}, ARRAY_FILTER_USE_KEY);

// Leave balance map — filter out gender-inapplicable types
$allBalanceMap = [
    'sick'        => ['label' => 'Sick Leave',      'field' => 'sick_leave_balance'],
    'vacation'    => ['label' => 'Vacation Leave',   'field' => 'vacation_leave_balance'],
    'emergency'   => ['label' => 'Emergency Leave',  'field' => 'emergency_leave_balance'],
    'sil'         => ['label' => 'SIL',              'field' => 'sil_balance'],
    'bereavement' => ['label' => 'Bereavement',      'field' => 'bereavement_leave_balance'],
    'maternity'   => ['label' => 'Maternity',        'field' => 'maternity_leave_balance',  'gender' => 'female'],
    'paternity'   => ['label' => 'Paternity',        'field' => 'paternity_leave_balance',  'gender' => 'male'],
    'vawc'        => ['label' => 'VAWC',             'field' => 'vawc_leave_balance',        'gender' => 'female'],
    'magna_carta' => ['label' => 'Magna Carta',      'field' => 'magna_carta_leave_balance', 'gender' => 'female'],
    'solo_parent' => ['label' => 'Solo Parent',      'field' => 'solo_parent_leave_balance'],
];
$balanceMap = array_filter($allBalanceMap, function($meta) use ($empGender) {
    if (!isset($meta['gender'])) return true;
    if ($empGender === '')        return true; // unknown gender — show all
    return $meta['gender'] === $empGender;
});
?>

<div class="page-title-bar">
  <i class="fas fa-calendar-minus text-warning"></i>
  <h1>My Leaves</h1>
  <button class="btn btn-sm btn-primary ml-auto" data-toggle="modal" data-target="#fileLeaveModal">
    <i class="fas fa-plus mr-1"></i> File Leave Request
  </button>
</div>

<?= $msg ?>

<!-- Leave Balances -->
<div class="row mb-4">
  <?php foreach ($balanceMap as $type => $meta):
    $balance = $employee[$meta['field']] ?? 0;
  ?>
  <div class="col-xl-3 col-md-4 col-6 mb-3">
    <div class="card text-center py-3">
      <div class="card-body p-2">
        <h4 class="mb-0 font-weight-bold <?= $balance > 0 ? 'text-success' : 'text-muted' ?>">
          <?= (float)$balance ?>
        </h4>
        <small class="text-muted"><?= $meta['label'] ?></small>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Leave History Table -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-history mr-2"></i>My Leave History</h3>
  </div>
  <div class="card-body table-responsive p-0">
    <?php if (empty($leaveRequests)): ?>
      <div class="text-center text-muted py-5">
        <i class="fas fa-calendar-times fa-3x mb-3 d-block" style="opacity:.2;"></i>
        No leave requests yet.
      </div>
    <?php else: ?>
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Type</th>
            <th>Date From</th>
            <th>Date To</th>
            <th class="text-center">Days</th>
            <th>Reason</th>
            <th class="text-center">Status</th>
            <th>Reviewed By</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($leaveRequests as $leave):
            $statusColors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
            $sc = $statusColors[$leave['status']] ?? 'secondary';
          ?>
          <tr>
            <td><?= htmlspecialchars($leaveTypes[$leave['leave_type']] ?? ucfirst($leave['leave_type'])) ?></td>
            <td><?= htmlspecialchars($leave['date_from']) ?></td>
            <td><?= htmlspecialchars($leave['date_to']) ?></td>
            <td class="text-center"><?= (float)$leave['days_applied'] ?></td>
            <td><?= htmlspecialchars(mb_strimwidth($leave['reason'] ?? '—', 0, 60, '...')) ?></td>
            <td class="text-center">
              <span class="badge badge-<?= $sc ?>"><?= ucfirst($leave['status']) ?></span>
            </td>
            <td><?= htmlspecialchars($leave['reviewed_by_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars(mb_strimwidth($leave['review_notes'] ?? '—', 0, 50, '...')) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<!-- File Leave Modal -->
<div class="modal fade" id="fileLeaveModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-calendar-plus mr-2 text-primary"></i>File Leave Request</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form method="POST" action="my_leaves.php">
        <input type="hidden" name="file_leave"  value="1">
        <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="modal-body">
          <div class="form-group">
            <label>Leave Type <span class="text-danger">*</span></label>
            <select name="leave_type" class="form-control" required>
              <option value="">-- Select Leave Type --</option>
              <?php foreach ($availableLeaveTypes as $key => $label): ?>
                <option value="<?= $key ?>"><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row">
            <div class="col-6">
              <div class="form-group">
                <label>Date From <span class="text-danger">*</span></label>
                <input type="date" name="date_from" id="dateFrom" class="form-control" required onchange="calcDays()">
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label>Date To <span class="text-danger">*</span></label>
                <input type="date" name="date_to" id="dateTo" class="form-control" required onchange="calcDays()">
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Number of Days <span class="text-danger">*</span></label>
            <input type="number" name="days_applied" id="daysApplied" class="form-control" min="0.5" step="0.5" required>
          </div>
          <div class="form-group">
            <label>Reason</label>
            <textarea name="reason" class="form-control" rows="3" placeholder="Optional reason for leave..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane mr-1"></i>Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = <<<JS
function calcDays() {
  const from = document.getElementById('dateFrom').value;
  const to   = document.getElementById('dateTo').value;
  if (from && to) {
    const d1 = new Date(from), d2 = new Date(to);
    if (d2 >= d1) {
      const diff = Math.round((d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
      document.getElementById('daysApplied').value = diff;
    }
  }
}
JS;
?>

<?php require_once __DIR__ . '/../layouts/employee_footer.php'; ?>