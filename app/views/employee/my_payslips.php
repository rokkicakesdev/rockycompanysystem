<?php
// app/views/employee/my_payslips.php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header('Location: ' . BASE_URL . 'index.php?error=access_denied');
    exit;
}

$pageTitle  = 'My Payslips';
$employeeId = (int)($_SESSION['employee_id'] ?? 0);

require_once __DIR__ . '/../../../core/Model.php';

$records  = Model::getPayrollRecordsByEmployee($employeeId);
// Employees only see Released payslips
$records  = array_filter($records, fn($r) => ($r['status'] ?? '') === 'released');
$records  = array_values($records);
$employee = $employeeId ? Model::findEmployeeById($employeeId) : null;

// Build full payroll records keyed by period for modal data (released only)
$fullRecords = [];
if ($employeeId) {
    foreach (Model::getPayrollByEmployee($employeeId) as $r) {
        if (($r['status'] ?? '') === 'released') {
            $fullRecords[$r['period']] = $r;
        }
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

// ──────────────────────────────────────────────────────────────────
// FIX: All jQuery-dependent JS is assigned to $extraJs so it is
//      injected by employee_footer.php AFTER jQuery has loaded.
//      printPayslip() is kept in a separate early <script> tag so
//      it remains in global scope for the onclick="" attribute.
// ──────────────────────────────────────────────────────────────────
ob_start();
?>
function fmt(val) {
  return '₱ ' + parseFloat(val || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

$('.view-payslip-btn').on('click', function () {
  var d = $(this).data();
  var period    = d.period;
  // period format is "YYYY-MM-1" or "YYYY-MM-2" — parse base month safely
  var periodBase = period.replace(/-(\d)$/, '');   // "YYYY-MM"
  var cutoffNum  = parseInt(period.slice(-1)) || 2; // 1 or 2
  var dateParts  = periodBase.split('-');
  var periodDate = new Date(parseInt(dateParts[0]), parseInt(dateParts[1]) - 1, 1);
  var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  var periodLbl  = monthNames[periodDate.getMonth()] + ' ' + periodDate.getFullYear()
                 + ' (' + (cutoffNum === 1 ? '1st–15th' : '16th–End') + ')';
  var absences  = parseFloat(d.absences || 0) + parseFloat(d.late || 0);

  // Period labels
  $('#ps-period-label').text(periodLbl);
  $('#ps-net-period').text(periodLbl.toUpperCase());
  // Update cutoff note on basic salary row
  $('#ps-cutoff-note').text('(' + (cutoffNum === 1 ? '1st cutoff' : '2nd cutoff') + ')');

  // Status badge
  var statusClass = d.status === 'released' ? 'badge-released' : (d.status === 'modification' ? 'badge-modification' : 'badge-pending');
  $('#ps-status-badge')
    .attr('class', 'status-badge ' + statusClass)
    .text(d.status ? (d.status.charAt(0).toUpperCase() + d.status.slice(1)) : '—');

  // Earnings — allowance is halved (semi-monthly)
  $('#ps-allowance').text(fmt(parseFloat(d.allowance || 0) / 2));
  $('#ps-gross').text(fmt(d.gross));

  // Gov deductions — show/hide based on cutoff
  var cutoff = parseInt(d.cutoff || 2);
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
  var reconcile = parseFloat(d.reconciliation || 0);
  if (reconcile !== 0) {
    var sign  = reconcile > 0 ? '− ' : '+ ';
    var cls   = reconcile > 0 ? 'text-danger' : 'text-success';
    $('#ps-reconcile').text(sign + fmt(Math.abs(reconcile))).removeClass('text-danger text-success').addClass(cls);
    $('#ps-reconcile-label').text(reconcile > 0 ? 'Year-End Tax (owe)' : 'Year-End Tax (refund)');
    $('#ps-reconcile-row').show();
  } else {
    $('#ps-reconcile-row').hide();
  }
  $('#ps-tax').text('− ' + fmt(d.tax));

  // Absences / Late
  if (absences > 0) {
    $('#ps-absences').text('− ' + fmt(absences));
    $('#ps-absences-row').show();
  } else {
    $('#ps-absences-row').hide();
  }

  // Totals
  $('#ps-deductions').text(fmt(d.deductions));
  $('#ps-net').text(fmt(d.net));

  // Processed by
  $('#ps-processedby').text(d.processedby || '—');

  // 13th Month Pay
  var thirteenth = parseFloat(d.thirteenth || 0);
  if (thirteenth > 0) {
    $('#ps-thirteenth').text(fmt(thirteenth));
    var t13Status = d.thirteenthstatus || '';
    var t13Badge  = t13Status === 'released'
      ? '<span class="ps-badge-released">Released</span>'
      : '<span class="ps-badge-pending">Pending</span>';
    $('#ps-thirteenth-badge').html(t13Badge);
    $('#ps-thirteenth-row').show();
  } else {
    $('#ps-thirteenth-row').hide();
  }

  // PDF download link
  var pdfUrl = 'payslip_pdf.php?period=' + period;
  $('#ps-pdf-link').attr('href', pdfUrl);

  // Show modal
  $('#payslipModal').modal('show');
});
<?php
$extraJs = ob_get_clean();

require_once __DIR__ . '/../layouts/employee_header.php';
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
            <th>Allowance</th>
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
            <td>&#8369; <?= number_format(($employee['basic_salary'] ?? 0) / 2, 2) ?></td>
            <td>&#8369; <?= number_format(($full['allowance'] ?? $employee['allowance'] ?? 0) / 2, 2) ?></td>
            <td>&#8369; <?= number_format($row['gross_pay'] ?? 0, 2) ?></td>
            <td class="text-danger">&#8369; <?= number_format($row['total_deductions'] ?? 0, 2) ?></td>
            <td class="text-success font-weight-bold">&#8369; <?= number_format($row['net_pay'] ?? 0, 2) ?></td>
            <td class="text-center">
              <?php
              $statusMap = [
                  'released'     => 'badge-released',
                  'pending'      => 'badge-pending',
                  'modification' => 'badge-modification',
              ];
              $statusCls = $statusMap[$row['status']] ?? 'badge-pending';
              ?>
              <span class="status-badge <?= $statusCls ?>">
                <?= ucfirst($row['status'] ?? '—') ?>
              </span>
            </td>
            <td class="text-center">
              <?php if ($full): ?>
              <button class="btn btn-xs btn-primary view-payslip-btn"
                data-id="<?= $full['id'] ?>"
                data-period="<?= htmlspecialchars($row['period']) ?>"
                data-gross="<?= $full['gross_pay'] ?>"
                data-allowance="<?= $full['allowance'] ?>"
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
                data-reconciliation="<?= (float)($full['other_deductions'] ?? 0) ?>">
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
  <div class="modal-dialog modal-lg modal-dialog-scrollable payslip-modal-dialog" role="document">
    <div class="modal-content">

      <!-- Modal Header -->
      <div class="modal-header no-print payslip-modal-header">
        <h5 class="modal-title">
          <i class="fas fa-file-invoice-dollar mr-2 text-primary"></i>Payslip Detail
        </h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body p-0" id="payslipPrintArea">

        <!-- ── Payslip Header Bar ──────────────────────────── -->
        <div class="ps-header-bar">
          <div class="ps-header-left">
            <div class="ps-company-name"><?= htmlspecialchars(COMPANY_NAME) ?></div>
            <div class="ps-company-sub">Payroll Slip / Official Document</div>
          </div>
          <div class="ps-header-right">
            <div id="ps-period-label" class="ps-period-value"></div>
            <span id="ps-status-badge" class="status-badge ps-status-pill"></span>
          </div>
        </div>

        <!-- ── Employee Info Grid ─────────────────────────── -->
        <div class="ps-body">
          <div class="ps-emp-grid">
            <div class="ps-emp-field">
              <span class="ps-emp-label">Employee No.</span>
              <span class="ps-emp-value"><code><?= htmlspecialchars($employee['employee_no'] ?? '—') ?></code></span>
            </div>
            <div class="ps-emp-field">
              <span class="ps-emp-label">Position</span>
              <span class="ps-emp-value"><?= htmlspecialchars($employee['position'] ?? '—') ?></span>
            </div>
            <div class="ps-emp-field">
              <span class="ps-emp-label">Name</span>
              <span class="ps-emp-value ps-emp-name"><?= htmlspecialchars($employee['name'] ?? '—') ?></span>
            </div>
            <div class="ps-emp-field">
              <span class="ps-emp-label">Date Start</span>
              <span class="ps-emp-value"><?= htmlspecialchars($employee['date_start'] ?? $employee['date_hired'] ?? '—') ?></span>
            </div>
            <div class="ps-emp-field">
              <span class="ps-emp-label">Department</span>
              <span class="ps-emp-value"><?= htmlspecialchars($employee['department'] ?? '—') ?></span>
            </div>
            <div class="ps-emp-field">
              <span class="ps-emp-label">Status</span>
              <span class="ps-emp-value"><span class="badge badge-success ps-modal-active-badge">Active</span></span>
            </div>
          </div>

          <div class="payslip-divider"></div>

          <!-- ── Earnings & Deductions ──────────────────────── -->
          <div class="ps-comp-grid">

            <!-- Earnings column -->
            <div class="ps-comp-col">
              <div class="ps-comp-heading">Earnings</div>
              <div class="ps-comp-row">
                <span class="ps-comp-label">Basic Salary <small class="ps-cutoff-note text-muted" id="ps-cutoff-note"></small></span>
                <span class="ps-comp-amount">&#8369;&nbsp;<?= number_format(($employee['basic_salary'] ?? 0) / 2, 2) ?></span>
              </div>
              <div class="ps-comp-row">
                <span class="ps-comp-label">Allowance</span>
                <span class="ps-comp-amount" id="ps-allowance">&#8369;&nbsp;0.00</span>
              </div>
              <div class="ps-comp-row ps-modal-13th-row" id="ps-thirteenth-row">
                <span class="ps-comp-label">13th Month Pay <span id="ps-thirteenth-badge" class="badge badge-info ml-1 ps-modal-13th-badge"></span></span>
                <span class="ps-comp-amount text-info font-weight-bold" id="ps-thirteenth">&#8369;&nbsp;0.00</span>
              </div>
              <div class="ps-comp-row ps-comp-total text-success">
                <span class="ps-comp-label">Gross Pay</span>
                <span class="ps-comp-amount" id="ps-gross">&#8369;&nbsp;0.00</span>
              </div>
            </div>

            <!-- Deductions column -->
            <div class="ps-comp-col">
              <div class="ps-comp-heading">Deductions</div>
              <div id="ps-gov-rows">
                <div class="ps-comp-row">
                  <span class="ps-comp-label">SSS</span>
                  <span class="ps-comp-amount text-danger" id="ps-sss">−&nbsp;&#8369;&nbsp;0.00</span>
                </div>
                <div class="ps-comp-row">
                  <span class="ps-comp-label">PhilHealth</span>
                  <span class="ps-comp-amount text-danger" id="ps-philhealth">−&nbsp;&#8369;&nbsp;0.00</span>
                </div>
                <div class="ps-comp-row">
                  <span class="ps-comp-label">Pag-IBIG</span>
                  <span class="ps-comp-amount text-danger" id="ps-pagibig">−&nbsp;&#8369;&nbsp;0.00</span>
                </div>
              </div>
              <div id="ps-no-gov-row" class="ps-comp-row text-muted ps-modal-gov-note">
                <span class="ps-comp-label"><i class="fas fa-info-circle mr-1"></i>Gov. deductions</span>
                <span class="ps-comp-amount">1st cutoff</span>
              </div>
              <div class="ps-comp-row">
                <span class="ps-comp-label">Withholding Tax</span>
                <span class="ps-comp-amount text-danger" id="ps-tax">−&nbsp;&#8369;&nbsp;0.00</span>
              </div>
              <div id="ps-reconcile-row" class="ps-comp-row ps-modal-reconcile-row">
                <span class="ps-comp-label" id="ps-reconcile-label">Year-End Adjustment</span>
                <span class="ps-comp-amount" id="ps-reconcile">&#8369;&nbsp;0.00</span>
              </div>
              <div class="ps-comp-row" id="ps-absences-row">
                <span class="ps-comp-label">Absences / Late</span>
                <span class="ps-comp-amount text-danger" id="ps-absences">−&nbsp;&#8369;&nbsp;0.00</span>
              </div>
              <div class="ps-comp-row ps-comp-total text-danger">
                <span class="ps-comp-label">Total Deductions</span>
                <span class="ps-comp-amount" id="ps-deductions">&#8369;&nbsp;0.00</span>
              </div>
            </div>

          </div><!-- /.ps-comp-grid -->

          <div class="payslip-divider"></div>

          <!-- ── Net Pay Box ─────────────────────────────────── -->
          <div class="ps-net-box">
            <div class="ps-net-left">
              <div class="ps-net-label">NET PAY FOR <span id="ps-net-period"></span></div>
              <div class="ps-net-amount" id="ps-net">&#8369; 0.00</div>
            </div>
            <div class="ps-net-right">
              <div class="ps-net-processed-label">Processed by</div>
              <div class="ps-net-processed-name" id="ps-processedby"></div>
              <div class="ps-net-processed-role">Payroll Administrator</div>
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

        </div><!-- /.ps-body -->
      </div><!-- /#payslipPrintArea -->

      <!-- ── Modal Footer ────────────────────────────────────── -->
      <div class="modal-footer no-print ps-modal-actions">
        <div class="ps-modal-timestamp">
          <i class="fas fa-clock mr-1"></i>
          Generated: <?= date('M d, Y h:i A') ?>
        </div>
        <div class="ps-modal-btns">
          <button type="button" class="btn btn-success ps-btn" onclick="printPayslip()">
            <i class="fas fa-print mr-1"></i>Print Payslip
          </button>
          <a id="ps-pdf-link" href="#" class="btn btn-primary ps-btn">
            <i class="fas fa-file-pdf mr-1"></i>Export PDF
          </a>
        </div>
      </div>

    </div><!-- /.modal-content -->
  </div>
</div>

<!-- printPayslip opens a clean, formatted print window with CONFIDENTIAL watermark -->
<script>
function printPayslip() {
  var area = document.getElementById('payslipPrintArea');
  if (!area) return;

  // Collect displayed values from modal
  var period      = $('#ps-period-label').text();
  var empName     = '<?= addslashes(htmlspecialchars($employee['name'] ?? '')) ?>';
  var empNo       = '<?= addslashes(htmlspecialchars($employee['employee_no'] ?? '')) ?>';
  var dept        = '<?= addslashes(htmlspecialchars($employee['department'] ?? '')) ?>';
  var pos         = '<?= addslashes(htmlspecialchars($employee['position'] ?? '')) ?>';
  var hired       = '<?= addslashes(htmlspecialchars($employee['date_start'] ?? $employee['date_hired'] ?? '')) ?>';
  var company     = '<?= addslashes(htmlspecialchars(COMPANY_NAME)) ?>';
  var statusTxt   = $('#ps-status-badge').text().trim();
  var statusCls   = $('#ps-status-badge').hasClass('badge-released') ? 'pill-released' : 'pill-pending';
  var basic       = '<?= number_format(($employee['basic_salary'] ?? 0) / 2, 2) ?>';
  var allowance   = $('#ps-allowance').text().replace('₱\u00a0','');
  var gross       = $('#ps-gross').text().replace('₱\u00a0','');
  var sss         = $('#ps-sss').text().replace('−\u00a0₱\u00a0','');
  var philhealth  = $('#ps-philhealth').text().replace('−\u00a0₱\u00a0','');
  var pagibig     = $('#ps-pagibig').text().replace('−\u00a0₱\u00a0','');
  var tax         = $('#ps-tax').text().replace('−\u00a0₱\u00a0','');
  var deductions  = $('#ps-deductions').text().replace('₱\u00a0','');
  var net         = $('#ps-net').text().replace('₱\u00a0','');
  var processedBy = $('#ps-processedby').text();
  var cutoffNote  = $('#ps-cutoff-note').text();
  var govHidden   = $('#ps-gov-rows').css('display') === 'none';

  var html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Payslip</title>'
    + '<style>'
    + '*{margin:0;padding:0;box-sizing:border-box}'
    + 'body{font-family:Arial,sans-serif;font-size:9pt;color:#111;background:#fff}'
    + '.page{padding:16mm 14mm;position:relative}'
    + '.watermark{position:fixed;top:35%;left:5%;font-size:52pt;font-weight:bold;color:rgba(30,58,95,0.07);transform:rotate(-30deg);letter-spacing:4px;z-index:0;pointer-events:none}'
    + '.hdr{width:100%;border-bottom:3px solid #1e3a5f;padding-bottom:8px;margin-bottom:10px;display:flex;justify-content:space-between}'
    + '.company-name{font-size:14pt;font-weight:bold;color:#1e3a5f}'
    + '.company-sub{font-size:8pt;color:#666;margin-top:1px}'
    + '.hdr-right{text-align:right}'
    + '.period-lbl{font-size:8pt;color:#888}'
    + '.period-val{font-size:11pt;font-weight:bold;color:#1e3a5f}'
    + '.pill-released{background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:6px;font-size:8pt;font-weight:bold}'
    + '.pill-pending{background:#fef9c3;color:#b45309;padding:2px 8px;border-radius:6px;font-size:8pt;font-weight:bold}'
    + '.emp-box{border:1px solid #e2e8f0;border-radius:4px;padding:8px 10px;margin-bottom:10px}'
    + 'table.info{width:100%;border-collapse:collapse}'
    + '.lbl{color:#888;width:35%;font-size:8.5pt;padding:2px 4px}'
    + '.val{font-size:8.5pt;padding:2px 4px}'
    + '.sec-title{font-size:8pt;font-weight:bold;color:#1e3a5f;text-transform:uppercase;letter-spacing:.5px;border-bottom:1.5px solid #e2e8f0;padding-bottom:3px;margin-bottom:5px;margin-top:8px}'
    + '.split{width:100%;border-collapse:collapse}'
    + '.split td{vertical-align:top;width:50%}'
    + '.split .left{padding-right:8px}.split .right{padding-left:8px}'
    + 'table.comp{width:100%;border-collapse:collapse}'
    + 'table.comp td{font-size:8.5pt;padding:2px 2px;border-bottom:1px dotted #eee}'
    + 'table.comp .amt{text-align:right}'
    + 'table.comp .total td{font-weight:bold;border-bottom:2px solid #cbd5e1}'
    + '.green{color:#15803d}.red{color:#dc2626}'
    + '.net-box{background:#f0f5ff;border:2px solid #1e3a5f;border-radius:6px;padding:10px 14px;margin:10px 0}'
    + '.net-amt{font-size:20pt;font-weight:bold;color:#1e3a5f}'
    + '.net-meta{font-size:8pt;color:#888}'
    + '.sig-tbl{width:100%;border-collapse:collapse;margin-top:24px}'
    + '.sig-td{width:50%;text-align:center;padding:0 12px}'
    + '.sig-line{border-top:1px solid #555;padding-top:4px;font-size:8pt;color:#555;margin-top:28px}'
    + '.footer{margin-top:14px;border-top:1px solid #e2e8f0;padding-top:5px;font-size:7pt;color:#aaa;display:flex;justify-content:space-between}'
    + '</style></head><body><div class="page">'
    + '<div class="watermark">CONFIDENTIAL</div>'
    + '<div class="hdr">'
    + '<div><div class="company-name">' + company + '</div><div class="company-sub">Official Payroll Slip</div></div>'
    + '<div class="hdr-right"><div class="period-lbl">Pay Period</div><div class="period-val">' + period + '</div>'
    + '<span class="' + statusCls + '">' + statusTxt + '</span></div>'
    + '</div>'
    + '<div class="emp-box"><table class="info"><tr>'
    + '<td class="left"><table>'
    + '<tr><td class="lbl">Employee No.</td><td class="val"><strong>' + empNo + '</strong></td></tr>'
    + '<tr><td class="lbl">Name</td><td class="val"><strong>' + empName + '</strong></td></tr>'
    + '<tr><td class="lbl">Department</td><td class="val">' + dept + '</td></tr>'
    + '</table></td>'
    + '<td class="right"><table>'
    + '<tr><td class="lbl">Position</td><td class="val">' + pos + '</td></tr>'
    + '<tr><td class="lbl">Date Start</td><td class="val">' + hired + '</td></tr>'
    + '</table></td>'
    + '</tr></table></div>'
    + '<table class="split"><tr>'
    + '<td class="left"><div class="sec-title">Earnings</div><table class="comp">'
    + '<tr><td>Basic Salary <small>' + cutoffNote + '</small></td><td class="amt">&#8369; ' + basic + '</td></tr>'
    + '<tr><td>Allowance</td><td class="amt">&#8369; ' + allowance + '</td></tr>'
    + '<tr class="total"><td class="green">Gross Pay</td><td class="amt green">&#8369; ' + gross + '</td></tr>'
    + '</table></td>'
    + '<td class="right"><div class="sec-title">Deductions</div><table class="comp">';

  if (govHidden) {
    html += '<tr><td colspan="2" style="font-size:7pt;color:#888;font-style:italic;padding:3px 0">Gov. deductions not collected on 1st cutoff</td></tr>';
  } else {
    html += '<tr><td>SSS</td><td class="amt red">&minus; &#8369; ' + sss + '</td></tr>'
          + '<tr><td>PhilHealth</td><td class="amt red">&minus; &#8369; ' + philhealth + '</td></tr>'
          + '<tr><td>Pag-IBIG</td><td class="amt red">&minus; &#8369; ' + pagibig + '</td></tr>';
  }
  html += '<tr><td>Withholding Tax</td><td class="amt red">&minus; &#8369; ' + tax + '</td></tr>'
        + '<tr class="total"><td class="red">Total Deductions</td><td class="amt red">&#8369; ' + deductions + '</td></tr>'
        + '</table></td></tr></table>'
        + '<div class="net-box"><table style="width:100%"><tr>'
        + '<td><div class="net-meta">NET PAY FOR ' + period.toUpperCase() + '</div><div class="net-amt">&#8369; ' + net + '</div></td>'
        + '<td style="text-align:right;vertical-align:middle"><div class="net-meta">Processed by</div><strong>' + processedBy + '</strong><br><span class="net-meta">Payroll Administrator</span></td>'
        + '</tr></table></div>'
        + '<table class="sig-tbl"><tr>'
        + '<td class="sig-td"><div class="sig-line">Employee Signature / Date<br><small style="color:#bbb">' + empName + '</small></div></td>'
        + '<td class="sig-td"><div class="sig-line">Authorized Signatory / Date<br><small style="color:#bbb">&nbsp;</small></div></td>'
        + '</tr></table>'
        + '<div class="footer"><span>' + company + ' &mdash; Official Payroll Slip &mdash; ' + period + '</span>'
        + '<span>Generated: ' + new Date().toLocaleString() + '</span></div>'
        + '</div></body></html>';

  var win = window.open('', '_blank', 'width=900,height=700');
  win.document.write(html);
  win.document.close();
  win.focus();
  setTimeout(function(){ win.print(); win.close(); }, 800);
}
</script>

<?php require_once __DIR__ . '/../layouts/employee_footer.php'; ?>