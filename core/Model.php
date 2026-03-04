<?php
// ============================================================
//  Rocky HRIS + Payroll System — Model v2.0
//  Covers: Users, Employees, Departments, Positions,
//          Attendance, Leave, Recruitment, Payroll,
//          Announcements, Holidays, Salary History
// ============================================================

if (!class_exists('Database')) {
    // Auto-load Database + config if not already loaded
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/Database.php';
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/PhilippineDeductions.php';

class Model {

    /**
     * Get PDO database connection (singleton)
     * @return PDO
     */
    protected static function db(): PDO {
        return Database::getInstance();
    }

    // ════════════════════════════════════════════════════════
    //  USERS
    // ════════════════════════════════════════════════════════

    public static function getAllUsers(): array {
        return self::db()->query('SELECT * FROM users ORDER BY role, name')->fetchAll();
    }

    public static function findUserByUsername(string $username): ?array {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public static function findUserById(int $id): ?array {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function createUser(array $data): bool {
        $stmt = self::db()->prepare('
            INSERT INTO users (name, username, email, password, role, status, created_by)
            VALUES (:name, :username, :email, :password, :role, :status, :created_by)
        ');
        return (bool) $stmt->execute([
            ':name'       => $data['name'],
            ':username'   => $data['username'],
            ':email'      => $data['email'],
            ':password'   => password_hash($data['password'], PASSWORD_BCRYPT),
            ':role'       => $data['role']       ?? 'admin',
            ':status'     => $data['status']     ?? 'active',
            ':created_by' => $data['created_by'] ?? null,
        ]);
    }

    public static function updateUser(int $id, array $data): bool {
        $stmt = self::db()->prepare('
            UPDATE users SET name = :name, email = :email, role = :role, status = :status
            WHERE id = :id
        ');
        return (bool) $stmt->execute([
            ':name'   => $data['name'],
            ':email'  => $data['email'],
            ':role'   => $data['role'],
            ':status' => $data['status'],
            ':id'     => $id,
        ]);
    }

    public static function updateUserPassword(int $id, string $newPassword): bool {
        $stmt = self::db()->prepare('UPDATE users SET password = ? WHERE id = ?');
        return (bool) $stmt->execute([password_hash($newPassword, PASSWORD_BCRYPT), $id]);
    }

    public static function updateUserStatus(int $id, string $status): bool {
        $stmt = self::db()->prepare('UPDATE users SET status = ? WHERE id = ?');
        return (bool) $stmt->execute([$status, $id]);
    }

    // ════════════════════════════════════════════════════════
    //  DEPARTMENTS & POSITIONS
    // ════════════════════════════════════════════════════════

    public static function getAllDepartments(): array {
        return self::db()->query('SELECT * FROM departments ORDER BY name')->fetchAll();
    }

    public static function findDepartmentById(int $id): ?array {
        $stmt = self::db()->prepare('SELECT * FROM departments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function createDepartment(string $name): bool {
        $stmt = self::db()->prepare('INSERT INTO departments (name) VALUES (?)');
        return (bool) $stmt->execute([$name]);
    }

    public static function updateDepartment(int $id, string $name): bool {
        $stmt = self::db()->prepare('UPDATE departments SET name = ? WHERE id = ?');
        return (bool) $stmt->execute([$name, $id]);
    }

    public static function getAllPositions(): array {
        return self::db()->query('
            SELECT p.*, d.name AS department_name
            FROM positions p JOIN departments d ON d.id = p.department_id
            ORDER BY d.name, p.name
        ')->fetchAll();
    }

    public static function getPositionsByDepartment(int $deptId): array {
        $stmt = self::db()->prepare('SELECT * FROM positions WHERE department_id = ? ORDER BY name');
        $stmt->execute([$deptId]);
        return $stmt->fetchAll();
    }

    public static function createPosition(int $deptId, string $name): bool {
        $stmt = self::db()->prepare('INSERT INTO positions (department_id, name) VALUES (?, ?)');
        return (bool) $stmt->execute([$deptId, $name]);
    }

    // ════════════════════════════════════════════════════════
    //  EMPLOYEES
    // ════════════════════════════════════════════════════════

    public static function getAllEmployees(string $status = ''): array {
        if ($status) {
            $stmt = self::db()->prepare('SELECT * FROM v_employees WHERE status = ? ORDER BY name');
            $stmt->execute([$status]);
            return $stmt->fetchAll();
        }
        return self::db()->query('SELECT * FROM v_employees ORDER BY name')->fetchAll();
    }

    public static function findEmployeeById(int $id): ?array {
        $stmt = self::db()->prepare('
            SELECT e.*, d.name AS department, d.id AS department_id,
                   p.name AS position, p.id AS position_id,
                   (e.basic_salary + e.allowance) AS gross_pay
            FROM employees e
            JOIN departments d ON d.id = e.department_id
            JOIN positions p ON p.id = e.position_id
            WHERE e.id = ? LIMIT 1
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function countActiveEmployees(): int {
        $row = self::db()->query("SELECT COUNT(*) AS cnt FROM employees WHERE status = 'active'")->fetch();
        return (int)$row['cnt'];
    }

    public static function countEmployeesByStatus(): array {
        return self::db()->query('
            SELECT status, COUNT(*) AS cnt FROM employees GROUP BY status
        ')->fetchAll();
    }

    public static function createEmployee(array $data): bool {
        $stmt = self::db()->prepare('
            INSERT INTO employees
              (name, gender, civil_status, birthdate, address,
               email, phone, sss_no, philhealth_no, pagibig_no, tin_no,
               department_id, position_id, basic_salary, allowance,
               date_hired, employment_type, status,
               sick_leave_balance, vacation_leave_balance,
               bereavement_leave_balance, emergency_leave_balance,
               sil_balance, maternity_leave_balance, paternity_leave_balance,
               solo_parent_leave_balance, vawc_leave_balance, magna_carta_leave_balance,
               emergency_contact_name, emergency_contact_phone, emergency_contact_relation)
            VALUES
              (:name, :gender, :civil_status, :birthdate, :address,
               :email, :phone, :sss_no, :philhealth_no, :pagibig_no, :tin_no,
               :department_id, :position_id, :basic_salary, :allowance,
               :date_hired, :employment_type, :status,
               :sick_leave_balance, :vacation_leave_balance,
               :bereavement_leave_balance, :emergency_leave_balance,
               :sil_balance, :maternity_leave_balance, :paternity_leave_balance,
               :solo_parent_leave_balance, :vawc_leave_balance, :magna_carta_leave_balance,
               :emergency_contact_name, :emergency_contact_phone, :emergency_contact_relation)
        ');
        return (bool) $stmt->execute([
            ':name'                      => $data['name'],
            ':gender'                    => $data['gender']                    ?? null,
            ':civil_status'              => $data['civil_status']              ?? null,
            ':birthdate'                 => $data['birthdate']                 ?? null,
            ':address'                   => $data['address']                   ?? null,
            ':email'                     => $data['email']                     ?? null,
            ':phone'                     => $data['phone']                     ?? null,
            ':sss_no'                    => $data['sss_no']                    ?? null,
            ':philhealth_no'             => $data['philhealth_no']             ?? null,
            ':pagibig_no'                => $data['pagibig_no']                ?? null,
            ':tin_no'                    => $data['tin_no']                    ?? null,
            ':department_id'             => $data['department_id'],
            ':position_id'               => $data['position_id'],
            ':basic_salary'              => $data['basic_salary'],
            ':allowance'                 => $data['allowance']                 ?? 0,
            ':date_hired'                => $data['date_hired'],
            ':employment_type'           => $data['employment_type']           ?? 'regular',
            ':status'                    => $data['status']                    ?? 'active',
            ':sick_leave_balance'        => $data['sick_leave_balance']        ?? 10,
            ':vacation_leave_balance'    => $data['vacation_leave_balance']    ?? 10,
            ':bereavement_leave_balance' => $data['bereavement_leave_balance'] ?? 5,
            ':emergency_leave_balance'   => $data['emergency_leave_balance']   ?? 5,
            ':sil_balance'               => $data['sil_balance']               ?? 5,
            ':maternity_leave_balance'   => $data['maternity_leave_balance']   ?? 105,
            ':paternity_leave_balance'   => $data['paternity_leave_balance']   ?? 7,
            ':solo_parent_leave_balance' => $data['solo_parent_leave_balance'] ?? 7,
            ':vawc_leave_balance'        => $data['vawc_leave_balance']        ?? 10,
            ':magna_carta_leave_balance' => $data['magna_carta_leave_balance'] ?? 60,
            ':emergency_contact_name'     => $data['emergency_contact_name']    ?? null,
            ':emergency_contact_phone'    => $data['emergency_contact_phone']   ?? null,
            ':emergency_contact_relation' => $data['emergency_contact_relation'] ?? null,
        ]);
    }

    public static function updateEmployee(int $id, array $data): bool {
        // Save salary history if salary changed
        $current = self::findEmployeeById($id);
        if ($current &&
            ((float)$current['basic_salary'] !== (float)$data['basic_salary'] ||
             (float)$current['allowance']     !== (float)($data['allowance'] ?? 0))) {
            self::createSalaryHistory([
                'employee_id'      => $id,
                'old_basic_salary' => $current['basic_salary'],
                'new_basic_salary' => $data['basic_salary'],
                'old_allowance'    => $current['allowance'],
                'new_allowance'    => $data['allowance'] ?? 0,
                'reason'           => $data['salary_change_reason'] ?? 'Manual update',
                'effective_date'   => date('Y-m-d'),
                'approved_by'      => $data['updated_by'] ?? null,
            ]);
        }

        $stmt = self::db()->prepare('
            UPDATE employees SET
                name = :name, gender = :gender, civil_status = :civil_status,
                birthdate = :birthdate, address = :address,
                email = :email, phone = :phone,
                sss_no = :sss_no, philhealth_no = :philhealth_no,
                pagibig_no = :pagibig_no, tin_no = :tin_no,
                department_id = :department_id, position_id = :position_id,
                basic_salary = :basic_salary, allowance = :allowance,
                date_hired = :date_hired, employment_type = :employment_type,
                status = :status,
                sick_leave_balance = :sick_leave_balance,
                vacation_leave_balance = :vacation_leave_balance,
                bereavement_leave_balance = :bereavement_leave_balance,
                emergency_leave_balance = :emergency_leave_balance,
                sil_balance = :sil_balance,
                maternity_leave_balance = :maternity_leave_balance,
                paternity_leave_balance = :paternity_leave_balance,
                solo_parent_leave_balance = :solo_parent_leave_balance,
                vawc_leave_balance = :vawc_leave_balance,
                magna_carta_leave_balance = :magna_carta_leave_balance,
                emergency_contact_name = :emergency_contact_name,
                emergency_contact_phone = :emergency_contact_phone,
                emergency_contact_relation = :emergency_contact_relation
            WHERE id = :id
        ');
        return (bool) $stmt->execute([
            ':name'                      => $data['name'],
            ':gender'                    => $data['gender']                    ?? null,
            ':civil_status'              => $data['civil_status']              ?? null,
            ':birthdate'                 => $data['birthdate']                 ?? null,
            ':address'                   => $data['address']                   ?? null,
            ':email'                     => $data['email']                     ?? null,
            ':phone'                     => $data['phone']                     ?? null,
            ':sss_no'                    => $data['sss_no']                    ?? null,
            ':philhealth_no'             => $data['philhealth_no']             ?? null,
            ':pagibig_no'                => $data['pagibig_no']                ?? null,
            ':tin_no'                    => $data['tin_no']                    ?? null,
            ':department_id'             => $data['department_id'],
            ':position_id'               => $data['position_id'],
            ':basic_salary'              => $data['basic_salary'],
            ':allowance'                 => $data['allowance']                 ?? 0,
            ':date_hired'                => $data['date_hired'],
            ':employment_type'           => $data['employment_type']           ?? 'regular',
            ':status'                    => $data['status'],
            ':sick_leave_balance'        => $data['sick_leave_balance']        ?? 10,
            ':vacation_leave_balance'    => $data['vacation_leave_balance']    ?? 10,
            ':bereavement_leave_balance' => $data['bereavement_leave_balance'] ?? 5,
            ':emergency_leave_balance'   => $data['emergency_leave_balance']   ?? 5,
            ':sil_balance'               => $data['sil_balance']               ?? 5,
            ':maternity_leave_balance'   => $data['maternity_leave_balance']   ?? 105,
            ':paternity_leave_balance'   => $data['paternity_leave_balance']   ?? 7,
            ':solo_parent_leave_balance' => $data['solo_parent_leave_balance'] ?? 7,
            ':vawc_leave_balance'        => $data['vawc_leave_balance']        ?? 10,
            ':magna_carta_leave_balance' => $data['magna_carta_leave_balance'] ?? 60,
            ':emergency_contact_name'     => $data['emergency_contact_name']    ?? null,
            ':emergency_contact_phone'    => $data['emergency_contact_phone']   ?? null,
            ':emergency_contact_relation' => $data['emergency_contact_relation'] ?? null,
            ':id'                        => $id,
        ]);
    }

    public static function toggleEmployeeStatus(int $id, string $newStatus): bool {
        $stmt = self::db()->prepare('UPDATE employees SET status = ? WHERE id = ?');
        return (bool) $stmt->execute([$newStatus, $id]);
    }

    public static function searchEmployees(string $query): array {
        $like = '%' . $query . '%';
        $stmt = self::db()->prepare('
            SELECT * FROM v_employees
            WHERE name LIKE ? OR employee_no LIKE ? OR email LIKE ? OR department LIKE ? OR position LIKE ?
            ORDER BY name
        ');
        $stmt->execute([$like, $like, $like, $like, $like]);
        return $stmt->fetchAll();
    }

    public static function getEmployeesByDepartment(int $deptId): array {
        $stmt = self::db()->prepare("SELECT * FROM v_employees WHERE department_id = ? AND status = 'active' ORDER BY name");
        $stmt->execute([$deptId]);
        return $stmt->fetchAll();
    }

    // ════════════════════════════════════════════════════════
    //  ATTENDANCE
    // ════════════════════════════════════════════════════════

    public static function getAttendanceByMonth(string $yearMonth): array {
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

    public static function getAttendanceByEmployee(int $employeeId, string $yearMonth = ''): array {
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

    public static function getAttendanceSummary(int $employeeId, string $yearMonth): array {
        $stmt = self::db()->prepare('
            SELECT
              COUNT(*) AS total_records,
              SUM(status = "present")  AS days_present,
              SUM(status = "absent")   AS days_absent,
              SUM(status = "on_leave") AS days_on_leave,
              SUM(status = "late")     AS days_late,
              SUM(status = "half_day") AS days_half,
              SUM(status = "holiday")  AS days_holiday,
              COALESCE(SUM(overtime_hours), 0) AS total_overtime,
              COALESCE(SUM(hours_worked), 0) AS total_hours
            FROM attendance
            WHERE employee_id = ? AND DATE_FORMAT(date, "%Y-%m") = ?
        ');
        $stmt->execute([$employeeId, $yearMonth]);
        return $stmt->fetch() ?: [];
    }

    public static function saveAttendance(array $data): bool {
        $stmt = self::db()->prepare('
            INSERT INTO attendance
              (employee_id, date, time_in, time_out, status, leave_type,
               remarks, hours_worked, is_overtime, overtime_hours, created_by)
            VALUES
              (:employee_id, :date, :time_in, :time_out, :status, :leave_type,
               :remarks, :hours_worked, :is_overtime, :overtime_hours, :created_by)
            ON DUPLICATE KEY UPDATE
              time_in = VALUES(time_in), time_out = VALUES(time_out),
              status = VALUES(status), leave_type = VALUES(leave_type),
              remarks = VALUES(remarks), hours_worked = VALUES(hours_worked),
              is_overtime = VALUES(is_overtime), overtime_hours = VALUES(overtime_hours)
        ');
        return (bool) $stmt->execute([
            ':employee_id'    => $data['employee_id'],
            ':date'           => $data['date'],
            ':time_in'        => $data['time_in']       ?? null,
            ':time_out'       => $data['time_out']      ?? null,
            ':status'         => $data['status']        ?? 'present',
            ':leave_type'     => $data['leave_type']    ?? null,
            ':remarks'        => $data['remarks']       ?? null,
            ':hours_worked'   => $data['hours_worked']  ?? null,
            ':is_overtime'    => $data['is_overtime']   ?? 0,
            ':overtime_hours' => $data['overtime_hours'] ?? 0,
            ':created_by'     => $data['created_by']   ?? null,
        ]);
    }

    // ════════════════════════════════════════════════════════
    //  LEAVE REQUESTS
    // ════════════════════════════════════════════════════════

    public static function getAllLeaveRequests(string $status = ''): array {
        if ($status) {
            $stmt = self::db()->prepare('
                SELECT lr.*, e.name AS employee_name, e.employee_no, d.name AS department,
                       u.name AS reviewed_by_name
                FROM leave_requests lr
                JOIN employees e ON e.id = lr.employee_id
                JOIN departments d ON d.id = e.department_id
                LEFT JOIN users u ON u.id = lr.reviewed_by
                WHERE lr.status = ? ORDER BY lr.filed_at DESC
            ');
            $stmt->execute([$status]);
        } else {
            $stmt = self::db()->query('
                SELECT lr.*, e.name AS employee_name, e.employee_no, d.name AS department,
                       u.name AS reviewed_by_name
                FROM leave_requests lr
                JOIN employees e ON e.id = lr.employee_id
                JOIN departments d ON d.id = e.department_id
                LEFT JOIN users u ON u.id = lr.reviewed_by
                ORDER BY lr.filed_at DESC
            ');
        }
        return $stmt->fetchAll();
    }

    public static function getLeaveRequestsByEmployee(int $employeeId): array {
        $stmt = self::db()->prepare('
            SELECT lr.*, u.name AS reviewed_by_name
            FROM leave_requests lr
            LEFT JOIN users u ON u.id = lr.reviewed_by
            WHERE lr.employee_id = ? ORDER BY lr.filed_at DESC
        ');
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }

    public static function findLeaveRequestById(int $id): ?array {
        $stmt = self::db()->prepare('
            SELECT lr.*, e.name AS employee_name, e.employee_no
            FROM leave_requests lr JOIN employees e ON e.id = lr.employee_id
            WHERE lr.id = ? LIMIT 1
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function createLeaveRequest(array $data): bool {
        $stmt = self::db()->prepare('
            INSERT INTO leave_requests (employee_id, leave_type, date_from, date_to, days_applied, reason)
            VALUES (:employee_id, :leave_type, :date_from, :date_to, :days_applied, :reason)
        ');
        return (bool) $stmt->execute([
            ':employee_id'  => $data['employee_id'],
            ':leave_type'   => $data['leave_type'],
            ':date_from'    => $data['date_from'],
            ':date_to'      => $data['date_to'],
            ':days_applied' => $data['days_applied'],
            ':reason'       => $data['reason'] ?? null,
        ]);
    }

    public static function reviewLeaveRequest(int $id, string $status, int $reviewedBy, string $notes = ''): bool {
        $stmt = self::db()->prepare('
            UPDATE leave_requests
            SET status = :status, reviewed_by = :reviewed_by,
                reviewed_at = NOW(), review_notes = :notes
            WHERE id = :id
        ');
        $success = $stmt->execute([
            ':status'      => $status,
            ':reviewed_by' => $reviewedBy,
            ':notes'       => $notes,
            ':id'          => $id
        ]);

        if ((bool) $success && $status === 'approved') {
            $leave = self::findLeaveRequestById($id);
            if ($leave && $leave['leave_type'] !== 'unpaid') {
                $balanceFields = LEAVE_BALANCE_FIELDS;
                if (isset($balanceFields[$leave['leave_type']])) {
                    $field = $balanceFields[$leave['leave_type']];
                    $deductStmt = self::db()->prepare("
                        UPDATE employees SET {$field} = GREATEST(0, {$field} - :days) WHERE id = :emp_id
                    ");
                    $deductStmt->execute([
                        ':days'    => $leave['days_applied'],
                        ':emp_id'  => $leave['employee_id']
                    ]);
                }
            }
        }

        return (bool) $success;
    }

    public static function countPendingLeaves(): int {
        $row = self::db()->query("SELECT COUNT(*) AS cnt FROM leave_requests WHERE status = 'pending'")->fetch();
        return (int)$row['cnt'];
    }

    // ════════════════════════════════════════════════════════
    //  RECRUITMENT
    // ════════════════════════════════════════════════════════

    public static function getAllJobPostings(string $status = ''): array {
        if ($status) {
            $stmt = self::db()->prepare('
                SELECT jp.*, d.name AS department_name, u.name AS posted_by_name,
                       (SELECT COUNT(*) FROM applicants WHERE job_posting_id = jp.id) AS applicant_count
                FROM job_postings jp
                JOIN departments d ON d.id = jp.department_id
                LEFT JOIN users u ON u.id = jp.posted_by
                WHERE jp.status = ? ORDER BY jp.created_at DESC
            ');
            $stmt->execute([$status]);
        } else {
            $stmt = self::db()->query('
                SELECT jp.*, d.name AS department_name, u.name AS posted_by_name,
                       (SELECT COUNT(*) FROM applicants WHERE job_posting_id = jp.id) AS applicant_count
                FROM job_postings jp
                JOIN departments d ON d.id = jp.department_id
                LEFT JOIN users u ON u.id = jp.posted_by
                ORDER BY jp.created_at DESC
            ');
        }
        return $stmt->fetchAll();
    }

    public static function findJobPostingById(int $id): ?array {
        $stmt = self::db()->prepare('
            SELECT jp.*, d.name AS department_name
            FROM job_postings jp JOIN departments d ON d.id = jp.department_id
            WHERE jp.id = ? LIMIT 1
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function createJobPosting(array $data): bool {
        $stmt = self::db()->prepare('
            INSERT INTO job_postings
              (department_id, position_id, title, description, requirements,
               slots, salary_min, salary_max, employment_type, deadline, posted_by)
            VALUES
              (:department_id, :position_id, :title, :description, :requirements,
               :slots, :salary_min, :salary_max, :employment_type, :deadline, :posted_by)
        ');
        return (bool) $stmt->execute([
            ':department_id'   => $data['department_id'],
            ':position_id'     => $data['position_id']   ?? null,
            ':title'           => $data['title'],
            ':description'     => $data['description']   ?? null,
            ':requirements'    => $data['requirements']  ?? null,
            ':slots'           => $data['slots']         ?? 1,
            ':salary_min'      => $data['salary_min']    ?? null,
            ':salary_max'      => $data['salary_max']    ?? null,
            ':employment_type' => $data['employment_type'] ?? 'regular',
            ':deadline'        => $data['deadline']      ?? null,
            ':posted_by'       => $data['posted_by']     ?? null,
        ]);
    }

    public static function updateJobPostingStatus(int $id, string $status): bool {
        $stmt = self::db()->prepare('UPDATE job_postings SET status = ? WHERE id = ?');
        return (bool) $stmt->execute([$status, $id]);
    }

    public static function getApplicantsByJob(int $jobId): array {
        $stmt = self::db()->prepare('
            SELECT a.*, u.name AS processed_by_name
            FROM applicants a LEFT JOIN users u ON u.id = a.processed_by
            WHERE a.job_posting_id = ? ORDER BY a.applied_at DESC
        ');
        $stmt->execute([$jobId]);
        return $stmt->fetchAll();
    }

    public static function findApplicantById(int $id): ?array {
        $stmt = self::db()->prepare('
            SELECT a.*, jp.title AS job_title, d.name AS department_name
            FROM applicants a
            JOIN job_postings jp ON jp.id = a.job_posting_id
            JOIN departments d ON d.id = jp.department_id
            WHERE a.id = ? LIMIT 1
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function createApplicant(array $data): bool {
        $stmt = self::db()->prepare('
            INSERT INTO applicants (job_posting_id, name, email, phone, source, notes)
            VALUES (:job_posting_id, :name, :email, :phone, :source, :notes)
        ');
        return (bool) $stmt->execute([
            ':job_posting_id' => $data['job_posting_id'],
            ':name'           => $data['name'],
            ':email'          => $data['email']  ?? null,
            ':phone'          => $data['phone']  ?? null,
            ':source'         => $data['source'] ?? 'walk_in',
            ':notes'          => $data['notes']  ?? null,
        ]);
    }

    public static function updateApplicantStatus(int $id, string $status, int $processedBy, string $notes = '', ?string $interviewDate = null): bool {
        $stmt = self::db()->prepare('
            UPDATE applicants
            SET status = :status, processed_by = :processed_by,
                notes = :notes, interview_date = :interview_date
            WHERE id = :id
        ');
        return (bool) $stmt->execute([
            ':status'         => $status,
            ':processed_by'   => $processedBy,
            ':notes'          => $notes,
            ':interview_date' => $interviewDate,
            ':id'             => $id,
        ]);
    }

    public static function countOpenJobPostings(): int {
        $row = self::db()->query("SELECT COUNT(*) AS cnt FROM job_postings WHERE status = 'open'")->fetch();
        return (int)$row['cnt'];
    }

    public static function countNewApplicants(): int {
        $row = self::db()->query("SELECT COUNT(*) AS cnt FROM applicants WHERE status = 'new'")->fetch();
        return (int)$row['cnt'];
    }

    // ════════════════════════════════════════════════════════
    //  EMPLOYEE DOCUMENTS
    // ════════════════════════════════════════════════════════

    public static function getDocumentsByEmployee(int $employeeId): array {
        $stmt = self::db()->prepare('
            SELECT ed.*, u.name AS uploaded_by_name
            FROM employee_documents ed LEFT JOIN users u ON u.id = ed.uploaded_by
            WHERE ed.employee_id = ? ORDER BY ed.document_type, ed.created_at DESC
        ');
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }

    public static function createDocument(array $data): bool {
        $stmt = self::db()->prepare('
            INSERT INTO employee_documents (employee_id, document_type, title, file_path, expiry_date, notes, uploaded_by)
            VALUES (:employee_id, :document_type, :title, :file_path, :expiry_date, :notes, :uploaded_by)
        ');
        return (bool) $stmt->execute([
            ':employee_id'    => $data['employee_id'],
            ':document_type'  => $data['document_type'],
            ':title'          => $data['title'],
            ':file_path'      => $data['file_path']   ?? null,
            ':expiry_date'    => $data['expiry_date']  ?? null,
            ':notes'          => $data['notes']        ?? null,
            ':uploaded_by'    => $data['uploaded_by']  ?? null,
        ]);
    }

    public static function deleteDocument(int $id): bool {
        $stmt = self::db()->prepare('DELETE FROM employee_documents WHERE id = ?');
        return (bool) $stmt->execute([$id]);
    }

    // ════════════════════════════════════════════════════════
    //  PAYROLL
    // ════════════════════════════════════════════════════════

    public static function getAllPayroll(): array {
        return self::db()->query('SELECT * FROM v_payroll ORDER BY period DESC, employee_name')->fetchAll();
    }

    public static function getPayrollByPeriod(string $period): array {
        $stmt = self::db()->prepare('SELECT * FROM v_payroll WHERE period = ? ORDER BY employee_name');
        $stmt->execute([$period]);
        return $stmt->fetchAll();
    }

    public static function getPayrollByEmployee(int $employeeId): array {
        $stmt = self::db()->prepare('SELECT * FROM v_payroll WHERE employee_id = ? ORDER BY period DESC');
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }

    public static function findPayrollById(int $id): ?array {
        $stmt = self::db()->prepare('SELECT * FROM v_payroll WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function periodExists(string $period): bool {
        $stmt = self::db()->prepare("SELECT COUNT(*) FROM payroll_records WHERE period = ?");
        $stmt->execute([$period]);
        return (bool) $stmt->fetchColumn();
    }

    public static function getTotalNetPayForPeriod(string $period): float {
        $stmt = self::db()->prepare("SELECT COALESCE(SUM(net_pay), 0) AS total FROM payroll_records WHERE period = ?");
        $stmt->execute([$period]);
        return (float)$stmt->fetch()['total'];
    }

    public static function getPayrollPeriods(): array {
        return self::db()->query('SELECT DISTINCT period FROM payroll_records ORDER BY period DESC')->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function createPayrollRecord(array $d): bool {
        $stmt = self::db()->prepare('
            INSERT INTO payroll_records
              (employee_id, period, basic_salary, allowance, gross_pay,
               sss_msc, sss_ee, sss_er,
               philhealth_mbs, philhealth_ee, philhealth_er,
               pagibig_mfs, pagibig_ee, pagibig_er,
               taxable_income, withholding_tax,
               other_deductions, total_deductions, net_pay,
               status, processed_by)
            VALUES
              (:employee_id, :period, :basic_salary, :allowance, :gross_pay,
               :sss_msc, :sss_ee, :sss_er,
               :philhealth_mbs, :philhealth_ee, :philhealth_er,
               :pagibig_mfs, :pagibig_ee, :pagibig_er,
               :taxable_income, :withholding_tax,
               :other_deductions, :total_deductions, :net_pay,
               :status, :processed_by)
        ');
        return (bool) $stmt->execute([
            ':employee_id'      => $d['employee_id'],
            ':period'           => $d['period'],
            ':basic_salary'     => $d['basic_salary'],
            ':allowance'        => $d['allowance'],
            ':gross_pay'        => $d['gross_pay'],
            ':sss_msc'          => $d['sss_msc'],
            ':sss_ee'           => $d['sss_ee'],
            ':sss_er'           => $d['sss_er'],
            ':philhealth_mbs'   => $d['philhealth_mbs'],
            ':philhealth_ee'    => $d['philhealth_ee'],
            ':philhealth_er'    => $d['philhealth_er'],
            ':pagibig_mfs'      => $d['pagibig_mfs'],
            ':pagibig_ee'       => $d['pagibig_ee'],
            ':pagibig_er'       => $d['pagibig_er'],
            ':taxable_income'   => $d['taxable_income'],
            ':withholding_tax'  => $d['withholding_tax'],
            ':other_deductions' => $d['other_deductions'] ?? 0,
            ':total_deductions' => $d['total_deductions'],
            ':net_pay'          => $d['net_pay'],
            ':status'           => $d['status']            ?? 'pending',
            ':processed_by'     => $d['processed_by']      ?? null,
        ]);
    }

    public static function releasePayroll(int $payrollId): bool {
        $stmt = self::db()->prepare("UPDATE payroll_records SET status = 'released', released_at = NOW() WHERE id = ?");
        return (bool) $stmt->execute([$payrollId]);
    }

    public static function releaseAllPayrollForPeriod(string $period): bool {
        $stmt = self::db()->prepare("UPDATE payroll_records SET status = 'released', released_at = NOW() WHERE period = ? AND status = 'pending'");
        return (bool) $stmt->execute([$period]);
    }

    public static function computePayroll(array $employee): array {
        return PhilippineDeductions::computeAll(
            (float)$employee['basic_salary'],
            (float)($employee['allowance'] ?? 0)
        );
    }

    // ════════════════════════════════════════════════════════
    //  SALARY HISTORY
    // ════════════════════════════════════════════════════════

    public static function getSalaryHistory(int $employeeId): array {
        $stmt = self::db()->prepare('
            SELECT sh.*, u.name AS approved_by_name
            FROM salary_history sh LEFT JOIN users u ON u.id = sh.approved_by
            WHERE sh.employee_id = ? ORDER BY sh.effective_date DESC
        ');
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }

    public static function createSalaryHistory(array $data): bool {
        $stmt = self::db()->prepare('
            INSERT INTO salary_history
              (employee_id, old_basic_salary, new_basic_salary, old_allowance, new_allowance,
               reason, effective_date, approved_by)
            VALUES (:employee_id, :old_basic_salary, :new_basic_salary, :old_allowance, :new_allowance,
                    :reason, :effective_date, :approved_by)
        ');
        return (bool) $stmt->execute([
            ':employee_id'      => $data['employee_id'],
            ':old_basic_salary' => $data['old_basic_salary'],
            ':new_basic_salary' => $data['new_basic_salary'],
            ':old_allowance'    => $data['old_allowance']  ?? 0,
            ':new_allowance'    => $data['new_allowance']  ?? 0,
            ':reason'           => $data['reason']         ?? null,
            ':effective_date'   => $data['effective_date'],
            ':approved_by'      => $data['approved_by']    ?? null,
        ]);
    }

    // ════════════════════════════════════════════════════════
    //  ANNOUNCEMENTS
    // ════════════════════════════════════════════════════════

    public static function getActiveAnnouncements(): array {
        return self::db()->query("
            SELECT a.*, u.name AS posted_by_name
            FROM announcements a LEFT JOIN users u ON u.id = a.posted_by
            WHERE a.expires_at IS NULL OR a.expires_at >= CURDATE()
            ORDER BY a.is_pinned DESC, a.created_at DESC
        ")->fetchAll();
    }

    public static function createAnnouncement(array $data): bool {
        $stmt = self::db()->prepare('
            INSERT INTO announcements (title, content, type, is_pinned, expires_at, posted_by)
            VALUES (:title, :content, :type, :is_pinned, :expires_at, :posted_by)
        ');
        return (bool) $stmt->execute([
            ':title'      => $data['title'],
            ':content'    => $data['content'],
            ':type'       => $data['type']       ?? 'general',
            ':is_pinned'  => $data['is_pinned']  ?? 0,
            ':expires_at' => $data['expires_at'] ?? null,
            ':posted_by'  => $data['posted_by']  ?? null,
        ]);
    }

    public static function deleteAnnouncement(int $id): bool {
        $stmt = self::db()->prepare('DELETE FROM announcements WHERE id = ?');
        return (bool) $stmt->execute([$id]);
    }

    // ════════════════════════════════════════════════════════
    //  HOLIDAYS
    // ════════════════════════════════════════════════════════

    public static function getHolidaysByYear(int $year): array {
        $stmt = self::db()->prepare("SELECT * FROM holidays WHERE YEAR(date) = ? ORDER BY date");
        $stmt->execute([$year]);
        return $stmt->fetchAll();
    }

    public static function isHoliday(string $date): ?array {
        $stmt = self::db()->prepare("SELECT * FROM holidays WHERE date = ? LIMIT 1");
        $stmt->execute([$date]);
        return $stmt->fetch() ?: null;
    }

    // ════════════════════════════════════════════════════════
    //  ACTIVITY LOG
    // ════════════════════════════════════════════════════════

    public static function log(?int $userId, string $action, string $description = ''): void {
        $stmt = self::db()->prepare(
            'INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $action, $description, $_SERVER['REMOTE_ADDR'] ?? null]);
    }

    public static function getActivityLogs(int $limit = 100): array {
        $stmt = self::db()->prepare('
            SELECT al.*, u.name AS user_name, u.role
            FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id
            ORDER BY al.created_at DESC LIMIT ?
        ');
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    // ════════════════════════════════════════════════════════
    //  DASHBOARD STATS
    // ════════════════════════════════════════════════════════

    public static function getDashboardStats(): array {
        $db = self::db();
        return [
            'total_employees'    => (int)$db->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn(),
            'total_departments'  => (int)$db->query("SELECT COUNT(*) FROM departments")->fetchColumn(),
            'pending_leaves'     => (int)$db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn(),
            'open_jobs'          => (int)$db->query("SELECT COUNT(*) FROM job_postings WHERE status = 'open'")->fetchColumn(),
            'new_applicants'     => (int)$db->query("SELECT COUNT(*) FROM applicants WHERE status = 'new'")->fetchColumn(),
            'this_month_payroll' => (float)$db->query("SELECT COALESCE(SUM(net_pay),0) FROM payroll_records WHERE period = DATE_FORMAT(NOW(),'%Y-%m')")->fetchColumn(),
            'last_month_payroll' => (float)$db->query("SELECT COALESCE(SUM(net_pay),0) FROM payroll_records WHERE period = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH),'%Y-%m')")->fetchColumn(),
            'resigned_this_year' => (int)$db->query("SELECT COUNT(*) FROM employees WHERE status IN ('resigned','terminated') AND YEAR(date_separated) = YEAR(NOW())")->fetchColumn(),
        ];
    }

    public static function getHeadcountByDepartment(): array {
        return self::db()->query("
            SELECT d.name AS department, COUNT(e.id) AS count
            FROM departments d
            LEFT JOIN employees e ON e.department_id = d.id AND e.status = 'active'
            GROUP BY d.id, d.name ORDER BY count DESC
        ")->fetchAll();
    }
}