<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../config/config.php';
// Admin and management only
if (!in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$pageTitle  = 'Payslips';
$breadcrumb = 'Payslips';
$activeMenu = 'payslip';
require_once __DIR__ . '/../layouts/admin_header.php';

$employees      = Model::getAllEmployees();
$selectedEmpId  = (int)($_GET['emp'] ?? 0);
$selectedEmp = $selectedEmpId ? Model::findEmployeeById($selectedEmpId) : null;

// Build period dropdown from actual DB records (latest first)
$rawPeriods = Model::getPayrollPeriods();
$allPeriods = [];
foreach ($rawPeriods as $p) {
    $allPeriods[$p] = Model::periodLabel($p);
}

// Default to latest available period
$selectedPeriod = $_GET['period'] ?? array_key_first($allPeriods);

// Load the actual payroll record from DB for this employee + period
$payrollRecord = null;
if ($selectedEmp && $selectedPeriod) {
    $records = Model::getPayrollByEmployee($selectedEmpId);
    foreach ($records as $r) {
        if ($r['period'] === $selectedPeriod) {
            $payrollRecord = $r;
            break;
        }
    }
}
?>

<div class="row">
  <!-- Left: selector -->
  <div class="col-md-4 no-print">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-search mr-2"></i>Select Payslip</h3>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label>Employee</label>
          <select class="form-control" id="empSelect">
            <option value="">-- Select Employee --</option>
            <?php foreach($employees as $e): ?>
              <option value="<?= $e['id'] ?>" <?= $selectedEmpId===$e['id']?'selected':'' ?>>
                <?= htmlspecialchars($e['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Period</label>
          <select class="form-control" id="periodSelect">
            <?php foreach($allPeriods as $val => $label): ?>
              <option value="<?= $val ?>" <?= $selectedPeriod===$val?'selected':'' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary btn-block" onclick="loadPayslip()">
          <i class="fas fa-eye mr-1"></i> View Payslip
        </button>
      </div>
    </div>

    <!-- All Payroll History -->
    <div class="card mt-2">
      <div class="card-header">
        <h3 class="card-title" class="payslip-employee-table"><i class="fas fa-history mr-1"></i>Payroll History</h3>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>Employee</th><th>Period</th><th>Net Pay</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach(Model::getAllPayroll() as $p): ?>
            <tr class="payslip-history-row">
              <td><?= htmlspecialchars(explode(' ', $p['employee_name'] ?? 'N/A')[0]) ?></td>
              <td><?= $p['period'] ?></td>
              <td>₱<?= number_format($p['net_pay'],0) ?></td>
              <td>
                <span class="badge badge-<?= $p['status']==='released'?'success':'warning' ?>" class="payslip-history-badge">
                  <?= ucfirst($p['status']) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Right: payslip -->
  <div class="col-md-8">
    <?php if($selectedEmp && $payrollRecord): ?>
    <div class="card payslip-card">
      <div class="payslip-header-bar">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h4 class="mb-0" style="font-weight:700">Rocky Company</h4>
            <p class="mb-0" style="font-size:.75rem; opacity:.7">Payroll Slip / Official Document</p>
          </div>
          <div class="text-right">
            <span style="font-size:.75rem; opacity:.7">Pay Period</span><br>
            <strong><?= Model::periodLabel($payrollRecord['period']) ?></strong>
          </div>
        </div>
      </div>
      <div class="card-body">

        <!-- Employee Info -->
        <div class="row mb-3">
          <div class="col-6">
            <table class="table table-sm table-borderless mb-0" class="payslip-employee-table">
              <tr><td class="text-muted pl-0" width="40%">Employee No.</td><td><code><?= $selectedEmp['employee_no'] ?></code></td></tr>
              <tr><td class="text-muted pl-0">Name</td><td><strong><?= htmlspecialchars($selectedEmp['name']) ?></strong></td></tr>
              <tr><td class="text-muted pl-0">Department</td><td><?= htmlspecialchars($selectedEmp['department']) ?></td></tr>
            </table>
          </div>
          <div class="col-6">
            <table class="table table-sm table-borderless mb-0" class="payslip-employee-table">
              <tr><td class="text-muted pl-0" width="40%">Position</td><td><?= htmlspecialchars($selectedEmp['position']) ?></td></tr>
              <tr><td class="text-muted pl-0">Date Hired</td><td><?= $selectedEmp['date_hired'] ?></td></tr>
              <tr><td class="text-muted pl-0">Status</td><td><span class="badge badge-success">Active</span></td></tr>
            </table>
          </div>
        </div>

        <div class="payslip-divider"></div>

        <?php
          $isCutoff1   = Model::periodCutoff($payrollRecord['period']) === 1;
          $isDecDec1   = Model::isDecember1stCutoff($payrollRecord['period']);
          // Detect 13th month: gross > basic+allowance on a 1st cutoff
          $expectedGross = round($payrollRecord['basic_salary'] + $payrollRecord['allowance'], 2);
          $thirteenth13  = $isCutoff1 ? max(0.0, round($payrollRecord['gross_pay'] - $expectedGross, 2)) : 0.0;
          // Detect reconciliation: stored in other_deductions minus absent_deduction
          $absentDed     = (float)($payrollRecord['absent_deduction'] ?? 0);
          $otherDed      = (float)($payrollRecord['other_deductions'] ?? 0);
          $reconcile     = round($otherDed - $absentDed, 2);
        ?>
        <!-- Earnings & Deductions -->
        <div class="row">
          <!-- Earnings -->
          <div class="col-6">
            <h6 class="payslip-section-title">Earnings</h6>
            <div class="comp-row">
              <span>Basic Salary <small class="text-muted">(<?= $isCutoff1 ? '1st' : '2nd' ?> cutoff)</small></span>
              <span>₱<?= number_format($payrollRecord['basic_salary'],2) ?></span>
            </div>
            <div class="comp-row"><span>Allowance</span><span>₱<?= number_format($payrollRecord['allowance'],2) ?></span></div>
            <?php if ($thirteenth13 > 0): ?>
            <div class="comp-row" style="color:#0369a1;">
              <span><i class="fas fa-gift mr-1"></i>13th Month Pay</span>
              <span>₱<?= number_format($thirteenth13,2) ?></span>
            </div>
            <?php endif; ?>
            <div class="comp-row total text-success"><span>Gross Pay</span><span>₱<?= number_format($payrollRecord['gross_pay'],2) ?></span></div>
          </div>
          <!-- Deductions -->
          <div class="col-6">
            <h6 class="payslip-section-title">Deductions</h6>
            <?php if (!$isCutoff1): ?>
            <div class="comp-row"><span>SSS</span><span class="text-danger">−₱<?= number_format($payrollRecord['sss_ee'],2) ?></span></div>
            <div class="comp-row"><span>PhilHealth</span><span class="text-danger">−₱<?= number_format($payrollRecord['philhealth_ee'],2) ?></span></div>
            <div class="comp-row"><span>Pag-IBIG</span><span class="text-danger">−₱<?= number_format($payrollRecord['pagibig_ee'],2) ?></span></div>
            <?php else: ?>
            <div class="comp-row text-muted" style="font-size:.78rem;">
              <span><i class="fas fa-info-circle mr-1"></i>Gov. deductions</span><span>1st cutoff — none</span>
            </div>
            <?php endif; ?>
            <div class="comp-row"><span>Withholding Tax</span><span class="text-danger">−₱<?= number_format($payrollRecord['withholding_tax'],2) ?></span></div>
            <?php if ($absentDed > 0): ?>
            <div class="comp-row"><span>Absent Deduction</span><span class="text-danger">−₱<?= number_format($absentDed,2) ?></span></div>
            <?php endif; ?>
            <?php if ($reconcile != 0): ?>
            <div class="comp-row" style="color:<?= $reconcile > 0 ? '#dc2626' : '#16a34a' ?>;">
              <span>Year-End Tax Reconciliation</span>
              <span><?= $reconcile > 0 ? '−' : '+' ?>₱<?= number_format(abs($reconcile),2) ?></span>
            </div>
            <?php endif; ?>
            <div class="comp-row total text-danger"><span>Total Deductions</span><span>₱<?= number_format($payrollRecord['total_deductions'],2) ?></span></div>
          </div>
        </div>

        <div class="payslip-divider"></div>

        <!-- Net Pay -->
        <div class="d-flex justify-content-between align-items-center p-3 rounded" class="payslip-net-box">
          <div>
            <p class="mb-0 text-muted" class="net-label">NET PAY FOR <?= strtoupper(Model::periodLabel($payrollRecord['period'])) ?></p>
            <h2 class="mb-0 text-primary font-weight-bold">₱<?= number_format($payrollRecord['net_pay'],2) ?></h2>
          </div>
          <div class="text-right">
            <p class="mb-0 text-muted" style="font-size:.75rem">Processed by</p>
            <strong class="payslip-employee-table"><?= htmlspecialchars($_SESSION['name']) ?></strong><br>
            <small class="text-muted">Administrator</small>
          </div>
        </div>

        <!-- Signature area — hidden on screen, visible on print -->
        <div class="row mt-4 pt-3 print-only">
          <div class="col-6 text-center">
            <div class="signature-line">Employee Signature / Date</div>
          </div>
          <div class="col-6 text-center">
            <div class="signature-line">Authorized Signatory</div>
          </div>
        </div>

      </div>
      <div class="card-footer no-print d-flex justify-content-between">
        <span class="text-muted" class="payslip-history-row">
          <i class="fas fa-clock mr-1"></i> Generated: <?= date('M d, Y h:i A') ?>
        </span>
        <div>
          <button class="btn btn-info btn-sm" onclick="window.print()">
            <i class="fas fa-print mr-1"></i> Print Payslip
          </button>
          <button class="btn btn-success btn-sm ml-1" onclick="window.print()">
            <i class="fas fa-download mr-1"></i> Export PDF
          </button>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="card-body text-center py-5 text-muted">
        <i class="fas fa-receipt fa-4x mb-3" style="opacity:.2"></i><br>
        <p>Select an employee and period to generate a payslip.</p>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php
$extraJs = <<<JS
function loadPayslip() {
  const emp = document.getElementById('empSelect').value;
  const period = document.getElementById('periodSelect').value;
  if(!emp) { alert('Please select an employee.'); return; }
  window.location = 'payslip.php?emp=' + emp + '&period=' + period;
}
JS;

require_once __DIR__ . '/../layouts/admin_footer.php';
?>