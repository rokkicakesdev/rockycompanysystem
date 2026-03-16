<?php
// app/views/layouts/admin_header.php
// Updated to match screenshot layout: grouped headers, modern icons, clean hierarchy

$root = dirname(__DIR__, 3);

// Load dependencies safely
if (!class_exists('Model') || !class_exists('Database')) {
    require_once $root . '/config/database.php';
    require_once $root . '/core/Database.php';
    require_once $root . '/core/Model.php';
    require_once $root . '/core/Controller.php';
}

require_once $root . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth guard — must be logged in with admin or management role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) {
    header('Location: ' . BASE_URL . '/index.php?error=access_denied');
    exit;
}

// Dynamic counters
$pendingLeaves = 0;
if (class_exists('Model') && method_exists('Model', 'countPendingLeaves')) {
    $pendingLeaves = Model::countPendingLeaves() ?? 0;
}

// Placeholder — add real method when ready
$newApplicants = 0; // Model::countNewApplicants() ?? 0;

$userName = $_SESSION['name'] ?? 'Admin';
$userRole = $_SESSION['role'] ?? 'Unknown';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars(APP_NAME ?? 'Rocky Company') ?> | Admin Panel</title>
  <!-- AdminLTE & Bootstrap -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap">
  <!-- Ionicons (optional, but useful) -->
  <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
  <!-- overlayScrollbars -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <!-- Custom -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/common.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/admin.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <!-- Top Navbar -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
    </ul>

    <ul class="navbar-nav ml-auto">
      <!-- Pending Leaves Notification -->
      <?php if ($pendingLeaves > 0): ?>
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="far fa-bell"></i>
          <span class="badge badge-danger navbar-badge"><?= $pendingLeaves ?></span>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <span class="dropdown-item dropdown-header"><?= $pendingLeaves ?> Pending Leave(s)</span>
          <div class="dropdown-divider"></div>
          <a href="<?= BASE_URL ?>/app/views/admin/leave.php?status=pending" class="dropdown-item">
            <i class="fas fa-calendar-check mr-2"></i> View Pending Leaves
          </a>
          <div class="dropdown-divider"></div>
          <a href="<?= BASE_URL ?>/app/views/admin/leave.php" class="dropdown-item dropdown-footer">See All</a>
        </div>
      </li>
      <?php endif; ?>

      <!-- User Dropdown -->
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#">
          <i class="far fa-user mr-1"></i>
          <?= htmlspecialchars($userName) ?>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
          <div class="dropdown-header">
            <strong><?= htmlspecialchars($userName) ?></strong><br>
            <small class="text-muted"><?= ucfirst(htmlspecialchars($userRole)) ?></small>
          </div>
          <div class="dropdown-divider"></div>
          <a href="<?= BASE_URL ?>/logout.php" class="dropdown-item"
             onclick="return confirm('Are you sure you want to log out?');">
            <i class="fas fa-sign-out-alt mr-2"></i> Logout
          </a>
        </div>
      </li>
    </ul>
  </nav>

  <!-- Sidebar -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand -->
    <a href="<?= BASE_URL ?>/app/views/admin/dashboard.php" class="brand-link">
      <i class="fas fa-building mr-2" class="sidebar-brand-icon"></i>
      <span class="brand-text">
        <?= COMPANY_NAME ?>
        <span>HRIS + Payroll</span>
      </span>
    </a>
    <!-- <a href="<?= BASE_URL ?>/app/views/admin/dashboard.php" class="brand-link text-center">
      <span class="brand-text font-weight-light">
        Rocky Company<br>
        <small>HRIS + Payroll</small>
      </span>
    </a> -->

    <div class="sidebar">
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

          <!-- MAIN -->
          <li class="nav-header">MAIN</li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>Dashboard</p>
            </a>
          </li>

          <!-- HRIS -->
          <li class="nav-header">HRIS</li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/employees.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'employees.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-users"></i>
              <p>Employees</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/attendance.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-clock"></i>
              <p>Attendance</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/leave.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'leave.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-calendar-check"></i>
              <p>Leave Management</p>
              <?php if ($pendingLeaves > 0): ?>
                <span class="right badge badge-danger"><?= $pendingLeaves ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/recruitment.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'recruitment.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-user-plus"></i>
              <p>Recruitment</p>
              <?php if ($newApplicants > 0): ?>
                <span class="right badge badge-warning"><?= $newApplicants ?></span>
              <?php endif; ?>
            </a>
          </li>

          <!-- PAYROLL -->
          <li class="nav-header">PAYROLL</li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/payroll.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'payroll.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-money-bill-wave"></i>
              <p>Payroll Processing</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/payslip.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'payslip.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-file-invoice"></i>
              <p>Payslips</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/thirteenth_month.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'thirteenth_month.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-gift"></i>
              <p>13th Month Pay</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/payroll_settings.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'payroll_settings.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-sliders-h"></i>
              <p>Payroll Settings</p>
            </a>
          </li>

          <!-- ADMINISTRATION -->
          <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <li class="nav-header">ADMINISTRATION</li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/departments.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'departments.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-sitemap"></i>
              <p>Departments & Positions</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/users.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-user-shield"></i>
              <p>User Management</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/announcements.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'announcements.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-bullhorn"></i>
              <p>Announcements</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/holidays.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'holidays.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-calendar-day"></i>
              <p>Holidays</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/activity_log.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'activity_log.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-history"></i>
              <p>Activity Logs</p>
            </a>
          </li>
          <?php endif; ?>

        </ul>
      </nav>
    </div>
  </aside>

  <!-- Content Wrapper -->
  <div class="content-wrapper">
    <!-- Content Header (page title) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
          </div>
        </div>
      </div>
    </section>

    <!-- Main content area starts here in your view files -->
    <section class="content">
      <div class="container-fluid">