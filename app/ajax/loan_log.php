<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/config.php';
if (!in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) {
    http_response_code(403); exit;
}

$loanId = (int)($_GET['loan_id'] ?? 0);
if (!$loanId) {
    echo '<div class="alert alert-warning">No loan ID specified.</div>';
    exit;
}

$loan = Model::findLoanById($loanId);
if (!$loan) {
    echo '<div class="alert alert-danger">Loan not found.</div>';
    exit;
}

$log = Model::getLoanDeductionLogByLoan($loanId);
?>
<div class="table-responsive">
  <table class="table table-bordered table-sm">
    <thead class="thead-light">
      <tr>
        <th>Period</th>
        <th class="text-right">Amount Deducted</th>
        <th class="text-right">Balance Before</th>
        <th class="text-right">Balance After</th>
        <th>Date Processed</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($log)): ?>
        <tr><td colspan="5" class="text-center text-muted py-3">No deductions recorded yet for this loan.</td></tr>
      <?php else: ?>
        <?php foreach ($log as $entry): ?>
          <tr>
            <td><?= htmlspecialchars(Model::periodLabel($entry['period'])) ?></td>
            <td class="text-right text-danger">&#8369; <?= number_format((float)$entry['amount_deducted'], 2) ?></td>
            <td class="text-right">&#8369; <?= number_format((float)$entry['balance_before'], 2) ?></td>
            <td class="text-right <?= (float)$entry['balance_after'] <= 0 ? 'text-success font-weight-bold' : '' ?>">
              &#8369; <?= number_format((float)$entry['balance_after'], 2) ?>
              <?php if ((float)$entry['balance_after'] <= 0): ?>
                <span class="badge badge-success ml-1">Paid</span>
              <?php endif; ?>
            </td>
            <td><?= date('M d, Y', strtotime($entry['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<div class="row px-3 pb-2">
  <div class="col-6">
    <small class="text-muted">Loan Amount: <strong>&#8369; <?= number_format((float)$loan['loan_amount'], 2) ?></strong></small>
  </div>
  <div class="col-6 text-right">
    <small class="text-muted">Remaining Balance: <strong class="<?= (float)$loan['remaining_balance'] <= 0 ? 'text-success' : 'text-warning' ?>">
      &#8369; <?= number_format((float)$loan['remaining_balance'], 2) ?>
    </strong></small>
  </div>
</div>
