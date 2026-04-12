<?php
// app/views/emails/leave_notification.php
// ─────────────────────────────────────────────────────────────────────────────
//  Email template: Leave request approved / rejected notification.
//  Called by: app/views/admin/leave.php → EmailTemplate::render()
//
//  Available variables via $vars[]:
//    company      string  Company name
//    name         string  Employee full name
//    statusWord   string  'Approved' | 'Rejected'
//    statusColor  string  CSS hex color (#16a34a | #dc2626)
//    statusBg     string  CSS background color
//    statusIcon   string  Emoji ✅ | ❌
//    leaveType    string  Human-readable leave type (e.g. 'Sick Leave')
//    dateFrom     string  Formatted start date (e.g. 'Jan 5, 2026')
//    dateTo       string  Formatted end date
//    days         mixed   Number of days applied
//    notes        string  Admin review notes (may be empty)
// ─────────────────────────────────────────────────────────────────────────────

$company     = htmlspecialchars($vars['company']    ?? 'Rocky HRIS');
$name        = htmlspecialchars($vars['name']       ?? 'Employee');
$statusWord  = htmlspecialchars($vars['statusWord'] ?? 'Updated');
$statusColor = $vars['statusColor'] ?? '#475569';
$statusBg    = $vars['statusBg']    ?? '#f8fafc';
$statusIcon  = $vars['statusIcon']  ?? '';
$leaveType   = htmlspecialchars($vars['leaveType']  ?? '');
$dateFrom    = htmlspecialchars($vars['dateFrom']   ?? '');
$dateTo      = htmlspecialchars($vars['dateTo']     ?? '');
$days        = htmlspecialchars((string)($vars['days'] ?? ''));
$notes       = $vars['notes'] ?? '';
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

      <!-- Status badge -->
      <div style="background:<?= $statusBg ?>;border-radius:8px;padding:16px;text-align:center;margin-bottom:20px;">
        <?php if ($statusIcon): ?>
          <div style="font-size:2rem;line-height:1;margin-bottom:8px;"><?= $statusIcon ?></div>
        <?php endif; ?>
        <h3 style="color:<?= $statusColor ?>;margin:0;font-size:1.15rem;">
          Leave Request <?= $statusWord ?>
        </h3>
      </div>

      <!-- Greeting -->
      <p style="color:#475569;line-height:1.6;margin:0 0 16px;">
        Hi <strong><?= $name ?></strong>,<br><br>
        Your leave request has been
        <strong style="color:<?= $statusColor ?>;"><?= $statusWord ?></strong>.
      </p>

      <!-- Details table -->
      <table style="width:100%;border-collapse:collapse;font-size:.9rem;margin-bottom:16px;">
        <tr style="background:#f8fafc;">
          <td style="padding:8px 12px;color:#64748b;width:40%;">Leave Type</td>
          <td style="padding:8px 12px;font-weight:600;"><?= $leaveType ?></td>
        </tr>
        <tr>
          <td style="padding:8px 12px;color:#64748b;">Period</td>
          <td style="padding:8px 12px;"><?= $dateFrom ?> &ndash; <?= $dateTo ?></td>
        </tr>
        <tr style="background:#f8fafc;">
          <td style="padding:8px 12px;color:#64748b;">Days Applied</td>
          <td style="padding:8px 12px;font-weight:600;"><?= $days ?> day(s)</td>
        </tr>
        <tr>
          <td style="padding:8px 12px;color:#64748b;">Status</td>
          <td style="padding:8px 12px;font-weight:700;color:<?= $statusColor ?>;"><?= $statusWord ?></td>
        </tr>
        <?php if ($notes): ?>
        <tr style="background:#f8fafc;">
          <td style="padding:8px 12px;color:#64748b;">Note</td>
          <td style="padding:8px 12px;"><?= htmlspecialchars($notes) ?></td>
        </tr>
        <?php endif; ?>
      </table>

      <p style="color:#64748b;font-size:.85rem;margin:0;">
        You can view your leave details by logging into the HRIS portal.
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
