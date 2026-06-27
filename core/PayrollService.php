<?php
// core/PayrollService.php
// ─────────────────────────────────────────────────────────────────────────────
//  PayrollService — pure computation service, zero HTML, zero session access.
//
//  Extracted from app/views/admin/payroll.php (generate_payroll POST handler).
//  The view now calls PayrollService::generateForPeriod() and only handles
//  the HTTP response (redirects, flash messages) — no computation inline.
//
//  Responsibilities:
//    - Validate period format and employee eligibility
//    - Compute attendance summaries per employee per cutoff
//    - Apply proration, absent deduction, LWOP deduction
//    - Compute OT pay (Art. 87 Labor Code)
//    - Compute holiday premium pay (PD 442)
//    - Dispatch to PhilippineDeductions for statutory calculations
//    - Handle 13th month injection (December 1st cutoff)
//    - Handle year-end tax reconciliation (December 2nd cutoff)
//    - Save payroll records in a single DB transaction
//    - Return a structured result DTO (no redirects, no headers)
//
//  Usage in payroll.php:
//    $result = PayrollService::generateForPeriod($genPeriod, $selectedEmpIds, $_SESSION['user_id']);
//    if ($result->success) { header("Location: ..."); exit; }
//    else                  { $msg = $result->errorHtml; }
// ─────────────────────────────────────────────────────────────────────────────

declare(strict_types=1);

require_once __DIR__ . '/Model.php';
require_once __DIR__ . '/PhilippineDeductions.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/models/LoanModel.php';

// ── Result DTO ─────────────────────────────────────────────────────────────
final class PayrollGenerationResult
{
    public function __construct(
        public readonly bool   $success,
        public readonly int    $generated    = 0,
        public readonly int    $skipped      = 0,
        public readonly string $period       = '',
        public readonly array  $skipReasons  = [],
        public readonly string $errorHtml    = '',
    ) {}
}

// ── Service ────────────────────────────────────────────────────────────────
final class PayrollService
{
    // =========================================================================
    //  PUBLIC ENTRY POINT
    // =========================================================================

    /**
     * Generate payroll records for the given period and employee IDs.
     *
     * @param string $period       YYYY-MM-C format (e.g. "2026-01-1")
     * @param array  $employeeIds  Array of employee IDs (strings or ints)
     * @param int    $processedBy  User ID of the admin triggering generation
     *
     * @return PayrollGenerationResult
     */
    public static function generateForPeriod(
        string $period,
        array  $employeeIds,
        int    $processedBy
    ): PayrollGenerationResult {

        // ── Input validation ──────────────────────────────────────────────────
        if (!preg_match('/^\d{4}-\d{2}-[12]$/', $period)) {
            return new PayrollGenerationResult(
                success: false,
                errorHtml: self::errorAlert('Invalid payroll period format.')
            );
        }

        $maxAllowed = date('Y-m', strtotime('+1 month'));
        if (Model::periodBase($period) > $maxAllowed) {
            return new PayrollGenerationResult(
                success: false,
                errorHtml: self::errorAlert('Cannot generate payroll more than one month in the future.')
            );
        }

        if (empty($employeeIds)) {
            return new PayrollGenerationResult(
                success: false,
                errorHtml: self::warnAlert('No employees selected.')
            );
        }

        // ── Attendance guard ──────────────────────────────────────────────────
        $missing = Model::getEmployeesWithMissingAttendance($employeeIds, $period);
        if (!empty($missing)) {
            $list = '<ul class="mb-0 mt-1 payroll-skip-list">';
            foreach ($missing as $name) {
                $list .= '<li>' . htmlspecialchars($name) . '</li>';
            }
            $list .= '</ul>';
            return new PayrollGenerationResult(
                success: false,
                errorHtml: self::errorAlert(
                    '<strong>Cannot generate payroll.</strong> The following employee(s) '
                    . 'have no attendance log for this period:' . $list
                )
            );
        }

        // ── Process each employee in a single transaction ─────────────────────
        $generated   = 0;
        $skipped     = 0;
        $skipReasons = [];
        $db          = Database::getInstance();
        $db->beginTransaction();

        try {
            foreach ($employeeIds as $rawId) {
                $empId = (int)$rawId;
                $result = self::processSingleEmployee($empId, $period, $processedBy);

                if ($result === null) {
                    $generated++;
                } else {
                    $skipped++;
                    $skipReasons[] = $result; // skip reason string
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log("PayrollService::generateForPeriod error [{$period}]: " . $e->getMessage());
            return new PayrollGenerationResult(
                success: false,
                errorHtml: self::errorAlert(
                    'Payroll generation failed due to a database error. No records were saved. Please try again.'
                )
            );
        }

        return new PayrollGenerationResult(
            success:     true,
            generated:   $generated,
            skipped:     $skipped,
            period:      $period,
            skipReasons: $skipReasons,
        );
    }

    // =========================================================================
    //  PRIVATE — per-employee computation
    // =========================================================================

    /**
     * Process one employee for the given period.
     * Returns null on success; returns a skip-reason string on skip/error.
     */
    private static function processSingleEmployee(
        int    $empId,
        string $period,
        int    $processedBy
    ): ?string {

        // ── Employee guard ────────────────────────────────────────────────────
        $emp = Model::findEmployeeById($empId);
        if (!$emp) {
            return "ID:{$empId} - employee not found";
        }
        if ($emp['status'] !== 'active') {
            return htmlspecialchars($emp['name']) . " - not active ({$emp['status']})";
        }
        if (Model::employeeExistsInPeriod($empId, $period)) {
            return htmlspecialchars($emp['name']) . " - already has a record for {$period}";
        }

        // ── Settings ──────────────────────────────────────────────────────────
        $settings    = Model::getEmployeePayrollSettings($empId);
        $fixedAmount = $settings['cutoff1_fixed_amount'] !== null
                        ? (float)$settings['cutoff1_fixed_amount']
                        : null;
        $taxMethod   = $settings['tax_method'];
        $govMode     = $settings['gov_deduction_mode'];
        $cutoffNum   = Model::periodCutoff($period);
        $yearMonth   = Model::periodBase($period);
        $dateStart   = $emp['date_start'] ?? $emp['date_hired'] ?? '';

        // ── Date range ────────────────────────────────────────────────────────
        [$year, $month] = explode('-', $yearMonth);
        $lastDay = date('t', mktime(0, 0, 0, (int)$month, 1, (int)$year));
        if ($cutoffNum === 1) {
            $cutoffFrom = "{$yearMonth}-01";
            $cutoffTo   = "{$yearMonth}-15";
        } else {
            $cutoffFrom = "{$yearMonth}-16";
            $cutoffTo   = "{$yearMonth}-{$lastDay}";
        }

        // ── Attendance summary ────────────────────────────────────────────────
        $att = Model::getCutoffAttendanceSummary($empId, $cutoffFrom, $cutoffTo, $dateStart);

        $scheduledDays      = (int)($att['scheduled_days']            ?? 0);
        $effectiveSchedDays = (int)($att['effective_scheduled_days']  ?? $scheduledDays);
        $daysPresent        = (int)($att['days_present']              ?? 0);
        $daysAbsentOnly     = (int)($att['days_absent']               ?? 0);
        $daysHalf           = (int)($att['days_half']                 ?? 0);
        $daysPaidLeave      = (int)($att['days_on_leave']             ?? 0);
        $daysUnpaidLeave    = (int)($att['days_unpaid_leave']         ?? 0);
        $otHoursRegularDay  = (float)($att['ot_hours_regular_day']    ?? 0);
        $otHoursOnHoliday   = (float)($att['ot_hours_on_holiday']     ?? 0);
        $workedRegHolidays  = (int)($att['worked_regular_holiday_days']  ?? 0);
        $workedSpecHolidays = (int)($att['worked_special_holiday_days']  ?? 0);

        // ── Daily rate & deductions ───────────────────────────────────────────
        $cutoffBasicAmount   = 0.0;
        $dailyRate           = 0.0;
        $proratedDeduction   = 0.0;
        $absentDeduction     = 0.0;
        $unpaidLeaveDeduction= 0.0;

        if ($scheduledDays > 0) {
            $cutoffBasicAmount = $fixedAmount !== null
                ? $fixedAmount
                : round((float)$emp['basic_salary'] / 2, 2);

            $dailyRate         = round($cutoffBasicAmount / $scheduledDays, 4);
            $proratedDays      = max(0, $scheduledDays - $effectiveSchedDays);
            $proratedDeduction = round($proratedDays * $dailyRate, 2);

            $absentDeduction = round(
                $proratedDeduction
                + ($daysAbsentOnly  * $dailyRate)
                + ($daysHalf        * $dailyRate * 0.5),
                2
            );
            $unpaidLeaveDeduction = round($daysUnpaidLeave * $dailyRate, 2);
        }

        // ── Overtime pay (Art. 87 Labor Code) ────────────────────────────────
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

        // ── Holiday premium pay (PD 442 Labor Code) ───────────────────────────
        $holidayPay = 0.0;
        if ($scheduledDays > 0 && ($workedRegHolidays > 0 || $workedSpecHolidays > 0)) {
            $holidayPay = PhilippineDeductions::computeHolidayPremiumPay(
                $cutoffBasicAmount,
                $scheduledDays,
                $workedRegHolidays,
                $workedSpecHolidays
            );
        }

        $extraEarnings = round($overtimePay + $holidayPay, 2);

        // ── 13th month (December 1st cutoff only) ─────────────────────────────
        $thirteenthAmount = 0.0;
        if (Model::isDecember1stCutoff($period)) {
            $rec13 = Model::get13thMonthByEmployee($empId, Model::periodYear($period));
            if ($rec13 && $rec13['status'] === 'pending') {
                $thirteenthAmount = (float)$rec13['amount'];
            }
        }

        // ── Year-end reconciliation (December 2nd cutoff only) ────────────────
        //
        // BIR Annualization requirement (RR 11-2018, RR 13-2023):
        // The employer must compute the employee's TRUE annual tax liability and
        // reconcile it against all withholding tax already deducted for the year.
        //
        // The December 2nd cutoff record has NOT been saved to the DB yet when we
        // compute the reconciliation. We therefore must MANUALLY add the current
        // cutoff's figures to the YTD totals pulled from the DB so that the annual
        // taxable base is complete and accurate:
        //
        //   annualBasic    += December 2nd cutoff basic earned (after proration)
        //   annualGovDeds  += December 2nd cutoff EE gov deductions (SSS + PH + PI)
        //   annualTaxPaid  += December 2nd cutoff semi-monthly withholding tax
        //                     (i.e., the "regular" tax that computeSecondCutoff will apply
        //                     independently; we include it here so the reconciliation
        //                     correctly measures only the REMAINING gap — otherwise we
        //                     would produce a reconciliation amount that double-counts
        //                     the regular semi-monthly tax for this cutoff)
        //
        // Without this correction:
        //   - annualTaxable is overstated  (Dec 2nd gov deds missing from the subtrahend)
        //   - correctAnnualTax is overstated
        //   - reconciliation = overstated - (tax paid excluding Dec 2nd cutoff regular tax)
        //   → employee is over-charged on reconciliation while also paying the
        //     regular Dec-2nd semi-monthly tax separately → effective double-withholding.
        $reconciliation = 0.0;
        if (Model::isDecember2ndCutoff($period)) {
            $year4 = Model::periodYear($period);
            if (Model::hasPayrollBeforeDecember($empId, $year4)) {
                // 1. Current cutoff earnings (basic after absent/proration + OT/holiday premium)
                //    extraEarnings (OT + holiday pay) is taxable under TRAIN Law (RR 13-2023)
                //    and must be included in the annual taxable base for correct reconciliation.
                $currentCutoffEarned = max(0.0, $cutoffBasicAmount - $proratedDeduction) + $extraEarnings;

                // 2. Current cutoff gov deductions — compute now so they can be
                //    included in the annual totals. Use the same method computeSecondCutoff
                //    will use when it runs immediately after this block.
                $dec2Sss        = PhilippineDeductions::computeSSS((float)$emp['basic_salary']);
                $dec2Ph         = PhilippineDeductions::computePhilHealth((float)$emp['basic_salary']);
                $dec2Pi         = PhilippineDeductions::computePagIbig((float)$emp['basic_salary']);

                if ($govMode === 'split') {
                    $dec2GovEe = round($dec2Sss['employee'] / 2, 2)
                               + round($dec2Ph['employee']  / 2, 2)
                               + round($dec2Pi['employee']  / 2, 2);
                } else {
                    $dec2GovEe = $dec2Sss['employee'] + $dec2Ph['employee'] + $dec2Pi['employee'];
                }

                // 3. Current cutoff regular semi-monthly withholding tax
                //    taxable = earned (basic + OT/holiday) minus gov deds, annualised ÷ 24
                //    This mirrors exactly what computeSecondCutoff will compute so that
                //    annualTaxPaid includes this cutoff's regular withholding before
                //    the reconciliation measures only the remaining gap.
                $dec2Taxable    = max(0.0, $currentCutoffEarned - $dec2GovEe);
                $dec2AnnualTax  = PhilippineDeductions::computeAnnualTax($dec2Taxable * 24);
                $dec2RegularTax = round($dec2AnnualTax / 24, 2);

                // 4. Build complete annual figures
                $annualBasic    = Model::getTotalBasicByYear($empId, $year4) + $currentCutoffEarned;
                $annualGovDeds  = Model::getTotalGovDedsByYear($empId, $year4)  + $dec2GovEe;
                $annualTaxPaid  = Model::getTotalWithholdingTaxByYear($empId, $year4) + $dec2RegularTax;

                $reconciliation = PhilippineDeductions::computeYearEndReconciliation(
                    $annualBasic, $annualGovDeds, $annualTaxPaid
                );
            }
        }

        // ── Statutory deductions (PhilippineDeductions engine) ────────────────
        $totalAbsent = $absentDeduction + $unpaidLeaveDeduction;
        if ($cutoffNum === 1) {
            $deductions = PhilippineDeductions::computeFirstCutoff(
                (float)$emp['basic_salary'],
                (float)($emp['allowance'] ?? 0),
                $fixedAmount,
                $taxMethod,
                $thirteenthAmount,
                $totalAbsent,
                $extraEarnings
            );
        } else {
            $deductions = PhilippineDeductions::computeSecondCutoff(
                (float)$emp['basic_salary'],
                (float)($emp['allowance'] ?? 0),
                $fixedAmount,
                $taxMethod,
                $govMode,
                $totalAbsent,
                $reconciliation,
                $extraEarnings
            );
        }

        $daysWorked = max(0, $daysPresent + ($daysHalf * 0.5));

        // ── Remarks string ────────────────────────────────────────────────────
        $remarks = '';
        if ($thirteenthAmount > 0) {
            $remarks .= '13th month included. ';
        }
        if ($overtimePay > 0) {
            $remarks .= 'OT pay: ₱' . number_format($overtimePay, 2) . '. ';
        }
        if ($holidayPay > 0) {
            $remarks .= 'Holiday premium: ₱' . number_format($holidayPay, 2) . '. ';
        }
        if ($reconciliation != 0) {
            $remarks .= 'Year-end tax reconciliation: '
                      . ($reconciliation > 0 ? '+' : '')
                      . number_format($reconciliation, 2) . '.';
        }

        // ── Loan deductions (SSS salary loan, Pag-IBIG MPL, company loans) ──
        $loanResult   = LoanModel::computeCutoffDeduction($empId, $period);
        $loanDeduction = $loanResult['total'];
        $loanItems    = $loanResult['items'];

        if ($loanDeduction > 0) {
            $remarks .= 'Loan deduction: ₱' . number_format($loanDeduction, 2) . '. ';
        }

        // ── Build record ──────────────────────────────────────────────────────
        $record = [
            'employee_id'            => $empId,
            'period'                 => $period,
            'basic_salary'           => $deductions['basic_salary'],
            'allowance'              => $deductions['allowance'],
            'gross_pay'              => $deductions['gross_pay'],
            'sss_msc'                => $deductions['sss_msc'],
            'sss_ee'                 => $deductions['sss_ee'],
            'sss_er'                 => $deductions['sss_er'],
            'philhealth_mbs'         => $deductions['philhealth_mbs'],
            'philhealth_ee'          => $deductions['philhealth_ee'],
            'philhealth_er'          => $deductions['philhealth_er'],
            'pagibig_mfs'            => $deductions['pagibig_mfs'],
            'pagibig_ee'             => $deductions['pagibig_ee'],
            'pagibig_er'             => $deductions['pagibig_er'],
            'taxable_income'         => $deductions['taxable_income'],
            'withholding_tax'        => $deductions['withholding_tax'],
            'other_deductions'       => round($deductions['reconciliation'] ?? 0, 2),
            'absent_deduction'       => $absentDeduction,
            'unpaid_leave_deduction' => $unpaidLeaveDeduction,
            'overtime_pay'           => $overtimePay,
            'holiday_pay'            => $holidayPay,
            'salary_deduction'       => 0,
            'loan_deduction'         => $loanDeduction,
            'total_deductions'       => round($deductions['total_deductions'] + $loanDeduction, 2),
            'net_pay'                => round($deductions['net_pay'] - $loanDeduction, 2),
            'days_worked'            => $daysWorked,
            'days_absent'            => $daysAbsentOnly + $daysUnpaidLeave,
            'days_paid_leave'        => $daysPaidLeave,
            'working_days_in_month'  => $scheduledDays,
            'remarks'                => trim($remarks),
            'status'                 => 'pending',
            'processed_by'           => $processedBy,
        ];

        if (!Model::createPayrollRecord($record)) {
            return htmlspecialchars($emp['name']) . ' - DB insert failed';
        }

        $payrollId = (int) Database::getInstance()->lastInsertId();

        if (!empty($loanItems)) {
            LoanModel::applyDeductions($payrollId, $empId, $period, $loanItems);
        }

        return null; // success
    }

    // =========================================================================
    //  HTML ALERT HELPERS (keeps the service boundary clean)
    // =========================================================================

    private static function errorAlert(string $body): string
    {
        return "<div class='alert alert-danger'>"
             . "<i class='fas fa-exclamation-circle mr-2'></i>{$body}</div>";
    }

    private static function warnAlert(string $body): string
    {
        return "<div class='alert alert-warning'>"
             . "<i class='fas fa-exclamation-triangle mr-2'></i>{$body}</div>";
    }
}
