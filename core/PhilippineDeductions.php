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
    // 2025 rates per SSS Circular 2024-006 (effective Jan 2025):
    // EE = 4.5%, ER = 9.5%, EC = 1% (borne by ER) → total employee share = 4.5%
    // For salaries above the MSC ceiling (35,000), contribution is computed
    // as rate × actual salary (not capped), matching real-world practice.
    private const SSS_RATE_TOTAL    = 0.14;
    private const SSS_RATE_EMPLOYEE = 0.045;
    private const SSS_RATE_EMPLOYER = 0.095;
    private const SSS_MIN_MSC       = 5000.0;
    private const SSS_MAX_MSC       = 35000.0;

    // ── PhilHealth ────────────────────────────────────────────────
    // 2025: 5% of basic salary, split equally EE/ER (unchanged)
    private const PHILHEALTH_RATE    = 0.05;
    private const PHILHEALTH_FLOOR   = 10000.0;
    private const PHILHEALTH_CEILING = 100000.0;

    // ── Pag-IBIG ──────────────────────────────────────────────────
    // 2025 per Pag-IBIG Circular 460: MFS cap = 5,000, max EE = ₱100
    // EE rate: 2% for salary > 1,500 (capped at 100), 1% for ≤ 1,500
    private const PAGIBIG_RATE_NORMAL  = 0.02;
    private const PAGIBIG_RATE_LOW     = 0.01;
    private const PAGIBIG_MFS_CAP      = 5000.0;
    private const PAGIBIG_MAX_PER_SIDE = 100.00;

    // SSS 2025 bracket table [salary_min, salary_max, msc, ee(4.5%), er(9.5%)]
    private static array $sssTable = [
        [0,         4999.99,  5000,   225.00,   475.00],
        [5000,      5249.99,  5000,   225.00,   475.00],
        [5250.01,   5749.99,  5500,   247.50,   522.50],
        [5750.01,   6249.99,  6000,   270.00,   570.00],
        [6250.01,   6749.99,  6500,   292.50,   617.50],
        [6750.01,   7249.99,  7000,   315.00,   665.00],
        [7250.01,   7749.99,  7500,   337.50,   712.50],
        [7750.01,   8249.99,  8000,   360.00,   760.00],
        [8250.01,   8749.99,  8500,   382.50,   807.50],
        [8750.01,   9249.99,  9000,   405.00,   855.00],
        [9250.01,   9749.99,  9500,   427.50,   902.50],
        [9750.01,   10249.99, 10000,  450.00,   950.00],
        [10250.01,  10749.99, 10500,  472.50,   997.50],
        [10750.01,  11249.99, 11000,  495.00,  1045.00],
        [11250.01,  11749.99, 11500,  517.50,  1092.50],
        [11750.01,  12249.99, 12000,  540.00,  1140.00],
        [12250.01,  12749.99, 12500,  562.50,  1187.50],
        [12750.01,  13249.99, 13000,  585.00,  1235.00],
        [13250.01,  13749.99, 13500,  607.50,  1282.50],
        [13750.01,  14249.99, 14000,  630.00,  1330.00],
        [14250.01,  14749.99, 14500,  652.50,  1377.50],
        [14750.01,  15249.99, 15000,  675.00,  1425.00],
        [15250.01,  15749.99, 15500,  697.50,  1472.50],
        [15750.01,  16249.99, 16000,  720.00,  1520.00],
        [16250.01,  16749.99, 16500,  742.50,  1567.50],
        [16750.01,  17249.99, 17000,  765.00,  1615.00],
        [17250.01,  17749.99, 17500,  787.50,  1662.50],
        [17750.01,  18249.99, 18000,  810.00,  1710.00],
        [18250.01,  18749.99, 18500,  832.50,  1757.50],
        [18750.01,  19249.99, 19000,  855.00,  1805.00],
        [19250.01,  19749.99, 19500,  877.50,  1852.50],
        [19750.01,  20249.99, 20000,  900.00,  1900.00],
        [20250.01,  20749.99, 20500,  922.50,  1947.50],
        [20750.01,  21249.99, 21000,  945.00,  1995.00],
        [21250.01,  21749.99, 21500,  967.50,  2042.50],
        [21750.01,  22249.99, 22000,  990.00,  2090.00],
        [22250.01,  22749.99, 22500, 1012.50,  2137.50],
        [22750.01,  23249.99, 23000, 1035.00,  2185.00],
        [23250.01,  23749.99, 23500, 1057.50,  2232.50],
        [23750.01,  24249.99, 24000, 1080.00,  2280.00],
        [24250.01,  24749.99, 24500, 1102.50,  2327.50],
        [24750.01,  25249.99, 25000, 1125.00,  2375.00],
        [25250.01,  25749.99, 25500, 1147.50,  2422.50],
        [25750.01,  26249.99, 26000, 1170.00,  2470.00],
        [26250.01,  26749.99, 26500, 1192.50,  2517.50],
        [26750.01,  27249.99, 27000, 1215.00,  2565.00],
        [27250.01,  27749.99, 27500, 1237.50,  2612.50],
        [27750.01,  28249.99, 28000, 1260.00,  2660.00],
        [28250.01,  28749.99, 28500, 1282.50,  2707.50],
        [28750.01,  29249.99, 29000, 1305.00,  2755.00],
        [29250.01,  29749.99, 29500, 1327.50,  2802.50],
        [29750.01,  30249.99, 30000, 1350.00,  2850.00],
        [30250.01,  30749.99, 30500, 1372.50,  2897.50],
        [30750.01,  31249.99, 31000, 1395.00,  2945.00],
        [31250.01,  31749.99, 31500, 1417.50,  2992.50],
        [31750.01,  32249.99, 32000, 1440.00,  3040.00],
        [32250.01,  32749.99, 32500, 1462.50,  3087.50],
        [32750.01,  33249.99, 33000, 1485.00,  3135.00],
        [33250.01,  33749.99, 33500, 1507.50,  3182.50],
        [33750.01,  34249.99, 34000, 1530.00,  3230.00],
        [34250.01,  34749.99, 34500, 1552.50,  3277.50],
        [34750.01,  PHP_INT_MAX, 35000, 1575.00, 3325.00],
    ];

    // ════════════════════════════════════════════════════════════
    //  INDIVIDUAL COMPONENT METHODS
    // ════════════════════════════════════════════════════════════

    public static function computeSSS(float $basicSalary): array
    {
        $salary = max(0.0, $basicSalary);

        // SSS uses a Monthly Salary Credit (MSC) bracket — NOT the raw salary.
        // The MSC ceiling is ₱35,000 (SSS Circular 2024-006, effective Jan 2025).
        // Any salary above ₱34,750.01 falls into the maximum bracket (MSC = ₱35,000).
        // Contribution is CAPPED regardless of actual salary — a ₱36k earner and a
        // ₱1M earner both contribute the same SSS amount: ₱35,000 × 4.5% = ₱1,575.
        foreach (self::$sssTable as $row) {
            [$min, $max, $msc, $ee, $er] = $row;
            if ($salary >= $min && $salary <= $max) {
                return ['msc' => $msc, 'employee' => round($ee, 2), 'employer' => round($er, 2), 'total' => round($ee + $er, 2)];
            }
        }
        // Fallback: any salary above the last bracket max → maximum MSC bracket
        $ee = round(self::SSS_MAX_MSC * self::SSS_RATE_EMPLOYEE, 2);
        $er = round(self::SSS_MAX_MSC * self::SSS_RATE_EMPLOYER, 2);
        return ['msc' => self::SSS_MAX_MSC, 'employee' => $ee, 'employer' => $er, 'total' => round($ee + $er, 2)];
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

    /**
     * Compute monthly withholding tax using the TRAIN Law annual bracket method.
     *
     * BIR correct approach (RR 11-2018, RR 13-2023):
     *   Step 1 — Compute monthly taxable income (basic − gov deductions)
     *   Step 2 — Annualize: monthly taxable × 12
     *   Step 3 — Apply annual BIR TRAIN bracket to get annual tax
     *   Step 4 — Divide annual tax by 12 → monthly withholding tax
     *
     * This is the only correct method. The old "monthly bracket" approach
     * (with thresholds like 20,833 / 33,332 / 66,666) was simply the annual
     * bracket divided by 12 pre-applied — but doing it that way introduces
     * rounding errors and doesn't match the official BIR withholding tables.
     */
    private static function applyMonthlyBracket(float $monthlyTaxable): float
    {
        $annualTaxable = $monthlyTaxable * 12;
        $annualTax     = self::computeAnnualTax($annualTaxable);
        return round($annualTax / 12, 2);
    }

    /**
     * Apply the TRAIN Law ANNUAL tax bracket (RR 13-2023, effective Jan 2023 onwards).
     *
     * Annual brackets:
     *   ₱0          – ₱250,000    →  0%
     *   ₱250,001    – ₱400,000    →  15% of excess over ₱250,000
     *   ₱400,001    – ₱800,000    →  ₱22,500  + 20% of excess over ₱400,000
     *   ₱800,001    – ₱2,000,000  →  ₱102,500 + 25% of excess over ₱800,000
     *   ₱2,000,001  – ₱8,000,000  →  ₱402,500 + 30% of excess over ₱2,000,000
     *   Above ₱8,000,000           →  ₱2,402,500 + 35% of excess over ₱8,000,000
     */
    public static function computeAnnualTax(float $annualTaxableIncome): float
    {
        if ($annualTaxableIncome <= 250000.00)   return 0.00;
        if ($annualTaxableIncome <= 400000.00)   return round(0.15  * ($annualTaxableIncome - 250000.00), 2);
        if ($annualTaxableIncome <= 800000.00)   return round(22500.00  + 0.20 * ($annualTaxableIncome - 400000.00), 2);
        if ($annualTaxableIncome <= 2000000.00)  return round(102500.00 + 0.25 * ($annualTaxableIncome - 800000.00), 2);
        if ($annualTaxableIncome <= 8000000.00)  return round(402500.00 + 0.30 * ($annualTaxableIncome - 2000000.00), 2);
        return round(2402500.00 + 0.35 * ($annualTaxableIncome - 8000000.00), 2);
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
        $basicSalary = max(0.0, $basicSalary);
        $allowance   = max(0.0, $allowance);

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