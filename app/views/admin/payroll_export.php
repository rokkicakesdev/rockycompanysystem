<?php
// app/views/admin/payroll_export.php
// Payroll export — Excel (SpreadsheetML) or printable HTML for PDF

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../core/Model.php';

// Auth
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'management'])) {
    header('Location: ' . BASE_URL . '/index.php?error=access_denied');
    exit;
}

$period = trim($_GET['period'] ?? '');
$format = trim($_GET['format'] ?? 'excel');

if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
    die('Invalid period.');
}

$records = Model::getPayrollByPeriod($period);
if (empty($records)) {
    die('No payroll records found for period: ' . htmlspecialchars($period));
}

// Totals
$totals = [
    'basic_salary' => 0, 'allowance' => 0, 'gross_pay' => 0,
    'sss_ee' => 0, 'philhealth_ee' => 0, 'pagibig_ee' => 0,
    'withholding_tax' => 0, 'other_deductions' => 0, 'total_deductions' => 0,
    'net_pay' => 0, 'sss_er' => 0, 'philhealth_er' => 0, 'pagibig_er' => 0,
];
foreach ($records as $r) {
    foreach (array_keys($totals) as $k) {
        $totals[$k] += (float)($r[$k] ?? 0);
    }
}

$companyName = defined('COMPANY_NAME') ? COMPANY_NAME : 'Rocky Company';
$periodLabel = date('F Y', strtotime($period . '-01'));
$generatedAt = date('F d, Y h:i A');
$processedBy = $_SESSION['name'] ?? 'System';

// ============================================================
//  EXCEL EXPORT (SpreadsheetML)
// ============================================================
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Payroll_' . $period . '.xml"');
    header('Cache-Control: max-age=0');

    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $out .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
    $out .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    $out .= '  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    $out .= '  xmlns:x="urn:schemas-microsoft-com:office:excel">' . "\n";

    // Styles
    $out .= '<Styles>' . "\n";
    $styles = [
        'title'       => ['font' => 'Bold="1" Size="14" Color="#1e3a5f"', 'align' => 'Horizontal="Center"', 'fill' => '', 'border' => '', 'numfmt' => ''],
        'subtitle'    => ['font' => 'Size="10" Color="#555555"', 'align' => 'Horizontal="Center"', 'fill' => '', 'border' => '', 'numfmt' => ''],
        'header'      => ['font' => 'Bold="1" Color="#FFFFFF" Size="9"', 'align' => 'Horizontal="Center" WrapText="1"', 'fill' => 'Color="#1e3a5f" Pattern="Solid"', 'border' => '', 'numfmt' => ''],
        'data'        => ['font' => 'Size="9"', 'align' => '', 'fill' => '', 'border' => 'light', 'numfmt' => ''],
        'dataAlt'     => ['font' => 'Size="9"', 'align' => '', 'fill' => 'Color="#F5F8FF" Pattern="Solid"', 'border' => 'light', 'numfmt' => ''],
        'currency'    => ['font' => 'Size="9"', 'align' => '', 'fill' => '', 'border' => 'light', 'numfmt' => '#,##0.00'],
        'currencyAlt' => ['font' => 'Size="9"', 'align' => '', 'fill' => 'Color="#F5F8FF" Pattern="Solid"', 'border' => 'light', 'numfmt' => '#,##0.00'],
        'totalLabel'  => ['font' => 'Bold="1" Size="9" Color="#FFFFFF"', 'align' => 'Horizontal="Right"', 'fill' => 'Color="#2e5090" Pattern="Solid"', 'border' => '', 'numfmt' => ''],
        'totalNum'    => ['font' => 'Bold="1" Size="9" Color="#FFFFFF"', 'align' => '', 'fill' => 'Color="#2e5090" Pattern="Solid"', 'border' => '', 'numfmt' => '#,##0.00'],
        'netPay'      => ['font' => 'Bold="1" Size="9" Color="#1a6b2f"', 'align' => '', 'fill' => '', 'border' => 'light', 'numfmt' => '#,##0.00'],
        'center'      => ['font' => 'Size="9"', 'align' => 'Horizontal="Center"', 'fill' => '', 'border' => 'light', 'numfmt' => ''],
    ];
    foreach ($styles as $id => $s) {
        $out .= '<Style ss:ID="' . $id . '">' . "\n";
        if ($s['font'])   $out .= '  <Font ss:' . $s['font'] . '/>' . "\n";
        if ($s['align'])  $out .= '  <Alignment ss:' . $s['align'] . '/>' . "\n";
        if ($s['fill'])   $out .= '  <Interior ss:' . $s['fill'] . '/>' . "\n";
        if ($s['numfmt']) $out .= '  <NumberFormat ss:Format="' . $s['numfmt'] . '"/>' . "\n";
        if ($s['border'] === 'light') {
            $out .= '  <Borders>' . "\n";
            $out .= '    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>' . "\n";
            $out .= '    <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#DDDDDD"/>' . "\n";
            $out .= '  </Borders>' . "\n";
        }
        $out .= '</Style>' . "\n";
    }
    $out .= '</Styles>' . "\n";

    // Worksheet
    $sheetName = 'Payroll ' . $period;
    $out .= '<Worksheet ss:Name="' . htmlspecialchars($sheetName) . '">' . "\n";
    $out .= '<Table>' . "\n";

    // Column widths
    $widths = [55, 140, 110, 110, 80, 70, 85, 70, 80, 75, 70, 70, 90, 90, 70, 80, 75, 65];
    foreach ($widths as $w) {
        $out .= '<Column ss:Width="' . $w . '"/>' . "\n";
    }

    // Row 1: Title
    $out .= '<Row ss:Height="22"><Cell ss:MergeAcross="17" ss:StyleID="title">';
    $out .= '<Data ss:Type="String">' . htmlspecialchars($companyName) . ' — Payroll Summary</Data></Cell></Row>' . "\n";

    // Row 2: Subtitle
    $out .= '<Row ss:Height="16"><Cell ss:MergeAcross="17" ss:StyleID="subtitle">';
    $out .= '<Data ss:Type="String">Period: ' . htmlspecialchars($periodLabel) . ' | Generated: ' . $generatedAt . ' | By: ' . htmlspecialchars($processedBy) . '</Data></Cell></Row>' . "\n";

    // Row 3: Blank
    $out .= '<Row ss:Height="6"/>' . "\n";

    // Row 4: Headers
    $headers = ['Emp No.','Employee Name','Department','Position','Basic Salary','Allowance','Gross Pay','SSS (EE)','PhilHealth (EE)','Pag-IBIG (EE)','W/Tax','Other Ded.','Total Deductions','Net Pay','SSS (ER)','PhilHealth (ER)','Pag-IBIG (ER)','Status'];
    $out .= '<Row ss:Height="30">';
    foreach ($headers as $h) {
        $out .= '<Cell ss:StyleID="header"><Data ss:Type="String">' . htmlspecialchars($h) . '</Data></Cell>';
    }
    $out .= '</Row>' . "\n";

    // Data rows
    $numKeys = ['basic_salary','allowance','gross_pay','sss_ee','philhealth_ee','pagibig_ee','withholding_tax','other_deductions','total_deductions','net_pay','sss_er','philhealth_er','pagibig_er'];
    foreach ($records as $idx => $r) {
        $alt = ($idx % 2 === 1);
        $sd  = $alt ? 'dataAlt' : 'data';
        $sc  = $alt ? 'currencyAlt' : 'currency';
        $out .= '<Row ss:Height="15">';
        $out .= '<Cell ss:StyleID="' . $sd . '"><Data ss:Type="String">' . htmlspecialchars($r['employee_no'] ?? '') . '</Data></Cell>';
        $out .= '<Cell ss:StyleID="' . $sd . '"><Data ss:Type="String">' . htmlspecialchars($r['employee_name'] ?? '') . '</Data></Cell>';
        $out .= '<Cell ss:StyleID="' . $sd . '"><Data ss:Type="String">' . htmlspecialchars($r['department'] ?? '') . '</Data></Cell>';
        $out .= '<Cell ss:StyleID="' . $sd . '"><Data ss:Type="String">' . htmlspecialchars($r['position'] ?? '') . '</Data></Cell>';
        foreach ($numKeys as $k) {
            $style = ($k === 'net_pay') ? 'netPay' : $sc;
            $out .= '<Cell ss:StyleID="' . $style . '"><Data ss:Type="Number">' . (float)($r[$k] ?? 0) . '</Data></Cell>';
        }
        $out .= '<Cell ss:StyleID="center"><Data ss:Type="String">' . ucfirst($r['status'] ?? '') . '</Data></Cell>';
        $out .= '</Row>' . "\n";
    }

    // Totals row
    $out .= '<Row ss:Height="18">';
    $out .= '<Cell ss:StyleID="totalLabel" ss:MergeAcross="3"><Data ss:Type="String">TOTALS (' . count($records) . ' employees)</Data></Cell>';
    $totalKeys = ['basic_salary','allowance','gross_pay','sss_ee','philhealth_ee','pagibig_ee','withholding_tax','other_deductions','total_deductions','net_pay','sss_er','philhealth_er','pagibig_er'];
    foreach ($totalKeys as $k) {
        $out .= '<Cell ss:StyleID="totalNum"><Data ss:Type="Number">' . $totals[$k] . '</Data></Cell>';
    }
    $out .= '<Cell ss:StyleID="totalLabel"><Data ss:Type="String"></Data></Cell>';
    $out .= '</Row>' . "\n";

    $out .= '</Table>' . "\n";
    $out .= '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">';
    $out .= '<FreezePanes/><FrozenNoSplit/><SplitHorizontal>4</SplitHorizontal>';
    $out .= '<TopRowBottomPane>4</TopRowBottomPane><ActivePane>2</ActivePane>';
    $out .= '</WorksheetOptions>' . "\n";
    $out .= '</Worksheet>' . "\n";
    $out .= '</Workbook>';

    echo $out;
    exit;
}

// ============================================================
//  PDF — printable HTML
// ============================================================
$erTotal = $totals['sss_er'] + $totals['philhealth_er'] + $totals['pagibig_er'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payroll <?= htmlspecialchars($period) ?> &mdash; <?= htmlspecialchars($companyName) ?></title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:Arial,sans-serif; font-size:8.5pt; color:#111; background:#fff; }
    .page { padding:14mm 12mm 12mm; }
    .no-print { margin-bottom:10px; text-align:right; }
    .btn-print { background:#1e3a5f; color:#fff; border:none; padding:8px 20px; border-radius:4px; cursor:pointer; font-size:10pt; }
    .btn-close  { background:#eee; color:#333; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-size:10pt; margin-left:6px; }
    .rpt-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px; border-bottom:2.5px solid #1e3a5f; padding-bottom:8px; }
    .rpt-title  { font-size:16pt; font-weight:700; color:#1e3a5f; line-height:1.1; }
    .rpt-sub    { font-size:8.5pt; color:#555; margin-top:3px; }
    .rpt-meta   { text-align:right; font-size:7.5pt; color:#666; line-height:1.6; }
    .summary-row { display:flex; gap:8px; margin-bottom:10px; }
    .summary-box { flex:1; border:1px solid #dde; border-radius:4px; padding:6px 8px; background:#f7f9ff; }
    .s-label { font-size:7pt; color:#888; text-transform:uppercase; letter-spacing:.4px; }
    .s-value { font-size:11pt; font-weight:700; color:#1e3a5f; margin-top:1px; }
    table { width:100%; border-collapse:collapse; }
    thead tr th { background:#1e3a5f; color:#fff; font-size:7pt; font-weight:600; padding:4px 5px; text-align:center; border:1px solid #16305a; white-space:nowrap; }
    thead tr th.left { text-align:left; }
    tbody tr td { font-size:7.5pt; padding:3px 5px; border:1px solid #dde; vertical-align:middle; }
    tbody tr td.num { text-align:right; }
    tbody tr td.ctr { text-align:center; }
    tbody tr:nth-child(even) td { background:#f5f8ff; }
    .totals-row td { font-weight:700; background:#2e5090 !important; color:#fff !important; border-color:#253f7a !important; }
    .net { font-weight:700; color:#1a6b2f; }
    .pill { display:inline-block; padding:1px 6px; border-radius:8px; font-size:6.5pt; font-weight:600; text-transform:uppercase; }
    .pill-released { background:#dcfce7; color:#15803d; }
    .pill-pending  { background:#fef9c3; color:#b45309; }
    .er-section { margin-top:12px; }
    .er-section h4 { font-size:8pt; color:#1e3a5f; margin-bottom:4px; font-weight:700; border-bottom:1px solid #dde; padding-bottom:3px; }
    .er-table { width:38% !important; }
    .sig-row { display:flex; gap:30px; margin-top:24px; }
    .sig-box { flex:1; border-top:1px solid #333; padding-top:4px; font-size:7.5pt; text-align:center; color:#444; }
    .rpt-footer { margin-top:14px; border-top:1px solid #dde; padding-top:8px; font-size:7pt; color:#888; display:flex; justify-content:space-between; }
    @page { size:A4 landscape; margin:10mm; }
    @media print {
      .no-print { display:none !important; }
      .page { padding:0; }
    }
  </style>
</head>
<body>
<div class="page">

  <div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128438; Print / Save as PDF</button>
    <button class="btn-close"  onclick="window.close()">&#10005; Close</button>
  </div>

  <div class="rpt-header">
    <div>
      <div class="rpt-title"><?= htmlspecialchars($companyName) ?></div>
      <div class="rpt-sub">Payroll Summary Report &mdash; <?= htmlspecialchars($periodLabel) ?></div>
    </div>
    <div class="rpt-meta">
      Generated: <?= $generatedAt ?><br>
      Prepared by: <?= htmlspecialchars($processedBy) ?><br>
      Total Employees: <?= count($records) ?>
    </div>
  </div>

  <div class="summary-row">
    <div class="summary-box">
      <div class="s-label">Total Gross Pay</div>
      <div class="s-value">&#8369;<?= number_format($totals['gross_pay'], 2) ?></div>
    </div>
    <div class="summary-box">
      <div class="s-label">Total Deductions</div>
      <div class="s-value">&#8369;<?= number_format($totals['total_deductions'], 2) ?></div>
    </div>
    <div class="summary-box">
      <div class="s-label">Total Net Pay</div>
      <div class="s-value" style="color:#1a6b2f;">&#8369;<?= number_format($totals['net_pay'], 2) ?></div>
    </div>
    <div class="summary-box">
      <div class="s-label">Total ER Contributions</div>
      <div class="s-value" style="font-size:9.5pt;">&#8369;<?= number_format($erTotal, 2) ?></div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th class="left" rowspan="2">#</th>
        <th class="left" rowspan="2">Emp No.</th>
        <th class="left" rowspan="2">Employee Name</th>
        <th class="left" rowspan="2">Department</th>
        <th colspan="3">EARNINGS</th>
        <th colspan="5">EMPLOYEE DEDUCTIONS</th>
        <th rowspan="2">Total Ded.</th>
        <th rowspan="2">Net Pay</th>
        <th rowspan="2">Status</th>
      </tr>
      <tr>
        <th>Basic</th>
        <th>Allow.</th>
        <th>Gross</th>
        <th>SSS</th>
        <th>PhilHealth</th>
        <th>Pag-IBIG</th>
        <th>W/Tax</th>
        <th>Others</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($records as $i => $r): ?>
      <tr>
        <td class="ctr"><?= $i + 1 ?></td>
        <td><?= htmlspecialchars($r['employee_no'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['employee_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['department'] ?? '') ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['basic_salary'], 2) ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['allowance'], 2) ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['gross_pay'], 2) ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['sss_ee'], 2) ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['philhealth_ee'], 2) ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['pagibig_ee'], 2) ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['withholding_tax'], 2) ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['other_deductions'], 2) ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['total_deductions'], 2) ?></td>
        <td class="num net">&#8369;<?= number_format((float)$r['net_pay'], 2) ?></td>
        <td class="ctr">
          <span class="pill pill-<?= $r['status'] ?>"><?= ucfirst($r['status'] ?? '') ?></span>
        </td>
      </tr>
      <?php endforeach; ?>
      <tr class="totals-row">
        <td colspan="4" style="text-align:right;">TOTALS</td>
        <td class="num">&#8369;<?= number_format($totals['basic_salary'], 2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['allowance'], 2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['gross_pay'], 2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['sss_ee'], 2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['philhealth_ee'], 2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['pagibig_ee'], 2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['withholding_tax'], 2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['other_deductions'], 2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['total_deductions'], 2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['net_pay'], 2) ?></td>
        <td></td>
      </tr>
    </tbody>
  </table>

  <div class="er-section">
    <h4>Employer Contributions (ER Share)</h4>
    <table class="er-table">
      <thead>
        <tr><th class="left">Contribution</th><th>Amount</th></tr>
      </thead>
      <tbody>
        <tr><td>SSS (Employer Share)</td><td class="num">&#8369;<?= number_format($totals['sss_er'], 2) ?></td></tr>
        <tr><td>PhilHealth (Employer Share)</td><td class="num">&#8369;<?= number_format($totals['philhealth_er'], 2) ?></td></tr>
        <tr><td>Pag-IBIG (Employer Share)</td><td class="num">&#8369;<?= number_format($totals['pagibig_er'], 2) ?></td></tr>
        <tr class="totals-row"><td>Total ER Contributions</td><td class="num">&#8369;<?= number_format($erTotal, 2) ?></td></tr>
      </tbody>
    </table>
  </div>

  <div class="sig-row">
    <div class="sig-box">Prepared by<br><br><strong><?= htmlspecialchars($processedBy) ?></strong></div>
    <div class="sig-box">Reviewed by<br><br>&nbsp;</div>
    <div class="sig-box">Approved by<br><br>&nbsp;</div>
  </div>

  <div class="rpt-footer">
    <span><?= htmlspecialchars($companyName) ?> &mdash; Payroll Summary &mdash; <?= htmlspecialchars($periodLabel) ?></span>
    <span>System-generated report. Printed on <?= $generatedAt ?></span>
  </div>

</div>
</body>
</html>