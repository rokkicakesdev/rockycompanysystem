<?php
// core/models/CompanySettingsModel.php
// ─────────────────────────────────────────────────────────────────────────────
//  Stores and retrieves company-level employer registration numbers used in
//  government remittance reports (SSS R-3, PhilHealth RF-1, Pag-IBIG MCRF,
//  BIR 1601-C).
//
//  Data lives in a key-value `company_settings` table that is auto-created
//  on first use — no separate migration script needed.
//
//  Keys used:
//    sss_employer_id      — SSS 10-digit Employer ID (e.g., 03-1234567-8)
//    sss_branch_code      — SSS Branch Code (3 chars, optional)
//    philhealth_employer_no — PhilHealth Employer Number (e.g., 12-345678901-2)
//    pagibig_employer_mid — Pag-IBIG Employer MID Number
//    bir_tin              — Company BIR TIN (e.g., 123-456-789-000)
//    bir_rdo_code         — BIR RDO Code (3-digit, e.g., 044)
//    company_name         — Override for reports (defaults to COMPANY_NAME constant)
//    company_address      — Override for reports (defaults to COMPANY_ADDRESS constant)
//    company_zip          — Zip code for BIR 1601-C
// ─────────────────────────────────────────────────────────────────────────────

class CompanySettingsModel extends BaseModel
{
    private static bool $tableEnsured = false;

    // ── Table bootstrap ──────────────────────────────────────────────────────

    private static function ensureTable(): void
    {
        if (self::$tableEnsured) return;
        self::db()->exec("
            CREATE TABLE IF NOT EXISTS `company_settings` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `setting_key`   VARCHAR(80)  NOT NULL,
                `setting_value` VARCHAR(255) NOT NULL DEFAULT '',
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_setting_key` (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        self::$tableEnsured = true;
    }

    // ── Public API ───────────────────────────────────────────────────────────

    /** Return a single setting value, or $default if not set. */
    public static function get(string $key, string $default = ''): string
    {
        self::ensureTable();
        $stmt = self::db()->prepare(
            'SELECT setting_value FROM company_settings WHERE setting_key = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['setting_value'] : $default;
    }

    /** Return all settings as an associative array key → value. */
    public static function getAll(): array
    {
        self::ensureTable();
        $stmt = self::db()->query(
            'SELECT setting_key, setting_value FROM company_settings'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out  = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = $r['setting_value'];
        }
        return $out;
    }

    /**
     * Upsert a single setting.
     * Empty-string values are stored as-is (intentional blank = "not configured").
     */
    public static function set(string $key, string $value): bool
    {
        self::ensureTable();
        $stmt = self::db()->prepare("
            INSERT INTO company_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        return (bool) $stmt->execute([$key, $value]);
    }

    /** Bulk-save an array of key → value pairs. Returns count saved. */
    public static function saveMany(array $data): int
    {
        $saved = 0;
        foreach ($data as $key => $value) {
            if (self::set((string)$key, (string)$value)) $saved++;
        }
        return $saved;
    }

    // ── Convenience getters for reports ─────────────────────────────────────

    public static function getReportHeader(): array
    {
        $all = self::getAll();
        return [
            'company_name'           => $all['company_name']           ?? (defined('COMPANY_NAME')    ? COMPANY_NAME    : ''),
            'company_address'        => $all['company_address']        ?? (defined('COMPANY_ADDRESS') ? COMPANY_ADDRESS : ''),
            'company_zip'            => $all['company_zip']            ?? '',
            'sss_employer_id'        => $all['sss_employer_id']        ?? '',
            'sss_branch_code'        => $all['sss_branch_code']        ?? '',
            'philhealth_employer_no' => $all['philhealth_employer_no'] ?? '',
            'pagibig_employer_mid'   => $all['pagibig_employer_mid']   ?? '',
            'bir_tin'                => $all['bir_tin']                ?? '',
            'bir_rdo_code'           => $all['bir_rdo_code']           ?? '',
        ];
    }
}
