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

    public static function findByEmail(string $email): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE email = ? AND status = ? LIMIT 1');
        $stmt->execute([$email, 'active']);
        return $stmt->fetch() ?: null;
    }

    /**
     * Store a password reset token.
     * Creates the password_reset_tokens table on first use (zero-migration bootstrap).
     * Token is hashed with SHA-256 before storage — raw token goes only to the email.
     */
    public static function createResetToken(int $userId, string $rawToken, int $expiryMinutes = 30): bool
    {
        self::ensureResetTokenTable();
        // Invalidate any existing tokens for this user first
        self::db()->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$userId]);

        $stmt = self::db()->prepare('
            INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))
        ');
        return (bool) $stmt->execute([$userId, hash('sha256', $rawToken), $expiryMinutes]);
    }

    /**
     * Validate a raw token (hashes it internally for comparison).
     * Returns the matching user record or null if invalid/expired.
     */
    public static function consumeResetToken(string $rawToken): ?array
    {
        self::ensureResetTokenTable();
        $hash = hash('sha256', $rawToken);
        $stmt = self::db()->prepare('
            SELECT prt.user_id, u.name, u.email, u.username
            FROM password_reset_tokens prt
            JOIN users u ON u.id = prt.user_id
            WHERE prt.token_hash = ?
              AND prt.expires_at > NOW()
              AND prt.used_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) return null;

        // Mark token as used immediately (single-use)
        self::db()->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = ?')
                  ->execute([$hash]);

        return $row;
    }

    /**
     * Check a token is still valid (without consuming it) — used on the reset form page load.
     */
    public static function isResetTokenValid(string $rawToken): bool
    {
        self::ensureResetTokenTable();
        $stmt = self::db()->prepare('
            SELECT COUNT(*) FROM password_reset_tokens
            WHERE token_hash = ? AND expires_at > NOW() AND used_at IS NULL
        ');
        $stmt->execute([hash('sha256', $rawToken)]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /** Bootstrap the reset token table if it doesn't exist yet. */
    private static function ensureResetTokenTable(): void
    {
        self::db()->exec("
            CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id`    INT UNSIGNED NOT NULL,
                `token_hash` VARCHAR(64)  NOT NULL,
                `expires_at` DATETIME     NOT NULL,
                `used_at`    DATETIME     DEFAULT NULL,
                `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_token` (`token_hash`),
                KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}