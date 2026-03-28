<?php
// app/views/admin/payroll_export.php
// Payroll export — real .xlsx (PhpSpreadsheet) or printable HTML for PDF

// Load config FIRST — sets session cookie flags before session_start()
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../core/Model.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ── Auth ─────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'management'])) {
    header('Location: ' . BASE_URL . '/index.php?error=access_denied');
    exit;
}

$period = trim($_GET['period'] ?? '');
$format = trim($_GET['format'] ?? 'excel');

// Accept both YYYY-MM-1 / YYYY-MM-2 (semi-monthly) and legacy YYYY-MM
if (preg_match('/^(\d{4}-\d{2})-[12]$/', $period, $m)) {
    // Semi-monthly format — use the base (YYYY-MM) for display, keep full period for DB query
    $periodBase  = $m[1];
    $periodLabel = date('F Y', strtotime($periodBase . '-01'));
} elseif (preg_match('/^\d{4}-\d{2}$/', $period)) {
    $periodBase  = $period;
    $periodLabel = date('F Y', strtotime($period . '-01'));
} else {
    die('Invalid period format. Expected YYYY-MM or YYYY-MM-1 / YYYY-MM-2.');
}

$records = Model::getPayrollByPeriod($period);
if (empty($records)) {
    die('No payroll records found for period: ' . htmlspecialchars($period));
}

// ── Totals ────────────────────────────────────────────────────────
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
$generatedAt = date('F d, Y h:i A');
$processedBy = $_SESSION['name'] ?? 'System';

// ════════════════════════════════════════════════════════════════
//  EXCEL EXPORT — Real .xlsx via PhpSpreadsheet
// ════════════════════════════════════════════════════════════════
if ($format === 'excel') {

    $autoload = __DIR__ . '/../../../vendor/autoload.php';
    if (!file_exists($autoload)) {
        die(
            '<div class="alert alert-danger m-4 p-4">' .
            '<h5 class="alert-heading">&#9888; PhpSpreadsheet not installed</h5>' .
            '<p>Open a terminal in your project root and run:</p>' .
            '<pre class="bg-light p-3 rounded mt-2">composer require phpoffice/phpspreadsheet</pre>' .
            '<p class="text-muted mb-0">Then reload this page.</p></div>'
        );
    }

    require_once $autoload;

    // ── Colours ───────────────────────────────────────────────────
    $DARK_BLUE  = '1E3A5F';
    $MID_BLUE   = '2E5090';
    $WHITE      = 'FFFFFF';
    $ALT_ROW    = 'F0F5FF';
    $GREEN_TEXT = '1A6B2F';
    $BORDER_CLR = 'CCDDEE';
    $phpNum     = '#,##0.00';

    $headerStyle = [
        'font'      => ['bold' => true, 'color' => ['rgb' => $WHITE], 'size' => 9],
        'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $DARK_BLUE]],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        'wrapText'   => true],
    ];
    $totalStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => $WHITE], 'size' => 9],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $MID_BLUE]],
    ];
    $thinBorder = [
        'borders' => ['allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color'       => ['rgb' => $BORDER_CLR],
        ]],
    ];

    // ── Build workbook ────────────────────────────────────────────
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Payroll ' . $period);

    // Row 1 — Title
    $sheet->mergeCells('A1:S1');
    $sheet->setCellValue('A1', $companyName . ' — Payroll Summary');
    $sheet->getStyle('A1')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => $DARK_BLUE]],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(22);

    // Row 2 — Subtitle
    $sheet->mergeCells('A2:S2');
    $sheet->setCellValue('A2', "Period: {$periodLabel}  |  Generated: {$generatedAt}  |  Prepared by: {$processedBy}");
    $sheet->getStyle('A2')->applyFromArray([
        'font'      => ['size' => 9, 'color' => ['rgb' => '555555']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getRowDimension(2)->setRowHeight(14);
    $sheet->getRowDimension(3)->setRowHeight(6);

    // Rows 4-5 — Two-row header (group + sub-header)
    // Group merges
    foreach (['A4:A5','B4:B5','C4:C5','D4:D5','M4:M5','N4:N5','O4:O5','S4:S5'] as $m) {
        $sheet->mergeCells($m);
    }
    $sheet->mergeCells('E4:G4');
    $sheet->mergeCells('H4:L4');
    $sheet->mergeCells('P4:R4');

    $row4 = ['A4'=>'#','B4'=>'Emp No.','C4'=>'Employee Name','D4'=>'Department',
             'E4'=>'EARNINGS','H4'=>'EMPLOYEE DEDUCTIONS',
             'M4'=>'Total Ded.','N4'=>'Net Pay','O4'=>'Status',
             'P4'=>'EMPLOYER CONTRIBUTIONS','S4'=>'Days Worked'];
    foreach ($row4 as $cell => $val) { $sheet->setCellValue($cell, $val); }

    $row5 = ['E5'=>'Basic Salary','F5'=>'Allowance','G5'=>'Gross Pay',
             'H5'=>'SSS','I5'=>'PhilHealth','J5'=>'Pag-IBIG','K5'=>'W/Tax','L5'=>'Others',
             'P5'=>'SSS (ER)','Q5'=>'PhilHealth (ER)','R5'=>'Pag-IBIG (ER)'];
    foreach ($row5 as $cell => $val) { $sheet->setCellValue($cell, $val); }

    $sheet->getStyle('A4:S5')->applyFromArray($headerStyle);
    $sheet->getRowDimension(4)->setRowHeight(18);
    $sheet->getRowDimension(5)->setRowHeight(28);

    // Data rows starting row 6
    $row = 6;
    foreach ($records as $idx => $r) {
        $isAlt = ($idx % 2 === 1);

        $sheet->setCellValue("A{$row}", $idx + 1);
        $sheet->setCellValue("B{$row}", $r['employee_no']   ?? '');
        $sheet->setCellValue("C{$row}", $r['employee_name'] ?? '');
        $sheet->setCellValue("D{$row}", $r['department']    ?? '');
        $sheet->setCellValue("E{$row}", (float)($r['basic_salary']     ?? 0));
        $sheet->setCellValue("F{$row}", (float)($r['allowance']        ?? 0));
        $sheet->setCellValue("G{$row}", (float)($r['gross_pay']        ?? 0));
        $sheet->setCellValue("H{$row}", (float)($r['sss_ee']           ?? 0));
        $sheet->setCellValue("I{$row}", (float)($r['philhealth_ee']    ?? 0));
        $sheet->setCellValue("J{$row}", (float)($r['pagibig_ee']       ?? 0));
        $sheet->setCellValue("K{$row}", (float)($r['withholding_tax']  ?? 0));
        $sheet->setCellValue("L{$row}", (float)($r['other_deductions'] ?? 0));
        $sheet->setCellValue("M{$row}", (float)($r['total_deductions'] ?? 0));
        $sheet->setCellValue("N{$row}", (float)($r['net_pay']          ?? 0));
        $sheet->setCellValue("O{$row}", ucfirst($r['status']           ?? ''));
        $sheet->setCellValue("P{$row}", (float)($r['sss_er']           ?? 0));
        $sheet->setCellValue("Q{$row}", (float)($r['philhealth_er']    ?? 0));
        $sheet->setCellValue("R{$row}", (float)($r['pagibig_er']       ?? 0));
        $sheet->setCellValue("S{$row}", $r['days_worked'] !== null ? (float)$r['days_worked'] : 'N/A');

        // Base row style
        $sheet->getStyle("A{$row}:S{$row}")->applyFromArray(array_merge($thinBorder, [
            'font'      => ['size' => 9],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]));

        // Alternating row bg
        if ($isAlt) {
            $sheet->getStyle("A{$row}:S{$row}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($ALT_ROW);
        }

        // Currency format
        foreach (['E','F','G','H','I','J','K','L','M','P','Q','R'] as $col) {
            $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode($phpNum);
        }
        // Net pay — bold green
        $sheet->getStyle("N{$row}")->applyFromArray([
            'font'         => ['bold' => true, 'color' => ['rgb' => $GREEN_TEXT]],
            'numberFormat' => ['formatCode' => $phpNum],
        ]);
        // Center cols
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("O{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("S{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->getRowDimension($row)->setRowHeight(15);
        $row++;
    }

    // Totals row
    $sheet->setCellValue("A{$row}", 'TOTALS (' . count($records) . ' employees)');
    $sheet->mergeCells("A{$row}:D{$row}");
    $sheet->getStyle("A{$row}:S{$row}")->applyFromArray($totalStyle);
    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

    $totalMap = [
        'E'=>'basic_salary','F'=>'allowance','G'=>'gross_pay',
        'H'=>'sss_ee','I'=>'philhealth_ee','J'=>'pagibig_ee',
        'K'=>'withholding_tax','L'=>'other_deductions','M'=>'total_deductions',
        'N'=>'net_pay','P'=>'sss_er','Q'=>'philhealth_er','R'=>'pagibig_er',
    ];
    foreach ($totalMap as $col => $key) {
        $sheet->setCellValue("{$col}{$row}", $totals[$key]);
        $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode($phpNum);
    }
    $sheet->getRowDimension($row)->setRowHeight(18);
    $row += 2;

    // ER summary block
    $sheet->setCellValue("A{$row}", 'EMPLOYER CONTRIBUTIONS SUMMARY');
    $sheet->mergeCells("A{$row}:D{$row}");
    $sheet->getStyle("A{$row}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $DARK_BLUE]]]);
    $row++;

    $erRows = [
        'SSS Employer Share'       => $totals['sss_er'],
        'PhilHealth Employer Share' => $totals['philhealth_er'],
        'Pag-IBIG Employer Share'   => $totals['pagibig_er'],
        'TOTAL ER CONTRIBUTIONS'    => $totals['sss_er'] + $totals['philhealth_er'] + $totals['pagibig_er'],
    ];
    foreach ($erRows as $label => $amount) {
        $isTotal = str_starts_with($label, 'TOTAL');
        $sheet->setCellValue("A{$row}", $label);
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("D{$row}", $amount);
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode($phpNum);
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($thinBorder);
        if ($isTotal) {
            $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($totalStyle);
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode($phpNum);
        }
        $row++;
    }

    // Column widths
    foreach ([
        'A'=>5,'B'=>11,'C'=>22,'D'=>18,
        'E'=>14,'F'=>12,'G'=>14,
        'H'=>10,'I'=>12,'J'=>10,'K'=>10,'L'=>10,
        'M'=>14,'N'=>14,'O'=>10,
        'P'=>11,'Q'=>15,'R'=>13,'S'=>12,
    ] as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }

    // Freeze panes & page setup
    $sheet->freezePane('A6');
    $sheet->getPageSetup()
        ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
        ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
        ->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
    $sheet->getHeaderFooter()
        ->setOddHeader("&C&B{$companyName} — Payroll — {$periodLabel}")
        ->setOddFooter("&LGenerated: {$generatedAt}&R&P of &N");

    // Output — clean buffer first to prevent leaked output corrupting binary file
    $filename = 'Payroll_' . $period . '_' . date('Ymd_His') . '.xlsx';

    while (ob_get_level()) { ob_end_clean(); }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');

    ob_start();
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    $xlsxContent = ob_get_clean();
    header('Content-Length: ' . strlen($xlsxContent));
    echo $xlsxContent;
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
    exit;
}

// ════════════════════════════════════════════════════════════════
//  PDF — printable HTML (unchanged)
// ════════════════════════════════════════════════════════════════
$erTotal = $totals['sss_er'] + $totals['philhealth_er'] + $totals['pagibig_er'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payroll <?= htmlspecialchars($period) ?> &mdash; <?= htmlspecialchars($companyName) ?></title>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:Arial,sans-serif;font-size:8.5pt;color:#111;background:#fff}
    .page{padding:14mm 12mm 12mm}
    .no-print{margin-bottom:10px;text-align:right}
    .btn-print{background:#1e3a5f;color:#fff;border:none;padding:8px 20px;border-radius:4px;cursor:pointer;font-size:10pt}
    .btn-close{background:#eee;color:#333;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;font-size:10pt;margin-left:6px}
    .rpt-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;border-bottom:2.5px solid #1e3a5f;padding-bottom:8px}
    .rpt-title{font-size:16pt;font-weight:700;color:#1e3a5f;line-height:1.1}
    .rpt-sub{font-size:8.5pt;color:#555;margin-top:3px}
    .rpt-meta{text-align:right;font-size:7.5pt;color:#666;line-height:1.6}
    .summary-row{display:flex;gap:8px;margin-bottom:10px}
    .summary-box{flex:1;border:1px solid #dde;border-radius:4px;padding:6px 8px;background:#f7f9ff}
    .s-label{font-size:7pt;color:#888;text-transform:uppercase;letter-spacing:.4px}
    .s-value{font-size:11pt;font-weight:700;color:#1e3a5f;margin-top:1px}
    table{width:100%;border-collapse:collapse}
    thead tr th{background:#1e3a5f;color:#fff;font-size:7pt;font-weight:600;padding:4px 5px;text-align:center;border:1px solid #16305a;white-space:nowrap}
    thead tr th.left{text-align:left}
    tbody tr td{font-size:7.5pt;padding:3px 5px;border:1px solid #dde;vertical-align:middle}
    tbody tr td.num{text-align:right}
    tbody tr td.ctr{text-align:center}
    tbody tr:nth-child(even) td{background:#f5f8ff}
    .totals-row td{font-weight:700;background:#2e5090!important;color:#fff!important;border-color:#253f7a!important}
    .net{font-weight:700;color:#1a6b2f}
    .pill{display:inline-block;padding:1px 6px;border-radius:8px;font-size:6.5pt;font-weight:600;text-transform:uppercase}
    .pill-released{background:#dcfce7;color:#15803d}
    .pill-pending{background:#fef9c3;color:#b45309}
    .er-section{margin-top:12px}
    .er-section h4{font-size:8pt;color:#1e3a5f;margin-bottom:4px;font-weight:700;border-bottom:1px solid #dde;padding-bottom:3px}
    .er-table{width:38%!important}
    .sig-row{display:flex;gap:30px;margin-top:24px}
    .sig-box{flex:1;border-top:1px solid #333;padding-top:4px;font-size:7.5pt;text-align:center;color:#444}
    .rpt-footer{margin-top:14px;border-top:1px solid #dde;padding-top:8px;font-size:7pt;color:#888;display:flex;justify-content:space-between}
    @page{size:A4 landscape;margin:10mm}
    @media print{.no-print{display:none!important}.page{padding:0}}
  </style>
</head>
<body>
<div class="page">
  <div class="no-print">
    <button class="btn-print" onclick="window.print()">&#128438; Print / Save as PDF</button>
    <button class="btn-close" onclick="window.close()">&#10005; Close</button>
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
    <div class="summary-box"><div class="s-label">Total Gross Pay</div><div class="s-value">&#8369;<?= number_format($totals['gross_pay'],2) ?></div></div>
    <div class="summary-box"><div class="s-label">Total Deductions</div><div class="s-value">&#8369;<?= number_format($totals['total_deductions'],2) ?></div></div>
    <div class="summary-box"><div class="s-label">Total Net Pay</div><div class="s-value payroll-export-total-value">&#8369;<?= number_format($totals['net_pay'],2) ?></div></div>
    <div class="summary-box"><div class="s-label">Total ER Contributions</div><div class="s-value payroll-export-er-value">&#8369;<?= number_format($erTotal,2) ?></div></div>
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
        <th>Basic</th><th>Allow.</th><th>Gross</th>
        <th>SSS</th><th>PhilHealth</th><th>Pag-IBIG</th><th>W/Tax</th><th>Others</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($records as $i => $r): ?>
      <tr>
        <td class="ctr"><?= $i+1 ?></td>
        <td><?= htmlspecialchars($r['employee_no']??'') ?></td>
        <td><?= htmlspecialchars($r['employee_name']??'') ?></td>
        <td><?= htmlspecialchars($r['department']??'') ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['basic_salary'],2) ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['allowance'],2) ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['gross_pay'],2) ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['sss_ee'],2) ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['philhealth_ee'],2) ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['pagibig_ee'],2) ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['withholding_tax'],2) ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['other_deductions'],2) ?></td>
        <td class="num">&#8369;<?= number_format((float)$r['total_deductions'],2) ?></td>
        <td class="num net">&#8369;<?= number_format((float)$r['net_pay'],2) ?></td>
        <td class="ctr"><span class="pill pill-<?= $r['status'] ?>"><?= ucfirst($r['status']??'') ?></span></td>
      </tr>
      <?php endforeach; ?>
      <tr class="totals-row">
        <td colspan="4" class="payroll-export-footer-cell">TOTALS</td>
        <td class="num">&#8369;<?= number_format($totals['basic_salary'],2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['allowance'],2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['gross_pay'],2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['sss_ee'],2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['philhealth_ee'],2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['pagibig_ee'],2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['withholding_tax'],2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['other_deductions'],2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['total_deductions'],2) ?></td>
        <td class="num">&#8369;<?= number_format($totals['net_pay'],2) ?></td>
        <td></td>
      </tr>
    </tbody>
  </table>
  <div class="er-section">
    <h4>Employer Contributions (ER Share)</h4>
    <table class="er-table">
      <thead><tr><th class="left">Contribution</th><th>Amount</th></tr></thead>
      <tbody>
        <tr><td>SSS (Employer Share)</td><td class="num">&#8369;<?= number_format($totals['sss_er'],2) ?></td></tr>
        <tr><td>PhilHealth (Employer Share)</td><td class="num">&#8369;<?= number_format($totals['philhealth_er'],2) ?></td></tr>
        <tr><td>Pag-IBIG (Employer Share)</td><td class="num">&#8369;<?= number_format($totals['pagibig_er'],2) ?></td></tr>
        <tr class="totals-row"><td>Total ER Contributions</td><td class="num">&#8369;<?= number_format($erTotal,2) ?></td></tr>
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