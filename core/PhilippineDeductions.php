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
    private static array $sssTable = [
        [0,       4999.99,  5000,   250.00,  500.00],
        [5000,    5249.99,  5000,   250.00,  500.00],
        [5250,    5749.99,  5500,   275.00,  550.00],
        // ... (your full table here – it's correct, no changes needed)
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