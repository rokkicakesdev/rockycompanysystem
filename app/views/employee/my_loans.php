<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../config/config.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== ROLE_EMPLOYEE) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$pageTitle  = 'My Loans & Cash Advances';
$breadcrumb = 'My Loans';
require_once __DIR__ . '/../layouts/employee_header.php';

$employeeId = (int)($_SESSION['employee_id'] ?? 0);
if (!$employeeId) {
    echo '<div class="alert alert-warning m-3">Employee profile not linked to your account.</div>';
    require_once __DIR__ . '/../layouts/employee_footer.php';
    exit;
}

LoanModel::ensureTable();
$myLoans   = Model::getLoansByEmployee($employeeId);
$loanTypes = Model::getLoanTypes();

$statusColors = [
    'active'     => 'primary',
    'fully_paid' => 'success',
    'cancelled'  => 'secondary',
];
?>

<div class="container-fluid">
  <h4 class="mb-3"><i class="fas fa-hand-holding-usd mr-2 text-primary"></i>My Loans &amp; Cash Advances</h4>

  <?php if (empty($myLoans)): ?>
    <div class="alert alert-info">
      <i class="fas fa-info-circle mr-2"></i>You have no loan or cash advance records on file.
    </div>
  <?php else: ?>
    <?php foreach ($myLoans as $loan): ?>
      <?php
        $color = $statusColors[$loan['status']] ?? 'secondary';
        $pct   = $loan['loan_amount'] > 0
            ? max(0, min(100, round((1 - $loan['remaining_balance'] / $loan['loan_amount']) * 100)))
            : 100;
        $paidAmt = (float)$loan['loan_amount'] - (float)$loan['remaining_balance'];
      ?>
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0 font-weight-bold">
            <i class="fas fa-file-invoice-dollar mr-2"></i>
            <?= htmlspecialchars($loanTypes[$loan['loan_type']] ?? $loan['loan_type']) ?>
          </h6>
          <span class="badge badge-<?= $color ?> px-3 py-2">
            <?= ucfirst(str_replace('_', ' ', $loan['status'])) ?>
          </span>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-3 col-6 mb-3">
              <div class="text-muted small">Loan Amount</div>
              <div class="font-weight-bold">&#8369; <?= number_format((float)$loan['loan_amount'], 2) ?></div>
            </div>
            <div class="col-md-3 col-6 mb-3">
              <div class="text-muted small">Monthly Deduction</div>
              <div class="font-weight-bold">&#8369; <?= number_format((float)$loan['monthly_deduction'], 2) ?></div>
            </div>
            <div class="col-md-3 col-6 mb-3">
              <div class="text-muted small">Per Cutoff Deduction</div>
              <div class="font-weight-bold">&#8369; <?= number_format((float)$loan['cutoff_deduction'], 2) ?></div>
            </div>
            <div class="col-md-3 col-6 mb-3">
              <div class="text-muted small">Start Date</div>
              <div class="font-weight-bold"><?= date('M d, Y', strtotime($loan['start_date'])) ?></div>
            </div>
          </div>

          <div class="mb-2">
            <div class="d-flex justify-content-between small mb-1">
              <span>Amount Paid: <strong>&#8369; <?= number_format($paidAmt, 2) ?></strong></span>
              <span>Remaining: <strong class="text-<?= $color ?>">&#8369; <?= number_format((float)$loan['remaining_balance'], 2) ?></strong></span>
            </div>
            <div class="progress" style="height:12px">
              <div class="progress-bar bg-<?= $color ?>"
                   role="progressbar"
                   style="width:<?= $pct ?>%"
                   title="<?= $pct ?>% paid">
                <?= $pct ?>%
              </div>
            </div>
          </div>

          <?php if (!empty($loan['reference_no'])): ?>
            <div class="text-muted small mt-2">
              <i class="fas fa-hashtag mr-1"></i>Ref: <?= htmlspecialchars($loan['reference_no']) ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($loan['notes'])): ?>
            <div class="text-muted small mt-1">
              <i class="fas fa-sticky-note mr-1"></i><?= htmlspecialchars($loan['notes']) ?>
            </div>
          <?php endif; ?>

          <?php
            $deductLog = Model::getLoanDeductionLogByLoan((int)$loan['id']);
          ?>
          <?php if (!empty($deductLog)): ?>
            <div class="mt-3">
              <button class="btn btn-outline-secondary btn-sm" type="button"
                data-toggle="collapse"
                data-target="#log-<?= $loan['id'] ?>">
                <i class="fas fa-history mr-1"></i>Deduction History (<?= count($deductLog) ?> records)
              </button>
              <div class="collapse mt-2" id="log-<?= $loan['id'] ?>">
                <div class="table-responsive">
                  <table class="table table-sm table-bordered mb-0">
                    <thead class="thead-light">
                      <tr>
                        <th>Period</th>
                        <th class="text-right">Deducted</th>
                        <th class="text-right">Balance After</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($deductLog as $entry): ?>
                        <tr>
                          <td><?= htmlspecialchars(Model::periodLabel($entry['period'])) ?></td>
                          <td class="text-right text-danger">&#8369; <?= number_format((float)$entry['amount_deducted'], 2) ?></td>
                          <td class="text-right <?= (float)$entry['balance_after'] <= 0 ? 'text-success font-weight-bold' : '' ?>">
                            &#8369; <?= number_format((float)$entry['balance_after'], 2) ?>
                            <?php if ((float)$entry['balance_after'] <= 0): ?>
                              <span class="badge badge-success">Paid</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layouts/employee_footer.php'; ?>
