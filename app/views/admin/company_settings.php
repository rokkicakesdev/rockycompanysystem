<?php
// app/views/admin/company_settings.php
// ─────────────────────────────────────────────────────────────────────────────
//  Admin page to configure company employer registration numbers used on all
//  government remittance reports (SSS R-3, PhilHealth RF-1, Pag-IBIG MCRF,
//  BIR 1601-C).
// ─────────────────────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/Model.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_ADMIN) {
    header('Location: ' . BASE_URL . '/index.php?error=access_denied'); exit;
}

$pageTitle  = 'Company Settings';
$breadcrumb = 'Company Settings';
$activeMenu = 'company_settings';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$msg = '';

// ── Handle save ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_company'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token. Please refresh.</div>";
    } else {
        $keys = [
            'company_name', 'company_address', 'company_zip',
            'sss_employer_id', 'sss_branch_code',
            'philhealth_employer_no',
            'pagibig_employer_mid',
            'bir_tin', 'bir_rdo_code',
        ];
        $data = [];
        foreach ($keys as $k) {
            $data[$k] = trim($_POST[$k] ?? '');
        }
        $saved = Model::saveCompanySettings($data);
        Model::log($_SESSION['user_id'], 'UPDATE_COMPANY_SETTINGS',
            "Updated {$saved} company settings (employer registration numbers).");
        $msg = "<div class='alert alert-success alert-auto-dismiss'>
            <i class='fas fa-check-circle mr-2'></i>
            <strong>{$saved}</strong> company settings saved successfully.</div>";
    }
}

// ── Load current values ───────────────────────────────────────────────────────
$s = Model::getAllCompanySettings();
$val = fn(string $key, string $fallback = ''): string =>
    htmlspecialchars($s[$key] ?? $fallback, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . '/../layouts/admin_header.php';
?>

<style>
.cs-card { background:#fff; border:1.5px solid #e2e8f0; border-radius:10px; margin-bottom:24px; overflow:hidden; }
.cs-hdr  { background:#1a2744; color:#fff; padding:14px 20px; display:flex; align-items:center; gap:10px; }
.cs-hdr h5 { margin:0; font-size:.97rem; font-weight:700; }
.cs-hdr .sub { font-size:.73rem; color:#93c5fd; }
.cs-body { padding:20px 24px; }
.field-group { margin-bottom:18px; }
.field-group label { font-weight:600; font-size:.84rem; color:#374151; display:block; margin-bottom:5px; }
.field-group .hint { font-size:.75rem; color:#64748b; margin-top:3px; }
.field-group input[type=text] {
    width:100%; padding:8px 12px;
    border:1.5px solid #cbd5e1; border-radius:6px;
    font-size:.9rem; color:#1e293b; background:#fff;
    transition: border-color .15s;
}
.field-group input[type=text]:focus {
    outline:none; border-color:#3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,.12);
}
.field-group input.required-field:invalid { border-color:#f87171; }
.badge-info { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; border-radius:5px; padding:3px 9px; font-size:.73rem; font-weight:600; }
.badge-warn { background:#fffbeb; color:#92400e; border:1px solid #fde68a; border-radius:5px; padding:3px 9px; font-size:.73rem; font-weight:600; }
.save-bar { background:#f8fafc; border-top:1px solid #e2e8f0; padding:14px 24px; display:flex; align-items:center; gap:12px; }
</style>

<div class="page-title-bar">
  <i class="fas fa-building text-primary"></i>
  <h1>Company Settings</h1>
  <small class="text-muted ml-2">Employer Registration Numbers for Government Reports</small>
</div>

<?= $msg ?>

<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
  <input type="hidden" name="save_company" value="1">

  <!-- Company Info -->
  <div class="cs-card">
    <div class="cs-hdr">
      <i class="fas fa-building fa-lg"></i>
      <div>
        <h5>Company Information</h5>
        <div class="sub">Used as the employer header on all government remittance reports</div>
      </div>
    </div>
    <div class="cs-body">
      <div class="row">
        <div class="col-md-8">
          <div class="field-group">
            <label>Company / Employer Name <span class="badge-warn">Required for reports</span></label>
            <input type="text" name="company_name"
                   value="<?= $val('company_name', defined('COMPANY_NAME') ? COMPANY_NAME : '') ?>"
                   placeholder="e.g., Rocky Company, Inc.">
            <div class="hint">Appears on SSS R-3, PhilHealth RF-1, Pag-IBIG MCRF, and BIR 1601-C headers.</div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="field-group">
            <label>ZIP Code <span class="badge-info">BIR 1601-C</span></label>
            <input type="text" name="company_zip"
                   value="<?= $val('company_zip') ?>"
                   placeholder="e.g., 1700" maxlength="10">
          </div>
        </div>
        <div class="col-md-12">
          <div class="field-group">
            <label>Registered Business Address</label>
            <input type="text" name="company_address"
                   value="<?= $val('company_address', defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : '') ?>"
                   placeholder="e.g., 123 Main Street, Parañaque City, Metro Manila">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- SSS -->
  <div class="cs-card">
    <div class="cs-hdr" style="background:#0f5132;">
      <i class="fas fa-shield-alt fa-lg"></i>
      <div>
        <h5>SSS — Social Security System</h5>
        <div class="sub">Required for SSS R-3 Monthly Collection List &amp; Flat-File Upload</div>
      </div>
    </div>
    <div class="cs-body">
      <div class="row">
        <div class="col-md-6">
          <div class="field-group">
            <label>SSS Employer ID Number <span class="badge-warn">Required</span></label>
            <input type="text" name="sss_employer_id"
                   value="<?= $val('sss_employer_id') ?>"
                   placeholder="e.g., 03-1234567-8" maxlength="30">
            <div class="hint">
              10-digit SSS Employer Number assigned upon employer registration.
              Used on the R-3 form header and flat-file upload (My.SSS portal).
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="field-group">
            <label>SSS Branch Code <span class="badge-info">Optional</span></label>
            <input type="text" name="sss_branch_code"
                   value="<?= $val('sss_branch_code') ?>"
                   placeholder="e.g., 001" maxlength="5">
            <div class="hint">3-digit branch code (if required by your SSS branch).</div>
          </div>
        </div>
      </div>
      <div class="alert alert-light border" style="font-size:.8rem;">
        <i class="fas fa-info-circle text-primary mr-1"></i>
        <strong>Filing deadline:</strong> Last day of the month following the applicable month.
        Remit via <a href="https://www.sss.gov.ph" target="_blank" rel="noopener">My.SSS Employer Portal</a>
        or SSS-accredited banks. Penalty: 2% per month for late payment.
      </div>
    </div>
  </div>

  <!-- PhilHealth -->
  <div class="cs-card">
    <div class="cs-hdr" style="background:#7f1d1d;">
      <i class="fas fa-heartbeat fa-lg"></i>
      <div>
        <h5>PhilHealth — Philippine Health Insurance Corporation</h5>
        <div class="sub">Required for PhilHealth RF-1 Monthly Remittance Form</div>
      </div>
    </div>
    <div class="cs-body">
      <div class="row">
        <div class="col-md-6">
          <div class="field-group">
            <label>PhilHealth Employer Number <span class="badge-warn">Required</span></label>
            <input type="text" name="philhealth_employer_no"
                   value="<?= $val('philhealth_employer_no') ?>"
                   placeholder="e.g., 12-345678901-2" maxlength="30">
            <div class="hint">
              PhilHealth-assigned employer number. Found on your PhilHealth Employer Certificate
              or eRMS account. The last digit determines your remittance due-date schedule.
            </div>
          </div>
        </div>
      </div>
      <div class="alert alert-light border" style="font-size:.8rem;">
        <i class="fas fa-info-circle text-danger mr-1"></i>
        <strong>Filing deadline:</strong> 11th–15th of the following month based on last digit of your employer number.
        Rate: 5% of MBS (PA2025-0002) — EE 2.5% / ER 2.5%. Ceiling: ₱100,000 MBS.
      </div>
    </div>
  </div>

  <!-- Pag-IBIG -->
  <div class="cs-card">
    <div class="cs-hdr" style="background:#3b0764;">
      <i class="fas fa-home fa-lg"></i>
      <div>
        <h5>Pag-IBIG Fund — Home Development Mutual Fund</h5>
        <div class="sub">Required for Pag-IBIG MCRF Monthly Contribution Remittance Form</div>
      </div>
    </div>
    <div class="cs-body">
      <div class="row">
        <div class="col-md-6">
          <div class="field-group">
            <label>Pag-IBIG Employer MID Number <span class="badge-warn">Required</span></label>
            <input type="text" name="pagibig_employer_mid"
                   value="<?= $val('pagibig_employer_mid') ?>"
                   placeholder="e.g., 1234-5678-9012" maxlength="30">
            <div class="hint">
              HDMF Member ID (MID) of the employer. Found on your Virtual Pag-IBIG account
              or employer registration certificate.
            </div>
          </div>
        </div>
      </div>
      <div class="alert alert-light border" style="font-size:.8rem;">
        <i class="fas fa-info-circle text-purple mr-1"></i>
        <strong>Filing deadline:</strong> 10th–15th of the following month per HDMF guidelines.
        Contribution: EE max ₱200 / ER max ₱200 per month (HDMF Circular 460).
      </div>
    </div>
  </div>

  <!-- BIR -->
  <div class="cs-card">
    <div class="cs-hdr" style="background:#78350f;">
      <i class="fas fa-receipt fa-lg"></i>
      <div>
        <h5>BIR — Bureau of Internal Revenue</h5>
        <div class="sub">Required for BIR Form 1601-C Monthly Withholding Tax Remittance</div>
      </div>
    </div>
    <div class="cs-body">
      <div class="row">
        <div class="col-md-5">
          <div class="field-group">
            <label>Company BIR TIN <span class="badge-warn">Required</span></label>
            <input type="text" name="bir_tin"
                   value="<?= $val('bir_tin') ?>"
                   placeholder="e.g., 123-456-789-000" maxlength="20">
            <div class="hint">
              12-digit Tax Identification Number (xxx-xxx-xxx-000 format for corporations).
              Found on your BIR Certificate of Registration (Form 2303).
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="field-group">
            <label>BIR RDO Code <span class="badge-warn">Required</span></label>
            <input type="text" name="bir_rdo_code"
                   value="<?= $val('bir_rdo_code') ?>"
                   placeholder="e.g., 044" maxlength="5">
            <div class="hint">
              3-digit Revenue District Office code where the company is registered.
              Also on your BIR Form 2303.
            </div>
          </div>
        </div>
      </div>
      <div class="alert alert-light border" style="font-size:.8rem;">
        <i class="fas fa-info-circle text-warning mr-1"></i>
        <strong>Filing deadline:</strong> 10th of the following month (non-eFPS filers).
        File via BIR eBIRForms or EFPS. Tax rate: TRAIN Law brackets (RR 13-2023).
        Penalty for late filing: 25% surcharge + 12% annual interest (NIRC Sec. 248-249).
      </div>
    </div>
  </div>

  <!-- Save button -->
  <div class="cs-card">
    <div class="save-bar">
      <button type="submit" name="save_company" value="1" class="btn btn-primary px-4">
        <i class="fas fa-save mr-2"></i>Save Company Settings
      </button>
      <a href="gov_reports.php" class="btn btn-outline-secondary">
        <i class="fas fa-file-invoice mr-1"></i>Back to Gov. Reports
      </a>
      <span class="text-muted ml-2" style="font-size:.8rem;">
        <i class="fas fa-info-circle mr-1"></i>
        These settings are stored in the database and used on all PDF/flat-file report exports.
      </span>
    </div>
  </div>

</form>

<?php require_once __DIR__ . '/../layouts/admin_footer.php'; ?>
