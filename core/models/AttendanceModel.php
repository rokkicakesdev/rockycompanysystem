<?php
// core/models/AttendanceModel.php
// ─────────────────────────────────────────────────────────────────────────────
//  Handles attendance table operations.
//  Extracted from Model.php (God Class split).
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/BaseModel.php';

class AttendanceModel extends BaseModel
{
    public static function getByMonth(string $yearMonth): array
    {
        $stmt = self::db()->prepare('
            SELECT a.*, e.name AS employee_name, e.employee_no, d.name AS department
            FROM attendance a
            JOIN employees e ON e.id = a.employee_id
            JOIN departments d ON d.id = e.department_id
            WHERE DATE_FORMAT(a.date, "%Y-%m") = ?
            ORDER BY a.date, e.name
        ');
        $stmt->execute([$yearMonth]);
        return $stmt->fetchAll();
    }

    public static function getByEmployee(int $employeeId, string $yearMonth = ''): array
    {
        if ($yearMonth) {
            $stmt = self::db()->prepare('
                SELECT * FROM attendance WHERE employee_id = ? AND DATE_FORMAT(date, "%Y-%m") = ?
                ORDER BY date DESC
            ');
            $stmt->execute([$employeeId, $yearMonth]);
        } else {
            $stmt = self::db()->prepare('SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 60');
            $stmt->execute([$employeeId]);
        }
        return $stmt->fetchAll();
    }

    public static function getSummary(int $employeeId, string $yearMonth): array
    {
        $stmt = self::db()->prepare('
            SELECT
              COUNT(*) AS total_records,
              COALESCE(SUM(status = "present"),  0) AS days_present,
              COALESCE(SUM(status = "absent"),   0) AS days_absent,
              COALESCE(SUM(status = "on_leave"), 0) AS days_on_leave,
              COALESCE(SUM(status = "late"),     0) AS days_late,
              COALESCE(SUM(status = "half_day"), 0) AS days_half,
              COALESCE(SUM(status = "holiday"),  0) AS days_holiday,
              COALESCE(SUM(overtime_hours), 0) AS total_overtime,
              COALESCE(SUM(hours_worked),   0) AS total_hours
            FROM attendance
            WHERE employee_id = ? AND DATE_FORMAT(date, "%Y-%m") = ?
        ');
        $stmt->execute([$employeeId, $yearMonth]);
        return $stmt->fetch() ?: [];
    }

    /**
     * Get attendance summary for a specific cutoff date range.
     * Excludes holidays from scheduled days, uses date_start for proration,
     * and properly classifies paid vs unpaid leave.
     *
     * @param int    $employeeId
     * @param string $dateFrom   Y-m-d  cutoff start (e.g. 2026-01-01)
     * @param string $dateTo     Y-m-d  cutoff end   (e.g. 2026-01-15)
     * @param string $dateStart  Y-m-d  employee actual first day (date_start column)
     *
     * KEY DISTINCTION:
     *   - scheduled_days  = ALL weekdays in the FULL cutoff (dateFrom→dateTo), INCLUDING
     *                       public holidays. Holidays are PAID days off — they are part of
     *                       the salary structure and must be counted in the schedule denominator.
     *                       Excluding them would understate the period (e.g. 10 vs 11 days when
     *                       Jan 1 holiday falls on a Thursday in a Jan 1–15 cutoff).
     *                       This is shown on the payslip "Scheduled Days" box and used as the
     *                       daily rate denominator.
     *   - effective_scheduled_days = ALL weekdays from dateStart (or dateFrom if dateStart is
     *                       before the cutoff), also including holidays on those days.
     *                       The GAP (scheduled_days − effective_scheduled_days) = weekdays
     *                       the employee did not work because they had not yet started.
     *                       This drives the proration deduction in payroll generation.
     *   Days before dateStart are NOT counted as absent — they generate a proration deduction
     *   instead. Absent days are only counted from dateStart onward.
     */
    public static function getCutoffSummary(
        int    $employeeId,
        string $dateFrom,
        string $dateTo,
        string $dateStart = ''
    ): array {
        // ── Clamp effective attendance range to employee's start date ────────
        // Days BEFORE dateStart are never counted as absent or present.
        $effectiveFrom = $dateFrom;
        if ($dateStart && $dateStart > $dateFrom) {
            $effectiveFrom = $dateStart;
        }

        // ── Fetch public holidays in the full cutoff range ───────────────────
        $holStmt = self::db()->prepare(
            'SELECT date FROM holidays WHERE date BETWEEN ? AND ?'
        );
        $holStmt->execute([$dateFrom, $dateTo]);
        $holidayDates = array_flip($holStmt->fetchAll(\PDO::FETCH_COLUMN));

        // ── Scheduled days = ALL weekdays in FULL cutoff range (holidays INCLUDED) ──
        // Holidays are PAID days off — they still count as part of the salary structure.
        // A New Year holiday falling on Thursday is still a "scheduled" day for which
        // the employee is compensated. Excluding it from the count would understate the
        // total period (10 instead of 11 for Jan 1–15 when Jan 1 is a holiday).
        // The daily rate denominator uses scheduledDays so the proration is correct.
        $scheduledDays = 0;
        $cur = new DateTime($dateFrom);
        $end = new DateTime($dateTo);
        while ($cur <= $end) {
            $dow = (int)$cur->format('N');
            if ($dow <= 5) {          // count ALL weekdays regardless of holiday status
                $scheduledDays++;
            }
            $cur->modify('+1 day');
        }

        // ── Effective scheduled days = ALL weekdays from dateStart onward ────
        // Counts ALL weekdays from the employee's first expected day, including
        // any holidays that fall within that range.
        // The gap (scheduledDays − effectiveScheduledDays) represents the number
        // of weekdays the employee did not work because they had not yet started —
        // this drives the proration deduction in payroll.php.
        $effectiveScheduledDays = 0;
        $cur = new DateTime($effectiveFrom);
        while ($cur <= $end) {
            $dow = (int)$cur->format('N');
            if ($dow <= 5) {          // count ALL weekdays from dateStart
                $effectiveScheduledDays++;
            }
            $cur->modify('+1 day');
        }

        // ── Pull individual attendance rows from effectiveFrom ────────────────
        $stmt = self::db()->prepare('
            SELECT date, status, leave_type, overtime_hours, hours_worked
            FROM attendance
            WHERE employee_id = ? AND date >= ? AND date <= ?
            ORDER BY date
        ');
        $stmt->execute([$employeeId, $effectiveFrom, $dateTo]);
        $attRows   = $stmt->fetchAll();
        $attByDate = [];
        foreach ($attRows as $row) {
            $attByDate[$row['date']] = $row;
        }

        // ── Paid leave types ────────────────────────────────────────────────
        $paidTypes = [
            'sick','vacation','bereavement','emergency','sil',
            'maternity','paternity','solo_parent','vawc','magna_carta'
        ];

        $daysPresent     = 0;
        $daysAbsent      = 0;
        $daysPaidLeave   = 0;
        $daysUnpaidLeave = 0;
        $daysHalf        = 0;
        $totalOvertime   = 0.0;
        $totalHours      = 0.0;

        // Walk every scheduled weekday from effectiveFrom (employee's first day)
        $cur = new DateTime($effectiveFrom);
        while ($cur <= $end) {
            $ds  = $cur->format('Y-m-d');
            $dow = (int)$cur->format('N');
            $cur->modify('+1 day');

            // Skip weekends & holidays — never absent
            if ($dow > 5 || isset($holidayDates[$ds])) continue;

            if (isset($attByDate[$ds])) {
                $att    = $attByDate[$ds];
                $status = $att['status'];
                $totalOvertime += (float)($att['overtime_hours'] ?? 0);
                $totalHours    += (float)($att['hours_worked']   ?? 0);

                switch ($status) {
                    case 'present':
                    case 'late':
                        $daysPresent++;
                        break;
                    case 'half_day':
                        $daysHalf++;
                        break;
                    case 'absent':
                        $daysAbsent++;
                        break;
                    case 'on_leave':
                        $lt = $att['leave_type'] ?? '';
                        if ($lt === 'unpaid') {
                            $daysUnpaidLeave++;
                        } else {
                            $daysPaidLeave++;
                        }
                        break;
                    // holiday/rest_day logged in attendance — skip
                }
            } else {
                // No attendance record for a scheduled weekday from dateStart → absent
                $daysAbsent++;
            }
        }

        // Total absences for deduction: explicit absent + unpaid leave
        $totalAbsent = $daysAbsent + $daysUnpaidLeave;

        return [
            'scheduled_days'          => $scheduledDays,          // FULL cutoff weekdays (for payslip display & daily rate)
            'effective_scheduled_days'=> $effectiveScheduledDays,  // From dateStart onward (for internal reference)
            'effective_from'          => $effectiveFrom,
            'days_present'            => $daysPresent,
            'days_absent'             => $daysAbsent,
            'days_on_leave'           => $daysPaidLeave,
            'days_unpaid_leave'       => $daysUnpaidLeave,
            'days_late'               => 0,
            'days_half'               => $daysHalf,
            'total_absent'            => $totalAbsent,
            'total_overtime'          => $totalOvertime,
            'total_hours'             => $totalHours,
        ];
    }

    /**
     * Count approved unpaid leave attendance rows for an employee in a cutoff.
     */
    public static function getUnpaidLeaveDaysInCutoff(
        int    $employeeId,
        string $dateFrom,
        string $dateTo
    ): int {
        $stmt = self::db()->prepare("
            SELECT COUNT(*) FROM attendance
            WHERE employee_id = ?
              AND date >= ? AND date <= ?
              AND status = 'on_leave'
              AND leave_type = 'unpaid'
        ");
        $stmt->execute([$employeeId, $dateFrom, $dateTo]);
        return (int) $stmt->fetchColumn();
    }

    public static function save(array $data): bool
    {
        $stmt = self::db()->prepare('
            INSERT INTO attendance
              (employee_id, date, time_in, time_out, status, leave_type,
               remarks, hours_worked, is_overtime, overtime_hours, created_by)
            VALUES
              (:employee_id, :date, :time_in, :time_out, :status, :leave_type,
               :remarks, :hours_worked, :is_overtime, :overtime_hours, :created_by)
            ON DUPLICATE KEY UPDATE
              time_in        = VALUES(time_in),
              time_out       = VALUES(time_out),
              status         = VALUES(status),
              leave_type     = VALUES(leave_type),
              remarks        = VALUES(remarks),
              hours_worked   = VALUES(hours_worked),
              is_overtime    = VALUES(is_overtime),
              overtime_hours = VALUES(overtime_hours)
        ');
        return (bool) $stmt->execute([
            ':employee_id'   => $data['employee_id'],
            ':date'          => $data['date'],
            ':time_in'       => $data['time_in']       ?? null,
            ':time_out'      => $data['time_out']      ?? null,
            ':status'        => $data['status']        ?? 'present',
            ':leave_type'    => $data['leave_type']    ?? null,
            ':remarks'       => $data['remarks']       ?? null,
            ':hours_worked'  => $data['hours_worked']  ?? null,
            ':is_overtime'   => $data['is_overtime']   ?? 0,
            ':overtime_hours'=> $data['overtime_hours'] ?? 0,
            ':created_by'    => $data['created_by']    ?? null,
        ]);
    }
}