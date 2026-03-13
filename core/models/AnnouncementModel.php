<?php
// core/models/AnnouncementModel.php
// ─────────────────────────────────────────────────────────────────────────────
//  Handles announcements table operations.
//  Extracted from Model.php (God Class split).
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/BaseModel.php';

class AnnouncementModel extends BaseModel
{
    public static function getActive(): array
    {
        return self::db()->query("
            SELECT a.*, u.name AS posted_by_name
            FROM announcements a LEFT JOIN users u ON u.id = a.posted_by
            WHERE a.expires_at IS NULL OR a.expires_at >= CURDATE()
            ORDER BY a.is_pinned DESC, a.created_at DESC
        ")->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM announcements WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): bool
    {
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

    public static function update(int $id, array $data): bool
    {
        $stmt = self::db()->prepare('
            UPDATE announcements
            SET title = :title, content = :content, type = :type,
                is_pinned = :is_pinned, expires_at = :expires_at
            WHERE id = :id
        ');
        return (bool) $stmt->execute([
            ':title'      => $data['title'],
            ':content'    => $data['content'],
            ':type'       => $data['type']       ?? 'general',
            ':is_pinned'  => $data['is_pinned']  ?? 0,
            ':expires_at' => $data['expires_at'] ?? null,
            ':id'         => $id,
        ]);
    }

    public static function delete(int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM announcements WHERE id = ?');
        return (bool) $stmt->execute([$id]);
    }
}
