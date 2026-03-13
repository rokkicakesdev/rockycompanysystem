<?php
// core/models/DepartmentModel.php
// ─────────────────────────────────────────────────────────────────────────────
//  Handles departments and positions table operations.
//  Extracted from Model.php (God Class split).
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/BaseModel.php';

class DepartmentModel extends BaseModel
{
    // ── Departments ──────────────────────────────────────────────────────────

    public static function getAll(): array
    {
        return self::db()->query('SELECT * FROM departments ORDER BY name')->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM departments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $name): bool
    {
        $stmt = self::db()->prepare('INSERT INTO departments (name) VALUES (?)');
        return (bool) $stmt->execute([$name]);
    }

    public static function update(int $id, string $name): bool
    {
        $stmt = self::db()->prepare('UPDATE departments SET name = ? WHERE id = ?');
        return (bool) $stmt->execute([$name, $id]);
    }

    public static function delete(int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM departments WHERE id = ?');
        return (bool) $stmt->execute([$id]);
    }

    public static function countActiveEmployees(int $deptId): int
    {
        $stmt = self::db()->prepare("SELECT COUNT(*) AS cnt FROM employees WHERE department_id = ? AND status = 'active'");
        $stmt->execute([$deptId]);
        return (int) $stmt->fetch()['cnt'];
    }

    // ── Positions ────────────────────────────────────────────────────────────

    public static function getAllPositions(): array
    {
        return self::db()->query('
            SELECT p.*, d.name AS department_name
            FROM positions p JOIN departments d ON d.id = p.department_id
            ORDER BY d.name, p.name
        ')->fetchAll();
    }

    public static function getPositionsByDepartment(int $deptId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM positions WHERE department_id = ? ORDER BY name');
        $stmt->execute([$deptId]);
        return $stmt->fetchAll();
    }

    public static function createPosition(int $deptId, string $name): bool
    {
        $stmt = self::db()->prepare('INSERT INTO positions (department_id, name) VALUES (?, ?)');
        return (bool) $stmt->execute([$deptId, $name]);
    }

    public static function deletePosition(int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM positions WHERE id = ?');
        return (bool) $stmt->execute([$id]);
    }

    public static function countActiveEmployeesInPosition(int $positionId): int
    {
        $stmt = self::db()->prepare("SELECT COUNT(*) AS cnt FROM employees WHERE position_id = ? AND status = 'active'");
        $stmt->execute([$positionId]);
        return (int) $stmt->fetch()['cnt'];
    }
}
