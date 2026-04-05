<?php
// core/models/PayrollModel.php
// ─────────────────────────────────────────────────────────────────────────────
//  Handles all payroll operations:
//    - Payroll records (CRUD, release)
//    - Period string helpers
//    - Employee payroll settings (semi-monthly)
//    - Year-to-date aggregates (tax, gov deductions, basic)
//    - 13th month pay
//  Extracted from Model.php (God Class split).
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/BaseModel.php';

if (!class_exists('PhilippineDeductions')) {
    require_once __DIR__ . '/../PhilippineDeductions.php';
}

class PayrollModel extends BaseModel
{
    // ════════════════════════════════════════════════════════
    //  PERIOD HELPERS
    //  Period format: YYYY-MM-C  (C = 1 or 2)
    //  e.g. "2026-12-1" = December 1–15
    //       "2026-12-2" = December 16–31
    // ════════════════════════════════════════════════════════

    /** "2026-02-1" → "February 2026 (1st–15th)" */
    public static function periodLabel(string $period): string
    {
        if (preg_match('/^(\d{4}-\d{2})-(\d)$/', $period, $m)) {
            $monthLabel = date('F Y', strtotime($m[1] . '-01'));
            $cutoff     = $m[2] === '1' ? '1st–15th' : '16th–End';
            return "{$monthLabel} ({$cutoff})";
        }
        // Fallback for legacy YYYY-MM format
        return date('F Y', strtotime($period . '-01'));
    }

    /** "2026-02-1" → "2026-02" */
    public static function periodBase(string $period): string
    {
        return preg_match('/^(\d{4}-\d{2})-\d$/', $period, $m) ? $m[1] : $period;
    }

    /** "2026-12-2" → 2026 */
    public static function periodYear(string $period): int
    {
        return (int) substr($period, 0, 4);
    }

    /** "2026-12-2" → 2 */
    public static function periodCutoff(string $period): int
    {
        return preg_match('/-(\d)$/', $period, $m) ? (int) $m[1] : 1;
    }

    /** True when period is the December 1st cutoff (13th month disbursal). */
    public static function isDecember1stCutoff(string $period): bool
    {
        return preg_match('/^\d{4}-12-1$/', $period) === 1;
    }

    /** True when period is the December 2nd cutoff (year-end tax reconciliation). */
    public static function isDecember2ndCutoff(string $period): bool
    {
        return preg_match('/^\d{4}-12-2$/', $period) === 1;
    }

    /** Returns ["YYYY-MM-1", "YYYY-MM-2"] for a given YYYY-MM base. */
    public static function periodsForMonth(string $yearMonth): array
    {
        return ["{$yearMonth}-1", "{$yearMonth}-2"];
    }

    // ════════════════════════════════════════════════════════
    //  PAYROLL RECORDS
    // ════════════════════════════════════════════════════════

    public static function getAll(): array
    {
        return self::db()->query('SELECT * FROM v_payroll ORDER BY period DESC, employee_name')->fetchAll();
    }

    public static function getByPeriod(string $period): array
    {
        $stmt = self::db()->prepare('SELECT * FROM v_payroll WHERE period = ? ORDER BY employee_name');
        $stmt->execute([$period]);
        return $stmt->fetchAll();
    }

    public static function getByEmployee(int $employeeId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM v_payroll WHERE employee_id = ? ORDER BY period DESC');
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }

    /** Returns last 2 years of records (48 semi-monthly periods). */
    public static function getRecentByEmployee(int $employeeId): array
    {
        $stmt = self::db()->prepare('
            SELECT period, gross_pay, total_deductions, net_pay, status, processed_by_name
            FROM v_payroll
            WHERE employee_id = ?
            ORDER BY period DESC
            LIMIT 48
        ');
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findById(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM v_payroll WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function periodExists(string $period): bool
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) FROM payroll_records WHERE period = ?');
        $stmt->execute([$period]);
        return (bool) $stmt->fetchColumn();
    }

    public static function employeeExistsInPeriod(int $employeeId, string $period): bool
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) FROM payroll_records WHERE employee_id = ? AND period = ?');
        $stmt->execute([$employeeId, $period]);
        return (bool) $stmt->fetchColumn();
    }

    public static function getTotalNetPayForPeriod(string $period): float
    {
        $stmt = self::db()->prepare('SELECT COALESCE(SUM(net_pay), 0) AS total FROM payroll_records WHERE period = ?');
        $stmt->execute([$period]);
        return (float) $stmt->fetch()['total'];
    }

    public static function getPeriods(): array
    {
        return self::db()->query('SELECT DISTINCT period FROM payroll_records ORDER BY period DESC')->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Returns the oldest existing payroll period (for default period selector). */
    public static function getOldestPeriod(): ?string
    {
        $row = self::db()->query('SELECT MIN(period) FROM payroll_records')->fetchColumn();
        return $row ?: null;
    }

    /** Get payroll periods for a specific employee (for dependent dropdown). */
    public static function getPeriodsForEmployee(int $employeeId): array
    {
        $stmt = self::db()->prepare('SELECT DISTINCT period FROM payroll_records WHERE employee_id = ? ORDER BY period DESC');
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Delete a single payroll record by ID.
     */
    public static function deleteRecord(int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM payroll_records WHERE id = ?');
        return (bool) $stmt->execute([$id]);
    }

    /**
     * Check if an employee has any payroll records in a given year BEFORE December.
     * Used to determine if Year-End Tax Reconciliation should apply.
     * A new hire who only has December records (< 2 prior cutoffs) is excluded.
     */
    public static function hasPayrollBeforeDecember(int $employeeId, int $year): bool
    {
        $stmt = self::db()->prepare(
            "SELECT COUNT(*) FROM payroll_records
             WHERE employee_id = ? AND period LIKE ? AND period NOT LIKE ?"
        );
        $stmt->execute([$employeeId, $year . '-%', $year . '-12-%']);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Check employees in a specific cutoff date range that have NO attendance records.
     * Respects hire date — an employee hired after the cutoff end is excluded entirely.
     * An employee hired mid-cutoff only needs records from their hire date onward.
     */
    public static function getEmployeesWithMissingAttendance(array $employeeIds, string $period): array
    {
        $cutoffNum = static::periodCutoff($period);
        $yearMonth = static::periodBase($period);
        [$year, $month] = explode('-', $yearMonth);
        $lastDay = date('t', mktime(0, 0, 0, (int)$month, 1, (int)$year));

        if ($cutoffNum === 1) {
            $cutoffFrom = "{$yearMonth}-01";
            $cutoffTo   = "{$yearMonth}-15";
        } else {
            $cutoffFrom = "{$yearMonth}-16";
            $cutoffTo   = "{$yearMonth}-{$lastDay}";
        }

        $missing = [];
        foreach ($employeeIds as $empId) {
            $empId = (int)$empId;

            // Fetch employee hire date
            $empStmt = self::db()->prepare('SELECT name, date_hired, date_start FROM employees WHERE id = ?');
            $empStmt->execute([$empId]);
            $empRow  = $empStmt->fetch();
            if (!$empRow) continue;

            // Use date_start if set, otherwise fall back to date_hired
            $dateStart = !empty($empRow['date_start']) ? $empRow['date_start'] : ($empRow['date_hired'] ?? '');

            // If employee started after the cutoff ends, skip — no records expected
            if ($dateStart && $dateStart > $cutoffTo) continue;

            // Effective start: clamp to date_start if started mid-cutoff
            $effectiveFrom = ($dateStart && $dateStart > $cutoffFrom) ? $dateStart : $cutoffFrom;

            // Count attendance records within the effective cutoff range
            $attStmt = self::db()->prepare(
                'SELECT COUNT(*) FROM attendance WHERE employee_id = ? AND date >= ? AND date <= ?'
            );
            $attStmt->execute([$empId, $effectiveFrom, $cutoffTo]);
            $count = (int) $attStmt->fetchColumn();

            if ($count === 0) {
                $missing[] = htmlspecialchars($empRow['name']);
            }
        }
        return $missing;
    }


    public static function create(array $d): bool
    {
        $stmt = self::db()->prepare('
            INSERT INTO payroll_records
              (employee_id, period, basic_salary, allowance, gross_pay,
               sss_msc, sss_ee, sss_er,
               philhealth_mbs, philhealth_ee, philhealth_er,
               pagibig_mfs, pagibig_ee, pagibig_er,
               taxable_income, withholding_tax,
               other_deductions, total_deductions, net_pay,
               absent_deduction, salary_deduction, unpaid_leave_deduction,
               overtime_pay, holiday_pay,
               days_worked, days_absent, days_paid_leave, working_days_in_month,
               remarks, status, processed_by)
            VALUES
              (:employee_id, :period, :basic_salary, :allowance, :gross_pay,
               :sss_msc, :sss_ee, :sss_er,
               :philhealth_mbs, :philhealth_ee, :philhealth_er,
               :pagibig_mfs, :pagibig_ee, :pagibig_er,
               :taxable_income, :withholding_tax,
               :other_deductions, :total_deductions, :net_pay,
               :absent_deduction, :salary_deduction, :unpaid_leave_deduction,
               :overtime_pay, :holiday_pay,
               :days_worked, :days_absent, :days_paid_leave, :working_days_in_month,
               :remarks, :status, :processed_by)
        ');
        return (bool) $stmt->execute([
            ':employee_id'             => $d['employee_id'],
            ':period'                  => $d['period'],
            ':basic_salary'            => $d['basic_salary'],
            ':allowance'               => $d['allowance'],
            ':gross_pay'               => $d['gross_pay'],
            ':sss_msc'                 => $d['sss_msc'],
            ':sss_ee'                  => $d['sss_ee'],
            ':sss_er'                  => $d['sss_er'],
            ':philhealth_mbs'          => $d['philhealth_mbs'],
            ':philhealth_ee'           => $d['philhealth_ee'],
            ':philhealth_er'           => $d['philhealth_er'],
            ':pagibig_mfs'             => $d['pagibig_mfs'],
            ':pagibig_ee'              => $d['pagibig_ee'],
            ':pagibig_er'              => $d['pagibig_er'],
            ':taxable_income'          => $d['taxable_income'],
            ':withholding_tax'         => $d['withholding_tax'],
            ':other_deductions'        => $d['other_deductions']        ?? 0,
            ':total_deductions'        => $d['total_deductions'],
            ':net_pay'                 => $d['net_pay'],
            ':absent_deduction'        => $d['absent_deduction']        ?? 0,
            ':salary_deduction'        => $d['salary_deduction']        ?? 0,
            ':unpaid_leave_deduction'  => $d['unpaid_leave_deduction']  ?? 0,
            ':overtime_pay'            => $d['overtime_pay']            ?? 0,
            ':holiday_pay'             => $d['holiday_pay']             ?? 0,
            ':days_worked'             => $d['days_worked']             ?? null,
            ':days_absent'             => $d['days_absent']             ?? null,
            ':days_paid_leave'         => $d['days_paid_leave']         ?? 0,
            ':working_days_in_month'   => $d['working_days_in_month']   ?? 22,
            ':remarks'                 => $d['remarks']                 ?? null,
            ':status'                  => $d['status']                  ?? 'pending',
            ':processed_by'            => $d['processed_by']            ?? null,
        ]);
    }

    /**
     * Get all salary deductions for a payroll record.
     */
    public static function getSalaryDeductions(int $payrollId): array
    {
        try {
            $stmt = self::db()->prepare('
                SELECT sd.*, u.name AS created_by_name
                FROM salary_deductions sd
                LEFT JOIN users u ON u.id = sd.created_by
                WHERE sd.payroll_id = ?
                ORDER BY sd.created_at ASC
            ');
            $stmt->execute([$payrollId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Add a salary deduction to a payroll record.
     * Also re-computes and updates the payroll total_deductions and net_pay.
     */
    public static function addSalaryDeduction(int $payrollId, array $d, int $userId): bool
    {
        $db = self::db();

        // Auto-create table if missing (graceful bootstrap)
        $db->exec('
            CREATE TABLE IF NOT EXISTS `salary_deductions` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `payroll_id`  INT UNSIGNED NOT NULL,
                `reason`      VARCHAR(100) NOT NULL,
                `description` VARCHAR(255) DEFAULT NULL,
                `amount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `notes`       TEXT NOT NULL,
                `created_by`  INT UNSIGNED DEFAULT NULL,
                `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_payroll_id` (`payroll_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');

        $stmt = $db->prepare('
            INSERT INTO salary_deductions (payroll_id, reason, description, amount, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $inserted = (bool) $stmt->execute([
            $payrollId,
            $d['reason'],
            $d['description'] ?? null,
            (float)$d['amount'],
            $d['notes'],
            $userId,
        ]);

        if (!$inserted) return false;

        // Recalculate total salary_deduction for this payroll record
        $sumStmt = $db->prepare('SELECT COALESCE(SUM(amount),0) FROM salary_deductions WHERE payroll_id = ?');
        $sumStmt->execute([$payrollId]);
        $totalSalaryDed = (float)$sumStmt->fetchColumn();

        // Update payroll_records: salary_deduction, total_deductions, net_pay
        $updateStmt = $db->prepare('
            UPDATE payroll_records
            SET salary_deduction  = :sd,
                total_deductions  = withholding_tax + sss_ee + philhealth_ee + pagibig_ee
                                    + absent_deduction + unpaid_leave_deduction + :sd2
                                    + other_deductions,
                net_pay           = gross_pay - (withholding_tax + sss_ee + philhealth_ee + pagibig_ee
                                    + absent_deduction + unpaid_leave_deduction + :sd3
                                    + other_deductions)
            WHERE id = :id
        ');
        return (bool) $updateStmt->execute([
            ':sd'  => $totalSalaryDed,
            ':sd2' => $totalSalaryDed,
            ':sd3' => $totalSalaryDed,
            ':id'  => $payrollId,
        ]);
    }

    /**
     * Total salary_deduction sum for YTD — include in Deductions YTD block.
     */
    public static function getYTDByEmployee(int $employeeId, string $upToPeriod): array
    {
        $year = (int) substr($upToPeriod, 0, 4);
        $stmt = self::db()->prepare('
            SELECT
                COALESCE(SUM(gross_pay - allowance), 0) AS ytd_basic, -- actual basic earned (excludes proration gaps)
                COALESCE(SUM(allowance),          0) AS ytd_allowance,
                COALESCE(SUM(gross_pay),          0) AS ytd_gross,
                COALESCE(SUM(sss_ee),             0) AS ytd_sss_ee,
                COALESCE(SUM(sss_er),             0) AS ytd_sss_er,
                COALESCE(SUM(philhealth_ee),      0) AS ytd_philhealth_ee,
                COALESCE(SUM(philhealth_er),      0) AS ytd_philhealth_er,
                COALESCE(SUM(pagibig_ee),         0) AS ytd_pagibig_ee,
                COALESCE(SUM(pagibig_er),         0) AS ytd_pagibig_er,
                COALESCE(SUM(withholding_tax),    0) AS ytd_tax,
                COALESCE(SUM(other_deductions),   0) AS ytd_reconciliation,
                COALESCE(SUM(absent_deduction),   0) AS ytd_absent_deduction,
                COALESCE(SUM(unpaid_leave_deduction), 0) AS ytd_unpaid_leave,
                COALESCE(SUM(salary_deduction),   0) AS ytd_salary_deduction,
                COALESCE(SUM(total_deductions),   0) AS ytd_deductions,
                COALESCE(SUM(net_pay),            0) AS ytd_net,
                COUNT(*)                              AS ytd_periods
            FROM payroll_records
            WHERE employee_id = ?
              AND period LIKE ?
              AND period <= ?
        ');
        $stmt->execute([$employeeId, $year . '-%', $upToPeriod]);
        return $stmt->fetch() ?: [];
    }

    public static function release(int $payrollId): bool
    {
        $stmt = self::db()->prepare("UPDATE payroll_records SET status = 'released', released_at = NOW() WHERE id = ?");
        return (bool) $stmt->execute([$payrollId]);
    }

    /**
     * Update the status of a payroll record.
     * Allowed statuses: released, pending, modification
     */
    public static function updateStatus(int $payrollId, string $status): bool
    {
        $allowed = ['released', 'pending', 'modification'];
        if (!in_array($status, $allowed, true)) return false;
        $releasedAt = $status === 'released' ? ', released_at = NOW()' : '';
        $stmt = self::db()->prepare("UPDATE payroll_records SET status = :status{$releasedAt} WHERE id = :id");
        return (bool) $stmt->execute([':status' => $status, ':id' => $payrollId]);
    }

    /**
     * Save a note for a payroll record into payroll_notes table.
     * Creates the table if it doesn't exist yet (graceful bootstrap).
     */
    public static function addNote(int $payrollId, string $note, int $userId): bool
    {
        // Auto-create table if missing
        self::db()->exec("
            CREATE TABLE IF NOT EXISTS `payroll_notes` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `payroll_id` INT UNSIGNED NOT NULL,
                `note`       VARCHAR(100) NOT NULL,
                `created_by` INT UNSIGNED DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_payroll_id` (`payroll_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $stmt = self::db()->prepare("INSERT INTO payroll_notes (payroll_id, note, created_by) VALUES (?, ?, ?)");
        return (bool) $stmt->execute([$payrollId, $note, $userId]);
    }

    /**
     * Get all notes for a payroll record ordered ASC by date.
     */
    public static function getNotes(int $payrollId): array
    {
        // Return empty if table doesn't exist yet
        try {
            $stmt = self::db()->prepare("SELECT pn.*, u.name AS created_by_name FROM payroll_notes pn LEFT JOIN users u ON u.id = pn.created_by WHERE pn.payroll_id = ? ORDER BY pn.created_at ASC");
            $stmt->execute([$payrollId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public static function releaseAllForPeriod(string $period): bool
    {
        $stmt = self::db()->prepare("UPDATE payroll_records SET status = 'released', released_at = NOW() WHERE period = ? AND status = 'pending'");
        return (bool) $stmt->execute([$period]);
    }

    /** Convenience wrapper around PhilippineDeductions::computeAll() */
    public static function computeForEmployee(array $employee): array
    {
        return PhilippineDeductions::computeAll(
            (float) $employee['basic_salary'],
            (float) ($employee['allowance'] ?? 0)
        );
    }

    // ════════════════════════════════════════════════════════
    //  EMPLOYEE PAYROLL SETTINGS (semi-monthly)
    // ════════════════════════════════════════════════════════

    public static function getSettings(int $employeeId): array
    {
        $stmt = self::db()->prepare(
            'SELECT cutoff1_fixed_amount, tax_method, gov_deduction_mode FROM employees WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$employeeId]);
        $row = $stmt->fetch();
        return [
            'cutoff1_fixed_amount' => $row['cutoff1_fixed_amount'] ?? null,
            'tax_method'           => $row['tax_method']           ?? 'half_monthly',
            'gov_deduction_mode'   => $row['gov_deduction_mode']   ?? 'second_cutoff',
        ];
    }

    public static function updateSettings(int $employeeId, array $s): bool
    {
        $stmt = self::db()->prepare(
            'UPDATE employees
             SET cutoff1_fixed_amount = :c1,
                 tax_method           = :tm,
                 gov_deduction_mode   = :gm
             WHERE id = :id'
        );
        return $stmt->execute([
            ':c1' => $s['cutoff1_fixed_amount'] !== '' ? (float) $s['cutoff1_fixed_amount'] : null,
            ':tm' => in_array($s['tax_method'], ['half_monthly', 'bir_table']) ? $s['tax_method'] : 'half_monthly',
            ':gm' => in_array($s['gov_deduction_mode'], ['second_cutoff', 'split']) ? $s['gov_deduction_mode'] : 'second_cutoff',
            ':id' => $employeeId,
        ]);
    }

    // ════════════════════════════════════════════════════════
    //  YEAR-TO-DATE AGGREGATES
    // ════════════════════════════════════════════════════════

    public static function getTotalWithholdingTaxByYear(int $employeeId, int $year): float
    {
        $stmt = self::db()->prepare(
            'SELECT COALESCE(SUM(withholding_tax), 0) FROM payroll_records WHERE employee_id = ? AND period LIKE ?'
        );
        $stmt->execute([$employeeId, $year . '-%']);
        return (float) $stmt->fetchColumn();
    }

    public static function getTotalGovDedsByYear(int $employeeId, int $year): float
    {
        $stmt = self::db()->prepare(
            'SELECT COALESCE(SUM(sss_ee + philhealth_ee + pagibig_ee), 0) FROM payroll_records WHERE employee_id = ? AND period LIKE ?'
        );
        $stmt->execute([$employeeId, $year . '-%']);
        return (float) $stmt->fetchColumn();
    }

    public static function getTotalBasicByYear(int $employeeId, int $year): float
    {
        // actual basic earned = gross_pay minus allowance (excludes proration gaps for new hires)
        $stmt = self::db()->prepare(
            'SELECT COALESCE(SUM(gross_pay - allowance), 0) FROM payroll_records WHERE employee_id = ? AND period LIKE ?'
        );
        $stmt->execute([$employeeId, $year . '-%']);
        return (float) $stmt->fetchColumn();
    }

    // ════════════════════════════════════════════════════════
    //  13TH MONTH PAY
    // ════════════════════════════════════════════════════════

    /**
     * Compute 13th month pay for all active employees for a given year.
     * Formula (PD 851): Total basic salary EARNED in the year ÷ 12.
     * Uses (gross_pay − allowance) instead of basic_salary column to correctly
     * exclude proration gaps for employees hired mid-cutoff.
     */
    public static function compute13thMonth(int $year): array
    {
        // BUG FIX: The original query used backslash-escaped single quotes (\'active\')
        // inside a PHP double-quoted heredoc string. PHP does NOT treat \' as an escape
        // sequence inside double-quoted strings — it produces a literal backslash + quote,
        // generating invalid SQL that causes a PDO syntax error at runtime.
        // Fixed by using a regular double-quoted string with single-quoted SQL literals.
        $stmt = self::db()->prepare("
            SELECT
                e.id            AS employee_id,
                e.name          AS employee_name,
                e.employee_no,
                d.name          AS department,
                p.name          AS position,
                e.basic_salary  AS current_basic,
                e.date_hired,
                e.status        AS emp_status,
                COALESCE(pr.total_basic, 0)       AS total_basic_earned,
                COALESCE(pr.months_worked, 0)     AS months_worked,
                COALESCE(pr.total_basic, 0) / 12  AS thirteenth_month_pay
            FROM employees e
            JOIN departments d ON d.id = e.department_id
            JOIN positions   p ON p.id = e.position_id
            LEFT JOIN (
                SELECT
                    employee_id,
                    SUM(gross_pay - allowance) AS total_basic, -- actual basic earned per PD 851 (excl. proration gaps)
                    COUNT(*) / 2.0      AS months_worked
                FROM payroll_records
                WHERE period LIKE ?
                  AND period NOT LIKE '%-0'
                GROUP BY employee_id
            ) pr ON pr.employee_id = e.id
            WHERE e.status IN ('active', 'on_leave')
            ORDER BY e.name
        ");
        $stmt->execute([$year . '-%']);
        return $stmt->fetchAll();
    }

    public static function get13thMonthByYear(int $year): array
    {
        $stmt = self::db()->prepare('
            SELECT tm.*, e.name AS employee_name, e.employee_no,
                   d.name AS department, p.name AS position
            FROM thirteenth_month_pay tm
            JOIN employees e ON e.id = tm.employee_id
            JOIN departments d ON d.id = e.department_id
            JOIN positions p ON p.id = e.position_id
            WHERE tm.year = ?
            ORDER BY e.name
        ');
        $stmt->execute([$year]);
        return $stmt->fetchAll();
    }

    public static function get13thMonthByEmployee(int $employeeId, int $year): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT * FROM thirteenth_month_pay WHERE employee_id = ? AND year = ? LIMIT 1'
        );
        $stmt->execute([$employeeId, $year]);
        return $stmt->fetch() ?: null;
    }

    public static function thirteenthMonthExists(int $year): bool
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) FROM thirteenth_month_pay WHERE year = ?');
        $stmt->execute([$year]);
        return (bool) $stmt->fetchColumn();
    }

    public static function thirteenthMonthExistsForEmployee(int $employeeId, int $year): bool
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) FROM thirteenth_month_pay WHERE employee_id = ? AND year = ?');
        $stmt->execute([$employeeId, $year]);
        return (bool) $stmt->fetchColumn();
    }

    /** Upsert a 13th month record (insert or update if already exists). */
    public static function save13thMonth(array $d): bool
    {
        $stmt = self::db()->prepare('
            INSERT INTO thirteenth_month_pay
                (employee_id, year, total_basic_earned, months_worked, amount, status, processed_by)
            VALUES
                (:employee_id, :year, :total_basic_earned, :months_worked, :amount, :status, :processed_by)
            ON DUPLICATE KEY UPDATE
                total_basic_earned = VALUES(total_basic_earned),
                months_worked      = VALUES(months_worked),
                amount             = VALUES(amount),
                processed_by       = VALUES(processed_by),
                updated_at         = NOW()
        ');
        return (bool) $stmt->execute([
            ':employee_id'        => $d['employee_id'],
            ':year'               => $d['year'],
            ':total_basic_earned' => $d['total_basic_earned'],
            ':months_worked'      => $d['months_worked'],
            ':amount'             => $d['amount'],
            ':status'             => $d['status']        ?? 'pending',
            ':processed_by'       => $d['processed_by']  ?? null,
        ]);
    }

    public static function release13thMonth(int $id): bool
    {
        $stmt = self::db()->prepare("UPDATE thirteenth_month_pay SET status = 'released', released_at = NOW() WHERE id = ?");
        return (bool) $stmt->execute([$id]);
    }

    public static function releaseAll13thMonth(int $year): bool
    {
        $stmt = self::db()->prepare("UPDATE thirteenth_month_pay SET status = 'released', released_at = NOW() WHERE year = ? AND status = 'pending'");
        return (bool) $stmt->execute([$year]);
    }
}