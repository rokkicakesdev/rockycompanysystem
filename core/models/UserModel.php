<?php
// core/models/UserModel.php

require_once __DIR__ . '/BaseModel.php';

class UserModel extends BaseModel
{
    public static function getAll(): array
    {
        return self::db()->query('
            SELECT u.*, e.employee_no
            FROM users u
            LEFT JOIN employees e ON e.id = u.employee_id
            ORDER BY u.created_at DESC
        ')->fetchAll();
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
            INSERT INTO users
              (name, username, email, password, role, employee_id,
               status, must_change_password, created_by)
            VALUES
              (:name, :username, :email, :password, :role, :employee_id,
               :status, :must_change_password, :created_by)
        ');
        return (bool) $stmt->execute([
            ':name'                 => $data['name'],
            ':username'             => $data['username'],
            ':email'                => $data['email'],
            ':password'             => password_hash($data['password'], PASSWORD_BCRYPT),
            ':role'                 => $data['role']               ?? 'employee',
            ':employee_id'          => $data['employee_id']        ?? null,
            ':status'               => $data['status']             ?? 'active',
            ':must_change_password' => (int)($data['must_change_password'] ?? 1),
            ':created_by'           => $data['created_by']         ?? null,
        ]);
    }

    /**
     * Generate a unique username from a full name.
     * Format: firstname.emp  (e.g. "Juan dela Cruz" → "juan.emp")
     * If taken, appends a number: juan.emp2, juan.emp3, ...
     */
    public static function generateEmployeeUsername(string $fullName): string
    {
        $parts     = preg_split('/\s+/', strtolower(trim($fullName)));
        $firstName = preg_replace('/[^a-z0-9]/', '', $parts[0] ?? 'emp');
        $base      = $firstName . '.emp';
        $username  = $base;
        $counter   = 2;

        while (self::findByUsername($username)) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }

    public static function update(int $id, array $data): bool
    {
        $stmt = self::db()->prepare('
            UPDATE users
            SET name   = :name,
                email  = :email,
                role   = :role,
                status = :status
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

    /**
     * Update password and clear the must_change_password flag.
     * Also records the timestamp of the change.
     */
    public static function updatePassword(int $id, string $newPassword): bool
    {
        $stmt = self::db()->prepare('
            UPDATE users
            SET password             = ?,
                must_change_password = 0,
                password_changed_at  = NOW()
            WHERE id = ?
        ');
        return (bool) $stmt->execute([password_hash($newPassword, PASSWORD_BCRYPT), $id]);
    }

    /**
     * Force the user to change password on next login (used by admin password reset).
     */
    public static function forcePasswordChange(int $id): bool
    {
        $stmt = self::db()->prepare(
            'UPDATE users SET must_change_password = 1 WHERE id = ?'
        );
        return (bool) $stmt->execute([$id]);
    }

    public static function updateStatus(int $id, string $status): bool
    {
        $stmt = self::db()->prepare('UPDATE users SET status = ? WHERE id = ?');
        return (bool) $stmt->execute([$status, $id]);
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Store a password reset token.
     * Creates the password_reset_tokens table on first use (zero-migration bootstrap).
     */
    public static function createResetToken(int $userId, string $rawToken, int $expiryMinutes = 30): bool
    {
        try {
            self::db()->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$userId]);
            $stmt = self::db()->prepare('
                INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))
            ');
            return (bool) $stmt->execute([$userId, hash('sha256', $rawToken), $expiryMinutes]);
        } catch (\Exception $e) {
            error_log('UserModel::createResetToken: ' . $e->getMessage());
            return false;
        }
    }

    public static function findByResetToken(string $rawToken): ?array
    {
        try {
            $stmt = self::db()->prepare('
                SELECT u.*, t.expires_at
                FROM password_reset_tokens t
                JOIN users u ON u.id = t.user_id
                WHERE t.token_hash = ? AND t.expires_at > NOW()
                LIMIT 1
            ');
            $stmt->execute([hash('sha256', $rawToken)]);
            return $stmt->fetch() ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function deleteResetToken(int $userId): void
    {
        try {
            self::db()->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')
                      ->execute([$userId]);
        } catch (\Exception $e) {
            error_log('UserModel::deleteResetToken: ' . $e->getMessage());
        }
    }

    /**
     * Check whether a raw token exists and has not yet expired.
     * Does NOT consume (delete) the token — safe to call on page load.
     */
    public static function isResetTokenValid(string $rawToken): bool
    {
        return self::findByResetToken($rawToken) !== null;
    }

    /**
     * Validate the raw token, delete it immediately (one-time use), and return
     * the associated user row so the caller can update the password.
     * Returns null if the token is invalid, expired, or already consumed.
     */
    public static function consumeResetToken(string $rawToken): ?array
    {
        $row = self::findByResetToken($rawToken);
        if ($row === null) {
            return null;
        }
        self::deleteResetToken((int) $row['id']);
        return $row;
    }
}