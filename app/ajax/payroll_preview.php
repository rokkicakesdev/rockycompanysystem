<?php
// app/ajax/payroll_preview.php
// Returns live net pay preview for payroll_settings.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Model.php';
require_once __DIR__ . '/../../core/PhilippineDeductions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$basic     = max(0.0, (float)($_GET['basic']     ?? 0));
$allowance = max(0.0, (float)($_GET['allowance'] ?? 0));
$c1Raw     = trim($_GET['cutoff1'] ?? '');
$taxMethod = in_array($_GET['tax_method'] ?? '', ['half_monthly','bir_table']) ? $_GET['tax_method'] : 'half_monthly';
$govMode   = in_array($_GET['gov_mode']   ?? '', ['second_cutoff','split'])    ? $_GET['gov_mode']   : 'second_cutoff';

$fixedAmount = ($c1Raw !== '' && is_numeric($c1Raw)) ? (float)$c1Raw : null;

$c1 = PhilippineDeductions::computeFirstCutoff($basic, $allowance, $fixedAmount, $taxMethod);
$c2 = PhilippineDeductions::computeSecondCutoff($basic, $allowance, $fixedAmount, $taxMethod, $govMode);

echo json_encode([
    'c1_net'    => number_format($c1['net_pay'], 2),
    'c2_net'    => number_format($c2['net_pay'], 2),
    'total_net' => number_format($c1['net_pay'] + $c2['net_pay'], 2),
    'c1_gross'  => number_format($c1['gross_pay'], 2),
    'c2_gross'  => number_format($c2['gross_pay'], 2),
]);
exit;
