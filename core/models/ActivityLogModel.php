<?php
// core/models/ActivityLogModel.php
// ─────────────────────────────────────────────────────────────────────────────
//  Handles activity_logs table operations.
//  Extracted from Model.php (God Class split).
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/BaseModel.php';

class ActivityLogModel extends BaseModel
{
    public static function log(?int $userId, string $action, string $description = ''): void
    {
        $stmt = self::db()->prepare(
            'INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $action, $description, $_SERVER['REMOTE_ADDR'] ?? null]);
    }

    public static function getRecent(int $limit = 100): array
    {
        $stmt = self::db()->prepare('
            SELECT al.*, u.name AS user_name, u.role
            FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id
            ORDER BY al.created_at DESC LIMIT ?
        ');
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public static function getPaginated(int $limit, int $offset): array
    {
        $stmt = self::db()->prepare('
            SELECT al.*, u.name AS user_name, u.role
            FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id
            ORDER BY al.created_at DESC LIMIT ? OFFSET ?
        ');
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    public static function count(): int
    {
        $row = self::db()->query('SELECT COUNT(*) AS cnt FROM activity_logs')->fetch();
        return (int) $row['cnt'];
    }
}
