<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../config/config.php';
if (!in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$pageTitle = 'Payroll Settings';
require_once __DIR__ . '/../layouts/admin_header.php';

$msg = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ── Handle save ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token.</div>";
    } else {
        $saved  = 0;
        $errors = 0;
        $rows   = $_POST['settings'] ?? [];

        foreach ($rows as $empId => $s) {
            $empId = (int)$empId;
            // Validate cutoff1_fixed_amount: must be null/empty or a positive number
            $c1Raw = trim($s['cutoff1_fixed_amount'] ?? '');
            if ($c1Raw !== '' && (!is_numeric($c1Raw) || (float)$c1Raw < 0)) {
                $errors++;
                continue;
            }
            $ok = Model::updateEmployeePayrollSettings($empId, [
                'cutoff1_fixed_amount' => $c1Raw,
                'tax_method'           => $s['tax_method']         ?? 'half_monthly',
                'gov_deduction_mode'   => $s['gov_deduction_mode'] ?? 'second_cutoff',
            ]);
            $ok ? $saved++ : $errors++;
        }

        Model::log($_SESSION['user_id'], 'UPDATE_PAYROLL_SETTINGS',
            "Updated payroll settings for {$saved} employee(s). Errors: {$errors}.");

        $msgClass = $errors === 0 ? 'success' : 'warning';
        $msg = "<div class='alert alert-{$msgClass} alert-auto-dismiss'>
            Settings saved for <strong>{$saved}</strong> employee(s)."
            . ($errors > 0 ? " <strong>{$errors}</strong> failed." : '') . "</div>";
    }
}

$employees = Model::getAllEmployees('active');

// Pre-load settings and compute previews for all employees
$settingsMap = [];
$previewMap  = [];
foreach ($employees as $emp) {
    $s = Model::getEmployeePayrollSettings((int)$emp['id']);
    $settingsMap[$emp['id']] = $s;

    // Compute preview for both cutoffs
    $previewMap[$emp['id']] = [
        'c1' => PhilippineDeductions::computeFirstCutoff(
            (float)$emp['basic_salary'],
            (float)($emp['allowance'] ?? 0),
            $s['cutoff1_fixed_amount'] !== null ? (float)$s['cutoff1_fixed_amount'] : null,
            $s['tax_method']
        ),
        'c2' => PhilippineDeductions::computeSecondCutoff(
            (float)$emp['basic_salary'],
            (float)($emp['allowance'] ?? 0),
            $s['cutoff1_fixed_amount'] !== null ? (float)$s['cutoff1_fixed_amount'] : null,
            $s['tax_method'],
            $s['gov_deduction_mode']
        ),
    ];
}
?>

<div class="page-title-bar">
  <i class="fas fa-sliders-h text-primary"></i>
  <h1>Payroll Settings</h1>
  <span class="text-muted ml-auto" style="font-size:.82rem;">
    Configure per-employee cutoff amounts, tax method, and government deduction schedule.
  </span>
</div>

<?= $msg ?>

<!-- Legend -->
<div class="alert alert-info mb-3" style="font-size:.82rem;">
  <i class="fas fa-info-circle mr-2"></i>
  <strong>Semi-Monthly Payroll Rules:</strong>
  <ul class="mb-0 mt-1">
    <li><strong>1st Cutoff (1–15):</strong> Basic ÷ 2 + Allowance ÷ 2. Withholding tax only. No government deductions.</li>
    <li><strong>2nd Cutoff (16–End):</strong> Remaining basic + Allowance ÷ 2. Gov deductions + Withholding tax.</li>
    <li><strong>December 1st Cutoff:</strong> 13th month pay is automatically included in earnings.</li>
    <li><strong>December 2nd Cutoff:</strong> Year-end tax reconciliation is automatically computed.</li>
  </ul>
</div>

<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
  <input type="hidden" name="save_settings" value="1">

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-users mr-2"></i>Employee Payroll Configuration</span>
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="fas fa-save mr-1"></i>Save All Changes
      </button>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.82rem;">
          <thead class="thead-light">
            <tr>
              <th style="min-width:180px;">Employee</th>
              <th>Monthly Salary</th>
              <th style="min-width:160px;">
                1st Cutoff Amount
                <br><small class="text-muted font-weight-normal">Leave blank for auto (salary ÷ 2)</small>
              </th>
              <th style="min-width:160px;">
                Tax Method
                <br><small class="text-muted font-weight-normal">Per cutoff</small>
              </th>
              <th style="min-width:180px;">
                Gov. Deductions Schedule
                <br><small class="text-muted font-weight-normal">SSS / PhilHealth / Pag-IBIG</small>
              </th>
              <th class="text-center" style="min-width:120px;">1st Cutoff Net</th>
              <th class="text-center" style="min-width:120px;">2nd Cutoff Net</th>
              <th class="text-center" style="min-width:110px;">Monthly Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $emp):
              $s  = $settingsMap[$emp['id']];
              $p  = $previewMap[$emp['id']];
              $c1 = $p['c1'];
              $c2 = $p['c2'];
              $monthlyNet = round($c1['net_pay'] + $c2['net_pay'], 2);
            ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($emp['name']) ?></strong><br>
                <small class="text-muted"><?= $emp['employee_no'] ?> — <?= htmlspecialchars($emp['department']) ?></small>
              </td>
              <td>
                ₱<?= number_format($emp['basic_salary'], 2) ?><br>
                <small class="text-muted">+ ₱<?= number_format($emp['allowance'], 2) ?> allow.</small>
              </td>
              <td>
                <div class="input-group input-group-sm">
                  <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                  <input type="number" step="0.01" min="0"
                         name="settings[<?= $emp['id'] ?>][cutoff1_fixed_amount]"
                         class="form-control cutoff1-input"
                         data-empid="<?= $emp['id'] ?>"
                         data-basic="<?= $emp['basic_salary'] ?>"
                         data-allowance="<?= $emp['allowance'] ?>"
                         value="<?= $s['cutoff1_fixed_amount'] !== null ? number_format((float)$s['cutoff1_fixed_amount'], 2, '.', '') : '' ?>"
                         placeholder="Auto (÷2)">
                </div>
              </td>
              <td>
                <select name="settings[<?= $emp['id'] ?>][tax_method]"
                        class="form-control form-control-sm tax-method-select"
                        data-empid="<?= $emp['id'] ?>">
                  <option value="half_monthly" <?= $s['tax_method'] === 'half_monthly' ? 'selected' : '' ?>>
                    Monthly Tax ÷ 2
                  </option>
                  <option value="bir_table" <?= $s['tax_method'] === 'bir_table' ? 'selected' : '' ?>>
                    Fresh BIR Table
                  </option>
                </select>
              </td>
              <td>
                <select name="settings[<?= $emp['id'] ?>][gov_deduction_mode]"
                        class="form-control form-control-sm gov-mode-select"
                        data-empid="<?= $emp['id'] ?>">
                  <option value="second_cutoff" <?= $s['gov_deduction_mode'] === 'second_cutoff' ? 'selected' : '' ?>>
                    Full on 2nd Cutoff
                  </option>
                  <option value="split" <?= $s['gov_deduction_mode'] === 'split' ? 'selected' : '' ?>>
                    Half per Cutoff
                  </option>
                </select>
              </td>
              <td class="text-center">
                <span class="font-weight-bold text-success" id="prev-c1-<?= $emp['id'] ?>">
                  ₱<?= number_format($c1['net_pay'], 2) ?>
                </span><br>
                <small class="text-muted">Gross: ₱<?= number_format($c1['gross_pay'], 2) ?></small>
              </td>
              <td class="text-center">
                <span class="font-weight-bold text-primary" id="prev-c2-<?= $emp['id'] ?>">
                  ₱<?= number_format($c2['net_pay'], 2) ?>
                </span><br>
                <small class="text-muted">Gross: ₱<?= number_format($c2['gross_pay'], 2) ?></small>
              </td>
              <td class="text-center">
                <span class="font-weight-bold" id="prev-total-<?= $emp['id'] ?>">
                  ₱<?= number_format($monthlyNet, 2) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <small class="text-muted">Preview updates live as you change settings. Save to apply permanently.</small>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save mr-1"></i>Save All Changes
      </button>
    </div>
  </div>
</form>

<?php
$extraJs = <<<JS
// Live preview via AJAX when any setting changes
function refreshPreview(empId) {
    const basic      = parseFloat(document.querySelector('.cutoff1-input[data-empid="' + empId + '"]').dataset.basic);
    const allowance  = parseFloat(document.querySelector('.cutoff1-input[data-empid="' + empId + '"]').dataset.allowance);
    const c1Raw      = document.querySelector('.cutoff1-input[data-empid="' + empId + '"]').value.trim();
    const taxMethod  = document.querySelector('.tax-method-select[data-empid="' + empId + '"]').value;
    const govMode    = document.querySelector('.gov-mode-select[data-empid="' + empId + '"]').value;

    $.getJSON('<?= BASE_URL ?>/app/ajax/payroll_preview.php', {
        emp_id:    empId,
        basic:     basic,
        allowance: allowance,
        cutoff1:   c1Raw,
        tax_method: taxMethod,
        gov_mode:  govMode
    }, function(d) {
        if (d.error) return;
        $('#prev-c1-' + empId).text('₱' + d.c1_net);
        $('#prev-c2-' + empId).text('₱' + d.c2_net);
        $('#prev-total-' + empId).text('₱' + d.total_net);
    });
}

document.querySelectorAll('.cutoff1-input, .tax-method-select, .gov-mode-select').forEach(function(el) {
    el.addEventListener('change', function() { refreshPreview(this.dataset.empid); });
});
JS;
require_once __DIR__ . '/../layouts/admin_footer.php';
?>
