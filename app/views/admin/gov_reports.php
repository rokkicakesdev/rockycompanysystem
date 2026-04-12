<?php
// app/views/admin/gov_reports.php — SSS R-3, PhilHealth RF-1, Pag-IBIG MCRF
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

$currentYM    = date('Y-m');
$selectedYM   = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : $currentYM;
$selectedYear = (int)substr($selectedYM, 0, 4);
$selectedMonth= (int)substr($selectedYM, 5, 2);
$activeReport = $_GET['report'] ?? 'sss';

// Aggregate both cutoffs per employee — released records only
$db = Database::getInstance();
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
$records = $stmt->fetchAll();

$totals = ['sss_ee'=>0,'sss_er'=>0,'ph_ee'=>0,'ph_er'=>0,'pi_ee'=>0,'pi_er'=>0,'wtax'=>0];
foreach ($records as $r) {
    foreach (['sss_ee','sss_er','ph_ee','ph_er','pi_ee','pi_er','wtax'] as $k) {
        $totals[$k] += (float)$r[$k];
    }
}
$monthLabel = date('F Y', mktime(0,0,0,$selectedMonth,1,$selectedYear));

require_once __DIR__ . '/../layouts/admin_header.php';
?>

<style>
.report-card{background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:24px;}
.report-hdr{background:#1a2744;color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
.report-hdr h5{margin:0;font-size:.98rem;font-weight:700;}
.report-hdr .ref{font-size:.73rem;color:#93c5fd;}
.rtable{width:100%;border-collapse:collapse;font-size:.81rem;}
.rtable thead th{background:#f1f5f9;color:#374151;padding:8px 10px;text-align:left;border-bottom:1.5px solid #cbd5e1;white-space:nowrap;}
.rtable thead th.r{text-align:right;}
.rtable tbody td{padding:7px 10px;border-bottom:1px solid #f1f5f9;color:#1e293b;vertical-align:middle;}
.rtable tbody td.r{text-align:right;font-variant-numeric:tabular-nums;}
.rtable tbody tr:hover{background:#f8fafc;}
.rtable tfoot td{padding:8px 10px;background:#f1f5f9;border-top:2px solid #cbd5e1;font-weight:700;}
.rtable tfoot td.r{text-align:right;color:#1d4ed8;}
.rpt-btn{border:none;border-radius:6px;padding:7px 16px;font-size:.86rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;}
.rpt-btn.on{background:#1a2744;color:#fff;}
.rpt-btn.off{background:#f1f5f9;color:#475569;}
.rpt-btn.off:hover{background:#e2e8f0;}
.stile{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;text-align:center;}
.stile .val{font-size:1.15rem;font-weight:800;color:#1a2744;}
.stile .lbl{font-size:.73rem;color:#64748b;margin-top:2px;}
.empty-box{text-align:center;padding:40px;color:#94a3b8;}
.empty-box i{font-size:2.5rem;display:block;margin-bottom:10px;}
.note-box{padding:12px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:.77rem;color:#64748b;}
@media print{
  .no-print,.main-header,.main-sidebar,.main-footer,.content-header,.breadcrumb,.page-title-bar{display:none !important;}
  .content-wrapper{margin-left:0!important;background:#fff!important;}
  .report-card{border:none;}
  body{font-size:10px!important;}
}
</style>

<div class="page-title-bar">
  <i class="fas fa-file-invoice text-primary"></i>
  <h1>Government Contribution Reports</h1>
  <div class="ml-auto no-print">
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-print mr-1"></i>Print
    </button>
  </div>
</div>

<!-- Controls -->
<div class="card mb-3 no-print">
  <div class="card-body py-3">
    <form method="GET" class="d-flex flex-wrap align-items-center gap-3">
      <input type="hidden" name="report" value="<?= htmlspecialchars($activeReport) ?>">
      <div>
        <label class="font-weight-600 mr-2">Month:</label>
        <input type="month" name="month" value="<?= $selectedYM ?>"
               class="form-control form-control-sm d-inline-block" style="width:auto"
               onchange="this.form.submit()">
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <?php foreach (['sss'=>'SSS R-3','philhealth'=>'PhilHealth RF-1','pagibig'=>'Pag-IBIG MCRF'] as $k=>$lbl): ?>
          <a href="?month=<?= $selectedYM ?>&report=<?= $k ?>"
             class="rpt-btn <?= $activeReport===$k ? 'on' : 'off' ?>">
            <?= $lbl ?>
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

<!-- Summary tiles -->
<div class="row mb-3">
  <?php
  $tiles = [
    ['Employees', count($records), 'fas fa-users', '#2563eb'],
    ['SSS (EE+ER)', number_format($totals['sss_ee']+$totals['sss_er'],2), 'fas fa-shield-alt', '#0f766e'],
    ['PhilHealth (EE+ER)', number_format($totals['ph_ee']+$totals['ph_er'],2), 'fas fa-heartbeat', '#dc2626'],
    ['Pag-IBIG (EE+ER)', number_format($totals['pi_ee']+$totals['pi_er'],2), 'fas fa-home', '#7c3aed'],
    ['Withholding Tax', number_format($totals['wtax'],2), 'fas fa-receipt', '#d97706'],
  ];
  foreach ($tiles as [$lbl,$val,$ico,$clr]): ?>
  <div class="col-xl col-md-4 col-6 mb-3">
    <div class="stile">
      <i class="<?= $ico ?>" style="color:<?= $clr ?>;font-size:1.2rem;margin-bottom:5px;display:block;"></i>
      <div class="val"><?= is_int($val) ? $val : '&#8369;'.$val ?></div>
      <div class="lbl"><?= $lbl ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($activeReport === 'sss'): ?>
<!-- SSS R-3 -->
<div class="report-card">
  <div class="report-hdr">
    <div>
      <h5><i class="fas fa-shield-alt mr-2"></i>SSS Monthly Collection List — R-3 Format</h5>
      <div class="ref">Circular 2024-006 &bull; EE 5% / ER 10% &bull; MSC &#8369;5,000–&#8369;35,000 &bull; <?= htmlspecialchars($monthLabel) ?></div>
    </div>
    <small style="color:#93c5fd;"><?= htmlspecialchars(COMPANY_NAME) ?></small>
  </div>
  <div class="table-responsive">
    <table class="rtable">
      <thead>
        <tr>
          <th>#</th><th>Employee No.</th><th>Name</th><th>SSS No.</th>
          <th class="r">MSC (&#8369;)</th><th class="r">EE 5% (&#8369;)</th>
          <th class="r">ER 10% (&#8369;)</th><th class="r">Total (&#8369;)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $i=>$r): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($r['employee_no']) ?></td>
          <td><strong><?= htmlspecialchars($r['employee_name']) ?></strong><br>
              <small class="text-muted"><?= htmlspecialchars($r['department']) ?></small></td>
          <td><?= htmlspecialchars($r['sss_no'] ?? '—') ?></td>
          <td class="r"><?= number_format((float)$r['sss_msc'],2) ?></td>
          <td class="r"><?= number_format((float)$r['sss_ee'],2) ?></td>
          <td class="r"><?= number_format((float)$r['sss_er'],2) ?></td>
          <td class="r"><strong><?= number_format((float)$r['sss_ee']+(float)$r['sss_er'],2) ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4"><strong>TOTALS — <?= count($records) ?> employee(s)</strong></td>
          <td class="r">—</td>
          <td class="r">&#8369;<?= number_format($totals['sss_ee'],2) ?></td>
          <td class="r">&#8369;<?= number_format($totals['sss_er'],2) ?></td>
          <td class="r">&#8369;<?= number_format($totals['sss_ee']+$totals['sss_er'],2) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <div class="note-box">
    <i class="fas fa-info-circle mr-1 text-info"></i>
    <strong>Total SSS remittance due:</strong>
    EE &#8369;<?= number_format($totals['sss_ee'],2) ?> + ER &#8369;<?= number_format($totals['sss_er'],2) ?>
    = <strong>&#8369;<?= number_format($totals['sss_ee']+$totals['sss_er'],2) ?></strong>.
    Submit via My.SSS employer portal or accredited banks on or before the 31st of the following month.
  </div>
</div>

<?php elseif ($activeReport === 'philhealth'): ?>
<!-- PhilHealth RF-1 -->
<div class="report-card">
  <div class="report-hdr">
    <div>
      <h5><i class="fas fa-heartbeat mr-2"></i>PhilHealth Monthly Remittance Form — RF-1 Format</h5>
      <div class="ref">PA2025-0002 &bull; 5% premium &bull; EE/ER 2.5% each &bull; Ceiling &#8369;100,000 &bull; <?= htmlspecialchars($monthLabel) ?></div>
    </div>
    <small style="color:#93c5fd;"><?= htmlspecialchars(COMPANY_NAME) ?></small>
  </div>
  <div class="table-responsive">
    <table class="rtable">
      <thead>
        <tr>
          <th>#</th><th>Employee No.</th><th>Name</th><th>PhilHealth No.</th>
          <th class="r">MBS (&#8369;)</th><th class="r">EE 2.5% (&#8369;)</th>
          <th class="r">ER 2.5% (&#8369;)</th><th class="r">Total (&#8369;)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $i=>$r): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($r['employee_no']) ?></td>
          <td><strong><?= htmlspecialchars($r['employee_name']) ?></strong><br>
              <small class="text-muted"><?= htmlspecialchars($r['department']) ?></small></td>
          <td><?= htmlspecialchars($r['philhealth_no'] ?? '—') ?></td>
          <td class="r"><?= number_format((float)$r['ph_mbs'],2) ?></td>
          <td class="r"><?= number_format((float)$r['ph_ee'],2) ?></td>
          <td class="r"><?= number_format((float)$r['ph_er'],2) ?></td>
          <td class="r"><strong><?= number_format((float)$r['ph_ee']+(float)$r['ph_er'],2) ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4"><strong>TOTALS — <?= count($records) ?> employee(s)</strong></td>
          <td class="r">—</td>
          <td class="r">&#8369;<?= number_format($totals['ph_ee'],2) ?></td>
          <td class="r">&#8369;<?= number_format($totals['ph_er'],2) ?></td>
          <td class="r">&#8369;<?= number_format($totals['ph_ee']+$totals['ph_er'],2) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <div class="note-box">
    <i class="fas fa-info-circle mr-1 text-info"></i>
    <strong>Total PhilHealth due:</strong> &#8369;<?= number_format($totals['ph_ee']+$totals['ph_er'],2) ?>.
    Submit RF-1 and remit via PhilHealth ER portal or accredited collecting agents on or before
    the 11th–15th of the following month based on your PhilHealth Employer Number last digit.
  </div>
</div>

<?php elseif ($activeReport === 'pagibig'): ?>
<!-- Pag-IBIG MCRF -->
<div class="report-card">
  <div class="report-hdr">
    <div>
      <h5><i class="fas fa-home mr-2"></i>Pag-IBIG Monthly Collection &amp; Remittance Form — MCRF Format</h5>
      <div class="ref">HDMF Circular 460 &bull; MFS &#8369;10,000 &bull; EE/ER max &#8369;200/mo each &bull; <?= htmlspecialchars($monthLabel) ?></div>
    </div>
    <small style="color:#93c5fd;"><?= htmlspecialchars(COMPANY_NAME) ?></small>
  </div>
  <div class="table-responsive">
    <table class="rtable">
      <thead>
        <tr>
          <th>#</th><th>Employee No.</th><th>Name</th><th>Pag-IBIG No.</th>
          <th class="r">MFS (&#8369;)</th><th class="r">EE (&#8369;)</th>
          <th class="r">ER (&#8369;)</th><th class="r">Total (&#8369;)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $i=>$r): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($r['employee_no']) ?></td>
          <td><strong><?= htmlspecialchars($r['employee_name']) ?></strong><br>
              <small class="text-muted"><?= htmlspecialchars($r['department']) ?></small></td>
          <td><?= htmlspecialchars($r['pagibig_no'] ?? '—') ?></td>
          <td class="r"><?= number_format((float)$r['pi_mfs'],2) ?></td>
          <td class="r"><?= number_format((float)$r['pi_ee'],2) ?></td>
          <td class="r"><?= number_format((float)$r['pi_er'],2) ?></td>
          <td class="r"><strong><?= number_format((float)$r['pi_ee']+(float)$r['pi_er'],2) ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4"><strong>TOTALS — <?= count($records) ?> employee(s)</strong></td>
          <td class="r">—</td>
          <td class="r">&#8369;<?= number_format($totals['pi_ee'],2) ?></td>
          <td class="r">&#8369;<?= number_format($totals['pi_er'],2) ?></td>
          <td class="r">&#8369;<?= number_format($totals['pi_ee']+$totals['pi_er'],2) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <div class="note-box">
    <i class="fas fa-info-circle mr-1 text-info"></i>
    <strong>Total Pag-IBIG due:</strong> &#8369;<?= number_format($totals['pi_ee']+$totals['pi_er'],2) ?>.
    Submit MCRF and remit via Virtual Pag-IBIG or accredited collecting partners
    on or before the 10th–15th of the following month per HDMF guidelines.
  </div>
</div>
<?php endif; ?>

<!-- Certification -->
<div class="row mt-2 mb-4">
  <div class="col-md-6 mb-3">
    <div style="border:1px solid #e2e8f0;border-radius:8px;padding:16px;font-size:.82rem;">
      <strong>Prepared by:</strong>
      <div style="border-top:1px solid #cbd5e1;margin-top:40px;padding-top:6px;color:#64748b;">
        HR / Payroll Officer — Signature over Printed Name / Date
      </div>
    </div>
  </div>
  <div class="col-md-6 mb-3">
    <div style="border:1px solid #e2e8f0;border-radius:8px;padding:16px;font-size:.82rem;">
      <strong>Approved by:</strong>
      <div style="border-top:1px solid #cbd5e1;margin-top:40px;padding-top:6px;color:#64748b;">
        Authorized Signatory — Signature over Printed Name / Date
      </div>
    </div>
  </div>
</div>
<p class="text-muted no-print" style="font-size:.77rem;">
  <i class="fas fa-info-circle mr-1"></i>
  Generated by <?= htmlspecialchars(APP_NAME) ?> on <?= date('F j, Y \a\t h:i A') ?>.
  Only <strong>released</strong> payroll records are included.
</p>

<?php endif; ?>
<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
