<?php
// ─── App Config ────────────────────────────────────────────────
define('APP_NAME',    'Rocky HRIS + Payroll System');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    ''); // Leave empty for relative paths

// ─── Session ───────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── Roles ─────────────────────────────────────────────────────
define('ROLE_ADMIN',      'admin');
define('ROLE_MANAGEMENT', 'management');

// ─── Leave Types (for UI labels) ──────────────────────────────
define('LEAVE_TYPES', [
    'sick'          => 'Sick Leave (SL)',
    'vacation'      => 'Vacation Leave (VL)',
    'bereavement'   => 'Bereavement Leave',
    'emergency'     => 'Emergency Leave',
    'sil'           => 'Service Incentive Leave (SIL)',
    'maternity'     => 'Maternity Leave',
    'paternity'     => 'Paternity Leave',
    'solo_parent'   => 'Solo Parent Leave',
    'vawc'          => 'VAWC Leave',
    'magna_carta'   => 'Magna Carta Leave',
    'unpaid'        => 'Leave Without Pay (LWOP)',
]);

// ─── Leave Balance Fields ─────────────────────────────────────
define('LEAVE_BALANCE_FIELDS', [
    'sick'          => 'sick_leave_balance',
    'vacation'      => 'vacation_leave_balance',
    'bereavement'   => 'bereavement_leave_balance',
    'emergency'     => 'emergency_leave_balance',
    'sil'           => 'sil_balance',
    'maternity'     => 'maternity_leave_balance',
    'paternity'     => 'paternity_leave_balance',
    'solo_parent'   => 'solo_parent_leave_balance',
    'vawc'          => 'vawc_leave_balance',
    'magna_carta'   => 'magna_carta_leave_balance',
]);

// ─── Employment Types ─────────────────────────────────────────
define('EMPLOYMENT_TYPES', [
    'regular'       => 'Regular',
    'probationary'  => 'Probationary',
    'contractual'   => 'Contractual',
    'part_time'     => 'Part-Time',
]);

// ─── Employee Status ──────────────────────────────────────────
define('EMPLOYEE_STATUS', [
    'active'      => 'Active',
    'inactive'    => 'Inactive',
    'resigned'    => 'Resigned',
    'terminated'  => 'Terminated',
]);

// ─── Company Settings ─────────────────────────────────────────
define('COMPANY_NAME',    'Rocky Company');
define('COMPANY_ADDRESS', 'Paranaque City, Metro Manila');
define('WORKING_DAYS',    22);   // Default working days per month
define('WORK_HOURS',       8);   // Hours per day

// ─── Pagination ───────────────────────────────────────────────
define('RECORDS_PER_PAGE', 15);