<?php
// core/models/LeaveModel.php
// ─────────────────────────────────────────────────────────────────────────────
//  Handles leave_requests table operations and leave balance deductions.
//  Extracted from Model.php (God Class split).
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/BaseModel.php';

class LeaveModel extends BaseModel
{
    public static function getAll(string $status = ''): array
    {
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

    public static function getByEmployee(int $employeeId): array
    {
        $stmt = self::db()->prepare('
            SELECT lr.*, u.name AS reviewed_by_name
            FROM leave_requests lr
            LEFT JOIN users u ON u.id = lr.reviewed_by
            WHERE lr.employee_id = ? ORDER BY lr.filed_at DESC
        ');
        $stmt->execute([$employeeId]);
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = self::db()->prepare('
            SELECT lr.*, e.name AS employee_name, e.employee_no
            FROM leave_requests lr JOIN employees e ON e.id = lr.employee_id
            WHERE lr.id = ? LIMIT 1
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): bool
    {
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

    /**
     * Approve or deny a leave request.
     * On approval, deducts the leave balance from the employee record.
     *
     * BUG FIX: The original code ran two separate queries with no transaction.
     * If the balance deduction query failed after the status was already set to
     * 'approved', the leave would be marked approved but the balance never deducted —
     * silent data corruption. Both operations are now wrapped in a transaction so
     * either both succeed or both are rolled back.
     */
    public static function review(int $id, string $status, int $reviewedBy, string $notes = ''): bool
    {
        $db = self::db();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('
                UPDATE leave_requests
                SET status = :status, reviewed_by = :reviewed_by,
                    reviewed_at = NOW(), review_notes = :notes
                WHERE id = :id
            ');
            $stmt->execute([
                ':status'      => $status,
                ':reviewed_by' => $reviewedBy,
                ':notes'       => $notes,
                ':id'          => $id,
            ]);

            if ($status === 'approved') {
                $leave = self::findById($id);
                if ($leave && $leave['leave_type'] !== 'unpaid') {
                    $balanceFields = LEAVE_BALANCE_FIELDS;
                    if (isset($balanceFields[$leave['leave_type']])) {
                        $field = $balanceFields[$leave['leave_type']];

                        // Whitelist $field against known balance columns to prevent SQL injection
                        $allowedColumns = array_values(LEAVE_BALANCE_FIELDS);
                        if (!in_array($field, $allowedColumns, true)) {
                            error_log("LeaveModel::review: rejected unsafe column '{$field}' for employee {$leave['employee_id']}");
                            $db->rollBack();
                            return false;
                        }

                        $deductStmt = $db->prepare("
                            UPDATE employees SET {$field} = GREATEST(0, {$field} - :days) WHERE id = :emp_id
                        ");
                        $deductStmt->execute([
                            ':days'   => $leave['days_applied'],
                            ':emp_id' => $leave['employee_id'],
                        ]);
                    }
                }
            }

            $db->commit();
            return true;

        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('LeaveModel::review failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function countPending(): int
    {
        $row = self::db()->query("SELECT COUNT(*) AS cnt FROM leave_requests WHERE status = 'pending'")->fetch();
        return (int) $row['cnt'];
    }
}