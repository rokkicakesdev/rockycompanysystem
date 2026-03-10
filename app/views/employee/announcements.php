<?php
// app/views/employee/announcements.php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$pageTitle = 'Announcements';
require_once __DIR__ . '/../layouts/employee_header.php';

$announcements = Model::getActiveAnnouncements();

$typeColors = [
    'urgent'  => 'danger',
    'payroll' => 'primary',
    'leave'   => 'warning',
    'holiday' => 'success',
    'general' => 'secondary',
];
$typeIcons = [
    'urgent'  => 'fa-exclamation-circle',
    'payroll' => 'fa-peso-sign',
    'leave'   => 'fa-calendar-minus',
    'holiday' => 'fa-umbrella-beach',
    'general' => 'fa-bullhorn',
];
?>

<div class="page-title-bar">
  <i class="fas fa-bullhorn text-danger"></i>
  <h1>Announcements</h1>
  <small class="text-muted ml-auto"><?= count($announcements) ?> active announcement<?= count($announcements) !== 1 ? 's' : '' ?></small>
</div>

<?php if (empty($announcements)): ?>
  <div class="card">
    <div class="card-body text-center py-5 text-muted">
      <i class="fas fa-bullhorn fa-4x mb-3 d-block" style="opacity:.15;"></i>
      <p class="mb-0">No active announcements at this time.</p>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($announcements as $ann):
    $color = $typeColors[$ann['type']] ?? 'secondary';
    $icon  = $typeIcons[$ann['type']]  ?? 'fa-bullhorn';
  ?>
  <div class="card mb-3 <?= $ann['is_pinned'] ? 'border-warning' : '' ?>">
    <div class="card-header d-flex align-items-center">
      <span class="badge badge-<?= $color ?> mr-2 px-2 py-1">
        <i class="fas <?= $icon ?> mr-1"></i><?= ucfirst($ann['type']) ?>
      </span>
      <strong class="mr-2"><?= htmlspecialchars($ann['title']) ?></strong>
      <?php if ($ann['is_pinned']): ?>
        <span class="badge badge-warning ml-1"><i class="fas fa-thumbtack mr-1"></i>Pinned</span>
      <?php endif; ?>
      <small class="text-muted ml-auto">
        <?= date('F d, Y', strtotime($ann['created_at'])) ?>
        &mdash; <?= htmlspecialchars($ann['posted_by_name'] ?? 'Admin') ?>
        <?php if ($ann['expires_at']): ?>
          &nbsp;|&nbsp; Expires: <?= date('M d, Y', strtotime($ann['expires_at'])) ?>
        <?php endif; ?>
      </small>
    </div>
    <div class="card-body">
      <p class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($ann['content']) ?></p>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/employee_footer.php'; ?>