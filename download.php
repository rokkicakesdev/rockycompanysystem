<?php
// download.php
// ─────────────────────────────────────────────────────────────────────────────
//  Secure file download handler.
//
//  ALL uploaded files (employee docs, reimbursement receipts) MUST be served
//  through this handler — never via direct URL.
//
//  Security controls:
//   1. Session authentication — must be logged in
//   2. Ownership / role check — employee can only download their own files;
//      admin/management can download any file
//   3. Path traversal prevention — token resolved to absolute path via DB,
//      never constructed from user input
//   4. MIME type detected from real file content (finfo), not extension
//   5. File existence check before streaming
//   6. No PHP execution possible (files are in uploads/ which has .htaccess
//      denying PHP execution, AND this handler streams bytes, never executes)
//   7. Content-Disposition: attachment forces download, prevents inline XSS
//
//  Usage:
//    download.php?type=doc&id=42          — employee document (id = record id)
//    download.php?type=reimb&id=17        — reimbursement receipt (id = record id)
// ─────────────────────────────────────────────────────────────────────────────

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Model.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ── 1. Authentication ─────────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized. Please <a href="' . BASE_URL . '/index.php">sign in</a>.');
}

$userId     = (int)$_SESSION['user_id'];
$role       = $_SESSION['role']        ?? '';
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$isAdmin    = in_array($role, [ROLE_ADMIN, ROLE_MANAGEMENT], true);

// ── 2. Input validation ───────────────────────────────────────────────────────
$type = trim($_GET['type'] ?? '');
$id   = (int)($_GET['id']  ?? 0);

if (!$id || !in_array($type, ['doc', 'reimb'], true)) {
    http_response_code(400);
    die('Invalid request.');
}

// ── 3. Resolve file path from DB (never trust user-supplied path) ─────────────
$filePath    = null;
$ownerEmpId  = null;

if ($type === 'doc') {
    // employee_documents table
    $record = Model::findDocumentById($id);
    if ($record) {
        $filePath   = $record['file_path']   ?? null;
        $ownerEmpId = (int)($record['employee_id'] ?? 0);
    }
} elseif ($type === 'reimb') {
    // reimbursements table — need to fetch receipt_file
    $db   = Database::getInstance();
    $stmt = $db->prepare('SELECT employee_id, receipt_file FROM reimbursements WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $record = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($record) {
        $filePath   = $record['receipt_file'] ?? null;
        $ownerEmpId = (int)($record['employee_id'] ?? 0);
    }
}

if (empty($filePath) || $ownerEmpId === null) {
    http_response_code(404);
    die('File not found.');
}

// ── 4. Ownership / authorization check ────────────────────────────────────────
// Employees may only access their own files.
// Admins and management can access any file.
if (!$isAdmin && $ownerEmpId !== $employeeId) {
    http_response_code(403);
    die('Access denied. This file does not belong to your account.');
}

// ── 5. Resolve absolute path — prevent path traversal ─────────────────────────
// $filePath is stored as a relative path like "uploads/employee_docs/1/abc123.pdf"
// We resolve it against the project root and use realpath() to canonicalize.
$projectRoot = rtrim(__DIR__, '/');
$absPath     = $projectRoot . '/' . ltrim($filePath, '/');
$realPath    = realpath($absPath);

// Ensure the resolved path is inside the uploads directory (path traversal guard)
$uploadsRoot = realpath($projectRoot . '/uploads');
if (!$realPath || !$uploadsRoot || !str_starts_with($realPath, $uploadsRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(403);
    die('Access denied. Invalid file path.');
}

if (!is_file($realPath) || !is_readable($realPath)) {
    http_response_code(404);
    die('File not found on server. It may have been deleted.');
}

// ── 6. Detect real MIME type from file content (not extension) ────────────────
$finfo    = new \finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($realPath);

// Whitelist MIME types we allow to be served — reject anything unexpected
$allowedMimes = [
    'application/pdf'  => 'pdf',
    'image/jpeg'       => 'jpg',
    'image/png'        => 'png',
    'image/webp'       => 'webp',
];

if (!isset($allowedMimes[$mimeType])) {
    http_response_code(415);
    die('Unsupported file type.');
}

// ── 7. Log the download access ────────────────────────────────────────────────
$logDesc = "Downloaded {$type} file ID:{$id} (" . basename($realPath) . ")";
Model::log($userId, 'DOWNLOAD_FILE', $logDesc);

// ── 8. Stream file to browser ─────────────────────────────────────────────────
// Force download (attachment) — do NOT use inline for PDFs on employee-facing
// pages as that could expose content in browser history on shared computers.
$fileSize = filesize($realPath);
$fileName = basename($realPath); // random hex name — safe to expose

// Clear any output buffering to ensure clean binary stream
while (ob_get_level()) ob_end_clean();

header('Content-Type: '        . $mimeType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: '      . $fileSize);
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
// Prevent clickjacking on the download response itself
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

readfile($realPath);
exit;
