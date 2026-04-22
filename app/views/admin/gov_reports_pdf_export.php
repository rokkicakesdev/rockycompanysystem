<?php
// app/views/admin/gov_reports_pdf_export.php
// ─────────────────────────────────────────────────────────────────────────────
//  Renders government remittance report PDFs via Dompdf.
//
//  Usage:  gov_reports_pdf_export.php?report=sss&month=2026-03
//          gov_reports_pdf_export.php?report=philhealth&month=2026-03
//          gov_reports_pdf_export.php?report=pagibig&month=2026-03
//          gov_reports_pdf_export.php?report=bir1601c&month=2026-03
//
//  Reports implemented:
//    sss       — SSS R-3  Monthly Collection List
//    philhealth — PhilHealth RF-1  Monthly Remittance Form
//    pagibig   — Pag-IBIG MCRF  Monthly Contribution Remittance Form
//    bir1601c  — BIR Form 1601-C  Monthly Remittance Return of Creditable
//                Income Taxes Withheld (Expanded) — Compensation
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/Model.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) {
    header('Location: ' . BASE_URL . '/index.php?error=access_denied'); exit;
}

$autoload = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($autoload)) { die('Dompdf not installed. Run: composer require dompdf/dompdf'); }
require_once $autoload;

// ── Parameters ───────────────────────────────────────────────────────────────
$validReports = ['sss', 'philhealth', 'pagibig', 'bir1601c'];
$report       = in_array($_GET['report'] ?? '', $validReports) ? $_GET['report'] : 'sss';
$currentYM    = date('Y-m');
$selectedYM   = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : $currentYM;
$selectedYear = (int)substr($selectedYM, 0, 4);
$selectedMonth = (int)substr($selectedYM, 5, 2);
$monthLabel   = date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
$monthLabelShort = date('M-Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));

// ── Company header ────────────────────────────────────────────────────────────
$co = Model::getReportHeader();
$coName    = $co['company_name']    ?: (defined('COMPANY_NAME')    ? COMPANY_NAME    : '');
$coAddress = $co['company_address'] ?: (defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : '');

// ── Payroll data — released records for the month ────────────────────────────
$db   = Database::getInstance();
$stmt = $db->prepare("
    SELECT
        pr.employee_id,
        e.employee_no,
        e.name                              AS employee_name,
        e.sss_no,
        e.philhealth_no,
        e.pagibig_no,
        e.tin_no,
        d.name                              AS department,
        SUM(pr.basic_salary)                AS total_basic,
        SUM(pr.gross_pay)                   AS total_gross,
        MAX(pr.sss_msc)                     AS sss_msc,
        SUM(pr.sss_ee)                      AS sss_ee,
        SUM(pr.sss_er)                      AS sss_er,
        MAX(pr.philhealth_mbs)              AS ph_mbs,
        SUM(pr.philhealth_ee)               AS ph_ee,
        SUM(pr.philhealth_er)               AS ph_er,
        MAX(pr.pagibig_mfs)                 AS pi_mfs,
        SUM(pr.pagibig_ee)                  AS pi_ee,
        SUM(pr.pagibig_er)                  AS pi_er,
        SUM(pr.withholding_tax)             AS wtax,
        SUM(pr.other_deductions)            AS other_ded,
        COUNT(pr.id)                        AS cutoffs_released
    FROM payroll_records pr
    JOIN employees e  ON e.id  = pr.employee_id
    JOIN departments d ON d.id = e.department_id
    WHERE pr.status = 'released'
      AND pr.period LIKE ?
    GROUP BY pr.employee_id, e.employee_no, e.name, e.sss_no,
             e.philhealth_no, e.pagibig_no, e.tin_no, d.name
    ORDER BY e.name
");
$stmt->execute([$selectedYM . '-%']);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Totals ────────────────────────────────────────────────────────────────────
$totals = ['sss_ee'=>0,'sss_er'=>0,'ph_ee'=>0,'ph_er'=>0,'pi_ee'=>0,'pi_er'=>0,'wtax'=>0,'total_gross'=>0];
foreach ($records as $r) {
    $totals['sss_ee']     += (float)$r['sss_ee'];
    $totals['sss_er']     += (float)$r['sss_er'];
    $totals['ph_ee']      += (float)$r['ph_ee'];
    $totals['ph_er']      += (float)$r['ph_er'];
    $totals['pi_ee']      += (float)$r['pi_ee'];
    $totals['pi_er']      += (float)$r['pi_er'];
    $totals['wtax']       += (float)$r['wtax'];
    $totals['total_gross']+= (float)$r['total_gross'];
}

// ── Helpers ───────────────────────────────────────────────────────────────────
$ph  = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$fmt = fn(float $v): string  => number_format($v, 2);
$fmtOr = fn(?float $v): string => ($v && $v > 0) ? number_format($v, 2) : '—';

// ── Shared CSS ────────────────────────────────────────────────────────────────
$css = '
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 8pt; color: #000; background:#fff; }
.page { padding: 10mm 10mm 8mm 10mm; }
h2 { font-size: 11pt; font-weight: 800; margin-bottom: 1mm; }
h3 { font-size: 9pt; font-weight: 700; margin-bottom: 3mm; color: #1a2744; }
.header-block { margin-bottom: 5mm; }
.header-block table { width:100%; border-collapse:collapse; }
.header-block td { vertical-align:top; padding: 1mm 2mm; font-size:8pt; }
.label { font-weight: 700; color: #374151; width:38%; }
.field { border-bottom: 1px solid #999; padding-bottom: 1px; }
.section-title {
    background: #1a2744; color: #fff;
    padding: 3mm 4mm; font-size: 9pt; font-weight: 700;
    margin-bottom: 2mm;
}
table.data { width: 100%; border-collapse: collapse; margin-bottom: 4mm; font-size: 7.5pt; }
table.data thead th {
    background: #e8edf5; color: #1a2744;
    border: 0.5pt solid #999; padding: 2mm 1.5mm;
    text-align: left; font-weight: 700;
}
table.data thead th.r { text-align: right; }
table.data tbody td {
    border: 0.5pt solid #ccc; padding: 1.5mm 1.5mm;
    vertical-align: middle;
}
table.data tbody td.r { text-align: right; font-variant-numeric: tabular-nums; }
table.data tfoot td {
    border: 0.5pt solid #999; padding: 2mm 1.5mm;
    background: #f1f5f9; font-weight: 700;
}
table.data tfoot td.r { text-align: right; color: #1d4ed8; }
.totals-box { border: 1pt solid #1a2744; padding: 3mm 5mm; margin-bottom: 5mm; }
.totals-box table { width: 100%; }
.totals-box td { padding: 1mm 2mm; font-size: 8.5pt; }
.totals-box td.r { text-align:right; font-weight:700; }
.sig-row { margin-top: 6mm; }
.sig-row table { width:100%; border-collapse:collapse; }
.sig-row td { width: 50%; padding: 2mm 4mm; vertical-align:bottom; font-size: 7.5pt; }
.sig-line { border-top: 1pt solid #555; margin-top: 10mm; padding-top: 2mm; color: #555; }
.note { font-size: 6.5pt; color: #555; margin-top: 3mm; }
.badge { display:inline-block; background:#1a2744; color:#fff; padding:1mm 3mm; border-radius:2mm; font-size:7pt; font-weight:700; }
.badge-green { background:#065f46; }
.badge-red { background:#991b1b; }
.badge-purple { background:#4c1d95; }
.badge-amber { background:#92400e; }
';

// ════════════════════════════════════════════════════════════════════════════
//  SSS R-3
// ════════════════════════════════════════════════════════════════════════════
if ($report === 'sss') {
    $sssEmpId    = $co['sss_employer_id']  ?: '[ Not Set — configure in Payroll Settings ]';
    $sssBranch   = $co['sss_branch_code']  ?: '';

    $rows = '';
    foreach ($records as $i => $r) {
        $total = (float)$r['sss_ee'] + (float)$r['sss_er'];
        $rows .= '<tr>
            <td>' . ($i + 1) . '</td>
            <td>' . $ph($r['employee_no']) . '</td>
            <td><b>' . $ph($r['employee_name']) . '</b></td>
            <td>' . $ph($r['sss_no'] ?? '') . '</td>
            <td class="r">' . $fmt((float)$r['sss_msc']) . '</td>
            <td class="r">' . $fmt((float)$r['sss_ee']) . '</td>
            <td class="r">' . $fmt((float)$r['sss_er']) . '</td>
            <td class="r"><b>' . $fmt($total) . '</b></td>
        </tr>';
    }

    $grandTotal = $totals['sss_ee'] + $totals['sss_er'];

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>' . $css . '</style></head><body><div class="page">

    <div style="text-align:center;margin-bottom:4mm;">
        <div style="font-size:7pt;color:#555;">Republic of the Philippines — Social Security System</div>
        <h2>MONTHLY COLLECTION LIST</h2>
        <div style="font-size:9pt;font-weight:700;">SSS Form R-3</div>
        <div style="font-size:8pt;margin-top:1mm;">For the Month of: <b>' . $ph($monthLabel) . '</b></div>
    </div>

    <div class="header-block">
        <table>
            <tr>
                <td class="label">Employer Name:</td>
                <td class="field"><b>' . $ph($coName) . '</b></td>
                <td style="width:5mm;"></td>
                <td class="label">SSS Employer ID:</td>
                <td class="field"><b>' . $ph($sssEmpId) . '</b></td>
            </tr>
            <tr>
                <td class="label">Employer Address:</td>
                <td class="field">' . $ph($coAddress) . '</td>
                <td></td>
                <td class="label">Branch Code:</td>
                <td class="field">' . $ph($sssBranch) . '</td>
            </tr>
            <tr>
                <td class="label">Report Period:</td>
                <td class="field"><b>' . $ph($monthLabel) . '</b></td>
                <td></td>
                <td class="label">No. of Employees:</td>
                <td class="field"><b>' . count($records) . '</b></td>
            </tr>
        </table>
    </div>

    <div class="section-title">
        Employee Contribution Details &nbsp;<span style="font-size:7pt;font-weight:400;">
        (SSS Circular 2024-006 — EE: 5% | ER: 10% | MSC Ceiling: &#8369;35,000)</span>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width:5mm;">#</th>
                <th style="width:18mm;">Emp No.</th>
                <th>Employee Name</th>
                <th style="width:28mm;">SSS Number</th>
                <th class="r" style="width:20mm;">MSC (&#8369;)</th>
                <th class="r" style="width:20mm;">EE 5% (&#8369;)</th>
                <th class="r" style="width:20mm;">ER 10% (&#8369;)</th>
                <th class="r" style="width:22mm;">Total (&#8369;)</th>
            </tr>
        </thead>
        <tbody>' . $rows . '</tbody>
        <tfoot>
            <tr>
                <td colspan="4"><b>GRAND TOTAL — ' . count($records) . ' Employee(s)</b></td>
                <td class="r">—</td>
                <td class="r">&#8369;' . $fmt($totals['sss_ee']) . '</td>
                <td class="r">&#8369;' . $fmt($totals['sss_er']) . '</td>
                <td class="r">&#8369;' . $fmt($grandTotal) . '</td>
            </tr>
        </tfoot>
    </table>

    <div class="totals-box">
        <table>
            <tr>
                <td>Total Employee Contributions (EE 5%):</td>
                <td class="r">&#8369; ' . $fmt($totals['sss_ee']) . '</td>
                <td style="width:10mm;"></td>
                <td>Total Employer Contributions (ER 10%):</td>
                <td class="r">&#8369; ' . $fmt($totals['sss_er']) . '</td>
            </tr>
            <tr>
                <td colspan="4" style="font-size:9pt;font-weight:800;padding-top:2mm;">
                    TOTAL SSS REMITTANCE DUE (EE + ER):</td>
                <td class="r" style="font-size:10pt;color:#1d4ed8;">
                    &#8369; ' . $fmt($grandTotal) . '</td>
            </tr>
        </table>
    </div>

    <div class="note" style="margin-bottom:4mm;">
        &#9432; Remit via My.SSS Employer Portal or SSS-accredited collecting banks/agents
        on or before the last day of the month following the applicable month.
        Late payment is subject to 2% penalty per month (SSS Law Sec. 22-a).
    </div>

    <div class="sig-row">
        <table>
            <tr>
                <td>
                    <div>Prepared by:</div>
                    <div class="sig-line">HR / Payroll Officer — Signature over Printed Name</div>
                    <div style="margin-top:1mm;font-size:7pt;">Date: ___________________________</div>
                </td>
                <td>
                    <div>Certified correct by:</div>
                    <div class="sig-line">Authorized Signatory — Signature over Printed Name &amp; Designation</div>
                    <div style="margin-top:1mm;font-size:7pt;">Date: ___________________________</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="note" style="margin-top:4mm;">
        Generated by ' . $ph(defined('APP_NAME') ? APP_NAME : 'Payroll System') . ' on ' . date('F j, Y \a\t h:i A') . '.
        Only <b>released</b> payroll records are included.
    </div>
    </div></body></html>';

    $filename = 'SSS_R3_' . $monthLabelShort . '_' . preg_replace('/\s+/', '_', $coName) . '.pdf';
    $paper    = 'Legal';
}

// ════════════════════════════════════════════════════════════════════════════
//  PhilHealth RF-1
// ════════════════════════════════════════════════════════════════════════════
elseif ($report === 'philhealth') {
    $phEmpNo = $co['philhealth_employer_no'] ?: '[ Not Set — configure in Payroll Settings ]';

    $rows = '';
    foreach ($records as $i => $r) {
        $total = (float)$r['ph_ee'] + (float)$r['ph_er'];
        $rows .= '<tr>
            <td>' . ($i + 1) . '</td>
            <td>' . $ph($r['employee_no']) . '</td>
            <td><b>' . $ph($r['employee_name']) . '</b></td>
            <td>' . $ph($r['philhealth_no'] ?? '') . '</td>
            <td class="r">' . $fmt((float)$r['ph_mbs']) . '</td>
            <td class="r">' . $fmt((float)$r['ph_ee']) . '</td>
            <td class="r">' . $fmt((float)$r['ph_er']) . '</td>
            <td class="r"><b>' . $fmt($total) . '</b></td>
        </tr>';
    }

    $grandTotal = $totals['ph_ee'] + $totals['ph_er'];

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>' . $css . '</style></head><body><div class="page">

    <div style="text-align:center;margin-bottom:4mm;">
        <div style="font-size:7pt;color:#555;">Republic of the Philippines — Philippine Health Insurance Corporation</div>
        <h2>EMPLOYER REMITTANCE REPORT</h2>
        <div style="font-size:9pt;font-weight:700;">PhilHealth RF-1 Format</div>
        <div style="font-size:8pt;margin-top:1mm;">For the Month of: <b>' . $ph($monthLabel) . '</b></div>
    </div>

    <div class="header-block">
        <table>
            <tr>
                <td class="label">Employer Name:</td>
                <td class="field"><b>' . $ph($coName) . '</b></td>
                <td style="width:5mm;"></td>
                <td class="label">PhilHealth Employer No.:</td>
                <td class="field"><b>' . $ph($phEmpNo) . '</b></td>
            </tr>
            <tr>
                <td class="label">Employer Address:</td>
                <td class="field">' . $ph($coAddress) . '</td>
                <td></td>
                <td class="label">Report Period:</td>
                <td class="field"><b>' . $ph($monthLabel) . '</b></td>
            </tr>
            <tr>
                <td class="label">No. of Employees:</td>
                <td class="field"><b>' . count($records) . '</b></td>
                <td></td>
                <td class="label">Premium Rate:</td>
                <td class="field"><b>5% (EE 2.5% / ER 2.5%) — PA2025-0002</b></td>
            </tr>
        </table>
    </div>

    <div class="section-title">
        Employee Premium Contribution Details &nbsp;<span style="font-size:7pt;font-weight:400;">
        (PhilHealth Advisory PA2025-0002 — 5% rate | MBS Floor &#8369;10,000 | Ceiling &#8369;100,000)</span>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width:5mm;">#</th>
                <th style="width:18mm;">Emp No.</th>
                <th>Employee Name</th>
                <th style="width:30mm;">PhilHealth No.</th>
                <th class="r" style="width:22mm;">MBS (&#8369;)</th>
                <th class="r" style="width:22mm;">EE 2.5% (&#8369;)</th>
                <th class="r" style="width:22mm;">ER 2.5% (&#8369;)</th>
                <th class="r" style="width:22mm;">Total (&#8369;)</th>
            </tr>
        </thead>
        <tbody>' . $rows . '</tbody>
        <tfoot>
            <tr>
                <td colspan="4"><b>GRAND TOTAL — ' . count($records) . ' Employee(s)</b></td>
                <td class="r">—</td>
                <td class="r">&#8369;' . $fmt($totals['ph_ee']) . '</td>
                <td class="r">&#8369;' . $fmt($totals['ph_er']) . '</td>
                <td class="r">&#8369;' . $fmt($grandTotal) . '</td>
            </tr>
        </tfoot>
    </table>

    <div class="totals-box">
        <table>
            <tr>
                <td>Total Employee Premiums (EE 2.5%):</td>
                <td class="r">&#8369; ' . $fmt($totals['ph_ee']) . '</td>
                <td style="width:10mm;"></td>
                <td>Total Employer Premiums (ER 2.5%):</td>
                <td class="r">&#8369; ' . $fmt($totals['ph_er']) . '</td>
            </tr>
            <tr>
                <td colspan="4" style="font-size:9pt;font-weight:800;padding-top:2mm;">
                    TOTAL PHILHEALTH PREMIUM DUE (EE + ER):</td>
                <td class="r" style="font-size:10pt;color:#991b1b;">
                    &#8369; ' . $fmt($grandTotal) . '</td>
            </tr>
        </table>
    </div>

    <div class="note" style="margin-bottom:4mm;">
        &#9432; Remit via PhilHealth Employer Portal (eRMS) or accredited collecting agents.
        Due date: 11th–15th of the following month based on employer number last digit per PhilHealth guidelines.
        Penalty: 2% per month for late remittance (RA 7875 as amended).
    </div>

    <div class="sig-row">
        <table>
            <tr>
                <td>
                    <div>Prepared by:</div>
                    <div class="sig-line">HR / Payroll Officer — Signature over Printed Name</div>
                    <div style="margin-top:1mm;font-size:7pt;">Date: ___________________________</div>
                </td>
                <td>
                    <div>Certified correct by:</div>
                    <div class="sig-line">Authorized Signatory — Signature over Printed Name &amp; Designation</div>
                    <div style="margin-top:1mm;font-size:7pt;">Date: ___________________________</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="note" style="margin-top:4mm;">
        Generated by ' . $ph(defined('APP_NAME') ? APP_NAME : 'Payroll System') . ' on ' . date('F j, Y \a\t h:i A') . '.
        Only <b>released</b> payroll records are included.
    </div>
    </div></body></html>';

    $filename = 'PhilHealth_RF1_' . $monthLabelShort . '_' . preg_replace('/\s+/', '_', $coName) . '.pdf';
    $paper    = 'Legal';
}

// ════════════════════════════════════════════════════════════════════════════
//  Pag-IBIG MCRF
// ════════════════════════════════════════════════════════════════════════════
elseif ($report === 'pagibig') {
    $piMid = $co['pagibig_employer_mid'] ?: '[ Not Set — configure in Payroll Settings ]';

    $rows = '';
    foreach ($records as $i => $r) {
        $total = (float)$r['pi_ee'] + (float)$r['pi_er'];
        $rows .= '<tr>
            <td>' . ($i + 1) . '</td>
            <td>' . $ph($r['employee_no']) . '</td>
            <td><b>' . $ph($r['employee_name']) . '</b></td>
            <td>' . $ph($r['pagibig_no'] ?? '') . '</td>
            <td class="r">' . $fmt((float)$r['pi_mfs']) . '</td>
            <td class="r">' . $fmt((float)$r['pi_ee']) . '</td>
            <td class="r">' . $fmt((float)$r['pi_er']) . '</td>
            <td class="r"><b>' . $fmt($total) . '</b></td>
        </tr>';
    }

    $grandTotal = $totals['pi_ee'] + $totals['pi_er'];

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>' . $css . '</style></head><body><div class="page">

    <div style="text-align:center;margin-bottom:4mm;">
        <div style="font-size:7pt;color:#555;">Republic of the Philippines — Home Development Mutual Fund (Pag-IBIG Fund)</div>
        <h2>MONTHLY CONTRIBUTION REMITTANCE FORM</h2>
        <div style="font-size:9pt;font-weight:700;">HDMF MCRF Format</div>
        <div style="font-size:8pt;margin-top:1mm;">For the Month of: <b>' . $ph($monthLabel) . '</b></div>
    </div>

    <div class="header-block">
        <table>
            <tr>
                <td class="label">Employer Name:</td>
                <td class="field"><b>' . $ph($coName) . '</b></td>
                <td style="width:5mm;"></td>
                <td class="label">Pag-IBIG Employer MID:</td>
                <td class="field"><b>' . $ph($piMid) . '</b></td>
            </tr>
            <tr>
                <td class="label">Employer Address:</td>
                <td class="field">' . $ph($coAddress) . '</td>
                <td></td>
                <td class="label">Report Period:</td>
                <td class="field"><b>' . $ph($monthLabel) . '</b></td>
            </tr>
            <tr>
                <td class="label">No. of Employees:</td>
                <td class="field"><b>' . count($records) . '</b></td>
                <td></td>
                <td class="label">Applicable Circular:</td>
                <td class="field"><b>HDMF Circular No. 460 (eff. Feb 2024)</b></td>
            </tr>
        </table>
    </div>

    <div class="section-title">
        Employee Contribution Details &nbsp;<span style="font-size:7pt;font-weight:400;">
        (MFS ceiling &#8369;10,000 | EE max &#8369;200/mo | ER max &#8369;200/mo)</span>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width:5mm;">#</th>
                <th style="width:18mm;">Emp No.</th>
                <th>Employee Name</th>
                <th style="width:30mm;">Pag-IBIG MID No.</th>
                <th class="r" style="width:20mm;">MFS (&#8369;)</th>
                <th class="r" style="width:20mm;">EE (&#8369;)</th>
                <th class="r" style="width:20mm;">ER (&#8369;)</th>
                <th class="r" style="width:22mm;">Total (&#8369;)</th>
            </tr>
        </thead>
        <tbody>' . $rows . '</tbody>
        <tfoot>
            <tr>
                <td colspan="4"><b>GRAND TOTAL — ' . count($records) . ' Employee(s)</b></td>
                <td class="r">—</td>
                <td class="r">&#8369;' . $fmt($totals['pi_ee']) . '</td>
                <td class="r">&#8369;' . $fmt($totals['pi_er']) . '</td>
                <td class="r">&#8369;' . $fmt($grandTotal) . '</td>
            </tr>
        </tfoot>
    </table>

    <div class="totals-box">
        <table>
            <tr>
                <td>Total Employee Contributions (EE):</td>
                <td class="r">&#8369; ' . $fmt($totals['pi_ee']) . '</td>
                <td style="width:10mm;"></td>
                <td>Total Employer Contributions (ER):</td>
                <td class="r">&#8369; ' . $fmt($totals['pi_er']) . '</td>
            </tr>
            <tr>
                <td colspan="4" style="font-size:9pt;font-weight:800;padding-top:2mm;">
                    TOTAL PAG-IBIG REMITTANCE DUE (EE + ER):</td>
                <td class="r" style="font-size:10pt;color:#4c1d95;">
                    &#8369; ' . $fmt($grandTotal) . '</td>
            </tr>
        </table>
    </div>

    <div class="note" style="margin-bottom:4mm;">
        &#9432; Remit via Virtual Pag-IBIG (www.pagibigfundservices.com) or accredited collecting partners.
        Due date: 10th–15th of the following month per HDMF guidelines.
        Penalty for late remittance: 1/10 of 1% per day on unpaid amount (RA 9679).
    </div>

    <div class="sig-row">
        <table>
            <tr>
                <td>
                    <div>Prepared by:</div>
                    <div class="sig-line">HR / Payroll Officer — Signature over Printed Name</div>
                    <div style="margin-top:1mm;font-size:7pt;">Date: ___________________________</div>
                </td>
                <td>
                    <div>Certified correct by:</div>
                    <div class="sig-line">Authorized Signatory — Signature over Printed Name &amp; Designation</div>
                    <div style="margin-top:1mm;font-size:7pt;">Date: ___________________________</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="note" style="margin-top:4mm;">
        Generated by ' . $ph(defined('APP_NAME') ? APP_NAME : 'Payroll System') . ' on ' . date('F j, Y \a\t h:i A') . '.
        Only <b>released</b> payroll records are included.
    </div>
    </div></body></html>';

    $filename = 'PagIBIG_MCRF_' . $monthLabelShort . '_' . preg_replace('/\s+/', '_', $coName) . '.pdf';
    $paper    = 'Legal';
}

// ════════════════════════════════════════════════════════════════════════════
//  BIR Form 1601-C — Monthly Remittance Return of Compensation Tax Withheld
// ════════════════════════════════════════════════════════════════════════════
else { // bir1601c
    $birTin    = $co['bir_tin']       ?: '[ Not Set ]';
    $birRdo    = $co['bir_rdo_code']  ?: '[ Not Set ]';
    $coZip     = $co['company_zip']   ?: '';

    // Per BIR RR 11-2018 / RR 13-2023:
    // Total tax withheld this month = sum of withholding_tax from released payroll_records.
    // Month/Quarter classification for 1601-C:
    //   Q1: Jan(1st month), Feb(2nd), Mar(3rd/last)
    //   Q2: Apr, May, Jun
    //   Q3: Jul, Aug, Sep
    //   Q4: Oct, Nov, Dec
    $quarterMap    = [1=>'Q1',2=>'Q1',3=>'Q1',4=>'Q2',5=>'Q2',6=>'Q2',7=>'Q3',8=>'Q3',9=>'Q3',10=>'Q4',11=>'Q4',12=>'Q4'];
    $monthInQtrMap = [1=>1,2=>2,3=>3,4=>1,5=>2,6=>3,7=>1,8=>2,9=>3,10=>1,11=>2,12=>3];
    $quarter       = $quarterMap[$selectedMonth] ?? '';
    $monthInQtr    = $monthInQtrMap[$selectedMonth] ?? '';
    $isLastMonth   = in_array($selectedMonth, [3,6,9,12]);
    $monthNames    = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
                      7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];

    $totalWtax     = $totals['wtax'];
    $totalEmployees= count($records);

    // Employee breakdown rows
    $rows = '';
    foreach ($records as $i => $r) {
        $rows .= '<tr>
            <td>' . ($i + 1) . '</td>
            <td>' . $ph($r['employee_no']) . '</td>
            <td><b>' . $ph($r['employee_name']) . '</b></td>
            <td>' . $ph($r['tin_no'] ?? '') . '</td>
            <td class="r">' . $fmt((float)$r['total_gross']) . '</td>
            <td class="r">' . $fmt((float)$r['wtax']) . '</td>
        </tr>';
    }

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>' . $css . '
    .bir-box { border: 1.5pt solid #000; margin-bottom: 3mm; }
    .bir-box-hdr { background: #1a2744; color: #fff; padding: 2mm 3mm; font-weight: 700; font-size: 8pt; }
    .bir-row { display: table; width: 100%; border-collapse: collapse; }
    .bir-cell { display: table-cell; padding: 2mm 3mm; vertical-align: top; border-right: 0.5pt solid #ddd; font-size: 8pt; }
    .bir-cell:last-child { border-right: none; }
    .item-no { font-size: 6.5pt; color: #666; display: block; }
    .item-val { font-weight: 700; font-size: 9pt; border-bottom: 1pt solid #333; padding-bottom: 1mm; min-height: 5mm; }
    </style></head><body><div class="page">

    <div style="text-align:center;margin-bottom:3mm;">
        <div style="font-size:7pt;color:#555;">Republic of the Philippines — Bureau of Internal Revenue</div>
        <h2 style="font-size:12pt;">BIR FORM 1601-C</h2>
        <div style="font-size:9pt;font-weight:700;">Monthly Remittance Return of Income Taxes Withheld on Compensation</div>
        <div style="font-size:7.5pt;color:#555;margin-top:1mm;">(Under the Provisions of the National Internal Revenue Code — TRAIN Law RR 11-2018 / RR 13-2023)</div>
        <div style="font-size:8pt;margin-top:2mm;font-weight:700;">For the Month of: ' . $ph($monthLabel) . '
        &nbsp;|&nbsp; Calendar Year: ' . $selectedYear . '
        &nbsp;|&nbsp; Quarter: ' . $quarter . '
        &nbsp;|&nbsp; Month in Quarter: ' . $monthInQtr . ($isLastMonth ? ' (Last month of quarter)' : '') . '
        </div>
    </div>

    <!-- Part I: Background Info -->
    <div class="bir-box">
        <div class="bir-box-hdr">PART I — TAXPAYER / EMPLOYER INFORMATION</div>
        <div style="padding:3mm;">
            <table style="width:100%;border-collapse:collapse;">
                <tr>
                    <td style="width:50%;padding:2mm;">
                        <span class="item-no">1. Employer / Taxpayer Name</span>
                        <div class="item-val">' . $ph($coName) . '</div>
                    </td>
                    <td style="width:25%;padding:2mm;">
                        <span class="item-no">2. BIR TIN</span>
                        <div class="item-val">' . $ph($birTin) . '</div>
                    </td>
                    <td style="width:25%;padding:2mm;">
                        <span class="item-no">3. RDO Code</span>
                        <div class="item-val">' . $ph($birRdo) . '</div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="padding:2mm;">
                        <span class="item-no">4. Registered Address</span>
                        <div class="item-val">' . $ph($coAddress) . '</div>
                    </td>
                    <td style="padding:2mm;">
                        <span class="item-no">5. ZIP Code</span>
                        <div class="item-val">' . $ph($coZip) . '</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:2mm;">
                        <span class="item-no">6. Category of Withholding Agent</span>
                        <div class="item-val">Private (Employer of Compensation Income Earners)</div>
                    </td>
                    <td style="padding:2mm;">
                        <span class="item-no">7. Return Period (Month/Year)</span>
                        <div class="item-val">' . $ph($monthLabel) . '</div>
                    </td>
                    <td style="padding:2mm;">
                        <span class="item-no">8. No. of Employees</span>
                        <div class="item-val">' . $totalEmployees . '</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Part II: Tax Computation -->
    <div class="bir-box">
        <div class="bir-box-hdr">PART II — COMPUTATION OF TAX WITHHELD</div>
        <div style="padding:3mm;">
            <table style="width:100%;border-collapse:collapse;">
                <tr>
                    <td style="padding:2mm 3mm;width:70%;">
                        <span class="item-no">9A. Total Compensation Income (Gross Pay — all employees this month)</span>
                        <div style="font-size:7.5pt;color:#555;">Sum of released payroll gross pay for ' . $ph($monthLabel) . '</div>
                    </td>
                    <td style="padding:2mm 3mm;text-align:right;">
                        <div class="item-val" style="text-align:right;">&#8369; ' . $fmt($totals['total_gross']) . '</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:2mm 3mm;">
                        <span class="item-no">9B. Total Non-Taxable / Exempt Compensation (Gov. Contributions EE share)</span>
                        <div style="font-size:7.5pt;color:#555;">SSS EE + PhilHealth EE + Pag-IBIG EE for ' . $ph($monthLabel) . '</div>
                    </td>
                    <td style="padding:2mm 3mm;text-align:right;">
                        <div class="item-val" style="text-align:right;">&#8369; ' . $fmt($totals['sss_ee'] + $totals['ph_ee'] + $totals['pi_ee']) . '</div>
                    </td>
                </tr>
                <tr style="background:#f8fafc;">
                    <td style="padding:2mm 3mm;">
                        <span class="item-no">10. Total Income Tax Withheld on Compensation</span>
                        <div style="font-size:7.5pt;color:#555;">Sum of withholding_tax from released payroll records for ' . $ph($monthLabel) . '</div>
                    </td>
                    <td style="padding:2mm 3mm;text-align:right;">
                        <div class="item-val" style="font-size:10pt;font-weight:800;text-align:right;color:#1d4ed8;">&#8369; ' . $fmt($totalWtax) . '</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:2mm 3mm;">
                        <span class="item-no">11. Tax Remitted in Return Previously Filed (if amended — leave blank if original)</span>
                    </td>
                    <td style="padding:2mm 3mm;text-align:right;">
                        <div class="item-val" style="text-align:right;color:#aaa;">___________________</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:2mm 3mm;">
                        <span class="item-no">12. Other Credits / Payments</span>
                    </td>
                    <td style="padding:2mm 3mm;text-align:right;">
                        <div class="item-val" style="text-align:right;color:#aaa;">___________________</div>
                    </td>
                </tr>
                <tr style="background:#e8edf5;">
                    <td style="padding:3mm;font-weight:800;font-size:9pt;">
                        13. TAX STILL DUE / (OVERPAYMENT) — Amount to Remit to BIR
                    </td>
                    <td style="padding:3mm;text-align:right;">
                        <div style="font-size:11pt;font-weight:800;color:#991b1b;">&#8369; ' . $fmt($totalWtax) . '</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Part III: Employee Breakdown -->
    <div class="section-title" style="margin-top:3mm;">
        SCHEDULE — Employee Compensation and Tax Withheld Breakdown
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width:5mm;">#</th>
                <th style="width:18mm;">Emp No.</th>
                <th>Employee Name</th>
                <th style="width:28mm;">TIN</th>
                <th class="r" style="width:28mm;">Gross Compensation (&#8369;)</th>
                <th class="r" style="width:28mm;">Tax Withheld (&#8369;)</th>
            </tr>
        </thead>
        <tbody>' . $rows . '</tbody>
        <tfoot>
            <tr>
                <td colspan="4"><b>TOTAL — ' . $totalEmployees . ' Employee(s)</b></td>
                <td class="r">&#8369;' . $fmt($totals['total_gross']) . '</td>
                <td class="r">&#8369;' . $fmt($totalWtax) . '</td>
            </tr>
        </tfoot>
    </table>

    <!-- Declaration -->
    <div class="bir-box" style="margin-top:3mm;">
        <div class="bir-box-hdr">DECLARATION</div>
        <div style="padding:3mm;font-size:7.5pt;">
            I declare, under the penalties of perjury, that this return has been made in good faith,
            verified by me, and to the best of my knowledge and belief, is true and correct,
            pursuant to the National Internal Revenue Code, as amended, and the regulations
            issued under authority thereof.
        </div>
    </div>

    <div class="note" style="margin-bottom:4mm;">
        &#9432; <b>Filing deadline:</b> ' . ($isLastMonth ? 'Last day of the month following the close of the quarter (quarterly return required).' : 'On or before the 10th day of the following month (monthly remittance).') . '
        File via eBIRForms (Offline Package) or EFPS. Payment via AABs or GCash/PayMaya (Authorized Agent Banks).
        Surcharge: 25% of tax due + 12% interest per annum for late filing (NIRC Sec. 248-249).
    </div>

    <div class="sig-row">
        <table>
            <tr>
                <td>
                    <div>Prepared by:</div>
                    <div class="sig-line">HR / Payroll Officer — Signature over Printed Name / TIN</div>
                    <div style="margin-top:1mm;font-size:7pt;">Date: ___________________________</div>
                </td>
                <td>
                    <div>Signed and Certified by (Authorized Officer):</div>
                    <div class="sig-line">President / Authorized Signatory — Signature over Printed Name / TIN / Designation</div>
                    <div style="margin-top:1mm;font-size:7pt;">Date: ___________________________</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="note" style="margin-top:4mm;">
        Generated by ' . $ph(defined('APP_NAME') ? APP_NAME : 'Payroll System') . ' on ' . date('F j, Y \a\t h:i A') . '.
        Only <b>released</b> payroll records are included. This is a system-generated working document —
        official filing must be done via BIR eBIRForms or EFPS using BIR-prescribed format.
    </div>
    </div></body></html>';

    $filename = 'BIR_1601C_' . $monthLabelShort . '_' . preg_replace('/\s+/', '_', $coName) . '.pdf';
    $paper    = 'Legal';
}

// ── Dompdf render ─────────────────────────────────────────────────────────────
$opts = new \Dompdf\Options();
$opts->set('defaultFont', 'Arial');
$opts->set('isRemoteEnabled', false);
$opts->set('isPhpEnabled', false);

$dompdf = new \Dompdf\Dompdf($opts);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper($paper, 'portrait');
$dompdf->render();

while (ob_get_level()) { ob_end_clean(); }
$safeName = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $filename);
$dompdf->stream($safeName, ['Attachment' => true]);
exit;
