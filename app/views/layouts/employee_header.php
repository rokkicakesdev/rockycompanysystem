<?php
// app/views/layouts/employee_header.php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/Model.php';

// Start session safely — prevents 'headers already sent' errors
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth: only employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header('Location: ' . BASE_URL . 'index.php?error=access_denied');
    exit;
}

$currentUser = $_SESSION['user'];
$currentPath = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars(APP_NAME ?? 'Rocky Company') ?> — <?= htmlspecialchars($pageTitle ?? 'Employee Portal') ?></title>

  <!-- CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/common.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/employee.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">

<div class="wrapper">

<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
    </li>
  </ul>

  <ul class="navbar-nav ml-auto">

    <!-- User Dropdown - now matched to admin -->
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#">
        <i class="far fa-user mr-1"></i>
        <?= htmlspecialchars($currentUser['name'] ?? 'Employee') ?>
      </a>
      <div class="dropdown-menu dropdown-menu-right">
        <div class="dropdown-header">
          <strong><?= htmlspecialchars($currentUser['name'] ?? 'Employee') ?></strong><br>
          <small class="text-muted"><?= ucfirst(htmlspecialchars($currentUser['role'] ?? 'employee')) ?></small>
        </div>
        <div class="dropdown-divider"></div>
        <a href="<?= BASE_URL ?>/app/views/employee/profile.php" class="dropdown-item">
          <i class="fas fa-user-circle mr-2"></i> My Profile
        </a>
        <div class="dropdown-divider"></div>
        <a href="#" class="dropdown-item" onclick="$('#logoutConfirmModal').modal('show'); return false;">
          <i class="fas fa-sign-out-alt mr-2"></i> Logout
        </a>
      </div>
    </li>
  </ul>
</nav>

<!-- Sidebar -->
<aside class="main-sidebar sidebar-dark-primary elevation-1">
  <a href="<?= BASE_URL ?>/app/views/employee/dashboard.php" class="brand-link text-center">
    <span class="brand-text font-weight-light">
      <?= htmlspecialchars(COMPANY_NAME ?? 'Rocky') ?>
      <small class="d-block text-muted">Employee Portal</small>
    </span>
  </a>

  <div class="sidebar">
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

        <li class="nav-header text-uppercase small">My Account</li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/app/views/employee/dashboard.php"
             class="nav-link <?= strpos($currentPath, 'dashboard') !== false ? 'active' : '' ?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>Dashboard</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/app/views/employee/my_payslips.php"
             class="nav-link <?= $currentPath === 'my_payslips.php' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-file-invoice-dollar"></i>
            <p>My Payslips</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/app/views/employee/my_leaves.php"
             class="nav-link <?= strpos($currentPath, 'my_leaves') !== false ? 'active' : '' ?>">
            <i class="nav-icon fas fa-calendar-minus"></i>
            <p>My Leaves</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/app/views/employee/my_attendance.php"
             class="nav-link <?= strpos($currentPath, 'my_attendance') !== false ? 'active' : '' ?>">
            <i class="nav-icon fas fa-clock"></i>
            <p>My Attendance</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/app/views/employee/my_reimbursements.php"
             class="nav-link <?= strpos($currentPath, 'my_reimbursements') !== false ? 'active' : '' ?>">
            <i class="nav-icon fas fa-receipt"></i>
            <p>Reimbursements</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/app/views/employee/announcements.php"
             class="nav-link <?= strpos($currentPath, 'announcements') !== false ? 'active' : '' ?>">
            <i class="nav-icon fas fa-bullhorn"></i>
            <p>Announcements</p>
          </a>
        </li>

        <li class="nav-header text-uppercase small">Account</li>

        <li class="nav-item">
          <a href="<?= BASE_URL ?>/app/views/employee/profile.php"
             class="nav-link <?= strpos($currentPath, 'profile') !== false ? 'active' : '' ?>">
            <i class="nav-icon fas fa-user-circle"></i>
            <p>My Profile</p>
          </a>
        </li>

      </ul>
    </nav>
  </div>
</aside>

<!-- Logout Confirm Modal -->
<div class="modal fade" id="logoutConfirmModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger">
        <h5 class="modal-title text-white"><i class="fas fa-sign-out-alt mr-2"></i>Confirm Logout</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">Are you sure you want to log out?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          <i class="fas fa-times mr-1"></i>Cancel
        </button>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-danger">
          <i class="fas fa-sign-out-alt mr-1"></i>Logout
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Content Wrapper -->
<div class="content-wrapper">
<div class="container-fluid py-3 px-4">