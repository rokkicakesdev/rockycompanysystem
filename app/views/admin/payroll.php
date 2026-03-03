<?php
$pageTitle  = 'Payroll Processing';
$breadcrumb = 'Payroll';
$activeMenu = 'payroll';

require_once __DIR__ . '/../layouts/admin_header.php';

$employees  = Model::getAllEmployees();
$allPayroll = Model::getAllPayroll();

$selectedPeriod = $_GET['period'] ?? '2025-02';
$periodPayroll  = Model::getPayrollByPeriod($selectedPeriod);
$totalNet       = Model::getTotalNetPayForPeriod($selectedPeriod);
?>

<!-- ─── Period Selector ──────────────────── -->
<div class="card card-primary card-outline mb-3">
  <div class="card-body py-3">
    <div class="d-flex align-items-center flex-wrap">
      <label class="mb-0 font-weight-bold mr-2">Payroll Period:</label>
      <select id="periodSelect" class="form-control payroll-period-select mr-2" onchange="window.location='payroll.php?period='+this.value">
        <option value="2025-01" <?= $selectedPeriod==='2025-01'?'selected':'' ?>>January 2025</option>
        <option value="2025-02" <?= $selectedPeriod==='2025-02'?'selected':'' ?>>February 2025</option>
        <option value="2025-03" <?= $selectedPeriod==='2025-03'?'selected':'' ?>>March 2025</option>
      </select>
      <button class="btn btn-success mr-2" data-toggle="modal" data-target="#generateModal">
        <i class="fas fa-cogs mr-1"></i> Generate Payroll
      </button>
      <button class="btn btn-info ml-auto" onclick="window.print()">
        <i class="fas fa-print mr-1"></i> Print
      </button>
    </div>
  </div>
</div>

<!-- ─── Summary Cards ─────────────────────── -->
<?php
$periodPayroll = Model::getPayrollByPeriod($selectedPeriod);
$totalGross    = array_sum(array_column($periodPayroll,'gross_pay'));
$totalDed      = array_sum(array_column($periodPayroll,'total_deductions'));
$totalNet      = array_sum(array_column($periodPayroll,'net_pay'));
$pendingList   = array_filter($periodPayroll, fn($p)=>$p['status']==='pending');
$releasedList  = array_filter($periodPayroll, fn($p)=>$p['status']==='released');
?>
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
        <span class="info-box-number">₱<?= number_format($totalGross,0) ?></span>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="info-box">
      <span class="info-box-icon bg-danger"><i class="fas fa-minus-circle"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Total Deductions</span>
        <span class="info-box-number">₱<?= number_format($totalDed,0) ?></span>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="info-box">
      <span class="info-box-icon bg-success"><i class="fas fa-hand-holding-usd"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Total Net Pay</span>
        <span class="info-box-number">₱<?= number_format($totalNet,0) ?></span>
      </div>
    </div>
  </div>
</div>

<!-- ─── Payroll Table ─────────────────────── -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fas fa-table mr-2"></i>
      Payroll Records — <?= date('F Y', strtotime($selectedPeriod.'-01')) ?>
    </h3>
    <div class="card-tools">
      <span class="badge badge-warning mr-2"><?= count($pendingList) ?> Pending</span>
      <span class="badge badge-success"><?= count($releasedList) ?> Released</span>
    </div>
  </div>
  <div class="card-body p-0">
    <?php if(empty($periodPayroll)): ?>
      <div class="p-4 text-center text-muted">
        <i class="fas fa-inbox fa-3x mb-3"></i><br>
        No payroll records for this period. Click "Generate Payroll" to begin.
      </div>
    <?php else: ?>
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
          <th>Total Ded.</th>
          <th class="text-success">Net Pay</th>
          <th>Status</th>
          <th class="text-center">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($periodPayroll as $p):
        $emp = Model::findEmployeeById($p['employee_id']);
        if(!$emp) continue;
      ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($emp['name']) ?></strong><br>
            <small class="text-muted"><?= $emp['employee_no'] ?></small>
          </td>
          <td><?= htmlspecialchars($emp['department']) ?></td>
          <td>₱<?= number_format($emp['basic_salary'],2) ?></td>
          <td>₱<?= number_format($emp['allowance'],2) ?></td>
          <td>₱<?= number_format($p['gross_pay'],2) ?></td>
          <td class="text-danger">₱<?= number_format($p['sss_ee'],2) ?></td>
          <td class="text-danger">₱<?= number_format($p['philhealth_ee'],2) ?></td>
          <td class="text-danger">₱<?= number_format($p['pagibig_ee'],2) ?></td>
          <td class="text-danger font-weight-bold">₱<?= number_format($p['total_deductions'],2) ?></td>
          <td class="text-success font-weight-bold">₱<?= number_format($p['net_pay'],2) ?></td>
          <td>
            <?= $p['status']==='released'
              ? '<span class="badge badge-success">Released</span>'
              : '<span class="badge badge-warning">Pending</span>' ?>
          </td>
          <td class="text-center">
            <a href="payslip.php?emp=<?= $emp['id'] ?>&period=<?= $selectedPeriod ?>" class="btn btn-sm btn-info" title="View Payslip">
              <i class="fas fa-receipt"></i>
            </a>
            <?php if($p['status']==='pending'): ?>
            <button class="btn btn-sm btn-success" title="Mark Released" onclick="confirmRelease('<?= htmlspecialchars($emp['name']) ?>')">
              <i class="fas fa-check"></i>
            </button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot class="bg-light">
        <tr>
          <th colspan="4" class="text-right">TOTALS</th>
          <th>₱<?= number_format($totalGross,2) ?></th>
          <th colspan="3"></th>
          <th class="text-danger">₱<?= number_format($totalDed,2) ?></th>
          <th class="text-success">₱<?= number_format($totalNet,2) ?></th>
          <th colspan="2"></th>
        </tr>
      </tfoot>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- ─── Generate Payroll Modal ───────────── -->
<div class="modal fade" id="generateModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fas fa-cogs mr-2"></i>Generate Payroll</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info">
          <i class="fas fa-info-circle mr-1"></i>
          The following employees will be included in payroll generation. Review and confirm.
        </div>
        <div class="form-group">
          <label>Payroll Period</label>
          <select class="form-control">
            <option>March 2025</option>
            <option>April 2025</option>
          </select>
        </div>
        <table class="table table-sm table-bordered">
          <thead>
            <tr><th>Employee</th><th>Gross Pay</th><th>Deductions</th><th>Net Pay</th><th><input type="checkbox" checked></th></tr>
          </thead>
          <tbody>
          <?php foreach(array_filter($employees, fn($e)=>$e['status']==='active') as $e):
            $c = Model::computePayroll($e);
          ?>
            <tr>
              <td><?= htmlspecialchars($e['name']) ?></td>
              <td>₱<?= number_format($c['gross_pay'],2) ?></td>
              <td class="text-danger">₱<?= number_format($c['total_deductions'],2) ?></td>
              <td class="text-success font-weight-bold">₱<?= number_format($c['net_pay'],2) ?></td>
              <td class="text-center"><input type="checkbox" checked></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success"><i class="fas fa-play mr-1"></i> Confirm & Generate</button>
      </div>
    </div>
  </div>
</div>

<?php
$extraJs = <<<JS
function confirmRelease(name) {
  if(confirm('Mark payroll as RELEASED for ' + name + '?')) {
    alert('Payslip released! (Will update DB when MySQL is connected.)');
  }
}
JS;

require_once __DIR__ . '/../layouts/admin_footer.php';
?>