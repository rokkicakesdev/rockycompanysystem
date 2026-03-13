<?php
// core/models/DashboardModel.php
// ─────────────────────────────────────────────────────────────────────────────
//  Aggregated stats queries for dashboard widgets.
//  Extracted from Model.php (God Class split).
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/BaseModel.php';

class DashboardModel extends BaseModel
{
    public static function getStats(): array
    {
        $db = self::db();
        return [
            'total_employees'    => (int) $db->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn(),
            'total_departments'  => (int) $db->query('SELECT COUNT(*) FROM departments')->fetchColumn(),
            'pending_leaves'     => (int) $db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn(),
            'open_jobs'          => (int) $db->query("SELECT COUNT(*) FROM job_postings WHERE status = 'open'")->fetchColumn(),
            'new_applicants'     => (int) $db->query("SELECT COUNT(*) FROM applicants WHERE status = 'new'")->fetchColumn(),
            // NOTE: these two queries use the old YYYY-MM period format and will return 0
            // now that periods are YYYY-MM-C. They are left as-is for backward compatibility
            // and can be updated to SUM over both cutoffs of the current month if needed.
            'this_month_payroll' => (float) $db->query("SELECT COALESCE(SUM(net_pay),0) FROM payroll_records WHERE period = DATE_FORMAT(NOW(),'%Y-%m')")->fetchColumn(),
            'last_month_payroll' => (float) $db->query("SELECT COALESCE(SUM(net_pay),0) FROM payroll_records WHERE period = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH),'%Y-%m')")->fetchColumn(),
            'resigned_this_year' => (int) $db->query("SELECT COUNT(*) FROM employees WHERE status IN ('resigned','terminated') AND YEAR(date_separated) = YEAR(NOW())")->fetchColumn(),
        ];
    }

    public static function getHeadcountByDepartment(): array
    {
        return self::db()->query("
            SELECT d.name AS department, COUNT(e.id) AS count
            FROM departments d
            LEFT JOIN employees e ON e.department_id = d.id AND e.status = 'active'
            GROUP BY d.id, d.name ORDER BY count DESC
        ")->fetchAll();
    }
}
