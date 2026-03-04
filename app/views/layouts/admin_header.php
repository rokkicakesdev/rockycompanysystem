<?php

if (!class_exists('Model')) {
    require_once __DIR__ . '/../../../config/database.php';
    require_once __DIR__ . '/../../../core/Database.php';
    require_once __DIR__ . '/../../../core/Model.php';
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/Model.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../index.php'); exit;
}
$currentUser = $_SESSION['user'];
$currentPath = basename($_SERVER['PHP_SELF']);
$pendingLeaves = Model::countPendingLeaves();
$openJobs      = Model::countOpenJobPostings();
$newApplicants = Model::countNewApplicants();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= APP_NAME ?> — <?= $pageTitle ?? 'Dashboard' ?></title>
  <!-- AdminLTE & Bootstrap -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap">
  <link rel="stylesheet" href="../../../assets/css/common.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
  <ul class="navbar-nav">
    <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li>
  </ul>
  <ul class="navbar-nav ml-auto">
    <?php if ($pendingLeaves > 0): ?>
    <li class="nav-item">
      <a class="nav-link" href="leave.php" title="Pending Leaves">
        <i class="fas fa-calendar-times"></i>
        <span class="badge badge-danger navbar-badge"><?= $pendingLeaves ?></span>
      </a>
    </li>
    <?php endif; ?>
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
        <div style="width:30px;height:30px;border-radius:50%;background:var(--accent);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:.8rem;font-weight:700;margin-right:6px;">
          <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
        </div>
        <?= htmlspecialchars($currentUser['name']) ?>
      </a>
      <div class="dropdown-menu dropdown-menu-right">
        <span class="dropdown-item-text text-muted" style="font-size:.75rem;">
          <?= ucfirst($currentUser['role']) ?>
        </span>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="../../../logout.php"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
      </div>
    </li>
  </ul>
</nav>

<!-- Sidebar -->
<aside class="main-sidebar sidebar-dark-primary elevation-1">
  <a href="dashboard.php" class="brand-link">
    <i class="fas fa-building mr-2" style="color:#60a5fa;font-size:1.3rem;"></i>
    <span class="brand-text">
      <?= COMPANY_NAME ?>
      <span>HRIS + Payroll</span>
    </span>
  </a>
  <div class="sidebar">
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

        <!-- MAIN -->
        <li class="nav-header">Main</li>
        <li class="nav-item">
          <a href="dashboard.php" class="nav-link <?= $currentPath==='dashboard.php'?'active':'' ?>">
            <i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p>
          </a>
        </li>

        <!-- HRIS -->
        <li class="nav-header">HRIS</li>
        <li class="nav-item">
          <a href="employees.php" class="nav-link <?= $currentPath==='employees.php'?'active':'' ?>">
            <i class="nav-icon fas fa-users"></i><p>Employees</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="attendance.php" class="nav-link <?= $currentPath==='attendance.php'?'active':'' ?>">
            <i class="nav-icon fas fa-clock"></i><p>Attendance</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="leave.php" class="nav-link <?= $currentPath==='leave.php'?'active':'' ?>">
            <i class="nav-icon fas fa-calendar-minus"></i><p>Leave Management <?php if ($pendingLeaves > 0): ?><span class="right badge badge-danger badge-pill"><?= $pendingLeaves ?></span><?php endif; ?></p>
          </a>
        </li>
        <li class="nav-item">
          <a href="recruitment.php" class="nav-link <?= $currentPath==='recruitment.php'?'active':'' ?>">
            <i class="nav-icon fas fa-briefcase"></i><p>Recruitment <?php if ($newApplicants > 0): ?><span class="right badge badge-warning badge-pill"><?= $newApplicants ?></span><?php endif; ?></p>
          </a>
        </li>

        <!-- PAYROLL -->
        <li class="nav-header">Payroll</li>
        <li class="nav-item">
          <a href="payroll.php" class="nav-link <?= $currentPath==='payroll.php'?'active':'' ?>">
            <i class="nav-icon fas fa-money-check-alt"></i><p>Payroll Processing</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="payslip.php" class="nav-link <?= $currentPath==='payslip.php'?'active':'' ?>">
            <i class="nav-icon fas fa-file-invoice-dollar"></i><p>Payslips</p>
          </a>
        </li>

        <!-- ADMIN -->
        <?php if ($_SESSION['role'] === ROLE_ADMIN): ?>
        <li class="nav-header">Administration</li>
        <li class="nav-item">
          <a href="departments.php" class="nav-link <?= $currentPath==='departments.php'?'active':'' ?>">
            <i class="nav-icon fas fa-sitemap"></i><p>Departments & Positions</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="users.php" class="nav-link <?= $currentPath==='users.php'?'active':'' ?>">
            <i class="nav-icon fas fa-user-shield"></i><p>User Management</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="announcements.php" class="nav-link <?= $currentPath==='announcements.php'?'active':'' ?>">
            <i class="nav-icon fas fa-bullhorn"></i><p>Announcements</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="holidays.php" class="nav-link <?= $currentPath==='holidays.php'?'active':'' ?>">
            <i class="nav-icon fas fa-umbrella-beach"></i><p>Holidays</p>
          </a>
        </li>
        <li class="nav-item">
          <a href="activity_log.php" class="nav-link <?= $currentPath==='activity_log.php'?'active':'' ?>">
            <i class="nav-icon fas fa-history"></i><p>Activity Logs</p>
          </a>
        </li>
        <?php endif; ?>

      </ul>
    </nav>
  </div>
</aside>

<!-- /.main-sidebar -->
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <h1 class="m-0" style="font-size:1.1rem;font-weight:700;color:#1e293b;"><?= $pageTitle ?? 'Page' ?></h1>
    </div>
  </div>
  <section class="content">
    <div class="container-fluid">