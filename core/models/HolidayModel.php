<?php
// core/models/HolidayModel.php
// ─────────────────────────────────────────────────────────────────────────────
//  Handles holidays table operations.
//  Extracted from Model.php (God Class split).
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/BaseModel.php';

class HolidayModel extends BaseModel
{
    public static function getByYear(int $year): array
    {
        $stmt = self::db()->prepare('SELECT * FROM holidays WHERE YEAR(date) = ? ORDER BY date');
        $stmt->execute([$year]);
        return $stmt->fetchAll();
    }

    public static function isHoliday(string $date): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM holidays WHERE date = ? LIMIT 1');
        $stmt->execute([$date]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Returns all holidays within a date range (inclusive).
     * Used to warn when a leave request overlaps with holidays.
     */
    public static function getInRange(string $dateFrom, string $dateTo): array
    {
        $stmt = self::db()->prepare('SELECT * FROM holidays WHERE date BETWEEN ? AND ? ORDER BY date');
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetchAll();
    }

    public static function create(array $d): bool
    {
        $stmt = self::db()->prepare('INSERT INTO holidays (name, date, type, is_recurring) VALUES (?, ?, ?, ?)');
        return $stmt->execute([$d['name'], $d['date'], $d['type'], $d['is_recurring']]);
    }

    public static function update(int $id, array $d): bool
    {
        $stmt = self::db()->prepare('UPDATE holidays SET name=?, date=?, type=?, is_recurring=? WHERE id=?');
        return $stmt->execute([$d['name'], $d['date'], $d['type'], $d['is_recurring'], $id]);
    }

    public static function delete(int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM holidays WHERE id=?');
        return $stmt->execute([$id]);
    }
}
