<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('ROLE_ADMIN')) require_once __DIR__ . '/../../../config/config.php';
// Admin and management only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) {
    header('Location: ' . BASE_URL . '/index.php?error=access_denied'); exit;
}

$pageTitle = '13th Month Pay';
require_once __DIR__ . '/../layouts/admin_header.php';

$msg = '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ── Year selector ─────────────────────────────────────────────────────────────
$currentYear = (int) date('Y');
$selectedYear = isset($_GET['year']) && is_numeric($_GET['year'])
    ? (int) $_GET['year']
    : $currentYear;
// Clamp to reasonable range
$selectedYear = max(2020, min($currentYear, $selectedYear));

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token. Please refresh and try again.</div>";

    } elseif (isset($_POST['generate_13th'])) {
        // Compute and save 13th month records for all eligible employees
        $computed = Model::compute13thMonth($selectedYear);
        $saved    = 0;
        foreach ($computed as $row) {
            Model::save13thMonthRecord([
                'employee_id'        => $row['employee_id'],
                'year'               => $selectedYear,
                'total_basic_earned' => $row['total_basic_earned'],
                'months_worked'      => $row['months_worked'],
                'amount'             => round($row['thirteenth_month_pay'], 2),
                'status'             => 'pending',
                'processed_by'       => $_SESSION['user_id'],
            ]);
            $saved++;
        }
        Model::log($_SESSION['user_id'], 'GENERATE_13TH_MONTH', "Generated 13th month pay for {$selectedYear} — {$saved} record(s)");
        $msg = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i><strong>{$saved}</strong> 13th month pay record(s) computed for <strong>{$selectedYear}</strong>.</div>";

    } elseif (isset($_POST['release_one'])) {
        $recordId = (int)($_POST['record_id'] ?? 0);
        if ($recordId) {
            Model::release13thMonth($recordId);
            Model::log($_SESSION['user_id'], 'RELEASE_13TH_MONTH', "Released 13th month pay record ID:{$recordId} for {$selectedYear}");
            $msg = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>13th month pay released.</div>";
        }

    } elseif (isset($_POST['release_all'])) {
        Model::releaseAll13thMonth($selectedYear);
        Model::log($_SESSION['user_id'], 'RELEASE_ALL_13TH_MONTH', "Released all pending 13th month pay for {$selectedYear}");
        $msg = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>All pending 13th month pay released for <strong>{$selectedYear}</strong>.</div>";
    }
}

// ── Load data ─────────────────────────────────────────────────────────────────
$filterDept13   = $_GET['dept'] ?? '';
$allDepartments13 = Model::getAllDepartments();
$alreadyGenerated = Model::thirteenthMonthExists($selectedYear);
$records          = $alreadyGenerated ? Model::get13thMonthByYear($selectedYear) : [];
$preview          = !$alreadyGenerated ? Model::compute13thMonth($selectedYear) : [];

// Apply department filter
if ($filterDept13 !== '') {
    $records = array_values(array_filter($records, function($r) use ($filterDept13) {
        $emp = Model::findEmployeeById((int)$r['employee_id']);
        return $emp && (int)$emp['department_id'] === (int)$filterDept13;
    }));
    $preview = array_values(array_filter($preview, function($r) use ($filterDept13) {
        $emp = Model::findEmployeeById((int)$r['employee_id']);
        return $emp && (int)$emp['department_id'] === (int)$filterDept13;
    }));
}

$totalAmount  = array_sum(array_column($records, 'amount'));
$pendingCount = count(array_filter($records, fn($r) => $r['status'] === 'pending'));
$totalPreview = array_sum(array_column($preview, 'thirteenth_month_pay'));

// Tax exemption threshold (TRAIN Law)
$TAX_EXEMPT_LIMIT = 90000.00;
?>

<div class="page-title-bar">
    <i class="fas fa-gift text-primary"></i>
    <h1>13th Month Pay</h1>
    <small class="text-muted ml-2">PD 851 Compliance</small>
</div>

<?= $msg ?>

<!-- Year + Controls -->
<div class="card card-primary card-outline mb-3">
  <div class="card-body py-3">
    <div class="d-flex align-items-center flex-wrap thirteenth-controls-gap thirteenth-controls-bar">
      <label class="mb-0 font-weight-bold mr-1">Year:</label>
      <select id="yearSelect" class="form-control thirteenth-year-select"
              onchange="window.location='thirteenth_month.php?year='+this.value">
        <?php for ($y = $currentYear; $y >= 2020; $y--): ?>
          <option value="<?= $y ?>" <?= $y === $selectedYear ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>

      <?php if (!$alreadyGenerated): ?>
        <form method="POST" class="d-inline">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
          <input type="hidden" name="generate_13th" value="1">
          <button type="submit" class="btn btn-success"
            onclick="return confirm('Compute 13th month pay for all eligible employees for <?= $selectedYear ?>?\n\nThis will save records based on actual payroll data processed this year.')">
            <i class="fas fa-cogs mr-1"></i>Compute 13th Month Pay
          </button>
        </form>
      <?php else: ?>
        <span class="badge badge-primary px-3 py-2 thirteenth-summary-badge">
          <i class="fas fa-check mr-1"></i><?= count($records) ?> Records Computed
        </span>
        <?php if ($pendingCount > 0): ?>
          <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="release_all" value="1">
            <button type="submit" class="btn btn-warning"
              onclick="return confirm('Release all <?= $pendingCount ?> pending 13th month pay record(s) for <?= $selectedYear ?>?\n\nThis marks them as paid to employees.')">
              <i class="fas fa-paper-plane mr-1"></i>Release All (<?= $pendingCount ?> pending)
            </button>
          </form>
        <?php endif; ?>
        <form method="POST" class="d-inline ml-2">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
          <input type="hidden" name="generate_13th" value="1">
          <button type="submit" class="btn btn-outline-secondary btn-sm"
            onclick="return confirm('Recompute all 13th month records for <?= $selectedYear ?>?\n\nExisting records will be updated with the latest payroll data.')">
            <i class="fas fa-sync mr-1"></i>Recompute
          </button>
        </form>
      <?php endif; ?>

      <div class="ml-3 d-flex align-items-center">
        <label class="mb-0 font-weight-bold mr-2 text-nowrap">Department:</label>
        <select class="form-control thirteenth-year-select"
                onchange="window.location='thirteenth_month.php?year=<?= $selectedYear ?>&dept='+this.value">
          <option value="">All Departments</option>
          <?php foreach ($allDepartments13 as $dept13): ?>
            <option value="<?= $dept13['id'] ?>" <?= (string)$filterDept13 === (string)$dept13['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($dept13['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <a href="thirteenth_month.php?year=<?= $selectedYear ?>&dept=<?= urlencode($filterDept13) ?>&print=1" target="_blank"
         class="btn btn-info ml-auto">
        <i class="fas fa-print mr-1"></i>Print
      </a>
    </div>
  </div>
</div>

<?php if (!$alreadyGenerated && !empty($preview)): ?>
<!-- Preview — show what will be computed -->
<div class="alert alert-info">
  <i class="fas fa-info-circle mr-2"></i>
  <strong>Preview</strong> — 13th month pay has not been computed for <?= $selectedYear ?> yet.
  The table below shows a preview based on payroll records processed so far.
  Click <strong>Compute 13th Month Pay</strong> to save and finalize.
</div>

<div class="row mb-3">
  <div class="col-md-4">
    <div class="info-box mb-0">
      <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Eligible Employees</span>
        <span class="info-box-number"><?= count($preview) ?></span>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="info-box mb-0">
      <span class="info-box-icon bg-success"><i class="fas fa-peso-sign"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Estimated Total Payout</span>
        <span class="info-box-number">₱<?= number_format($totalPreview, 2) ?></span>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="info-box mb-0">
      <span class="info-box-icon bg-warning"><i class="fas fa-calendar"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Deadline</span>
        <span class="info-box-number">Dec 24, <?= $selectedYear ?></span>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex align-items-center">
    <i class="fas fa-table mr-2"></i>
    <span class="flex-grow-1">Preview — <?= $selectedYear ?> 13th Month Pay</span>
    <small class="text-muted">Formula: Total Basic Salary Earned ÷ 12</small>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Department</th>
            <th>Current Basic</th>
            <th class="text-center">Months Worked</th>
            <th class="text-right">Total Basic Earned</th>
            <th class="text-right">13th Month Pay</th>
            <th class="text-center">Tax Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($preview as $row):
            $taxable = max(0, $row['thirteenth_month_pay'] - $TAX_EXEMPT_LIMIT);
          ?>
          <tr>
            <td>
              <strong class="thirteenth-emp-name"><?= htmlspecialchars($row['employee_name']) ?></strong><br>
              <small class="text-muted"><?= htmlspecialchars($row['employee_no'] ?? '') ?></small>
            </td>
            <td><small><?= htmlspecialchars($row['department']) ?></small></td>
            <td>₱<?= number_format($row['current_basic'], 2) ?></td>
            <td class="text-center">
              <?php if ($row['months_worked'] > 0): ?>
                <span class="badge badge-info"><?= $row['months_worked'] ?>/12</span>
              <?php else: ?>
                <span class="badge badge-secondary">No payroll</span>
              <?php endif; ?>
            </td>
            <td class="text-right">₱<?= number_format($row['total_basic_earned'], 2) ?></td>
            <td class="text-right"><strong>₱<?= number_format($row['thirteenth_month_pay'], 2) ?></strong></td>
            <td class="text-center">
              <?php if ($row['thirteenth_month_pay'] <= $TAX_EXEMPT_LIMIT): ?>
                <span class="badge badge-success">Tax Exempt</span>
              <?php else: ?>
                <span class="badge badge-warning" title="Excess of ₱<?= number_format($taxable, 2) ?> is taxable">
                  Partial Exempt
                </span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($preview)): ?>
          <tr><td colspan="7" class="text-center py-4 text-muted">No active employees found.</td></tr>
          <?php endif; ?>
        </tbody>
        <?php if (!empty($preview)): ?>
        <tfoot class="table-light font-weight-bold">
          <tr>
            <td colspan="4">TOTAL</td>
            <td class="text-right">₱<?= number_format(array_sum(array_column($preview, 'total_basic_earned')), 2) ?></td>
            <td class="text-right">₱<?= number_format($totalPreview, 2) ?></td>
            <td></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<?php elseif ($alreadyGenerated): ?>
<!-- Finalized records -->
<div class="row mb-3">
  <div class="col-md-3">
    <div class="info-box mb-0">
      <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Total Employees</span>
        <span class="info-box-number"><?= count($records) ?></span>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="info-box mb-0">
      <span class="info-box-icon bg-success"><i class="fas fa-coins"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Total Payout</span>
        <span class="info-box-number">₱<?= number_format($totalAmount, 2) ?></span>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="info-box mb-0">
      <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Pending Release</span>
        <span class="info-box-number"><?= $pendingCount ?></span>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="info-box mb-0">
      <span class="info-box-icon bg-primary"><i class="fas fa-check-circle"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Released</span>
        <span class="info-box-number"><?= count($records) - $pendingCount ?></span>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex align-items-center">
    <i class="fas fa-gift mr-2"></i>
    <span class="flex-grow-1">13th Month Pay — <?= $selectedYear ?></span>
    <small class="text-muted">Tax-exempt up to ₱<?= number_format($TAX_EXEMPT_LIMIT, 0) ?> (TRAIN Law)</small>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Department</th>
            <th class="text-center">Months Worked</th>
            <th class="text-right">Total Basic Earned</th>
            <th class="text-right">13th Month Pay</th>
            <th class="text-center">Tax Status</th>
            <th class="text-center">Status</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $rec):
            $taxable = max(0, $rec['amount'] - $TAX_EXEMPT_LIMIT);
          ?>
          <tr>
            <td>
              <strong class="thirteenth-emp-name"><?= htmlspecialchars($rec['employee_name']) ?></strong><br>
              <small class="text-muted"><?= htmlspecialchars($rec['employee_no'] ?? '') ?></small>
            </td>
            <td><small><?= htmlspecialchars($rec['department']) ?></small></td>
            <td class="text-center">
              <span class="badge badge-info"><?= $rec['months_worked'] ?>/12</span>
            </td>
            <td class="text-right">₱<?= number_format($rec['total_basic_earned'], 2) ?></td>
            <td class="text-right"><strong>₱<?= number_format($rec['amount'], 2) ?></strong></td>
            <td class="text-center">
              <?php if ($rec['amount'] <= $TAX_EXEMPT_LIMIT): ?>
                <span class="badge badge-success">Tax Exempt</span>
              <?php else: ?>
                <span class="badge badge-warning" title="Excess: ₱<?= number_format($taxable, 2) ?>">
                  Partial Exempt
                </span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <?php if ($rec['status'] === 'released'): ?>
                <span class="badge badge-success">Released</span>
                <?php if ($rec['released_at']): ?>
                  <br><small class="text-muted"><?= date('M d, Y', strtotime($rec['released_at'])) ?></small>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge badge-warning">Pending</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <?php if ($rec['status'] === 'pending'): ?>
              <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="release_one" value="1">
                <input type="hidden" name="record_id" value="<?= $rec['id'] ?>">
                <button type="submit" class="btn btn-xs btn-success"
                  onclick="return confirm('Release ₱<?= number_format($rec['amount'], 2) ?> to <?= htmlspecialchars(addslashes($rec['employee_name'])) ?>?')">
                  <i class="fas fa-paper-plane mr-1"></i>Release
                </button>
              </form>
              <?php else: ?>
                <small class="text-muted">—</small>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($records)): ?>
          <tr><td colspan="8" class="text-center py-4 text-muted">No records found for <?= $selectedYear ?>.</td></tr>
          <?php endif; ?>
        </tbody>
        <?php if (!empty($records)): ?>
        <tfoot class="table-light font-weight-bold">
          <tr>
            <td colspan="3">TOTAL</td>
            <td class="text-right">₱<?= number_format(array_sum(array_column($records, 'total_basic_earned')), 2) ?></td>
            <td class="text-right">₱<?= number_format($totalAmount, 2) ?></td>
            <td colspan="3"></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<?php else: ?>
<div class="card">
  <div class="card-body text-center py-5">
    <i class="fas fa-gift fa-3x mb-3 thirteenth-empty-icon"></i>
    <h5 class="text-muted">No 13th Month Pay Records for <?= $selectedYear ?></h5>
    <p class="text-muted mb-4">
      Click <strong>Compute 13th Month Pay</strong> to calculate based on actual payroll data.<br>
      <small>Requires payroll records to have been processed for <?= $selectedYear ?>.</small>
    </p>
  </div>
</div>
<?php endif; ?>

<!-- Law Reference Card -->
<div class="card mt-3">
  <div class="card-header">
    <i class="fas fa-balance-scale mr-2"></i>Legal Reference — PD 851
  </div>
  <div class="card-body thirteenth-info-table">
    <div class="row">
      <div class="col-md-4">
        <strong>Formula</strong><br>
        <code>13th Month Pay = Total Basic Salary Earned in Year ÷ 12</code>
      </div>
      <div class="col-md-4">
        <strong>Who is covered</strong><br>
        All rank-and-file employees who have worked at least one month in the calendar year.
        Managerial employees are excluded from the mandate.
      </div>
      <div class="col-md-4">
        <strong>Tax Exemption (TRAIN Law)</strong><br>
        The first ₱90,000 of 13th month pay and other benefits is tax-exempt.
        Any excess is subject to withholding tax.
      </div>
    </div>
    <hr class="my-2">
    <div class="row">
      <div class="col-md-4">
        <strong>Deadline</strong><br>
        Must be paid on or before <strong>December 24</strong> of the year.
        Can be paid in two installments — half by June 30, remainder by December 24.
      </div>
      <div class="col-md-4">
        <strong>Basis</strong><br>
        Basic salary only — excludes allowances, overtime pay, holiday pay, night differential, and bonuses.
      </div>
      <div class="col-md-4">
        <strong>Pro-ration</strong><br>
        An employee who worked less than 12 months is entitled to a proportionate amount
        based on months actually worked.
      </div>
    </div>
  </div>
</div>

</div>
<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>