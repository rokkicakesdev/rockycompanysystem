<?php
// core/models/ReimbursementModel.php
// ─────────────────────────────────────────────────────────────────────────────
//  Handles the reimbursements table.
//  Employees submit reimbursement requests; Admin/Management approves or rejects.
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/BaseModel.php';

class ReimbursementModel extends BaseModel
{
    /** Reimbursement type labels */
    public static function types(): array
    {
        return [
            'transportation'  => 'Transportation',
            'meal'            => 'Meal / Per Diem',
            'medical'         => 'Medical / Health',
            'communication'   => 'Communication / Internet',
            'office_supplies' => 'Office Supplies',
            'training'        => 'Training / Seminar',
            'other'           => 'Other',
        ];
    }

    public static function getAll(string $status = ''): array
    {
        if ($status) {
            $stmt = self::db()->prepare('
                SELECT r.*, e.name AS employee_name, e.employee_no, d.name AS department,
                       u.name AS reviewed_by_name
                FROM reimbursements r
                JOIN employees e ON e.id = r.employee_id
                JOIN departments d ON d.id = e.department_id
                LEFT JOIN users u ON u.id = r.reviewed_by
                WHERE r.status = ?
                ORDER BY r.created_at DESC
            ');
            $stmt->execute([$status]);
        } else {
            $stmt = self::db()->query('
                SELECT r.*, e.name AS employee_name, e.employee_no, d.name AS department,
                       u.name AS reviewed_by_name
                FROM reimbursements r
                JOIN employees e ON e.id = r.employee_id
                JOIN departments d ON d.id = e.department_id
                LEFT JOIN users u ON u.id = r.reviewed_by
                ORDER BY r.created_at DESC
            ');
        }
        return $stmt->fetchAll();
    }

    public static function getByEmployee(int $employeeId): array
    {
        $stmt = self::db()->prepare('
            SELECT r.*, u.name AS reviewed_by_name
            FROM reimbursements r
            LEFT JOIN users u ON u.id = r.reviewed_by
            WHERE r.employee_id = ?
            ORDER BY r.created_at DESC
        ');
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = self::db()->prepare('
            SELECT r.*, e.name AS employee_name, e.employee_no, d.name AS department,
                   u.name AS reviewed_by_name
            FROM reimbursements r
            JOIN employees e ON e.id = r.employee_id
            JOIN departments d ON d.id = e.department_id
            LEFT JOIN users u ON u.id = r.reviewed_by
            WHERE r.id = ? LIMIT 1
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $d): bool
    {
        $stmt = self::db()->prepare('
            INSERT INTO reimbursements
              (employee_id, type, amount, receipt_date, description, receipt_no, status)
            VALUES
              (:employee_id, :type, :amount, :receipt_date, :description, :receipt_no, "pending")
        ');
        return (bool) $stmt->execute([
            ':employee_id'  => $d['employee_id'],
            ':type'         => $d['type'],
            ':amount'       => (float)$d['amount'],
            ':receipt_date' => $d['receipt_date'],
            ':description'  => $d['description'] ?? null,
            ':receipt_no'   => $d['receipt_no']  ?? null,
        ]);
    }

    public static function review(int $id, string $status, int $reviewedBy, string $notes = ''): bool
    {
        $allowed = ['approved', 'rejected', 'paid'];
        if (!in_array($status, $allowed, true)) return false;

        $stmt = self::db()->prepare('
            UPDATE reimbursements
            SET status      = :status,
                reviewed_by = :reviewed_by,
                reviewed_at = NOW(),
                review_notes = :notes
            WHERE id = :id
        ');
        return (bool) $stmt->execute([
            ':status'      => $status,
            ':reviewed_by' => $reviewedBy,
            ':notes'       => $notes,
            ':id'          => $id,
        ]);
    }

    public static function countPending(): int
    {
        $row = self::db()->query("SELECT COUNT(*) AS cnt FROM reimbursements WHERE status = 'pending'")->fetch();
        return (int)($row['cnt'] ?? 0);
    }
}
