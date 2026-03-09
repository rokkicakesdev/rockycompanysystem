<?php
// app/views/employee/my_payslips.php

session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header('Location: ' . BASE_URL . 'index.php?error=access_denied');
    exit;
}

$pageTitle = 'My Payslips';
require_once __DIR__ . '/../layouts/employee_header.php';
?>

<!-- Page content -->
<div class="page-title-bar">
  <i class="fas fa-file-invoice-dollar text-primary"></i>
  <h1>My Payslips <small class="text-muted" style="font-size:.55em;">View your payroll history</small></h1>
</div>

<div class="card">
  <div class="card-body table-responsive p-0">
    <?php
    require_once __DIR__ . '/../../../core/Model.php';
    $records = Model::getPayrollRecordsByEmployee($_SESSION['employee_id'] ?? 0);

    if (empty($records)): ?>
      <div class="alert alert-info text-center m-4">
        <i class="fas fa-info-circle mr-2"></i>
        No payroll records found yet.
      </div>
    <?php else: ?>
      <table class="table table-hover table-bordered text-nowrap">
        <thead class="thead-light">
          <tr>
            <th>Period</th>
            <th>Gross Pay</th>
            <th>Total Deductions</th>
            <th>Net Pay</th>
            <th>Status</th>
            <th>Processed By</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['period'] ?? '—') ?></td>
            <td>&#8369; <?= number_format($row['gross_pay'] ?? 0, 2) ?></td>
            <td>&#8369; <?= number_format($row['total_deductions'] ?? 0, 2) ?></td>
            <td>&#8369; <?= number_format($row['net_pay'] ?? 0, 2) ?></td>
            <td><?= htmlspecialchars(ucfirst($row['status'] ?? '—')) ?></td>
            <td><?= htmlspecialchars($row['processed_by_name'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../layouts/employee_footer.php'; ?>