<?php
// tests/PhilippineDeductionsTest.php
// ─────────────────────────────────────────────────────────────────────────────
//  PHPUnit test suite for PhilippineDeductions.php
//
//  Coverage:
//   ✓ SSS — bracket table, floor, ceiling, edge cases
//   ✓ PhilHealth — floor, ceiling, normal, split
//   ✓ Pag-IBIG — low salary rate, normal rate, cap
//   ✓ Withholding Tax — TRAIN Law annual bracket, all tiers
//   ✓ 1st Cutoff — basic split, fixed amount, tax methods, 13th month, absences
//   ✓ 2nd Cutoff — gov deduction modes, reconciliation, absences
//   ✓ Year-End Reconciliation — underpaid, overpaid, exact
//   ✓ computeAll() — legacy full-month method
//   ✓ Edge cases — zero salary, negative input, boundary salaries
//
//  Run with:  composer test
//  or:        ./vendor/bin/phpunit --testdox
// ─────────────────────────────────────────────────────────────────────────────

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

final class PhilippineDeductionsTest extends TestCase
{
    // =========================================================================
    //  SSS
    // =========================================================================

    #[Test]
    #[Group('sss')]
    public function sss_minimum_salary_gets_minimum_bracket(): void
    {
        $result = PhilippineDeductions::computeSSS(3000.00);
        $this->assertSame(5000,   $result['msc']);
        $this->assertSame(225.00, $result['employee']);
        $this->assertSame(475.00, $result['employer']);
        $this->assertSame(700.00, $result['total']);
    }

    #[Test]
    #[Group('sss')]
    public function sss_zero_salary_gets_minimum_bracket(): void
    {
        $result = PhilippineDeductions::computeSSS(0.0);
        $this->assertSame(225.00, $result['employee']);
        $this->assertSame(5000,   $result['msc']);
    }

    #[Test]
    #[Group('sss')]
    public function sss_exact_bracket_boundary_5000(): void
    {
        $result = PhilippineDeductions::computeSSS(5000.00);
        $this->assertSame(5000,   $result['msc']);
        $this->assertSame(225.00, $result['employee']);
    }

    #[Test]
    #[Group('sss')]
    public function sss_mid_range_salary_20000(): void
    {
        // 20,000 falls in [19,750.01 – 20,249.99] → MSC 20,000, EE 900.00
        $result = PhilippineDeductions::computeSSS(20000.00);
        $this->assertSame(20000,  $result['msc']);
        $this->assertSame(900.00, $result['employee']);
        $this->assertSame(1900.00, $result['employer']);
    }

    #[Test]
    #[Group('sss')]
    public function sss_salary_at_ceiling_35000(): void
    {
        $result = PhilippineDeductions::computeSSS(35000.00);
        $this->assertSame(35000,   $result['msc']);
        $this->assertSame(1575.00, $result['employee']);
        $this->assertSame(3325.00, $result['employer']);
    }

    #[Test]
    #[Group('sss')]
    public function sss_salary_above_ceiling_is_capped(): void
    {
        // Any salary above 34,750.01 gets the maximum bracket
        $result100k = PhilippineDeductions::computeSSS(100000.00);
        $result50k  = PhilippineDeductions::computeSSS(50000.00);

        $this->assertSame(1575.00, $result100k['employee']);
        $this->assertSame(1575.00, $result50k['employee']);
        $this->assertSame($result100k['employee'], $result50k['employee']);
    }

    #[Test]
    #[Group('sss')]
    public function sss_bracket_15000_salary(): void
    {
        // 15,000 → [14,750.01 – 15,249.99] → MSC 15,000, EE 675.00
        $result = PhilippineDeductions::computeSSS(15000.00);
        $this->assertSame(15000,  $result['msc']);
        $this->assertSame(675.00, $result['employee']);
    }

    // =========================================================================
    //  PhilHealth
    // =========================================================================

    #[Test]
    #[Group('philhealth')]
    public function philhealth_floor_applied_for_low_salary(): void
    {
        // Salary below 10,000 → uses floor of 10,000 → total = 500, EE = 250
        $result = PhilippineDeductions::computePhilHealth(5000.00);
        $this->assertSame(10000.0, $result['mbs']);
        $this->assertSame(500.00,  $result['total']);
        $this->assertSame(250.00,  $result['employee']);
        $this->assertSame(250.00,  $result['employer']);
    }

    #[Test]
    #[Group('philhealth')]
    public function philhealth_normal_salary_25000(): void
    {
        // 25,000 × 5% = 1,250 → EE = 625
        $result = PhilippineDeductions::computePhilHealth(25000.00);
        $this->assertSame(25000.0, $result['mbs']);
        $this->assertSame(1250.00, $result['total']);
        $this->assertSame(625.00,  $result['employee']);
    }

    #[Test]
    #[Group('philhealth')]
    public function philhealth_ceiling_applied_for_high_salary(): void
    {
        // Salary above 100,000 → capped at 100,000 → total = 5,000, EE = 2,500
        $result = PhilippineDeductions::computePhilHealth(150000.00);
        $this->assertSame(100000.0, $result['mbs']);
        $this->assertSame(5000.00,  $result['total']);
        $this->assertSame(2500.00,  $result['employee']);
    }

    #[Test]
    #[Group('philhealth')]
    public function philhealth_exact_floor_10000(): void
    {
        $result = PhilippineDeductions::computePhilHealth(10000.00);
        $this->assertSame(10000.0, $result['mbs']);
        $this->assertSame(500.00,  $result['total']);
        $this->assertSame(250.00,  $result['employee']);
    }

    #[Test]
    #[Group('philhealth')]
    public function philhealth_exact_ceiling_100000(): void
    {
        $result = PhilippineDeductions::computePhilHealth(100000.00);
        $this->assertSame(100000.0, $result['mbs']);
        $this->assertSame(5000.00,  $result['total']);
        $this->assertSame(2500.00,  $result['employee']);
    }

    #[Test]
    #[Group('philhealth')]
    public function philhealth_employee_and_employer_shares_are_equal(): void
    {
        $result = PhilippineDeductions::computePhilHealth(30000.00);
        $this->assertSame($result['employee'], $result['employer']);
    }

    // =========================================================================
    //  Pag-IBIG
    // =========================================================================

    #[Test]
    #[Group('pagibig')]
    public function pagibig_low_salary_uses_1_percent_rate(): void
    {
        // Salary ≤ 1,500 → 1% rate → EE = 15.00
        $result = PhilippineDeductions::computePagIbig(1500.00);
        $this->assertSame('1%',   $result['employee_rate']);
        $this->assertSame(15.00,  $result['employee']);
    }

    #[Test]
    #[Group('pagibig')]
    public function pagibig_normal_salary_uses_2_percent_rate(): void
    {
        // Salary > 1,500 → 2% rate, capped at 100
        $result = PhilippineDeductions::computePagIbig(5000.00);
        $this->assertSame('2%',   $result['employee_rate']);
        $this->assertSame(100.00, $result['employee']); // 5000 × 2% = 100
    }

    #[Test]
    #[Group('pagibig')]
    public function pagibig_employee_contribution_capped_at_100(): void
    {
        // Any salary above 5,000 → still capped at 100
        $result = PhilippineDeductions::computePagIbig(50000.00);
        $this->assertSame(100.00, $result['employee']);
        $this->assertSame(100.00, $result['employer']);
    }

    #[Test]
    #[Group('pagibig')]
    public function pagibig_mfs_capped_at_5000(): void
    {
        $result = PhilippineDeductions::computePagIbig(20000.00);
        $this->assertSame(5000.0, $result['mfs']);
    }

    #[Test]
    #[Group('pagibig')]
    public function pagibig_zero_salary(): void
    {
        $result = PhilippineDeductions::computePagIbig(0.0);
        $this->assertSame(0.00, $result['employee']);
    }

    // =========================================================================
    //  Withholding Tax — TRAIN Law Annual Bracket
    // =========================================================================

    #[Test]
    #[Group('tax')]
    public function tax_zero_for_income_at_exemption_limit(): void
    {
        // Annual 250,000 → tax = 0. Monthly taxable = 20,833.33
        $tax = PhilippineDeductions::computeAnnualTax(250000.00);
        $this->assertSame(0.00, $tax);
    }

    #[Test]
    #[Group('tax')]
    public function tax_zero_for_income_below_exemption(): void
    {
        $tax = PhilippineDeductions::computeAnnualTax(200000.00);
        $this->assertSame(0.00, $tax);
    }

    #[Test]
    #[Group('tax')]
    public function tax_first_bracket_15_percent(): void
    {
        // Annual 300,000 → 15% of (300,000 − 250,000) = 15% × 50,000 = 7,500
        $tax = PhilippineDeductions::computeAnnualTax(300000.00);
        $this->assertSame(7500.00, $tax);
    }

    #[Test]
    #[Group('tax')]
    public function tax_second_bracket_20_percent(): void
    {
        // Annual 600,000 → 22,500 + 20% × (600,000 − 400,000) = 22,500 + 40,000 = 62,500
        $tax = PhilippineDeductions::computeAnnualTax(600000.00);
        $this->assertSame(62500.00, $tax);
    }

    #[Test]
    #[Group('tax')]
    public function tax_third_bracket_25_percent(): void
    {
        // Annual 1,000,000 → 102,500 + 25% × (1,000,000 − 800,000) = 102,500 + 50,000 = 152,500
        $tax = PhilippineDeductions::computeAnnualTax(1000000.00);
        $this->assertSame(152500.00, $tax);
    }

    #[Test]
    #[Group('tax')]
    public function tax_fourth_bracket_30_percent(): void
    {
        // Annual 3,000,000 → 402,500 + 30% × (3,000,000 − 2,000,000) = 402,500 + 300,000 = 702,500
        $tax = PhilippineDeductions::computeAnnualTax(3000000.00);
        $this->assertSame(702500.00, $tax);
    }

    #[Test]
    #[Group('tax')]
    public function tax_fifth_bracket_35_percent(): void
    {
        // Annual 10,000,000 → 2,402,500 + 35% × (10,000,000 − 8,000,000) = 2,402,500 + 700,000 = 3,102,500
        $tax = PhilippineDeductions::computeAnnualTax(10000000.00);
        $this->assertSame(3102500.00, $tax);
    }

    #[Test]
    #[Group('tax')]
    public function tax_bracket_boundary_400000_exactly(): void
    {
        // At exactly 400,000 → still in 15% bracket → 15% × 150,000 = 22,500
        $tax = PhilippineDeductions::computeAnnualTax(400000.00);
        $this->assertSame(22500.00, $tax);
    }

    #[Test]
    #[Group('tax')]
    public function tax_bracket_boundary_800000_exactly(): void
    {
        // At exactly 800,000 → 22,500 + 20% × 400,000 = 22,500 + 80,000 = 102,500
        $tax = PhilippineDeductions::computeAnnualTax(800000.00);
        $this->assertSame(102500.00, $tax);
    }

    // =========================================================================
    //  1st Cutoff
    // =========================================================================

    #[Test]
    #[Group('cutoff1')]
    public function first_cutoff_basic_split_in_half(): void
    {
        $result = PhilippineDeductions::computeFirstCutoff(30000.00, 5000.00);

        $this->assertSame(15000.00, $result['basic_salary']);  // 30,000 / 2
        $this->assertSame(2500.00,  $result['allowance']);      // 5,000 / 2
        $this->assertSame(17500.00, $result['gross_pay']);      // 15,000 + 2,500
    }

    #[Test]
    #[Group('cutoff1')]
    public function first_cutoff_has_no_gov_deductions(): void
    {
        $result = PhilippineDeductions::computeFirstCutoff(30000.00, 0.0);

        $this->assertSame(0.0, $result['sss_ee']);
        $this->assertSame(0.0, $result['philhealth_ee']);
        $this->assertSame(0.0, $result['pagibig_ee']);
        $this->assertSame(0.0, $result['sss_er']);
        $this->assertSame(0.0, $result['philhealth_er']);
        $this->assertSame(0.0, $result['pagibig_er']);
    }

    #[Test]
    #[Group('cutoff1')]
    public function first_cutoff_fixed_amount_overrides_half_split(): void
    {
        // fixedAmount = 12,000 instead of 30,000/2 = 15,000
        $result = PhilippineDeductions::computeFirstCutoff(30000.00, 0.0, 12000.00);
        $this->assertSame(12000.00, $result['basic_salary']);
    }

    #[Test]
    #[Group('cutoff1')]
    public function first_cutoff_half_monthly_tax_method(): void
    {
        $resultFirst  = PhilippineDeductions::computeFirstCutoff(30000.00, 0.0, null, 'half_monthly');
        $resultSecond = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, null, 'half_monthly');

        // Both cutoffs should have equal withholding tax under half_monthly
        $this->assertSame($resultFirst['withholding_tax'], $resultSecond['withholding_tax']);
    }

    #[Test]
    #[Group('cutoff1')]
    public function first_cutoff_includes_thirteenth_month_in_gross(): void
    {
        $thirteenth = 25000.00;
        $result = PhilippineDeductions::computeFirstCutoff(30000.00, 0.0, null, 'half_monthly', $thirteenth);

        // Gross should include 13th month
        $this->assertSame(40000.00, $result['gross_pay']); // 15,000 + 25,000
        $this->assertSame($thirteenth, $result['thirteenth_month']);
    }

    #[Test]
    #[Group('cutoff1')]
    public function first_cutoff_absent_deduction_reduces_gross(): void
    {
        $result = PhilippineDeductions::computeFirstCutoff(30000.00, 0.0, null, 'half_monthly', 0.0, 1363.64);

        // gross = 15,000 − 1,363.64 = 13,636.36
        $this->assertSame(13636.36, $result['gross_pay']);
        $this->assertSame(1363.64,  $result['absent_deduction']);
    }

    #[Test]
    #[Group('cutoff1')]
    public function first_cutoff_net_pay_equals_gross_minus_tax(): void
    {
        $result = PhilippineDeductions::computeFirstCutoff(30000.00, 0.0);
        $expected = round($result['gross_pay'] - $result['withholding_tax'], 2);
        $this->assertSame($expected, $result['net_pay']);
    }

    #[Test]
    #[Group('cutoff1')]
    public function first_cutoff_cutoff_number_is_1(): void
    {
        $result = PhilippineDeductions::computeFirstCutoff(20000.00);
        $this->assertSame(1, $result['cutoff']);
    }

    // =========================================================================
    //  2nd Cutoff
    // =========================================================================

    #[Test]
    #[Group('cutoff2')]
    public function second_cutoff_basic_is_remainder_after_first(): void
    {
        // No fixed amount: 2nd basic = 30,000 − 15,000 = 15,000
        $result = PhilippineDeductions::computeSecondCutoff(30000.00);
        $this->assertSame(15000.00, $result['basic_salary']);
    }

    #[Test]
    #[Group('cutoff2')]
    public function second_cutoff_basic_accounts_for_fixed_first_cutoff(): void
    {
        // Fixed 1st = 12,000 → 2nd = 30,000 − 12,000 = 18,000
        $result = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, 12000.00);
        $this->assertSame(18000.00, $result['basic_salary']);
    }

    #[Test]
    #[Group('cutoff2')]
    public function second_cutoff_second_cutoff_mode_has_full_gov_deductions(): void
    {
        $result = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, null, 'half_monthly', 'second_cutoff');

        $sss        = PhilippineDeductions::computeSSS(30000.00);
        $philhealth = PhilippineDeductions::computePhilHealth(30000.00);
        $pagibig    = PhilippineDeductions::computePagIbig(30000.00);

        $this->assertSame($sss['employee'],        $result['sss_ee']);
        $this->assertSame($philhealth['employee'],  $result['philhealth_ee']);
        $this->assertSame($pagibig['employee'],     $result['pagibig_ee']);
    }

    #[Test]
    #[Group('cutoff2')]
    public function second_cutoff_split_mode_has_half_gov_deductions(): void
    {
        $result = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, null, 'half_monthly', 'split');

        $sss        = PhilippineDeductions::computeSSS(30000.00);
        $philhealth = PhilippineDeductions::computePhilHealth(30000.00);
        $pagibig    = PhilippineDeductions::computePagIbig(30000.00);

        $this->assertSame(round($sss['employee'] / 2, 2),       $result['sss_ee']);
        $this->assertSame(round($philhealth['employee'] / 2, 2), $result['philhealth_ee']);
        $this->assertSame(round($pagibig['employee'] / 2, 2),    $result['pagibig_ee']);
    }

    #[Test]
    #[Group('cutoff2')]
    public function second_cutoff_positive_reconciliation_reduces_net(): void
    {
        $without = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, null, 'half_monthly', 'second_cutoff', 0.0, 0.0);
        $with    = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, null, 'half_monthly', 'second_cutoff', 0.0, 5000.00);

        // Net should be lower when there is a positive reconciliation (employee owes tax)
        $this->assertLessThan($without['net_pay'], $with['net_pay']);
        $this->assertSame(5000.00, $with['reconciliation']);
    }

    #[Test]
    #[Group('cutoff2')]
    public function second_cutoff_negative_reconciliation_increases_net(): void
    {
        $without = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, null, 'half_monthly', 'second_cutoff', 0.0, 0.0);
        $with    = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, null, 'half_monthly', 'second_cutoff', 0.0, -3000.00);

        // Net should be higher when there is a negative reconciliation (refund)
        $this->assertGreaterThan($without['net_pay'], $with['net_pay']);
    }

    #[Test]
    #[Group('cutoff2')]
    public function second_cutoff_absent_deduction_reduces_gross(): void
    {
        $withoutAbsence = PhilippineDeductions::computeSecondCutoff(30000.00);
        $withAbsence    = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, null, 'half_monthly', 'second_cutoff', 1500.00);

        $this->assertLessThan($withoutAbsence['gross_pay'], $withAbsence['gross_pay']);
        $this->assertSame(1500.00, $withAbsence['absent_deduction']);
    }

    #[Test]
    #[Group('cutoff2')]
    public function second_cutoff_cutoff_number_is_2(): void
    {
        $result = PhilippineDeductions::computeSecondCutoff(20000.00);
        $this->assertSame(2, $result['cutoff']);
    }

    #[Test]
    #[Group('cutoff2')]
    public function second_cutoff_no_thirteenth_month(): void
    {
        $result = PhilippineDeductions::computeSecondCutoff(30000.00);
        $this->assertSame(0.0, $result['thirteenth_month']);
    }

    // =========================================================================
    //  Both cutoffs combined — sanity check that 1st + 2nd = full month
    // =========================================================================

    #[Test]
    #[Group('integration')]
    public function both_cutoffs_combined_gov_ded_equals_full_month(): void
    {
        // Under second_cutoff mode, all gov deductions fall on the 2nd cutoff.
        // SSS/PH/PI on 2nd cutoff should equal full monthly contributions.
        $salary = 30000.00;

        $second = PhilippineDeductions::computeSecondCutoff($salary, 0.0, null, 'half_monthly', 'second_cutoff');
        $sss    = PhilippineDeductions::computeSSS($salary);
        $ph     = PhilippineDeductions::computePhilHealth($salary);
        $pi     = PhilippineDeductions::computePagIbig($salary);

        $this->assertSame($sss['employee'], $second['sss_ee']);
        $this->assertSame($ph['employee'],  $second['philhealth_ee']);
        $this->assertSame($pi['employee'],  $second['pagibig_ee']);
    }

    #[Test]
    #[Group('integration')]
    public function split_mode_both_cutoffs_combined_gov_ded_equals_full_month(): void
    {
        $salary = 30000.00;

        $first  = PhilippineDeductions::computeFirstCutoff($salary);
        $second = PhilippineDeductions::computeSecondCutoff($salary, 0.0, null, 'half_monthly', 'split');

        $sss = PhilippineDeductions::computeSSS($salary);
        $ph  = PhilippineDeductions::computePhilHealth($salary);
        $pi  = PhilippineDeductions::computePagIbig($salary);

        // 1st cutoff has no gov deductions
        $this->assertSame(0.0, $first['sss_ee']);

        // 2nd cutoff split = half
        $this->assertSame(round($sss['employee'] / 2, 2), $second['sss_ee']);
        $this->assertSame(round($ph['employee']  / 2, 2), $second['philhealth_ee']);
        $this->assertSame(round($pi['employee']  / 2, 2), $second['pagibig_ee']);
    }

    #[Test]
    #[Group('integration')]
    public function half_monthly_tax_both_cutoffs_sum_to_full_monthly_tax(): void
    {
        $salary = 40000.00;

        $first  = PhilippineDeductions::computeFirstCutoff($salary,  0.0, null, 'half_monthly');
        $second = PhilippineDeductions::computeSecondCutoff($salary, 0.0, null, 'half_monthly');

        $totalTax     = round($first['withholding_tax'] + $second['withholding_tax'], 2);
        $fullMonthAll = PhilippineDeductions::computeAll($salary);
        $fullMonthTax = $fullMonthAll['withholding_tax'];

        // Combined semi-monthly tax should equal full monthly tax.
        // Tolerance of ₱0.01 is allowed: the half_monthly method divides the
        // full monthly tax by 2 and rounds each half independently, which can
        // produce a 1-cent accumulation vs. the single-pass computeAll() result.
        // This is expected floating-point behaviour, not a calculation error.
        $this->assertEqualsWithDelta($fullMonthTax, $totalTax, 0.01);
    }

    // =========================================================================
    //  Year-End Reconciliation
    // =========================================================================

    #[Test]
    #[Group('reconciliation')]
    public function reconciliation_positive_when_underpaid(): void
    {
        // If annual taxable is high enough, correct tax > what was paid
        $annualBasic   = 600000.00;  // annual basic
        $annualGovDeds = 30000.00;   // gov deductions paid
        $annualTaxPaid = 10000.00;   // tax already withheld (intentionally low)

        $result = PhilippineDeductions::computeYearEndReconciliation(
            $annualBasic, $annualGovDeds, $annualTaxPaid
        );

        // Should be positive — employee owes more tax
        $this->assertGreaterThan(0.0, $result);
    }

    #[Test]
    #[Group('reconciliation')]
    public function reconciliation_negative_when_overpaid(): void
    {
        // If annual taxable is low, correct tax < what was paid
        $annualBasic   = 300000.00;  // annual basic (low income)
        $annualGovDeds = 20000.00;
        $annualTaxPaid = 50000.00;   // overpaid (too much withheld)

        $result = PhilippineDeductions::computeYearEndReconciliation(
            $annualBasic, $annualGovDeds, $annualTaxPaid
        );

        // Should be negative — employee is owed a refund
        $this->assertLessThan(0.0, $result);
    }

    #[Test]
    #[Group('reconciliation')]
    public function reconciliation_zero_when_exact(): void
    {
        // Annual taxable = 570,000 → tax = 22,500 + 20% × 170,000 = 56,500
        $annualTaxable = 570000.00;
        $correctTax    = PhilippineDeductions::computeAnnualTax($annualTaxable);

        $result = PhilippineDeductions::computeYearEndReconciliation(
            $annualTaxable, 0.0, $correctTax
        );

        $this->assertSame(0.00, $result);
    }

    #[Test]
    #[Group('reconciliation')]
    public function reconciliation_zero_tax_for_income_below_exemption(): void
    {
        // Annual taxable below 250,000 → no tax owed
        $result = PhilippineDeductions::computeYearEndReconciliation(
            200000.00, 0.0, 0.0
        );
        $this->assertSame(0.00, $result);
    }

    // =========================================================================
    //  computeAll() — legacy full-month method
    // =========================================================================

    #[Test]
    #[Group('compute_all')]
    public function compute_all_gross_pay_is_basic_plus_allowance(): void
    {
        $result = PhilippineDeductions::computeAll(30000.00, 5000.00);
        $this->assertSame(35000.00, $result['gross_pay']);
    }

    #[Test]
    #[Group('compute_all')]
    public function compute_all_net_pay_equals_gross_minus_total_deductions(): void
    {
        $result = PhilippineDeductions::computeAll(30000.00, 5000.00);
        $expected = round($result['gross_pay'] - $result['total_deductions'], 2);
        $this->assertSame($expected, $result['net_pay']);
    }

    #[Test]
    #[Group('compute_all')]
    public function compute_all_total_deductions_sums_components(): void
    {
        $result = PhilippineDeductions::computeAll(30000.00, 0.0);
        $expected = round(
            $result['sss_ee'] + $result['philhealth_ee'] +
            $result['pagibig_ee'] + $result['withholding_tax'],
            2
        );
        $this->assertSame($expected, $result['total_deductions']);
    }

    #[Test]
    #[Group('compute_all')]
    public function compute_all_has_all_required_keys(): void
    {
        $result = PhilippineDeductions::computeAll(25000.00);
        $required = [
            'basic_salary', 'allowance', 'gross_pay',
            'sss_msc', 'sss_ee', 'sss_er',
            'philhealth_mbs', 'philhealth_ee', 'philhealth_er',
            'pagibig_mfs', 'pagibig_ee', 'pagibig_er',
            'taxable_income', 'withholding_tax',
            'total_deductions', 'net_pay',
        ];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: {$key}");
        }
    }

    // =========================================================================
    //  Edge Cases
    // =========================================================================

    #[Test]
    #[Group('edge_cases')]
    public function zero_salary_produces_zero_net_pay_not_negative(): void
    {
        $result = PhilippineDeductions::computeAll(0.0, 0.0);
        $this->assertGreaterThanOrEqual(0.0, $result['net_pay']);
    }

    #[Test]
    #[Group('edge_cases')]
    public function negative_salary_is_treated_as_zero(): void
    {
        $result = PhilippineDeductions::computeAll(-5000.0);
        $this->assertGreaterThanOrEqual(0.0, $result['net_pay']);
        $this->assertSame(0.00, $result['basic_salary']);
    }

    #[Test]
    #[Group('edge_cases')]
    public function first_cutoff_absent_deduction_cannot_make_gross_negative(): void
    {
        // Absent deduction larger than gross should floor at 0
        $result = PhilippineDeductions::computeFirstCutoff(10000.00, 0.0, null, 'half_monthly', 0.0, 999999.00);
        $this->assertGreaterThanOrEqual(0.0, $result['gross_pay']);
    }

    #[Test]
    #[Group('edge_cases')]
    public function second_cutoff_net_pay_not_negative(): void
    {
        $result = PhilippineDeductions::computeSecondCutoff(10000.00, 0.0, null, 'half_monthly', 'second_cutoff', 0.0, 999999.00);
        $this->assertGreaterThanOrEqual(0.0, $result['net_pay']);
    }

    #[Test]
    #[Group('edge_cases')]
    public function very_high_salary_uses_maximum_sss_bracket(): void
    {
        $result = PhilippineDeductions::computeSSS(1000000.00);
        $this->assertSame(35000,   $result['msc']);
        $this->assertSame(1575.00, $result['employee']);
    }

    #[Test]
    #[Group('edge_cases')]
    public function minimum_wage_earner_pays_zero_income_tax(): void
    {
        // Monthly minimum wage ~18,000 → annual ~216,000 → below 250,000 exemption
        $result = PhilippineDeductions::computeAll(18000.00);
        $this->assertSame(0.00, $result['withholding_tax']);
    }

    #[Test]
    #[Group('edge_cases')]
    public function all_result_values_are_non_negative(): void
    {
        $salaries = [5000.0, 15000.0, 30000.0, 50000.0, 100000.0];
        foreach ($salaries as $salary) {
            $result = PhilippineDeductions::computeAll($salary);
            foreach (['gross_pay', 'net_pay', 'total_deductions', 'withholding_tax',
                      'sss_ee', 'philhealth_ee', 'pagibig_ee'] as $key) {
                $this->assertGreaterThanOrEqual(
                    0.0, $result[$key],
                    "Key '{$key}' was negative for salary {$salary}"
                );
            }
        }
    }

    // =========================================================================
    //  Real-world salary scenarios (cross-check against BIR/SSS tables)
    // =========================================================================

    #[Test]
    #[Group('scenarios')]
    public function scenario_minimum_wage_metro_manila(): void
    {
        // Metro Manila daily minimum wage ~645/day × 22 days = ~14,190/month
        $salary = 14190.00;
        $result = PhilippineDeductions::computeAll($salary);

        // SSS: salary ~14,190 → bracket [13,750.01–14,249.99] → EE = 630.00
        $this->assertSame(630.00, $result['sss_ee']);

        // PhilHealth: 14,190 × 5% = 709.50 → EE = 354.75
        $this->assertSame(354.75, $result['philhealth_ee']);

        // Pag-IBIG: capped at 100
        $this->assertSame(100.00, $result['pagibig_ee']);

        // Annual taxable = 14,190 × 12 − (630 + 354.75 + 100) × 12
        // = 170,280 − 12,897 = 157,383 → below 250,000 → tax = 0
        $this->assertSame(0.00, $result['withholding_tax']);
    }

    #[Test]
    #[Group('scenarios')]
    public function scenario_mid_level_employee_30000(): void
    {
        $salary = 30000.00;
        $result = PhilippineDeductions::computeAll($salary);

        // SSS: [29,750.01–30,249.99] → MSC 30,000 → EE = 1,350.00
        $this->assertSame(1350.00, $result['sss_ee']);

        // PhilHealth: 30,000 × 5% = 1,500 → EE = 750.00
        $this->assertSame(750.00, $result['philhealth_ee']);

        // Pag-IBIG: capped at 100
        $this->assertSame(100.00, $result['pagibig_ee']);

        // Net should be positive and less than gross
        $this->assertGreaterThan(0.0, $result['net_pay']);
        $this->assertLessThan($result['gross_pay'], $result['net_pay']);
    }

    #[Test]
    #[Group('scenarios')]
    public function scenario_senior_employee_80000(): void
    {
        $salary = 80000.00;
        $result = PhilippineDeductions::computeAll($salary);

        // SSS: above ceiling → MSC 35,000 → EE = 1,575.00
        $this->assertSame(1575.00, $result['sss_ee']);

        // PhilHealth: 80,000 × 5% = 4,000 → EE = 2,000.00
        $this->assertSame(2000.00, $result['philhealth_ee']);

        // Pag-IBIG: capped at 100
        $this->assertSame(100.00, $result['pagibig_ee']);

        // Should be paying significant income tax
        $this->assertGreaterThan(0.0, $result['withholding_tax']);
    }
}
