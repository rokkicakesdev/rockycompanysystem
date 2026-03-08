<?php
// core/PhilippineDeductions.php
// 2025 Philippine Statutory Deduction Computations
// Updated: Matches SSS Circular 2024-006, PhilHealth PA2025-0002, Pag-IBIG Circular 460, BIR TRAIN (RR 13-2023)

declare(strict_types=1);

final class PhilippineDeductions
{
    private const SSS_RATE_TOTAL       = 0.15;   // 15% total (5% EE + 10% ER)
    private const SSS_RATE_EMPLOYEE    = 0.05;
    private const SSS_MIN_MSC          = 5000.0;
    private const SSS_MAX_MSC          = 35000.0;

    private const PHILHEALTH_RATE      = 0.05;   // 5%
    private const PHILHEALTH_FLOOR     = 10000.0;
    private const PHILHEALTH_CEILING   = 100000.0;

    private const PAGIBIG_RATE_NORMAL  = 0.02;   // 2%
    private const PAGIBIG_RATE_LOW     = 0.01;   // 1% if <= 1500
    private const PAGIBIG_MFS_CAP      = 10000.0;
    private const PAGIBIG_MAX_PER_SIDE = 200.00;

    // SSS bracket table [min, max, msc, ee_share, er_share]
    // Source: SSS Circular No. 2024-006 — effective January 2025
    // MSC range: ₱5,000 – ₱35,000 | Total rate: 15% (EE: 5%, ER: 10%)
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

    /**
     * Compute SSS contributions (EE + ER)
     */
    public static function computeSSS(float $basicSalary): array
    {
        $salary = max(0.0, $basicSalary);

        foreach (self::$sssTable as $row) {
            [$min, $max, $msc, $ee, $er] = $row;
            if ($salary >= $min && $salary < $max + 0.01) { // slight tolerance for float
                return [
                    'msc'      => $msc,
                    'employee' => round($ee, 2),
                    'employer' => round($er, 2),
                    'total'    => round($ee + $er, 2),
                ];
            }
        }

        // Cap at max
        return [
            'msc'      => self::SSS_MAX_MSC,
            'employee' => 1750.00,
            'employer' => 3500.00,
            'total'    => 5250.00,
        ];
    }

    /**
     * Compute PhilHealth (50/50 split)
     */
    public static function computePhilHealth(float $basicSalary): array
    {
        $mbs = max(self::PHILHEALTH_FLOOR, min($basicSalary, self::PHILHEALTH_CEILING));
        $total = round($mbs * self::PHILHEALTH_RATE, 2);
        $share = round($total / 2, 2);

        return [
            'mbs'      => $mbs,
            'rate'     => self::PHILHEALTH_RATE * 100 . '%',
            'total'    => $total,
            'employee' => $share,
            'employer' => $share,
        ];
    }

    /**
     * Compute Pag-IBIG / HDMF
     */
    public static function computePagIbig(float $basicSalary): array
    {
        $mfs = min(max(0.0, $basicSalary), self::PAGIBIG_MFS_CAP);

        $eeRate = ($basicSalary <= 1500) ? self::PAGIBIG_RATE_LOW : self::PAGIBIG_RATE_NORMAL;

        $ee = min(round($mfs * $eeRate, 2), self::PAGIBIG_MAX_PER_SIDE);
        $er = min(round($mfs * self::PAGIBIG_RATE_NORMAL, 2), self::PAGIBIG_MAX_PER_SIDE);

        return [
            'mfs'          => $mfs,
            'employee_rate'=> ($eeRate * 100) . '%',
            'employee'     => $ee,
            'employer'     => $er,
            'total'        => $ee + $er,
        ];
    }

    /**
     * Compute BIR monthly withholding tax (TRAIN Law brackets)
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
        if ($taxable <= 20833.00) {
            return 0.00;
        }
        if ($taxable <= 33332.99) {
            return 0.00 + 0.15 * ($taxable - 20833.00);
        }
        if ($taxable <= 66666.99) {
            return 2500.00 + 0.20 * ($taxable - 33333.00);
        }
        if ($taxable <= 166666.99) {
            return 10833.00 + 0.25 * ($taxable - 66667.00);
        }
        if ($taxable <= 666666.99) {
            return 40833.00 + 0.30 * ($taxable - 166667.00);
        }
        return 200833.00 + 0.35 * ($taxable - 666667.00);
    }

    /**
     * Compute full deductions for payroll processing
     */
    public static function computeAll(float $basicSalary, float $allowance = 0.0): array
    {
        if ($basicSalary < 0) {
            $basicSalary = 0.0;
        }

        $sss        = self::computeSSS($basicSalary);
        $philhealth = self::computePhilHealth($basicSalary);
        $pagibig    = self::computePagIbig($basicSalary);

        $taxData = self::computeWithholdingTax(
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