<?php
// core/models/EmployeeModel.php
// ─────────────────────────────────────────────────────────────────────────────
//  Handles employee records, employee documents, and salary history.
//  Extracted from Model.php (God Class split).
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/BaseModel.php';

class EmployeeModel extends BaseModel
{
    // ── Employee No Generator ────────────────────────────────────────────────

    /**
     * Generate the next sequential employee number in EMP-XXX format.
     * Scans the max numeric suffix currently in the table and increments by 1.
     * Thread-safe enough for a small-to-medium system (no concurrent batch inserts).
     */
    public static function generateNextEmployeeNo(): string
    {
        $row = self::db()->query(
            "SELECT MAX(CAST(SUBSTRING(employee_no, 5) AS UNSIGNED)) AS max_seq
             FROM employees
             WHERE employee_no REGEXP '^EMP-[0-9]+$'"
        )->fetch();

        $next = (int)($row['max_seq'] ?? 0) + 1;
        return 'EMP-' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Return the auto-increment ID of the last inserted employee row.
     */
    public static function getLastInsertId(): int
    {
        return (int) self::db()->lastInsertId();
    }

    // ── Employee CRUD ────────────────────────────────────────────────────────

    public static function getAll(string $status = ''): array
    {
        $sql = '
            SELECT e.*, d.name AS department, d.id AS department_id,
                   p.name AS position, p.id AS position_id,
                   (e.basic_salary + e.allowance) AS gross_pay
            FROM employees e
            JOIN departments d ON d.id = e.department_id
            JOIN positions  p ON p.id = e.position_id
        ';
        if ($status) {
            $stmt = self::db()->prepare($sql . ' WHERE e.status = ? ORDER BY e.name');
            $stmt->execute([$status]);
            return $stmt->fetchAll();
        }
        return self::db()->query($sql . ' ORDER BY e.name')->fetchAll();
    }

    public static function findById(int $id): ?array
    {
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

    public static function countActive(): int
    {
        $row = self::db()->query("SELECT COUNT(*) AS cnt FROM employees WHERE status = 'active'")->fetch();
        return (int) $row['cnt'];
    }

    public static function countByStatus(): array
    {
        return self::db()->query('SELECT status, COUNT(*) AS cnt FROM employees GROUP BY status')->fetchAll();
    }

    public static function create(array $data): bool
    {
        // Auto-generate employee_no if not explicitly provided
        if (empty($data['employee_no'])) {
            $data['employee_no'] = self::generateNextEmployeeNo();
        }

        $stmt = self::db()->prepare('
            INSERT INTO employees
              (employee_no, name, gender, civil_status, birthdate, address,
               email, phone, sss_no, philhealth_no, pagibig_no, tin_no,
               department_id, position_id, basic_salary, allowance,
               date_hired, date_start, employment_type, status,
               sick_leave_balance, vacation_leave_balance,
               bereavement_leave_balance, emergency_leave_balance,
               sil_balance, maternity_leave_balance, paternity_leave_balance,
               solo_parent_leave_balance, vawc_leave_balance, magna_carta_leave_balance,
               emergency_contact_name, emergency_contact_phone, emergency_contact_relation)
            VALUES
              (:employee_no, :name, :gender, :civil_status, :birthdate, :address,
               :email, :phone, :sss_no, :philhealth_no, :pagibig_no, :tin_no,
               :department_id, :position_id, :basic_salary, :allowance,
               :date_hired, :date_start, :employment_type, :status,
               :sick_leave_balance, :vacation_leave_balance,
               :bereavement_leave_balance, :emergency_leave_balance,
               :sil_balance, :maternity_leave_balance, :paternity_leave_balance,
               :solo_parent_leave_balance, :vawc_leave_balance, :magna_carta_leave_balance,
               :emergency_contact_name, :emergency_contact_phone, :emergency_contact_relation)
        ');
        return (bool) $stmt->execute([
            ':employee_no'               => $data['employee_no'],
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
            ':date_start'                => $data['date_start']                ?? $data['date_hired'],
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

    public static function update(int $id, array $data): bool
    {
        // Auto-record salary history if salary changed
        $current = self::findById($id);
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
                date_hired = :date_hired, date_start = :date_start,
                employment_type = :employment_type,
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
            ':date_start'                => $data['date_start']                ?? $data['date_hired'],
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

    /** Employee self-service: safe fields only (no salary/dept/position) */
    public static function updateProfile(int $id, array $data): bool
    {
        $stmt = self::db()->prepare('
            UPDATE employees SET
                phone      = :phone,
                address    = :address,
                emergency_contact_name     = :emergency_contact_name,
                emergency_contact_phone    = :emergency_contact_phone,
                emergency_contact_relation = :emergency_contact_relation
            WHERE id = :id
        ');
        return (bool) $stmt->execute([
            ':phone'                     => $data['phone']                     ?? null,
            ':address'                   => $data['address']                   ?? null,
            ':emergency_contact_name'    => $data['emergency_contact_name']    ?? null,
            ':emergency_contact_phone'   => $data['emergency_contact_phone']   ?? null,
            ':emergency_contact_relation'=> $data['emergency_contact_relation'] ?? null,
            ':id'                        => $id,
        ]);
    }

    public static function toggleStatus(int $id, string $newStatus): bool
    {
        $stmt = self::db()->prepare('UPDATE employees SET status = ? WHERE id = ?');
        return (bool) $stmt->execute([$newStatus, $id]);
    }

    public static function search(string $query): array
    {
        $like = '%' . $query . '%';
        $stmt = self::db()->prepare('
            SELECT e.*, d.name AS department, d.id AS department_id,
                   p.name AS position, p.id AS position_id,
                   (e.basic_salary + e.allowance) AS gross_pay
            FROM employees e
            JOIN departments d ON d.id = e.department_id
            JOIN positions  p ON p.id = e.position_id
            WHERE e.name LIKE ? OR e.employee_no LIKE ? OR e.email LIKE ?
               OR d.name LIKE ? OR p.name LIKE ?
            ORDER BY e.name
        ');
        $stmt->execute([$like, $like, $like, $like, $like]);
        return $stmt->fetchAll();
    }

    public static function getByDepartment(int $deptId): array
    {
        $stmt = self::db()->prepare("SELECT * FROM v_employees WHERE department_id = ? AND status = 'active' ORDER BY name");
        $stmt->execute([$deptId]);
        return $stmt->fetchAll();
    }

    // ── Documents ────────────────────────────────────────────────────────────

    public static function getDocuments(int $employeeId): array
    {
        $stmt = self::db()->prepare('
            SELECT ed.*, u.name AS uploaded_by_name
            FROM employee_documents ed LEFT JOIN users u ON u.id = ed.uploaded_by
            WHERE ed.employee_id = ? ORDER BY ed.document_type, ed.created_at DESC
        ');
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }

    public static function createDocument(array $data): bool
    {
        $stmt = self::db()->prepare('
            INSERT INTO employee_documents (employee_id, document_type, title, file_path, expiry_date, notes, uploaded_by)
            VALUES (:employee_id, :document_type, :title, :file_path, :expiry_date, :notes, :uploaded_by)
        ');
        return (bool) $stmt->execute([
            ':employee_id'   => $data['employee_id'],
            ':document_type' => $data['document_type'],
            ':title'         => $data['title'],
            ':file_path'     => $data['file_path']  ?? null,
            ':expiry_date'   => $data['expiry_date'] ?? null,
            ':notes'         => $data['notes']       ?? null,
            ':uploaded_by'   => $data['uploaded_by'] ?? null,
        ]);
    }

    public static function deleteDocument(int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM employee_documents WHERE id = ?');
        return (bool) $stmt->execute([$id]);
    }

    public static function findDocumentById(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM employee_documents WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Update a specific subset of employee columns without touching everything else.
     * Used by the promotion/demotion/role-change handler to apply only salary + position.
     * Salary history must already have been recorded by the caller.
     */
    public static function updatePartial(int $id, array $data): bool
    {
        $allowed = [
            'basic_salary', 'allowance', 'position_id', 'department_id',
            'employment_type', 'status', 'date_regularized',
            'date_separated', 'separation_reason',
        ];
        $sets   = [];
        $params = [':id' => $id];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]            = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }
        if (empty($sets)) return true;
        $sets[] = 'updated_at = NOW()';
        $stmt   = self::db()->prepare(
            'UPDATE employees SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        return (bool) $stmt->execute($params);
    }

    // ── Salary History ───────────────────────────────────────────────────────

    public static function getSalaryHistory(int $employeeId): array
    {
        $stmt = self::db()->prepare('
            SELECT sh.*,
                   u.name  AS approved_by_name,
                   op.name AS old_position_name,
                   np.name AS new_position_name
            FROM salary_history sh
            LEFT JOIN users     u  ON u.id  = sh.approved_by
            LEFT JOIN positions op ON op.id = sh.old_position_id
            LEFT JOIN positions np ON np.id = sh.new_position_id
            WHERE sh.employee_id = ? ORDER BY sh.effective_date DESC, sh.id DESC
        ');
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }

    public static function createSalaryHistory(array $data): bool
    {
        $stmt = self::db()->prepare('
            INSERT INTO salary_history
              (employee_id, old_basic_salary, new_basic_salary, old_allowance, new_allowance,
               reason, change_type, old_position_id, new_position_id, effective_date, approved_by)
            VALUES (:employee_id, :old_basic_salary, :new_basic_salary, :old_allowance, :new_allowance,
                    :reason, :change_type, :old_position_id, :new_position_id, :effective_date, :approved_by)
        ');
        return (bool) $stmt->execute([
            ':employee_id'      => $data['employee_id'],
            ':old_basic_salary' => $data['old_basic_salary'],
            ':new_basic_salary' => $data['new_basic_salary'],
            ':old_allowance'    => $data['old_allowance']    ?? 0,
            ':new_allowance'    => $data['new_allowance']    ?? 0,
            ':reason'           => $data['reason']           ?? null,
            ':change_type'      => $data['change_type']      ?? 'salary_increase',
            ':old_position_id'  => $data['old_position_id']  ?? null,
            ':new_position_id'  => $data['new_position_id']  ?? null,
            ':effective_date'   => $data['effective_date'],
            ':approved_by'      => $data['approved_by']      ?? null,
        ]);
    }
}