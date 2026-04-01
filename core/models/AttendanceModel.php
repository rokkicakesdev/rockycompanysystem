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
     * Also counts scheduled working days (Mon–Fri) in the range,
     * excluding dates before the employee's hire date.
     *
     * @param int    $employeeId
     * @param string $dateFrom    Y-m-d  (e.g. 2026-01-01)
     * @param string $dateTo      Y-m-d  (e.g. 2026-01-15)
     * @param string $dateHired   Y-m-d  employee hire date (for proration)
     */
    public static function getCutoffSummary(
        int    $employeeId,
        string $dateFrom,
        string $dateTo,
        string $dateHired = ''
    ): array {
        // Clamp date range to hire date if employee was hired mid-cutoff
        $effectiveFrom = $dateFrom;
        if ($dateHired && $dateHired > $dateFrom) {
            $effectiveFrom = $dateHired;
        }

        // Count scheduled working days (Mon–Fri) in effective range,
        // excluding holidays and rest_days from attendance
        $scheduledDays = 0;
        $cur = new DateTime($effectiveFrom);
        $end = new DateTime($dateTo);
        while ($cur <= $end) {
            $dow = (int)$cur->format('N'); // 1=Mon … 7=Sun
            if ($dow <= 5) $scheduledDays++;
            $cur->modify('+1 day');
        }

        // Pull attendance records for employee within the cutoff range
        $stmt = self::db()->prepare('
            SELECT
              COALESCE(SUM(status IN ("present","late")),                         0) AS days_present,
              COALESCE(SUM(status = "absent"),                                    0) AS days_absent,
              COALESCE(SUM(status = "on_leave"),                                  0) AS days_on_leave,
              COALESCE(SUM(status = "late"),                                      0) AS days_late,
              COALESCE(SUM(status = "half_day"),                                  0) AS days_half,
              COALESCE(SUM(status IN ("holiday","rest_day")),                     0) AS days_nonwork,
              COALESCE(SUM(overtime_hours),                                       0) AS total_overtime,
              COALESCE(SUM(hours_worked),                                         0) AS total_hours
            FROM attendance
            WHERE employee_id = ? AND date >= ? AND date <= ?
        ');
        $stmt->execute([$employeeId, $effectiveFrom, $dateTo]);
        $row = $stmt->fetch() ?: [];

        // Days worked = present + late (late means they showed up)
        // Days paid = present + late + on_leave + holiday
        // Absent days = scheduled days not covered by any attendance status,
        //               plus explicitly marked absent
        $daysPresent  = (int)($row['days_present']  ?? 0);
        $daysAbsent   = (int)($row['days_absent']   ?? 0);
        $daysOnLeave  = (int)($row['days_on_leave'] ?? 0);
        $daysHalf     = (int)($row['days_half']     ?? 0);
        $daysNonWork  = (int)($row['days_nonwork']  ?? 0);
        $totalLogged  = $daysPresent + $daysAbsent + $daysOnLeave + $daysHalf + $daysNonWork;

        // Weekdays with no attendance log at all are treated as absent
        $unloggedAbsent = max(0, $scheduledDays - $totalLogged + $daysNonWork);

        // Total effective absences (explicit + unlogged weekdays)
        $totalAbsent = $daysAbsent + ($scheduledDays - ($daysPresent + $daysAbsent + $daysOnLeave + $daysHalf));
        $totalAbsent = max(0, $totalAbsent);

        return [
            'scheduled_days' => $scheduledDays,     // working days employee is expected
            'effective_from' => $effectiveFrom,      // actual start (clamped to hire date)
            'days_present'   => $daysPresent,
            'days_absent'    => $daysAbsent,
            'days_on_leave'  => $daysOnLeave,
            'days_late'      => (int)($row['days_late'] ?? 0),
            'days_half'      => $daysHalf,
            'days_nonwork'   => $daysNonWork,
            'total_absent'   => $totalAbsent,        // used for deduction
            'total_overtime' => (float)($row['total_overtime'] ?? 0),
            'total_hours'    => (float)($row['total_hours']    ?? 0),
        ];
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