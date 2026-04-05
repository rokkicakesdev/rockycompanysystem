<?php
// core/PhilippineDeductions.php
// 2026 Philippine Statutory Deduction Computations
// Updated: Semi-monthly payroll support + 2026 compliance corrections
// Sources: SSS Circular 2024-006 (EE 5% / ER 10%, effective Jan 2025 → unchanged 2026),
//          PhilHealth PA2025-0002 (5% premium rate, ceiling ₱100,000, unchanged 2026),
//          Pag-IBIG HDMF Circular 460 (MFS ₱10,000, EE/ER max ₱200 each, eff. Feb 2024),
//          BIR TRAIN Law (RR 13-2023, annual bracket method, unchanged 2026)

declare(strict_types=1);

final class PhilippineDeductions
{
    // ── SSS ───────────────────────────────────────────────────────
    // 2026 rates per SSS Circular 2024-006 (effective Jan 2025, unchanged 2026):
    // EE = 5.0%, ER = 10.0%, EC = 1.0% (borne by ER) → total ER contribution = 10%
    // MSC ceiling = ₱35,000 — any salary above this uses the max bracket.
    // At ₱35,000 MSC: EE = ₱1,750/month, ER = ₱3,500/month.
    private const SSS_RATE_TOTAL    = 0.15;
    private const SSS_RATE_EMPLOYEE = 0.05;
    private const SSS_RATE_EMPLOYER = 0.10;
    private const SSS_MIN_MSC       = 5000.0;
    private const SSS_MAX_MSC       = 35000.0;

    // ── PhilHealth ────────────────────────────────────────────────
    // 2025: 5% of basic salary, split equally EE/ER (unchanged)
    private const PHILHEALTH_RATE    = 0.05;
    private const PHILHEALTH_FLOOR   = 10000.0;
    private const PHILHEALTH_CEILING = 100000.0;

    // ── Pag-IBIG ──────────────────────────────────────────────────
    // Per HDMF Circular No. 460 (effective February 2024, unchanged 2026):
    //   MFS ceiling raised from ₱5,000 → ₱10,000.
    //   EE rate: 2% for salary > ₱1,500 (max ₱200/mo), 1% for ≤ ₱1,500
    //   ER rate: 2% (max ₱200/mo)
    //   Total max contribution: ₱200 EE + ₱200 ER = ₱400/month.
    private const PAGIBIG_RATE_NORMAL  = 0.02;
    private const PAGIBIG_RATE_LOW     = 0.01;
    private const PAGIBIG_MFS_CAP      = 10000.0;  // raised from ₱5,000 per Circular 460
    private const PAGIBIG_MAX_PER_SIDE = 200.00;   // raised from ₱100 per Circular 460

    // SSS 2026 contribution table — [salary_min, salary_max, msc, ee(5.0%), er(10.0%)]
    // Source: SSS Circular 2024-006, effective January 2025, unchanged for 2026.
    // EE = 5% of MSC, ER = 10% of MSC. Max MSC = ₱35,000 → EE ₱1,750 / ER ₱3,500.
    private static array $sssTable = [
        [        0.00,      4999.99,   5000,   250.00,   500.00],
        [     5000.00,      5250.00,   5000,   250.00,   500.00],  // upper bound raised: closes gap at exactly ₱5,250.00
        [     5250.01,      5749.99,   5500,   275.00,   550.00],
        [     5750.01,      6249.99,   6000,   300.00,   600.00],
        [     6250.01,      6749.99,   6500,   325.00,   650.00],
        [     6750.01,      7249.99,   7000,   350.00,   700.00],
        [     7250.01,      7749.99,   7500,   375.00,   750.00],
        [     7750.01,      8249.99,   8000,   400.00,   800.00],
        [     8250.01,      8749.99,   8500,   425.00,   850.00],
        [     8750.01,      9249.99,   9000,   450.00,   900.00],
        [     9250.01,      9749.99,   9500,   475.00,   950.00],
        [     9750.01,     10249.99,  10000,   500.00,  1000.00],
        [    10250.01,     10749.99,  10500,   525.00,  1050.00],
        [    10750.01,     11249.99,  11000,   550.00,  1100.00],
        [    11250.01,     11749.99,  11500,   575.00,  1150.00],
        [    11750.01,     12249.99,  12000,   600.00,  1200.00],
        [    12250.01,     12749.99,  12500,   625.00,  1250.00],
        [    12750.01,     13249.99,  13000,   650.00,  1300.00],
        [    13250.01,     13749.99,  13500,   675.00,  1350.00],
        [    13750.01,     14249.99,  14000,   700.00,  1400.00],
        [    14250.01,     14749.99,  14500,   725.00,  1450.00],
        [    14750.01,     15249.99,  15000,   750.00,  1500.00],
        [    15250.01,     15749.99,  15500,   775.00,  1550.00],
        [    15750.01,     16249.99,  16000,   800.00,  1600.00],
        [    16250.01,     16749.99,  16500,   825.00,  1650.00],
        [    16750.01,     17249.99,  17000,   850.00,  1700.00],
        [    17250.01,     17749.99,  17500,   875.00,  1750.00],
        [    17750.01,     18249.99,  18000,   900.00,  1800.00],
        [    18250.01,     18749.99,  18500,   925.00,  1850.00],
        [    18750.01,     19249.99,  19000,   950.00,  1900.00],
        [    19250.01,     19749.99,  19500,   975.00,  1950.00],
        [    19750.01,     20249.99,  20000,  1000.00,  2000.00],
        [    20250.01,     20749.99,  20500,  1025.00,  2050.00],
        [    20750.01,     21249.99,  21000,  1050.00,  2100.00],
        [    21250.01,     21749.99,  21500,  1075.00,  2150.00],
        [    21750.01,     22249.99,  22000,  1100.00,  2200.00],
        [    22250.01,     22749.99,  22500,  1125.00,  2250.00],
        [    22750.01,     23249.99,  23000,  1150.00,  2300.00],
        [    23250.01,     23749.99,  23500,  1175.00,  2350.00],
        [    23750.01,     24249.99,  24000,  1200.00,  2400.00],
        [    24250.01,     24749.99,  24500,  1225.00,  2450.00],
        [    24750.01,     25249.99,  25000,  1250.00,  2500.00],
        [    25250.01,     25749.99,  25500,  1275.00,  2550.00],
        [    25750.01,     26249.99,  26000,  1300.00,  2600.00],
        [    26250.01,     26749.99,  26500,  1325.00,  2650.00],
        [    26750.01,     27249.99,  27000,  1350.00,  2700.00],
        [    27250.01,     27749.99,  27500,  1375.00,  2750.00],
        [    27750.01,     28249.99,  28000,  1400.00,  2800.00],
        [    28250.01,     28749.99,  28500,  1425.00,  2850.00],
        [    28750.01,     29249.99,  29000,  1450.00,  2900.00],
        [    29250.01,     29749.99,  29500,  1475.00,  2950.00],
        [    29750.01,     30249.99,  30000,  1500.00,  3000.00],
        [    30250.01,     30749.99,  30500,  1525.00,  3050.00],
        [    30750.01,     31249.99,  31000,  1550.00,  3100.00],
        [    31250.01,     31749.99,  31500,  1575.00,  3150.00],
        [    31750.01,     32249.99,  32000,  1600.00,  3200.00],
        [    32250.01,     32749.99,  32500,  1625.00,  3250.00],
        [    32750.01,     33249.99,  33000,  1650.00,  3300.00],
        [    33250.01,     33749.99,  33500,  1675.00,  3350.00],
        [    33750.01,     34249.99,  34000,  1700.00,  3400.00],
        [    34250.01,     34749.99,  34500,  1725.00,  3450.00],
        [    34750.01,  PHP_INT_MAX,  35000,  1750.00,  3500.00],
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
     * Compute monthly withholding tax (annualised / 12).
     * Used ONLY by the legacy computeAll() and computeWithholdingTax() methods.
     * Semi-monthly cutoff methods use computeAnnualTax() directly and divide by 24.
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
    //  OVERTIME & HOLIDAY PREMIUM PAY  (PD 442 Labor Code)
    // ════════════════════════════════════════════════════════════

    /**
     * Compute overtime pay for a cutoff period.
     *
     * Per Art. 87, Labor Code of the Philippines:
     *   Regular day OT   = hourly rate × 1.25 × OT hours
     *   Holiday day OT   = hourly rate × 1.30 × OT hours (on top of holiday premium)
     *
     * @param float $cutoffBasicAmount  Basic salary for this cutoff (cutoffBasic)
     * @param int   $scheduledDays      Total scheduled work days in this cutoff
     * @param int   $workHoursPerDay    Standard daily work hours (default 8)
     * @param float $otHoursRegularDay  Overtime hours on regular working days
     * @param float $otHoursOnHoliday   Overtime hours on holiday days
     */
    public static function computeOvertimePay(
        float $cutoffBasicAmount,
        int   $scheduledDays,
        int   $workHoursPerDay    = 8,
        float $otHoursRegularDay  = 0.0,
        float $otHoursOnHoliday   = 0.0
    ): float {
        if ($scheduledDays <= 0 || $workHoursPerDay <= 0) return 0.0;
        if ($otHoursRegularDay <= 0.0 && $otHoursOnHoliday <= 0.0) return 0.0;

        $dailyRate  = $cutoffBasicAmount / $scheduledDays;
        $hourlyRate = $dailyRate / $workHoursPerDay;

        // Regular day OT: hourly rate × 1.25
        $otRegular = round($otHoursRegularDay * $hourlyRate * 1.25, 2);

        // Holiday OT: hourly rate × 1.30 (the additional 30% above the regular hourly rate
        // that applies when overtime is rendered on a holiday)
        $otHoliday = round($otHoursOnHoliday * $hourlyRate * 1.30, 2);

        return round($otRegular + $otHoliday, 2);
    }

    /**
     * Compute holiday premium pay for days an employee WORKED on a holiday.
     *
     * Per PD 442 Labor Code and DOLE advisories:
     *   Regular Holiday worked    = 200% of daily rate (employee gets DOUBLE pay)
     *     The base 100% is already in gross_pay as a normal worked day.
     *     Premium = additional 100% of daily rate.
     *   Special Non-Working worked = 130% of daily rate.
     *     The base 100% is in gross_pay.
     *     Premium = additional 30% of daily rate.
     *
     * NOTE: Regular Holidays NOT worked are already paid at 100% (no deduction applied).
     * This method only computes the ADDITIONAL premium for WORKED holidays.
     *
     * @param float $cutoffBasicAmount          Basic salary for this cutoff
     * @param int   $scheduledDays              Scheduled work days in cutoff
     * @param int   $workedRegularHolidayDays   Days worked on Regular Holidays
     * @param int   $workedSpecialHolidayDays   Days worked on Special Non-Working Holidays
     */
    public static function computeHolidayPremiumPay(
        float $cutoffBasicAmount,
        int   $scheduledDays,
        int   $workedRegularHolidayDays  = 0,
        int   $workedSpecialHolidayDays  = 0
    ): float {
        if ($scheduledDays <= 0) return 0.0;
        if ($workedRegularHolidayDays <= 0 && $workedSpecialHolidayDays <= 0) return 0.0;

        $dailyRate = $cutoffBasicAmount / $scheduledDays;

        // Regular Holiday premium = +100% of daily rate per worked day (total 200%)
        $regularPremium = round($workedRegularHolidayDays * $dailyRate * 1.00, 2);

        // Special Non-Working Holiday premium = +30% of daily rate per worked day (total 130%)
        $specialPremium = round($workedSpecialHolidayDays * $dailyRate * 0.30, 2);

        return round($regularPremium + $specialPremium, 2);
    }

    // ════════════════════════════════════════════════════════════
    //  SEMI-MONTHLY CUTOFF METHODS
    // ════════════════════════════════════════════════════════════

    /**
     * Compute 1ST CUTOFF payroll (1–15th of the month).
     *
     * Rules:
     * - Earnings  = cutoff1_amount (fixed override) OR basic_salary/2, plus allowance/2
     * - Gov deductions = ALL ZERO on 1st cutoff (collected entirely on 2nd cutoff)
     * - Withholding tax = annualised computation on semi-monthly gross (no gov deds),
     *   divided by 24. This is the BIR-correct method: there are no government
     *   deductions to subtract from the 1st cutoff taxable base because SSS,
     *   PhilHealth and Pag-IBIG have not yet been collected this month.
     * - 13th month = added to gross/net on December 1st cutoff only
     *
     * @param float       $basicSalary   Full monthly basic salary
     * @param float       $allowance     Full monthly allowance
     * @param float|null  $fixedAmount   If set, overrides basic/2 for earnings split
     * @param string      $taxMethod     Reserved for future use — always uses annualised method
     * @param float       $thirteenth    13th month amount to include (0 if not December)
     * @param float       $absentDeduction Deduction for absences
     */
    public static function computeFirstCutoff(
        float  $basicSalary,
        float  $allowance       = 0.0,
        ?float $fixedAmount     = null,
        string $taxMethod       = 'half_monthly',
        float  $thirteenth      = 0.0,
        float  $absentDeduction = 0.0,
        float  $extraEarnings   = 0.0   // OT pay + holiday premium pay (taxable)
    ): array {
        $basicSalary = max(0.0, $basicSalary);
        $allowance   = max(0.0, $allowance);

        // Determine 1st cutoff earnings
        $cutoffBasic     = $fixedAmount !== null ? max(0.0, $fixedAmount) : round($basicSalary / 2, 2);
        $cutoffAllowance = round($allowance / 2, 2);

        // Gross before absences (includes OT pay and holiday premium as extra taxable earnings)
        $extraEarnings   = max(0.0, $extraEarnings);
        $grossPay = round($cutoffBasic + $cutoffAllowance + $thirteenth + $extraEarnings, 2);

        // Apply absent deduction
        $absentDeduction = max(0.0, $absentDeduction);
        $grossPay        = round(max(0.0, $grossPay - $absentDeduction), 2);

        // ── Withholding tax — BIR annualised method, NO gov deductions ──────────
        // On the 1st cutoff, SSS/PhilHealth/Pag-IBIG have not been collected yet,
        // so the taxable base is the semi-monthly basic actually earned (after
        // absent/proration deduction). Taxing the full cutoffBasic when the employee
        // did not work all scheduled days over-withholds tax on income never received.
        //
        // Correct steps per BIR RR 11-2018 / RR 13-2023:
        //   1. Taxable = cutoffBasic − absentDeduction (allowance excluded from tax base)
        //   2. Annualise: taxable × 24
        //   3. Apply annual TRAIN bracket → annual tax
        //   4. Semi-monthly tax = annual tax ÷ 24
        // OT pay and holiday premium are fully taxable under TRAIN Law (RR 13-2023).
        $taxableIncome  = max(0.0, $cutoffBasic - $absentDeduction + $extraEarnings);
        $annualTaxable  = $taxableIncome * 24;
        $annualTax      = self::computeAnnualTax($annualTaxable);
        $withholdingTax = round($annualTax / 24, 2);

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
            'extra_earnings'   => round($extraEarnings, 2),
            'total_deductions' => $totalDeductions,
            'net_pay'          => $netPay,

            // Meta
            'cutoff'           => 1,
            'tax_method'       => 'annualised',
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
        float  $reconciliation   = 0.0,
        float  $extraEarnings    = 0.0   // OT pay + holiday premium pay (taxable)
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

        // ── Withholding tax — BIR annualised method ──────────────────────────────
        // Taxable base = semi-monthly basic actually earned, minus gov deductions.
        // Absent/proration deduction is subtracted FIRST because the employee should
        // not pay tax on income they did not receive (e.g. days before hire date).
        // Annualise × 24, apply annual TRAIN bracket, divide by 24.
        // OT pay and holiday premium are fully taxable under TRAIN Law (RR 13-2023).
        $taxableIncome  = max(0.0, $cutoffBasic - $absentDeduction + $extraEarnings - $sssEe - $phEe - $piEe);
        $annualTaxable  = $taxableIncome * 24;
        $annualTax      = self::computeAnnualTax($annualTaxable);
        $withholdingTax = round($annualTax / 24, 2);

        // Year-end reconciliation: add any shortfall (or refund excess) to December 2nd cutoff
        // Positive reconciliation = extra tax owed by employee (deducted)
        // Negative reconciliation = overpayment refunded (added to net)
        $reconciliation = round($reconciliation, 2);

        // Gross and totals (extraEarnings = OT pay + holiday premium, fully taxable)
        $extraEarnings   = max(0.0, $extraEarnings);
        $grossPay  = round(max(0.0, $cutoffBasic + $cutoffAllowance + $extraEarnings - $absentDeduction), 2);
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
            'extra_earnings'   => round($extraEarnings, 2),
            'total_deductions' => $totalDeductions,
            'net_pay'          => $netPay,

            // Meta
            'cutoff'           => 2,
            'tax_method'       => 'annualised',
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