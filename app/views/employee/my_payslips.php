<?php
// Top of file - protect the page
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../index.php?error=access_denied");
    exit;
}

$pageTitle = 'My Payslips';
require_once __DIR__ . '/../layouts/admin_header.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <h1>
            My Payslips
            <small>View your payroll history</small>
        </h1>
    </section>

    <section class="content">
        <div class="box">
            <div class="box-header">
                <h3 class="box-title">Payroll Records</h3>
            </div>
            <div class="box-body table-responsive">
                <?php
                // Fetch records - add this method to core/Model.php
                $records = Model::getPayrollRecordsByEmployee($_SESSION['employee_id']);
                if (empty($records)): ?>
                    <p class="text-center text-muted">No payroll records found yet.</p>
                <?php else: ?>
                    <table class="table table-bordered table-striped">
                        <thead>
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
                                <td><?= htmlspecialchars($row['status'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($row['processed_by_name'] ?? '—') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<?php
// Footer
require_once '../../layouts/footer.php';
?>