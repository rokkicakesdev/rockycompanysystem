<?php
// core/PhilippineDeductions.php
// 2025 Philippine Statutory Deduction Computations
// Updated: Semi-monthly payroll support
// Sources: SSS Circular 2024-006, PhilHealth PA2025-0002,
//          Pag-IBIG Circular 460, BIR TRAIN Law (RR 13-2023)

declare(strict_types=1);

final class PhilippineDeductions
{
    // ── SSS ───────────────────────────────────────────────────────
    private const SSS_RATE_TOTAL    = 0.15;
    private const SSS_RATE_EMPLOYEE = 0.05;
    private const SSS_MIN_MSC       = 5000.0;
    private const SSS_MAX_MSC       = 35000.0;

    // ── PhilHealth ────────────────────────────────────────────────
    private const PHILHEALTH_RATE    = 0.05;
    private const PHILHEALTH_FLOOR   = 10000.0;
    private const PHILHEALTH_CEILING = 100000.0;

    // ── Pag-IBIG ──────────────────────────────────────────────────
    private const PAGIBIG_RATE_NORMAL  = 0.02;
    private const PAGIBIG_RATE_LOW     = 0.01;
    private const PAGIBIG_MFS_CAP      = 10000.0;
    private const PAGIBIG_MAX_PER_SIDE = 200.00;

    // SSS bracket table [min, max, msc, ee_share, er_share]
    private static array $sssTable = [
        [0,       4999.99,  5000,   250.00,   500.00],
        [5000,    5249.99,  5000,   250.00,   500.00],
        [5250,    5749.99,  5500,   275.00,   550.00],
        [5750,    6249.99,  6000,   300.00,   600.00],
        [6250,    6749.99,  6500,   325.00,   650.00],
        [6750,    7249.99,  7000,   350.00,   700.00],
        [7250,    7749.99,  7500,   375.00,   750.00],
        [7750,    8249.99,  8000,   400.00,   800.00],
        [8250,    8749.99,  8500,   425.00,   850.00],
        [8750,    9249.99,  9000,   450.00,   900.00],
        [9250,    9749.99,  9500,   475.00,   950.00],
        [9750,    10249.99, 10000,  500.00,  1000.00],
        [10250,   10749.99, 10500,  525.00,  1050.00],
        [10750,   11249.99, 11000,  550.00,  1100.00],
        [11250,   11749.99, 11500,  575.00,  1150.00],
        [11750,   12249.99, 12000,  600.00,  1200.00],
        [12250,   12749.99, 12500,  625.00,  1250.00],
        [12750,   13249.99, 13000,  650.00,  1300.00],
        [13250,   13749.99, 13500,  675.00,  1350.00],
        [13750,   14249.99, 14000,  700.00,  1400.00],
        [14250,   14749.99, 14500,  725.00,  1450.00],
        [14750,   15249.99, 15000,  750.00,  1500.00],
        [15250,   15749.99, 15500,  775.00,  1550.00],
        [15750,   16249.99, 16000,  800.00,  1600.00],
        [16250,   16749.99, 16500,  825.00,  1650.00],
        [16750,   17249.99, 17000,  850.00,  1700.00],
        [17250,   17749.99, 17500,  875.00,  1750.00],
        [17750,   18249.99, 18000,  900.00,  1800.00],
        [18250,   18749.99, 18500,  925.00,  1850.00],
        [18750,   19249.99, 19000,  950.00,  1900.00],
        [19250,   19749.99, 19500,  975.00,  1950.00],
        [19750,   20249.99, 20000, 1000.00,  2000.00],
        [20250,   20749.99, 20500, 1025.00,  2050.00],
        [20750,   21249.99, 21000, 1050.00,  2100.00],
        [21250,   21749.99, 21500, 1075.00,  2150.00],
        [21750,   22249.99, 22000, 1100.00,  2200.00],
        [22250,   22749.99, 22500, 1125.00,  2250.00],
        [22750,   23249.99, 23000, 1150.00,  2300.00],
        [23250,   23749.99, 23500, 1175.00,  2350.00],
        [23750,   24249.99, 24000, 1200.00,  2400.00],
        [24250,   24749.99, 24500, 1225.00,  2450.00],
        [24750,   25249.99, 25000, 1250.00,  2500.00],
        [25250,   25749.99, 25500, 1275.00,  2550.00],
        [25750,   26249.99, 26000, 1300.00,  2600.00],
        [26250,   26749.99, 26500, 1325.00,  2650.00],
        [26750,   27249.99, 27000, 1350.00,  2700.00],
        [27250,   27749.99, 27500, 1375.00,  2750.00],
        [27750,   28249.99, 28000, 1400.00,  2800.00],
        [28250,   28749.99, 28500, 1425.00,  2850.00],
        [28750,   29249.99, 29000, 1450.00,  2900.00],
        [29250,   29749.99, 29500, 1475.00,  2950.00],
        [29750,   30249.99, 30000, 1500.00,  3000.00],
        [30250,   30749.99, 30500, 1525.00,  3050.00],
        [30750,   31249.99, 31000, 1550.00,  3100.00],
        [31250,   31749.99, 31500, 1575.00,  3150.00],
        [31750,   32249.99, 32000, 1600.00,  3200.00],
        [32250,   32749.99, 32500, 1625.00,  3250.00],
        [32750,   33249.99, 33000, 1650.00,  3300.00],
        [33250,   33749.99, 33500, 1675.00,  3350.00],
        [33750,   34249.99, 34000, 1700.00,  3400.00],
        [34250,   34749.99, 34500, 1725.00,  3450.00],
        [34750,   PHP_INT_MAX, 35000, 1750.00, 3500.00],
    ];

    // ════════════════════════════════════════════════════════════
    //  INDIVIDUAL COMPONENT METHODS
    // ════════════════════════════════════════════════════════════

    public static function computeSSS(float $basicSalary): array
    {
        $salary = max(0.0, $basicSalary);
        foreach (self::$sssTable as $row) {
            [$min, $max, $msc, $ee, $er] = $row;
            if ($salary >= $min && $salary < $max + 0.01) {
                return ['msc' => $msc, 'employee' => round($ee,2), 'employer' => round($er,2), 'total' => round($ee+$er,2)];
            }
        }
        return ['msc' => self::SSS_MAX_MSC, 'employee' => 1750.00, 'employer' => 3500.00, 'total' => 5250.00];
    }

    public static function computePhilHealth(float $basicSalary): array
    {
        $mbs   = max(self::PHILHEALTH_FLOOR, min($basicSalary, self::PHILHEALTH_CEILING));
        $total = round($mbs * self::PHILHEALTH_RATE, 2);
        $share = round($total / 2, 2);
        return ['mbs' => $mbs, 'rate' => (self::PHILHEALTH_RATE * 100).'%', 'total' => $total, 'employee' => $share, 'employer' => $share];
    }

    public static function computePagIbig(float $basicSalary): array
    {
        $mfs    = min(max(0.0, $basicSalary), self::PAGIBIG_MFS_CAP);
        $eeRate = ($basicSalary <= 1500) ? self::PAGIBIG_RATE_LOW : self::PAGIBIG_RATE_NORMAL;
        $ee     = min(round($mfs * $eeRate, 2), self::PAGIBIG_MAX_PER_SIDE);
        $er     = min(round($mfs * self::PAGIBIG_RATE_NORMAL, 2), self::PAGIBIG_MAX_PER_SIDE);
        return ['mfs' => $mfs, 'employee_rate' => ($eeRate*100).'%', 'employee' => $ee, 'employer' => $er, 'total' => $ee+$er];
    }

    /**
     * Compute BIR withholding tax from full monthly salary and deductions.
     * Used for 2nd cutoff and legacy full-month payroll.
     */
    public static function computeWithholdingTax(
        float $basicSalary,
        float $sssEe,
        float $philhealthEe,
        float $pagibigEe
    ): array {
        $deductions = $sssEe + $philhealthEe + $pagibigEe;
        $taxable    = max(0.0, $basicSalary - $deductions);
        $tax        = self::applyMonthlyBracket($taxable);
        return [
            'taxable_income'   => round($taxable, 2),
            'total_deductions' => round($deductions, 2),
            'withholding_tax'  => round($tax, 2),
        ];
    }

    private static function applyMonthlyBracket(float $taxable): float
    {
        if ($taxable <= 20833.00)  return 0.00;
        if ($taxable <= 33332.99)  return round(0.15 * ($taxable - 20833.00), 2);
        if ($taxable <= 66666.99)  return round(2500.00  + 0.20 * ($taxable - 33333.00), 2);
        if ($taxable <= 166666.99) return round(10833.00 + 0.25 * ($taxable - 66667.00), 2);
        if ($taxable <= 666666.99) return round(40833.00 + 0.30 * ($taxable - 166667.00), 2);
        return round(200833.00 + 0.35 * ($taxable - 666667.00), 2);
    }

    /**
     * Apply the ANNUAL BIR tax bracket then convert to monthly equivalent.
     * Used for year-end reconciliation computation.
     */
    public static function computeAnnualTax(float $annualTaxableIncome): float
    {
        if ($annualTaxableIncome <= 250000.00)  return 0.00;
        if ($annualTaxableIncome <= 400000.00)  return round(0.15 * ($annualTaxableIncome - 250000.00), 2);
        if ($annualTaxableIncome <= 800000.00)  return round(22500.00  + 0.20 * ($annualTaxableIncome - 400000.00), 2);
        if ($annualTaxableIncome <= 2000000.00) return round(102500.00 + 0.25 * ($annualTaxableIncome - 800000.00), 2);
        if ($annualTaxableIncome <= 8000000.00) return round(402500.00 + 0.30 * ($annualTaxableIncome - 2000000.00), 2);
        return round(2202500.00 + 0.35 * ($annualTaxableIncome - 8000000.00), 2);
    }

    // ════════════════════════════════════════════════════════════
    //  SEMI-MONTHLY CUTOFF METHODS
    // ════════════════════════════════════════════════════════════

    /**
     * Compute 1ST CUTOFF payroll (1–15th of the month).
     *
     * Rules:
     * - Earnings  = cutoff1_amount (fixed override) OR basic_salary/2, plus allowance/2
     * - Deductions = withholding tax ONLY (no gov deductions)
     * - Tax method = half_monthly (monthly_tax/2) OR bir_table (fresh compute on half salary)
     * - Gov deductions = all zeros
     * - 13th month = added to gross/net on December 1st cutoff only
     *
     * @param float       $basicSalary   Full monthly basic salary
     * @param float       $allowance     Full monthly allowance
     * @param float|null  $fixedAmount   If set, overrides basic/2 for earnings split
     * @param string      $taxMethod     'half_monthly' or 'bir_table'
     * @param float       $thirteenth    13th month amount to include (0 if not December)
     * @param float       $absentDeduction Deduction for absences
     */
    public static function computeFirstCutoff(
        float  $basicSalary,
        float  $allowance      = 0.0,
        ?float $fixedAmount    = null,
        string $taxMethod      = 'half_monthly',
        float  $thirteenth     = 0.0,
        float  $absentDeduction = 0.0
    ): array {
        $basicSalary = max(0.0, $basicSalary);
        $allowance   = max(0.0, $allowance);

        // Determine 1st cutoff earnings
        $cutoffBasic     = $fixedAmount !== null ? max(0.0, $fixedAmount) : round($basicSalary / 2, 2);
        $cutoffAllowance = round($allowance / 2, 2);

        // Gross before absences
        $grossPay = round($cutoffBasic + $cutoffAllowance + $thirteenth, 2);

        // Apply absent deduction
        $absentDeduction = max(0.0, $absentDeduction);
        $grossPay        = round(max(0.0, $grossPay - $absentDeduction), 2);

        // Withholding tax — NO gov deductions on 1st cutoff
        if ($taxMethod === 'bir_table') {
            // Fresh compute on the half salary (no gov deductions to subtract)
            $taxableIncome   = max(0.0, $cutoffBasic);
            $withholdingTax  = self::applyMonthlyBracket($taxableIncome);
        } else {
            // Default: half of full monthly tax
            $fullMonthlyTax  = self::computeFullMonthlyTax($basicSalary);
            $withholdingTax  = round($fullMonthlyTax / 2, 2);
            $taxableIncome   = max(0.0, $cutoffBasic);
        }
        $withholdingTax = round($withholdingTax, 2);

        $totalDeductions = round($withholdingTax + $absentDeduction, 2);
        $netPay          = round(max(0.0, $grossPay - $withholdingTax), 2);

        return [
            // Earnings
            'basic_salary'     => round($cutoffBasic, 2),
            'allowance'        => round($cutoffAllowance, 2),
            'thirteenth_month' => round($thirteenth, 2),
            'gross_pay'        => $grossPay,

            // Gov deductions — all zero on 1st cutoff
            'sss_msc'          => 0.0,
            'sss_ee'           => 0.0,
            'sss_er'           => 0.0,
            'philhealth_mbs'   => 0.0,
            'philhealth_ee'    => 0.0,
            'philhealth_er'    => 0.0,
            'pagibig_mfs'      => 0.0,
            'pagibig_ee'       => 0.0,
            'pagibig_er'       => 0.0,

            // Tax
            'taxable_income'   => round($taxableIncome, 2),
            'withholding_tax'  => $withholdingTax,

            // Totals
            'absent_deduction' => round($absentDeduction, 2),
            'total_deductions' => $totalDeductions,
            'net_pay'          => $netPay,

            // Meta
            'cutoff'           => 1,
            'tax_method'       => $taxMethod,
        ];
    }

    /**
     * Compute 2ND CUTOFF payroll (16th–end of month).
     *
     * Rules:
     * - Earnings = (basic_salary - cutoff1_amount) OR basic/2, plus allowance/2
     * - Gov deductions:
     *   - 'second_cutoff' mode: FULL monthly SSS/PhilHealth/Pag-IBIG
     *   - 'split' mode: HALF of monthly SSS/PhilHealth/Pag-IBIG
     * - Tax method: half_monthly (monthly_tax/2) OR bir_table (fresh on half salary w/ gov deds)
     * - Year-end reconciliation adjustment added here in December
     *
     * @param float       $basicSalary      Full monthly basic salary
     * @param float       $allowance        Full monthly allowance
     * @param float|null  $fixedAmount      1st cutoff fixed amount (to compute remainder)
     * @param string      $taxMethod        'half_monthly' or 'bir_table'
     * @param string      $govMode          'second_cutoff' or 'split'
     * @param float       $absentDeduction  Deduction for absences
     * @param float       $reconciliation   Year-end tax adjustment (+ = owe more, - = refund)
     */
    public static function computeSecondCutoff(
        float  $basicSalary,
        float  $allowance        = 0.0,
        ?float $fixedAmount      = null,
        string $taxMethod        = 'half_monthly',
        string $govMode          = 'second_cutoff',
        float  $absentDeduction  = 0.0,
        float  $reconciliation   = 0.0
    ): array {
        $basicSalary = max(0.0, $basicSalary);
        $allowance   = max(0.0, $allowance);

        // 2nd cutoff earnings = full monthly minus what was paid in 1st cutoff
        $cutoff1Basic    = $fixedAmount !== null ? max(0.0, $fixedAmount) : round($basicSalary / 2, 2);
        $cutoffBasic     = round(max(0.0, $basicSalary - $cutoff1Basic), 2);
        $cutoffAllowance = round($allowance / 2, 2);

        // Gov deductions — always computed on FULL monthly salary basis
        $sss        = self::computeSSS($basicSalary);
        $philhealth = self::computePhilHealth($basicSalary);
        $pagibig    = self::computePagIbig($basicSalary);

        if ($govMode === 'split') {
            // Half of each government contribution
            $sssEe        = round($sss['employee'] / 2, 2);
            $sssEr        = round($sss['employer'] / 2, 2);
            $sssMsc       = $sss['msc'];
            $phEe         = round($philhealth['employee'] / 2, 2);
            $phEr         = round($philhealth['employer'] / 2, 2);
            $phMbs        = $philhealth['mbs'];
            $piEe         = round($pagibig['employee'] / 2, 2);
            $piEr         = round($pagibig['employer'] / 2, 2);
            $piMfs        = $pagibig['mfs'];
        } else {
            // Full monthly contributions collected entirely on 2nd cutoff
            $sssEe        = $sss['employee'];
            $sssEr        = $sss['employer'];
            $sssMsc       = $sss['msc'];
            $phEe         = $philhealth['employee'];
            $phEr         = $philhealth['employer'];
            $phMbs        = $philhealth['mbs'];
            $piEe         = $pagibig['employee'];
            $piEr         = $pagibig['employer'];
            $piMfs        = $pagibig['mfs'];
        }

        // Withholding tax
        if ($taxMethod === 'bir_table') {
            // Fresh compute: taxable = cutoff basic minus gov deductions applied here
            $taxableIncome  = max(0.0, $cutoffBasic - $sssEe - $phEe - $piEe);
            $withholdingTax = self::applyMonthlyBracket($taxableIncome);
        } else {
            // half_monthly: half of full monthly tax
            $fullMonthlyTax = self::computeFullMonthlyTax($basicSalary);
            $withholdingTax = round($fullMonthlyTax / 2, 2);
            $taxableIncome  = max(0.0, $cutoffBasic - $sssEe - $phEe - $piEe);
        }
        $withholdingTax = round($withholdingTax, 2);

        // Year-end reconciliation: add any shortfall (or refund excess) to December 2nd cutoff
        // Positive reconciliation = extra tax owed by employee (deducted)
        // Negative reconciliation = overpayment refunded (added to net)
        $reconciliation = round($reconciliation, 2);

        // Gross and totals
        $grossPay  = round(max(0.0, $cutoffBasic + $cutoffAllowance - $absentDeduction), 2);
        $govEeTotal = $sssEe + $phEe + $piEe;
        $totalDeductions = round($govEeTotal + $withholdingTax + $absentDeduction + $reconciliation, 2);
        $netPay          = round(max(0.0, $grossPay - $govEeTotal - $withholdingTax - $reconciliation), 2);

        return [
            // Earnings
            'basic_salary'     => $cutoffBasic,
            'allowance'        => $cutoffAllowance,
            'thirteenth_month' => 0.0,
            'gross_pay'        => $grossPay,

            // Gov deductions
            'sss_msc'          => $sssMsc,
            'sss_ee'           => $sssEe,
            'sss_er'           => $sssEr,
            'philhealth_mbs'   => $phMbs,
            'philhealth_ee'    => $phEe,
            'philhealth_er'    => $phEr,
            'pagibig_mfs'      => $piMfs,
            'pagibig_ee'       => $piEe,
            'pagibig_er'       => $piEr,

            // Tax
            'taxable_income'   => round($taxableIncome, 2),
            'withholding_tax'  => $withholdingTax,

            // Year-end reconciliation (stored in other_deductions if != 0)
            'reconciliation'   => $reconciliation,

            // Totals
            'absent_deduction' => round($absentDeduction, 2),
            'total_deductions' => $totalDeductions,
            'net_pay'          => $netPay,

            // Meta
            'cutoff'           => 2,
            'tax_method'       => $taxMethod,
            'gov_mode'         => $govMode,
        ];
    }

    /**
     * Compute year-end tax reconciliation.
     * Returns the difference between what SHOULD have been withheld
     * (based on full annual income) and what WAS actually withheld.
     *
     * Positive = employee underpaid tax (deduct from December 2nd cutoff net pay)
     * Negative = employee overpaid tax (refund via December 2nd cutoff)
     *
     * @param float $annualBasicSalary  Total basic salary earned Jan–Dec (from payroll_records)
     * @param float $annualGovDeds      Total gov deductions paid Jan–Nov (SSS+PH+PI EE only)
     * @param float $annualTaxPaid      Total withholding tax already deducted Jan–Nov cutoffs
     */
    public static function computeYearEndReconciliation(
        float $annualBasicSalary,
        float $annualGovDeds,
        float $annualTaxPaid
    ): float {
        // Annual taxable income = annual basic - annual gov deductions
        $annualTaxable = max(0.0, $annualBasicSalary - $annualGovDeds);

        // Correct annual tax per BIR annual bracket
        $correctAnnualTax = self::computeAnnualTax($annualTaxable);

        // Difference = what should be paid - what was already paid
        $adjustment = round($correctAnnualTax - $annualTaxPaid, 2);

        return $adjustment; // positive = owe, negative = refund
    }

    /**
     * Helper: compute full monthly withholding tax from monthly basic salary.
     * Used internally for 'half_monthly' tax method.
     */
    private static function computeFullMonthlyTax(float $basicSalary): float
    {
        $sss        = self::computeSSS($basicSalary);
        $philhealth = self::computePhilHealth($basicSalary);
        $pagibig    = self::computePagIbig($basicSalary);
        $taxable    = max(0.0, $basicSalary - $sss['employee'] - $philhealth['employee'] - $pagibig['employee']);
        return self::applyMonthlyBracket($taxable);
    }

    // ════════════════════════════════════════════════════════════
    //  LEGACY FULL-MONTH METHOD (kept for backward compatibility)
    // ════════════════════════════════════════════════════════════

    /**
     * Compute full deductions for a complete monthly payroll period.
     * Still used by admin payslip preview and computePayroll().
     */
    public static function computeAll(float $basicSalary, float $allowance = 0.0): array
    {
        if ($basicSalary < 0) $basicSalary = 0.0;

        $sss        = self::computeSSS($basicSalary);
        $philhealth = self::computePhilHealth($basicSalary);
        $pagibig    = self::computePagIbig($basicSalary);

        $taxData  = self::computeWithholdingTax(
            $basicSalary,
            $sss['employee'],
            $philhealth['employee'],
            $pagibig['employee']
        );

        $grossPay = $basicSalary + $allowance;
        $totalDed = $sss['employee'] + $philhealth['employee'] + $pagibig['employee'] + $taxData['withholding_tax'];
        $netPay   = $grossPay - $totalDed;

        return [
            'basic_salary'     => round($basicSalary, 2),
            'allowance'        => round($allowance, 2),
            'gross_pay'        => round($grossPay, 2),
            'sss_msc'          => $sss['msc'],
            'sss_ee'           => $sss['employee'],
            'sss_er'           => $sss['employer'],
            'philhealth_mbs'   => $philhealth['mbs'],
            'philhealth_ee'    => $philhealth['employee'],
            'philhealth_er'    => $philhealth['employer'],
            'pagibig_mfs'      => $pagibig['mfs'],
            'pagibig_ee'       => $pagibig['employee'],
            'pagibig_er'       => $pagibig['employer'],
            'taxable_income'   => $taxData['taxable_income'],
            'withholding_tax'  => $taxData['withholding_tax'],
            'total_deductions' => round($totalDed, 2),
            'net_pay'          => round($netPay, 2),
        ];
    }
}