<?php
// app/views/admin/reimbursements.php
// ─────────────────────────────────────────────────────────────────────────────
//  Admin/Management: View, approve, reject, and mark reimbursements as paid.
// ─────────────────────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle  = 'Reimbursements';
$breadcrumb = 'Reimbursements';
$activeMenu = 'reimbursements';

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../core/Model.php';
require_once __DIR__ . '/../../../core/models/ReimbursementModel.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) {
    header('Location: ' . BASE_URL . '/index.php?error=access_denied'); exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$msg = '';

// ── POST: Review (approve / reject / mark paid) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_reimbursement'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Invalid security token.</div>";
    } else {
        $reimbId     = (int)($_POST['reimb_id']    ?? 0);
        $newStatus   = trim($_POST['new_status']   ?? '');
        $reviewNotes = trim($_POST['review_notes'] ?? '');
        $allowed     = ['approved', 'rejected', 'paid'];

        if (!$reimbId || !in_array($newStatus, $allowed, true)) {
            $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Invalid request.</div>";
        } elseif ($newStatus === 'rejected' && empty($reviewNotes)) {
            $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>A reason is required when rejecting a request.</div>";
        } else {
            $reimb   = ReimbursementModel::findById($reimbId);
            $empName = $reimb['employee_name'] ?? "ID:{$reimbId}";

            if (ReimbursementModel::review($reimbId, $newStatus, $_SESSION['user_id'], $reviewNotes)) {
                Model::log($_SESSION['user_id'], 'REVIEW_REIMBURSEMENT',
                    "Marked reimbursement ID:{$reimbId} ({$empName}) as {$newStatus}" .
                    ($reviewNotes ? " | Notes: {$reviewNotes}" : ''));
                $msg = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>Reimbursement for <strong>" . htmlspecialchars($empName) . "</strong> marked as <strong>{$newStatus}</strong>.</div>";
            } else {
                $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Failed to update reimbursement.</div>";
            }
        }
    }
}

// ── Fetch data ─────────────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$records      = ReimbursementModel::getAll($filterStatus);
$typeLabels   = ReimbursementModel::types();

$statusCounts = [
    'pending'  => count(array_filter($records ?: ReimbursementModel::getAll(), fn($r) => $r['status'] === 'pending')),
    'approved' => count(array_filter($records ?: ReimbursementModel::getAll(), fn($r) => $r['status'] === 'approved')),
    'paid'     => count(array_filter($records ?: ReimbursementModel::getAll(), fn($r) => $r['status'] === 'paid')),
    'rejected' => count(array_filter($records ?: ReimbursementModel::getAll(), fn($r) => $r['status'] === 'rejected')),
];
if ($filterStatus) {
    $allRecords   = ReimbursementModel::getAll();
    $statusCounts = [
        'pending'  => count(array_filter($allRecords, fn($r) => $r['status'] === 'pending')),
        'approved' => count(array_filter($allRecords, fn($r) => $r['status'] === 'approved')),
        'paid'     => count(array_filter($allRecords, fn($r) => $r['status'] === 'paid')),
        'rejected' => count(array_filter($allRecords, fn($r) => $r['status'] === 'rejected')),
    ];
}

require_once __DIR__ . '/../layouts/admin_header.php';
?>

<?= $msg ?>

<!-- ── Page Title Bar ──────────────────────────────────────── -->
<div class="page-title-bar">
  <i class="fas fa-receipt text-primary"></i>
  <h1>Reimbursements</h1>
</div>

<!-- ── Summary Cards ──────────────────────────────────────── -->
<div class="row mb-3">
  <div class="col-6 col-md-3">
    <a href="reimbursements.php<?= $filterStatus === 'pending' ? '' : '?status=pending' ?>" class="info-box <?= $filterStatus === 'pending' ? 'bg-warning' : '' ?>" style="text-decoration:none;">
      <span class="info-box-icon <?= $filterStatus === 'pending' ? 'bg-warning-dark' : 'bg-warning' ?>">
        <i class="fas fa-hourglass-half"></i>
      </span>
      <div class="info-box-content">
        <span class="info-box-text">Pending</span>
        <span class="info-box-number"><?= $statusCounts['pending'] ?></span>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="reimbursements.php<?= $filterStatus === 'approved' ? '' : '?status=approved' ?>" class="info-box <?= $filterStatus === 'approved' ? 'bg-info' : '' ?>" style="text-decoration:none;">
      <span class="info-box-icon <?= $filterStatus === 'approved' ? 'bg-info-dark' : 'bg-info' ?>">
        <i class="fas fa-check"></i>
      </span>
      <div class="info-box-content">
        <span class="info-box-text">Approved</span>
        <span class="info-box-number"><?= $statusCounts['approved'] ?></span>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="reimbursements.php<?= $filterStatus === 'paid' ? '' : '?status=paid' ?>" class="info-box <?= $filterStatus === 'paid' ? 'bg-success' : '' ?>" style="text-decoration:none;">
      <span class="info-box-icon <?= $filterStatus === 'paid' ? 'bg-success-dark' : 'bg-success' ?>">
        <i class="fas fa-money-bill-wave"></i>
      </span>
      <div class="info-box-content">
        <span class="info-box-text">Paid</span>
        <span class="info-box-number"><?= $statusCounts['paid'] ?></span>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="reimbursements.php<?= $filterStatus === 'rejected' ? '' : '?status=rejected' ?>" class="info-box <?= $filterStatus === 'rejected' ? 'bg-danger' : '' ?>" style="text-decoration:none;">
      <span class="info-box-icon <?= $filterStatus === 'rejected' ? 'bg-danger-dark' : 'bg-danger' ?>">
        <i class="fas fa-times"></i>
      </span>
      <div class="info-box-content">
        <span class="info-box-text">Rejected</span>
        <span class="info-box-number"><?= $statusCounts['rejected'] ?></span>
      </div>
    </a>
  </div>
</div>

<!-- ── Filter Bar ─────────────────────────────────────────── -->
<div class="mb-2 d-flex align-items-center">
  <?php if ($filterStatus): ?>
    <span class="badge badge-secondary px-3 py-2 mr-2">
      Showing: <?= ucfirst($filterStatus) ?>
    </span>
    <a href="reimbursements.php" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-times mr-1"></i>Clear Filter
    </a>
  <?php endif; ?>
</div>

<!-- ── Records Table ──────────────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fas fa-list mr-2"></i>
      Reimbursement Requests <?= $filterStatus ? '— ' . ucfirst($filterStatus) : '' ?>
    </h3>
    <div class="card-tools">
      <span class="badge badge-warning"><?= $statusCounts['pending'] ?> Pending</span>
    </div>
  </div>
  <div class="card-body p-0">
    <?php if (empty($records)): ?>
      <div class="p-5 text-center text-muted">
        <i class="fas fa-receipt fa-3x mb-3 d-block opacity-50"></i>
        No reimbursement requests<?= $filterStatus ? " with status <strong>{$filterStatus}</strong>" : '' ?>.
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="reimbTable">
        <thead class="thead-light">
          <tr>
            <th>Date Filed</th>
            <th>Employee</th>
            <th>Department</th>
            <th>Type</th>
            <th>Receipt Date</th>
            <th>Description</th>
            <th>Receipt No.</th>
            <th>Attachment</th>
            <th class="text-right">Amount</th>
            <th class="text-center">Status</th>
            <th class="text-center no-print">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $r): ?>
          <tr>
            <td><small><?= date('M d, Y', strtotime($r['created_at'])) ?></small></td>
            <td>
              <strong><?= htmlspecialchars($r['employee_name']) ?></strong><br>
              <small class="text-muted"><?= htmlspecialchars($r['employee_no']) ?></small>
            </td>
            <td><?= htmlspecialchars($r['department']) ?></td>
            <td><?= htmlspecialchars($typeLabels[$r['type']] ?? ucfirst($r['type'])) ?></td>
            <td><?= htmlspecialchars($r['receipt_date']) ?></td>
            <td style="max-width:200px; white-space:normal;">
              <?= htmlspecialchars($r['description'] ?? '—') ?>
            </td>
            <td><?= htmlspecialchars($r['receipt_no'] ?? '—') ?></td>
            <td>
              <?php if (!empty($r['receipt_file'])): ?>
                <a href="<?= BASE_URL . '/' . ltrim(htmlspecialchars($r['receipt_file']), '/') ?>"
                   target="_blank" class="btn btn-xs btn-outline-info" title="View/Download Attachment">
                  <i class="fas fa-paperclip mr-1"></i>View
                </a>
              <?php else: ?>
                <span class="text-muted small">None</span>
              <?php endif; ?>
            </td>
            <td class="text-right font-weight-bold">
              &#8369;&nbsp;<?= number_format($r['amount'], 2) ?>
            </td>
            <td class="text-center">
              <?php
              $statusMap = [
                  'pending'  => 'badge-warning',
                  'approved' => 'badge-info',
                  'paid'     => 'badge-success',
                  'rejected' => 'badge-danger',
              ];
              $cls = $statusMap[$r['status']] ?? 'badge-secondary';
              ?>
              <span class="badge <?= $cls ?>"><?= ucfirst($r['status']) ?></span>
              <?php if (!empty($r['review_notes'])): ?>
              <br><small class="text-muted" title="<?= htmlspecialchars($r['review_notes']) ?>">
                <i class="fas fa-comment-alt mr-1"></i><?= mb_strimwidth(htmlspecialchars($r['review_notes']), 0, 30, '…') ?>
              </small>
              <?php endif; ?>
            </td>
            <td class="text-center no-print">
              <?php if ($r['status'] === 'pending'): ?>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-success btn-sm reimb-action-btn"
                        data-id="<?= $r['id'] ?>"
                        data-employee="<?= htmlspecialchars($r['employee_name']) ?>"
                        data-type="<?= htmlspecialchars($typeLabels[$r['type']] ?? $r['type']) ?>"
                        data-amount="<?= number_format($r['amount'], 2) ?>"
                        data-action="approved"
                        title="Approve">
                  <i class="fas fa-check"></i> Approve
                </button>
                <button class="btn btn-danger btn-sm reimb-action-btn"
                        data-id="<?= $r['id'] ?>"
                        data-employee="<?= htmlspecialchars($r['employee_name']) ?>"
                        data-type="<?= htmlspecialchars($typeLabels[$r['type']] ?? $r['type']) ?>"
                        data-amount="<?= number_format($r['amount'], 2) ?>"
                        data-action="rejected"
                        title="Reject">
                  <i class="fas fa-times"></i> Reject
                </button>
              </div>
              <?php elseif ($r['status'] === 'approved'): ?>
              <button class="btn btn-primary btn-sm reimb-action-btn"
                      data-id="<?= $r['id'] ?>"
                      data-employee="<?= htmlspecialchars($r['employee_name']) ?>"
                      data-type="<?= htmlspecialchars($typeLabels[$r['type']] ?? $r['type']) ?>"
                      data-amount="<?= number_format($r['amount'], 2) ?>"
                      data-action="paid"
                      title="Mark as Paid">
                <i class="fas fa-money-bill-wave mr-1"></i>Mark Paid
              </button>
              <?php else: ?>
              <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-light">
          <tr>
            <td colspan="7" class="text-right font-weight-bold">Total Shown:</td>
            <td class="text-right font-weight-bold text-success">
              &#8369;&nbsp;<?= number_format(array_sum(array_column($records, 'amount')), 2) ?>
            </td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Review Modal ───────────────────────────────────────── -->
<div class="modal fade" id="reimbReviewModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" id="reimbModalHeader">
        <h5 class="modal-title" id="reimbModalTitle">
          <i class="fas fa-check-circle mr-2"></i>Confirm Action
        </h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form method="POST" id="reimbReviewForm">
        <input type="hidden" name="review_reimbursement" value="1">
        <input type="hidden" name="reimb_id"    id="reimbId">
        <input type="hidden" name="new_status"  id="reimbNewStatus">
        <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="modal-body">
          <div class="mb-3">
            <table class="table table-sm table-borderless mb-0">
              <tr>
                <td class="text-muted" width="35%">Employee:</td>
                <td><strong id="reimbModalEmp"></strong></td>
              </tr>
              <tr>
                <td class="text-muted">Type:</td>
                <td id="reimbModalType"></td>
              </tr>
              <tr>
                <td class="text-muted">Amount:</td>
                <td class="font-weight-bold text-success" id="reimbModalAmount"></td>
              </tr>
            </table>
          </div>
          <div class="form-group">
            <label class="font-weight-bold" id="reimbNotesLabel">Notes / Reason</label>
            <textarea name="review_notes" id="reimbNotes" class="form-control" rows="3"
                      maxlength="255"
                      placeholder="Optional notes for approval, or required reason for rejection..."></textarea>
            <small class="text-muted" id="reimbNotesHint">Optional for approval; required for rejection.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i>Cancel
          </button>
          <button type="submit" class="btn" id="reimbSubmitBtn">
            <i class="fas fa-check mr-1"></i>Confirm
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraJs = <<<JS
// ── Reimbursement Action Modal ──────────────────────────────────────
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.reimb-action-btn');
    if (!btn) return;
    e.preventDefault();

    var action   = btn.dataset.action;
    var empName  = btn.dataset.employee;
    var type     = btn.dataset.type;
    var amount   = btn.dataset.amount;
    var id       = btn.dataset.id;

    document.getElementById('reimbId').value        = id;
    document.getElementById('reimbNewStatus').value = action;
    document.getElementById('reimbModalEmp').textContent    = empName;
    document.getElementById('reimbModalType').textContent   = type;
    document.getElementById('reimbModalAmount').textContent = '₱ ' + amount;
    document.getElementById('reimbNotes').value     = '';
    document.getElementById('reimbNotes').required  = (action === 'rejected');

    var header = document.getElementById('reimbModalHeader');
    var title  = document.getElementById('reimbModalTitle');
    var btn2   = document.getElementById('reimbSubmitBtn');
    var hint   = document.getElementById('reimbNotesHint');

    // Reset classes
    header.className = 'modal-header';

    if (action === 'approved') {
        header.classList.add('bg-success', 'text-white');
        title.innerHTML  = '<i class="fas fa-check-circle mr-2"></i>Approve Reimbursement';
        btn2.className   = 'btn btn-success';
        btn2.innerHTML   = '<i class="fas fa-check mr-1"></i>Approve';
        hint.textContent = 'Optional: add notes for the employee.';
    } else if (action === 'rejected') {
        header.classList.add('bg-danger', 'text-white');
        title.innerHTML  = '<i class="fas fa-times-circle mr-2"></i>Reject Reimbursement';
        btn2.className   = 'btn btn-danger';
        btn2.innerHTML   = '<i class="fas fa-times mr-1"></i>Reject';
        hint.textContent = 'Required: state the reason for rejection.';
    } else if (action === 'paid') {
        header.classList.add('bg-primary', 'text-white');
        title.innerHTML  = '<i class="fas fa-money-bill-wave mr-2"></i>Mark as Paid';
        btn2.className   = 'btn btn-primary';
        btn2.innerHTML   = '<i class="fas fa-money-bill-wave mr-1"></i>Mark Paid';
        hint.textContent = 'Optional: add payment reference or notes.';
    }

    $('#reimbReviewModal').modal('show');
});
JS;

require_once __DIR__ . '/../layouts/admin_footer.php';
?>