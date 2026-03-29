<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../config/config.php';
if (!in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$pageTitle  = 'Payslips';
$breadcrumb = 'Payslips';
$activeMenu = 'payslip';
require_once __DIR__ . '/../layouts/admin_header.php';

$employees     = Model::getAllEmployees();
$selectedEmpId = (int)($_GET['emp'] ?? 0);
$selectedEmp   = $selectedEmpId ? Model::findEmployeeById($selectedEmpId) : null;

// Period dropdown depends on selected employee
if ($selectedEmpId) {
    $rawPeriods = Model::getPayrollPeriodsForEmployee($selectedEmpId);
} else {
    $rawPeriods = Model::getPayrollPeriods();
}
$allPeriods = [];
foreach ($rawPeriods as $p) { $allPeriods[$p] = Model::periodLabel($p); }

// Pre-fetch all employee periods for JS dependent dropdown
$allEmpPeriods = [];
foreach ($employees as $e) {
    $ep = Model::getPayrollPeriodsForEmployee((int)$e['id']);
    $allEmpPeriods[(int)$e['id']] = array_map(fn($p) => ['value' => $p, 'label' => Model::periodLabel($p)], $ep);
}

$selectedPeriod = $_GET['period'] ?? array_key_first($allPeriods);

$payrollRecord = null;
if ($selectedEmp && $selectedPeriod) {
    foreach (Model::getPayrollByEmployee($selectedEmpId) as $r) {
        if ($r['period'] === $selectedPeriod) { $payrollRecord = $r; break; }
    }
}

$pdfUrl = ($selectedEmpId && $selectedPeriod)
    ? BASE_URL . '/app/views/admin/payslip_export.php?emp=' . $selectedEmpId . '&period=' . urlencode($selectedPeriod)
    : '#';
?>

<div class="row">
  <div class="col-md-4 col-12 no-print adm-ps-selector-col">
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="fas fa-search mr-2"></i>Select Payslip</h3></div>
      <div class="card-body">
        <div class="form-group">
          <label>Employee</label>
          <select class="form-control" id="empSelect">
            <option value="">-- Select Employee --</option>
            <?php foreach($employees as $e): ?>
              <option value="<?= $e['id'] ?>" <?= $selectedEmpId===$e['id']?'selected':'' ?>><?= htmlspecialchars($e['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Period</label>
          <select class="form-control" id="periodSelect">
            <option value="">-- Select Period --</option>
            <?php foreach($allPeriods as $val => $label): ?>
              <option value="<?= $val ?>" <?= $selectedPeriod===$val?'selected':'' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary btn-block" onclick="loadPayslip()">
          <i class="fas fa-eye mr-1"></i>View Payslip
        </button>
      </div>
    </div>
  </div>

  <!-- Validation Modal (AdminLTE) -->
  <div class="modal fade" id="payslipValidationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
      <div class="modal-content">
        <div class="modal-header bg-warning">
          <h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i>Selection Required</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <p class="mb-0" id="payslipValidationMsg">Please select an employee and period.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-warning btn-sm" data-dismiss="modal">
            <i class="fas fa-check mr-1"></i>OK
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-8 col-12 adm-ps-content-col">
    <?php if($selectedEmp && $payrollRecord): ?>
    <?php
      $isCutoff1    = Model::periodCutoff($payrollRecord['period']) === 1;
      $expectedGross = round($payrollRecord['basic_salary'] + $payrollRecord['allowance'], 2);
      $thirteenth13  = $isCutoff1 ? max(0.0, round($payrollRecord['gross_pay'] - $expectedGross, 2)) : 0.0;
      $absentDed     = (float)($payrollRecord['absent_deduction'] ?? 0);
      $otherDed      = (float)($payrollRecord['other_deductions'] ?? 0);
      $reconcile     = round($otherDed - $absentDed, 2);
      $cutoffLabel   = $isCutoff1 ? '1st cutoff' : '2nd cutoff';
    ?>
    <div class="card payslip-card" id="payslipPrintArea">
      <div class="adm-ps-header-bar">
        <div class="adm-ps-header-left">
          <div class="adm-ps-company-name"><?= htmlspecialchars(COMPANY_NAME) ?></div>
          <div class="adm-ps-company-sub">Payroll Slip / Official Document</div>
        </div>
        <div class="adm-ps-header-right">
          <div class="adm-ps-period"><?= htmlspecialchars(Model::periodLabel($payrollRecord['period'])) ?></div>
          <span class="status-badge badge-released adm-ps-status-pill">Released</span>
        </div>
      </div>
      <div class="card-body adm-ps-body">
        <div class="adm-ps-emp-grid">
          <div class="adm-ps-emp-field"><span class="adm-ps-emp-label">Employee No.</span><span class="adm-ps-emp-value"><code><?= htmlspecialchars($selectedEmp['employee_no']) ?></code></span></div>
          <div class="adm-ps-emp-field"><span class="adm-ps-emp-label">Position</span><span class="adm-ps-emp-value"><?= htmlspecialchars($selectedEmp['position']) ?></span></div>
          <div class="adm-ps-emp-field"><span class="adm-ps-emp-label">Name</span><span class="adm-ps-emp-value adm-ps-emp-name"><?= htmlspecialchars($selectedEmp['name']) ?></span></div>
          <div class="adm-ps-emp-field"><span class="adm-ps-emp-label">Date Hired</span><span class="adm-ps-emp-value"><?= htmlspecialchars($selectedEmp['date_hired']) ?></span></div>
          <div class="adm-ps-emp-field"><span class="adm-ps-emp-label">Department</span><span class="adm-ps-emp-value"><?= htmlspecialchars($selectedEmp['department']) ?></span></div>
          <div class="adm-ps-emp-field"><span class="adm-ps-emp-label">Status</span><span class="adm-ps-emp-value"><span class="badge badge-success">Active</span></span></div>
        </div>
        <div class="payslip-divider"></div>
        <div class="adm-ps-comp-grid">
          <div class="adm-ps-comp-col">
            <div class="adm-ps-comp-heading">Earnings</div>
            <div class="adm-ps-comp-row"><span class="adm-ps-comp-label">Basic Salary <small class="text-muted">(<?= $cutoffLabel ?>)</small></span><span class="adm-ps-comp-amount">&#8369;&nbsp;<?= number_format($payrollRecord['basic_salary'],2) ?></span></div>
            <div class="adm-ps-comp-row"><span class="adm-ps-comp-label">Allowance</span><span class="adm-ps-comp-amount">&#8369;&nbsp;<?= number_format($payrollRecord['allowance'],2) ?></span></div>
            <?php if($thirteenth13>0): ?><div class="adm-ps-comp-row"><span class="adm-ps-comp-label"><i class="fas fa-gift mr-1 text-info"></i>13th Month Pay</span><span class="adm-ps-comp-amount text-info">&#8369;&nbsp;<?= number_format($thirteenth13,2) ?></span></div><?php endif; ?>
            <div class="adm-ps-comp-row adm-ps-comp-total text-success"><span class="adm-ps-comp-label">Gross Pay</span><span class="adm-ps-comp-amount">&#8369;&nbsp;<?= number_format($payrollRecord['gross_pay'],2) ?></span></div>
          </div>
          <div class="adm-ps-comp-col">
            <div class="adm-ps-comp-heading">Deductions</div>
            <?php if(!$isCutoff1): ?>
            <div class="adm-ps-comp-row"><span class="adm-ps-comp-label">SSS</span><span class="adm-ps-comp-amount text-danger">−&nbsp;&#8369;&nbsp;<?= number_format($payrollRecord['sss_ee'],2) ?></span></div>
            <div class="adm-ps-comp-row"><span class="adm-ps-comp-label">PhilHealth</span><span class="adm-ps-comp-amount text-danger">−&nbsp;&#8369;&nbsp;<?= number_format($payrollRecord['philhealth_ee'],2) ?></span></div>
            <div class="adm-ps-comp-row"><span class="adm-ps-comp-label">Pag-IBIG</span><span class="adm-ps-comp-amount text-danger">−&nbsp;&#8369;&nbsp;<?= number_format($payrollRecord['pagibig_ee'],2) ?></span></div>
            <?php else: ?><div class="adm-ps-comp-row text-muted adm-ps-gov-note"><span class="adm-ps-comp-label"><i class="fas fa-info-circle mr-1"></i>Gov. deductions</span><span class="adm-ps-comp-amount">1st cutoff — none</span></div><?php endif; ?>
            <div class="adm-ps-comp-row"><span class="adm-ps-comp-label">Withholding Tax</span><span class="adm-ps-comp-amount text-danger">−&nbsp;&#8369;&nbsp;<?= number_format($payrollRecord['withholding_tax'],2) ?></span></div>
            <?php if($absentDed>0): ?><div class="adm-ps-comp-row"><span class="adm-ps-comp-label">Absent Deduction</span><span class="adm-ps-comp-amount text-danger">−&nbsp;&#8369;&nbsp;<?= number_format($absentDed,2) ?></span></div><?php endif; ?>
            <?php if($reconcile!=0): ?><div class="adm-ps-comp-row <?= $reconcile>0?'comp-row-negative':'comp-row-positive' ?>"><span class="adm-ps-comp-label">Year-End Tax Reconciliation</span><span class="adm-ps-comp-amount"><?= $reconcile>0?'−':'+' ?>&nbsp;&#8369;&nbsp;<?= number_format(abs($reconcile),2) ?></span></div><?php endif; ?>
            <div class="adm-ps-comp-row adm-ps-comp-total text-danger"><span class="adm-ps-comp-label">Total Deductions</span><span class="adm-ps-comp-amount">&#8369;&nbsp;<?= number_format($payrollRecord['total_deductions'],2) ?></span></div>
          </div>
        </div>
        <div class="payslip-divider"></div>
        <div class="adm-ps-net-box">
          <div class="adm-ps-net-left">
            <div class="adm-ps-net-label">NET PAY FOR <?= strtoupper(htmlspecialchars(Model::periodLabel($payrollRecord['period']))) ?></div>
            <div class="adm-ps-net-amount">&#8369;&nbsp;<?= number_format($payrollRecord['net_pay'],2) ?></div>
          </div>
          <div class="adm-ps-net-right">
            <div class="adm-ps-net-processed-label">Processed by</div>
            <div class="adm-ps-net-processed-name"><?= htmlspecialchars($_SESSION['name']) ?></div>
            <div class="adm-ps-net-processed-role">Payroll Administrator</div>
          </div>
        </div>
        <div class="row mt-4 pt-3 print-only">
          <div class="col-6 text-center"><div class="signature-line">Employee Signature / Date</div></div>
          <div class="col-6 text-center"><div class="signature-line">Authorized Signatory</div></div>
        </div>
      </div>
      <div class="card-footer no-print adm-ps-footer">
        <div class="adm-ps-footer-timestamp"><i class="fas fa-clock mr-1"></i>Generated: <?= date('M d, Y h:i A') ?></div>
        <div class="adm-ps-footer-btns">
          <button class="btn btn-info btn-sm adm-ps-btn" onclick="printAdminPayslip()">
            <i class="fas fa-print mr-1"></i>Print Payslip
          </button>
          <a href="<?= htmlspecialchars($pdfUrl) ?>" class="btn btn-success btn-sm adm-ps-btn">
            <i class="fas fa-download mr-1"></i>Export PDF
          </a>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="card"><div class="card-body text-center py-5 text-muted">
      <i class="fas fa-receipt fa-4x mb-3 payslip-empty-icon"></i><br>
      <p>Select an employee and period to generate a payslip.</p>
    </div></div>
    <?php endif; ?>
  </div>
</div>

<?php
$baseUrl = BASE_URL;
$allEmpPeriodsJson = json_encode($allEmpPeriods);
$extraJs = <<<JS
var ALL_EMP_PERIODS = {$allEmpPeriodsJson};

document.getElementById('empSelect').addEventListener('change', function() {
    var empId = parseInt(this.value) || 0;
    var periodSel = document.getElementById('periodSelect');
    periodSel.innerHTML = '<option value="">-- Select Period --</option>';
    if (empId && ALL_EMP_PERIODS[empId]) {
        ALL_EMP_PERIODS[empId].forEach(function(p) {
            var opt = document.createElement('option');
            opt.value = p.value;
            opt.textContent = p.label;
            periodSel.appendChild(opt);
        });
    }
});

function loadPayslip() {
    var emp    = document.getElementById('empSelect').value;
    var period = document.getElementById('periodSelect').value;
    if (!emp) {
        document.getElementById('payslipValidationMsg').textContent = 'Please select an employee first.';
        $('#payslipValidationModal').modal('show');
        return;
    }
    if (!period) {
        document.getElementById('payslipValidationMsg').textContent = 'Please select a payslip period.';
        $('#payslipValidationModal').modal('show');
        return;
    }
    window.location = 'payslip.php?emp=' + emp + '&period=' + period;
}

function printAdminPayslip() {
    var el = document.getElementById('payslipPrintArea');
    if (!el) return;
    var printContents = el.innerHTML;
    var win = window.open('', '_blank', 'width=900,height=700');
    win.document.write(
        '<!DOCTYPE html><html><head><title>Payslip</title>'
        + '<link rel="stylesheet" href="{$baseUrl}/assets/css/common.css">'
        + '<link rel="stylesheet" href="{$baseUrl}/assets/css/admin.css">'
        + '<style>body{background:#fff!important}.no-print{display:none!important}.card-footer{display:none!important}</style>'
        + '</head><body>' + printContents + '</body></html>'
    );
    win.document.close();
    win.focus();
    setTimeout(function(){ win.print(); win.close(); }, 600);
}
JS;

require_once __DIR__ . '/../layouts/admin_footer.php';
?>