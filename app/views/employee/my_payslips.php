<?php
// app/views/employee/my_payslips.php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header('Location: ' . BASE_URL . 'index.php?error=access_denied');
    exit;
}

$pageTitle  = 'My Payslips';
$employeeId = (int)($_SESSION['employee_id'] ?? 0);

require_once __DIR__ . '/../layouts/employee_header.php';
require_once __DIR__ . '/../../../core/Model.php';

$records  = Model::getPayrollRecordsByEmployee($employeeId);
$employee = $employeeId ? Model::findEmployeeById($employeeId) : null;

// Build full payroll records keyed by period for modal data
$fullRecords = [];
if ($employeeId) {
    foreach (Model::getPayrollByEmployee($employeeId) as $r) {
        $fullRecords[$r['period']] = $r;
    }
}

// Build 13th month records keyed by year
$thirteenthRecords = [];
if ($employeeId) {
    $years = array_unique(array_map(fn($p) => substr($p, 0, 4), array_keys($fullRecords)));
    foreach ($years as $yr) {
        $rec13 = Model::get13thMonthByEmployee($employeeId, (int)$yr);
        if ($rec13) $thirteenthRecords[$yr] = $rec13;
    }
}
?>

<div class="page-title-bar">
  <i class="fas fa-file-invoice-dollar text-primary"></i>
  <h1>My Payslips <small class="text-muted my-payslips-subtitle">View &amp; print your payroll history</small></h1>
</div>

<!-- Payslip Table -->
<div class="card">
  <div class="card-body table-responsive p-0">
    <?php if (empty($records)): ?>
      <div class="alert alert-info text-center m-4">
        <i class="fas fa-info-circle mr-2"></i>
        No payroll records found yet.
      </div>
    <?php else: ?>
      <table class="table table-hover mb-0">
        <thead class="thead-light">
          <tr>
            <th>Period</th>
            <th>Basic Salary</th>
            <th>Gross Pay</th>
            <th>Total Deductions</th>
            <th class="text-success">Net Pay</th>
            <th class="text-center">Status</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $row):
            $full = $fullRecords[$row['period']] ?? null;
            $payrollId = $full['id'] ?? 0;
          ?>
          <tr>
            <td><strong><?= htmlspecialchars(Model::periodLabel($row['period'])) ?></strong></td>
            <td>&#8369; <?= number_format($employee['basic_salary'] ?? 0, 2) ?></td>
            <td>&#8369; <?= number_format($row['gross_pay'] ?? 0, 2) ?></td>
            <td class="text-danger">&#8369; <?= number_format($row['total_deductions'] ?? 0, 2) ?></td>
            <td class="text-success font-weight-bold">&#8369; <?= number_format($row['net_pay'] ?? 0, 2) ?></td>
            <td class="text-center">
              <span class="status-badge badge-<?= $row['status'] === 'released' ? 'released' : 'pending' ?>">
                <?= ucfirst($row['status'] ?? '—') ?>
              </span>
            </td>
            <td class="text-center">
              <?php if ($full): ?>
              <button class="btn btn-xs btn-primary view-payslip-btn"
                data-id="<?= $full['id'] ?>"
                data-period="<?= htmlspecialchars($row['period']) ?>"
                data-gross="<?= $full['gross_pay'] ?>"
                data-net="<?= $full['net_pay'] ?>"
                data-deductions="<?= $full['total_deductions'] ?>"
                data-sss="<?= $full['sss_ee'] ?>"
                data-philhealth="<?= $full['philhealth_ee'] ?>"
                data-pagibig="<?= $full['pagibig_ee'] ?>"
                data-tax="<?= $full['withholding_tax'] ?>"
                data-absences="<?= $full['absent_deduction'] ?? 0 ?>"
                data-late="<?= $full['late_deduction'] ?? 0 ?>"
                data-processedby="<?= htmlspecialchars($row['processed_by_name'] ?? '—') ?>"
                data-status="<?= htmlspecialchars($row['status']) ?>"
                data-thirteenth="<?= $thirteenthRecords[substr($row['period'],0,4)]['amount'] ?? '' ?>"
                data-thirteenthstatus="<?= $thirteenthRecords[substr($row['period'],0,4)]['status'] ?? '' ?>"
                data-cutoff="<?= Model::periodCutoff($row['period']) ?>"
                data-reconciliation="<?= round(($full['other_deductions'] ?? 0) - ($full['absent_deduction'] ?? 0), 2) ?>">
                <i class="fas fa-eye mr-1"></i> View
              </button>
              <?php else: ?>
                <span class="text-muted my-payslips-empty-dash">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     PAYSLIP MODAL
     ══════════════════════════════════════════════════════ -->
<div class="modal fade" id="payslipModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">

      <!-- Modal Header (screen only) -->
      <div class="modal-header no-print">
        <h5 class="modal-title"><i class="fas fa-file-invoice-dollar mr-2 text-primary"></i>Payslip Detail</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body p-0" id="payslipPrintArea">

        <!-- Payslip Header Bar -->
        <div class="payslip-header-bar d-flex justify-content-between align-items-start px-4 py-3">
          <div>
            <h5 class="mb-0 font-weight-bold"><?= htmlspecialchars(COMPANY_NAME) ?></h5>
            <small class="ps-modal-company-address"><?= htmlspecialchars(COMPANY_ADDRESS) ?></small><br>
            <small class="ps-modal-company-sub">Official Payroll Slip</small>
          </div>
          <div class="text-right">
            <small class="ps-modal-company-address">Pay Period</small><br>
            <strong id="ps-period-label" class="ps-modal-period-value"></strong><br>
            <span id="ps-status-badge" class="status-badge mt-1 d-inline-block"></span>
          </div>
        </div>

        <div class="px-4 py-3">

          <!-- Employee Info -->
          <div class="row mb-3">
            <div class="col-6">
              <table class="table table-sm table-borderless mb-0 ps-modal-emp-table">
                <tr>
                  <td class="text-muted pl-0" width="40%">Employee No.</td>
                  <td><code><?= htmlspecialchars($employee['employee_no'] ?? '—') ?></code></td>
                </tr>
                <tr>
                  <td class="text-muted pl-0">Name</td>
                  <td><strong><?= htmlspecialchars($employee['name'] ?? '—') ?></strong></td>
                </tr>
                <tr>
                  <td class="text-muted pl-0">Department</td>
                  <td><?= htmlspecialchars($employee['department'] ?? '—') ?></td>
                </tr>
              </table>
            </div>
            <div class="col-6">
              <table class="table table-sm table-borderless mb-0 ps-modal-emp-table">
                <tr>
                  <td class="text-muted pl-0" width="40%">Position</td>
                  <td><?= htmlspecialchars($employee['position'] ?? '—') ?></td>
                </tr>
                <tr>
                  <td class="text-muted pl-0">Date Hired</td>
                  <td><?= htmlspecialchars($employee['date_hired'] ?? '—') ?></td>
                </tr>
                <tr>
                  <td class="text-muted pl-0">Employment</td>
                  <td><span class="badge badge-success ps-modal-active-badge">Active</span></td>
                </tr>
              </table>
            </div>
          </div>

          <div class="payslip-divider"></div>

          <!-- Earnings & Deductions -->
          <div class="row">
            <!-- Earnings -->
            <div class="col-6">
              <h6 class="payslip-section-title">Earnings</h6>
              <div class="comp-row">
                <span>Basic Salary</span>
                <span>&#8369; <?= number_format($employee['basic_salary'] ?? 0, 2) ?></span>
              </div>
              <div class="comp-row">
                <span>Allowance</span>
                <span>&#8369; <?= number_format($employee['allowance'] ?? 0, 2) ?></span>
              </div>
              <div class="comp-row ps-modal-13th-row" id="ps-thirteenth-row" style="display:none">
                <span>13th Month Pay <span id="ps-thirteenth-badge" class="badge badge-info ml-1 ps-modal-13th-badge"></span></span>
                <span class="text-info font-weight-bold" id="ps-thirteenth">&#8369; 0.00</span>
              </div>
              <div class="comp-row total text-success">
                <span>Gross Pay</span>
                <span id="ps-gross">&#8369; 0.00</span>
              </div>
            </div>

            <!-- Deductions -->
            <div class="col-6">
              <h6 class="payslip-section-title">Deductions</h6>
              <div id="ps-gov-rows">
                <div class="comp-row">
                  <span>SSS</span>
                  <span class="text-danger" id="ps-sss">− &#8369; 0.00</span>
                </div>
                <div class="comp-row">
                  <span>PhilHealth</span>
                  <span class="text-danger" id="ps-philhealth">− &#8369; 0.00</span>
                </div>
                <div class="comp-row">
                  <span>Pag-IBIG</span>
                  <span class="text-danger" id="ps-pagibig">− &#8369; 0.00</span>
                </div>
              </div>
              <div id="ps-no-gov-row" class="comp-row text-muted ps-modal-gov-note">
                <span><i class="fas fa-info-circle mr-1"></i>Gov. deductions</span>
                <span>1st cutoff — none</span>
              </div>
              <div class="comp-row">
                <span>Withholding Tax</span>
                <span class="text-danger" id="ps-tax">− &#8369; 0.00</span>
              </div>
              <div id="ps-reconcile-row" class="comp-row ps-modal-reconcile-row" style="display:none">
                <span id="ps-reconcile-label">Year-End Tax Adjustment</span>
                <span id="ps-reconcile">&#8369; 0.00</span>
              </div>
              <div class="comp-row" id="ps-absences-row">
                <span>Absences / Late</span>
                <span class="text-danger" id="ps-absences">− &#8369; 0.00</span>
              </div>
              <div class="comp-row total text-danger">
                <span>Total Deductions</span>
                <span id="ps-deductions">&#8369; 0.00</span>
              </div>
            </div>
          </div>

          <div class="payslip-divider"></div>

          <!-- Net Pay Box -->
          <div class="payslip-net-box">
            <div>
              <p class="mb-1 text-muted ps-modal-net-label">
                Net Pay for <span id="ps-net-period"></span>
              </p>
              <h2 class="mb-0 font-weight-bold ps-modal-net-amount" id="ps-net">&#8369; 0.00</h2>
            </div>
            <div class="text-right">
              <p class="mb-0 text-muted ps-modal-processed-label">Processed by</p>
              <strong id="ps-processedby"></strong><br>
              <small class="text-muted">Payroll Administrator</small>
            </div>
          </div>

          <!-- Signature Lines (print only) -->
          <div class="row mt-4 pt-3 print-only">
            <div class="col-6 text-center">
              <div class="signature-line">Employee Signature / Date</div>
            </div>
            <div class="col-6 text-center">
              <div class="signature-line">Authorized Signatory</div>
            </div>
          </div>

          <!-- Generated timestamp (print only) -->
          <div class="print-only text-right mt-3">
            <small class="ps-modal-generated-note">
              Generated: <?= date('F d, Y h:i A') ?> &mdash; <?= htmlspecialchars(COMPANY_NAME) ?> HRIS
            </small>
          </div>

        </div><!-- /.px-4 -->
      </div><!-- /#payslipPrintArea -->

      <!-- Modal Footer (screen only) -->
      <div class="modal-footer no-print">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          <i class="fas fa-times mr-1"></i> Close
        </button>
        <a id="ps-pdf-link" href="#" target="_blank" class="btn btn-success">
          <i class="fas fa-file-pdf mr-1"></i> Download PDF
        </a>
        <button type="button" class="btn btn-info" onclick="printPayslip()">
          <i class="fas fa-print mr-1"></i> Print
        </button>
      </div>

    </div><!-- /.modal-content -->
  </div>
</div>

<script>
function fmt(val) {
  return '₱ ' + parseFloat(val || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

$('.view-payslip-btn').on('click', function() {
  const d = $(this).data();
  const period    = d.period;
  const periodLbl = new Date(period + '-02').toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
  const absences  = parseFloat(d.absences || 0) + parseFloat(d.late || 0);

  // Period labels
  $('#ps-period-label').text(periodLbl);
  $('#ps-net-period').text(periodLbl.toUpperCase());

  // Status badge
  const statusClass = d.status === 'released' ? 'badge-released' : 'badge-pending';
  $('#ps-status-badge').attr('class', 'status-badge ' + statusClass).text(d.status.charAt(0).toUpperCase() + d.status.slice(1));

  // Earnings
  $('#ps-gross').text(fmt(d.gross));

  // Gov deductions — show/hide based on cutoff
  const cutoff = parseInt(d.cutoff || 2);
  if (cutoff === 1) {
    $('#ps-gov-rows').hide();
    $('#ps-no-gov-row').show();
  } else {
    $('#ps-gov-rows').show();
    $('#ps-no-gov-row').hide();
  }
  $('#ps-sss').text('− ' + fmt(d.sss));
  $('#ps-philhealth').text('− ' + fmt(d.philhealth));
  $('#ps-pagibig').text('− ' + fmt(d.pagibig));

  // Year-end reconciliation
  const reconcile = parseFloat(d.reconciliation || 0);
  if (reconcile !== 0) {
    const sign = reconcile > 0 ? '− ' : '+ ';
    const color = reconcile > 0 ? '#dc2626' : '#16a34a';
    $('#ps-reconcile').text(sign + fmt(Math.abs(reconcile))).css('color', color);
    $('#ps-reconcile-label').text(reconcile > 0 ? 'Year-End Tax (owe)' : 'Year-End Tax (refund)');
    $('#ps-reconcile-row').show();
  } else {
    $('#ps-reconcile-row').hide();
  }
  $('#ps-tax').text('− ' + fmt(d.tax));

  if (absences > 0) {
    $('#ps-absences').text('− ' + fmt(absences));
    $('#ps-absences-row').show();
  } else {
    $('#ps-absences-row').hide();
  }

  $('#ps-deductions').text(fmt(d.deductions));

  // Net pay
  $('#ps-net').text(fmt(d.net));

  // Processed by
  $('#ps-processedby').text(d.processedby || '—');

  // 13th Month Pay
  const thirteenth = parseFloat(d.thirteenth || 0);
  if (thirteenth > 0) {
    $('#ps-thirteenth').text(fmt(thirteenth));
    const t13Status = d.thirteenthstatus || '';
    const t13Badge  = t13Status === 'released'
      ? '<span class="ps-badge-released">Released</span>'
      : '<span class="ps-badge-pending">Pending</span>';
    $('#ps-thirteenth-badge').html(t13Badge);
    $('#ps-thirteenth-row').show();
  } else {
    $('#ps-thirteenth-row').hide();
  }

  // Set PDF download link for this period
  const pdfUrl = 'payslip_pdf.php?period=' + period;
  $('#ps-pdf-link').attr('href', pdfUrl);

  $('#payslipModal').modal('show');
});

function printPayslip() {
  window.print();
}
</script>

<?php require_once __DIR__ . '/../layouts/employee_footer.php'; ?>