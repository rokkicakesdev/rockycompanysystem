<?php
// app/views/emails/payslip_released.php
// ─────────────────────────────────────────────────────────────────────────────
//  Email template: Payslip released notification.
//  Called by: app/views/admin/payroll.php → EmailTemplate::render()
//
//  Available variables via $vars[]:
//    company      string  Company name
//    name         string  Employee full name
//    periodLabel  string  Formatted period (e.g. 'January 2026 (1st–15th)')
//    netPay       string  Formatted net pay (e.g. '₱20,653.41')
//    grossPay     string  Formatted gross pay
//    totalDed     string  Formatted total deductions
// ─────────────────────────────────────────────────────────────────────────────

$company     = htmlspecialchars($vars['company']     ?? 'Rocky HRIS');
$name        = htmlspecialchars($vars['name']        ?? 'Employee');
$periodLabel = htmlspecialchars($vars['periodLabel'] ?? '');
$netPay      = htmlspecialchars($vars['netPay']      ?? '');
$grossPay    = htmlspecialchars($vars['grossPay']    ?? '');
$totalDed    = htmlspecialchars($vars['totalDed']    ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;">

  <div style="max-width:560px;margin:32px auto;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);">

    <!-- Header -->
    <div style="background:#1a2744;padding:24px 32px;text-align:center;">
      <h2 style="color:#ffffff;margin:0;font-size:1.3rem;"><?= $company ?></h2>
      <p style="color:#93c5fd;margin:4px 0 0;font-size:.82rem;">HRIS + Payroll System</p>
    </div>

    <!-- Body -->
    <div style="padding:28px 32px;">

      <!-- Greeting -->
      <p style="color:#475569;line-height:1.6;margin:0 0 20px;">
        Hi <strong><?= $name ?></strong>,<br><br>
        Your payslip for <strong><?= $periodLabel ?></strong> has been released
        and is now available in the HRIS portal.
      </p>

      <!-- Net Pay banner -->
      <div style="background:#f0fdf4;border-radius:8px;padding:20px;text-align:center;margin-bottom:20px;">
        <p style="color:#64748b;font-size:.82rem;margin:0 0 4px;">NET PAY</p>
        <h2 style="color:#16a34a;margin:0;font-size:2rem;font-weight:800;"><?= $netPay ?></h2>
        <p style="color:#64748b;font-size:.78rem;margin:8px 0 0;"><?= $periodLabel ?></p>
      </div>

      <!-- Breakdown table -->
      <table style="width:100%;border-collapse:collapse;font-size:.88rem;margin-bottom:20px;">
        <tr style="background:#f8fafc;">
          <td style="padding:8px 12px;color:#64748b;">Gross Pay</td>
          <td style="padding:8px 12px;text-align:right;font-weight:600;"><?= $grossPay ?></td>
        </tr>
        <tr>
          <td style="padding:8px 12px;color:#64748b;">Total Deductions</td>
          <td style="padding:8px 12px;text-align:right;color:#dc2626;"><?= $totalDed ?></td>
        </tr>
        <tr style="background:#f8fafc;border-top:2px solid #e2e8f0;">
          <td style="padding:8px 12px;font-weight:700;">Net Pay</td>
          <td style="padding:8px 12px;text-align:right;font-weight:800;color:#16a34a;"><?= $netPay ?></td>
        </tr>
      </table>

      <p style="color:#64748b;font-size:.85rem;margin:0;">
        Log in to the HRIS portal to view your full payslip and download a PDF copy.
      </p>

    </div>

    <!-- Footer -->
    <div style="background:#f8fafc;padding:14px 32px;text-align:center;border-top:1px solid #e2e8f0;">
      <p style="color:#94a3b8;font-size:.76rem;margin:0;">
        &copy; <?= $company ?> &mdash; Automated notification, do not reply.
      </p>
    </div>

  </div>

</body>
</html>
