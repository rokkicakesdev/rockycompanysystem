<?php
// app/views/admin/bir_2316.php
// BIR Form 2316 — Certificate of Compensation Payment / Tax Withheld
// September 2021 (ENCS) — Official layout matching BIR Form No. 2316 9/21ENCS

$pageTitle  = 'BIR Form 2316';
$breadcrumb = 'BIR Form 2316';
$activeMenu = 'bir_2316';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('ROLE_ADMIN'))    require_once __DIR__ . '/../../../config/config.php';
if (!defined('DB_HOST'))       require_once __DIR__ . '/../../../config/database.php';
if (!class_exists('Database')) require_once __DIR__ . '/../../../core/Database.php';
if (!class_exists('Model'))    require_once __DIR__ . '/../../../core/Model.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) {
    header('Location: ' . BASE_URL . '/index.php?error=access_denied'); exit;
}

$currentYear  = (int) date('Y');
$selectedYear = isset($_GET['year']) && is_numeric($_GET['year'])
    ? max(2020, min($currentYear, (int)$_GET['year'])) : $currentYear;
$selectedEmpId = isset($_GET['emp_id']) ? (int)$_GET['emp_id'] : 0;

$allEmployees = Model::getAllEmployees();
$eligibleEmployees = [];
foreach ($allEmployees as $emp) {
    $periods = Model::getPayrollPeriodsForEmployee((int)$emp['id']);
    foreach ($periods as $p) {
        if (str_starts_with($p, $selectedYear . '-')) { $eligibleEmployees[] = $emp; break; }
    }
}
if (!$selectedEmpId && !empty($eligibleEmployees)) {
    $selectedEmpId = (int)$eligibleEmployees[0]['id'];
}

$employee = null; $ytd = []; $ytd13th = null;
if ($selectedEmpId) {
    foreach ($eligibleEmployees as $e) {
        if ((int)$e['id'] === $selectedEmpId) { $employee = $e; break; }
    }
    if ($employee) {
        $ytd     = Model::getPayrollYTD($selectedEmpId, $selectedYear . '-12-2');
        $ytd13th = Model::get13thMonthByEmployee($selectedEmpId, $selectedYear);
    }
}

// ── Computed figures ──────────────────────────────────────────────────────────
$totalBasic      = (float)($ytd['ytd_basic']        ?? 0);
$totalAllowance  = (float)($ytd['ytd_allowance']    ?? 0);
$totalGross      = (float)($ytd['ytd_gross']         ?? 0);
$totalSSS        = (float)($ytd['ytd_sss_ee']        ?? 0);
$totalPhilHealth = (float)($ytd['ytd_philhealth_ee'] ?? 0);
$totalPagIbig    = (float)($ytd['ytd_pagibig_ee']    ?? 0);
$totalTax        = (float)($ytd['ytd_tax']           ?? 0);
$thirteenth      = $ytd13th ? (float)($ytd13th['amount'] ?? 0) : 0.0;
$exemptThirteenth = min($thirteenth, 90000.0);
$taxableThirteenth = max(0, $thirteenth - 90000.0);
$govDeds         = $totalSSS + $totalPhilHealth + $totalPagIbig;
$totalNonTaxable = $exemptThirteenth + $govDeds;
// Item 19: Gross Compensation Income = basic + allowance + 13th month (total gross)
$item19 = $totalGross + $thirteenth;
// Item 20: Total Non-Taxable (gov deds + exempt 13th)
$item20 = $totalNonTaxable;
// Item 21: Taxable from present employer
$item21 = max(0, $item19 - $item20);
// Item 23: Gross Taxable
$item23 = $item21;
// Item 24: Tax Due
$item24 = $totalTax;
// Item 52: Total Taxable Compensation (basic + taxable 13th + allowance if taxable)
$item52 = max(0, $totalBasic - $govDeds) + $taxableThirteenth;

// Helper: format amount for form fields
function f2316(float $v): string { return $v > 0 ? number_format($v, 2) : ''; }

require_once __DIR__ . '/../layouts/admin_header.php';
?>

<style>
/* ── BIR 2316 Official Form Styles ──────────────────────────────────────── */
.bir-wrap { max-width: 960px; margin: 0 auto; }
.no-print { }
.bir-form {
  font-family: Arial, sans-serif;
  font-size: 8.5pt;
  color: #000;
  background: #fff;
  border: 1px solid #000;
  padding: 0;
  width: 100%;
}
/* Top bar */
.bir-topbar {
  display: flex; align-items: stretch;
  border-bottom: 1px solid #000;
}
.bir-topbar-left {
  padding: 4px 8px; font-size: 7pt; border-right: 1px solid #000;
  display: flex; flex-direction: column; justify-content: space-between; min-width: 90px;
}
.bir-topbar-center {
  flex: 1; text-align: center; padding: 6px 8px;
  border-right: 1px solid #000;
}
.bir-form-title-big { font-size: 20pt; font-weight: 900; line-height: 1; }
.bir-form-subtitle  { font-size: 11pt; font-weight: 700; }
.bir-form-desc      { font-size: 7pt; }
.bir-topbar-right {
  padding: 4px 8px; font-size: 7pt; min-width: 100px;
  display: flex; flex-direction: column; align-items: flex-end; justify-content: space-between;
}
/* Government header */
.bir-gov-header { text-align: center; padding: 3px 8px; font-size: 7.5pt; }
/* Main layout: two columns */
.bir-body { display: grid; grid-template-columns: 1fr 1fr; }
.bir-left  { border-right: 1px solid #000; }
.bir-right { }
/* Section headers */
.bir-section-hdr {
  background: #000; color: #fff;
  text-align: center; font-weight: 700;
  font-size: 7.5pt; padding: 2px 4px;
  border-bottom: 1px solid #000;
}
/* Field rows */
.bir-row {
  display: flex; align-items: stretch;
  border-bottom: 1px solid #000;
  min-height: 18px;
}
.bir-row:last-child { border-bottom: none; }
.bir-label {
  font-size: 6.5pt; color: #000;
  padding: 2px 4px; flex-shrink: 0;
  display: flex; align-items: flex-start; flex-direction: column;
}
.bir-num { font-size: 6pt; font-weight: 700; }
.bir-field-val {
  flex: 1; border-left: 1px solid #000;
  padding: 2px 4px; font-size: 8pt;
  display: flex; align-items: center;
}
.bir-field-val.right { justify-content: flex-end; font-weight: 600; }
/* Dual column row */
.bir-row-dual { display: grid; grid-template-columns: 1fr 1fr; border-bottom: 1px solid #000; }
.bir-col { border-right: 1px solid #000; padding: 2px 4px; font-size: 6.5pt; }
.bir-col:last-child { border-right: none; }
/* Amount rows in Part IV-B */
.bir-amount-row {
  display: flex; border-bottom: 1px solid #999; min-height: 15px;
}
.bir-amount-row:last-child { border-bottom: none; }
.bir-amount-label { flex: 1; padding: 1px 4px; font-size: 6.5pt; display: flex; align-items: center; }
.bir-amount-box   { width: 100px; border-left: 1px solid #000; padding: 1px 4px; font-size: 7pt; text-align: right; display: flex; align-items: center; justify-content: flex-end; }
/* Part IVA Summary */
.bir-sum-row {
  display: flex; border-bottom: 1px solid #999; min-height: 16px;
}
.bir-sum-label { flex: 1; padding: 2px 4px; font-size: 6.5pt; display: flex; align-items: center; }
.bir-sum-box   { width: 120px; border-left: 1px solid #000; padding: 2px 4px; font-size: 7pt; text-align: right; font-weight: 600; display: flex; align-items: center; justify-content: flex-end; }
/* Checkbox */
.bir-check { display: inline-block; width: 10px; height: 10px; border: 1px solid #000; margin-right: 3px; vertical-align: middle; text-align: center; line-height: 10px; font-size: 8pt; }
/* Certification */
.bir-cert { border-top: 1px solid #000; padding: 4px 8px; font-size: 6.5pt; }
.bir-sig  { border-top: 1px solid #000; margin-top: 20px; padding-top: 2px; font-size: 6.5pt; text-align: center; }
/* Footer */
.bir-footer-note { font-size: 6pt; padding: 2px 8px; border-top: 1px solid #000; }
/* Columns within sections */
.bir-inner-cols { display: grid; grid-template-columns: 1fr 1fr; }
.bir-inner-col-left  { border-right: 1px solid #000; }
.bir-inner-col-right { }

/* Part III */
.bir-part3 { border-top: 1px solid #000; }

/* Print */
@media print {
  .no-print, .main-header, .main-sidebar, .main-footer,
  .content-header, .breadcrumb, .page-title-bar { display: none !important; }
  .content-wrapper { margin-left: 0 !important; background: #fff !important; padding: 0 !important; }
  .bir-wrap { max-width: 100%; }
  body { font-size: 8pt !important; }
}
</style>

<div class="bir-wrap">

  <!-- Controls -->
  <div class="no-print mb-3">
    <div class="card">
      <div class="card-body py-3 d-flex flex-wrap align-items-center gap-3">
        <div>
          <label class="font-weight-600 mr-2">Year:</label>
          <select onchange="window.location='?year='+this.value+'&emp_id=<?= $selectedEmpId ?>'"
                  class="form-control form-control-sm d-inline-block" style="width:auto;">
            <?php for ($y = $currentYear; $y >= 2020; $y--): ?>
              <option value="<?= $y ?>" <?= $y === $selectedYear ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="font-weight-600 mr-2">Employee:</label>
          <select onchange="window.location='?year=<?= $selectedYear ?>&emp_id='+this.value"
                  class="form-control form-control-sm d-inline-block" style="width:auto;min-width:220px;">
            <?php foreach ($eligibleEmployees as $e): ?>
              <option value="<?= $e['id'] ?>" <?= (int)$e['id']===$selectedEmpId?'selected':'' ?>>
                <?= htmlspecialchars($e['employee_no'].' — '.$e['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button onclick="window.print()" class="btn btn-sm btn-primary ml-auto">
          <i class="fas fa-print mr-1"></i>Print Form
        </button>
      </div>
    </div>
  </div>

<?php if ($employee && !empty($ytd)): ?>

  <!-- ══════════════════════════════════════════════ BIR FORM 2316 ══ -->
  <div class="bir-form">

    <!-- Top bar -->
    <div class="bir-topbar">
      <div class="bir-topbar-left">
        <div>For BIR<br>Use Only<br>BCS/<br>Item:</div>
        <div style="font-size:11pt;font-weight:900;">2316</div>
      </div>
      <div class="bir-topbar-center">
        <div class="bir-gov-header">
          Republic of the Philippines &bull; Department of Finance &bull; <strong>Bureau of Internal Revenue</strong>
        </div>
        <div style="font-size:6.5pt;margin-bottom:2px;">BIR Form No.</div>
        <div class="bir-form-title-big">2316</div>
        <div class="bir-form-subtitle">Certificate of Compensation</div>
        <div class="bir-form-subtitle">Payment/Tax Withheld</div>
        <div class="bir-form-desc">For Compensation Payment With or Without Tax Withheld</div>
      </div>
      <div class="bir-topbar-right">
        <div style="font-size:7pt;">September 2021(ENCS)</div>
        <div style="font-size:8pt;font-weight:700;">2316 9/21ENCS</div>
      </div>
    </div>

    <!-- Instruction row -->
    <div style="padding:2px 8px;font-size:6.5pt;border-bottom:1px solid #000;">
      Fill in all applicable spaces. Mark all appropriate boxes with an &quot;X&quot;.
    </div>

    <!-- Item 1 & 2 -->
    <div class="bir-row" style="min-height:22px;">
      <div style="flex:1;padding:2px 8px;border-right:1px solid #000;font-size:6.5pt;">
        <span class="bir-num">1</span> For the Year <strong>(YYYY)</strong>
        <div style="font-size:10pt;font-weight:700;margin-top:1px;"><?= $selectedYear ?></div>
      </div>
      <div style="flex:2;padding:2px 8px;font-size:6.5pt;">
        <span class="bir-num">2</span> For the Period &nbsp;
        From <strong>(MM/DD)</strong>
        <span style="border:1px solid #000;padding:0 4px;margin:0 4px;">01/01</span>
        To <strong>(MM/DD)</strong>
        <span style="border:1px solid #000;padding:0 4px;margin:0 4px;">12/31</span>
      </div>
    </div>

    <!-- Two-column body -->
    <div class="bir-body">

      <!-- LEFT COLUMN: Parts I, II, III, IVA -->
      <div class="bir-left">

        <!-- Part I: Employee Information -->
        <div class="bir-section-hdr">Part I - Employee Information</div>

        <!-- Item 3: TIN -->
        <div class="bir-row" style="min-height:20px;">
          <div class="bir-label" style="min-width:120px;"><span class="bir-num">3</span> TIN</div>
          <div class="bir-field-val"><?= htmlspecialchars($employee['tin_no'] ?? '') ?></div>
        </div>

        <!-- Item 4 & 5 -->
        <div style="display:flex;border-bottom:1px solid #000;">
          <div style="flex:1;padding:2px 4px;border-right:1px solid #000;font-size:6.5pt;">
            <span class="bir-num">4</span> Employee's Name <em>(Last Name, First Name, Middle Name)</em><br>
            <strong style="font-size:8pt;"><?= htmlspecialchars($employee['name'] ?? '') ?></strong>
          </div>
          <div style="padding:2px 4px;min-width:80px;font-size:6.5pt;">
            <span class="bir-num">5</span> RDO Code<br>
            <span style="font-size:7pt;">—</span>
          </div>
        </div>

        <!-- Item 6: Registered Address -->
        <div class="bir-row" style="min-height:18px;">
          <div class="bir-label" style="min-width:120px;"><span class="bir-num">6</span> Registered Address</div>
          <div class="bir-field-val" style="font-size:7pt;"><?= htmlspecialchars($employee['address'] ?? '') ?></div>
        </div>
        <!-- 6A -->
        <div style="display:flex;border-bottom:1px solid #000;">
          <div style="flex:1;padding:2px 4px;border-right:1px solid #000;font-size:6.5pt;">
            <span class="bir-num">6B</span> Local Home Address
          </div>
          <div style="padding:2px 4px;min-width:80px;font-size:6.5pt;">
            <span class="bir-num">6C</span> ZIP Code
          </div>
        </div>
        <!-- 6D -->
        <div class="bir-row" style="min-height:16px;">
          <div class="bir-label"><span class="bir-num">6D</span> Foreign Address</div>
          <div class="bir-field-val"></div>
        </div>

        <!-- Item 7 & 8 -->
        <div style="display:flex;border-bottom:1px solid #000;">
          <div style="flex:1;padding:2px 4px;border-right:1px solid #000;font-size:6.5pt;">
            <span class="bir-num">7</span> Date of Birth <em>(MM/DD/YYYY)</em><br>
            <span style="font-size:7pt;">
              <?= !empty($employee['birthdate']) ? date('m/d/Y', strtotime($employee['birthdate'])) : '' ?>
            </span>
          </div>
          <div style="flex:1;padding:2px 4px;font-size:6.5pt;">
            <span class="bir-num">8</span> Contact Number<br>
            <span style="font-size:7pt;"><?= htmlspecialchars($employee['phone'] ?? '') ?></span>
          </div>
        </div>

        <!-- Item 9 & 10 -->
        <div style="display:flex;border-bottom:1px solid #000;">
          <div style="flex:1;padding:2px 4px;border-right:1px solid #000;font-size:6.5pt;">
            <span class="bir-num">9</span> Statutory Minimum Wage rate per day
          </div>
          <div style="flex:1;padding:2px 4px;font-size:6.5pt;">
            <span class="bir-num">10</span> Statutory Minimum Wage rate per month
          </div>
        </div>

        <!-- Item 11: MWE -->
        <div class="bir-row" style="min-height:16px;">
          <div style="padding:2px 4px;font-size:6.5pt;width:100%;">
            <span class="bir-num">11</span>
            <span class="bir-check"></span>
            Minimum Wage Earner (MWE) whose compensation is exempt from withholding tax and not subject to income tax
          </div>
        </div>

        <!-- Part II: Employer Information (Present) -->
        <div class="bir-section-hdr">Part II - Employer Information <em>(Present)</em></div>

        <!-- Item 12: TIN -->
        <div class="bir-row" style="min-height:18px;">
          <div class="bir-label" style="min-width:80px;"><span class="bir-num">12</span> TIN</div>
          <div class="bir-field-val">—</div>
        </div>
        <!-- Item 13 -->
        <div class="bir-row" style="min-height:18px;">
          <div class="bir-label" style="min-width:80px;"><span class="bir-num">13</span> Employer's Name</div>
          <div class="bir-field-val"><strong><?= htmlspecialchars(COMPANY_NAME) ?></strong></div>
        </div>
        <!-- Item 14 -->
        <div class="bir-row" style="min-height:18px;">
          <div class="bir-label" style="min-width:80px;"><span class="bir-num">14</span> Registered Address</div>
          <div class="bir-field-val" style="font-size:7pt;"><?= htmlspecialchars(COMPANY_ADDRESS) ?></div>
        </div>
        <div class="bir-row" style="min-height:14px;font-size:6.5pt;">
          <div style="padding:2px 8px;">
            <span class="bir-num">14A</span> ZIP Code: &nbsp;—
          </div>
        </div>

        <!-- Item 15: Type of Employer -->
        <div style="padding:2px 8px;border-bottom:1px solid #000;font-size:6.5pt;">
          <span class="bir-num">15</span> Type of Employer &nbsp;
          <span class="bir-check">✗</span> Main Employer &nbsp;
          <span class="bir-check"></span> Secondary Employer
        </div>

        <!-- Part III: Previous Employer -->
        <div class="bir-section-hdr">Part III - Employer Information <em>(Previous)</em></div>
        <div class="bir-row" style="min-height:16px;">
          <div class="bir-label" style="min-width:80px;"><span class="bir-num">16</span> TIN</div>
          <div class="bir-field-val"></div>
        </div>
        <div class="bir-row" style="min-height:16px;">
          <div class="bir-label" style="min-width:80px;"><span class="bir-num">17</span> Employer's Name</div>
          <div class="bir-field-val"></div>
        </div>
        <div class="bir-row" style="min-height:16px;">
          <div class="bir-label" style="min-width:80px;"><span class="bir-num">18</span> Registered Address</div>
          <div class="bir-field-val"></div>
        </div>
        <div class="bir-row" style="min-height:14px;font-size:6.5pt;">
          <div style="padding:2px 8px;"><span class="bir-num">18A</span> ZIP Code:</div>
        </div>

        <!-- Part IVA: Summary -->
        <div class="bir-section-hdr">Part IVA - Summary</div>

        <?php
        $sumRows = [
            ['19', 'Gross Compensation Income from Present Employer (Sum of Items 38 and 52)', f2316($item19)],
            ['20', 'Less: Total Non-Taxable/Exempt Compensation Income from Present Employer (From Item 38)', f2316($item20)],
            ['21', 'Taxable Compensation Income from Present Employer (Item 19 Less Item 20) (From Item 52)', f2316($item21)],
            ['22', 'Add: Taxable Compensation Income from Previous Employer, if applicable', ''],
            ['23', 'Gross Taxable Compensation Income (Sum of Items 21 and 22)', f2316($item23)],
            ['24', 'Tax Due', f2316($item24)],
        ];
        foreach ($sumRows as [$num,$lbl,$val]): ?>
        <div class="bir-sum-row">
          <div class="bir-sum-label"><span class="bir-num"><?= $num ?></span>&nbsp;<?= $lbl ?></div>
          <div class="bir-sum-box"><?= $val ?></div>
        </div>
        <?php endforeach; ?>

        <!-- Item 25 -->
        <div style="padding:2px 4px;border-bottom:1px solid #999;font-size:6.5pt;font-weight:700;">
          <span class="bir-num">25</span> Amount of Taxes Withheld
        </div>
        <div class="bir-sum-row">
          <div class="bir-sum-label">&nbsp;&nbsp;<span class="bir-num">25A</span> Present Employer</div>
          <div class="bir-sum-box"><?= f2316($totalTax) ?></div>
        </div>
        <div class="bir-sum-row">
          <div class="bir-sum-label">&nbsp;&nbsp;<span class="bir-num">25B</span> Previous Employer, if applicable</div>
          <div class="bir-sum-box"></div>
        </div>
        <div class="bir-sum-row">
          <div class="bir-sum-label"><span class="bir-num">26</span> Total Amount of Taxes Withheld as adjusted (Sum of Items 25A and 25B)</div>
          <div class="bir-sum-box"><?= f2316($totalTax) ?></div>
        </div>
        <div class="bir-sum-row">
          <div class="bir-sum-label"><span class="bir-num">27</span> 5% Tax Credit (PERA Act of 2008)</div>
          <div class="bir-sum-box"></div>
        </div>
        <div class="bir-sum-row">
          <div class="bir-sum-label"><span class="bir-num">28</span> Total Taxes Withheld <em>(Sum of Items 26 and 27)</em></div>
          <div class="bir-sum-box"><?= f2316($totalTax) ?></div>
        </div>

      </div><!-- /.bir-left -->

      <!-- RIGHT COLUMN: Part IV-B Details -->
      <div class="bir-right">
        <div class="bir-section-hdr">Part IV-B Details of Compensation Income &amp; Tax Withheld from Present Employer</div>

        <!-- A. NON-TAXABLE -->
        <div style="padding:2px 6px;background:#f0f0f0;border-bottom:1px solid #000;font-size:6.5pt;font-weight:700;">
          A. NON-TAXABLE/EXEMPT COMPENSATION INCOME &nbsp;&nbsp;&nbsp; <span style="float:right;">Amount</span>
        </div>

        <?php
        $nonTaxRows = [
            ['29', 'Basic Salary (including the exempt ₱250,000 &amp; below) or the Statutory Minimum Wage of the MWE', ''],
            ['30', 'Holiday Pay (MWE)', ''],
            ['31', 'Overtime Pay (MWE)', ''],
            ['32', 'Night Shift Differential (MWE)', ''],
            ['33', 'Hazard Pay (MWE)', ''],
            ['34', '13th Month Pay and Other Benefits (maximum of ₱90,000)', f2316($exemptThirteenth)],
            ['35', 'De Minimis Benefits', ''],
            ['36', 'SSS, GSIS, PHIC &amp; PAG-IBIG Contributions and Union Dues (Employee share only)', f2316($govDeds)],
            ['37', 'Salaries and Other Forms of Compensation', ''],
            ['38', 'Total Non-Taxable/Exempt Compensation Income (Sum of Items 29 to 37)', f2316($totalNonTaxable)],
        ];
        foreach ($nonTaxRows as [$num,$lbl,$val]): ?>
        <div class="bir-amount-row <?= $num==='38'?'':'' ?>" style="<?= $num==='38'?'border-top:1.5px solid #000;font-weight:700;':'' ?>">
          <div class="bir-amount-label"><span class="bir-num"><?= $num ?></span>&nbsp;<?= $lbl ?></div>
          <div class="bir-amount-box"><?= $val ?></div>
        </div>
        <?php endforeach; ?>

        <!-- B. TAXABLE -->
        <div style="padding:2px 6px;background:#f0f0f0;border-bottom:1px solid #000;border-top:1px solid #000;font-size:6.5pt;font-weight:700;">
          B. TAXABLE COMPENSATION INCOME &nbsp;&nbsp; <span style="float:right;">REGULAR</span>
        </div>

        <?php
        $taxableRows = [
            ['39', 'Basic Salary', f2316(max(0, $totalBasic - $govDeds))],
            ['40', 'Representation', ''],
            ['41', 'Transportation', ''],
            ['42', 'Cost of Living Allowance (COLA)', ''],
            ['43', 'Fixed Housing Allowance', ''],
            ['44', 'Others (specify)', ''],
            ['44A', '', ''],
            ['44B', 'SUPPLEMENTARY', ''],
            ['45', 'Commission', ''],
            ['46', 'Profit Sharing', ''],
            ['47', 'Fees Including Director\'s Fees', ''],
            ['48', 'Taxable 13th Month Benefits', f2316($taxableThirteenth)],
            ['49', 'Hazard Pay', ''],
            ['50', 'Overtime Pay', ''],
            ['51', 'Others (specify)', ''],
            ['51A', '', ''],
            ['51B', '', ''],
        ];
        foreach ($taxableRows as [$num,$lbl,$val]): ?>
        <div class="bir-amount-row">
          <div class="bir-amount-label">
            <span class="bir-num"><?= $num ?></span>
            <?php if ($num === '44B'): ?>
              &nbsp;<em>SUPPLEMENTARY</em>
            <?php else: ?>
              &nbsp;<?= $lbl ?>
            <?php endif; ?>
          </div>
          <div class="bir-amount-box"><?= $val ?></div>
        </div>
        <?php endforeach; ?>

        <!-- Item 52 -->
        <div class="bir-amount-row" style="border-top:1.5px solid #000;font-weight:700;">
          <div class="bir-amount-label">
            <span class="bir-num">52</span>&nbsp;Total Taxable Compensation Income (Sum of Items 39 to 51B)
          </div>
          <div class="bir-amount-box"><?= f2316($item52) ?></div>
        </div>

        <!-- Total tax withheld -->
        <div class="bir-amount-row" style="border-top:1px solid #000;background:#fffde7;">
          <div class="bir-amount-label">
            <span class="bir-num" style="color:#b45309;">53</span>
            &nbsp;<strong>Total Amount of Taxes Withheld as adjusted</strong>
          </div>
          <div class="bir-amount-box" style="font-weight:800;"><?= f2316($totalTax) ?></div>
        </div>

      </div><!-- /.bir-right -->
    </div><!-- /.bir-body -->

    <!-- Certification -->
    <div class="bir-cert">
      <p style="margin:0 0 4px;">
        I/We declare, under the penalties of perjury that this certificate has been made in good faith, verified by me/us, and to the best of my/our knowledge and belief, is true and correct, pursuant to
        the provisions of the National Internal Revenue Code, as amended, and the regulations issued under authority thereof. Further, I/we give my/our consent to the processing of my/our information
        as contemplated under the *Data Privacy Act of 2012 (R.A. No. 10173) for legitimate and lawful purposes.
      </p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">
        <div>
          <div class="bir-sig"><?= htmlspecialchars(COMPANY_NAME) ?> — Authorized Signatory</div>
          <div style="text-align:center;font-size:6.5pt;">Present Employer/Authorized Agent Signature over Printed Name</div>
          <div style="text-align:right;font-size:6.5pt;margin-top:4px;">
            Date Signed: <span style="border-bottom:1px solid #000;padding:0 20px;"></span>
          </div>
        </div>
        <div>
          <div style="font-size:7pt;font-weight:700;margin-bottom:2px;">CONFORME:</div>
          <div class="bir-sig">&nbsp;</div>
          <div style="text-align:center;font-size:6.5pt;">Employee Signature over Printed Name</div>
          <div style="text-align:right;font-size:6.5pt;margin-top:4px;">
            Date Signed: <span style="border-bottom:1px solid #000;padding:0 20px;"></span>
          </div>
        </div>
      </div>
      <div style="margin-top:6px;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:6.5pt;">
        <div style="border:1px solid #000;padding:4px;">
          CTC/Valid ID No. of Employee: <span style="border-bottom:1px solid #000;padding:0 30px;"></span><br>
          Place of Issue: <span style="border-bottom:1px solid #000;padding:0 30px;"></span><br>
          Date Issued: <span style="border-bottom:1px solid #000;padding:0 30px;"></span><br>
          Amount paid, if CTC: <span style="border-bottom:1px solid #000;padding:0 20px;"></span>
        </div>
        <div style="border:1px solid #000;padding:4px;background:#fffde7;">
          <strong>To be accomplished under substituted filing</strong><br>
          I declare, under the penalties of perjury that I am qualified under substituted filing of Income Tax Return
          (BIR Form No. 1700), since I received purely compensation income from only one employer in the Philippines
          for the calendar year; that taxes have been correctly withheld by my employer (tax due equals tax withheld)...
        </div>
      </div>
    </div>

    <!-- Substituted filing signatures -->
    <div style="display:grid;grid-template-columns:1fr 1fr;border-top:1px solid #000;">
      <div style="padding:4px 8px;border-right:1px solid #000;font-size:6.5pt;">
        <div style="margin-bottom:2px;">I declare, under the penalties of perjury that the information herein stated are
        reported under BIR Form No. 1604-C which has been filed with the Bureau of Internal Revenue.</div>
        <div class="bir-sig" style="margin-top:16px;"><?= htmlspecialchars(COMPANY_NAME) ?></div>
        <div style="text-align:center;font-size:6pt;">
          <span class="bir-num">55</span>&nbsp;Present Employer/Authorized Agent Signature over Printed Name<br>
          (Head of Accounting/Human Resource or Authorized Representative)
        </div>
      </div>
      <div style="padding:4px 8px;font-size:6.5pt;">
        <div>&nbsp;</div>
        <div class="bir-sig" style="margin-top:16px;">&nbsp;</div>
        <div style="text-align:center;font-size:6pt;">
          <span class="bir-num">56</span>&nbsp;Employee Signature over Printed Name
        </div>
      </div>
    </div>

    <div class="bir-footer-note">
      *NOTE: The BIR Data Privacy is in the BIR website (www.bir.gov.ph)
    </div>

  </div><!-- /.bir-form -->

  <!-- Data summary (no-print) -->
  <div class="no-print mt-3">
    <div class="card">
      <div class="card-header"><i class="fas fa-info-circle mr-2 text-info"></i>Data Summary — <?= $selectedYear ?></div>
      <div class="card-body">
        <div class="row">
          <?php
          $summary = [
            'Total Basic Pay'         => '₱'.number_format($totalBasic,2),
            'Total Allowance'         => '₱'.number_format($totalAllowance,2),
            '13th Month Pay'          => '₱'.number_format($thirteenth,2),
            'SSS (EE)'                => '₱'.number_format($totalSSS,2),
            'PhilHealth (EE)'         => '₱'.number_format($totalPhilHealth,2),
            'Pag-IBIG (EE)'           => '₱'.number_format($totalPagIbig,2),
            'Exempt 13th (max ₱90k)'  => '₱'.number_format($exemptThirteenth,2),
            'Total Non-Taxable'       => '₱'.number_format($totalNonTaxable,2),
            'Taxable Compensation'    => '₱'.number_format($item52,2),
            'Withholding Tax Withheld'=> '₱'.number_format($totalTax,2),
          ];
          foreach ($summary as $k=>$v): ?>
          <div class="col-md-4 mb-1">
            <small class="text-muted"><?= $k ?>:</small>
            <strong class="ml-1"><?= $v ?></strong>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($selectedEmpId): ?>
  <div class="alert alert-warning">
    No payroll records found for this employee in <?= $selectedYear ?>.
  </div>
<?php else: ?>
  <div class="alert alert-info">No employees with payroll records for <?= $selectedYear ?>.</div>
<?php endif; ?>

</div><!-- /.bir-wrap -->

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>