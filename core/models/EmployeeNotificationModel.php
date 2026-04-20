<?php
// core/models/EmployeeNotificationModel.php
// DB-backed employee notifications — used for promotion/regularization/retirement banners.
// Admins insert a notification; the employee reads + auto-marks it read on dashboard load.

require_once __DIR__ . '/BaseModel.php';

class EmployeeNotificationModel extends BaseModel
{
    /**
     * Fetch all unread notifications for an employee, then mark them read atomically.
     * Returns the rows BEFORE they are marked read (so the dashboard can display them).
     */
    public static function popUnread(int $employeeId): array
    {
        $db = self::db();

        // Fetch unread rows
        $stmt = $db->prepare('
            SELECT * FROM employee_notifications
            WHERE employee_id = ? AND is_read = 0
            ORDER BY created_at ASC
        ');
        $stmt->execute([$employeeId]);
        $rows = $stmt->fetchAll();

        if (!empty($rows)) {
            // Mark all as read
            $ids = implode(',', array_map('intval', array_column($rows, 'id')));
            $db->exec("UPDATE employee_notifications SET is_read = 1 WHERE id IN ({$ids})");
        }

        return $rows;
    }

    /**
     * Insert a notification for a specific employee.
     */
    public static function create(array $data): bool
    {
        $stmt = self::db()->prepare('
            INSERT INTO employee_notifications
              (employee_id, type, title, message, created_by)
            VALUES
              (:employee_id, :type, :title, :message, :created_by)
        ');
        return (bool) $stmt->execute([
            ':employee_id' => $data['employee_id'],
            ':type'        => $data['type']       ?? 'general',
            ':title'       => $data['title'],
            ':message'     => $data['message'],
            ':created_by'  => $data['created_by'] ?? null,
        ]);
    }

    /**
     * Count unread notifications for badge display.
     */
    public static function countUnread(int $employeeId): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM employee_notifications WHERE employee_id = ? AND is_read = 0'
        );
        $stmt->execute([$employeeId]);
        return (int) $stmt->fetchColumn();
    }
}
