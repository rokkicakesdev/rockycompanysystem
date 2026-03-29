<?php
// app/views/admin/payslip_export.php
// Admin PDF export for any employee's payslip (downloads as file)
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

$cutoffNum  = Model::periodCutoff($period);
$isFirstCut = ($cutoffNum === 1);
$year13     = Model::periodYear($period);
$record13th = Model::isDecember1stCutoff($period) ? Model::get13thMonthByEmployee($employeeId, $year13) : null;

$absentDed  = (float)($payrollRecord['absent_deduction'] ?? 0);
$otherDed   = (float)($payrollRecord['other_deductions'] ?? 0);
$reconcile  = round($otherDed - $absentDed, 2);

$companyName = defined('COMPANY_NAME') ? COMPANY_NAME : 'Rocky Company';
$companyAddress = defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : '';
$periodLabel = Model::periodLabel($period);
$generatedAt = date('F d, Y h:i A');
$processedBy = htmlspecialchars($payrollRecord['processed_by_name'] ?? $_SESSION['name'] ?? 'Admin');
$empNo       = htmlspecialchars($employee['employee_no'] ?? '');

$p = fn($v) => number_format((float)$v, 2);

$halfBasic     = $p(($employee['basic_salary'] ?? 0) / 2);
$basicSalary   = $p($payrollRecord['basic_salary']   ?? 0);
$allowance     = $p($payrollRecord['allowance']      ?? 0);
$grossPay      = $p($payrollRecord['gross_pay']      ?? 0);
$sssEE         = $p($payrollRecord['sss_ee']         ?? 0);
$philhealthEE  = $p($payrollRecord['philhealth_ee']  ?? 0);
$pagibigEE     = $p($payrollRecord['pagibig_ee']     ?? 0);
$withholdingTax= $p($payrollRecord['withholding_tax']?? 0);
$totalDed      = $p($payrollRecord['total_deductions']??0);
$netPay        = $p($payrollRecord['net_pay']        ?? 0);
$absDedFmt     = $p($absentDed);

$amount13th    = $record13th ? (float)$record13th['amount'] : 0;
$amount13thFmt = $p($amount13th);
$status13th    = $record13th ? ucfirst($record13th['status']) : '';

$statusClass   = $payrollRecord['status'] === 'released' ? 'pill-released' : 'pill-pending';
$statusLabel   = ucfirst($payrollRecord['status'] ?? 'pending');

$empName = htmlspecialchars($employee['name'] ?? '');
$dept    = htmlspecialchars($employee['department'] ?? '');
$pos     = htmlspecialchars($employee['position'] ?? '');
$hired   = htmlspecialchars($employee['date_hired'] ?? '');
$empType = ucfirst(htmlspecialchars($employee['employment_type'] ?? 'Regular'));

$absRow = $absentDed > 0
    ? "<tr><td class='lbl'>Absent Deduction</td><td class='red'>&minus; &#8369; {$absDedFmt}</td></tr>"
    : '';

$otherDedRaw = (float)($payrollRecord['other_deductions'] ?? 0);
$otherDedRow = '';
if ($otherDedRaw > 0 && $reconcile == 0) {
    $otherDedFmt = $p($otherDedRaw);
    $otherDedRow = "<tr><td>Other Deductions</td><td class='amt red'>&minus; &#8369; {$otherDedFmt}</td></tr>";
}

$reconcileFmt = $p(abs($reconcile));
$reconcileRow = '';
if ($reconcile != 0) {
    $reconcileLabel = $reconcile > 0 ? 'Year-End Tax (owe)' : 'Year-End Tax (refund)';
    $reconcileSign  = $reconcile > 0 ? '&minus;' : '+';
    $reconcileColor = $reconcile > 0 ? 'red' : '#16a34a';
    $reconcileRow   = "<tr><td>{$reconcileLabel}</td><td class='amt' style='color:{$reconcileColor}'>{$reconcileSign} &#8369; {$reconcileFmt}</td></tr>";
}

$html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:DejaVu Sans,sans-serif;font-size:9pt;color:#111}
.page{padding:16mm 14mm}
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
.green{color:#15803d}
.red{color:#dc2626}
.net-box{background:#f0f5ff;border:2px solid #1e3a5f;border-radius:6px;padding:10px 14px;margin:10px 0 14px}
.net-amount{font-size:20pt;font-weight:bold;color:#1e3a5f}
.net-meta{font-size:8pt;color:#888}
.net-table{width:100%}
.net-right{text-align:right;vertical-align:middle}
.sig-table{width:100%;margin-top:20px;border-collapse:collapse}
.sig-td{width:50%;text-align:center;padding:0 12px}
.sig-line{border-top:1px solid #555;padding-top:4px;font-size:8pt;color:#555;margin-top:28px}
.footer-table{width:100%;margin-top:14px;border-top:1px solid #e2e8f0;padding-top:5px}
.footer-td{font-size:7pt;color:#aaa}
.footer-right{text-align:right}
</style></head><body>
<div class="page">
<table class="header-table"><tr>
  <td><div class="company-name">{$companyName}</div><div class="company-sub">{$companyAddress}</div><div class="company-sub">Official Payroll Slip — Admin Copy</div></td>
  <td class="hdr-right"><div class="period-label">Pay Period</div><div class="period-value">{$periodLabel}</div><span class="{$statusClass}">{$statusLabel}</span></td>
</tr></table>
<div class="emp-box"><table style="width:100%"><tr>
  <td class="half"><table>
    <tr><td class="lbl">Employee No.</td><td class="val"><strong>{$empNo}</strong></td></tr>
    <tr><td class="lbl">Name</td><td class="val"><strong>{$empName}</strong></td></tr>
    <tr><td class="lbl">Department</td><td class="val">{$dept}</td></tr>
  </table></td>
  <td class="half"><table>
    <tr><td class="lbl">Position</td><td class="val">{$pos}</td></tr>
    <tr><td class="lbl">Date Hired</td><td class="val">{$hired}</td></tr>
    <tr><td class="lbl">Type</td><td class="val">{$empType}</td></tr>
  </table></td>
</tr></table></div>
<table class="split-table"><tr>
  <td class="split-left"><h4 class="section-title">Earnings</h4><table class="comp">
    <tr><td>Basic Salary</td><td class="amt">&#8369; {$basicSalary}</td></tr>
    <tr><td>Allowance</td><td class="amt">&#8369; {$allowance}</td></tr>
HTML;
if ($amount13th > 0) {
    $html .= "<tr><td>13th Month Pay</td><td class='amt' style='color:#0369a1;font-weight:bold;'>&#8369; {$amount13thFmt}</td></tr>";
}
$html .= <<<HTML
    <tr class="total"><td class="green">Gross Pay</td><td class="amt green">&#8369; {$grossPay}</td></tr>
  </table></td>
  <td class="split-right"><h4 class="section-title">Deductions</h4><table class="comp">
HTML;
if ($isFirstCut) {
    $html .= '<tr><td colspan="2" style="font-size:7pt;color:#888;font-style:italic;padding:3px 0;">Gov. deductions not collected on 1st cutoff</td></tr>';
} else {
    $html .= "<tr><td>SSS (EE)</td><td class='amt red'>&minus; &#8369; {$sssEE}</td></tr>"
           . "<tr><td>PhilHealth (EE)</td><td class='amt red'>&minus; &#8369; {$philhealthEE}</td></tr>"
           . "<tr><td>Pag-IBIG (EE)</td><td class='amt red'>&minus; &#8369; {$pagibigEE}</td></tr>";
}
$html .= <<<HTML
    <tr><td>Withholding Tax</td><td class="amt red">&minus; &#8369; {$withholdingTax}</td></tr>
    {$reconcileRow}{$otherDedRow}{$absRow}
    <tr class="total"><td class="red">Total Deductions</td><td class="amt red">&#8369; {$totalDed}</td></tr>
  </table></td>
</tr></table>
<div class="net-box"><table class="net-table"><tr>
  <td><div class="net-meta">NET PAY FOR {$periodLabel}</div><div class="net-amount">&#8369; {$netPay}</div></td>
  <td class="net-right"><div class="net-meta">Processed by</div><strong>{$processedBy}</strong><br><span class="net-meta">Payroll Administrator</span></td>
</tr></table></div>
<table class="sig-table"><tr>
  <td class="sig-td"><div class="sig-line">Employee Signature / Date<br><small style="color:#bbb">{$empName}</small></div></td>
  <td class="sig-td"><div class="sig-line">Authorized Signatory / Date<br><small style="color:#bbb">&nbsp;</small></div></td>
</tr></table>
<table class="footer-table"><tr>
  <td class="footer-td">{$companyName} &mdash; Official Payroll Slip &mdash; {$periodLabel}</td>
  <td class="footer-td footer-right">Generated: {$generatedAt} &mdash; {$companyName} HRIS</td>
</tr></table>
</div></body></html>
HTML;

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
