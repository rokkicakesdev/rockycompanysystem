<?php
// ===========================================================================
//  ALL POST HANDLERS MUST BE BEFORE ANY HTML OUTPUT
// ===========================================================================
$pageTitle  = 'Payroll Processing';
$breadcrumb = 'Payroll';
$activeMenu = 'payroll';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('ROLE_ADMIN'))   require_once __DIR__ . '/../../../config/config.php';
if (!defined('DB_HOST'))      require_once __DIR__ . '/../../../config/database.php';
if (!class_exists('Database'))             require_once __DIR__ . '/../../../core/Database.php';
if (!class_exists('Model'))                require_once __DIR__ . '/../../../core/Model.php';
if (!class_exists('PhilippineDeductions')) require_once __DIR__ . '/../../../core/PhilippineDeductions.php';

// Auth guard
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', [ROLE_ADMIN, ROLE_MANAGEMENT])) {
    header('Location: ' . BASE_URL . '/index.php?error=access_denied'); exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ===========================================================================
//  POST: GENERATE PAYROLL
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_payroll'])) {

    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Invalid security token. Please refresh and try again.</div>";
    } else {
        $genPeriod      = trim($_POST['gen_period'] ?? '');
        $selectedEmpIds = $_POST['employee_ids'] ?? [];
        $maxAllowed     = date('Y-m', strtotime('+1 month'));

        if (!preg_match('/^\d{4}-\d{2}-[12]$/', $genPeriod)) {
            $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Invalid payroll period format.</div>";
        } elseif (Model::periodBase($genPeriod) > $maxAllowed) {
            $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Cannot generate payroll more than one month in the future.</div>";
        } elseif (empty($selectedEmpIds)) {
            $msg = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle mr-2'></i>No employees selected.</div>";
        } else {
            // ── Check for missing attendance logs ─────────────────────
            $missingAttendance = Model::getEmployeesWithMissingAttendance($selectedEmpIds, $genPeriod);
            if (!empty($missingAttendance)) {
                $missingList = '<ul class="mb-0 mt-1 payroll-skip-list">';
                foreach ($missingAttendance as $name) {
                    $missingList .= '<li>' . $name . '</li>';
                }
                $missingList .= '</ul>';
                $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>"
                     . "<strong>Cannot generate payroll.</strong> The following employee(s) have no attendance log for this period:"
                     . $missingList . "</div>";
            } else {
            $generated   = 0;
            $skipped     = 0;
            $skipReasons = [];
            $workingDays = WORKING_DAYS;  // full month working days (default 22)
            $db          = Database::getInstance();
            $db->beginTransaction();
            $txSuccess = false;

            try {
                foreach ($selectedEmpIds as $empId) {
                    $empId = (int)$empId;
                    $emp   = Model::findEmployeeById($empId);

                    if (!$emp) { $skipped++; $skipReasons[] = "ID:{$empId} - employee not found"; continue; }
                    if ($emp['status'] !== 'active') { $skipped++; $skipReasons[] = htmlspecialchars($emp['name']) . " - not active ({$emp['status']})"; continue; }
                    if (Model::employeeExistsInPeriod($empId, $genPeriod)) { $skipped++; $skipReasons[] = htmlspecialchars($emp['name']) . " - already has a record for {$genPeriod}"; continue; }

                    $settings    = Model::getEmployeePayrollSettings($empId);
                    $fixedAmount = $settings['cutoff1_fixed_amount'] !== null ? (float)$settings['cutoff1_fixed_amount'] : null;
                    $taxMethod   = $settings['tax_method'];
                    $govMode     = $settings['gov_deduction_mode'];
                    $cutoffNum   = Model::periodCutoff($genPeriod);
                    $yearMonth   = Model::periodBase($genPeriod);  // e.g. "2026-01"
                    $dateStart   = $emp['date_start'] ?? $emp['date_hired'] ?? '';

                    // ── Determine cutoff date range ─────────────────────────────
                    // 1st cutoff: 1st–15th of the month
                    // 2nd cutoff: 16th–last day of the month
                    [$year, $month] = explode('-', $yearMonth);
                    $lastDay = date('t', mktime(0, 0, 0, (int)$month, 1, (int)$year));
                    if ($cutoffNum === 1) {
                        $cutoffFrom = "{$yearMonth}-01";
                        $cutoffTo   = "{$yearMonth}-15";
                    } else {
                        $cutoffFrom = "{$yearMonth}-16";
                        $cutoffTo   = "{$yearMonth}-{$lastDay}";
                    }

                    // ── Get cutoff-specific attendance (uses date_start for proration) ──
                    $attData               = Model::getCutoffAttendanceSummary($empId, $cutoffFrom, $cutoffTo, $dateStart);
                    $scheduledDays         = (int)($attData['scheduled_days']          ?? 0);  // full cutoff weekdays (payslip display)
                    $effectiveSchedDays    = (int)($attData['effective_scheduled_days'] ?? $scheduledDays); // from dateStart (deduction denominator)
                    $totalAbsent           = (int)($attData['total_absent']             ?? 0);
                    $daysHalf              = (int)($attData['days_half']                ?? 0);
                    $daysPaidLeave         = (int)($attData['days_on_leave']            ?? 0);
                    $daysUnpaidLeave       = (int)($attData['days_unpaid_leave']        ?? 0);
                    $daysPresent           = (int)($attData['days_present']             ?? 0);
                    $daysAbsentOnly        = (int)($attData['days_absent']              ?? 0); // excl. unpaid leave
                    // Overtime and holiday premium data
                    $otHoursRegularDay     = (float)($attData['ot_hours_regular_day']        ?? 0);
                    $otHoursOnHoliday      = (float)($attData['ot_hours_on_holiday']         ?? 0);
                    $workedRegHolidays     = (int)($attData['worked_regular_holiday_days']   ?? 0);
                    $workedSpecialHolidays = (int)($attData['worked_special_holiday_days']   ?? 0);

                    // ── Compute daily rate and deductions ──────────────────────
                    // Daily rate denominator = scheduledDays (ALL weekdays in the full cutoff,
                    // including public holidays). This is the correct denominator because
                    // holidays are PAID days in Philippine labor law — they are part of the
                    // salary structure, not excluded from it.
                    //
                    // Proration for new hires:
                    //   proratedDays = scheduledDays − effectiveSchedDays
                    //   = weekdays BEFORE the employee's start date (e.g. Jan 1 holiday + Jan 2
                    //     for an employee who started Jan 5 in a Jan 1–15 cutoff = 2 days).
                    //   proratedDeduction = proratedDays × dailyRate
                    //   This is added to absentDeduction so it flows into total_deductions
                    //   and net_pay automatically without any schema change.
                    if ($scheduledDays > 0) {
                        $cutoffBasicAmount    = $fixedAmount !== null ? $fixedAmount : round((float)$emp['basic_salary'] / 2, 2);
                        $dailyRate            = round($cutoffBasicAmount / $scheduledDays, 4);
                        // Proration deduction = days before employee's start date × daily rate
                        $proratedDays        = max(0, $scheduledDays - $effectiveSchedDays);
                        $proratedDeduction   = round($proratedDays * $dailyRate, 2);
                        // Absent deduction = explicit absent days (NOT unpaid leave — tracked separately)
                        $absentDeduction      = round($proratedDeduction + ($daysAbsentOnly * $dailyRate) + ($daysHalf * $dailyRate * 0.5), 2);
                        // Unpaid leave deduction = LWOP days × daily rate
                        $unpaidLeaveDeduction = round($daysUnpaidLeave * $dailyRate, 2);
                    } else {
                        $dailyRate            = 0.0;
                        $proratedDeduction    = 0.0;
                        $absentDeduction      = 0.0;
                        $unpaidLeaveDeduction = 0.0;
                    }

                    // ── Overtime pay (Art. 87 Labor Code) ─────────────────────
                    $overtimePay = 0.0;
                    if ($scheduledDays > 0 && ($otHoursRegularDay > 0 || $otHoursOnHoliday > 0)) {
                        $overtimePay = PhilippineDeductions::computeOvertimePay(
                            $cutoffBasicAmount,
                            $scheduledDays,
                            WORK_HOURS,
                            $otHoursRegularDay,
                            $otHoursOnHoliday
                        );
                    }

                    // ── Holiday premium pay (PD 442 Labor Code) ────────────────
                    // Additional pay for working on Regular (200%) or Special Non-Working (130%) holidays.
                    // The base daily rate is already in gross_pay as a normal worked day.
                    // This is the PREMIUM (additional) portion only.
                    $holidayPay = 0.0;
                    if ($scheduledDays > 0 && ($workedRegHolidays > 0 || $workedSpecialHolidays > 0)) {
                        $holidayPay = PhilippineDeductions::computeHolidayPremiumPay(
                            $cutoffBasicAmount,
                            $scheduledDays,
                            $workedRegHolidays,
                            $workedSpecialHolidays
                        );
                    }

                    $extraEarnings = round($overtimePay + $holidayPay, 2);

                    // ── 13th month (December 1st cutoff only) ──────────────────
                    $thirteenthAmount = 0.0;
                    if (Model::isDecember1stCutoff($genPeriod)) {
                        $rec13 = Model::get13thMonthByEmployee($empId, Model::periodYear($genPeriod));
                        if ($rec13 && $rec13['status'] === 'pending') {
                            $thirteenthAmount = (float)$rec13['amount'];
                        }
                    }

                    // ── Year-end reconciliation (December 2nd cutoff only) ──────
                    // Only applies to employees who have been with the company for at least
                    // one full cutoff BEFORE December. New hires in December are excluded
                    // because they haven't had enough periods for any tax over/under-payment.
                    $reconciliation = 0.0;
                    if (Model::isDecember2ndCutoff($genPeriod)) {
                        $year4 = Model::periodYear($genPeriod);
                        if (Model::hasPayrollBeforeDecember($empId, $year4)) {
                            // annualBasic = YTD basic from all prior records + actual basic earned in this Dec 2nd cutoff.
                            // Use (cutoffBasicAmount - proratedDeduction) instead of raw basic_salary/2
                            // to correctly exclude proration gaps for employees hired mid-cutoff.
                            $currentCutoffEarned = max(0.0, $cutoffBasicAmount - $proratedDeduction);
                            $annualBasic   = Model::getTotalBasicByYear($empId, $year4) + $currentCutoffEarned;
                            $annualGovDeds = Model::getTotalGovDedsByYear($empId, $year4);
                            $annualTaxPaid = Model::getTotalWithholdingTaxByYear($empId, $year4);
                            $reconciliation = PhilippineDeductions::computeYearEndReconciliation(
                                $annualBasic, $annualGovDeds, $annualTaxPaid
                            );
                        }
                    }

                    // ── Compute payroll deductions ──────────────────────────────
                    if ($cutoffNum === 1) {
                        $deductions = PhilippineDeductions::computeFirstCutoff(
                            (float)$emp['basic_salary'], (float)($emp['allowance'] ?? 0),
                            $fixedAmount, $taxMethod, $thirteenthAmount,
                            $absentDeduction + $unpaidLeaveDeduction,
                            $extraEarnings
                        );
                    } else {
                        $deductions = PhilippineDeductions::computeSecondCutoff(
                            (float)$emp['basic_salary'], (float)($emp['allowance'] ?? 0),
                            $fixedAmount, $taxMethod, $govMode,
                            $absentDeduction + $unpaidLeaveDeduction, $reconciliation,
                            $extraEarnings
                        );
                    }

                    // Days worked = full present days + half-days counted as 0.5 each.
                    // NOTE: $daysPresent counts only 'present'/'late' rows — half_day rows
                    // are tracked separately in $daysHalf and must be ADDED, not subtracted.
                    $daysWorked = max(0, $daysPresent + ($daysHalf * 0.5));

                    $record = [
                        'employee_id'             => $empId,
                        'period'                  => $genPeriod,
                        'basic_salary'            => $deductions['basic_salary'],
                        'allowance'               => $deductions['allowance'],
                        'gross_pay'               => $deductions['gross_pay'],
                        'sss_msc'                 => $deductions['sss_msc'],
                        'sss_ee'                  => $deductions['sss_ee'],
                        'sss_er'                  => $deductions['sss_er'],
                        'philhealth_mbs'          => $deductions['philhealth_mbs'],
                        'philhealth_ee'           => $deductions['philhealth_ee'],
                        'philhealth_er'           => $deductions['philhealth_er'],
                        'pagibig_mfs'             => $deductions['pagibig_mfs'],
                        'pagibig_ee'              => $deductions['pagibig_ee'],
                        'pagibig_er'              => $deductions['pagibig_er'],
                        'taxable_income'          => $deductions['taxable_income'],
                        'withholding_tax'         => $deductions['withholding_tax'],
                        // other_deductions = year-end reconciliation ONLY (not absent/unpaid)
                        'other_deductions'        => round($deductions['reconciliation'] ?? 0, 2),
                        'absent_deduction'        => $absentDeduction,
                        'unpaid_leave_deduction'  => $unpaidLeaveDeduction,
                        'overtime_pay'            => $overtimePay,
                        'holiday_pay'             => $holidayPay,
                        'salary_deduction'        => 0,
                        'total_deductions'        => $deductions['total_deductions'],
                        'net_pay'                 => $deductions['net_pay'],
                        'days_worked'             => $daysWorked,
                        'days_absent'             => $daysAbsentOnly + $daysUnpaidLeave,
                        'days_paid_leave'         => $daysPaidLeave,
                        'working_days_in_month'   => $scheduledDays,
                        'remarks'                 => ($thirteenthAmount > 0 ? '13th month included. ' : '')
                                                   . ($overtimePay > 0 ? 'OT pay: ₱' . number_format($overtimePay, 2) . '. ' : '')
                                                   . ($holidayPay > 0 ? 'Holiday premium: ₱' . number_format($holidayPay, 2) . '. ' : '')
                                                   . ($reconciliation != 0 ? 'Year-end tax reconciliation: ' . ($reconciliation > 0 ? '+' : '') . number_format($reconciliation, 2) . '.' : ''),
                        'status'                  => 'pending',
                        'processed_by'            => $_SESSION['user_id'],
                    ];

                    if (Model::createPayrollRecord($record)) { $generated++; }
                    else { $skipped++; $skipReasons[] = htmlspecialchars($emp['name']) . " - DB insert failed"; }
                }

                $db->commit();
                $txSuccess = true;

            } catch (Exception $e) {
                $db->rollBack();
                error_log("Payroll generation error for period {$genPeriod}: " . $e->getMessage());
                $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Payroll generation failed due to a database error. No records were saved. Please try again.</div>";
            }

            if ($txSuccess) {
                Model::log($_SESSION['user_id'], 'GENERATE_PAYROLL',
                    "Generated payroll for period {$genPeriod}: {$generated} records created, {$skipped} skipped.");
                $skipParam = !empty($skipReasons) ? '&skipreasons=' . urlencode(implode('|', $skipReasons)) : '';
                header("Location: payroll.php?period={$genPeriod}&msg=generated&count={$generated}&skipped={$skipped}{$skipParam}");
                exit;
            }
            } // end missing attendance else
        }
    }
}

// ===========================================================================
//  POST: RELEASE SINGLE
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_single'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Invalid security token.</div>";
    } else {
        $releaseId     = (int)($_POST['payroll_id']    ?? 0);
        $releasePeriod = trim($_POST['release_period'] ?? '');
        if ($releaseId && Model::releasePayroll($releaseId)) {
            $payRecord = Model::findPayrollById($releaseId);
            $empName   = $payRecord['employee_name'] ?? "ID:{$releaseId}";
            Model::log($_SESSION['user_id'], 'RELEASE_PAYROLL',
                "Released payroll ID:{$releaseId} for {$empName} period {$releasePeriod}");
            header("Location: payroll.php?period={$releasePeriod}&msg=released&name=" . urlencode($empName));
            exit;
        }
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Failed to release record.</div>";
    }
}

// ===========================================================================
//  POST: RELEASE ALL
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_all'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Invalid security token.</div>";
    } else {
        $releasePeriod = trim($_POST['release_period'] ?? '');
        if ($releasePeriod && Model::releaseAllPayrollForPeriod($releasePeriod)) {
            Model::log($_SESSION['user_id'], 'RELEASE_ALL_PAYROLL',
                "Released all payroll for period {$releasePeriod}");
            header("Location: payroll.php?period={$releasePeriod}&msg=released_all");
            exit;
        }
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Failed to release records.</div>";
    }
}

// ===========================================================================
//  POST: EDIT STATUS
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_status'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Invalid security token.</div>";
    } else {
        $editId     = (int)($_POST['payroll_id']   ?? 0);
        $newStatus  = trim($_POST['new_status']    ?? '');
        $noteText   = trim($_POST['status_note']   ?? '');
        $editPeriod = trim($_POST['edit_period']   ?? '');
        $allowed    = ['released', 'pending'];

        if ($editId && in_array($newStatus, $allowed, true)) {
            $payRecord = Model::findPayrollById($editId);
            $empName   = $payRecord['employee_name'] ?? "ID:{$editId}";
            $oldStatus = $payRecord['status']         ?? 'unknown';

            if (Model::updatePayrollStatus($editId, $newStatus)) {
                // Save note if status requires it or a note was provided
                if (!empty($noteText)) {
                    Model::addPayrollNote($editId, substr($noteText, 0, 100), $_SESSION['user_id']);
                }
                Model::log(
                    $_SESSION['user_id'],
                    'EDIT_PAYROLL_STATUS',
                    "Changed payroll ID:{$editId} ({$empName}) status from {$oldStatus} to {$newStatus} for period {$editPeriod}"
                    . (!empty($noteText) ? " | Note: " . substr($noteText, 0, 80) : '')
                );
                header("Location: payroll.php?period={$editPeriod}&msg=status_updated&name=" . urlencode($empName));
                exit;
            }
        }
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Failed to update status.</div>";
    }
}

// ===========================================================================
//  POST: DELETE PAYROLL RECORD
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_payroll'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Invalid security token.</div>";
    } else {
        $deleteId     = (int)($_POST['payroll_id']     ?? 0);
        $deletePeriod = trim($_POST['delete_period']   ?? '');
        if ($deleteId) {
            $payRecord = Model::findPayrollById($deleteId);
            $empName   = $payRecord['employee_name'] ?? "ID:{$deleteId}";
            if (Model::deletePayrollRecord($deleteId)) {
                Model::log($_SESSION['user_id'], 'DELETE_PAYROLL',
                    "Deleted payroll ID:{$deleteId} for {$empName} period {$deletePeriod}");
                header("Location: payroll.php?period={$deletePeriod}&msg=deleted&name=" . urlencode($empName));
                exit;
            }
        }
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Failed to delete record.</div>";
    }
}

// ===========================================================================
//  POST: ADD SALARY DEDUCTION
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_salary_deduction'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Invalid security token.</div>";
    } else {
        $dedPayrollId = (int)($_POST['payroll_id']   ?? 0);
        $dedPeriod    = trim($_POST['ded_period']    ?? '');
        $dedReason    = trim($_POST['ded_reason']    ?? '');
        $dedDesc      = trim($_POST['ded_desc']      ?? '');
        $dedAmount    = (float)($_POST['ded_amount'] ?? 0);
        $dedNotes     = trim($_POST['ded_notes']     ?? '');

        // Check payroll status — only Pending is allowed
        $payRecord = Model::findPayrollById($dedPayrollId);
        if (!$payRecord || $payRecord['status'] !== 'pending') {
            $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Salary deduction can only be added to Pending payroll records.</div>";
        } elseif (empty($dedReason)) {
            $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Please select a reason for the deduction.</div>";
        } elseif ($dedAmount <= 0) {
            $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Deduction amount must be greater than zero.</div>";
        } elseif (empty($dedNotes)) {
            $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Notes are required for salary deductions.</div>";
        } else {
            $added = Model::addSalaryDeduction($dedPayrollId, [
                'reason'      => $dedReason,
                'description' => $dedDesc,
                'amount'      => $dedAmount,
                'notes'       => $dedNotes,
            ], $_SESSION['user_id']);

            if ($added) {
                $empName = $payRecord['employee_name'] ?? "ID:{$dedPayrollId}";
                // Format: Reason - Amount - Notes (recorded as payroll note)
                $reasonLabel = ucwords(str_replace('_', ' ', $dedReason));
                $descPart    = !empty($dedDesc) ? " ({$dedDesc})" : '';
                $noteText    = "{$reasonLabel}{$descPart} — ₱" . number_format($dedAmount, 2) . " — {$dedNotes}";
                Model::addPayrollNote($dedPayrollId, substr($noteText, 0, 100), $_SESSION['user_id']);
                Model::log($_SESSION['user_id'], 'ADD_SALARY_DEDUCTION',
                    "Added salary deduction ₱" . number_format($dedAmount, 2) .
                    " ({$dedReason}) to payroll ID:{$dedPayrollId} ({$empName}) period {$dedPeriod}. Notes: {$dedNotes}");
                header("Location: payroll.php?period={$dedPeriod}&msg=deduction_added&name=" . urlencode($empName));
                exit;
            }
            $msg = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle mr-2'></i>Failed to add salary deduction.</div>";
        }
    }
}

// ===========================================================================
//  POST: UPDATE SALARY DEDUCTION
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_salary_deduction'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token.</div>";
    } else {
        $dedId     = (int)($_POST['edit_ded_id']     ?? 0);
        $dedPeriod = trim($_POST['edit_ded_period']  ?? '');
        $dedReason = trim($_POST['edit_ded_reason']  ?? '');
        $dedDesc   = trim($_POST['edit_ded_desc']    ?? '');
        $dedAmount = (float)($_POST['edit_ded_amount'] ?? 0);
        $dedNotes  = trim($_POST['edit_ded_notes']   ?? '');

        if ($dedId && $dedReason && $dedAmount > 0 && !empty($dedNotes)) {
            $updated = Model::updateSalaryDeduction($dedId, [
                'reason'      => $dedReason,
                'description' => $dedDesc,
                'amount'      => $dedAmount,
                'notes'       => $dedNotes,
            ]);
            if ($updated) {
                Model::log($_SESSION['user_id'], 'UPDATE_SALARY_DEDUCTION', "Updated salary deduction ID:{$dedId}");
                header("Location: payroll.php?period={$dedPeriod}&msg=deduction_added&name=Updated");
                exit;
            }
        }
        $msg = "<div class='alert alert-danger'>Failed to update deduction.</div>";
    }
}

// ===========================================================================
//  POST: DELETE SALARY DEDUCTION
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_salary_deduction'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token.</div>";
    } else {
        $dedId     = (int)($_POST['del_ded_id']    ?? 0);
        $dedPeriod = trim($_POST['del_ded_period'] ?? '');
        if ($dedId && Model::deleteSalaryDeduction($dedId)) {
            Model::log($_SESSION['user_id'], 'DELETE_SALARY_DEDUCTION', "Deleted salary deduction ID:{$dedId}");
            header("Location: payroll.php?period={$dedPeriod}&msg=deduction_added&name=Deleted");
            exit;
        }
        $msg = "<div class='alert alert-danger'>Failed to delete deduction.</div>";
    }
}

// ===========================================================================
//  POST: DELETE PAYROLL NOTE
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_payroll_note'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $msg = "<div class='alert alert-danger'>Invalid security token.</div>";
    } else {
        $noteId    = (int)($_POST['del_note_id']    ?? 0);
        $notePeriod = trim($_POST['del_note_period'] ?? '');
        if ($noteId && Model::deletePayrollNote($noteId)) {
            Model::log($_SESSION['user_id'], 'DELETE_PAYROLL_NOTE', "Deleted payroll note ID:{$noteId}");
            header("Location: payroll.php?period={$notePeriod}");
            exit;
        }
        $msg = "<div class='alert alert-danger'>Failed to delete note.</div>";
    }
}

require_once __DIR__ . '/../layouts/admin_header.php';

// Preserve any $msg set by POST handlers above — only init if not already set
if (!isset($msg)) { $msg = ''; }

// ===========================================================================
//  SETUP: Period selection and data
// ===========================================================================
$todayCutoff     = date('j') <= 15 ? '1' : '2';
$existingPeriods = Model::getPayrollPeriods();
$oldestPeriod    = Model::getOldestPayrollPeriod();

// Default selected period: from GET, or oldest existing record, or current cutoff
$defaultPeriod  = $oldestPeriod ?? (date('Y-m') . '-' . $todayCutoff);
$selectedPeriod = $_GET['period'] ?? $defaultPeriod;

// Build period options: merge last 6 months + all existing periods (oldest to newest range)
$periodOptions = [];
if ($oldestPeriod) {
    // Generate from oldest period up to 1 month in the future
    $cursor = $oldestPeriod;
    $limit  = date('Y-m', strtotime('+1 month')) . '-2';
    while ($cursor <= $limit) {
        $periodOptions[] = $cursor;
        // increment by half-month
        $parts = explode('-', $cursor);
        if ($parts[2] === '1') {
            $cursor = $parts[0] . '-' . $parts[1] . '-2';
        } else {
            $next = date('Y-m', strtotime($parts[0] . '-' . $parts[1] . '-01 +1 month'));
            $cursor = $next . '-1';
        }
    }
} else {
    for ($i = 5; $i >= 0; $i--) {
        $base = date('Y-m', strtotime("-{$i} months"));
        $periodOptions[] = $base . '-1';
        $periodOptions[] = $base . '-2';
    }
}
$periodOptions = array_unique(array_merge($periodOptions, $existingPeriods));
usort($periodOptions, fn($a, $b) => strcmp($b, $a));

// Flash messages
if (!$msg) {
    $msgParam = $_GET['msg'] ?? '';
    if ($msgParam === 'generated') {
        $count    = (int)($_GET['count']   ?? 0);
        $skipped  = (int)($_GET['skipped'] ?? 0);
        $skipNote = '';
        if ($skipped > 0) {
            $reasons = array_filter(explode('|', urldecode($_GET['skipreasons'] ?? '')));
            $reasonHtml = '';
            if (!empty($reasons)) {
                $reasonHtml = '<ul class="mb-0 mt-1 payroll-skip-list">';
                foreach ($reasons as $r) { $reasonHtml .= '<li>' . htmlspecialchars($r) . '</li>'; }
                $reasonHtml .= '</ul>';
            }
            $skipNote = " <strong>{$skipped} skipped</strong>{$reasonHtml}";
        }
        $periodLabel = Model::periodLabel($selectedPeriod);
        $autoDismiss = $skipped === 0 ? 'alert-auto-dismiss' : '';
        $msg = "<div class='alert alert-success {$autoDismiss}'><i class='fas fa-check-circle mr-2'></i><strong>{$count}</strong> payroll record(s) created for <strong>{$periodLabel}</strong>.{$skipNote}</div>";
    } elseif ($msgParam === 'released') {
        $name = htmlspecialchars($_GET['name'] ?? 'Employee');
        $msg  = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>Payroll released for <strong>{$name}</strong>.</div>";
    } elseif ($msgParam === 'released_all') {
        $msg = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>All pending payroll records released.</div>";
    } elseif ($msgParam === 'status_updated') {
        $name = htmlspecialchars($_GET['name'] ?? 'Employee');
        $msg  = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>Payroll status updated for <strong>{$name}</strong>.</div>";
    } elseif ($msgParam === 'deleted') {
        $name = htmlspecialchars($_GET['name'] ?? 'Employee');
        $msg  = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>Payroll record deleted for <strong>{$name}</strong>.</div>";
    } elseif ($msgParam === 'deduction_added') {
        $name = htmlspecialchars($_GET['name'] ?? 'Employee');
        $msg  = "<div class='alert alert-success alert-auto-dismiss'><i class='fas fa-check-circle mr-2'></i>Salary deduction added for <strong>{$name}</strong>.</div>";
    }
}

$filterDept       = $_GET['dept'] ?? '';
$allActiveEmps    = Model::getAllEmployees('active');
$employees        = $filterDept !== ''
    ? array_values(array_filter($allActiveEmps, fn($e) => (int)$e['department_id'] === (int)$filterDept))
    : $allActiveEmps;
$allDepartments   = Model::getAllDepartments();
$periodPayroll    = Model::getPayrollByPeriod($selectedPeriod);
// Apply dept filter to periodPayroll if needed
if ($filterDept !== '') {
    $empDeptCache = [];
    $periodPayroll = array_values(array_filter($periodPayroll, function($p) use ($filterDept, &$empDeptCache) {
        if (!isset($empDeptCache[$p['employee_id']])) {
            $e = Model::findEmployeeById((int)$p['employee_id']);
            $empDeptCache[$p['employee_id']] = (int)($e['department_id'] ?? 0);
        }
        return $empDeptCache[$p['employee_id']] === (int)$filterDept;
    }));
}
$totalGross       = array_sum(array_column($periodPayroll, 'gross_pay'));
$totalDed         = array_sum(array_column($periodPayroll, 'total_deductions'));
$totalNet         = array_sum(array_column($periodPayroll, 'net_pay'));
$pendingList      = array_filter($periodPayroll, fn($p) => $p['status'] === 'pending');
$releasedList     = array_filter($periodPayroll, fn($p) => $p['status'] === 'released');
$alreadyGenerated = Model::periodExists($selectedPeriod);

// IDs of employees already generated for this period (pending or released)
$generatedEmpIds = array_column($periodPayroll, 'employee_id');

// Compute the cutoff date range for the selected period (used to filter employees)
$selCutoffNum = Model::periodCutoff($selectedPeriod);
$selYearMonth = Model::periodBase($selectedPeriod);
[$selYear, $selMonth] = explode('-', $selYearMonth);
$selLastDay   = date('t', mktime(0, 0, 0, (int)$selMonth, 1, (int)$selYear));
$selCutoffFrom = $selCutoffNum === 1 ? "{$selYearMonth}-01" : "{$selYearMonth}-16";
$selCutoffTo   = $selCutoffNum === 1 ? "{$selYearMonth}-15" : "{$selYearMonth}-{$selLastDay}";

// Employees NOT yet generated AND whose date_start is on or before the cutoff end date.
// Employees whose date_start is AFTER the cutoff end are excluded — they don't work in this period.
$ungeneratedEmployees = array_filter($employees, function($e) use ($generatedEmpIds, $selCutoffTo) {
    // Skip already-generated
    if (in_array($e['id'], $generatedEmpIds)) return false;
    // Use date_start if set, otherwise date_hired
    $startDate = !empty($e['date_start']) ? $e['date_start'] : ($e['date_hired'] ?? '');
    // If employee hasn't started yet by the end of the cutoff, exclude them
    if ($startDate && $startDate > $selCutoffTo) return false;
    return true;
});
$ungeneratedEmployees = array_values($ungeneratedEmployees);

// Show Generate button only if there are employees eligible for this cutoff not yet generated
$showGenerateBtn = count($ungeneratedEmployees) > 0;

// Pre-fetch notes for all payroll records in this period
$payrollNotes = [];
foreach ($periodPayroll as $p) {
    $payrollNotes[$p['id']] = Model::getPayrollNotes((int)$p['id']);
}
?>

<?= $msg ?>

<!-- ── PRINT TITLE (hidden on screen, visible when printing) ── -->
<div class="print-title">
  <?= htmlspecialchars(COMPANY_NAME) ?> — Payroll Register: <?= Model::periodLabel($selectedPeriod) ?>
</div>

<!-- ── Period Selector ─────────────────────────────────────── -->
<div class="card card-primary card-outline mb-3 no-print">
  <div class="card-body py-3">
    <div class="d-flex align-items-center flex-wrap payroll-period-bar">
      <label class="mb-0 font-weight-bold mr-2 text-nowrap">Payroll Period:</label>
      <select id="periodSelect" class="form-control mr-3 payroll-period-select-md"
              onchange="window.location='payroll.php?period='+this.value">
        <?php foreach ($periodOptions as $p): ?>
          <option value="<?= $p ?>" <?= $p === $selectedPeriod ? 'selected' : '' ?>>
            <?= Model::periodLabel($p) ?><?= in_array($p, $existingPeriods) ? ' ✓' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>

      <?php if ($showGenerateBtn): ?>
        <button class="btn btn-success mr-2" data-toggle="modal" data-target="#generateModal">
          <i class="fas fa-cogs mr-1"></i><span class="d-none d-sm-inline">Generate Payroll</span><span class="d-inline d-sm-none">Generate</span>
        </button>
      <?php endif; ?>
      <?php if ($alreadyGenerated): ?>
        <span class="badge badge-primary px-3 py-2 mr-2 payroll-summary-badge">
          <i class="fas fa-check mr-1"></i> Payroll Generated
        </span>
        <?php if (count($pendingList) > 0): ?>
          <button class="btn btn-warning mr-2" data-toggle="modal" data-target="#releaseAllModal">
            <i class="fas fa-paper-plane mr-1"></i><span class="d-none d-sm-inline">Release All</span><span class="d-inline d-sm-none">Release</span>
          </button>
        <?php endif; ?>
      <?php endif; ?>

      <div class="ml-3 d-flex align-items-center">
        <label class="mb-0 font-weight-bold mr-2 text-nowrap">Department:</label>
        <select class="form-control payroll-period-select-md"
                onchange="window.location='payroll.php?period=<?= urlencode($selectedPeriod) ?>&dept='+this.value">
          <option value="">All Departments</option>
          <?php foreach ($allDepartments as $dept): ?>
            <option value="<?= $dept['id'] ?>" <?= (string)$filterDept === (string)$dept['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($dept['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-info ml-auto" onclick="window.print()">
        <i class="fas fa-print mr-1"></i><span class="d-none d-sm-inline">Print</span>
      </button>
    </div>
  </div>
</div>

<!-- ── Summary Cards ───────────────────────────────────────── -->
<div class="row payroll-info-row">
  <div class="col-6 col-md-3">
    <div class="info-box">
      <span class="info-box-icon bg-primary"><i class="fas fa-file-invoice"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Total Records</span>
        <span class="info-box-number"><?= count($periodPayroll) ?></span>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="info-box">
      <span class="info-box-icon bg-warning"><i class="fas fa-money-bill-alt"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Total Gross Pay</span>
        <span class="info-box-number">&#8369;<?= number_format($totalGross, 0) ?></span>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="info-box">
      <span class="info-box-icon bg-danger"><i class="fas fa-minus-circle"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Total Deductions</span>
        <span class="info-box-number">&#8369;<?= number_format($totalDed, 0) ?></span>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="info-box">
      <span class="info-box-icon bg-success"><i class="fas fa-hand-holding-usd"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Total Net Pay</span>
        <span class="info-box-number">&#8369;<?= number_format($totalNet, 0) ?></span>
      </div>
    </div>
  </div>
</div>

<!-- ── Payroll Table ───────────────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fas fa-table mr-2"></i>
      Payroll Records &mdash; <?= Model::periodLabel($selectedPeriod) ?>
    </h3>
    <div class="card-tools d-flex align-items-center payroll-card-tools">
      <span class="badge badge-warning mr-2"><?= count($pendingList) ?> Pending</span>
      <span class="badge badge-success mr-2"><?= count($releasedList) ?> Released</span>
      <?php if (!empty($periodPayroll)): ?>
      <a href="payroll_export.php?period=<?= urlencode($selectedPeriod) ?>&format=pdf"
         target="_blank"
         class="btn btn-xs btn-outline-danger mr-1 no-print"
         title="Export PDF">
        <i class="fas fa-file-pdf mr-1"></i><span class="d-none d-md-inline">PDF</span>
      </a>
      <a href="payroll_export.php?period=<?= urlencode($selectedPeriod) ?>&format=excel"
         class="btn btn-xs btn-outline-success no-print"
         title="Export Excel">
        <i class="fas fa-file-excel mr-1"></i><span class="d-none d-md-inline">Excel</span>
      </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card-body p-0">
    <?php if (empty($periodPayroll)): ?>
      <div class="p-5 text-center text-muted">
        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
        No payroll records for <strong><?= Model::periodLabel($selectedPeriod) ?></strong>.<br>
        <small>Click <strong>"Generate Payroll"</strong> above to begin.</small>
      </div>
    <?php else: ?>
    <!-- payroll-table-wrap enables horizontal scroll on mobile -->
    <div class="payroll-table-wrap">
      <table class="table table-hover mb-0 payroll-table">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Department</th>
            <th>Basic Salary</th>
            <th class="payroll-col-allowance">Allowance</th>
            <th>Gross Pay</th>
            <th class="payroll-col-sss">SSS</th>
            <th class="payroll-col-philhealth">PhilHealth</th>
            <th class="payroll-col-pagibig">Pag-IBIG</th>
            <th class="payroll-col-wtax">W. Tax</th>
            <th class="payroll-col-absentded">Absent / Unpaid</th>
            <th>Total Ded.</th>
            <th class="text-success">Net Pay</th>
            <th>Status</th>
            <th class="text-center no-print">Notes</th>
            <th class="text-center no-print">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($periodPayroll as $p): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($p['employee_name']) ?></strong><br>
              <small class="text-muted"><?= htmlspecialchars($p['employee_no']) ?></small>
            </td>
            <td><?= htmlspecialchars($p['department']) ?></td>
            <td>&#8369;<?= number_format($p['basic_salary'], 2) ?></td>
            <td class="payroll-col-allowance">&#8369;<?= number_format($p['allowance'], 2) ?></td>
            <td>&#8369;<?= number_format($p['gross_pay'], 2) ?></td>
            <td class="text-danger payroll-col-sss">&#8369;<?= number_format($p['sss_ee'], 2) ?></td>
            <td class="text-danger payroll-col-philhealth">&#8369;<?= number_format($p['philhealth_ee'], 2) ?></td>
            <td class="text-danger payroll-col-pagibig">&#8369;<?= number_format($p['pagibig_ee'], 2) ?></td>
            <td class="text-danger payroll-col-wtax">&#8369;<?= number_format($p['withholding_tax'], 2) ?></td>
            <td class="text-danger payroll-col-absentded">&#8369;<?= number_format(($p['absent_deduction'] ?? 0) + ($p['unpaid_leave_deduction'] ?? 0), 2) ?></td>
            <td class="text-danger font-weight-bold">&#8369;<?= number_format($p['total_deductions'], 2) ?></td>
            <td class="text-success font-weight-bold">&#8369;<?= number_format($p['net_pay'], 2) ?></td>
            <td>
              <?php
              $badgeMap = [
                  'released' => 'badge-success',
                  'pending'  => 'badge-warning',
              ];
              $badgeCls = $badgeMap[$p['status']] ?? 'badge-warning';
              ?>
              <span class="badge <?= $badgeCls ?>"><?= ucfirst($p['status']) ?></span>
            </td>
            <td class="text-center payroll-notes-cell no-print">
              <?php
                $rowNotes = $payrollNotes[$p['id']] ?? [];
                $rowDeds  = Model::getSalaryDeductions((int)$p['id']);
              ?>
              <button type="button"
                      class="btn btn-sm btn-outline-secondary payroll-notes-btn"
                      title="View Notes"
                      data-payroll-id="<?= $p['id'] ?>"
                      data-employee="<?= htmlspecialchars($p['employee_name']) ?>"
                      data-notes="<?= htmlspecialchars(json_encode($rowNotes)) ?>"
                      data-deductions="<?= htmlspecialchars(json_encode($rowDeds)) ?>"
                      data-period="<?= htmlspecialchars($selectedPeriod) ?>">
                <i class="fas fa-book"></i>
                <?php if (count($rowNotes) > 0 || count($rowDeds) > 0): ?>
                  <span class="badge badge-info ml-1"><?= count($rowNotes) ?></span>
                <?php endif; ?>
              </button>
            </td>
            <td class="text-center payroll-actions-cell no-print">
              <div class="dropdown">
                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                  <i class="fas fa-ellipsis-v"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                  <a class="dropdown-item" href="payslip.php?emp=<?= $p['employee_id'] ?>&period=<?= $selectedPeriod ?>">
                    <i class="fas fa-receipt mr-2 text-info"></i>View Payslip
                  </a>
                  <div class="dropdown-divider"></div>
                  <?php if (in_array($p['status'], ['released', 'pending'])): ?>
                  <a class="dropdown-item payroll-edit-status-btn" href="#"
                     data-payroll-id="<?= $p['id'] ?>"
                     data-current-status="<?= $p['status'] ?>"
                     data-employee="<?= htmlspecialchars($p['employee_name']) ?>">
                    <i class="fas fa-edit mr-2 text-warning"></i>Edit
                  </a>
                  <?php endif; ?>
                  <?php if ($p['status'] === 'pending'): ?>
                  <a class="dropdown-item payroll-add-deduction-btn" href="#"
                     data-payroll-id="<?= $p['id'] ?>"
                     data-employee="<?= htmlspecialchars($p['employee_name']) ?>"
                     data-period="<?= htmlspecialchars($selectedPeriod) ?>">
                    <i class="fas fa-minus-circle mr-2 text-danger"></i>Add Salary Deduction
                  </a>
                  <a class="dropdown-item payroll-release-btn" href="#"
                     data-payroll-id="<?= $p['id'] ?>"
                     data-employee="<?= htmlspecialchars($p['employee_name']) ?>"
                     data-period="<?= htmlspecialchars($selectedPeriod) ?>">
                    <i class="fas fa-paper-plane mr-2 text-success"></i>Release
                  </a>
                  <?php endif; ?>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item payroll-delete-btn" href="#"
                     data-payroll-id="<?= $p['id'] ?>"
                     data-employee="<?= htmlspecialchars($p['employee_name']) ?>"
                     data-period="<?= htmlspecialchars($selectedPeriod) ?>">
                    <i class="fas fa-trash mr-2 text-danger"></i>Delete
                  </a>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-light">
          <tr>
            <th colspan="2" class="text-right">TOTALS</th>
            <th colspan="2">&#8369;<?= number_format($totalGross, 2) ?></th>
            <th colspan="6"></th>
            <th class="text-danger">&#8369;<?= number_format($totalDed, 2) ?></th>
            <th class="text-success">&#8369;<?= number_format($totalNet, 2) ?></th>
            <th colspan="3" class="no-print"></th>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── EDIT STATUS MODAL ────────────────────────────────────── -->
<div class="modal fade no-print" id="editStatusModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Payroll Status</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form method="POST" id="editStatusForm">
        <input type="hidden" name="edit_status"  value="1">
        <input type="hidden" name="payroll_id"   id="editStatusPayrollId">
        <input type="hidden" name="edit_period"  value="<?= htmlspecialchars($selectedPeriod) ?>">
        <input type="hidden" name="csrf_token"   value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="modal-body">
          <p class="text-muted mb-3 payroll-edit-emp-label">
            <i class="fas fa-user mr-1"></i><strong id="editStatusEmpName"></strong>
          </p>
          <div class="form-group">
            <label class="font-weight-bold">New Status <span class="text-danger">*</span></label>
            <select name="new_status" id="editStatusSelect" class="form-control" required>
              <option value="released">Released</option>
              <option value="pending">Pending</option>
            </select>
          </div>
          <div class="form-group payroll-note-hidden" id="editStatusNoteGroup">
            <label class="font-weight-bold">Notes <span class="text-danger">*</span></label>
            <textarea name="status_note" id="editStatusNote" class="form-control" rows="3"
                      maxlength="100" placeholder="Enter reason or notes (max 100 characters)..."></textarea>
            <small class="text-muted"><span id="editStatusNoteCount">0</span>/100 characters</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i>Cancel
          </button>
          <button type="submit" class="btn btn-warning" id="editStatusSaveBtn">
            <i class="fas fa-save mr-1"></i>Update Status
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── NOTES VIEW MODAL ─────────────────────────────────────── -->
<div class="modal fade no-print" id="payrollNotesModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title"><i class="fas fa-book mr-2"></i>Payroll Notes — <span id="notesModalEmpName"></span></h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div id="notesModalContent"></div>
        <!-- Hidden salary deduction items for edit referencing -->
        <div id="notesModalDedItems" style="display:none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ── EDIT SALARY DEDUCTION MODAL (launched from notes list) ── -->
<div class="modal fade no-print" id="editDeductionModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Salary Deduction</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form method="POST" id="editDeductionForm">
        <input type="hidden" name="update_salary_deduction" value="1">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="edit_ded_id" id="editDedId">
        <input type="hidden" name="edit_ded_period" value="<?= htmlspecialchars($selectedPeriod) ?>">
        <div class="modal-body">
          <div class="form-row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold">Reason <span class="text-danger">*</span></label>
                <select name="edit_ded_reason" id="editDedReason" class="form-control" required onchange="updateEditDedDescription(this.value)">
                  <option value="">— Select Reason —</option>
                  <option value="destroyed_asset">Destroyed Company Asset</option>
                  <option value="lost_asset">Lost Company Asset</option>
                  <option value="cash_advance">Cash Advance</option>
                  <option value="loan">Company Loan</option>
                  <option value="overpayment">Salary Overpayment</option>
                  <option value="damage">Property Damage</option>
                  <option value="other">Other</option>
                </select>
              </div>
            </div>
            <div class="col-md-6" id="editDedDescGroup">
              <div class="form-group">
                <label class="font-weight-bold">Asset / Item</label>
                <select name="edit_ded_desc" id="editDedDescSelect" class="form-control">
                  <option value="">— Select Asset —</option>
                  <option value="Laptop">Laptop</option>
                  <option value="Mobile Phone">Mobile Phone</option>
                  <option value="Monitor">Monitor</option>
                  <option value="Keyboard / Mouse">Keyboard / Mouse</option>
                  <option value="Office Chair">Office Chair</option>
                  <option value="ID / Access Card">ID / Access Card</option>
                  <option value="Uniform">Uniform</option>
                  <option value="Tools / Equipment">Tools / Equipment</option>
                  <option value="Vehicle">Vehicle</option>
                  <option value="Other Asset">Other Asset</option>
                </select>
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="col-md-4">
              <div class="form-group">
                <label class="font-weight-bold">Amount (₱) <span class="text-danger">*</span></label>
                <div class="input-group">
                  <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                  <input type="number" name="edit_ded_amount" id="editDedAmount" class="form-control" step="0.01" min="0.01" required>
                </div>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Notes <span class="text-danger">*</span></label>
            <textarea name="edit_ded_notes" id="editDedNotes" class="form-control" rows="3" required maxlength="500"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancel</button>
          <button type="submit" class="btn btn-warning"><i class="fas fa-save mr-1"></i>Update Deduction</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── DELETE PAYROLL NOTE FORM (hidden) ── -->
<form method="POST" id="deleteNoteForm" style="display:none;">
  <input type="hidden" name="delete_payroll_note" value="1">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
  <input type="hidden" name="del_note_id" id="delNoteId">
  <input type="hidden" name="del_note_period" value="<?= htmlspecialchars($selectedPeriod) ?>">
</form>

<!-- ── DELETE DEDUCTION FORM (hidden) ── -->
<form method="POST" id="deleteDedForm" style="display:none;">
  <input type="hidden" name="delete_salary_deduction" value="1">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
  <input type="hidden" name="del_ded_id" id="delDedId">
  <input type="hidden" name="del_ded_period" value="<?= htmlspecialchars($selectedPeriod) ?>">
</form>

<!-- ── DELETE PAYROLL MODAL ──────────────────────────────────── -->
<div class="modal fade no-print" id="deletePayrollModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger">
        <h5 class="modal-title text-white"><i class="fas fa-trash mr-2"></i>Delete Payroll Record</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form method="POST" id="deletePayrollForm">
        <input type="hidden" name="delete_payroll" value="1">
        <input type="hidden" name="payroll_id"     id="deletePayrollId">
        <input type="hidden" name="delete_period"  id="deletePayrollPeriod">
        <input type="hidden" name="csrf_token"     value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="modal-body">
          <div class="d-flex align-items-start">
            <i class="fas fa-exclamation-triangle fa-2x text-danger mr-3 mt-1"></i>
            <div>
              <p class="mb-1 font-weight-bold">Are you sure you want to delete this payroll record?</p>
              <p class="text-muted mb-1 payroll-delete-emp-label"><i class="fas fa-user mr-1"></i><strong id="deletePayrollEmpName"></strong></p>
              <p class="text-danger mb-0"><small>This action cannot be undone. The record will be permanently removed.</small></p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i>Cancel
          </button>
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-trash mr-1"></i>Delete Record
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── RELEASE SINGLE MODAL ───────────────────────────────────── -->
<div class="modal fade no-print" id="releasePayrollModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success">
        <h5 class="modal-title text-white"><i class="fas fa-paper-plane mr-2"></i>Release Payroll</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form method="POST" id="releasePayrollForm">
        <input type="hidden" name="release_single"  value="1">
        <input type="hidden" name="payroll_id"      id="releasePayrollId">
        <input type="hidden" name="release_period"  id="releasePayrollPeriod">
        <input type="hidden" name="csrf_token"      value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="modal-body">
          <div class="d-flex align-items-start">
            <i class="fas fa-paper-plane fa-2x text-success mr-3 mt-1"></i>
            <div>
              <p class="mb-1 font-weight-bold">Release payroll for this employee?</p>
              <p class="text-muted mb-1"><i class="fas fa-user mr-1"></i><strong id="releasePayrollEmpName"></strong></p>
              <p class="text-muted mb-0"><small>Once released, the employee can view their payslip. This cannot be undone.</small></p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i>Cancel
          </button>
          <button type="submit" class="btn btn-success">
            <i class="fas fa-paper-plane mr-1"></i>Release
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── GENERATE CONFIRM MODAL ────────────────────────────────── -->
<div class="modal fade no-print" id="generateConfirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success">
        <h5 class="modal-title text-white"><i class="fas fa-cogs mr-2"></i>Confirm Payroll Generation</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-start">
          <i class="fas fa-info-circle fa-2x text-success mr-3 mt-1"></i>
          <div>
            <p class="mb-1 font-weight-bold" id="genConfirmMsg">Generate payroll?</p>
            <p class="text-muted mb-0"><small>Deductions will be computed from attendance records. This cannot be undone.</small></p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          <i class="fas fa-times mr-1"></i>Cancel
        </button>
        <button type="button" class="btn btn-success" id="genConfirmOkBtn">
          <i class="fas fa-play mr-1"></i>Confirm &amp; Generate
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── SALARY DEDUCTION MODAL ────────────────────────────── -->
<div class="modal fade no-print" id="salaryDeductionModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="fas fa-minus-circle mr-2"></i>Add Salary Deduction</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form method="POST" id="salaryDeductionForm">
        <input type="hidden" name="add_salary_deduction" value="1">
        <input type="hidden" name="payroll_id"  id="dedPayrollId">
        <input type="hidden" name="ded_period"  value="<?= htmlspecialchars($selectedPeriod) ?>">
        <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="modal-body">
          <p class="text-muted mb-3">
            <i class="fas fa-user mr-1"></i>Employee: <strong id="dedEmpName"></strong>
          </p>
          <div class="alert alert-warning mb-3 py-2">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Deductions can only be added to <strong>Pending</strong> payroll records.
          </div>
          <div class="form-row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold">Reason <span class="text-danger">*</span></label>
                <select name="ded_reason" id="dedReason" class="form-control" required onchange="updateDedDescription(this.value)">
                  <option value="">— Select Reason —</option>
                  <option value="destroyed_asset">Destroyed Company Asset</option>
                  <option value="lost_asset">Lost Company Asset</option>
                  <option value="cash_advance">Cash Advance</option>
                  <option value="loan">Company Loan</option>
                  <option value="overpayment">Salary Overpayment</option>
                  <option value="damage">Property Damage</option>
                  <option value="other">Other</option>
                </select>
              </div>
            </div>
            <div class="col-md-6" id="dedDescGroup" style="display:none;">
              <div class="form-group">
                <label class="font-weight-bold">Asset / Item</label>
                <select name="ded_desc" id="dedDescSelect" class="form-control">
                  <option value="">— Select Asset —</option>
                  <option value="Laptop">Laptop</option>
                  <option value="Mobile Phone">Mobile Phone</option>
                  <option value="Monitor">Monitor</option>
                  <option value="Keyboard / Mouse">Keyboard / Mouse</option>
                  <option value="Office Chair">Office Chair</option>
                  <option value="ID / Access Card">ID / Access Card</option>
                  <option value="Uniform">Uniform</option>
                  <option value="Tools / Equipment">Tools / Equipment</option>
                  <option value="Vehicle">Vehicle</option>
                  <option value="Other Asset">Other Asset</option>
                </select>
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="col-md-4">
              <div class="form-group">
                <label class="font-weight-bold">Amount (₱) <span class="text-danger">*</span></label>
                <div class="input-group">
                  <div class="input-group-prepend"><span class="input-group-text">₱</span></div>
                  <input type="number" name="ded_amount" id="dedAmount" class="form-control"
                         step="0.01" min="0.01" placeholder="0.00" required>
                </div>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label class="font-weight-bold">Notes <span class="text-danger">*</span></label>
            <textarea name="ded_notes" id="dedNotes" class="form-control" rows="3" required
                      maxlength="500"
                      placeholder="Required: describe the incident, authorization, or reference number..."></textarea>
            <small class="text-muted">Notes are mandatory and recorded in the audit trail.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i>Cancel
          </button>
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-minus-circle mr-1"></i>Apply Deduction
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── GENERATE PAYROLL MODAL ──────────────────────────────── -->
<div class="modal fade no-print" id="generateModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fas fa-cogs mr-2"></i>Generate Payroll</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form method="POST" onsubmit="return confirmGenerate()">
        <input type="hidden" name="generate_payroll" value="1">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="modal-body">
          <div class="alert alert-info mb-3">
            <i class="fas fa-info-circle mr-1"></i>
            Review employees below. Deductions are computed from current salary data.
            Absent deductions are applied automatically from attendance records.
          </div>
          <div class="form-group row align-items-center mb-3">
            <label class="col-sm-3 col-form-label font-weight-bold">Payroll Period</label>
            <div class="col-sm-9">
              <div class="input-group">
                <input type="month" id="genPeriodMonth"
                       class="form-control"
                       value="<?= Model::periodBase($selectedPeriod) ?>"
                       min="2020-01"
                       max="<?= date('Y-m', strtotime('+1 month')) ?>"
                       required>
                <select id="genPeriodCutoff" class="form-control payroll-cutoff-select">
                  <option value="1" <?= Model::periodCutoff($selectedPeriod) === 1 ? 'selected' : '' ?>>1st Cutoff (1–15)</option>
                  <option value="2" <?= Model::periodCutoff($selectedPeriod) === 2 ? 'selected' : '' ?>>2nd Cutoff (16–End)</option>
                </select>
              </div>
              <input type="hidden" name="gen_period" id="genPeriodFinal" value="<?= htmlspecialchars($selectedPeriod) ?>">
              <small class="text-muted mt-1 d-block">13th month pay is automatically included in the <strong>December 2nd cutoff</strong>.</small>
            </div>
          </div>
          <div class="table-responsive payroll-scroll-table">
            <table class="table table-sm table-bordered mb-0">
              <thead class="thead-light">
                <tr>
                  <th class="payroll-cb-col">
                    <input type="checkbox" id="checkAll" checked title="Select/deselect all">
                  </th>
                  <th>Employee</th>
                  <th>Department</th>
                  <th>Basic Salary</th>
                  <th>Gross Pay</th>
                  <th class="text-danger">Deductions</th>
                  <th class="text-success">Est. Net Pay</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($ungeneratedEmployees as $e):
                $c = Model::computePayroll($e);
              ?>
                <tr>
                  <td class="text-center">
                    <input type="checkbox" name="employee_ids[]"
                           value="<?= $e['id'] ?>" class="emp-check" checked>
                  </td>
                  <td>
                    <strong><?= htmlspecialchars($e['name']) ?></strong><br>
                    <small class="text-muted"><?= $e['employee_no'] ?></small>
                  </td>
                  <td><?= htmlspecialchars($e['department'] ?? '-') ?></td>
                  <td>&#8369;<?= number_format($e['basic_salary'], 2) ?></td>
                  <td>&#8369;<?= number_format($c['gross_pay'], 2) ?></td>
                  <td class="text-danger">&#8369;<?= number_format($c['total_deductions'], 2) ?></td>
                  <td class="text-success font-weight-bold">&#8369;<?= number_format($c['net_pay'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($ungeneratedEmployees)): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">
                  <i class="fas fa-check-circle text-success mr-1"></i>
                  All active employees already have payroll for this period.
                </td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i> Cancel
          </button>
          <button type="submit" class="btn btn-success" id="generateBtn">
            <i class="fas fa-play mr-1"></i> Confirm &amp; Generate
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── RELEASE ALL MODAL ────────────────────────────────────── -->
<div class="modal fade no-print" id="releaseAllModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="fas fa-paper-plane mr-2"></i>Release All Payroll</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form method="POST">
        <input type="hidden" name="release_all"    value="1">
        <input type="hidden" name="release_period" value="<?= $selectedPeriod ?>">
        <input type="hidden" name="csrf_token"     value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="modal-body">
          <p>You are about to release <strong><?= count($pendingList) ?> pending</strong> payroll record(s) for
             <strong><?= Model::periodLabel($selectedPeriod) ?></strong>.</p>
          <p class="text-muted mb-0">Once released, employees can view their payslips. This cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning">
            <i class="fas fa-paper-plane mr-1"></i> Release All
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$existingPeriodsJson = json_encode($existingPeriods);
$maxPeriod = date('Y-m', strtotime('+1 month'));

$extraJs = <<<JSEOF
document.getElementById('checkAll').addEventListener('change', function () {
    document.querySelectorAll('.emp-check').forEach(function(cb) { cb.checked = this.checked; }, this);
});
document.querySelectorAll('.emp-check').forEach(function(cb) {
    cb.addEventListener('change', function () {
        var all     = document.querySelectorAll('.emp-check').length;
        var checked = document.querySelectorAll('.emp-check:checked').length;
        document.getElementById('checkAll').checked = (all === checked);
    });
});

function buildPeriod() {
    var month  = document.getElementById('genPeriodMonth').value;
    var cutoff = document.getElementById('genPeriodCutoff').value;
    if (!month) return '';
    return month + '-' + cutoff;
}
function syncPeriod() {
    var period = buildPeriod();
    document.getElementById('genPeriodFinal').value = period;
    var btn = document.getElementById('generateBtn');
    // Never disable based on period existing — partial generation is supported.
    // The server filters out already-generated employees per cutoff.
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-play mr-1"></i> Confirm & Generate';
}
document.getElementById('genPeriodMonth').addEventListener('change', syncPeriod);
document.getElementById('genPeriodCutoff').addEventListener('change', syncPeriod);
syncPeriod();

function confirmGenerate() {
    var period  = buildPeriod();
    var checked = document.querySelectorAll('.emp-check:checked').length;
    if (!period) { alert('Please select a payroll period.'); return false; }
    if (checked === 0) { alert('Please select at least one employee.'); return false; }
    var cutoffLabel = document.getElementById('genPeriodCutoff').selectedOptions[0].text;
    // Show AdminLTE confirm modal instead of native confirm()
    var msg = 'Generate payroll for <strong>' + checked + '</strong> employee(s)?<br>'
            + '<small class="text-muted">Period: ' + period + ' (' + cutoffLabel + ')</small>';
    document.getElementById('genConfirmMsg').innerHTML = msg;
    // Store the form reference and show the modal
    window._pendingGenerateForm = document.querySelector('#generateModal form');
    $('#generateModal').modal('hide');
    setTimeout(function() { $('#generateConfirmModal').modal('show'); }, 300);
    return false; // Prevent native submit — modal handles it
}

document.getElementById('genConfirmOkBtn') && document.getElementById('genConfirmOkBtn').addEventListener('click', function() {
    $('#generateConfirmModal').modal('hide');
    if (window._pendingGenerateForm) {
        // Bypass the onsubmit that calls confirmGenerate (would loop)
        window._pendingGenerateForm.onsubmit = null;
        window._pendingGenerateForm.submit();
    }
});

// ── Delete Payroll Modal ────────────────────────────────────────
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.payroll-delete-btn');
    if (!btn) return;
    e.preventDefault();
    document.getElementById('deletePayrollId').value      = btn.dataset.payrollId;
    document.getElementById('deletePayrollPeriod').value  = btn.dataset.period;
    document.getElementById('deletePayrollEmpName').textContent = btn.dataset.employee;
    $('#deletePayrollModal').modal('show');
});

// ── Release Single Modal ────────────────────────────────────────
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.payroll-release-btn');
    if (!btn) return;
    e.preventDefault();
    document.getElementById('releasePayrollId').value      = btn.dataset.payrollId;
    document.getElementById('releasePayrollPeriod').value  = btn.dataset.period;
    document.getElementById('releasePayrollEmpName').textContent = btn.dataset.employee;
    $('#releasePayrollModal').modal('show');
});

// ── Salary Deduction Modal ──────────────────────────────────────
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.payroll-add-deduction-btn');
    if (!btn) return;
    e.preventDefault();
    document.getElementById('dedPayrollId').value    = btn.dataset.payrollId;
    document.getElementById('dedEmpName').textContent = btn.dataset.employee;
    document.getElementById('dedReason').value       = '';
    document.getElementById('dedAmount').value       = '';
    document.getElementById('dedNotes').value        = '';
    document.getElementById('dedDescGroup').style.display = 'none';
    $('#salaryDeductionModal').modal('show');
});

function updateDedDescription(reason) {
    var grp  = document.getElementById('dedDescGroup');
    var assetReasons = ['destroyed_asset', 'lost_asset', 'damage'];
    if (assetReasons.indexOf(reason) !== -1) {
        grp.style.display = 'block';
    } else {
        grp.style.display = 'none';
        document.getElementById('dedDescSelect').value = '';
    }
}

// ── Edit Status (now on <a> not button) ────────────────────────
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.payroll-edit-status-btn');
    if (!btn) return;
    e.preventDefault();
    var payrollId     = btn.dataset.payrollId;
    var currentStatus = btn.dataset.currentStatus;
    var empName       = btn.dataset.employee;

    document.getElementById('editStatusPayrollId').value = payrollId;
    document.getElementById('editStatusEmpName').textContent  = empName;
    document.getElementById('editStatusSelect').value    = currentStatus;
    document.getElementById('editStatusNote').value      = '';
    document.getElementById('editStatusNoteCount').textContent = '0';
    toggleNoteField(currentStatus);
    $('#editStatusModal').modal('show');
});

function toggleNoteField(status) {
    var noteGroup = document.getElementById('editStatusNoteGroup');
    var noteField = document.getElementById('editStatusNote');
    if (status === 'pending') {
        noteGroup.classList.remove('payroll-note-hidden');
        noteGroup.classList.add('payroll-note-visible');
        noteField.required = true;
    } else {
        noteGroup.classList.remove('payroll-note-visible');
        noteGroup.classList.add('payroll-note-hidden');
        noteField.required = false;
    }
}

document.getElementById('editStatusSelect') && document.getElementById('editStatusSelect').addEventListener('change', function() {
    toggleNoteField(this.value);
});

document.getElementById('editStatusNote') && document.getElementById('editStatusNote').addEventListener('input', function() {
    document.getElementById('editStatusNoteCount').textContent = this.value.length;
});

// ── Notes View Modal ────────────────────────────────────────────
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

document.querySelectorAll('.payroll-notes-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var empName    = this.dataset.employee;
        var period     = this.dataset.period || '';
        var notes      = [];
        var deductions = [];
        try { notes      = JSON.parse(this.dataset.notes      || '[]'); } catch(e){}
        try { deductions = JSON.parse(this.dataset.deductions || '[]'); } catch(e){}

        document.getElementById('notesModalEmpName').textContent = empName;

        var modalContent = document.getElementById('notesModalContent');
        modalContent.innerHTML = '';

        if (deductions.length === 0 && notes.length === 0) {
            modalContent.innerHTML = '<p class="text-muted text-center py-3"><i class="fas fa-book-open fa-2x mb-2 d-block payroll-notes-empty-icon"></i>No notes or deductions on record.</p>';
        }

        // ── Salary Deductions section ──────────────────────────────────────
        if (deductions.length > 0) {
            var dedHeader = document.createElement('h6');
            dedHeader.className = 'font-weight-bold text-danger mb-2';
            dedHeader.innerHTML = '<i class="fas fa-minus-circle mr-1"></i>Salary Deductions';
            modalContent.appendChild(dedHeader);

            var dedList = document.createElement('div');
            dedList.className = 'list-group mb-3';

            deductions.forEach(function(d) {
                var reasonLabel = String(d.reason || '').replace(/_/g,' ').replace(/\b\w/g, function(c){ return c.toUpperCase(); });
                var descPart    = d.description ? ' (' + esc(d.description) + ')' : '';
                var amtFmt      = parseFloat(d.amount || 0).toLocaleString('en-PH', {minimumFractionDigits:2});

                var item = document.createElement('div');
                item.className = 'list-group-item px-2 py-2';
                item.innerHTML =
                    '<div class="d-flex justify-content-between align-items-start">'
                  + '<div style="flex:1;min-width:0;">'
                  + '<strong class="text-danger">' + esc(reasonLabel) + esc(descPart) + '</strong>'
                  + ' &mdash; <strong>&#8369;' + amtFmt + '</strong>'
                  + '<br><small class="text-muted"><i class="fas fa-sticky-note mr-1"></i>' + esc(d.notes) + '</small>'
                  + '<br><small class="text-muted"><i class="fas fa-clock mr-1"></i>' + esc(d.created_at || '') + '</small>'
                  + '</div>'
                  + '<div class="ml-2 flex-shrink-0">'
                  + '<button type="button" class="btn btn-xs btn-warning mr-1 edit-ded-btn"><i class="fas fa-edit"></i></button>'
                  + '<button type="button" class="btn btn-xs btn-danger del-ded-btn" data-ded-id="' + esc(String(d.id)) + '" data-period="' + esc(period) + '"><i class="fas fa-trash"></i></button>'
                  + '</div>'
                  + '</div>';

                // Attach deduction data directly to the edit button DOM node (avoids JSON-in-HTML-attribute)
                item.querySelector('.edit-ded-btn')._dedData = d;
                dedList.appendChild(item);
            });
            modalContent.appendChild(dedList);

            // Bind edit buttons
            dedList.querySelectorAll('.edit-ded-btn').forEach(function(b) {
                b.addEventListener('click', function() {
                    var d = this._dedData;
                    document.getElementById('editDedId').value     = d.id;
                    document.getElementById('editDedReason').value = d.reason || '';
                    document.getElementById('editDedAmount').value = parseFloat(d.amount) || 0;
                    document.getElementById('editDedNotes').value  = d.notes  || '';
                    updateEditDedDescription(d.reason || '');
                    setTimeout(function(){
                        document.getElementById('editDedDescSelect').value = d.description || '';
                    }, 50);
                    $('#payrollNotesModal').modal('hide');
                    setTimeout(function(){ $('#editDeductionModal').modal('show'); }, 350);
                });
            });
            // Bind delete buttons
            dedList.querySelectorAll('.del-ded-btn').forEach(function(b) {
                b.addEventListener('click', function() {
                    if (!confirm('Delete this salary deduction? Net pay will be recalculated.')) return;
                    document.getElementById('delDedId').value = this.dataset.dedId;
                    document.getElementById('deleteDedForm').submit();
                });
            });
        }

        // ── Notes Log section ──────────────────────────────────────────────
        if (notes.length > 0) {
            var notesHeader = document.createElement('h6');
            notesHeader.className = 'font-weight-bold text-secondary mb-2';
            notesHeader.innerHTML = '<i class="fas fa-book mr-1"></i>Notes Log';
            modalContent.appendChild(notesHeader);

            var notesList = document.createElement('div');
            notesList.className = 'list-group';
            notes.forEach(function(n) {
                var nItem = document.createElement('div');
                nItem.className = 'list-group-item px-2 py-2';
                nItem.innerHTML =
                    '<div class="d-flex justify-content-between align-items-start">'
                  + '<div style="flex:1;min-width:0;">'
                  + '<span class="payroll-note-text">' + esc(n.note) + '</span>'
                  + '<br><small class="text-muted"><i class="fas fa-clock mr-1"></i>'
                  + esc(n.created_at || '') + ' &mdash; ' + esc(n.created_by_name || 'System')
                  + '</small>'
                  + '</div>'
                  + '<div class="ml-2 flex-shrink-0">'
                  + '<button type="button" class="btn btn-xs btn-danger del-note-btn" data-note-id="' + esc(String(n.id)) + '" data-period="' + esc(period) + '"><i class="fas fa-trash"></i></button>'
                  + '</div>'
                  + '</div>';
                notesList.appendChild(nItem);
            });
            modalContent.appendChild(notesList);

            notesList.querySelectorAll('.del-note-btn').forEach(function(b) {
                b.addEventListener('click', function() {
                    if (!confirm('Delete this note?')) return;
                    document.getElementById('delNoteId').value = this.dataset.noteId;
                    document.getElementById('deleteNoteForm').submit();
                });
            });
        }

        $('#payrollNotesModal').modal('show');
    });
});

// Edit deduction description dropdown handler
function updateEditDedDescription(reason) {
    var group = document.getElementById('editDedDescGroup');
    if (!group) return;
    var showDesc = ['destroyed_asset','lost_asset','damage'].includes(reason);
    group.style.display = showDesc ? '' : 'none';
}
JSEOF;

require_once __DIR__ . '/../layouts/admin_footer.php';
?>