<?php
// app/views/admin/gov_reports_sss_flatfile.php
// ─────────────────────────────────────────────────────────────────────────────
//  Generates the SSS-prescribed flat-file (.txt) R-3 format for upload to the
//  My.SSS Employer Portal (SSS Online Filing System).
//
//  Usage: gov_reports_sss_flatfile.php?month=2026-03
//
//  Format specification — SSS File Upload Format (Updated 2024):
//
//  HEADER RECORD (1 line):
//    Col  1–10  : Employer SSS ID (10 chars, right-pad spaces)
//    Col 11–14  : Filing year  (4 digits)
//    Col 15–16  : Filing month (2 digits, zero-padded)
//    Col 17–20  : Record count (4 digits, zero-padded)
//    Col 21–30  : Total EE contributions (10 chars, no decimal — centavos, zero-padded)
//    Col 31–40  : Total ER contributions (10 chars, no decimal — centavos, zero-padded)
//    Col 41     : Record type = 'H'
//
//  DETAIL RECORDS (1 line per employee):
//    Col  1–10  : Employee SSS Number (10 chars, right-pad spaces, dashes stripped)
//    Col 11–20  : Employee Name — Last Name (10 chars, right-pad spaces)
//    Col 21–30  : Employee Name — First Name (10 chars, right-pad spaces)
//    Col 31–32  : Employee Name — Middle Initial (2 chars)
//    Col 33–37  : MSC (5 digits, zero-padded, no decimal)
//    Col 38–44  : EE Contribution (7 digits, zero-padded, centavos — e.g. ₱250.00 = 0025000)
//    Col 45–51  : ER Contribution (7 digits, zero-padded, centavos)
//    Col 52     : Record type = 'D'
//
//  TRAILER RECORD (1 line):
//    Col  1–4   : Record count of detail records (4 digits, zero-padded)
//    Col  5–14  : Total EE (10 chars, centavos, zero-padded)
//    Col 15–24  : Total ER (10 chars, centavos, zero-padded)
//    Col 25     : Record type = 'T'
//
//  Notes:
//   - All fields are fixed-width, no delimiters.
//   - SSS Number: strip dashes, 10 numeric chars.
//   - Amounts: no decimal point, in centavos (multiply by 100, zero-pad).
//   - Names: uppercase, special chars stripped.
//   - Line ending: CRLF (\r\n) per SSS specification.
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/Model.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) {
    header('Location: ' . BASE_URL . '/index.php?error=access_denied'); exit;
}

// ── Parameters ────────────────────────────────────────────────────────────────
$currentYM   = date('Y-m');
$selectedYM  = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : $currentYM;
$yr          = substr($selectedYM, 0, 4);
$mo          = substr($selectedYM, 5, 2);

// ── Employer info ─────────────────────────────────────────────────────────────
$co           = Model::getReportHeader();
$sssEmpId     = preg_replace('/[^0-9]/', '', $co['sss_employer_id'] ?? '');
$sssEmpId     = str_pad(substr($sssEmpId, 0, 10), 10);       // 10 chars, right-padded
$coName       = strtoupper($co['company_name'] ?: (defined('COMPANY_NAME') ? COMPANY_NAME : ''));

// ── Payroll data ──────────────────────────────────────────────────────────────
$db   = Database::getInstance();
$stmt = $db->prepare("
    SELECT
        e.name          AS employee_name,
        e.sss_no,
        MAX(pr.sss_msc) AS sss_msc,
        SUM(pr.sss_ee)  AS sss_ee,
        SUM(pr.sss_er)  AS sss_er
    FROM payroll_records pr
    JOIN employees e ON e.id = pr.employee_id
    WHERE pr.status = 'released'
      AND pr.period LIKE ?
    GROUP BY pr.employee_id, e.name, e.sss_no
    ORDER BY e.name
");
$stmt->execute([$selectedYM . '-%']);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($records)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "No released payroll records found for {$yr}-{$mo}.";
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Convert float amount to centavos integer string, zero-padded to $len digits. */
function toCentavos(float $amount, int $len): string
{
    return str_pad((string)(int)round($amount * 100), $len, '0', STR_PAD_LEFT);
}

/** Pad/truncate a string to exactly $len chars (right-pad with spaces). */
function fixedStr(string $s, int $len): string
{
    $s = strtoupper(preg_replace('/[^A-Za-z0-9\s\-\/\.&,]/', '', $s));
    return str_pad(substr($s, 0, $len), $len);
}

/**
 * Split an employee full name into [lastName, firstName, middleInitial].
 * Assumes format: "Last Name, First Name M.I." or "First Last".
 */
function splitName(string $fullName): array
{
    $fullName = trim($fullName);
    if (strpos($fullName, ',') !== false) {
        [$last, $rest] = explode(',', $fullName, 2);
        $rest  = trim($rest);
        $parts = preg_split('/\s+/', $rest);
        $first = $parts[0] ?? '';
        $mi    = isset($parts[1]) ? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $parts[1]), 0, 1)) : '';
    } else {
        $parts = preg_split('/\s+/', $fullName);
        $last  = array_pop($parts) ?: '';
        $first = array_shift($parts) ?: '';
        $mi    = '';
        if (!empty($parts)) {
            $mi = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', implode('', $parts)), 0, 1));
        }
    }
    return [trim($last), trim($first), $mi];
}

// ── Build detail records ──────────────────────────────────────────────────────
$totalEeCents  = 0;
$totalErCents  = 0;
$detailLines   = [];

foreach ($records as $r) {
    $sssNo = preg_replace('/[^0-9]/', '', $r['sss_no'] ?? '');
    $sssNo = str_pad(substr($sssNo, 0, 10), 10);

    [$last, $first, $mi] = splitName($r['employee_name']);

    $msc   = str_pad((string)(int)round((float)$r['sss_msc']), 5, '0', STR_PAD_LEFT);
    $ee    = toCentavos((float)$r['sss_ee'], 7);
    $er    = toCentavos((float)$r['sss_er'], 7);

    $totalEeCents += (int)round((float)$r['sss_ee'] * 100);
    $totalErCents += (int)round((float)$r['sss_er'] * 100);

    $line  = $sssNo                         // Col  1–10  : SSS No
           . fixedStr($last,  10)           // Col 11–20  : Last Name
           . fixedStr($first, 10)           // Col 21–30  : First Name
           . str_pad($mi, 2)                // Col 31–32  : MI
           . $msc                           // Col 33–37  : MSC
           . $ee                            // Col 38–44  : EE
           . $er                            // Col 45–51  : ER
           . 'D';                           // Col 52     : Record Type

    $detailLines[] = $line;
}

$count      = count($detailLines);
$totalEeStr = str_pad((string)$totalEeCents, 10, '0', STR_PAD_LEFT);
$totalErStr = str_pad((string)$totalErCents, 10, '0', STR_PAD_LEFT);

// ── Header record ─────────────────────────────────────────────────────────────
$header = $sssEmpId                                          // Col  1–10 : Employer SSS ID
        . $yr                                                // Col 11–14 : Year
        . $mo                                                // Col 15–16 : Month
        . str_pad((string)$count, 4, '0', STR_PAD_LEFT)     // Col 17–20 : Record count
        . $totalEeStr                                        // Col 21–30 : Total EE
        . $totalErStr                                        // Col 31–40 : Total ER
        . 'H';                                               // Col 41    : Record type

// ── Trailer record ────────────────────────────────────────────────────────────
$trailer = str_pad((string)$count, 4, '0', STR_PAD_LEFT)    // Col  1–4  : Count
         . $totalEeStr                                        // Col  5–14 : Total EE
         . $totalErStr                                        // Col 15–24 : Total ER
         . 'T';                                               // Col 25    : Record type

// ── Assemble file ─────────────────────────────────────────────────────────────
$lines   = array_merge([$header], $detailLines, [$trailer]);
$content = implode("\r\n", $lines) . "\r\n";

// ── Stream to browser ─────────────────────────────────────────────────────────
$safeEmpId   = preg_replace('/[^0-9]/', '', $co['sss_employer_id'] ?? 'XXXXX');
$filename    = 'SSS_R3_' . $yr . $mo . '_' . ($safeEmpId ?: 'EMPLOYER') . '.txt';

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
header('Cache-Control: no-cache, no-store, must-revalidate');
echo $content;
exit;
