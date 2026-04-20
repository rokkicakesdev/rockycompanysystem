<?php
// app/views/admin/payslip_export.php — Admin PDF export
// • YTD section matches payslip view
// • CONFIDENTIAL watermark
// • No generated date/time in footer
// • Employee Signature line removed
// • Employer Contributions (2nd cutoff)
// • Attendance summary

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../core/Model.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) {
    header('Location: ' . BASE_URL . '/index.php?error=access_denied'); exit;
}

$employeeId = (int)($_GET['emp']    ?? 0);
$period     = trim($_GET['period'] ?? '');
if (!$employeeId || !preg_match('/^\d{4}-\d{2}-[12]$/', $period)) {
    http_response_code(400); die('Invalid request.');
}

$employee = Model::findEmployeeById($employeeId);
if (!$employee) { http_response_code(404); die('Employee not found.'); }

$payrollRecord = null;
foreach (Model::getPayrollByEmployee($employeeId) as $r) {
    if ($r['period'] === $period) { $payrollRecord = $r; break; }
}
if (!$payrollRecord) { http_response_code(404); die('No payroll record for this period.'); }

$autoload = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($autoload)) { die('Dompdf not installed. Run: composer require dompdf/dompdf'); }
require_once $autoload;

$cutoffNum      = Model::periodCutoff($period);
$isFirstCut     = ($cutoffNum === 1);
$year13         = Model::periodYear($period);
$record13th     = Model::isDecember1stCutoff($period) ? Model::get13thMonthByEmployee($employeeId, $year13) : null;

$absentDed      = (float)($payrollRecord['absent_deduction']       ?? 0);
$unpaidLeaveDed = (float)($payrollRecord['unpaid_leave_deduction'] ?? 0);
$salaryDedTotal = (float)($payrollRecord['salary_deduction']       ?? 0);
$reconcile      = (float)($payrollRecord['other_deductions']       ?? 0);

$companyName    = defined('COMPANY_NAME')    ? COMPANY_NAME    : 'Rocky Company';
$companyAddress = defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : '';
$periodLabel    = Model::periodLabel($period);
$processedBy    = htmlspecialchars($payrollRecord['processed_by_name'] ?? $_SESSION['name'] ?? 'Admin');
$empNo          = htmlspecialchars($employee['employee_no'] ?? '');
$p              = fn($v) => number_format((float)$v, 2);

$basicSalary    = $p($payrollRecord['basic_salary']    ?? 0);
$allowance      = $p($payrollRecord['allowance']       ?? 0);
$grossPay       = $p($payrollRecord['gross_pay']       ?? 0);
$sssEE          = $p($payrollRecord['sss_ee']          ?? 0);
$philhealthEE   = $p($payrollRecord['philhealth_ee']   ?? 0);
$pagibigEE      = $p($payrollRecord['pagibig_ee']      ?? 0);
$withholdingTax = $p($payrollRecord['withholding_tax'] ?? 0);
$totalDed       = $p($payrollRecord['total_deductions']?? 0);
$netPay         = $p($payrollRecord['net_pay']         ?? 0);
$absDedFmt      = $p($absentDed);
$unpaidDedFmt   = $p($unpaidLeaveDed);
$overtimePay    = (float)($payrollRecord['overtime_pay'] ?? 0);
$holidayPay     = (float)($payrollRecord['holiday_pay']  ?? 0);
$otPayFmt       = $p($overtimePay);
$holidayPayFmt  = $p($holidayPay);

$amount13th    = $record13th ? (float)$record13th['amount'] : 0;
$amount13thFmt = $p($amount13th);
$status13th    = $record13th ? ucfirst($record13th['status']) : '';

$sssER        = (float)($payrollRecord['sss_er']        ?? 0);
$philhealthER = (float)($payrollRecord['philhealth_er'] ?? 0);
$pagibigER    = (float)($payrollRecord['pagibig_er']    ?? 0);
$totalER      = round($sssER + $philhealthER + $pagibigER, 2);
$sssERFmt     = $p($sssER); $phERFmt = $p($philhealthER); $piERFmt = $p($pagibigER); $totalERFmt = $p($totalER);

$statusClass = $payrollRecord['status'] === 'released' ? 'pill-released' : 'pill-pending';
$statusLabel = ucfirst($payrollRecord['status'] ?? 'pending');
$empName     = htmlspecialchars($employee['name']       ?? '');
$dept        = htmlspecialchars($employee['department'] ?? '');
$pos         = htmlspecialchars($employee['position']   ?? '');
$dateStart   = htmlspecialchars($employee['date_start'] ?? $employee['date_hired'] ?? '');
$empType     = ucfirst(htmlspecialchars($employee['employment_type'] ?? 'Regular'));

$absRow    = $absentDed      > 0 ? "<tr><td class='lbl'>Absent Deduction</td><td class='red'>&minus; &#8369; {$absDedFmt}</td></tr>" : '';
$unpaidRow = $unpaidLeaveDed > 0 ? "<tr><td class='lbl'>Unpaid Leave</td><td class='red'>&minus; &#8369; {$unpaidDedFmt}</td></tr>" : '';

$salaryDedRows = '';
if ($salaryDedTotal > 0) {
    foreach (Model::getSalaryDeductions((int)$payrollRecord['id']) as $si) {
        $r2  = ucwords(str_replace('_', ' ', $si['reason']));
        $d2  = !empty($si['description']) ? " ({$si['description']})" : '';
        $salaryDedRows .= "<tr><td class='lbl'>{$r2}{$d2}</td><td class='red'>&minus; &#8369; {$p($si['amount'])}</td></tr>";
    }
}
$reconcileFmt = $p(abs($reconcile));
$reconcileRow = '';
if ($reconcile != 0) {
    $rL = $reconcile > 0 ? 'Year-End Tax (owe)' : 'Year-End Tax (refund)';
    $rS = $reconcile > 0 ? '&minus;' : '+';
    $rC = $reconcile > 0 ? 'red' : '#16a34a';
    $reconcileRow = "<tr><td>{$rL}</td><td class='amt' style='color:{$rC}'>{$rS} &#8369; {$reconcileFmt}</td></tr>";
}

// Attendance block
$schedDays  = $payrollRecord['working_days_in_month'] ?? null;
$daysAbsent = (int)($payrollRecord['days_absent']     ?? 0);
$paidLeave  = (int)($payrollRecord['days_paid_leave'] ?? 0);
$attBlock = '';
if ($schedDays !== null) {
    $dw = (int)(($payrollRecord['days_worked'] ?? null) ?? max(0, $schedDays - $daysAbsent));
    $attBlock = "<h4 class='section-title'>Attendance Summary</h4><table class='att-table'><tr>"
              . "<td class='att-cell'><div class='att-num'>{$schedDays}</div><div class='att-lbl'>Scheduled Days</div></td>"
              . "<td class='att-cell'><div class='att-num green'>{$dw}</div><div class='att-lbl'>Days Worked</div></td>"
              . "<td class='att-cell'><div class='att-num red'>{$daysAbsent}</div><div class='att-lbl'>Days Absent</div></td>"
              . "<td class='att-cell'><div class='att-num amber'>{$paidLeave}</div><div class='att-lbl'>On Leave</div></td>"
              . "</tr></table>";
}

// Employer contributions (2nd cutoff)
$erBlock = '';
if (!$isFirstCut && $totalER > 0) {
    $erBlock = "<h4 class='section-title'>Employer Contributions <span style='font-weight:400;font-size:7pt;color:#888;'>(not deducted from employee)</span></h4>"
             . "<table class='split-table'><tr>"
             . "<td class='split-left'><table class='comp'>"
             . "<tr><td>SSS (Employer Share)</td><td class='amt blue'>&#8369; {$sssERFmt}</td></tr>"
             . "<tr><td>PhilHealth (Employer Share)</td><td class='amt blue'>&#8369; {$phERFmt}</td></tr>"
             . "</table></td>"
             . "<td class='split-right'><table class='comp'>"
             . "<tr><td>Pag-IBIG (Employer Share)</td><td class='amt blue'>&#8369; {$piERFmt}</td></tr>"
             . "<tr class='total'><td>Total ER Contributions</td><td class='amt blue'>&#8369; {$totalERFmt}</td></tr>"
             . "</table></td></tr></table>";
}

// YTD block
$ytd = Model::getPayrollYTD($employeeId, $period);
$ytdBlock = '';
if (!empty($ytd) && (float)($ytd['ytd_gross'] ?? 0) > 0) {
    $yB=$p($ytd['ytd_basic']??0); $yA=$p($ytd['ytd_allowance']??0); $yG=$p($ytd['ytd_gross']??0);
    $yS=$p($ytd['ytd_sss_ee']??0); $yPh=$p($ytd['ytd_philhealth_ee']??0); $yPi=$p($ytd['ytd_pagibig_ee']??0);
    $yT=$p($ytd['ytd_tax']??0); $yD=$p($ytd['ytd_deductions']??0); $yN=$p($ytd['ytd_net']??0);
    $yPer=(int)($ytd['ytd_periods']??0);
    $ySER=$p($ytd['ytd_sss_er']??0); $yPER=$p($ytd['ytd_philhealth_er']??0); $yIER=$p($ytd['ytd_pagibig_er']??0);
    $yTER=$p(($ytd['ytd_sss_er']??0)+($ytd['ytd_philhealth_er']??0)+($ytd['ytd_pagibig_er']??0));
    $yAbsent=(float)($ytd['ytd_absent_deduction']??0); $yUnp=(float)($ytd['ytd_unpaid_leave']??0);
    $ySalD=(float)($ytd['ytd_salary_deduction']??0); $yRec=(float)($ytd['ytd_reconciliation']??0);
    $yAbsentR = $yAbsent>0 ? "<tr><td>Absent Deductions</td><td class='amt red'>&#8369; {$p($yAbsent)}</td></tr>" : '';
    $yUnpR    = $yUnp>0    ? "<tr><td>Unpaid Leave</td><td class='amt red'>&#8369; {$p($yUnp)}</td></tr>" : '';
    $ySalR    = $ySalD>0   ? "<tr><td>Salary Deductions</td><td class='amt red'>&#8369; {$p($ySalD)}</td></tr>" : '';
    $yRecR    = $yRec!=0   ? "<tr><td>Year-End Reconciliation</td><td class='amt red'>&#8369; {$p(abs($yRec))}</td></tr>" : '';

    $ytdBlock = "<h4 class='section-title'>Year-to-Date Summary ({$yPer} pay period(s))</h4>"
              . "<table class='split-table'><tr>"
              . "<td class='split-left'><table class='comp'>"
              . "<tr><td colspan='2' style='font-size:7pt;color:#888;font-weight:bold;padding-bottom:2px;'>EARNINGS YTD</td></tr>"
              . "<tr><td>Basic Salary</td><td class='amt'>&#8369; {$yB}</td></tr>"
              . "<tr><td>Allowance</td><td class='amt'>&#8369; {$yA}</td></tr>"
              . "<tr class='total'><td class='green'>Gross Pay</td><td class='amt green'>&#8369; {$yG}</td></tr>"
              . "<tr class='total'><td>Net Pay YTD</td><td class='amt'>&#8369; {$yN}</td></tr>"
              . "<tr><td colspan='2' style='font-size:7pt;color:#888;font-weight:bold;padding:6px 0 2px;'>EMPLOYER CONTRIBUTIONS YTD</td></tr>"
              . "<tr><td>SSS (ER)</td><td class='amt blue'>&#8369; {$ySER}</td></tr>"
              . "<tr><td>PhilHealth (ER)</td><td class='amt blue'>&#8369; {$yPER}</td></tr>"
              . "<tr><td>Pag-IBIG (ER)</td><td class='amt blue'>&#8369; {$yIER}</td></tr>"
              . "<tr class='total'><td>Total ER YTD</td><td class='amt blue'>&#8369; {$yTER}</td></tr>"
              . "</table></td>"
              . "<td class='split-right'><table class='comp'>"
              . "<tr><td colspan='2' style='font-size:7pt;color:#888;font-weight:bold;padding-bottom:2px;'>DEDUCTIONS YTD</td></tr>"
              . "<tr><td>SSS (EE)</td><td class='amt red'>&#8369; {$yS}</td></tr>"
              . "<tr><td>PhilHealth (EE)</td><td class='amt red'>&#8369; {$yPh}</td></tr>"
              . "<tr><td>Pag-IBIG (EE)</td><td class='amt red'>&#8369; {$yPi}</td></tr>"
              . "<tr><td>Withholding Tax</td><td class='amt red'>&#8369; {$yT}</td></tr>"
              . "{$yAbsentR}{$yUnpR}{$ySalR}{$yRecR}"
              . "<tr class='total'><td class='red'>Total Deductions</td><td class='amt red'>&#8369; {$yD}</td></tr>"
              . "</table></td></tr></table>";
}

// Build full HTML
$css = '*{margin:0;padding:0;box-sizing:border-box}
body{font-family:DejaVu Sans,sans-serif;font-size:9pt;color:#111}
.page{padding:16mm 14mm}
.watermark{position:fixed;top:38%;left:5%;font-size:62pt;font-weight:bold;color:rgba(220,38,38,0.07);transform:rotate(-30deg);z-index:-1;letter-spacing:6px;white-space:nowrap}
.header-table{width:100%;border-bottom:3px solid #1e3a5f;padding-bottom:10px;margin-bottom:12px}
.company-name{font-size:15pt;font-weight:bold;color:#1e3a5f}
.company-sub{font-size:8pt;color:#666;margin-top:1px}
.hdr-right{text-align:right}
.period-label{font-size:8pt;color:#888}
.period-value{font-size:12pt;font-weight:bold;color:#1e3a5f}
.pill-released{background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:6px;font-size:8pt;font-weight:bold}
.pill-pending{background:#fef9c3;color:#b45309;padding:2px 8px;border-radius:6px;font-size:8pt;font-weight:bold}
.emp-box{border:1px solid #e2e8f0;border-radius:4px;padding:8px 10px;margin-bottom:12px}
.half{width:50%;vertical-align:top}
.lbl{color:#888;width:35%;font-size:8.5pt;padding:2px 4px}
.val{font-size:8.5pt;padding:2px 4px}
.section-title{font-size:8pt;font-weight:bold;color:#1e3a5f;text-transform:uppercase;letter-spacing:.5px;border-bottom:1.5px solid #e2e8f0;padding-bottom:3px;margin-bottom:6px;margin-top:10px}
.split-table{width:100%;margin-bottom:10px}
.split-left{width:50%;vertical-align:top;padding-right:8px}
.split-right{width:50%;vertical-align:top;padding-left:8px}
.comp{width:100%;border-collapse:collapse}
.comp td{padding:2px 2px;font-size:8.5pt;border-bottom:1px dotted #eee}
.comp .amt{text-align:right}
.comp .total{font-weight:bold;border-bottom:2px solid #cbd5e1}
.green{color:#15803d}.red{color:#dc2626}.amber{color:#d97706}.blue{color:#1d4ed8}
.net-box{background:#f0f5ff;border:2px solid #1e3a5f;border-radius:6px;padding:10px 14px;margin:10px 0 14px}
.net-amount{font-size:20pt;font-weight:bold;color:#1e3a5f}
.net-meta{font-size:8pt;color:#888}
.net-table{width:100%}
.net-right{text-align:right;vertical-align:middle}
.att-table{width:100%;border-collapse:collapse;margin-bottom:10px}
.att-cell{text-align:center;border:1px solid #e2e8f0;border-radius:4px;padding:6px}
.att-num{font-size:14pt;font-weight:bold;color:#1e3a5f}
.att-lbl{font-size:7pt;color:#888}
.sig-table{width:100%;margin-top:20px;border-collapse:collapse}
.sig-td{width:50%;text-align:center;padding:0 12px}
.sig-line{border-top:1px solid #555;padding-top:4px;font-size:8pt;color:#555;margin-top:28px}
.footer-table{width:100%;margin-top:14px;border-top:1px solid #e2e8f0;padding-top:5px}
.footer-td{font-size:7pt;color:#aaa}
.footer-right{text-align:right}';

$deductionRows = '';
if ($isFirstCut) {
    $deductionRows = '<tr><td colspan="2" style="font-size:7pt;color:#888;font-style:italic;padding:3px 0;">Gov. deductions not collected on 1st cutoff</td></tr>';
} else {
    $deductionRows = "<tr><td>SSS (EE)</td><td class='amt red'>&minus; &#8369; {$sssEE}</td></tr>"
                   . "<tr><td>PhilHealth (EE)</td><td class='amt red'>&minus; &#8369; {$philhealthEE}</td></tr>"
                   . "<tr><td>Pag-IBIG (EE)</td><td class='amt red'>&minus; &#8369; {$pagibigEE}</td></tr>";
}

$earningRows13 = '';
if ($amount13th > 0) {
    $pill13 = $status13th === 'Released'
        ? '<span style="background:#dcfce7;color:#15803d;padding:1px 5px;border-radius:3px;font-size:7pt;">' . $status13th . '</span>'
        : '<span style="background:#fef9c3;color:#b45309;padding:1px 5px;border-radius:3px;font-size:7pt;">' . $status13th . '</span>';
    $earningRows13 = "<tr><td>13th Month Pay {$pill13}</td><td class='amt' style='color:#0369a1;font-weight:bold;'>&#8369; {$amount13thFmt}</td></tr>";
}
$earningRowsOT = $overtimePay > 0 ? "<tr><td>Overtime Pay</td><td class='amt' style='color:#b45309;font-weight:bold;'>&#8369; {$otPayFmt}</td></tr>" : '';
$earningRowsHP = $holidayPay  > 0 ? "<tr><td>Holiday Premium Pay</td><td class='amt' style='color:#b45309;font-weight:bold;'>&#8369; {$holidayPayFmt}</td></tr>" : '';

$html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>{$css}</style></head><body><div class='page'>"
      . "<div class='watermark'>CONFIDENTIAL</div>"
      . "<table class='header-table'><tr>"
      . "<td><div class='company-name'>{$companyName}</div><div class='company-sub'>{$companyAddress}</div><div class='company-sub'>Official Payroll Slip</div></td>"
      . "<td class='hdr-right'><div class='period-label'>Pay Period</div><div class='period-value'>{$periodLabel}</div><span class='{$statusClass}'>{$statusLabel}</span></td>"
      . "</tr></table>"
      . "<div class='emp-box'><table style='width:100%'><tr>"
      . "<td class='half'><table><tr><td class='lbl'>Employee No.</td><td class='val'><strong>{$empNo}</strong></td></tr><tr><td class='lbl'>Name</td><td class='val'><strong>{$empName}</strong></td></tr><tr><td class='lbl'>Department</td><td class='val'>{$dept}</td></tr></table></td>"
      . "<td class='half'><table><tr><td class='lbl'>Position</td><td class='val'>{$pos}</td></tr><tr><td class='lbl'>Date Start</td><td class='val'>{$dateStart}</td></tr><tr><td class='lbl'>Status</td><td class='val'>{$empType}</td></tr></table></td>"
      . "</tr></table></div>"
      . "<table class='split-table'><tr>"
      . "<td class='split-left'><h4 class='section-title'>Earnings</h4><table class='comp'>"
      . "<tr><td>Basic Salary</td><td class='amt'>&#8369; {$basicSalary}</td></tr>"
      . "<tr><td>Allowance</td><td class='amt'>&#8369; {$allowance}</td></tr>"
      . "{$earningRowsOT}{$earningRowsHP}{$earningRows13}"
      . "<tr class='total'><td class='green'>Gross Pay</td><td class='amt green'>&#8369; {$grossPay}</td></tr>"
      . "</table></td>"
      . "<td class='split-right'><h4 class='section-title'>Deductions</h4><table class='comp'>"
      . "{$deductionRows}"
      . "<tr><td>Withholding Tax</td><td class='amt red'>&minus; &#8369; {$withholdingTax}</td></tr>"
      . "{$absRow}{$unpaidRow}{$salaryDedRows}{$reconcileRow}"
      . "<tr class='total'><td class='red'>Total Deductions</td><td class='amt red'>&#8369; {$totalDed}</td></tr>"
      . "</table></td></tr></table>"
      . "<div class='net-box'><table class='net-table'><tr>"
      . "<td><div class='net-meta'>NET PAY FOR {$periodLabel}</div><div class='net-amount'>&#8369; {$netPay}</div></td>"
      . "<td class='net-right'><div class='net-meta'>Processed by</div><strong>{$processedBy}</strong><br><span class='net-meta'>Payroll Administrator</span></td>"
      . "</tr></table></div>"
      . "{$attBlock}"
      . "{$erBlock}"
      . "{$ytdBlock}"
      // Authorized Signatory only — no employee signature line
      . "<table class='sig-table'><tr><td class='sig-td'></td>"
      . "<td class='sig-td'><div class='sig-line'>Authorized Signatory / Date<br><small style='color:#bbb;'>&nbsp;</small></div></td>"
      . "</tr></table>"
      // Footer: no generated timestamp
      . "<table class='footer-table'><tr>"
      . "<td class='footer-td'>{$companyName} &mdash; Official Payroll Slip &mdash; {$periodLabel}</td>"
      . "<td class='footer-td footer-right'>{$companyName} HRIS + Payroll System</td>"
      . "</tr></table>"
      . "</div></body></html>";

$options = new \Dompdf\Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);
$options->set('isPhpEnabled', false);
$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
while (ob_get_level()) { ob_end_clean(); }
$filename = 'Payslip_' . ($empNo ?: $employeeId) . '_' . $period . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;