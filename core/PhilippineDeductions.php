<?php
// ============================================================
//  PhilippineDeductions.php
//  2025 Philippine Statutory Deduction Computations
//  Based on:
//    - SSS Circular 2024-006 (effective Jan 2025) — 15% rate, MSC ₱5k–₱35k
//    - PhilHealth Advisory PA2025-0002   — 5% rate, floor ₱10k, ceiling ₱100k
//    - Pag-IBIG Circular 460 (eff Feb 2024, still 2025) — 2%, MFS cap ₱10k
//    - BIR TRAIN Law RR 13-2023 / RMC 05-2023 — monthly withholding tax table
//  Place in: core/PhilippineDeductions.php
// ============================================================

class PhilippineDeductions {

    // ════════════════════════════════════════════════════════
    //  SSS — 2025
    //  Rate : 15% of MSC (Employee 5%, Employer 10%)
    //  MSC  : ₱5,000 min — ₱35,000 max
    //  Source: SSS Circular 2024-006
    // ════════════════════════════════════════════════════════

    /**
     * Full SSS contribution table (2025).
     * Each row: [compensation_range_min, compensation_range_max, msc, employee_share, employer_share]
     * EC (Employees' Compensation) is employer-only; not deducted from employee.
     */
    private static array $sssTable = [
        [0,        4999.99,  5000,  250.00,   500.00],
        [5000,     5249.99,  5000,  250.00,   500.00],
        [5250,     5749.99,  5500,  275.00,   550.00],
        [5750,     6249.99,  6000,  300.00,   600.00],
        [6250,     6749.99,  6500,  325.00,   650.00],
        [6750,     7249.99,  7000,  350.00,   700.00],
        [7250,     7749.99,  7500,  375.00,   750.00],
        [7750,     8249.99,  8000,  400.00,   800.00],
        [8250,     8749.99,  8500,  425.00,   850.00],
        [8750,     9249.99,  9000,  450.00,   900.00],
        [9250,     9749.99,  9500,  475.00,   950.00],
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
        [34750,   PHP_INT_MAX, 35000, 1750.00, 3500.00], // max MSC cap
    ];

    /**
     * Returns SSS employee share based on monthly basic salary.
     */
    public static function computeSSS(float $basicSalary): array {
        foreach (self::$sssTable as $row) {
            [$min, $max, $msc, $employee, $employer] = $row;
            if ($basicSalary >= $min && $basicSalary <= $max) {
                return [
                    'msc'           => $msc,
                    'employee'      => $employee,
                    'employer'      => $employer,
                    'total'         => $employee + $employer,
                ];
            }
        }
        // Fallback: max bracket
        return ['msc' => 35000, 'employee' => 1750.00, 'employer' => 3500.00, 'total' => 5250.00];
    }

    // ════════════════════════════════════════════════════════
    //  PhilHealth — 2025
    //  Rate  : 5% of Monthly Basic Salary
    //  Floor : ₱10,000  → min contribution ₱500 total (₱250 employee)
    //  Ceiling: ₱100,000 → max contribution ₱5,000 total (₱2,500 employee)
    //  Split : 50% employee / 50% employer
    //  Source: PhilHealth Advisory PA2025-0002
    // ════════════════════════════════════════════════════════

    public static function computePhilHealth(float $basicSalary): array {
        $mbs = max(10000, min($basicSalary, 100000)); // apply floor & ceiling
        $total    = round($mbs * 0.05, 2);
        $employee = round($total / 2, 2);
        $employer = round($total / 2, 2);
        return [
            'mbs'      => $mbs,
            'rate'     => '5%',
            'total'    => $total,
            'employee' => $employee,
            'employer' => $employer,
        ];
    }

    // ════════════════════════════════════════════════════════
    //  Pag-IBIG (HDMF) — 2025
    //  Rate : 2% employee + 2% employer
    //  MFS  : capped at ₱10,000 → max ₱200 each side
    //  Salary ≤ ₱1,500 → employee pays 1% only
    //  Source: HDMF Circular No. 460 (eff. Feb 2024, still 2025)
    // ════════════════════════════════════════════════════════

    public static function computePagIbig(float $basicSalary): array {
        $mfs = min($basicSalary, 10000); // cap at ₱10,000

        if ($basicSalary <= 1500) {
            $employeeRate = 0.01; // 1% for low-income
        } else {
            $employeeRate = 0.02; // 2%
        }

        $employee = round($mfs * $employeeRate, 2);
        $employer = round($mfs * 0.02, 2);

        // Hard cap: max ₱200 each side
        $employee = min($employee, 200.00);
        $employer = min($employer, 200.00);

        return [
            'mfs'          => $mfs,
            'employee_rate'=> ($employeeRate * 100) . '%',
            'employee'     => $employee,
            'employer'     => $employer,
            'total'        => $employee + $employer,
        ];
    }

    // ════════════════════════════════════════════════════════
    //  BIR Withholding Tax — TRAIN Law (2023 onward, still 2025)
    //  Taxable Income = Basic Salary - SSS(ee) - PhilHealth(ee) - PagIbig(ee)
    //
    //  Monthly Bracket (RR 13-2023 / RMC 05-2023):
    //  ₱0            – ₱20,833       → 0%
    //  ₱20,833.01    – ₱33,332.99    → ₱0      + 15% over ₱20,833
    //  ₱33,333       – ₱66,666.99    → ₱2,500  + 20% over ₱33,333
    //  ₱66,667       – ₱166,666.99   → ₱10,833 + 25% over ₱66,667
    //  ₱166,667      – ₱666,666.99   → ₱40,833 + 30% over ₱166,667
    //  ₱666,667      and above        → ₱200,833 + 35% over ₱666,667
    // ════════════════════════════════════════════════════════

    public static function computeWithholdingTax(
        float $basicSalary,
        float $sssEe,
        float $philhealthEe,
        float $pagibigEe
    ): array {
        // Step 1: Taxable Income
        $totalDeductions = $sssEe + $philhealthEe + $pagibigEe;
        $taxableIncome   = max(0, $basicSalary - $totalDeductions);

        // Step 2: Apply TRAIN monthly bracket
        $tax = self::applyMonthlyBracket($taxableIncome);

        return [
            'taxable_income'   => round($taxableIncome, 2),
            'total_deductions' => round($totalDeductions, 2),
            'withholding_tax'  => round($tax, 2),
        ];
    }

    private static function applyMonthlyBracket(float $taxableIncome): float {
        if ($taxableIncome <= 20833) {
            return 0.00;
        } elseif ($taxableIncome <= 33332.99) {
            return 0 + 0.15 * ($taxableIncome - 20833);
        } elseif ($taxableIncome <= 66666.99) {
            return 2500 + 0.20 * ($taxableIncome - 33333);
        } elseif ($taxableIncome <= 166666.99) {
            return 10833 + 0.25 * ($taxableIncome - 66667);
        } elseif ($taxableIncome <= 666666.99) {
            return 40833 + 0.30 * ($taxableIncome - 166667);
        } else {
            return 200833 + 0.35 * ($taxableIncome - 666667);
        }
    }

    // ════════════════════════════════════════════════════════
    //  MASTER COMPUTE — all deductions for one employee
    //  Pass basic_salary only; everything else is auto-computed.
    // ════════════════════════════════════════════════════════

    public static function computeAll(float $basicSalary, float $allowance = 0): array {
        $sss       = self::computeSSS($basicSalary);
        $philhealth= self::computePhilHealth($basicSalary);
        $pagibig   = self::computePagIbig($basicSalary);

        $withholdingTax = self::computeWithholdingTax(
            $basicSalary,
            $sss['employee'],
            $philhealth['employee'],
            $pagibig['employee']
        );

        $grossPay        = $basicSalary + $allowance;
        $totalDeductions = $sss['employee']
                         + $philhealth['employee']
                         + $pagibig['employee']
                         + $withholdingTax['withholding_tax'];
        $netPay          = $grossPay - $totalDeductions;

        return [
            // Earnings
            'basic_salary'     => round($basicSalary, 2),
            'allowance'        => round($allowance, 2),
            'gross_pay'        => round($grossPay, 2),

            // SSS
            'sss_msc'          => $sss['msc'],
            'sss_ee'           => $sss['employee'],      // employee deduction
            'sss_er'           => $sss['employer'],      // employer share (not deducted)

            // PhilHealth
            'philhealth_mbs'   => $philhealth['mbs'],
            'philhealth_ee'    => $philhealth['employee'],
            'philhealth_er'    => $philhealth['employer'],

            // Pag-IBIG
            'pagibig_mfs'      => $pagibig['mfs'],
            'pagibig_ee'       => $pagibig['employee'],
            'pagibig_er'       => $pagibig['employer'],

            // BIR Withholding Tax
            'taxable_income'   => $withholdingTax['taxable_income'],
            'withholding_tax'  => $withholdingTax['withholding_tax'],

            // Totals
            'total_deductions' => round($totalDeductions, 2),
            'net_pay'          => round($netPay, 2),
        ];
    }
}
