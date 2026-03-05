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
<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0">
          My Payslips
          <small class="text-muted">View your payroll history</small>
        </h1>
      </div>
    </div>
  </div>
</section>

<section class="content">
  <div class="container-fluid">
    <div class="card">
      <div class="card-header bg-gradient-primary text-white">
        <h3 class="card-title">Payroll Records</h3>
      </div>
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
                <td>₱ <?= number_format($row['gross_pay'] ?? 0, 2) ?></td>
                <td>₱ <?= number_format($row['total_deductions'] ?? 0, 2) ?></td>
                <td>₱ <?= number_format($row['net_pay'] ?? 0, 2) ?></td>
                <td><?= htmlspecialchars(ucfirst($row['status'] ?? '—')) ?></td>
                <td><?= htmlspecialchars($row['processed_by_name'] ?? '—') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../layouts/employee_footer.php'; ?>