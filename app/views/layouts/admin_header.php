<?php
// app/views/layouts/admin_header.php
// ──────────────────────────────────────────────────────────────
// Admin Header / Navbar Layout
// Loads core dependencies safely, no duplicates
// ──────────────────────────────────────────────────────────────

// Calculate project root once (from app/views/layouts/ → root = 3 levels up)
$root = dirname(__DIR__, 3);

// Safety net: load core classes if not already present
if (!class_exists('Model') || !class_exists('Database')) {
    require_once $root . '/config/database.php';     // .env + DB constants
    require_once $root . '/core/Database.php';       // PDO singleton
    require_once $root . '/core/Model.php';          // main model class
    require_once $root . '/core/Controller.php';     // base controller (if used)
}

// Always load global config (defines APP_NAME, BASE_URL, etc.)
require_once $root . '/config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get pending leave count for badge (fallback to 0 if method fails)
$pendingLeaves = 0;
if (class_exists('Model') && method_exists('Model', 'countPendingLeaves')) {
    $pendingLeaves = Model::countPendingLeaves() ?? 0;
}

// User info (fallback if not set)
$userName = $_SESSION['name'] ?? 'Admin';
$userRole = $_SESSION['role'] ?? 'Unknown';

// Debug: confirm BASE_URL (remove after testing)
// echo '<pre>BASE_URL = ' . BASE_URL . '</pre>';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars(APP_NAME ?? 'Rocky Company') ?> | Admin Panel</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/plugins/fontawesome-free/css/all.min.css">
  <!-- Ionicons -->
  <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/dist/css/adminlte.min.css">
  <!-- overlayScrollbars -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <!-- Custom styles -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/common.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="<?= BASE_URL ?>/app/views/admin/dashboard.php" class="nav-link">Dashboard</a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="<?= BASE_URL ?>/app/views/admin/employees.php" class="nav-link">Employees</a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">

      <!-- Notifications: Pending Leaves -->
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="far fa-bell"></i>
          <?php if ($pendingLeaves > 0): ?>
            <span class="badge badge-danger navbar-badge"><?= $pendingLeaves ?></span>
          <?php endif; ?>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <span class="dropdown-item dropdown-header">
            <?= $pendingLeaves ?> Pending Leave Request<?= $pendingLeaves !== 1 ? 's' : '' ?>
          </span>
          <div class="dropdown-divider"></div>
          <a href="<?= BASE_URL ?>/app/views/admin/leave-requests.php?status=pending" class="dropdown-item">
            <i class="fas fa-envelope mr-2"></i> View Pending Leaves
          </a>
          <div class="dropdown-divider"></div>
          <a href="<?= BASE_URL ?>/app/views/admin/leave-requests.php" class="dropdown-item dropdown-footer">See All Requests</a>
        </div>
      </li>

      <!-- User Menu -->
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="far fa-user-circle mr-1"></i>
          <span class="d-none d-md-inline"><?= htmlspecialchars($userName) ?></span>
        </a>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
          <div class="dropdown-header">
            <strong><?= htmlspecialchars($userName) ?></strong><br>
            <small class="text-muted"><?= htmlspecialchars($userRole) ?></small>
          </div>
          <div class="dropdown-divider"></div>
          <a href="<?= BASE_URL ?>/logout.php" class="dropdown-item">
            <i class="fas fa-sign-out-alt mr-2"></i> Logout
          </a>
        </div>
      </li>

      <!-- Fullscreen toggle -->
      <li class="nav-item">
        <a class="nav-link" data-widget="fullscreen" href="#" role="button">
          <i class="fas fa-expand-arrows-alt"></i>
        </a>
      </li>
    </ul>
  </nav>
  <!-- /.navbar -->

  <!-- Main Sidebar Container -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="<?= BASE_URL ?>/app/views/admin/dashboard.php" class="brand-link">
      <img src="<?= ASSETS_URL ?>/dist/img/AdminLTELogo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><?= htmlspecialchars(APP_NAME ?? 'Rocky') ?></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar user panel -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="<?= ASSETS_URL ?>/dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
        </div>
        <div class="info">
          <a href="#" class="d-block"><?= htmlspecialchars($userName) ?></a>
        </div>
      </div>

      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/dashboard.php" class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>Dashboard</p>
            </a>
          </li>

          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/employees.php" class="nav-link <?= $currentPage === 'employees.php' ? 'active' : '' ?>">
              <i class="nav-icon fas fa-users"></i>
              <p>Employees</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/payroll.php" class="nav-link">
              <i class="nav-icon fas fa-money-bill-wave"></i>
              <p>Payroll</p>
            </a>
          </li>

          <li class="nav-item">
            <a href="<?= BASE_URL ?>/app/views/admin/leave.php" class="nav-link">
              <i class="nav-icon fas fa-calendar-alt"></i>
              <p>Leave Requests</p>
              <?php if ($pendingLeaves > 0): ?>
                <span class="badge badge-danger right"><?= $pendingLeaves ?></span>
              <?php endif; ?>
            </a>
          </li>

          <li class="nav-header">SYSTEM</li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/logout.php" class="nav-link">
              <i class="nav-icon fas fa-sign-out-alt"></i>
              <p>Logout</p>
            </a>
          </li>
        </ul>
      </nav>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>

  <!-- Content Wrapper -->
  <div class="content-wrapper">
    <!-- Your page content starts here -->