<?php
// app/views/admin/gov_reports.php
$pageTitle  = 'Gov. Contribution Reports';
$breadcrumb = 'Reports';
$activeMenu = 'gov_reports';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('ROLE_ADMIN'))    require_once __DIR__ . '/../../../config/config.php';
if (!defined('DB_HOST'))       require_once __DIR__ . '/../../../config/database.php';
if (!class_exists('Database')) require_once __DIR__ . '/../../../core/Database.php';
if (!class_exists('Model'))    require_once __DIR__ . '/../../../core/Model.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) {
    header('Location: ' . BASE_URL . '/index.php?error=access_denied'); exit;
}

$currentYM     = date('Y-m');
$selectedYM    = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : $currentYM;
$selectedYear  = (int)substr($selectedYM, 0, 4);
$selectedMonth = (int)substr($selectedYM, 5, 2);
$activeReport  = in_array($_GET['report'] ?? '', ['sss','philhealth','pagibig','bir1601c'])
               ? $_GET['report'] : 'sss';

$db   = Database::getInstance();
$stmt = $db->prepare("
    SELECT
        pr.employee_id,
        e.employee_no,
        e.name                              AS employee_name,
        e.sss_no, e.philhealth_no, e.pagibig_no, e.tin_no,
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
        COUNT(pr.id)                        AS cutoffs_released
    FROM payroll_records pr
    JOIN employees e  ON e.id  = pr.employee_id
    JOIN departments d ON d.id = e.department_id
    WHERE pr.status = 'released' AND pr.period LIKE ?
    GROUP BY pr.employee_id, e.employee_no, e.name, e.sss_no,
             e.philhealth_no, e.pagibig_no, e.tin_no, d.name
    ORDER BY e.name
");
$stmt->execute([$selectedYM . '-%']);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totals = ['sss_ee'=>0,'sss_er'=>0,'ph_ee'=>0,'ph_er'=>0,'pi_ee'=>0,'pi_er'=>0,'wtax'=>0,'total_gross'=>0];
foreach ($records as $r) {
    foreach (['sss_ee','sss_er','ph_ee','ph_er','pi_ee','pi_er','wtax'] as $k) $totals[$k] += (float)$r[$k];
    $totals['total_gross'] += (float)$r['total_gross'];
}

$monthLabel = date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
$co         = Model::getReportHeader();
$hasSSS     = !empty(trim($co['sss_employer_id']));
$hasPH      = !empty(trim($co['philhealth_employer_no']));
$hasPI      = !empty(trim($co['pagibig_employer_mid']));
$hasBIR     = !empty(trim($co['bir_tin']));
$quarterMap    = [1=>'Q1',2=>'Q1',3=>'Q1',4=>'Q2',5=>'Q2',6=>'Q2',7=>'Q3',8=>'Q3',9=>'Q3',10=>'Q4',11=>'Q4',12=>'Q4'];
$monthInQtrMap = [1=>1,2=>2,3=>3,4=>1,5=>2,6=>3,7=>1,8=>2,9=>3,10=>1,11=>2,12=>3];
$birQuarter    = $quarterMap[$selectedMonth] ?? '';
$birMonthInQtr = $monthInQtrMap[$selectedMonth] ?? '';

require_once __DIR__ . '/../layouts/admin_header.php';
?>
<style>
.report-card{background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:24px;}
.report-hdr{background:#1a2744;color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
.report-hdr h5{margin:0;font-size:.98rem;font-weight:700;}
.report-hdr .ref{font-size:.73rem;color:#93c5fd;}
.export-bar{background:#f8fafc;border-bottom:1px solid #e2e8f0;padding:10px 18px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.export-bar .lbl{font-size:.78rem;font-weight:600;color:#374151;margin-right:4px;}
.ebtn{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:6px;font-size:.82rem;font-weight:600;text-decoration:none;border:none;cursor:pointer;transition:background .15s;}
.ebtn-pdf{background:#dc2626;color:#fff;} .ebtn-pdf:hover{background:#b91c1c;color:#fff;}
.ebtn-txt{background:#0891b2;color:#fff;} .ebtn-txt:hover{background:#0e7490;color:#fff;}
.rpt-tab{border:none;border-radius:6px;padding:7px 16px;font-size:.86rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;transition:background .15s;}
.rpt-tab.on{background:#1a2744;color:#fff;}
.rpt-tab.off{background:#f1f5f9;color:#475569;} .rpt-tab.off:hover{background:#e2e8f0;}
.rtable{width:100%;border-collapse:collapse;font-size:.81rem;}
.rtable thead th{background:#f1f5f9;color:#374151;padding:8px 10px;text-align:left;border-bottom:1.5px solid #cbd5e1;white-space:nowrap;}
.rtable thead th.r{text-align:right;}
.rtable tbody td{padding:7px 10px;border-bottom:1px solid #f1f5f9;color:#1e293b;vertical-align:middle;}
.rtable tbody td.r{text-align:right;font-variant-numeric:tabular-nums;}
.rtable tbody tr:hover{background:#f8fafc;}
.rtable tfoot td{padding:8px 10px;background:#f1f5f9;border-top:2px solid #cbd5e1;font-weight:700;}
.rtable tfoot td.r{text-align:right;color:#1d4ed8;}
.stile{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;text-align:center;}
.stile .val{font-size:1.05rem;font-weight:800;color:#1a2744;}
.stile .lbl{font-size:.73rem;color:#64748b;margin-top:2px;}
.empty-box{text-align:center;padding:40px;color:#94a3b8;}
.empty-box i{font-size:2.5rem;display:block;margin-bottom:10px;}
.note-box{padding:12px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:.77rem;color:#64748b;}
.warn-banner{background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:9px 14px;font-size:.8rem;color:#78350f;margin-bottom:12px;}
.warn-banner a{color:#92400e;font-weight:600;}
.bir-summary{background:#fff7ed;border:1.5px solid #fed7aa;border-radius:8px;padding:16px 20px;margin-bottom:16px;}
.bir-summary .big{font-size:1.5rem;font-weight:800;color:#9a3412;}
.bir-meta td{padding:4px 10px;font-size:.82rem;}
.bir-meta td.lbl{font-weight:600;color:#374151;white-space:nowrap;}
.sig-block{border:1px solid #e2e8f0;border-radius:8px;padding:16px;font-size:.82rem;}
.sig-line{border-top:1px solid #cbd5e1;margin-top:40px;padding-top:6px;color:#64748b;}
@media print{
  .no-print,.main-header,.main-sidebar,.main-footer,.content-header,.breadcrumb,.page-title-bar,
  .export-bar,.warn-banner{display:none !important;}
  .content-wrapper{margin-left:0!important;background:#fff!important;}
  .report-card{border:none;} body{font-size:10px!important;}
}
</style>

<div class="page-title-bar">
  <i class="fas fa-file-invoice text-primary"></i>
  <h1>Government Contribution Reports</h1>
  <div class="ml-auto no-print">
    <a href="company_settings.php" class="btn btn-sm btn-outline-primary mr-2">
      <i class="fas fa-cog mr-1"></i>Employer Settings
    </a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-print mr-1"></i>Print
    </button>
  </div>
</div>

<div class="card mb-3 no-print">
  <div class="card-body py-3">
    <form method="GET" class="d-flex flex-wrap align-items-center" style="gap:12px;">
      <input type="hidden" name="report" value="<?= htmlspecialchars($activeReport) ?>">
      <div>
        <label class="font-weight-600 mr-2">Month:</label>
        <input type="month" name="month" value="<?= $selectedYM ?>"
               class="form-control form-control-sm d-inline-block" style="width:auto"
               onchange="this.form.submit()">
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <?php foreach (['sss'=>['SSS R-3','fa-shield-alt'],'philhealth'=>['PhilHealth RF-1','fa-heartbeat'],'pagibig'=>['Pag-IBIG MCRF','fa-home'],'bir1601c'=>['BIR 1601-C','fa-receipt']] as $k=>[$lbl,$ico]): ?>
          <a href="?month=<?= $selectedYM ?>&report=<?= $k ?>"
             class="rpt-tab <?= $activeReport===$k ? 'on' : 'off' ?>">
            <i class="fas <?= $ico ?> mr-1"></i><?= $lbl ?>
          </a>
        <?php endforeach; ?>
      </div>
    </form>
  </div>
</div>

<?php if (empty($records)): ?>
<div class="report-card">
  <div class="report-hdr"><h5>No Released Records for <?= htmlspecialchars($monthLabel) ?></h5></div>
  <div class="empty-box">
    <i class="fas fa-file-invoice"></i>
    No released payroll records found for <strong><?= htmlspecialchars($monthLabel) ?></strong>.<br>
    <small>Only released payroll records are included in government contribution reports.</small>
  </div>
</div>
<?php else: ?>

<div class="row mb-3 no-print">
  <?php
  $tiles=[
    ['Employees',count($records),'fas fa-users','#2563eb'],
    ['SSS Total','&#8369;'.number_format($totals['sss_ee']+$totals['sss_er'],2),'fas fa-shield-alt','#0f766e'],
    ['PhilHealth Total','&#8369;'.number_format($totals['ph_ee']+$totals['ph_er'],2),'fas fa-heartbeat','#dc2626'],
    ['Pag-IBIG Total','&#8369;'.number_format($totals['pi_ee']+$totals['pi_er'],2),'fas fa-home','#7c3aed'],
    ['Withholding Tax','&#8369;'.number_format($totals['wtax'],2),'fas fa-receipt','#d97706'],
    ['All Remittances','&#8369;'.number_format($totals['sss_ee']+$totals['sss_er']+$totals['ph_ee']+$totals['ph_er']+$totals['pi_ee']+$totals['pi_er']+$totals['wtax'],2),'fas fa-coins','#0369a1'],
  ];
  foreach($tiles as [$lbl,$val,$ico,$clr]): ?>
  <div class="col-xl-2 col-md-4 col-6 mb-3">
    <div class="stile">
      <i class="<?=$ico?>" style="color:<?=$clr?>;font-size:1.2rem;margin-bottom:5px;display:block;"></i>
      <div class="val"><?= is_int($val)?$val:$val ?></div>
      <div class="lbl"><?=$lbl?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($activeReport==='sss'): ?>
<?php if(!$hasSSS): ?><div class="warn-banner no-print"><i class="fas fa-exclamation-triangle mr-2"></i><strong>SSS Employer ID not configured.</strong> PDF and flat-file will show a placeholder. <a href="company_settings.php">Configure now &rarr;</a></div><?php endif; ?>
<div class="report-card">
  <div class="report-hdr">
    <div>
      <h5><i class="fas fa-shield-alt mr-2"></i>SSS Monthly Collection List &mdash; R-3 Format</h5>
      <div class="ref">Circular 2024-006 &bull; EE 5% / ER 10% &bull; MSC &#8369;5,000&ndash;&#8369;35,000 &bull; <?= htmlspecialchars($monthLabel) ?></div>
    </div>
    <small style="color:#93c5fd;"><?= htmlspecialchars($co['company_name']?:COMPANY_NAME) ?></small>
  </div>
  <div class="export-bar no-print">
    <span class="lbl">Export:</span>
    <a href="gov_reports_pdf_export.php?report=sss&month=<?= $selectedYM ?>" class="ebtn ebtn-pdf" target="_blank"><i class="fas fa-file-pdf"></i> Download PDF (R-3)</a>
    <a href="gov_reports_sss_flatfile.php?month=<?= $selectedYM ?>" class="ebtn ebtn-txt"><i class="fas fa-file-alt"></i> Download Flat-File (.txt)</a>
    <span style="font-size:.75rem;color:#64748b;"><i class="fas fa-info-circle ml-2"></i> .txt flat-file is for My.SSS Employer Portal upload</span>
  </div>
  <div class="table-responsive">
    <table class="rtable">
      <thead><tr><th>#</th><th>Employee No.</th><th>Name</th><th>SSS No.</th><th class="r">MSC (&#8369;)</th><th class="r">EE 5% (&#8369;)</th><th class="r">ER 10% (&#8369;)</th><th class="r">Total (&#8369;)</th></tr></thead>
      <tbody>
        <?php foreach($records as $i=>$r): ?>
        <tr>
          <td><?=$i+1?></td><td><?=htmlspecialchars($r['employee_no'])?></td>
          <td><strong><?=htmlspecialchars($r['employee_name'])?></strong><br><small class="text-muted"><?=htmlspecialchars($r['department'])?></small></td>
          <td><?=htmlspecialchars($r['sss_no']?:'&mdash;')?></td>
          <td class="r"><?=number_format((float)$r['sss_msc'],2)?></td>
          <td class="r"><?=number_format((float)$r['sss_ee'],2)?></td>
          <td class="r"><?=number_format((float)$r['sss_er'],2)?></td>
          <td class="r"><strong><?=number_format((float)$r['sss_ee']+(float)$r['sss_er'],2)?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="4"><strong>TOTALS &mdash; <?=count($records)?> employee(s)</strong></td><td class="r">&mdash;</td><td class="r">&#8369;<?=number_format($totals['sss_ee'],2)?></td><td class="r">&#8369;<?=number_format($totals['sss_er'],2)?></td><td class="r">&#8369;<?=number_format($totals['sss_ee']+$totals['sss_er'],2)?></td></tr></tfoot>
    </table>
  </div>
  <div class="note-box">
    <i class="fas fa-info-circle mr-1 text-info"></i>
    <strong>Total SSS remittance due:</strong> EE &#8369;<?=number_format($totals['sss_ee'],2)?> + ER &#8369;<?=number_format($totals['sss_er'],2)?> = <strong>&#8369;<?=number_format($totals['sss_ee']+$totals['sss_er'],2)?></strong>.
    &bull; Submit via My.SSS employer portal or accredited banks on or before the <strong>last day</strong> of the following month.
    &bull; Employer ID: <strong><?=htmlspecialchars($co['sss_employer_id']?:'Not configured')?></strong>
  </div>
</div>

<?php elseif($activeReport==='philhealth'): ?>
<?php if(!$hasPH): ?><div class="warn-banner no-print"><i class="fas fa-exclamation-triangle mr-2"></i><strong>PhilHealth Employer Number not configured.</strong> <a href="company_settings.php">Configure now &rarr;</a></div><?php endif; ?>
<div class="report-card">
  <div class="report-hdr">
    <div>
      <h5><i class="fas fa-heartbeat mr-2"></i>PhilHealth Monthly Remittance Form &mdash; RF-1 Format</h5>
      <div class="ref">PA2025-0002 &bull; 5% premium &bull; EE/ER 2.5% each &bull; Ceiling &#8369;100,000 &bull; <?=htmlspecialchars($monthLabel)?></div>
    </div>
    <small style="color:#93c5fd;"><?=htmlspecialchars($co['company_name']?:COMPANY_NAME)?></small>
  </div>
  <div class="export-bar no-print">
    <span class="lbl">Export:</span>
    <a href="gov_reports_pdf_export.php?report=philhealth&month=<?=$selectedYM?>" class="ebtn ebtn-pdf" target="_blank"><i class="fas fa-file-pdf"></i> Download PDF (RF-1)</a>
  </div>
  <div class="table-responsive">
    <table class="rtable">
      <thead><tr><th>#</th><th>Employee No.</th><th>Name</th><th>PhilHealth No.</th><th class="r">MBS (&#8369;)</th><th class="r">EE 2.5% (&#8369;)</th><th class="r">ER 2.5% (&#8369;)</th><th class="r">Total (&#8369;)</th></tr></thead>
      <tbody>
        <?php foreach($records as $i=>$r): ?>
        <tr>
          <td><?=$i+1?></td><td><?=htmlspecialchars($r['employee_no'])?></td>
          <td><strong><?=htmlspecialchars($r['employee_name'])?></strong><br><small class="text-muted"><?=htmlspecialchars($r['department'])?></small></td>
          <td><?=htmlspecialchars($r['philhealth_no']?:'&mdash;')?></td>
          <td class="r"><?=number_format((float)$r['ph_mbs'],2)?></td>
          <td class="r"><?=number_format((float)$r['ph_ee'],2)?></td>
          <td class="r"><?=number_format((float)$r['ph_er'],2)?></td>
          <td class="r"><strong><?=number_format((float)$r['ph_ee']+(float)$r['ph_er'],2)?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="4"><strong>TOTALS &mdash; <?=count($records)?> employee(s)</strong></td><td class="r">&mdash;</td><td class="r">&#8369;<?=number_format($totals['ph_ee'],2)?></td><td class="r">&#8369;<?=number_format($totals['ph_er'],2)?></td><td class="r">&#8369;<?=number_format($totals['ph_ee']+$totals['ph_er'],2)?></td></tr></tfoot>
    </table>
  </div>
  <div class="note-box">
    <i class="fas fa-info-circle mr-1 text-info"></i>
    <strong>Total PhilHealth premium due:</strong> &#8369;<?=number_format($totals['ph_ee']+$totals['ph_er'],2)?>.
    &bull; Submit via PhilHealth eRMS or accredited collecting agents.
    &bull; Due: 11th&ndash;15th of the following month based on employer number last digit.
    &bull; Employer No.: <strong><?=htmlspecialchars($co['philhealth_employer_no']?:'Not configured')?></strong>
  </div>
</div>

<?php elseif($activeReport==='pagibig'): ?>
<?php if(!$hasPI): ?><div class="warn-banner no-print"><i class="fas fa-exclamation-triangle mr-2"></i><strong>Pag-IBIG Employer MID not configured.</strong> <a href="company_settings.php">Configure now &rarr;</a></div><?php endif; ?>
<div class="report-card">
  <div class="report-hdr">
    <div>
      <h5><i class="fas fa-home mr-2"></i>Pag-IBIG Monthly Collection &amp; Remittance Form &mdash; MCRF Format</h5>
      <div class="ref">HDMF Circular 460 &bull; MFS &#8369;10,000 &bull; EE/ER max &#8369;200/mo each &bull; <?=htmlspecialchars($monthLabel)?></div>
    </div>
    <small style="color:#93c5fd;"><?=htmlspecialchars($co['company_name']?:COMPANY_NAME)?></small>
  </div>
  <div class="export-bar no-print">
    <span class="lbl">Export:</span>
    <a href="gov_reports_pdf_export.php?report=pagibig&month=<?=$selectedYM?>" class="ebtn ebtn-pdf" target="_blank"><i class="fas fa-file-pdf"></i> Download PDF (MCRF)</a>
  </div>
  <div class="table-responsive">
    <table class="rtable">
      <thead><tr><th>#</th><th>Employee No.</th><th>Name</th><th>Pag-IBIG MID No.</th><th class="r">MFS (&#8369;)</th><th class="r">EE (&#8369;)</th><th class="r">ER (&#8369;)</th><th class="r">Total (&#8369;)</th></tr></thead>
      <tbody>
        <?php foreach($records as $i=>$r): ?>
        <tr>
          <td><?=$i+1?></td><td><?=htmlspecialchars($r['employee_no'])?></td>
          <td><strong><?=htmlspecialchars($r['employee_name'])?></strong><br><small class="text-muted"><?=htmlspecialchars($r['department'])?></small></td>
          <td><?=htmlspecialchars($r['pagibig_no']?:'&mdash;')?></td>
          <td class="r"><?=number_format((float)$r['pi_mfs'],2)?></td>
          <td class="r"><?=number_format((float)$r['pi_ee'],2)?></td>
          <td class="r"><?=number_format((float)$r['pi_er'],2)?></td>
          <td class="r"><strong><?=number_format((float)$r['pi_ee']+(float)$r['pi_er'],2)?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="4"><strong>TOTALS &mdash; <?=count($records)?> employee(s)</strong></td><td class="r">&mdash;</td><td class="r">&#8369;<?=number_format($totals['pi_ee'],2)?></td><td class="r">&#8369;<?=number_format($totals['pi_er'],2)?></td><td class="r">&#8369;<?=number_format($totals['pi_ee']+$totals['pi_er'],2)?></td></tr></tfoot>
    </table>
  </div>
  <div class="note-box">
    <i class="fas fa-info-circle mr-1 text-info"></i>
    <strong>Total Pag-IBIG contribution due:</strong> &#8369;<?=number_format($totals['pi_ee']+$totals['pi_er'],2)?>.
    &bull; Submit via Virtual Pag-IBIG or accredited collecting partners.
    &bull; Due: 10th&ndash;15th of the following month.
    &bull; Employer MID: <strong><?=htmlspecialchars($co['pagibig_employer_mid']?:'Not configured')?></strong>
  </div>
</div>

<?php else: // BIR 1601-C ?>
<?php if(!$hasBIR): ?><div class="warn-banner no-print"><i class="fas fa-exclamation-triangle mr-2"></i><strong>BIR TIN and RDO Code not configured.</strong> Required for 1601-C header. <a href="company_settings.php">Configure now &rarr;</a></div><?php endif; ?>

<div class="bir-summary">
  <div class="row align-items-center">
    <div class="col-md-4 text-center mb-3 mb-md-0">
      <div style="font-size:.82rem;color:#78350f;font-weight:600;margin-bottom:4px;">TOTAL TAX DUE TO BIR</div>
      <div class="big">&#8369;<?=number_format($totals['wtax'],2)?></div>
      <div style="font-size:.75rem;color:#9a3412;margin-top:4px;">BIR Form 1601-C &bull; <?=htmlspecialchars($monthLabel)?></div>
    </div>
    <div class="col-md-8">
      <table class="bir-meta">
        <tr><td class="lbl">Employer:</td><td><?=htmlspecialchars($co['company_name']?:COMPANY_NAME)?></td></tr>
        <tr><td class="lbl">BIR TIN:</td><td><strong><?=htmlspecialchars($co['bir_tin']?:'[ Not configured ]')?></strong></td></tr>
        <tr><td class="lbl">RDO Code:</td><td><?=htmlspecialchars($co['bir_rdo_code']?:'[ Not configured ]')?></td></tr>
        <tr><td class="lbl">Return Period:</td><td><?=htmlspecialchars($monthLabel)?></td></tr>
        <tr><td class="lbl">Quarter / Month:</td><td><?=$birQuarter?> &mdash; Month <?=$birMonthInQtr?> of quarter</td></tr>
        <tr><td class="lbl">No. of Employees:</td><td><?=count($records)?></td></tr>
      </table>
    </div>
  </div>
</div>

<div class="report-card">
  <div class="report-hdr">
    <div>
      <h5><i class="fas fa-receipt mr-2"></i>BIR Form 1601-C &mdash; Monthly Remittance Return of Income Taxes Withheld on Compensation</h5>
      <div class="ref">TRAIN Law RR 11-2018 / RR 13-2023 &bull; NIRC Sec. 79 &bull; <?=htmlspecialchars($monthLabel)?></div>
    </div>
    <small style="color:#93c5fd;"><?=htmlspecialchars($co['company_name']?:COMPANY_NAME)?></small>
  </div>
  <div class="export-bar no-print">
    <span class="lbl">Export:</span>
    <a href="gov_reports_pdf_export.php?report=bir1601c&month=<?=$selectedYM?>" class="ebtn ebtn-pdf" target="_blank"><i class="fas fa-file-pdf"></i> Download PDF (1601-C Working Doc.)</a>
    <span style="font-size:.75rem;color:#64748b;"><i class="fas fa-info-circle ml-2"></i> Official filing must be done via BIR eBIRForms or EFPS portal</span>
  </div>

  <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;">
    <h6 style="font-weight:700;color:#374151;margin-bottom:12px;"><i class="fas fa-calculator mr-2 text-warning"></i>Part II &mdash; Tax Computation Summary</h6>
    <table style="width:100%;font-size:.85rem;">
      <tr style="border-bottom:1px solid #f1f5f9;">
        <td style="padding:7px 10px;"><strong>9A.</strong> Total Compensation (Gross Pay &mdash; all employees)</td>
        <td style="padding:7px 10px;text-align:right;font-weight:600;">&#8369;<?=number_format($totals['total_gross'],2)?></td>
      </tr>
      <tr style="border-bottom:1px solid #f1f5f9;">
        <td style="padding:7px 10px;"><strong>9B.</strong> Non-Taxable / Exempt Compensation <small class="text-muted">(SSS EE + PhilHealth EE + Pag-IBIG EE)</small></td>
        <td style="padding:7px 10px;text-align:right;font-weight:600;">&#8369;<?=number_format($totals['sss_ee']+$totals['ph_ee']+$totals['pi_ee'],2)?></td>
      </tr>
      <tr style="background:#fff7ed;border-bottom:2px solid #fed7aa;">
        <td style="padding:10px;font-weight:800;font-size:.92rem;color:#9a3412;"><strong>10.</strong> Total Income Tax Withheld on Compensation <small style="font-weight:400;">(Amount to remit to BIR)</small></td>
        <td style="padding:10px;text-align:right;font-weight:800;font-size:1.05rem;color:#9a3412;">&#8369;<?=number_format($totals['wtax'],2)?></td>
      </tr>
    </table>
  </div>

  <div style="padding:12px 20px 8px;font-size:.82rem;font-weight:700;color:#374151;border-bottom:1px solid #f1f5f9;">
    <i class="fas fa-list mr-1 text-muted"></i>Schedule &mdash; Employee Compensation &amp; Tax Withheld Breakdown
  </div>
  <div class="table-responsive">
    <table class="rtable">
      <thead>
        <tr><th>#</th><th>Employee No.</th><th>Name</th><th>TIN</th>
        <th class="r">Gross Compensation (&#8369;)</th>
        <th class="r">Gov. Ded. EE (&#8369;)<br><small class="text-muted">SSS+PH+PI</small></th>
        <th class="r">Taxable Income (&#8369;)</th>
        <th class="r">Tax Withheld (&#8369;)</th></tr>
      </thead>
      <tbody>
        <?php foreach($records as $i=>$r):
          $govEe=(float)$r['sss_ee']+(float)$r['ph_ee']+(float)$r['pi_ee'];
          $taxable=max(0,(float)$r['total_gross']-$govEe);
        ?>
        <tr>
          <td><?=$i+1?></td><td><?=htmlspecialchars($r['employee_no'])?></td>
          <td><strong><?=htmlspecialchars($r['employee_name'])?></strong><br><small class="text-muted"><?=htmlspecialchars($r['department'])?></small></td>
          <td><?=htmlspecialchars($r['tin_no']?:'&mdash;')?></td>
          <td class="r"><?=number_format((float)$r['total_gross'],2)?></td>
          <td class="r"><?=number_format($govEe,2)?></td>
          <td class="r"><?=number_format($taxable,2)?></td>
          <td class="r"><strong><?=number_format((float)$r['wtax'],2)?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="4"><strong>TOTALS &mdash; <?=count($records)?> employee(s)</strong></td>
        <td class="r">&#8369;<?=number_format($totals['total_gross'],2)?></td>
        <td class="r">&#8369;<?=number_format($totals['sss_ee']+$totals['ph_ee']+$totals['pi_ee'],2)?></td>
        <td class="r">&mdash;</td>
        <td class="r">&#8369;<?=number_format($totals['wtax'],2)?></td></tr>
      </tfoot>
    </table>
  </div>
  <div class="note-box">
    <i class="fas fa-info-circle mr-1 text-warning"></i>
    <strong>Filing deadline:</strong> Non-eFPS: <strong>10th of the following month</strong>. eFPS: varies by group (5th&ndash;14th).
    &bull; File via <strong>BIR eBIRForms</strong> (offline package) or <strong>EFPS</strong>. Pay via AABs, GCash, or PayMaya.
    &bull; Penalty: 25% surcharge + 12% p.a. interest for late filing (NIRC Sec. 248&ndash;249).
    &bull; <em>This is a working document — official return must be filed using BIR-prescribed eBIRForms.</em>
  </div>
</div>
<?php endif; ?>

<div class="row mt-2 mb-4">
  <div class="col-md-6 mb-3">
    <div class="sig-block"><strong>Prepared by:</strong><div class="sig-line">HR / Payroll Officer &mdash; Signature over Printed Name / Date</div></div>
  </div>
  <div class="col-md-6 mb-3">
    <div class="sig-block"><strong>Certified correct and approved by:</strong><div class="sig-line">Authorized Signatory &mdash; Signature over Printed Name &amp; Designation / Date</div></div>
  </div>
</div>
<p class="text-muted no-print" style="font-size:.77rem;">
  <i class="fas fa-info-circle mr-1"></i>
  Generated by <?=htmlspecialchars(APP_NAME)?> on <?=date('F j, Y \a\t h:i A')?>.
  Only <strong>released</strong> payroll records are included.
</p>
<?php endif; ?>
<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
