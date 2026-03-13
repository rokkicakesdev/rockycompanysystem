<?php
// core/models/UserModel.php
// ─────────────────────────────────────────────────────────────────────────────
//  Handles all users table operations.
//  Extracted from Model.php (God Class split).
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/BaseModel.php';

class UserModel extends BaseModel
{
    public static function getAll(): array
    {
        return self::db()->query('SELECT * FROM users ORDER BY role, name')->fetchAll();
    }

    public static function findByUsername(string $username): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmployeeId(int $employeeId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE employee_id = ? LIMIT 1');
        $stmt->execute([$employeeId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): bool
    {
        $stmt = self::db()->prepare('
            INSERT INTO users (name, username, email, password, role, employee_id, status, created_by)
            VALUES (:name, :username, :email, :password, :role, :employee_id, :status, :created_by)
        ');
        return (bool) $stmt->execute([
            ':name'        => $data['name'],
            ':username'    => $data['username'],
            ':email'       => $data['email'],
            ':password'    => password_hash($data['password'], PASSWORD_BCRYPT),
            ':role'        => $data['role']        ?? 'employee',
            ':employee_id' => $data['employee_id'] ?? null,
            ':status'      => $data['status']      ?? 'active',
            ':created_by'  => $data['created_by']  ?? null,
        ]);
    }

    /**
     * Generate a unique username from a full name.
     * Format: firstname.emp  (e.g. "Juan dela Cruz" → "juan.emp")
     * If taken, appends a number: juan.emp2, juan.emp3, ...
     */
    public static function generateEmployeeUsername(string $fullName): string
    {
        $firstName = strtolower(explode(' ', trim($fullName))[0]);
        // Strip non-alphanumeric characters for safety
        $firstName = preg_replace('/[^a-z0-9]/', '', $firstName);
        $base      = $firstName . '.emp';
        $username  = $base;
        $suffix    = 2;

        while (self::findByUsername($username) !== null) {
            $username = $base . $suffix;
            $suffix++;
        }

        return $username;
    }

    public static function update(int $id, array $data): bool
    {
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

    public static function updatePassword(int $id, string $newPassword): bool
    {
        $stmt = self::db()->prepare('UPDATE users SET password = ? WHERE id = ?');
        return (bool) $stmt->execute([password_hash($newPassword, PASSWORD_BCRYPT), $id]);
    }

    public static function updateStatus(int $id, string $status): bool
    {
        $stmt = self::db()->prepare('UPDATE users SET status = ? WHERE id = ?');
        return (bool) $stmt->execute([$status, $id]);
    }
}