<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../core/Model.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) {
    header('Location: ' . BASE_URL . '/index.php?error=access_denied'); exit;
}

$currentYear   = (int) date('Y');
$selectedYear  = isset($_GET['year']) && is_numeric($_GET['year'])
    ? max(2020, min($currentYear, (int)$_GET['year'])) : $currentYear;
$selectedEmpId = (int)($_GET['emp_id'] ?? 0);

$autoload = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($autoload)) { die('Dompdf not installed. Run: composer require dompdf/dompdf'); }
require_once $autoload;

$employee = $selectedEmpId ? Model::findEmployeeById($selectedEmpId) : null;
if (!$employee) { http_response_code(404); die('Employee not found.'); }

$ytd     = Model::getPayrollYTD($selectedEmpId, $selectedYear . '-12-2');
$ytd13th = Model::get13thMonthByEmployee($selectedEmpId, $selectedYear);

if (empty($ytd)) { http_response_code(404); die('No payroll data for this employee/year.'); }

$companyCfg     = Model::getAllCompanySettings();
$companyName    = $companyCfg['company_name']    ?? (defined('COMPANY_NAME')    ? COMPANY_NAME    : '');
$companyAddress = $companyCfg['company_address'] ?? (defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : '');
$companyZip     = $companyCfg['company_zip']     ?? '';
$employerTin    = $companyCfg['bir_tin']         ?? '';
$employerRdo    = $companyCfg['bir_rdo_code']    ?? '';

$totalBasic      = (float)($ytd['ytd_basic']        ?? 0);
$totalAllowance  = (float)($ytd['ytd_allowance']    ?? 0);
$totalGross      = (float)($ytd['ytd_gross']         ?? 0);
$totalSSS        = (float)($ytd['ytd_sss_ee']        ?? 0);
$totalPhilHealth = (float)($ytd['ytd_philhealth_ee'] ?? 0);
$totalPagIbig    = (float)($ytd['ytd_pagibig_ee']    ?? 0);
$totalTax        = (float)($ytd['ytd_tax']           ?? 0);
$thirteenth      = $ytd13th ? (float)($ytd13th['amount'] ?? 0) : 0.0;
$exemptThirteenth  = min($thirteenth, 90000.0);
$taxableThirteenth = max(0.0, $thirteenth - 90000.0);
$govDeds           = $totalSSS + $totalPhilHealth + $totalPagIbig;

$nonTaxableBasic = min($totalBasic, 250000.0);
$taxableBasic    = max(0.0, $totalBasic - 250000.0);

$totalNonTaxable = $nonTaxableBasic + $exemptThirteenth + $govDeds;

$item19 = $totalGross + $thirteenth;
$item20 = $totalNonTaxable;
$item21 = max(0.0, $item19 - $item20);
$item23 = $item21;
$item24 = $totalTax;
$item52 = $taxableBasic + $taxableThirteenth;

$f = fn(float $v): string => $v > 0 ? number_format($v, 2) : '';

$formatTin = function (string $tin): string {
    $d = preg_replace('/\D/', '', $tin);
    if (strlen($d) === 9) {
        return substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6, 3);
    }
    if (strlen($d) >= 12) {
        return substr($d, 0, 3) . '-' . substr($d, 3, 3) . '-' . substr($d, 6, 3) . '-' . substr($d, 9, 3);
    }
    return $tin;
};

$empTin     = htmlspecialchars($formatTin($employee['tin_no'] ?? ''));
$empName    = htmlspecialchars($employee['name']    ?? '');
$empAddress = htmlspecialchars($employee['address'] ?? '');
$empZip     = htmlspecialchars($employee['zip_code'] ?? $employee['zip'] ?? '');
$empBirth   = !empty($employee['birthdate']) ? date('m/d/Y', strtotime($employee['birthdate'])) : '';
$empPhone   = htmlspecialchars($employee['phone'] ?? '');
$fmtEmpRdo  = htmlspecialchars($employerRdo);
$fmtEmrTin  = htmlspecialchars($formatTin($employerTin));

$substitutedFilingText = 'I declare, under the penalties of perjury that I am qualified under substituted filing of Income Tax Return (BIR Form No. 1700), since I received purely compensation income from only one employer in the Philippines for the calendar year; that taxes have been correctly withheld by my employer (tax due equals tax withheld); that the BIR Form No. 1604-C filed by my employer to the BIR shall constitute as my income tax return; and that BIR Form No. 2316 shall serve the same purpose as if BIR Form No. 1700 has been filed pursuant to the provisions of Revenue Regulations (RR) No. 3-2002, as amended.';

$css = '
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}
body{font-size:7pt;color:#000;background:#fff;}
.page{padding:8mm 8mm;}
.bir-form{border:1.5px solid #000;width:100%;}
.top-bar{display:table;width:100%;border-bottom:1px solid #000;}
.top-left{display:table-cell;width:60px;font-size:6pt;padding:3px 5px;border-right:1px solid #000;vertical-align:top;}
.top-center{display:table-cell;text-align:center;padding:4px 6px;border-right:1px solid #000;vertical-align:middle;}
.top-right{display:table-cell;width:90px;font-size:6pt;padding:3px 5px;text-align:right;vertical-align:bottom;}
.form-no-big{font-size:22pt;font-weight:900;line-height:1;}
.form-title{font-size:10pt;font-weight:700;}
.form-desc{font-size:6pt;}
.gov-hdr{font-size:6.5pt;margin-bottom:2px;}
.instr{padding:2px 6px;font-size:6pt;border-bottom:1px solid #000;}
.yr-row{display:table;width:100%;border-bottom:1px solid #000;}
.yr-cell{display:table-cell;padding:2px 6px;font-size:6pt;vertical-align:top;}
.yr-cell-r{border-left:1px solid #000;}
.val-big{font-size:10pt;font-weight:700;}
.body-wrap{display:table;width:100%;}
.col-left{display:table-cell;width:50%;vertical-align:top;border-right:1px solid #000;}
.col-right{display:table-cell;width:50%;vertical-align:top;}
.sec-hdr{background:#000;color:#fff;font-size:6.5pt;font-weight:700;text-align:center;padding:1px 4px;border-bottom:1px solid #000;}
.frow{border-bottom:1px solid #ddd;display:table;width:100%;min-height:14px;}
.frow-nb{border-bottom:none;}
.flabel{display:table-cell;font-size:5.5pt;padding:1px 3px;vertical-align:top;width:45%;}
.fval{display:table-cell;font-size:7pt;font-weight:600;padding:1px 3px;vertical-align:middle;border-left:1px solid #ccc;}
.fnum{font-size:5pt;font-weight:700;}
.drow{border-bottom:1px solid #ddd;display:table;width:100%;}
.dcell{display:table-cell;padding:1px 3px;font-size:5.5pt;vertical-align:top;}
.dcell-r{border-left:1px solid #ccc;}
.arow{display:table;width:100%;border-bottom:1px solid #eee;min-height:12px;}
.arow-bold{border-top:1.5px solid #000;font-weight:700;}
.alabel{display:table-cell;font-size:5.5pt;padding:1px 4px;vertical-align:middle;}
.abox{display:table-cell;width:80px;border-left:1px solid #000;font-size:6.5pt;font-weight:700;text-align:right;padding:1px 3px;vertical-align:middle;}
.srow{display:table;width:100%;border-bottom:1px solid #eee;min-height:14px;}
.slabel{display:table-cell;font-size:5.5pt;padding:1px 4px;vertical-align:middle;}
.sbox{display:table-cell;width:90px;border-left:1px solid #000;font-size:6.5pt;font-weight:700;text-align:right;padding:1px 3px;vertical-align:middle;}
.chk{display:inline-block;width:8px;height:8px;border:1px solid #000;font-size:7pt;text-align:center;line-height:7px;vertical-align:middle;margin-right:2px;}
.cert{padding:4px 6px;font-size:5.5pt;border-top:1px solid #000;}
.sig-grid{display:table;width:100%;margin-top:6px;}
.sig-cell{display:table-cell;width:50%;padding:0 6px;text-align:center;vertical-align:bottom;}
.sig-line{border-top:1px solid #000;padding-top:2px;font-size:5.5pt;margin-top:18px;}
.ctc-grid{display:table;width:100%;margin-top:5px;}
.ctc-left{display:table-cell;width:50%;border:1px solid #000;padding:3px;font-size:5.5pt;vertical-align:top;}
.ctc-right{display:table-cell;width:50%;border:1px solid #000;padding:3px;font-size:5.5pt;vertical-align:top;}
.sub-grid{display:table;width:100%;border-top:1px solid #000;}
.sub-cell{display:table-cell;width:50%;padding:4px 6px;font-size:5.5pt;vertical-align:top;}
.sub-cell-r{border-left:1px solid #000;}
.note{font-size:5pt;padding:2px 6px;border-top:1px solid #000;}
.sec-hdr-gray{background:#e0e0e0;color:#000;font-size:6.5pt;font-weight:700;padding:1px 4px;border-bottom:1px solid #000;}
.withheld-row{background:#fffde7;border-top:1px solid #000;}
';

$html  = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>{$css}</style></head><body>";
$html .= "<div class='page'><div class='bir-form'>";

$html .= "
<div class='top-bar'>
  <div class='top-left'>For BIR<br>Use Only<br>BCS/<br>Item:<br><strong>2316</strong></div>
  <div class='top-center'>
    <div class='gov-hdr'>Republic of the Philippines &bull; Department of Finance &bull; <strong>Bureau of Internal Revenue</strong></div>
    <div>BIR Form No.</div>
    <div class='form-no-big'>2316</div>
    <div class='form-title'>Certificate of Compensation</div>
    <div class='form-title'>Payment/Tax Withheld</div>
    <div class='form-desc'>For Compensation Payment With or Without Tax Withheld</div>
  </div>
  <div class='top-right'>
    <div>September 2021(ENCS)</div>
    <div>2316 9/21ENCS</div>
  </div>
</div>
<div class='instr'>Fill in all applicable spaces. Mark all appropriate boxes with an &quot;X&quot;.</div>
<div class='yr-row'>
  <div class='yr-cell' style='width:33%'>
    <span class='fnum'>1</span> For the Year <strong>(YYYY)</strong>
    <div class='val-big'>{$selectedYear}</div>
  </div>
  <div class='yr-cell yr-cell-r'>
    <span class='fnum'>2</span> For the Period &nbsp;
    From <strong>(MM/DD)</strong> <span style='border:1px solid #000;padding:0 3px;'>01/01</span>
    &nbsp; To <strong>(MM/DD)</strong> <span style='border:1px solid #000;padding:0 3px;'>12/31</span>
  </div>
</div>
<div class='body-wrap'>
  <div class='col-left'>
    <div class='sec-hdr'>Part I - Employee Information</div>
    <div class='frow'><div class='flabel'><span class='fnum'>3</span> TIN</div><div class='fval'>{$empTin}</div></div>
    <div class='drow'>
      <div class='dcell' style='width:65%'><span class='fnum'>4</span> Employee's Name <em>(Last Name, First Name, Middle Name)</em><br><strong>{$empName}</strong></div>
      <div class='dcell dcell-r'><span class='fnum'>5</span> RDO Code<br><span>{$fmtEmpRdo}</span></div>
    </div>
    <div class='frow'><div class='flabel'><span class='fnum'>6</span> Registered Address</div><div class='fval'>{$empAddress}</div></div>
    <div class='drow'>
      <div class='dcell' style='width:65%'><span class='fnum'>6B</span> Local Home Address</div>
      <div class='dcell dcell-r'><span class='fnum'>6A</span> ZIP Code<br><strong>{$empZip}</strong></div>
    </div>
    <div class='frow'><div class='flabel'><span class='fnum'>6D</span> Foreign Address</div><div class='fval'>&nbsp;</div></div>
    <div class='drow'>
      <div class='dcell' style='width:50%'><span class='fnum'>7</span> Date of Birth <em>(MM/DD/YYYY)</em><br><strong>{$empBirth}</strong></div>
      <div class='dcell dcell-r'><span class='fnum'>8</span> Contact Number<br><strong>{$empPhone}</strong></div>
    </div>
    <div class='drow'>
      <div class='dcell' style='width:50%'><span class='fnum'>9</span> Statutory Min. Wage/day</div>
      <div class='dcell dcell-r'><span class='fnum'>10</span> Statutory Min. Wage/month</div>
    </div>
    <div class='frow frow-nb' style='padding:2px 3px;'><span class='fnum'>11</span> <span class='chk'>&nbsp;</span> Minimum Wage Earner (MWE) exempt from withholding tax</div>

    <div class='sec-hdr'>Part II - Employer Information (Present)</div>
    <div class='frow'><div class='flabel'><span class='fnum'>12</span> TIN</div><div class='fval'>{$fmtEmrTin}</div></div>
    <div class='frow'><div class='flabel'><span class='fnum'>13</span> Employer's Name</div><div class='fval'>" . htmlspecialchars($companyName) . "</div></div>
    <div class='frow'><div class='flabel'><span class='fnum'>14</span> Registered Address</div><div class='fval'>" . htmlspecialchars($companyAddress) . "</div></div>
    <div class='frow'><div class='flabel'><span class='fnum'>14A</span> ZIP Code</div><div class='fval'>" . htmlspecialchars($companyZip) . "</div></div>
    <div class='frow frow-nb' style='padding:2px 4px;font-size:6pt;'><span class='fnum'>15</span> Type of Employer &nbsp;<span class='chk'>&#10003;</span> Main Employer &nbsp;<span class='chk'>&nbsp;</span> Secondary Employer</div>

    <div class='sec-hdr'>Part III - Employer Information (Previous)</div>
    <div class='frow'><div class='flabel'><span class='fnum'>16</span> TIN</div><div class='fval'>&nbsp;</div></div>
    <div class='frow'><div class='flabel'><span class='fnum'>17</span> Employer's Name</div><div class='fval'>&nbsp;</div></div>
    <div class='frow'><div class='flabel'><span class='fnum'>18</span> Registered Address</div><div class='fval'>&nbsp;</div></div>
    <div class='frow'><div class='flabel'><span class='fnum'>18A</span> ZIP Code</div><div class='fval'>&nbsp;</div></div>

    <div class='sec-hdr'>Part IVA - Summary</div>";

$sumRows = [
    ['19', 'Gross Compensation Income from Present Employer (Sum of Items 38 and 52)', $f($item19)],
    ['20', 'Less: Total Non-Taxable/Exempt Compensation Income (From Item 38)',        $f($item20)],
    ['21', 'Taxable Compensation Income from Present Employer (Item 19 Less 20)',      $f($item21)],
    ['22', 'Add: Taxable Compensation Income from Previous Employer',                  ''],
    ['23', 'Gross Taxable Compensation Income (Sum of Items 21 and 22)',               $f($item23)],
    ['24', 'Tax Due',                                                                  $f($item24)],
];
foreach ($sumRows as [$n, $l, $v]) {
    $html .= "<div class='srow'><div class='slabel'><span class='fnum'>{$n}</span> {$l}</div><div class='sbox'>{$v}</div></div>";
}

$html .= "
    <div style='padding:1px 4px;font-size:5.5pt;font-weight:700;border-bottom:1px solid #eee;'><span class='fnum'>25</span> Amount of Taxes Withheld</div>
    <div class='srow'><div class='slabel'>&nbsp;&nbsp;<span class='fnum'>25A</span> Present Employer</div><div class='sbox'>{$f($totalTax)}</div></div>
    <div class='srow'><div class='slabel'>&nbsp;&nbsp;<span class='fnum'>25B</span> Previous Employer, if applicable</div><div class='sbox'></div></div>
    <div class='srow'><div class='slabel'><span class='fnum'>26</span> Total Taxes Withheld as adjusted (Sum of 25A and 25B)</div><div class='sbox'>{$f($totalTax)}</div></div>
    <div class='srow'><div class='slabel'><span class='fnum'>27</span> 5% Tax Credit (PERA Act of 2008)</div><div class='sbox'></div></div>
    <div class='srow'><div class='slabel'><span class='fnum'>28</span> Total Taxes Withheld (Sum of Items 26 and 27)</div><div class='sbox'>{$f($totalTax)}</div></div>
  </div>

  <div class='col-right'>
    <div class='sec-hdr'>Part IV-B Details of Compensation Income &amp; Tax Withheld from Present Employer</div>
    <div class='sec-hdr-gray'>A. NON-TAXABLE/EXEMPT COMPENSATION INCOME <span style='float:right;'>Amount</span></div>";

$nonTaxRows = [
    ['29', 'Basic Salary (exempt portion up to &#8369;250,000 per TRAIN Law)',       $f($nonTaxableBasic)],
    ['30', 'Holiday Pay (MWE)',                                                       ''],
    ['31', 'Overtime Pay (MWE)',                                                      ''],
    ['32', 'Night Shift Differential (MWE)',                                          ''],
    ['33', 'Hazard Pay (MWE)',                                                        ''],
    ['34', '13th Month Pay and Other Benefits (max &#8369;90,000)',                   $f($exemptThirteenth)],
    ['35', 'De Minimis Benefits',                                                     ''],
    ['36', 'SSS, GSIS, PHIC &amp; PAG-IBIG Contributions and Union Dues (EE share)', $f($govDeds)],
    ['37', 'Salaries and Other Forms of Compensation',                                ''],
    ['38', 'Total Non-Taxable/Exempt (Sum of Items 29 to 37)',                        $f($totalNonTaxable)],
];
foreach ($nonTaxRows as [$n, $l, $v]) {
    $cls = ($n === '38') ? 'arow arow-bold' : 'arow';
    $html .= "<div class='{$cls}'><div class='alabel'><span class='fnum'>{$n}</span> {$l}</div><div class='abox'>{$v}</div></div>";
}

$html .= "<div class='sec-hdr-gray' style='margin-top:2px;'>B. TAXABLE COMPENSATION INCOME <span style='float:right;'>REGULAR</span></div>";

$taxableRows = [
    ['39',  'Basic Salary (taxable portion exceeding &#8369;250,000 exemption)',   $f($taxableBasic)],
    ['40',  'Representation',                                                       ''],
    ['41',  'Transportation',                                                       ''],
    ['42',  'Cost of Living Allowance (COLA)',                                      ''],
    ['43',  'Fixed Housing Allowance',                                              ''],
    ['44',  'Others (specify)',                                                     ''],
    ['44A', '',                                                                     ''],
    ['44B', '<em>SUPPLEMENTARY</em>',                                               ''],
    ['45',  'Commission',                                                           ''],
    ['46',  'Profit Sharing',                                                       ''],
    ['47',  'Fees Including Director\'s Fees',                                      ''],
    ['48',  'Taxable 13th Month Benefits (excess over &#8369;90,000)',              $f($taxableThirteenth)],
    ['49',  'Hazard Pay',                                                           ''],
    ['50',  'Overtime Pay',                                                         ''],
    ['51',  'Others (specify)',                                                     ''],
    ['51A', '',                                                                     ''],
    ['51B', '',                                                                     ''],
];
foreach ($taxableRows as [$n, $l, $v]) {
    $html .= "<div class='arow'><div class='alabel'><span class='fnum'>{$n}</span> {$l}</div><div class='abox'>{$v}</div></div>";
}

$html .= "
    <div class='arow arow-bold'><div class='alabel'><span class='fnum'>52</span> Total Taxable Compensation Income (Sum of Items 39 to 51B)</div><div class='abox'>{$f($item52)}</div></div>
    <div class='arow withheld-row'><div class='alabel'><span class='fnum'>53</span> <strong>Total Amount of Taxes Withheld as adjusted</strong></div><div class='abox'>{$f($totalTax)}</div></div>
  </div>
</div>

<div class='cert'>
  <div>I/We declare, under the penalties of perjury that this certificate has been made in good faith, verified by me/us, and to the best of my/our knowledge and belief, is true and correct, pursuant to the provisions of the National Internal Revenue Code, as amended, and the regulations issued under authority thereof. Further, I/we give my/our consent to the processing of my/our information as contemplated under the *Data Privacy Act of 2012 (R.A. No. 10173) for legitimate and lawful purposes.</div>
  <div class='sig-grid'>
    <div class='sig-cell'>
      <div class='sig-line'>" . htmlspecialchars($companyName) . "<br><span>Present Employer/Authorized Agent Signature over Printed Name</span></div>
      <div style='text-align:right;font-size:5.5pt;margin-top:3px;'>Date Signed: <span style='border-bottom:1px solid #000;padding:0 18px;'></span></div>
    </div>
    <div class='sig-cell'>
      <div style='font-size:6pt;font-weight:700;text-align:left;margin-bottom:2px;'>CONFORME:</div>
      <div class='sig-line'>{$empName}<br><span>Employee Signature over Printed Name</span></div>
      <div style='text-align:right;font-size:5.5pt;margin-top:3px;'>Date Signed: <span style='border-bottom:1px solid #000;padding:0 18px;'></span></div>
    </div>
  </div>
  <div class='ctc-grid'>
    <div class='ctc-left'>
      CTC/Valid ID No. of Employee: <span style='border-bottom:1px solid #000;padding:0 20px;'></span><br>
      Place of Issue: <span style='border-bottom:1px solid #000;padding:0 25px;'></span><br>
      Date Issued: <span style='border-bottom:1px solid #000;padding:0 25px;'></span><br>
      Amount paid, if CTC: <span style='border-bottom:1px solid #000;padding:0 15px;'></span>
    </div>
    <div class='ctc-right'>
      <strong>To be accomplished under substituted filing</strong><br>
      {$substitutedFilingText}
    </div>
  </div>
</div>

<div class='sub-grid'>
  <div class='sub-cell'>
    <div>I declare, under the penalties of perjury that the information herein stated are reported under BIR Form No. 1604-C which has been filed with the Bureau of Internal Revenue.</div>
    <div class='sig-line' style='margin-top:14px;'>" . htmlspecialchars($companyName) . "</div>
    <div style='text-align:center;font-size:5pt;'><span class='fnum'>55</span> Present Employer/Authorized Agent Signature over Printed Name<br>(Head of Accounting/Human Resource or Authorized Representative)</div>
  </div>
  <div class='sub-cell sub-cell-r'>
    <div>&nbsp;</div>
    <div class='sig-line' style='margin-top:14px;'>&nbsp;</div>
    <div style='text-align:center;font-size:5pt;'><span class='fnum'>56</span> Employee Signature over Printed Name</div>
  </div>
</div>
<div class='note'>*NOTE: The BIR Data Privacy is in the BIR website (www.bir.gov.ph)</div>
</div></div></body></html>";

$opts = new \Dompdf\Options();
$opts->set('defaultFont', 'Arial');
$opts->set('isRemoteEnabled', false);
$opts->set('isPhpEnabled', false);

$dompdf = new \Dompdf\Dompdf($opts);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('Legal', 'portrait');
$dompdf->render();

while (ob_get_level()) { ob_end_clean(); }
$fn = 'BIR2316_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $employee['name'] ?? 'Employee') . '_' . $selectedYear . '.pdf';
$dompdf->stream($fn, ['Attachment' => true]);
exit;
