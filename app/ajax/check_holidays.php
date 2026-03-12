<?php
// app/ajax/check_holidays.php
// Returns holidays that fall within a date range as JSON.
// Used by leave.php to warn about holiday overlaps.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Model.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'management'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to']   ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    echo json_encode(['holidays' => []]);
    exit;
}

// Clamp range to 365 days max to avoid abuse
if ((strtotime($dateTo) - strtotime($dateFrom)) > 86400 * 365) {
    echo json_encode(['holidays' => []]);
    exit;
}

$holidays = Model::getHolidaysInRange($dateFrom, $dateTo);

echo json_encode([
    'holidays' => array_map(fn($h) => [
        'name' => $h['name'],
        'date' => date('M d, Y', strtotime($h['date'])),
        'type' => $h['type'],
    ], $holidays),
]);
exit;