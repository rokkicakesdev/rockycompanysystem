<?php
// app/ajax/pending_count.php
// Returns pending leave count as JSON - called by sidebar badge polling

session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'management'])) {
    http_response_code(403);
    echo json_encode(['count' => 0]);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Model.php';

header('Content-Type: application/json');
echo json_encode(['count' => (int)(Model::countPendingLeaves() ?? 0)]);