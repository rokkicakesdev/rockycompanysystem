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
  <h1>My Payslips <small class="text-muted" style="font-size:.55em;">View &amp; print your payroll history</small></h1>
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
                <span class="text-muted" style="font-size:.8rem;">—</span>
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
            <small style="opacity:.7;"><?= htmlspecialchars(COMPANY_ADDRESS) ?></small><br>
            <small style="opacity:.6;">Official Payroll Slip</small>
          </div>
          <div class="text-right">
            <small style="opacity:.7;">Pay Period</small><br>
            <strong id="ps-period-label" style="font-size:1rem;"></strong><br>
            <span id="ps-status-badge" class="status-badge mt-1 d-inline-block"></span>
          </div>
        </div>

        <div class="px-4 py-3">

          <!-- Employee Info -->
          <div class="row mb-3">
            <div class="col-6">
              <table class="table table-sm table-borderless mb-0" style="font-size:.85rem;">
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
              <table class="table table-sm table-borderless mb-0" style="font-size:.85rem;">
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
                  <td><span class="badge badge-success" style="font-size:.75rem;">Active</span></td>
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
              <div class="comp-row" id="ps-thirteenth-row" style="display:none;">
                <span>13th Month Pay <span id="ps-thirteenth-badge" class="badge badge-info ml-1" style="font-size:.65rem;"></span></span>
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
              <div id="ps-no-gov-row" class="comp-row text-muted" style="display:none;font-size:.78rem;">
                <span><i class="fas fa-info-circle mr-1"></i>Gov. deductions</span>
                <span>1st cutoff — none</span>
              </div>
              <div class="comp-row">
                <span>Withholding Tax</span>
                <span class="text-danger" id="ps-tax">− &#8369; 0.00</span>
              </div>
              <div id="ps-reconcile-row" class="comp-row" style="display:none;">
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
              <p class="mb-1 text-muted" style="font-size:.78rem; text-transform:uppercase; letter-spacing:.5px;">
                Net Pay for <span id="ps-net-period"></span>
              </p>
              <h2 class="mb-0 font-weight-bold" style="color:#1e3a5f;" id="ps-net">&#8369; 0.00</h2>
            </div>
            <div class="text-right">
              <p class="mb-0 text-muted" style="font-size:.75rem;">Processed by</p>
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
            <small style="color:#999; font-size:7.5pt;">
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

<?php
$extraJs = <<<JS
<script>
function fmt(val) {
  return '₱ ' + parseFloat(val || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// BUG FIX: Parse YYYY-MM-C period format correctly for display.
// The period string is e.g. "2026-02-1" — appending '-02' produced "2026-02-1-02"
// which is an invalid date. We extract only the YYYY-MM base for the Date constructor.
function periodLabel(period) {
  const base = period.substring(0, 7); // "2026-02"
  const cutoff = parseInt(period.slice(-1)) || 2;
  // Use the 2nd of the month to avoid timezone-boundary day-shift issues
  const dt = new Date(base + '-02');
  const monthYear = dt.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
  const cutoffStr = cutoff === 1 ? '(1st–15th)' : '(16th–End)';
  return monthYear + ' ' + cutoffStr;
}

$('.view-payslip-btn').on('click', function() {
  const d = $(this).data();
  const period    = d.period;
  const periodLbl = periodLabel(period);

  // BUG FIX: d.absences already holds the combined absent_deduction value from the DB.
  // The original code added d.late on top, but late_deduction is not a separate column —
  // it was already folded into absent_deduction server-side. Adding d.late (which is
  // always undefined/0 from data attributes) caused stale double-counting.
  const absences = parseFloat(d.absences || 0);

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

  // BUG FIX: always reset the absences row first to prevent stale state
  // from a previous modal open bleeding into the current one.
  $('#ps-absences-row').hide();
  $('#ps-absences').text('− ' + fmt(0));
  if (absences > 0) {
    $('#ps-absences').text('− ' + fmt(absences));
    $('#ps-absences-row').show();
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
      ? '<span style="background:#dcfce7;color:#15803d;padding:1px 5px;border-radius:4px;font-size:.65rem;">Released</span>'
      : '<span style="background:#fef9c3;color:#b45309;padding:1px 5px;border-radius:4px;font-size:.65rem;">Pending</span>';
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
  const content = document.getElementById('payslipPrintArea').innerHTML;

  const styles = `
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Segoe UI',Arial,sans-serif; font-size:11pt; color:#111; background:#fff; padding:14mm 16mm; }
    @page { size:A4 portrait; margin:14mm 16mm; }

    .payslip-header-bar {
      background:#1e2433 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact;
      color:#fff !important; padding:16px 22px; border-radius:6px 6px 0 0;
      display:flex; justify-content:space-between; align-items:flex-start;
    }
    .payslip-header-bar h5  { font-size:14pt; font-weight:700; margin:0; color:#fff !important; }
    .payslip-header-bar small, .payslip-header-bar strong { color:#fff !important; }
    .payslip-header-bar small { font-size:8pt; opacity:.75; }
    .payslip-header-bar strong { font-size:11pt; }
    .payslip-header-bar .text-right { text-align:right; }

    .status-badge { display:inline-block; padding:2px 10px; border-radius:20px; font-size:8pt; font-weight:600; }
    .badge-released { background:#dbeafe !important; color:#1e40af !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .badge-pending  { background:#fef3c7 !important; color:#92400e !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }

    .px-4 { padding-left:20px; padding-right:20px; }
    .py-3 { padding-top:14px; padding-bottom:14px; }

    .row    { display:flex; flex-wrap:wrap; }
    .col-6  { width:50%; flex:0 0 50%; max-width:50%; padding:0 8px; }
    .mb-3   { margin-bottom:12px; }
    .mb-0   { margin-bottom:0; }
    .mb-1   { margin-bottom:4px; }
    .mt-1   { margin-top:4px; }
    .mt-4   { margin-top:18px; }
    .pt-3   { padding-top:14px; }
    .pl-0   { padding-left:0 !important; }
    .d-flex { display:flex; }
    .d-inline-block { display:inline-block; }
    .justify-content-between { justify-content:space-between; }
    .align-items-start { align-items:flex-start; }
    .text-right  { text-align:right; }
    .text-center { text-align:center; }
    .font-weight-bold { font-weight:700; }
    strong { font-weight:700; }
    small  { font-size:80%; }

    .table { width:100%; border-collapse:collapse; }
    .table-sm td { padding:2px 4px; font-size:8.5pt; }
    .table-borderless td { border:none; }
    code { font-family:monospace; font-size:8.5pt; background:none; }
    .badge-success { background:#dcfce7 !important; color:#166534 !important; padding:1px 6px; border-radius:10px; font-size:8pt; -webkit-print-color-adjust:exact; print-color-adjust:exact; }

    .payslip-divider { border-top:1.5px dashed #aaa; margin:10px 0; }

    .payslip-section-title { font-size:7.5pt; text-transform:uppercase; letter-spacing:.08em; color:#555; margin-bottom:6px; border-bottom:1px solid #ddd; padding-bottom:3px; font-weight:600; }
    .comp-row { display:flex; justify-content:space-between; padding:3px 0; font-size:9.5pt; }
    .comp-row.total { font-weight:700; border-top:1.5px solid #333; padding-top:5px; margin-top:4px; font-size:10pt; }

    .text-danger  { color:#c0392b !important; }
    .text-success { color:#166534 !important; }
    .text-info    { color:#0369a1 !important; }
    .text-muted   { color:#666    !important; }

    .payslip-net-box {
      background:#f4f6f9 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact;
      border:1px solid #ddd; border-radius:6px; padding:12px 16px;
      display:flex; justify-content:space-between; align-items:center; margin:10px 0 14px;
    }
    .payslip-net-box h2     { font-size:20pt; font-weight:700; color:#1e3a5f !important; margin:0; }
    .payslip-net-box p      { font-size:8pt; color:#888; margin:0; }
    .payslip-net-box strong { font-size:9.5pt; }
    .payslip-net-box small  { font-size:8pt; color:#888; }

    .print-only { display:block !important; }
    .signature-line { border-top:1px solid #333; padding-top:6px; font-size:8.5pt; color:#333; margin-top:40px; text-align:center; }

    .no-print, .modal-header, .modal-footer { display:none !important; }
  `;

  const win = window.open('', '_blank', 'width=900,height=700');
  win.document.write('<html><head><title>Payslip</title><style>' + styles + '</style></head><body>' + content + '</body></html>');
  win.document.close();
  win.focus();
  setTimeout(function() { win.print(); win.close(); }, 400);
}
</script>
JS;
?>

<?php require_once __DIR__ . '/../layouts/employee_footer.php'; ?>