<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../config/config.php';
if (!in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$pageTitle  = 'Loans & Cash Advances';
$breadcrumb = 'Loans & Cash Advances';
$activeMenu = 'loans';
require_once __DIR__ . '/../layouts/admin_header.php';

LoanModel::ensureTable();

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['create_loan'])) {
        $required = ['employee_id', 'loan_type', 'loan_amount', 'monthly_deduction', 'start_date'];
        $valid = true;
        foreach ($required as $f) {
            if (empty($_POST[$f])) { $valid = false; break; }
        }
        if (!$valid || (float)$_POST['loan_amount'] <= 0 || (float)$_POST['monthly_deduction'] <= 0) {
            $flash = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>Please fill in all required fields with valid amounts.</div>';
        } else {
            $ok = Model::createLoan([
                'employee_id'       => (int)$_POST['employee_id'],
                'loan_type'         => $_POST['loan_type'],
                'loan_amount'       => (float)$_POST['loan_amount'],
                'monthly_deduction' => (float)$_POST['monthly_deduction'],
                'start_date'        => $_POST['start_date'],
                'reference_no'      => trim($_POST['reference_no'] ?? ''),
                'notes'             => trim($_POST['notes'] ?? ''),
            ], (int)$_SESSION['user_id']);
            if ($ok) {
                Model::logActivity($_SESSION['user_id'], 'CREATE_LOAN',
                    'Created loan for employee ID:' . (int)$_POST['employee_id']
                    . ' | Type: ' . $_POST['loan_type']
                    . ' | Amount: ₱' . number_format((float)$_POST['loan_amount'], 2));
                $flash = '<div class="alert alert-success"><i class="fas fa-check-circle mr-2"></i>Loan record created successfully.</div>';
            } else {
                $flash = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>Failed to create loan record.</div>';
            }
        }
    }

    if (isset($_POST['update_loan'])) {
        $loanId = (int)($_POST['loan_id'] ?? 0);
        $loan   = $loanId ? Model::findLoanById($loanId) : null;
        if (!$loan) {
            $flash = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>Loan not found.</div>';
        } else {
            $ok = Model::updateLoan($loanId, [
                'loan_type'         => $_POST['loan_type'],
                'loan_amount'       => (float)$_POST['loan_amount'],
                'monthly_deduction' => (float)$_POST['monthly_deduction'],
                'remaining_balance' => (float)$_POST['remaining_balance'],
                'start_date'        => $_POST['start_date'],
                'status'            => $_POST['status'],
                'reference_no'      => trim($_POST['reference_no'] ?? ''),
                'notes'             => trim($_POST['notes'] ?? ''),
            ]);
            if ($ok) {
                Model::logActivity($_SESSION['user_id'], 'UPDATE_LOAN',
                    'Updated loan ID:' . $loanId . ' for ' . $loan['employee_name']);
                $flash = '<div class="alert alert-success"><i class="fas fa-check-circle mr-2"></i>Loan record updated.</div>';
            } else {
                $flash = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>Failed to update loan record.</div>';
            }
        }
    }

    if (isset($_POST['delete_loan'])) {
        $loanId = (int)($_POST['loan_id'] ?? 0);
        $loan   = $loanId ? Model::findLoanById($loanId) : null;
        if ($loan) {
            Model::deleteLoan($loanId);
            Model::logActivity($_SESSION['user_id'], 'DELETE_LOAN',
                'Deleted loan ID:' . $loanId . ' for ' . $loan['employee_name']);
            $flash = '<div class="alert alert-success"><i class="fas fa-check-circle mr-2"></i>Loan record deleted.</div>';
        }
    }
}

$allLoans    = Model::getAllLoans();
$allEmployees = Model::getAllEmployees();
$loanTypes   = Model::getLoanTypes();
$stats       = Model::getLoanSummaryStats();

$statusColors = [
    'active'     => 'primary',
    'fully_paid' => 'success',
    'cancelled'  => 'secondary',
];

$filterStatus = $_GET['status'] ?? '';
$filterEmp    = (int)($_GET['emp'] ?? 0);

$filteredLoans = array_filter($allLoans, function($l) use ($filterStatus, $filterEmp) {
    if ($filterStatus && $l['status'] !== $filterStatus) return false;
    if ($filterEmp && (int)$l['employee_id'] !== $filterEmp) return false;
    return true;
});
?>

<?= $flash ?>

<div class="row">
  <div class="col-md-3 col-6">
    <div class="small-box bg-info">
      <div class="inner">
        <h3><?= (int)($stats['active_count'] ?? 0) ?></h3>
        <p>Active Loans</p>
      </div>
      <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="small-box bg-success">
      <div class="inner">
        <h3><?= (int)($stats['paid_count'] ?? 0) ?></h3>
        <p>Fully Paid</p>
      </div>
      <div class="icon"><i class="fas fa-check-circle"></i></div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="small-box bg-warning">
      <div class="inner">
        <h3>&#8369; <?= number_format((float)($stats['total_outstanding'] ?? 0), 2) ?></h3>
        <p>Total Outstanding</p>
      </div>
      <div class="icon"><i class="fas fa-balance-scale"></i></div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="small-box bg-secondary">
      <div class="inner">
        <h3>&#8369; <?= number_format((float)($stats['total_amount'] ?? 0), 2) ?></h3>
        <p>Total Loan Amount</p>
      </div>
      <div class="icon"><i class="fas fa-coins"></i></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-hand-holding-usd mr-2"></i>Loan & Cash Advance Records</h3>
    <div class="card-tools">
      <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createLoanModal">
        <i class="fas fa-plus mr-1"></i>Add Loan
      </button>
    </div>
  </div>
  <div class="card-body">
    <div class="row mb-3">
      <div class="col-md-4">
        <select class="form-control form-control-sm" id="filterStatus" onchange="applyFilter()">
          <option value="">All Statuses</option>
          <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="fully_paid" <?= $filterStatus === 'fully_paid' ? 'selected' : '' ?>>Fully Paid</option>
          <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
      </div>
      <div class="col-md-4">
        <select class="form-control form-control-sm" id="filterEmp" onchange="applyFilter()">
          <option value="">All Employees</option>
          <?php foreach ($allEmployees as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $filterEmp === (int)$e['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($e['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered table-hover table-sm" id="loansTable">
        <thead class="thead-light">
          <tr>
            <th>Employee</th>
            <th>Loan Type</th>
            <th>Loan Amount</th>
            <th>Monthly Deduction</th>
            <th>Cutoff Deduction</th>
            <th>Remaining Balance</th>
            <th>Start Date</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($filteredLoans)): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No loan records found.</td></tr>
          <?php else: ?>
            <?php foreach ($filteredLoans as $loan): ?>
              <?php
                $color    = $statusColors[$loan['status']] ?? 'secondary';
                $pct      = $loan['loan_amount'] > 0
                    ? max(0, min(100, round((1 - $loan['remaining_balance'] / $loan['loan_amount']) * 100)))
                    : 100;
              ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($loan['employee_name']) ?></strong><br>
                  <small class="text-muted"><?= htmlspecialchars($loan['emp_code'] ?? '') ?></small>
                </td>
                <td><?= htmlspecialchars($loanTypes[$loan['loan_type']] ?? $loan['loan_type']) ?></td>
                <td class="text-right">&#8369; <?= number_format((float)$loan['loan_amount'], 2) ?></td>
                <td class="text-right">&#8369; <?= number_format((float)$loan['monthly_deduction'], 2) ?>/mo</td>
                <td class="text-right">&#8369; <?= number_format((float)$loan['cutoff_deduction'], 2) ?>/cutoff</td>
                <td>
                  <div class="d-flex align-items-center">
                    <div class="mr-2" style="flex:1">
                      <div class="progress progress-xs">
                        <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
                      </div>
                    </div>
                    <span class="text-right" style="min-width:90px">&#8369; <?= number_format((float)$loan['remaining_balance'], 2) ?></span>
                  </div>
                </td>
                <td><?= htmlspecialchars($loan['start_date']) ?></td>
                <td><span class="badge badge-<?= $color ?>"><?= ucfirst(str_replace('_', ' ', $loan['status'])) ?></span></td>
                <td>
                  <button class="btn btn-xs btn-info mr-1"
                    onclick="openEditLoan(<?= htmlspecialchars(json_encode($loan)) ?>)"
                    title="Edit">
                    <i class="fas fa-edit"></i>
                  </button>
                  <button class="btn btn-xs btn-outline-info mr-1"
                    onclick="viewLoanLog(<?= (int)$loan['id'] ?>, '<?= htmlspecialchars($loan['employee_name']) ?>')"
                    title="View Deduction History">
                    <i class="fas fa-history"></i>
                  </button>
                  <button class="btn btn-xs btn-danger"
                    onclick="confirmDeleteLoan(<?= (int)$loan['id'] ?>, '<?= htmlspecialchars(addslashes($loan['employee_name'])) ?>')"
                    title="Delete">
                    <i class="fas fa-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Create Loan Modal -->
<div class="modal fade" id="createLoanModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="create_loan" value="1">
        <div class="modal-header bg-primary">
          <h5 class="modal-title text-white"><i class="fas fa-plus mr-2"></i>Add Loan / Cash Advance</h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Employee <span class="text-danger">*</span></label>
                <select name="employee_id" class="form-control" required>
                  <option value="">-- Select Employee --</option>
                  <?php foreach ($allEmployees as $e): ?>
                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Loan Type <span class="text-danger">*</span></label>
                <select name="loan_type" class="form-control" required>
                  <?php foreach ($loanTypes as $key => $label): ?>
                    <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Loan Amount (₱) <span class="text-danger">*</span></label>
                <input type="number" name="loan_amount" class="form-control" step="0.01" min="1" required placeholder="0.00" id="createLoanAmount">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Monthly Deduction (₱) <span class="text-danger">*</span></label>
                <input type="number" name="monthly_deduction" class="form-control" step="0.01" min="1" required placeholder="0.00" id="createMonthlyDed" oninput="updateCreateCutoff()">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Per Cutoff Deduction</label>
                <input type="text" class="form-control" id="createCutoffPreview" readonly placeholder="auto-computed">
                <small class="text-muted">= Monthly ÷ 2</small>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Start Date <span class="text-danger">*</span></label>
                <input type="date" name="start_date" class="form-control" required value="<?= date('Y-m-d') ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Reference / Control No.</label>
                <input type="text" name="reference_no" class="form-control" placeholder="e.g. SSS-2026-001">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Estimated Months to Pay</label>
                <input type="text" class="form-control" id="createMonthsPreview" readonly placeholder="—">
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes about this loan..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Loan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Loan Modal -->
<div class="modal fade" id="editLoanModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="update_loan" value="1">
        <input type="hidden" name="loan_id" id="editLoanId">
        <div class="modal-header bg-info">
          <h5 class="modal-title text-white"><i class="fas fa-edit mr-2"></i>Edit Loan Record</h5>
          <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <p class="mb-3"><strong>Employee:</strong> <span id="editLoanEmployee" class="text-primary"></span></p>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Loan Type</label>
                <select name="loan_type" id="editLoanType" class="form-control">
                  <?php foreach ($loanTypes as $key => $label): ?>
                    <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Status</label>
                <select name="status" id="editLoanStatus" class="form-control">
                  <option value="active">Active</option>
                  <option value="fully_paid">Fully Paid</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Loan Amount (₱)</label>
                <input type="number" name="loan_amount" id="editLoanAmount" class="form-control" step="0.01" min="0" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Monthly Deduction (₱)</label>
                <input type="number" name="monthly_deduction" id="editMonthlyDed" class="form-control" step="0.01" min="0" required oninput="updateEditCutoff()">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Per Cutoff Deduction</label>
                <input type="text" class="form-control" id="editCutoffPreview" readonly>
                <small class="text-muted">= Monthly ÷ 2</small>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Remaining Balance (₱)</label>
                <input type="number" name="remaining_balance" id="editRemainingBalance" class="form-control" step="0.01" min="0" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="start_date" id="editStartDate" class="form-control" required>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Reference / Control No.</label>
            <input type="text" name="reference_no" id="editReferenceNo" class="form-control">
          </div>
          <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" id="editNotes" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-info"><i class="fas fa-save mr-1"></i>Update Loan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Loan Form (hidden) -->
<form method="POST" id="deleteLoanForm">
  <input type="hidden" name="delete_loan" value="1">
  <input type="hidden" name="loan_id" id="deleteLoanId">
</form>

<!-- Loan Log Modal -->
<div class="modal fade" id="loanLogModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-dark">
        <h5 class="modal-title text-white"><i class="fas fa-history mr-2"></i>Deduction History — <span id="loanLogName"></span></h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" id="loanLogBody">
        <div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
      </div>
    </div>
  </div>
</div>

<script>
function applyFilter() {
    var status = document.getElementById('filterStatus').value;
    var emp    = document.getElementById('filterEmp').value;
    var url    = '<?= BASE_URL ?>/app/views/admin/loans.php?';
    if (status) url += 'status=' + encodeURIComponent(status) + '&';
    if (emp)    url += 'emp=' + encodeURIComponent(emp) + '&';
    window.location.href = url;
}

function updateCreateCutoff() {
    var monthly = parseFloat(document.getElementById('createMonthlyDed').value) || 0;
    var amount  = parseFloat(document.getElementById('createLoanAmount').value)  || 0;
    var cutoff  = (monthly / 2).toFixed(2);
    document.getElementById('createCutoffPreview').value = '₱ ' + parseFloat(cutoff).toLocaleString('en-PH', {minimumFractionDigits:2});
    if (monthly > 0 && amount > 0) {
        var months = Math.ceil(amount / monthly);
        document.getElementById('createMonthsPreview').value = months + ' month(s)';
    } else {
        document.getElementById('createMonthsPreview').value = '—';
    }
}

function updateEditCutoff() {
    var monthly = parseFloat(document.getElementById('editMonthlyDed').value) || 0;
    var cutoff  = (monthly / 2).toFixed(2);
    document.getElementById('editCutoffPreview').value = '₱ ' + parseFloat(cutoff).toLocaleString('en-PH', {minimumFractionDigits:2});
}

function openEditLoan(loan) {
    document.getElementById('editLoanId').value          = loan.id;
    document.getElementById('editLoanEmployee').textContent = loan.employee_name;
    document.getElementById('editLoanType').value        = loan.loan_type;
    document.getElementById('editLoanStatus').value      = loan.status;
    document.getElementById('editLoanAmount').value      = parseFloat(loan.loan_amount).toFixed(2);
    document.getElementById('editMonthlyDed').value      = parseFloat(loan.monthly_deduction).toFixed(2);
    document.getElementById('editRemainingBalance').value = parseFloat(loan.remaining_balance).toFixed(2);
    document.getElementById('editStartDate').value       = loan.start_date;
    document.getElementById('editReferenceNo').value     = loan.reference_no || '';
    document.getElementById('editNotes').value           = loan.notes || '';
    updateEditCutoff();
    $('#editLoanModal').modal('show');
}

function confirmDeleteLoan(id, name) {
    if (confirm('Delete the loan record for ' + name + '?\n\nThis cannot be undone.')) {
        document.getElementById('deleteLoanId').value = id;
        document.getElementById('deleteLoanForm').submit();
    }
}

function viewLoanLog(loanId, name) {
    document.getElementById('loanLogName').textContent = name;
    document.getElementById('loanLogBody').innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    $('#loanLogModal').modal('show');

    fetch('<?= BASE_URL ?>/app/ajax/loan_log.php?loan_id=' + loanId)
        .then(function(r){ return r.text(); })
        .then(function(html){ document.getElementById('loanLogBody').innerHTML = html; })
        .catch(function(){ document.getElementById('loanLogBody').innerHTML = '<div class="alert alert-danger">Failed to load.</div>'; });
}
</script>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
