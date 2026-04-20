<?php
// app/views/employee/my_reimbursements.php
// Employee self-service: submit and track reimbursement requests (with receipt upload).

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header('Location: ' . BASE_URL . 'index.php?error=access_denied');
    exit;
}

$pageTitle  = 'My Reimbursements';
$employeeId = (int)($_SESSION['employee_id'] ?? 0);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../core/Model.php';
require_once __DIR__ . '/../../../core/models/ReimbursementModel.php';
require_once __DIR__ . '/../../../core/FileUploadService.php';

if (!defined('UPLOAD_BASE_DIR')) {
    define('UPLOAD_BASE_DIR', rtrim(dirname(__DIR__, 3), '/') . '/uploads');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$msg        = '';

// ── POST: Submit new reimbursement ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reimbursement'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>Invalid security token. Please refresh.</div>';
    } else {
        $type        = trim($_POST['type']         ?? '');
        $amount      = (float)($_POST['amount']    ?? 0);
        $receiptDate = trim($_POST['receipt_date'] ?? '');
        $description = trim($_POST['description']  ?? '');
        $receiptNo   = trim($_POST['receipt_no']   ?? '');

        $typeLabels = ReimbursementModel::types();

        if (!array_key_exists($type, $typeLabels)) {
            $msg = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>Please select a valid reimbursement type.</div>';
        } elseif ($amount <= 0) {
            $msg = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>Amount must be greater than zero.</div>';
        } elseif (empty($receiptDate)) {
            $msg = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>Receipt date is required.</div>';
        } else {
            // Handle optional file upload
            $receiptFilePath = null;
            $fileUploaded    = !empty($_FILES['receipt_file']['name']);

            if ($fileUploaded) {
                $destDir = UPLOAD_BASE_DIR . '/reimbursements/' . $employeeId;
                $result  = FileUploadService::upload(
                    $_FILES['receipt_file'],
                    FileUploadService::EMPLOYEE_DOC,
                    $destDir,
                    'uploads/reimbursements/' . $employeeId
                );
                if (!$result->ok) {
                    $msg = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>'
                         . htmlspecialchars($result->error) . '</div>';
                    $fileUploaded = false; // prevent proceeding
                } else {
                    $receiptFilePath = $result->relativePath;
                }
            }

            // Only proceed if no file error
            if (!$fileUploaded || $receiptFilePath !== null || !$fileUploaded) {
                if (empty($msg)) {
                    $created = ReimbursementModel::create([
                        'employee_id'  => $employeeId,
                        'type'         => $type,
                        'amount'       => $amount,
                        'receipt_date' => $receiptDate,
                        'description'  => $description,
                        'receipt_no'   => $receiptNo,
                        'receipt_file' => $receiptFilePath,
                    ]);

                    if ($created) {
                        Model::log($_SESSION['user_id'], 'SUBMIT_REIMBURSEMENT',
                            "Employee ID:{$employeeId} submitted reimbursement: {$typeLabels[$type]} ₱"
                            . number_format($amount, 2)
                            . ($receiptFilePath ? ' [with attachment]' : ''));
                        $msg = '<div class="alert alert-success alert-auto-dismiss">'
                             . '<i class="fas fa-check-circle mr-2"></i>'
                             . 'Reimbursement request submitted successfully. Awaiting approval.</div>';
                    } else {
                        $msg = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>Failed to submit request. Please try again.</div>';
                    }
                }
            }
        }
    }
}

$records    = ReimbursementModel::getByEmployee($employeeId);
$typeLabels = ReimbursementModel::types();

// Build the base URL for reimbursement attachments (served via direct path)
$uploadBaseUrl = BASE_URL . '/uploads/reimbursements/' . $employeeId . '/';

require_once __DIR__ . '/../layouts/employee_header.php';
?>

<div class="page-title-bar">
  <i class="fas fa-receipt text-primary"></i>
  <h1>My Reimbursements <small class="text-muted" style="font-size:.6em;">Submit &amp; track expense reimbursements</small></h1>
  <div class="ml-auto">
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#submitReimbModal">
      <i class="fas fa-plus mr-1"></i> Submit Request
    </button>
  </div>
</div>

<?= $msg ?>

<!-- ── Reimbursement Table ──────────────────────────────────────── -->
<div class="card">
  <div class="card-body table-responsive p-0">
    <?php if (empty($records)): ?>
      <div class="alert alert-info text-center m-4">
        <i class="fas fa-info-circle mr-2"></i>
        No reimbursement requests yet. Click <strong>Submit Request</strong> to get started.
      </div>
    <?php else: ?>
      <table class="table table-hover mb-0">
        <thead class="thead-light">
          <tr>
            <th>Date Filed</th>
            <th>Type</th>
            <th>Receipt Date</th>
            <th>Description</th>
            <th>Receipt No.</th>
            <th>Attachment</th>
            <th class="text-right">Amount</th>
            <th class="text-center">Status</th>
            <th>Review Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $r): ?>
          <tr>
            <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
            <td><?= htmlspecialchars($typeLabels[$r['type']] ?? ucfirst($r['type'])) ?></td>
            <td><?= htmlspecialchars($r['receipt_date']) ?></td>
            <td><?= htmlspecialchars($r['description'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['receipt_no'] ?? '—') ?></td>
            <td>
              <?php if (!empty($r['receipt_file'])): ?>
                <a href="<?= BASE_URL . '/' . ltrim($r['receipt_file'], '/') ?>"
                   target="_blank" class="btn btn-xs btn-outline-secondary" title="View Attachment">
                  <i class="fas fa-paperclip mr-1"></i>View
                </a>
              <?php else: ?>
                <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
            <td class="text-right font-weight-bold">&#8369;&nbsp;<?= number_format($r['amount'], 2) ?></td>
            <td class="text-center">
              <?php
              $statusMap = [
                  'pending'  => ['badge-warning',  'Pending'],
                  'approved' => ['badge-info',     'Approved'],
                  'rejected' => ['badge-danger',   'Rejected'],
                  'paid'     => ['badge-success',  'Paid'],
              ];
              [$cls, $lbl] = $statusMap[$r['status']] ?? ['badge-secondary', ucfirst($r['status'])];
              ?>
              <span class="badge <?= $cls ?>"><?= $lbl ?></span>
            </td>
            <td class="text-muted" style="max-width:200px; white-space:normal;">
              <?= htmlspecialchars($r['review_notes'] ?? '—') ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<!-- ── Submit Reimbursement Modal ──────────────────────────────── -->
<div class="modal fade" id="submitReimbModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-receipt mr-2"></i>Submit Reimbursement Request</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="submit_reimbursement" value="1">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="modal-body">
          <div class="alert alert-info py-2">
            <i class="fas fa-info-circle mr-1"></i>
            You may attach a digital copy of your receipt (PDF, JPG, PNG — max 10 MB).
            Physical receipts should still be submitted to HR after approval.
          </div>
          <div class="form-row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold">Expense Type <span class="text-danger">*</span></label>
                <select name="type" class="form-control" required>
                  <option value="">— Select Type —</option>
                  <?php foreach ($typeLabels as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold">Receipt Date <span class="text-danger">*</span></label>
                <input type="date" name="receipt_date" class="form-control" required
                       max="<?= date('Y-m-d') ?>">
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold">Amount (₱) <span class="text-danger">*</span></label>
                <div class="input-group">
                  <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                  <input type="number" name="amount" class="form-control"
                         step="0.01" min="0.01" placeholder="0.00" required>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold">Receipt / OR Number</label>
                <input type="text" name="receipt_no" class="form-control"
                       maxlength="60" placeholder="e.g. OR-2026-0001">
              </div>
            </div>
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Description / Purpose</label>
            <textarea name="description" class="form-control" rows="3" maxlength="255"
                      placeholder="Briefly describe the expense and business purpose..."></textarea>
          </div>
          <!-- Receipt File Upload -->
          <div class="form-group">
            <label class="font-weight-bold">
              <i class="fas fa-paperclip mr-1 text-secondary"></i>
              Attach Receipt / Document
              <small class="text-muted font-weight-normal ml-1">PDF, JPG, PNG, WEBP — max 10 MB (optional)</small>
            </label>
            <div class="custom-file">
              <input type="file" class="custom-file-input" id="receiptFileInput"
                     name="receipt_file" accept=".pdf,.jpg,.jpeg,.png,.webp">
              <label class="custom-file-label" for="receiptFileInput">Choose file…</label>
            </div>
            <small class="form-text text-muted">
              <i class="fas fa-shield-alt mr-1 text-success"></i>
              Your file is stored securely and only accessible to HR/Admin.
            </small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i>Cancel
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane mr-1"></i>Submit Request
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Update custom-file label with selected filename
document.getElementById('receiptFileInput').addEventListener('change', function() {
    var label = this.nextElementSibling;
    label.textContent = this.files.length ? this.files[0].name : 'Choose file…';
});
</script>

<?php require_once __DIR__ . '/../layouts/employee_footer.php'; ?>