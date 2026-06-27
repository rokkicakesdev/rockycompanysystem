-- ============================================================
--  Rocky HRIS & Payroll — Loan & Cash Advance Migration
--  Run this against rocky_payroll database
--  Tables are created with IF NOT EXISTS / ADD COLUMN IF NOT EXISTS
--  so the script is safe to run multiple times.
-- ============================================================

-- ────────────────────────────────────────────────────────────
-- 1. Employee Loans table
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `employee_loans` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id`        INT UNSIGNED NOT NULL,
    `loan_type`          VARCHAR(60) NOT NULL
        COMMENT 'sss_salary_loan | pagibig_mpl | company_cash_advance | company_loan',
    `loan_amount`        DECIMAL(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Original loan principal',
    `monthly_deduction`  DECIMAL(10,2) NOT NULL DEFAULT 0.00
        COMMENT 'Fixed monthly instalment amount',
    `cutoff_deduction`   DECIMAL(10,2) NOT NULL DEFAULT 0.00
        COMMENT 'Per semi-monthly cutoff deduction = monthly_deduction / 2',
    `remaining_balance`  DECIMAL(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Decrements each payroll cutoff; auto-sets status=fully_paid when zero',
    `start_date`         DATE NOT NULL
        COMMENT 'First payroll cutoff on or after this date triggers deduction',
    `status`             ENUM('active','fully_paid','cancelled') NOT NULL DEFAULT 'active',
    `reference_no`       VARCHAR(100) DEFAULT NULL
        COMMENT 'SSS control number, Pag-IBIG reference, or internal ref',
    `notes`              TEXT DEFAULT NULL,
    `created_by`         INT UNSIGNED DEFAULT NULL,
    `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_employee_id` (`employee_id`),
    KEY `idx_status`      (`status`),
    CONSTRAINT `fk_loan_employee`
        FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- 2. Loan Deduction Log table
--    Records every payroll deduction per loan for audit trail
--    and balance reversal when a payroll record is deleted.
-- ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `loan_deduction_log` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `loan_id`         INT UNSIGNED NOT NULL,
    `payroll_id`      INT UNSIGNED NOT NULL,
    `employee_id`     INT UNSIGNED NOT NULL,
    `period`          VARCHAR(9) NOT NULL
        COMMENT 'YYYY-MM-1 or YYYY-MM-2',
    `amount_deducted` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `balance_before`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `balance_after`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_loan_id`       (`loan_id`),
    KEY `idx_payroll_id`    (`payroll_id`),
    KEY `idx_emp_period`    (`employee_id`, `period`),
    CONSTRAINT `fk_log_loan`
        FOREIGN KEY (`loan_id`) REFERENCES `employee_loans` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────
-- 3. Add loan_deduction column to payroll_records
--    (safe: ADD COLUMN IF NOT EXISTS — MariaDB 10.4+)
-- ────────────────────────────────────────────────────────────
ALTER TABLE `payroll_records`
    ADD COLUMN IF NOT EXISTS `loan_deduction` DECIMAL(10,2) NOT NULL DEFAULT 0.00
        COMMENT 'Auto-deducted SSS/Pag-IBIG/company loan instalments for this cutoff'
        AFTER `salary_deduction`;
