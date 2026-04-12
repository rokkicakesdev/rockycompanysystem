<?php
// app/views/admin/bir_2316.php
// ─────────────────────────────────────────────────────────────────────────────
//  BIR Form 2316 — Certificate of Compensation Payment / Tax Withheld
//  RR No. 2-98, as amended by RR No. 11-2013, RR No. 8-2018
//
//  Data sourced entirely from payroll_records (YTD aggregates per year).
//  All figures are read-only; no DB writes from this page.
// ─────────────────────────────────────────────────────────────────────────────

$pageTitle  = 'BIR Form 2316';
$breadcrumb = 'BIR Form 2316';
$activeMenu = 'bir_2316';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('ROLE_ADMIN'))   require_once __DIR__ . '/../../../config/config.php';
if (!defined('DB_HOST'))      require_once __DIR__ . '/../../../config/database.php';
if (!class_exists('Database')) require_once __DIR__ . '/../../../core/Database.php';
if (!class_exists('Model'))    require_once __DIR__ . '/../../../core/Model.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) {
    header('Location: ' . BASE_URL . '/index.php?error=access_denied'); exit;
}

$currentYear   = (int) date('Y');
$selectedYear  = isset($_GET['year']) && is_numeric($_GET['year'])
    ? max(2020, min($currentYear, (int)$_GET['year']))
    : $currentYear;

$selectedEmpId = isset($_GET['emp_id']) ? (int)$_GET['emp_id'] : 0;

// Load all active + separated employees (Form 2316 is generated for ALL employees in the year)
$allEmployees = Model::getAllEmployees();  // no status filter — include separated employees

// Filter to employees who have at least one payroll record in the selected year
$eligibleEmployees = [];
foreach ($allEmployees as $emp) {
    $periods = Model::getPayrollPeriodsForEmployee((int)$emp['id']);
    foreach ($periods as $p) {
        if (str_starts_with($p, $selectedYear . '-')) {
            $eligibleEmployees[] = $emp;
            break;
        }
    }
}

// Default to first eligible employee
if (!$selectedEmpId && !empty($eligibleEmployees)) {
    $selectedEmpId = (int)$eligibleEmployees[0]['id'];
}

// Load the selected employee's data
$employee = null;
$ytd      = [];
$ytd13th  = null;
if ($selectedEmpId) {
    foreach ($eligibleEmployees as $e) {
        if ((int)$e['id'] === $selectedEmpId) { $employee = $e; break; }
    }
    if ($employee) {
        // YTD data — all periods in the selected year
        $lastPeriodOfYear = $selectedYear . '-12-2';
        $ytd = Model::getPayrollYTD($selectedEmpId, $lastPeriodOfYear);
        // 13th month released this year
        $ytd13th = Model::get13thMonthByEmployee($selectedEmpId, $selectedYear);
    }
}

// Derived figures for Form 2316
$totalBasicPay     = (float)($ytd['ytd_basic']          ?? 0);
$totalAllowance    = (float)($ytd['ytd_allowance']       ?? 0);
$totalGrossPay     = (float)($ytd['ytd_gross']           ?? 0);
$totalSSS          = (float)($ytd['ytd_sss_ee']          ?? 0);
$totalPhilHealth   = (float)($ytd['ytd_philhealth_ee']   ?? 0);
$totalPagIbig      = (float)($ytd['ytd_pagibig_ee']      ?? 0);
$totalTaxableIncome = max(0, $totalBasicPay - $totalSSS - $totalPhilHealth - $totalPagIbig);
$totalTaxWithheld  = (float)($ytd['ytd_tax']             ?? 0);
$totalNetPay       = (float)($ytd['ytd_net']             ?? 0);
$thirteenthPay     = $ytd13th ? (float)($ytd13th['amount'] ?? 0) : 0.0;

require_once __DIR__ . '/../layouts/admin_header.php';
?>

<div class="container-fluid">

  <!-- Page Header -->
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h4 class="mb-0 font-weight-bold"><i class="fas fa-file-contract text-primary mr-2"></i>BIR Form 2316</h4>
      <small class="text-muted">Certificate of Compensation Payment / Tax Withheld — RR No. 2-98</small>
    </div>
    <?php if ($employee): ?>
    <div class="no-print">
      <button onclick="window.print()" class="btn btn-primary btn-sm">
        <i class="fas fa-print mr-1"></i>Print Form
      </button>
      <a href="?year=<?= $selectedYear ?>&emp_id=<?= $selectedEmpId ?>&export=pdf"
         class="btn btn-danger btn-sm ml-1">
        <i class="fas fa-file-pdf mr-1"></i>Export PDF
      </a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Controls -->
  <div class="card mb-4 no-print">
    <div class="card-body py-3">
      <form method="GET" class="form-inline flex-wrap gap-2">
        <label class="font-weight-600 mr-2">Year:</label>
        <select name="year" class="form-control form-control-sm mr-3"
                onchange="this.form.submit()">
          <?php for ($y = $currentYear; $y >= 2020; $y--): ?>
            <option value="<?= $y ?>" <?= $y === $selectedYear ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>

        <label class="font-weight-600 mr-2">Employee:</label>
        <select name="emp_id" class="form-control form-control-sm mr-3"
                onchange="this.form.submit()">
          <option value="">-- Select Employee --</option>
          <?php foreach ($eligibleEmployees as $e): ?>
            <option value="<?= $e['id'] ?>" <?= (int)$e['id'] === $selectedEmpId ? 'selected' : '' ?>>
              <?= htmlspecialchars($e['employee_no'] . ' — ' . $e['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <noscript><button type="submit" class="btn btn-sm btn-secondary">Go</button></noscript>
      </form>

      <?php if (empty($eligibleEmployees)): ?>
        <div class="alert alert-warning mt-3 mb-0">
          <i class="fas fa-exclamation-triangle mr-2"></i>
          No employees have payroll records for <strong><?= $selectedYear ?></strong>.
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php if ($employee && !empty($ytd)): ?>

  <!-- ── BIR Form 2316 ─────────────────────────────────────────────────────── -->
  <div class="bir-form-wrapper" id="bir2316Form">

    <!-- Form Header -->
    <div class="bir-header text-center">
      <div class="bir-gov-label">Republic of the Philippines</div>
      <div class="bir-gov-label">Department of Finance</div>
      <div class="bir-gov-label font-weight-bold">BUREAU OF INTERNAL REVENUE</div>
      <div class="bir-form-title">BIR FORM NO. 2316</div>
      <div class="bir-form-subtitle">Certificate of Compensation Payment / Tax Withheld</div>
      <div class="bir-form-period">For Compensation Payment With or Without Tax Withheld</div>
      <div class="bir-year-badge">Taxable Year: <strong><?= $selectedYear ?></strong></div>
    </div>

    <!-- Part I — Employee / Employer Info -->
    <div class="bir-section-title">PART I — Employee and Employer Information</div>
    <div class="bir-grid-2">
      <div class="bir-field-group">
        <div class="bir-section-subtitle">EMPLOYEE</div>
        <div class="bir-row">
          <div class="bir-label">1. Employee TIN</div>
          <div class="bir-value"><?= htmlspecialchars($employee['tin_no'] ?? 'N/A') ?></div>
        </div>
        <div class="bir-row">
          <div class="bir-label">2. Employee Name</div>
          <div class="bir-value font-weight-bold"><?= htmlspecialchars($employee['name']) ?></div>
        </div>
        <div class="bir-row">
          <div class="bir-label">3. Date of Birth</div>
          <div class="bir-value"><?= !empty($employee['birthdate']) ? date('F j, Y', strtotime($employee['birthdate'])) : 'N/A' ?></div>
        </div>
        <div class="bir-row">
          <div class="bir-label">4. Address</div>
          <div class="bir-value"><?= htmlspecialchars($employee['address'] ?? 'N/A') ?></div>
        </div>
        <div class="bir-row">
          <div class="bir-label">5. Contact Number</div>
          <div class="bir-value"><?= htmlspecialchars($employee['phone'] ?? 'N/A') ?></div>
        </div>
        <div class="bir-row">
          <div class="bir-label">6. Civil Status</div>
          <div class="bir-value"><?= htmlspecialchars(ucfirst($employee['civil_status'] ?? 'N/A')) ?></div>
        </div>
        <div class="bir-row">
          <div class="bir-label">7. SSS No.</div>
          <div class="bir-value"><?= htmlspecialchars($employee['sss_no'] ?? 'N/A') ?></div>
        </div>
        <div class="bir-row">
          <div class="bir-label">8. PhilHealth No.</div>
          <div class="bir-value"><?= htmlspecialchars($employee['philhealth_no'] ?? 'N/A') ?></div>
        </div>
        <div class="bir-row">
          <div class="bir-label">9. Pag-IBIG No.</div>
          <div class="bir-value"><?= htmlspecialchars($employee['pagibig_no'] ?? 'N/A') ?></div>
        </div>
        <div class="bir-row">
          <div class="bir-label">10. Employment Period (<?= $selectedYear ?>)</div>
          <div class="bir-value">
            Jan 1 – Dec 31, <?= $selectedYear ?>
          </div>
        </div>
      </div>

      <div class="bir-field-group">
        <div class="bir-section-subtitle">EMPLOYER</div>
        <div class="bir-row">
          <div class="bir-label">11. Employer Name</div>
          <div class="bir-value font-weight-bold"><?= htmlspecialchars(COMPANY_NAME) ?></div>
        </div>
        <div class="bir-row">
          <div class="bir-label">12. Employer Address</div>
          <div class="bir-value"><?= htmlspecialchars(COMPANY_ADDRESS) ?></div>
        </div>
        <div class="bir-row">
          <div class="bir-label">13. Employment Status</div>
          <div class="bir-value">
            <?= ucfirst($employee['employment_type'] ?? 'Regular') ?>
            <?php if (!empty($employee['date_regularized'])): ?>
              (Regularized <?= date('M j, Y', strtotime($employee['date_regularized'])) ?>)
            <?php endif; ?>
          </div>
        </div>
        <div class="bir-row">
          <div class="bir-label">14. Department</div>
          <div class="bir-value"><?= htmlspecialchars($employee['department'] ?? 'N/A') ?></div>
        </div>
        <div class="bir-row">
          <div class="bir-label">15. Position</div>
          <div class="bir-value"><?= htmlspecialchars($employee['position'] ?? 'N/A') ?></div>
        </div>
      </div>
    </div>

    <!-- Part II — Compensation -->
    <div class="bir-section-title">PART II — Compensation Income and Tax Withheld</div>

    <div class="bir-comp-table">
      <!-- Gross Compensation -->
      <div class="bir-comp-header">A. GROSS COMPENSATION INCOME</div>
      <div class="bir-comp-row">
        <div class="bir-comp-label">16. Basic Salary / Pay</div>
        <div class="bir-comp-amount">&#8369;<?= number_format($totalBasicPay, 2) ?></div>
      </div>
      <div class="bir-comp-row">
        <div class="bir-comp-label">17. Allowances / Other Compensation</div>
        <div class="bir-comp-amount">&#8369;<?= number_format($totalAllowance, 2) ?></div>
      </div>
      <div class="bir-comp-row">
        <div class="bir-comp-label">18. 13th Month Pay (De Minimis up to ₱90,000 is exempt)</div>
        <div class="bir-comp-amount">
          &#8369;<?= number_format($thirteenthPay, 2) ?>
          <?php if ($thirteenthPay > 0 && $thirteenthPay <= 90000): ?>
            <span class="bir-badge-exempt">EXEMPT</span>
          <?php elseif ($thirteenthPay > 90000): ?>
            <span class="bir-badge-taxable">Excess: &#8369;<?= number_format($thirteenthPay - 90000, 2) ?> taxable</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="bir-comp-row bir-comp-total">
        <div class="bir-comp-label">19. Total Gross Compensation (16 + 17 + 18)</div>
        <div class="bir-comp-amount">&#8369;<?= number_format($totalGrossPay + $thirteenthPay, 2) ?></div>
      </div>

      <!-- Deductions / Exemptions -->
      <div class="bir-comp-header">B. NON-TAXABLE / EXEMPT COMPENSATION & MANDATORY DEDUCTIONS</div>
      <div class="bir-comp-row">
        <div class="bir-comp-label">20. SSS Contributions (EE share)</div>
        <div class="bir-comp-amount">&#8369;<?= number_format($totalSSS, 2) ?></div>
      </div>
      <div class="bir-comp-row">
        <div class="bir-comp-label">21. PhilHealth Contributions (EE share)</div>
        <div class="bir-comp-amount">&#8369;<?= number_format($totalPhilHealth, 2) ?></div>
      </div>
      <div class="bir-comp-row">
        <div class="bir-comp-label">22. Pag-IBIG Contributions (EE share)</div>
        <div class="bir-comp-amount">&#8369;<?= number_format($totalPagIbig, 2) ?></div>
      </div>
      <?php
        $exemptThirteenth = min($thirteenthPay, 90000.00);
      ?>
      <div class="bir-comp-row">
        <div class="bir-comp-label">23. 13th Month Pay (exempt portion — max ₱90,000)</div>
        <div class="bir-comp-amount">&#8369;<?= number_format($exemptThirteenth, 2) ?></div>
      </div>
      <div class="bir-comp-row bir-comp-total">
        <div class="bir-comp-label">24. Total Non-Taxable / Exempt (20+21+22+23)</div>
        <div class="bir-comp-amount">&#8369;<?= number_format($totalSSS + $totalPhilHealth + $totalPagIbig + $exemptThirteenth, 2) ?></div>
      </div>

      <!-- Taxable Compensation -->
      <div class="bir-comp-header">C. TAXABLE COMPENSATION INCOME</div>
      <?php
        $taxableThirteenth = max(0, $thirteenthPay - 90000);
        $taxableCompensation = max(0, $totalBasicPay - $totalSSS - $totalPhilHealth - $totalPagIbig) + $taxableThirteenth;
      ?>
      <div class="bir-comp-row">
        <div class="bir-comp-label">25. Basic Pay less mandatory deductions (16 − 20 − 21 − 22)</div>
        <div class="bir-comp-amount">&#8369;<?= number_format(max(0, $totalBasicPay - $totalSSS - $totalPhilHealth - $totalPagIbig), 2) ?></div>
      </div>
      <div class="bir-comp-row">
        <div class="bir-comp-label">26. Taxable 13th month excess (if any)</div>
        <div class="bir-comp-amount">&#8369;<?= number_format($taxableThirteenth, 2) ?></div>
      </div>
      <div class="bir-comp-row bir-comp-total bir-comp-highlight">
        <div class="bir-comp-label">27. Total Taxable Compensation Income (25 + 26)</div>
        <div class="bir-comp-amount font-weight-bold">&#8369;<?= number_format($taxableCompensation, 2) ?></div>
      </div>

      <!-- Tax Withheld -->
      <div class="bir-comp-header">D. TAX WITHHELD</div>
      <div class="bir-comp-row">
        <div class="bir-comp-label">28. Tax Required to be Withheld (TRAIN Law, annualised method)</div>
        <div class="bir-comp-amount">&#8369;<?= number_format($totalTaxWithheld, 2) ?></div>
      </div>
      <div class="bir-comp-row bir-comp-total bir-comp-highlight">
        <div class="bir-comp-label">29. Total Tax Withheld (28)</div>
        <div class="bir-comp-amount font-weight-bold">&#8369;<?= number_format($totalTaxWithheld, 2) ?></div>
      </div>
    </div>

    <!-- Part III — Certification -->
    <div class="bir-section-title">PART III — TRAIN Law Tax Bracket Reference (Annual, 2026)</div>
    <div class="bir-tax-table">
      <?php
      $brackets = [
          ['₱0 – ₱250,000',           '0%',                    '—'],
          ['₱250,001 – ₱400,000',      '15%',                   '15% of excess over ₱250,000'],
          ['₱400,001 – ₱800,000',      '₱22,500 + 20%',        '20% of excess over ₱400,000'],
          ['₱800,001 – ₱2,000,000',    '₱102,500 + 25%',       '25% of excess over ₱800,000'],
          ['₱2,000,001 – ₱8,000,000',  '₱402,500 + 30%',       '30% of excess over ₱2,000,000'],
          ['Above ₱8,000,000',          '₱2,402,500 + 35%',     '35% of excess over ₱8,000,000'],
      ];
      ?>
      <table class="table table-sm table-bordered mb-0">
        <thead class="thead-dark">
          <tr>
            <th>Annual Taxable Compensation</th>
            <th>Tax Rate</th>
            <th>Computation</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($brackets as $i => $row): ?>
            <?php
              $ann = $taxableCompensation * 12;
              $bracketIdx = 0;
              if     ($ann <= 250000)      $bracketIdx = 0;
              elseif ($ann <= 400000)      $bracketIdx = 1;
              elseif ($ann <= 800000)      $bracketIdx = 2;
              elseif ($ann <= 2000000)     $bracketIdx = 3;
              elseif ($ann <= 8000000)     $bracketIdx = 4;
              else                         $bracketIdx = 5;
            ?>
            <tr <?= ($ann > 0 && $i === $bracketIdx) ? 'class="bir-bracket-active"' : '' ?>>
              <td><?= $row[0] ?></td>
              <td><?= $row[1] ?></td>
              <td><?= $row[2] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php
        // Determine which bracket applies
        $annualTaxable = $taxableCompensation * 12;
        $bracketLabel = 'Not in payroll year';
        if ($annualTaxable <= 250000)           $bracketLabel = 'EXEMPT (0%)';
        elseif ($annualTaxable <= 400000)       $bracketLabel = '15% bracket';
        elseif ($annualTaxable <= 800000)       $bracketLabel = '20% bracket';
        elseif ($annualTaxable <= 2000000)      $bracketLabel = '25% bracket';
        elseif ($annualTaxable <= 8000000)      $bracketLabel = '30% bracket';
        else                                    $bracketLabel = '35% bracket';
      ?>
      <p class="small text-muted mt-2 mb-0">
        <i class="fas fa-info-circle mr-1"></i>
        This employee's annualised taxable income is approximately <strong>₱<?= number_format($annualTaxable, 2) ?></strong>
        — falls in the <strong><?= $bracketLabel ?></strong>.
        Actual tax withheld per cutoff uses the semi-monthly annualised method (taxable × 24 → annual bracket → ÷ 24).
      </p>
    </div>

    <!-- Certification Block -->
    <div class="bir-certification">
      <div class="row">
        <div class="col-md-6">
          <div class="bir-cert-block">
            <div class="bir-cert-title">EMPLOYEE CERTIFICATION</div>
            <p class="bir-cert-text">
              I hereby declare, under the penalties of perjury, that this certificate has been made
              in good faith, verified by me, and to the best of my knowledge and belief, is true
              and correct, pursuant to the provisions of the National Internal Revenue Code, as
              amended, and the regulations issued under authority thereof.
            </p>
            <div class="bir-sig-line">
              <div class="bir-sig-name"><?= htmlspecialchars($employee['name']) ?></div>
              <div class="bir-sig-label">Employee Signature over Printed Name / Date</div>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="bir-cert-block">
            <div class="bir-cert-title">EMPLOYER CERTIFICATION</div>
            <p class="bir-cert-text">
              I hereby declare, under the penalties of perjury, that this certificate has been made
              in good faith, verified by me, and to the best of my knowledge and belief, is true
              and correct, pursuant to the provisions of the National Internal Revenue Code, as
              amended, and the regulations issued under authority thereof.
            </p>
            <div class="bir-sig-line">
              <div class="bir-sig-name"><?= htmlspecialchars(COMPANY_NAME) ?></div>
              <div class="bir-sig-label">Authorized Signatory (HR / Payroll Officer) / Date</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="bir-form-footer">
      <span>Generated by <?= htmlspecialchars(APP_NAME) ?> — <?= date('F j, Y \a\t h:i A') ?></span>
      <span>Taxable Year: <?= $selectedYear ?></span>
      <span>BIR Form 2316 | RR No. 2-98</span>
    </div>

  </div><!-- /.bir-form-wrapper -->

<?php elseif ($selectedEmpId && empty($ytd)): ?>
  <div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle mr-2"></i>
    No payroll records found for this employee in <strong><?= $selectedYear ?></strong>.
    Generate payroll first before producing Form 2316.
  </div>
<?php endif; ?>

</div><!-- /.container-fluid -->

<style>
/* ── BIR Form 2316 Styles ─────────────────────────────────────────────────── */
.bir-form-wrapper {
  max-width: 900px;
  margin: 0 auto;
  background: #fff;
  border: 1.5px solid #cbd5e1;
  border-radius: 8px;
  overflow: hidden;
  font-size: 0.85rem;
}
.bir-header {
  background: linear-gradient(135deg, #1a2744 0%, #2563eb 100%);
  color: #fff;
  padding: 18px 24px 14px;
}
.bir-gov-label    { font-size: 0.8rem; letter-spacing: .04em; opacity: .9; }
.bir-form-title   { font-size: 1.5rem; font-weight: 800; letter-spacing: .06em; margin: 8px 0 2px; }
.bir-form-subtitle{ font-size: 0.95rem; font-weight: 600; margin-bottom: 2px; }
.bir-form-period  { font-size: 0.8rem; opacity: .85; }
.bir-year-badge   {
  display: inline-block; margin-top: 10px;
  background: rgba(255,255,255,.15); border-radius: 20px;
  padding: 4px 18px; font-size: .85rem;
}
.bir-section-title {
  background: #1e293b; color: #f1f5f9;
  font-weight: 700; font-size: .78rem;
  letter-spacing: .06em; text-transform: uppercase;
  padding: 7px 16px;
}
.bir-section-subtitle {
  font-weight: 700; font-size: .75rem; color: #2563eb;
  text-transform: uppercase; letter-spacing: .04em;
  padding: 8px 12px 4px; background: #eff6ff;
  border-bottom: 1px solid #bfdbfe;
}
.bir-grid-2 {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 0; border-bottom: 1.5px solid #e2e8f0;
}
.bir-field-group { border-right: 1px solid #e2e8f0; }
.bir-field-group:last-child { border-right: none; }
.bir-row {
  display: grid; grid-template-columns: 160px 1fr;
  border-bottom: 1px solid #f1f5f9;
  min-height: 32px;
}
.bir-label {
  background: #f8fafc; color: #64748b; font-size: .77rem;
  padding: 6px 10px; border-right: 1px solid #e2e8f0;
  display: flex; align-items: center;
}
.bir-value {
  padding: 6px 10px; color: #1e293b;
  display: flex; align-items: center; flex-wrap: wrap; gap: 4px;
}
/* Compensation table */
.bir-comp-table { padding: 0; }
.bir-comp-header {
  background: #f1f5f9; color: #475569;
  font-weight: 700; font-size: .75rem; text-transform: uppercase;
  padding: 6px 16px; border-bottom: 1px solid #e2e8f0;
  letter-spacing: .04em;
}
.bir-comp-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 7px 16px; border-bottom: 1px solid #f1f5f9;
}
.bir-comp-row:hover { background: #f8fafc; }
.bir-comp-label { color: #374151; flex: 1; }
.bir-comp-amount { font-weight: 600; color: #1e293b; min-width: 140px; text-align: right; }
.bir-comp-total { background: #f1f5f9; border-top: 1px solid #cbd5e1; font-weight: 700; }
.bir-comp-highlight { background: #eff6ff !important; }
.bir-comp-highlight .bir-comp-amount { color: #1d4ed8; font-size: 1rem; }
.bir-badge-exempt  { background: #d1fae5; color: #065f46; font-size: .7rem; border-radius: 10px; padding: 1px 8px; font-weight: 600; }
.bir-badge-taxable { background: #fee2e2; color: #991b1b; font-size: .7rem; border-radius: 10px; padding: 1px 8px; font-weight: 600; }
/* Tax table */
.bir-tax-table { padding: 14px 16px 10px; background: #fafafa; border-bottom: 1px solid #e2e8f0; }
.bir-bracket-active { background: #dbeafe !important; font-weight: 600; }
/* Certification */
.bir-certification { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; }
.bir-cert-block { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px; height: 100%; }
.bir-cert-title { font-weight: 700; font-size: .78rem; text-transform: uppercase; color: #374151; margin-bottom: 8px; letter-spacing: .04em; }
.bir-cert-text  { font-size: .78rem; color: #64748b; line-height: 1.5; margin-bottom: 14px; }
.bir-sig-line   { border-top: 1.5px solid #1e293b; padding-top: 6px; margin-top: 20px; }
.bir-sig-name   { font-weight: 700; font-size: .85rem; }
.bir-sig-label  { font-size: .72rem; color: #64748b; }
/* Footer */
.bir-form-footer {
  background: #1e293b; color: #94a3b8;
  display: flex; justify-content: space-between;
  padding: 8px 16px; font-size: .72rem;
  flex-wrap: wrap; gap: 4px;
}

/* Print styles */
@media print {
  .no-print, .main-header, .main-sidebar, .main-footer,
  .content-header, .breadcrumb { display: none !important; }
  .content-wrapper { margin-left: 0 !important; background: #fff !important; }
  .bir-form-wrapper { border: none; max-width: 100%; }
  body { font-size: 11px !important; }
  .bir-comp-row { page-break-inside: avoid; }
}
@media (max-width: 600px) {
  .bir-grid-2 { grid-template-columns: 1fr; }
  .bir-field-group { border-right: none; border-bottom: 1px solid #e2e8f0; }
  .bir-row { grid-template-columns: 120px 1fr; }
  .bir-comp-row { flex-direction: column; align-items: flex-start; gap: 2px; }
  .bir-comp-amount { text-align: left; }
}
</style>

<?php
$extraJs = <<<'JS'
// No interactive JS needed — form is read-only display
JS;
?>
<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>