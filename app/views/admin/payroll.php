<?php
// ===========================================================================
//  ALL POST HANDLERS MUST BE BEFORE ANY HTML OUTPUT
// ===========================================================================
$pageTitle  = 'Payroll Processing';
$breadcrumb = 'Payroll';
$activeMenu = 'payroll';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('ROLE_ADMIN'))   require_once __DIR__ . '/../../../config/config.php';
if (!defined('DB_HOST'))      require_once __DIR__ . '/../../../config/database.php';
if (!class_exists('Database'))             require_once __DIR__ . '/../../../core/Database.php';
if (!class_exists('Model'))                require_once __DIR__ . '/../../../core/Model.php';
if (!class_exists('PhilippineDeductions')) require_once __DIR__ . '/../../../core/PhilippineDeductions.php';
// Admin and management only — guard before any POST processing
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) {
    header('Location: ' . BASE_URL . '/index.php?error=access_denied'); exit;
}

// Core files (Database, Model, PhilippineDeductions) are loaded by the router
// before this view is included — do NOT require them here directly.

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// (msg is set by POST handlers above, or empty for GET requests)

// ===========================================================================
//  POST: GENERATE PAYROLL
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_payroll'])) {

    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Invalid security token. Please refresh and try again.</div>";
    } else {
        $genPeriod      = trim($_POST['gen_period'] ?? '');
        $selectedEmpIds = $_POST['employee_ids'] ?? [];
        $maxAllowed     = date('Y-m', strtotime('+1 month')); // compare against period base

        if (!preg_match('/^\d{4}-\d{2}-[12]$/', $genPeriod)) {
            $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Invalid payroll period format.</div>";
        } elseif (Model::periodBase($genPeriod) > $maxAllowed) {
            $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Cannot generate payroll more than one month in the future.</div>";
        } elseif (empty($selectedEmpIds)) {
            $msg = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle mr-2'></i>No employees selected.</div>";
        } else {
            $generated   = 0;
            $skipped     = 0;
            $skipReasons = [];
            $workingDays = WORKING_DAYS;
            $db          = Database::getInstance();
            $db->beginTransaction();
            $txSuccess = false;

            try {
                foreach ($selectedEmpIds as $empId) {
                    $empId = (int)$empId;
                    $emp   = Model::findEmployeeById($empId);

                    if (!$emp) {
                        $skipped++;
                        $skipReasons[] = "ID:{$empId} - employee not found";
                        continue;
                    }
                    if ($emp['status'] !== 'active') {
                        $skipped++;
                        $skipReasons[] = htmlspecialchars($emp['name']) . " - not active ({$emp['status']})";
                        continue;
                    }
                    if (Model::employeeExistsInPeriod($empId, $genPeriod)) {
                        $skipped++;
                        $skipReasons[] = htmlspecialchars($emp['name']) . " - already has a record for {$genPeriod}";
                        continue;
                    }

                    // ── Load per-employee payroll settings ──────────────
                    $settings        = Model::getEmployeePayrollSettings($empId);
                    $fixedAmount     = $settings['cutoff1_fixed_amount'] !== null ? (float)$settings['cutoff1_fixed_amount'] : null;
                    $taxMethod       = $settings['tax_method'];
                    $govMode         = $settings['gov_deduction_mode'];
                    $cutoffNum       = Model::periodCutoff($genPeriod);

                    // ── Attendance absent deduction (based on half-month rate) ──
                    $attendance      = Model::getAttendanceSummary($empId, Model::periodBase($genPeriod));
                    $daysAbsent      = (int)($attendance['days_absent'] ?? 0);
                    $daysHalf        = (int)($attendance['days_half']   ?? 0);
                    // Daily rate based on half-month (11 working days per cutoff)
                    $halfMonthDays   = round($workingDays / 2);
                    $dailyRate       = $halfMonthDays > 0 ? (float)$emp['basic_salary'] / $workingDays : 0.0;
                    $absentDeduction = round(($daysAbsent * $dailyRate) + ($daysHalf * $dailyRate * 0.5), 2);

                    // ── 13th Month Pay — December 1st cutoff only ──────────
                    $thirteenthAmount = 0.0;
                    if (Model::isDecember1stCutoff($genPeriod)) {
                        $rec13 = Model::get13thMonthByEmployee($empId, Model::periodYear($genPeriod));
                        if ($rec13 && $rec13['status'] === 'pending') {
                            $thirteenthAmount = (float)$rec13['amount'];
                        }
                    }

                    // ── Year-end reconciliation — December 2nd cutoff ──────
                    $reconciliation = 0.0;
                    if (Model::isDecember2ndCutoff($genPeriod)) {
                        $year          = Model::periodYear($genPeriod);
                        $annualBasic   = Model::getTotalBasicByYear($empId, $year) + ((float)$emp['basic_salary'] / 2);
                        $annualGovDeds = Model::getTotalGovDedsByYear($empId, $year);
                        $annualTaxPaid = Model::getTotalWithholdingTaxByYear($empId, $year);
                        $reconciliation = PhilippineDeductions::computeYearEndReconciliation(
                            $annualBasic, $annualGovDeds, $annualTaxPaid
                        );
                    }

                    // ── Compute cutoff deductions ───────────────────────────
                    if ($cutoffNum === 1) {
                        $deductions = PhilippineDeductions::computeFirstCutoff(
                            (float)$emp['basic_salary'],
                            (float)($emp['allowance'] ?? 0),
                            $fixedAmount,
                            $taxMethod,
                            $thirteenthAmount,
                            $absentDeduction
                        );
                    } else {
                        $deductions = PhilippineDeductions::computeSecondCutoff(
                            (float)$emp['basic_salary'],
                            (float)($emp['allowance'] ?? 0),
                            $fixedAmount,
                            $taxMethod,
                            $govMode,
                            $absentDeduction,
                            $reconciliation
                        );
                    }

                    $record = [
                        'employee_id'      => $empId,
                        'period'           => $genPeriod,
                        'basic_salary'     => $deductions['basic_salary'],
                        'allowance'        => $deductions['allowance'],
                        'gross_pay'        => $deductions['gross_pay'],
                        'sss_msc'          => $deductions['sss_msc'],
                        'sss_ee'           => $deductions['sss_ee'],
                        'sss_er'           => $deductions['sss_er'],
                        'philhealth_mbs'   => $deductions['philhealth_mbs'],
                        'philhealth_ee'    => $deductions['philhealth_ee'],
                        'philhealth_er'    => $deductions['philhealth_er'],
                        'pagibig_mfs'      => $deductions['pagibig_mfs'],
                        'pagibig_ee'       => $deductions['pagibig_ee'],
                        'pagibig_er'       => $deductions['pagibig_er'],
                        'taxable_income'   => $deductions['taxable_income'],
                        'withholding_tax'  => $deductions['withholding_tax'],
                        'other_deductions' => round($deductions['absent_deduction'] + ($deductions['reconciliation'] ?? 0), 2),
                        'total_deductions' => $deductions['total_deductions'],
                        'net_pay'          => $deductions['net_pay'],
                        'remarks'          => ($thirteenthAmount > 0 ? '13th month included. ' : '')
                                           . ($reconciliation != 0 ? 'Year-end tax reconciliation: ' . ($reconciliation > 0 ? '+' : '') . number_format($reconciliation, 2) . '.' : ''),
                        'status'           => 'pending',
                        'processed_by'     => $_SESSION['user_id'],
                    ];

                    if (Model::createPayrollRecord($record)) {
                        $generated++;
                    } else {
                        $skipped++;
                        $skipReasons[] = htmlspecialchars($emp['name']) . " - DB insert failed";
                    }
                }

                $db->commit();
                $txSuccess = true;

            } catch (Exception $e) {
                $db->rollBack();
                error_log("Payroll generation error for period {$genPeriod}: " . $e->getMessage());
                $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Payroll generation failed due to a database error. No records were saved. Please try again.</div>";
            }

            if ($txSuccess) {
                Model::log($_SESSION['user_id'], 'GENERATE_PAYROLL',
                    "Generated payroll for period {$genPeriod}: {$generated} records created, {$skipped} skipped.");
                $skipParam = !empty($skipReasons) ? '&skipreasons=' . urlencode(implode('|', $skipReasons)) : '';
                header("Location: payroll.php?period={$genPeriod}&msg=generated&count={$generated}&skipped={$skipped}{$skipParam}");
                exit;
            }
        }
    }
}

// ===========================================================================
//  POST: RELEASE SINGLE PAYROLL RECORD
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_single'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Invalid security token.</div>";
    } else {
        $releaseId     = (int)($_POST['payroll_id']    ?? 0);
        $releasePeriod = trim($_POST['release_period'] ?? '');
        if ($releaseId && Model::releasePayroll($releaseId)) {
            $payRecord = Model::findPayrollById($releaseId);
            $empName   = $payRecord['employee_name'] ?? "ID:{$releaseId}";
            Model::log($_SESSION['user_id'], 'RELEASE_PAYROLL',
                "Released payroll ID:{$releaseId} for {$empName} period {$releasePeriod}");
            header("Location: payroll.php?period={$releasePeriod}&msg=released&name=" . urlencode($empName));
            exit;
        }
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Failed to release record.</div>";
    }
}

// ===========================================================================
//  POST: RELEASE ALL PENDING FOR PERIOD
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_all'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Invalid security token.</div>";
    } else {
        $releasePeriod = trim($_POST['release_period'] ?? '');
        if ($releasePeriod && Model::releaseAllPayrollForPeriod($releasePeriod)) {
            Model::log($_SESSION['user_id'], 'RELEASE_ALL_PAYROLL',
                "Released all payroll for period {$releasePeriod}");
            header("Location: payroll.php?period={$releasePeriod}&msg=released_all");
            exit;
        }
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Failed to release records.</div>";
    }
}


require_once __DIR__ . '/../layouts/admin_header.php';

$msg = '';

// ===========================================================================
//  SETUP: Period selection and data
// ===========================================================================
// Default to the current cutoff based on today's date
$todayCutoff     = date('j') <= 15 ? '1' : '2';
$selectedPeriod  = $_GET['period'] ?? (date('Y-m') . '-' . $todayCutoff);
$existingPeriods = Model::getPayrollPeriods();

// Build semi-monthly period options: last 6 months × 2 cutoffs = 12 options
$periodOptions = [];
for ($i = 0; $i < 6; $i++) {
    $base = date('Y-m', strtotime("-{$i} months"));
    $periodOptions[] = $base . '-2';  // 16th–end
    $periodOptions[] = $base . '-1';  // 1st–15th
}
// Merge in any existing periods from DB that may be older
$periodOptions = array_unique(array_merge($periodOptions, $existingPeriods));
// Sort descending (newest first) — works because YYYY-MM-C sorts correctly as string
usort($periodOptions, fn($a, $b) => strcmp($b, $a));

// Flash messages from redirect
if (!$msg) {
    $msgParam = $_GET['msg'] ?? '';
    if ($msgParam === 'generated') {
        $count    = (int)($_GET['count']   ?? 0);
        $skipped  = (int)($_GET['skipped'] ?? 0);
        $skipNote = '';
        if ($skipped > 0) {
            $reasons = array_filter(explode('|', urldecode($_GET['skipreasons'] ?? '')));
            $reasonHtml = '';
            if (!empty($reasons)) {
                $reasonHtml = '<ul class="mb-0 mt-1 payroll-skip-list">';
                foreach ($reasons as $r) {
                    $reasonHtml .= '<li>' . htmlspecialchars($r) . '</li>';
                }
                $reasonHtml .= '</ul>';
            }
            $skipNote = " <strong>{$skipped} skipped</strong>{$reasonHtml}";
        }
        $periodLabel = Model::periodLabel($selectedPeriod);
        $autoDismiss = $skipped === 0 ? 'alert-auto-dismiss' : '';
        $msg = "<div class='alert alert-success {$autoDismiss}'><i class='fas fa-check-circle mr-2'></i><strong>{$count}</strong> payroll record(s) created for <strong>{$periodLabel}</strong>.{$skipNote}</div>";
    } elseif ($msgParam === 'released') {
        $name = htmlspecialchars($_GET['name'] ?? 'Employee');
        $msg  = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>Payroll released for <strong>{$name}</strong>.</div>";
    } elseif ($msgParam === 'released_all') {
        $msg = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>All pending payroll records released.</div>";
    }
}

// Load data for current view
$employees        = Model::getAllEmployees('active');
$periodPayroll    = Model::getPayrollByPeriod($selectedPeriod);
$totalGross       = array_sum(array_column($periodPayroll, 'gross_pay'));
$totalDed         = array_sum(array_column($periodPayroll, 'total_deductions'));
$totalNet         = array_sum(array_column($periodPayroll, 'net_pay'));
$pendingList      = array_filter($periodPayroll, fn($p) => $p['status'] === 'pending');
$releasedList     = array_filter($periodPayroll, fn($p) => $p['status'] === 'released');
$alreadyGenerated = Model::periodExists($selectedPeriod);
?>

<?= $msg ?>

<!-- Period Selector -->
<div class="card card-primary card-outline mb-3">
  <div class="card-body py-3">
    <div class="d-flex align-items-center flex-wrap">
      <label class="mb-0 font-weight-bold mr-2">Payroll Period:</label>
      <select id="periodSelect" class="form-control mr-3 payroll-period-select-md"
              onchange="window.location='payroll.php?period='+this.value">
        <?php foreach ($periodOptions as $p): ?>
          <option value="<?= $p ?>" <?= $p === $selectedPeriod ? 'selected' : '' ?>>
            <?= Model::periodLabel($p) ?><?= in_array($p, $existingPeriods) ? ' ✓' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>

      <?php if (!$alreadyGenerated): ?>
        <button class="btn btn-success mr-2" data-toggle="modal" data-target="#generateModal">
          <i class="fas fa-cogs mr-1"></i> Generate Payroll
        </button>
      <?php else: ?>
        <span class="badge badge-primary px-3 py-2 mr-2 payroll-skip-list">
          <i class="fas fa-check mr-1"></i> Payroll Generated
        </span>
        <?php if (count($pendingList) > 0): ?>
          <button class="btn btn-warning mr-2" data-toggle="modal" data-target="#releaseAllModal">
            <i class="fas fa-paper-plane mr-1"></i> Release All
          </button>
        <?php endif; ?>
      <?php endif; ?>

      <button class="btn btn-info ml-auto" onclick="window.print()">
        <i class="fas fa-print mr-1"></i> Print
      </button>
    </div>
  </div>
</div>

<!-- Summary Cards -->
<div class="row">
  <div class="col-md-3">
    <div class="info-box">
      <span class="info-box-icon bg-primary"><i class="fas fa-file-invoice"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Total Records</span>
        <span class="info-box-number"><?= count($periodPayroll) ?></span>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="info-box">
      <span class="info-box-icon bg-warning"><i class="fas fa-money-bill-alt"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Total Gross Pay</span>
        <span class="info-box-number">&#8369;<?= number_format($totalGross, 0) ?></span>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="info-box">
      <span class="info-box-icon bg-danger"><i class="fas fa-minus-circle"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Total Deductions</span>
        <span class="info-box-number">&#8369;<?= number_format($totalDed, 0) ?></span>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="info-box">
      <span class="info-box-icon bg-success"><i class="fas fa-hand-holding-usd"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Total Net Pay</span>
        <span class="info-box-number">&#8369;<?= number_format($totalNet, 0) ?></span>
      </div>
    </div>
  </div>
</div>

<!-- Payroll Table -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fas fa-table mr-2"></i>
      Payroll Records &mdash; <?= date('F Y', strtotime($selectedPeriod . '-01')) ?>
    </h3>
    <div class="card-tools d-flex align-items-center">
      <span class="badge badge-warning mr-2"><?= count($pendingList) ?> Pending</span>
      <span class="badge badge-success mr-3"><?= count($releasedList) ?> Released</span>
      <?php if (!empty($periodPayroll)): ?>
      <a href="payroll_export.php?period=<?= urlencode($selectedPeriod) ?>&format=pdf"
         target="_blank"
         class="btn btn-xs btn-outline-danger mr-1"
         title="Print / Export as PDF">
        <i class="fas fa-file-pdf mr-1"></i>PDF
      </a>
      <a href="payroll_export.php?period=<?= urlencode($selectedPeriod) ?>&format=excel"
         class="btn btn-xs btn-outline-success"
         title="Export as Excel">
        <i class="fas fa-file-excel mr-1"></i>Excel
      </a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body p-0">
    <?php if (empty($periodPayroll)): ?>
      <div class="p-5 text-center text-muted">
        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
        No payroll records for <strong><?= date('F Y', strtotime($selectedPeriod . '-01')) ?></strong>.<br>
        <small>Click <strong>"Generate Payroll"</strong> above to begin.</small>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Department</th>
            <th>Basic Salary</th>
            <th>Allowance</th>
            <th>Gross Pay</th>
            <th>SSS</th>
            <th>PhilHealth</th>
            <th>Pag-IBIG</th>
            <th>W. Tax</th>
            <th>Absent Ded.</th>
            <th>Total Ded.</th>
            <th class="text-success">Net Pay</th>
            <th>Status</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($periodPayroll as $p): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($p['employee_name']) ?></strong><br>
              <small class="text-muted"><?= htmlspecialchars($p['employee_no']) ?></small>
            </td>
            <td><?= htmlspecialchars($p['department']) ?></td>
            <td>&#8369;<?= number_format($p['basic_salary'], 2) ?></td>
            <td>&#8369;<?= number_format($p['allowance'], 2) ?></td>
            <td>&#8369;<?= number_format($p['gross_pay'], 2) ?></td>
            <td class="text-danger">&#8369;<?= number_format($p['sss_ee'], 2) ?></td>
            <td class="text-danger">&#8369;<?= number_format($p['philhealth_ee'], 2) ?></td>
            <td class="text-danger">&#8369;<?= number_format($p['pagibig_ee'], 2) ?></td>
            <td class="text-danger">&#8369;<?= number_format($p['withholding_tax'], 2) ?></td>
            <td class="text-danger">&#8369;<?= number_format($p['other_deductions'] ?? 0, 2) ?></td>
            <td class="text-danger font-weight-bold">&#8369;<?= number_format($p['total_deductions'], 2) ?></td>
            <td class="text-success font-weight-bold">&#8369;<?= number_format($p['net_pay'], 2) ?></td>
            <td>
              <?= $p['status'] === 'released'
                ? '<span class="badge badge-success">Released</span>'
                : '<span class="badge badge-warning">Pending</span>' ?>
            </td>
            <td class="text-center payroll-actions-cell">
              <a href="payslip.php?emp=<?= $p['employee_id'] ?>&period=<?= $selectedPeriod ?>"
                 class="btn btn-sm btn-info" title="View Payslip">
                <i class="fas fa-receipt"></i>
              </a>
              <?php if ($p['status'] === 'pending'): ?>
              <form method="POST" class="action-form-inline"
                    onsubmit="return confirm('Release payroll for <?= htmlspecialchars(addslashes($p['employee_name'])) ?>?')">
                <input type="hidden" name="release_single"  value="1">
                <input type="hidden" name="payroll_id"      value="<?= $p['id'] ?>">
                <input type="hidden" name="release_period"  value="<?= $selectedPeriod ?>">
                <input type="hidden" name="csrf_token"      value="<?= htmlspecialchars($csrf_token) ?>">
                <button type="submit" class="btn btn-sm btn-success" title="Release Payslip">
                  <i class="fas fa-paper-plane"></i>
                </button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-light">
          <tr>
            <th colspan="4" class="text-right">TOTALS</th>
            <th>&#8369;<?= number_format($totalGross, 2) ?></th>
            <th colspan="6"></th>
            <th class="text-danger">&#8369;<?= number_format($totalDed, 2) ?></th>
            <th class="text-success">&#8369;<?= number_format($totalNet, 2) ?></th>
            <th colspan="2"></th>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- GENERATE PAYROLL MODAL -->
<div class="modal fade" id="generateModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fas fa-cogs mr-2"></i>Generate Payroll</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form method="POST" onsubmit="return confirmGenerate()">
        <input type="hidden" name="generate_payroll" value="1">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="modal-body">
          <div class="alert alert-info mb-3">
            <i class="fas fa-info-circle mr-1"></i>
            Review employees below. Deductions are computed from current salary data.
            Absent deductions are applied automatically from attendance records.
          </div>
          <div class="form-group row align-items-center mb-3">
            <label class="col-sm-3 col-form-label font-weight-bold">Payroll Period</label>
            <div class="col-sm-6">
              <div class="input-group">
                <input type="month" id="genPeriodMonth"
                       class="form-control"
                       value="<?= Model::periodBase($selectedPeriod) ?>"
                       min="2020-01"
                       max="<?= date('Y-m', strtotime('+1 month')) ?>"
                       required>
                <select id="genPeriodCutoff" class="form-control payroll-cutoff-select">
                  <option value="1" <?= Model::periodCutoff($selectedPeriod) === 1 ? 'selected' : '' ?>>1st Cutoff (1–15)</option>
                  <option value="2" <?= Model::periodCutoff($selectedPeriod) === 2 ? 'selected' : '' ?>>2nd Cutoff (16–End)</option>
                </select>
              </div>
              <input type="hidden" name="gen_period" id="genPeriodFinal" value="<?= htmlspecialchars($selectedPeriod) ?>">
              <small class="text-muted mt-1 d-block">13th month pay is automatically included in the <strong>December 2nd cutoff</strong>.</small>
            </div>
          </div>
          <div class="table-responsive payroll-scroll-table">
            <table class="table table-sm table-bordered mb-0">
              <thead class="thead-light">
                <tr>
                  <th class="payroll-cb-col">
                    <input type="checkbox" id="checkAll" checked title="Select/deselect all">
                  </th>
                  <th>Employee</th>
                  <th>Department</th>
                  <th>Basic Salary</th>
                  <th>Gross Pay</th>
                  <th class="text-danger">Deductions</th>
                  <th class="text-success">Est. Net Pay</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($employees as $e):
                $c = Model::computePayroll($e);
              ?>
                <tr>
                  <td class="text-center">
                    <input type="checkbox" name="employee_ids[]"
                           value="<?= $e['id'] ?>" class="emp-check" checked>
                  </td>
                  <td>
                    <strong><?= htmlspecialchars($e['name']) ?></strong><br>
                    <small class="text-muted"><?= $e['employee_no'] ?></small>
                  </td>
                  <td><?= htmlspecialchars($e['department'] ?? '-') ?></td>
                  <td>&#8369;<?= number_format($e['basic_salary'], 2) ?></td>
                  <td>&#8369;<?= number_format($c['gross_pay'], 2) ?></td>
                  <td class="text-danger">&#8369;<?= number_format($c['total_deductions'], 2) ?></td>
                  <td class="text-success font-weight-bold">&#8369;<?= number_format($c['net_pay'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($employees)): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">No active employees found.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i> Cancel
          </button>
          <button type="submit" class="btn btn-success" id="generateBtn">
            <i class="fas fa-play mr-1"></i> Confirm &amp; Generate
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- RELEASE ALL MODAL -->
<div class="modal fade" id="releaseAllModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="fas fa-paper-plane mr-2"></i>Release All Payroll</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form method="POST">
        <input type="hidden" name="release_all"    value="1">
        <input type="hidden" name="release_period" value="<?= $selectedPeriod ?>">
        <input type="hidden" name="csrf_token"     value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="modal-body">
          <p>You are about to release <strong><?= count($pendingList) ?> pending</strong> payroll record(s) for
             <strong><?= date('F Y', strtotime($selectedPeriod . '-01')) ?></strong>.</p>
          <p class="text-muted mb-0">Once released, employees can view their payslips. This cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning">
            <i class="fas fa-paper-plane mr-1"></i> Release All
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$existingPeriodsJson = json_encode($existingPeriods);
$existingPeriodsJs   = htmlspecialchars($existingPeriodsJson, ENT_QUOTES);
$maxPeriod = date('Y-m', strtotime('+1 month'));

$extraJs = <<<JSEOF
document.getElementById('checkAll').addEventListener('change', function () {
    document.querySelectorAll('.emp-check').forEach(function(cb) { cb.checked = this.checked; }, this);
});
document.querySelectorAll('.emp-check').forEach(function(cb) {
    cb.addEventListener('change', function () {
        var all     = document.querySelectorAll('.emp-check').length;
        var checked = document.querySelectorAll('.emp-check:checked').length;
        document.getElementById('checkAll').checked = (all === checked);
    });
});

// Semi-monthly period builder — combine month + cutoff into YYYY-MM-C
function buildPeriod() {
    var month  = document.getElementById('genPeriodMonth').value;   // YYYY-MM
    var cutoff = document.getElementById('genPeriodCutoff').value;  // 1 or 2
    if (!month) return '';
    return month + '-' + cutoff;  // e.g. 2026-12-2
}
function syncPeriod() {
    var period = buildPeriod();
    document.getElementById('genPeriodFinal').value = period;
    var existingPeriods = $existingPeriodsJson;
    var btn = document.getElementById('generateBtn');
    if (period && existingPeriods.indexOf(period) !== -1) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-ban mr-1"></i> Already Generated';
    } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play mr-1"></i> Confirm & Generate';
    }
}
document.getElementById('genPeriodMonth').addEventListener('change', syncPeriod);
document.getElementById('genPeriodCutoff').addEventListener('change', syncPeriod);
syncPeriod();

function confirmGenerate() {
    var period  = buildPeriod();
    var checked = document.querySelectorAll('.emp-check:checked').length;
    if (!period) { alert('Please select a payroll period.'); return false; }
    if (checked === 0) { alert('Please select at least one employee.'); return false; }
    var cutoffLabel = document.getElementById('genPeriodCutoff').selectedOptions[0].text;
    return confirm('Generate payroll for ' + checked + ' employee(s)?\\nPeriod: ' + period + ' (' + cutoffLabel + ')\\nThis cannot be undone.');
}
JSEOF;

require_once __DIR__ . '/../layouts/admin_footer.php';
?>