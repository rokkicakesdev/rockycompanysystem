<?php
// core/models/LoanModel.php

require_once __DIR__ . '/BaseModel.php';

class LoanModel extends BaseModel
{
    // ════════════════════════════════════════════════════════
    //  LOAN TYPES
    // ════════════════════════════════════════════════════════

    public static function getLoanTypes(): array
    {
        return [
            'sss_salary_loan'   => 'SSS Salary Loan',
            'pagibig_mpl'       => 'Pag-IBIG Multi-Purpose Loan',
            'company_cash_advance' => 'Company Cash Advance',
            'company_loan'      => 'Company Loan',
        ];
    }

    public static function getLoanTypeLabel(string $type): string
    {
        return self::getLoanTypes()[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    // ════════════════════════════════════════════════════════
    //  TABLE BOOTSTRAP
    // ════════════════════════════════════════════════════════

    public static function ensureTable(): void
    {
        self::db()->exec("
            CREATE TABLE IF NOT EXISTS `employee_loans` (
                `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `employee_id`        INT UNSIGNED NOT NULL,
                `loan_type`          VARCHAR(60) NOT NULL,
                `loan_amount`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `monthly_deduction`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `cutoff_deduction`   DECIMAL(10,2) NOT NULL DEFAULT 0.00
                    COMMENT 'monthly_deduction / 2, deducted each semi-monthly cutoff',
                `remaining_balance`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `start_date`         DATE NOT NULL,
                `status`             ENUM('active','fully_paid','cancelled') NOT NULL DEFAULT 'active',
                `reference_no`       VARCHAR(100) DEFAULT NULL,
                `notes`              TEXT DEFAULT NULL,
                `created_by`         INT UNSIGNED DEFAULT NULL,
                `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_employee_id` (`employee_id`),
                KEY `idx_status`      (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::db()->exec("
            CREATE TABLE IF NOT EXISTS `loan_deduction_log` (
                `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `loan_id`        INT UNSIGNED NOT NULL,
                `payroll_id`     INT UNSIGNED NOT NULL,
                `employee_id`    INT UNSIGNED NOT NULL,
                `period`         VARCHAR(9) NOT NULL,
                `amount_deducted` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `balance_before` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `balance_after`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_loan_id`    (`loan_id`),
                KEY `idx_payroll_id` (`payroll_id`),
                KEY `idx_employee_period` (`employee_id`, `period`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // ════════════════════════════════════════════════════════
    //  CRUD
    // ════════════════════════════════════════════════════════

    public static function getAll(): array
    {
        self::ensureTable();
        $stmt = self::db()->query("
            SELECT el.*, e.name AS employee_name, e.employee_no AS emp_code,
                   d.name AS department, p.name AS position,
                   u.name AS created_by_name
            FROM employee_loans el
            JOIN employees e ON e.id = el.employee_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN positions p ON p.id = e.position_id
            LEFT JOIN users u ON u.id = el.created_by
            ORDER BY el.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    public static function getByEmployee(int $employeeId): array
    {
        self::ensureTable();
        $stmt = self::db()->prepare("
            SELECT el.*, u.name AS created_by_name
            FROM employee_loans el
            LEFT JOIN users u ON u.id = el.created_by
            WHERE el.employee_id = ?
            ORDER BY el.created_at DESC
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }

    public static function getActiveByEmployee(int $employeeId): array
    {
        self::ensureTable();
        $stmt = self::db()->prepare("
            SELECT * FROM employee_loans
            WHERE employee_id = ? AND status = 'active'
            ORDER BY start_date ASC
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        self::ensureTable();
        $stmt = self::db()->prepare("
            SELECT el.*, e.name AS employee_name
            FROM employee_loans el
            JOIN employees e ON e.id = el.employee_id
            WHERE el.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $d, int $userId): bool
    {
        self::ensureTable();
        $monthly    = (float)$d['monthly_deduction'];
        $cutoff     = round($monthly / 2, 2);
        $loanAmount = (float)$d['loan_amount'];

        $stmt = self::db()->prepare("
            INSERT INTO employee_loans
                (employee_id, loan_type, loan_amount, monthly_deduction, cutoff_deduction,
                 remaining_balance, start_date, status, reference_no, notes, created_by)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)
        ");
        return (bool) $stmt->execute([
            (int)$d['employee_id'],
            $d['loan_type'],
            $loanAmount,
            $monthly,
            $cutoff,
            $loanAmount,
            $d['start_date'],
            $d['reference_no'] ?? null,
            $d['notes'] ?? null,
            $userId,
        ]);
    }

    public static function update(int $id, array $d): bool
    {
        $monthly = (float)$d['monthly_deduction'];
        $cutoff  = round($monthly / 2, 2);

        $stmt = self::db()->prepare("
            UPDATE employee_loans
            SET loan_type          = ?,
                loan_amount        = ?,
                monthly_deduction  = ?,
                cutoff_deduction   = ?,
                remaining_balance  = ?,
                start_date         = ?,
                status             = ?,
                reference_no       = ?,
                notes              = ?
            WHERE id = ?
        ");
        return (bool) $stmt->execute([
            $d['loan_type'],
            (float)$d['loan_amount'],
            $monthly,
            $cutoff,
            (float)$d['remaining_balance'],
            $d['start_date'],
            $d['status'],
            $d['reference_no'] ?? null,
            $d['notes'] ?? null,
            $id,
        ]);
    }

    public static function delete(int $id): bool
    {
        $stmt = self::db()->prepare("DELETE FROM employee_loans WHERE id = ?");
        return (bool) $stmt->execute([$id]);
    }

    // ════════════════════════════════════════════════════════
    //  PAYROLL DEDUCTION ENGINE
    // ════════════════════════════════════════════════════════

    /**
     * Compute total loan deduction for an employee for one payroll cutoff.
     * Returns the total amount to deduct and a detail breakdown array.
     *
     * Rules (Philippine compliance 2026):
     *  - SSS Salary Loan   : fixed monthly instalment split over 2 cutoffs
     *  - Pag-IBIG MPL      : fixed monthly instalment split over 2 cutoffs
     *  - Company loans     : fixed cutoff amount as configured
     *  - Auto-stops when remaining_balance reaches zero
     *  - Each cutoff deducts min(cutoff_deduction, remaining_balance)
     */
    public static function computeCutoffDeduction(int $employeeId, string $period): array
    {
        self::ensureTable();
        $activeLoans = self::getActiveByEmployee($employeeId);

        if (empty($activeLoans)) {
            return ['total' => 0.0, 'items' => []];
        }

        $periodBase = self::periodBase($period);
        $cutoffStart = $periodBase . '-01';

        $total = 0.0;
        $items = [];

        foreach ($activeLoans as $loan) {
            if ($loan['start_date'] > $periodBase . '-15') {
                continue;
            }

            $remaining = (float)$loan['remaining_balance'];
            if ($remaining <= 0) {
                continue;
            }

            $cutoffAmt = min((float)$loan['cutoff_deduction'], $remaining);
            if ($cutoffAmt <= 0) {
                continue;
            }

            $total  += $cutoffAmt;
            $items[] = [
                'loan_id'       => (int)$loan['id'],
                'loan_type'     => $loan['loan_type'],
                'label'         => self::getLoanTypeLabel($loan['loan_type']),
                'amount'        => $cutoffAmt,
                'balance_before'=> $remaining,
                'balance_after' => max(0.0, $remaining - $cutoffAmt),
            ];
        }

        return ['total' => round($total, 2), 'items' => $items];
    }

    /**
     * Apply loan deductions after payroll record is saved.
     * Reduces remaining_balance on each loan and logs the deduction.
     * Marks loans as 'fully_paid' when balance hits zero.
     */
    public static function applyDeductions(int $payrollId, int $employeeId, string $period, array $items): void
    {
        self::ensureTable();
        $db = self::db();

        foreach ($items as $item) {
            $loanId      = (int)$item['loan_id'];
            $amtDeducted = (float)$item['amount'];
            $balBefore   = (float)$item['balance_before'];
            $balAfter    = (float)$item['balance_after'];

            $logStmt = $db->prepare("
                INSERT INTO loan_deduction_log
                    (loan_id, payroll_id, employee_id, period, amount_deducted, balance_before, balance_after)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $logStmt->execute([$loanId, $payrollId, $employeeId, $period, $amtDeducted, $balBefore, $balAfter]);

            $newStatus = $balAfter <= 0 ? 'fully_paid' : 'active';

            $updStmt = $db->prepare("
                UPDATE employee_loans
                SET remaining_balance = ?, status = ?
                WHERE id = ?
            ");
            $updStmt->execute([$balAfter, $newStatus, $loanId]);
        }
    }

    /**
     * Reverse a loan deduction when a payroll record is deleted.
     * Restores the balance from the log.
     */
    public static function reverseDeductions(int $payrollId): void
    {
        self::ensureTable();
        $db = self::db();

        $stmt = $db->prepare("SELECT * FROM loan_deduction_log WHERE payroll_id = ?");
        $stmt->execute([$payrollId]);
        $logs = $stmt->fetchAll();

        foreach ($logs as $log) {
            $restoreStmt = $db->prepare("
                UPDATE employee_loans
                SET remaining_balance = ?, status = 'active'
                WHERE id = ?
            ");
            $restoreStmt->execute([(float)$log['balance_before'], (int)$log['loan_id']]);
        }

        $delStmt = $db->prepare("DELETE FROM loan_deduction_log WHERE payroll_id = ?");
        $delStmt->execute([$payrollId]);
    }

    // ════════════════════════════════════════════════════════
    //  DEDUCTION LOG QUERIES
    // ════════════════════════════════════════════════════════

    public static function getDeductionLogByLoan(int $loanId): array
    {
        self::ensureTable();
        $stmt = self::db()->prepare("
            SELECT ldl.*, pr.period AS payroll_period, e.name AS employee_name
            FROM loan_deduction_log ldl
            JOIN payroll_records pr ON pr.id = ldl.payroll_id
            JOIN employees e ON e.id = ldl.employee_id
            WHERE ldl.loan_id = ?
            ORDER BY ldl.created_at ASC
        ");
        $stmt->execute([$loanId]);
        return $stmt->fetchAll();
    }

    public static function getDeductionsByPayroll(int $payrollId): array
    {
        self::ensureTable();
        $stmt = self::db()->prepare("
            SELECT ldl.*, el.loan_type, el.loan_amount
            FROM loan_deduction_log ldl
            JOIN employee_loans el ON el.id = ldl.loan_id
            WHERE ldl.payroll_id = ?
            ORDER BY ldl.created_at ASC
        ");
        $stmt->execute([$payrollId]);
        return $stmt->fetchAll();
    }

    // ════════════════════════════════════════════════════════
    //  SUMMARY STATS
    // ════════════════════════════════════════════════════════

    public static function getSummaryStats(): array
    {
        self::ensureTable();
        $stmt = self::db()->query("
            SELECT
                COUNT(*) AS total_loans,
                COALESCE(SUM(CASE WHEN status = 'active'     THEN 1 ELSE 0 END), 0) AS active_count,
                COALESCE(SUM(CASE WHEN status = 'fully_paid' THEN 1 ELSE 0 END), 0) AS paid_count,
                COALESCE(SUM(loan_amount), 0)        AS total_amount,
                COALESCE(SUM(remaining_balance), 0)  AS total_outstanding
            FROM employee_loans
        ");
        return $stmt->fetch() ?: [];
    }

    // ════════════════════════════════════════════════════════
    //  PERIOD HELPER (mirrors PayrollModel)
    // ════════════════════════════════════════════════════════

    private static function periodBase(string $period): string
    {
        return preg_match('/^(\d{4}-\d{2})-\d$/', $period, $m) ? $m[1] : $period;
    }
}
