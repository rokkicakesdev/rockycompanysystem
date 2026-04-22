<?php
// tests/PhilippineDeductionsTest.php
// ─────────────────────────────────────────────────────────────────────────────
//  PHPUnit test suite for PhilippineDeductions.php
//
//  Coverage:
//   ✓ SSS — bracket table, floor, ceiling, edge cases (2026: EE 5%, ER 10%)
//   ✓ PhilHealth — floor, ceiling, normal, split (2026: 5%, ceiling ₱100k)
//   ✓ Pag-IBIG — low salary rate, normal rate, cap (2026: MFS ₱10k, max ₱200)
//   ✓ Withholding Tax — TRAIN Law annual bracket (RR 13-2023), all tiers
//   ✓ 1st Cutoff — basic split, fixed amount, 13th month, absences, tax on actual earned
//   ✓ 2nd Cutoff — gov deduction modes, reconciliation, absences, tax on actual earned
//   ✓ Year-End Reconciliation — underpaid, overpaid, exact
//   ✓ computeAll() — legacy full-month method
//   ✓ Edge cases — zero salary, negative input, boundary salaries
//
//  Compliance basis (2026):
//   SSS   — Circular 2024-006 (15% total: EE 5%, ER 10%, MSC ₱5k–₱35k)
//   PH    — PA2025-0002 (5% premium, floor ₱10k, ceiling ₱100k, unchanged 2026)
//   HDMF  — Circular 460 (MFS ₱10k, EE/ER max ₱200 each, eff. Feb 2024)
//   BIR   — TRAIN Law RR 13-2023 (annual bracket, unchanged 2026)
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
    //  SSS — 2026 rates: EE 5%, ER 10%, MSC ₱5,000–₱35,000
    //  Source: SSS Circular 2024-006, effective January 2025, unchanged 2026.
    // =========================================================================

    #[Test]
    #[Group('sss')]
    public function sss_minimum_salary_gets_minimum_bracket(): void
    {
        // Salary 3,000 → below floor → MSC 5,000 → EE 5% = 250.00, ER 10% = 500.00
        $result = PhilippineDeductions::computeSSS(3000.00);
        $this->assertSame(5000,   $result['msc']);
        $this->assertSame(250.00, $result['employee']);
        $this->assertSame(500.00, $result['employer']);
        $this->assertSame(750.00, $result['total']);
    }

    #[Test]
    #[Group('sss')]
    public function sss_zero_salary_gets_minimum_bracket(): void
    {
        // Zero salary → MSC 5,000 → EE = 250.00
        $result = PhilippineDeductions::computeSSS(0.0);
        $this->assertSame(250.00, $result['employee']);
        $this->assertSame(5000,   $result['msc']);
    }

    #[Test]
    #[Group('sss')]
    public function sss_exact_bracket_boundary_5000(): void
    {
        // Salary exactly 5,000 → MSC 5,000 → EE = 5,000 × 5% = 250.00
        $result = PhilippineDeductions::computeSSS(5000.00);
        $this->assertSame(5000,   $result['msc']);
        $this->assertSame(250.00, $result['employee']);
    }

    #[Test]
    #[Group('sss')]
    public function sss_gap_boundary_5250_exactly(): void
    {
        // Salary exactly 5,250 — previously a bracket gap that caused fallthrough
        // to max MSC. Fixed: row 2 upper bound raised to 5,250.00.
        $result = PhilippineDeductions::computeSSS(5250.00);
        $this->assertSame(5000,   $result['msc']);   // still in MSC 5,000 bracket
        $this->assertSame(250.00, $result['employee']);
    }

    #[Test]
    #[Group('sss')]
    public function sss_mid_range_salary_20000(): void
    {
        // 20,000 → bracket [19,750.01–20,249.99] → MSC 20,000 → EE = 20,000 × 5% = 1,000.00
        $result = PhilippineDeductions::computeSSS(20000.00);
        $this->assertSame(20000,   $result['msc']);
        $this->assertSame(1000.00, $result['employee']);
        $this->assertSame(2000.00, $result['employer']);
    }

    #[Test]
    #[Group('sss')]
    public function sss_salary_at_ceiling_35000(): void
    {
        // 35,000 → max MSC bracket → EE = 35,000 × 5% = 1,750.00, ER = 35,000 × 10% = 3,500.00
        $result = PhilippineDeductions::computeSSS(35000.00);
        $this->assertSame(35000,   $result['msc']);
        $this->assertSame(1750.00, $result['employee']);
        $this->assertSame(3500.00, $result['employer']);
    }

    #[Test]
    #[Group('sss')]
    public function sss_salary_above_ceiling_is_capped(): void
    {
        // Any salary above ceiling uses max MSC bracket — EE always 1,750.00
        $result100k = PhilippineDeductions::computeSSS(100000.00);
        $result50k  = PhilippineDeductions::computeSSS(50000.00);

        $this->assertSame(1750.00, $result100k['employee']);
        $this->assertSame(1750.00, $result50k['employee']);
        $this->assertSame($result100k['employee'], $result50k['employee']);
    }

    #[Test]
    #[Group('sss')]
    public function sss_bracket_15000_salary(): void
    {
        // 15,000 → bracket [14,750.01–15,249.99] → MSC 15,000 → EE = 15,000 × 5% = 750.00
        $result = PhilippineDeductions::computeSSS(15000.00);
        $this->assertSame(15000,  $result['msc']);
        $this->assertSame(750.00, $result['employee']);
    }

    // =========================================================================
    //  PhilHealth — 2026: 5% premium, floor ₱10,000, ceiling ₱100,000
    //  Source: PA2025-0002, unchanged for 2026.
    // =========================================================================

    #[Test]
    #[Group('philhealth')]
    public function philhealth_floor_applied_for_low_salary(): void
    {
        // Salary below 10,000 → floor 10,000 → total = 500.00, EE = 250.00
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
        // 25,000 × 5% = 1,250 → EE = 625.00
        $result = PhilippineDeductions::computePhilHealth(25000.00);
        $this->assertSame(25000.0, $result['mbs']);
        $this->assertSame(1250.00, $result['total']);
        $this->assertSame(625.00,  $result['employee']);
    }

    #[Test]
    #[Group('philhealth')]
    public function philhealth_ceiling_applied_for_high_salary(): void
    {
        // Salary above 100,000 → ceiling 100,000 → total = 5,000.00, EE = 2,500.00
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
    //  Pag-IBIG / HDMF — 2026: MFS ₱10,000, EE/ER max ₱200 each
    //  Source: HDMF Circular 460, effective February 2024, unchanged 2026.
    // =========================================================================

    #[Test]
    #[Group('pagibig')]
    public function pagibig_low_salary_uses_1_percent_rate(): void
    {
        // Salary ≤ 1,500 → 1% EE rate → 1,500 × 1% = 15.00
        $result = PhilippineDeductions::computePagIbig(1500.00);
        $this->assertSame('1%',  $result['employee_rate']);
        $this->assertSame(15.00, $result['employee']);
    }

    #[Test]
    #[Group('pagibig')]
    public function pagibig_normal_salary_below_mfs_cap(): void
    {
        // Salary 5,000 > 1,500 → 2% rate → 5,000 × 2% = 100.00 (below ₱200 cap)
        $result = PhilippineDeductions::computePagIbig(5000.00);
        $this->assertSame('2%',   $result['employee_rate']);
        $this->assertSame(100.00, $result['employee']);
    }

    #[Test]
    #[Group('pagibig')]
    public function pagibig_employee_contribution_capped_at_200(): void
    {
        // Salary above MFS ceiling (10,000) → capped at ₱200 EE and ₱200 ER
        // Per HDMF Circular 460: MFS raised to ₱10,000, max per side = ₱200
        $result = PhilippineDeductions::computePagIbig(50000.00);
        $this->assertSame(200.00, $result['employee']);
        $this->assertSame(200.00, $result['employer']);
    }

    #[Test]
    #[Group('pagibig')]
    public function pagibig_mfs_capped_at_10000(): void
    {
        // Per HDMF Circular 460: MFS ceiling raised from ₱5,000 to ₱10,000
        $result = PhilippineDeductions::computePagIbig(20000.00);
        $this->assertSame(10000.0, $result['mfs']);
    }

    #[Test]
    #[Group('pagibig')]
    public function pagibig_salary_exactly_at_mfs_cap(): void
    {
        // Salary exactly 10,000 → MFS = 10,000 → EE = 10,000 × 2% = 200.00 (at cap)
        $result = PhilippineDeductions::computePagIbig(10000.00);
        $this->assertSame(10000.0, $result['mfs']);
        $this->assertSame(200.00,  $result['employee']);
        $this->assertSame(200.00,  $result['employer']);
    }

    #[Test]
    #[Group('pagibig')]
    public function pagibig_zero_salary(): void
    {
        $result = PhilippineDeductions::computePagIbig(0.0);
        $this->assertSame(0.00, $result['employee']);
    }

    // =========================================================================
    //  Withholding Tax — TRAIN Law Annual Bracket (RR 13-2023)
    // =========================================================================

    #[Test]
    #[Group('tax')]
    public function tax_zero_for_income_at_exemption_limit(): void
    {
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
        // Annual 300,000 → 15% of (300,000 − 250,000) = 7,500.00
        $tax = PhilippineDeductions::computeAnnualTax(300000.00);
        $this->assertSame(7500.00, $tax);
    }

    #[Test]
    #[Group('tax')]
    public function tax_second_bracket_20_percent(): void
    {
        // Annual 600,000 → 22,500 + 20% × 200,000 = 62,500.00
        $tax = PhilippineDeductions::computeAnnualTax(600000.00);
        $this->assertSame(62500.00, $tax);
    }

    #[Test]
    #[Group('tax')]
    public function tax_third_bracket_25_percent(): void
    {
        // Annual 1,000,000 → 102,500 + 25% × 200,000 = 152,500.00
        $tax = PhilippineDeductions::computeAnnualTax(1000000.00);
        $this->assertSame(152500.00, $tax);
    }

    #[Test]
    #[Group('tax')]
    public function tax_fourth_bracket_30_percent(): void
    {
        // Annual 3,000,000 → 402,500 + 30% × 1,000,000 = 702,500.00
        $tax = PhilippineDeductions::computeAnnualTax(3000000.00);
        $this->assertSame(702500.00, $tax);
    }

    #[Test]
    #[Group('tax')]
    public function tax_fifth_bracket_35_percent(): void
    {
        // Annual 10,000,000 → 2,402,500 + 35% × 2,000,000 = 3,102,500.00
        $tax = PhilippineDeductions::computeAnnualTax(10000000.00);
        $this->assertSame(3102500.00, $tax);
    }

    #[Test]
    #[Group('tax')]
    public function tax_bracket_boundary_400000_exactly(): void
    {
        // At exactly 400,000 → 15% × 150,000 = 22,500.00
        $tax = PhilippineDeductions::computeAnnualTax(400000.00);
        $this->assertSame(22500.00, $tax);
    }

    #[Test]
    #[Group('tax')]
    public function tax_bracket_boundary_800000_exactly(): void
    {
        // At exactly 800,000 → 22,500 + 20% × 400,000 = 102,500.00
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
        $this->assertSame(15000.00, $result['basic_salary']);
        $this->assertSame(2500.00,  $result['allowance']);
        $this->assertSame(17500.00, $result['gross_pay']);
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
        $result = PhilippineDeductions::computeFirstCutoff(30000.00, 0.0, 12000.00);
        $this->assertSame(12000.00, $result['basic_salary']);
    }

    #[Test]
    #[Group('cutoff1')]
    public function first_cutoff_tax_is_higher_than_second_cutoff_due_to_no_gov_deds(): void
    {
        // The 1st cutoff has NO government deductions in its taxable base (by design:
        // SSS/PhilHealth/Pag-IBIG are collected entirely on the 2nd cutoff).
        // Therefore 1st cutoff taxable = semi-monthly basic (₱15,000).
        //
        // The 2nd cutoff DOES deduct government contributions from its taxable base:
        // taxable = ₱15,000 − SSS EE − PhilHealth EE − Pag-IBIG EE → lower taxable.
        //
        // Both cutoffs use the annualised BIR bracket method (RR 11-2018, RR 13-2023),
        // so the 1st cutoff tax will be HIGHER than the 2nd cutoff tax.
        //
        // This is correct and intentional — the employee pays more tax on the 1st cutoff
        // (when no gov deds have been collected yet) and less on the 2nd cutoff once the
        // gov deductions reduce the taxable base.
        $resultFirst  = PhilippineDeductions::computeFirstCutoff(30000.00, 0.0, null, 'half_monthly');
        $resultSecond = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, null, 'half_monthly');

        // 1st cutoff tax should be GREATER than 2nd cutoff tax
        $this->assertGreaterThan(
            $resultSecond['withholding_tax'],
            $resultFirst['withholding_tax'],
            '1st cutoff tax should exceed 2nd cutoff tax because gov deductions are absent from 1st cutoff taxable base.'
        );

        // Their combined tax should be a reasonable approximation of the full monthly tax
        $combinedTax = round($resultFirst['withholding_tax'] + $resultSecond['withholding_tax'], 2);
        $this->assertGreaterThan(0.0, $combinedTax);
    }

    #[Test]
    #[Group('cutoff1')]
    public function first_cutoff_includes_thirteenth_month_in_gross(): void
    {
        $thirteenth = 25000.00;
        $result = PhilippineDeductions::computeFirstCutoff(30000.00, 0.0, null, 'half_monthly', $thirteenth);
        $this->assertSame(40000.00, $result['gross_pay']); // 15,000 + 25,000
        $this->assertSame($thirteenth, $result['thirteenth_month']);
    }

    #[Test]
    #[Group('cutoff1')]
    public function first_cutoff_absent_deduction_reduces_gross(): void
    {
        // cutoffBasic = 15,000 − 1,363.64 = 13,636.36
        $result = PhilippineDeductions::computeFirstCutoff(30000.00, 0.0, null, 'half_monthly', 0.0, 1363.64);
        $this->assertSame(13636.36, $result['gross_pay']);
        $this->assertSame(1363.64,  $result['absent_deduction']);
    }

    #[Test]
    #[Group('cutoff1')]
    public function first_cutoff_tax_based_on_actual_earned_not_full_basic(): void
    {
        // Employee with 2-day proration: cutoffBasic=15,000, absentDeduction=1,363.64
        // Taxable MUST be 15,000 − 1,363.64 = 13,636.36 (actual earned), not 15,000.
        // Annual taxable = 13,636.36 × 24 = 327,272.64
        // Tax = 15% × (327,272.64 − 250,000) = 15% × 77,272.64 = 11,590.90
        // Semi-monthly = 11,590.90 / 24 = 482.95
        $result = PhilippineDeductions::computeFirstCutoff(30000.00, 0.0, null, 'half_monthly', 0.0, 1363.64);

        $this->assertSame(13636.36, $result['taxable_income']);
        $this->assertSame(482.95,   $result['withholding_tax']);
    }

    #[Test]
    #[Group('cutoff1')]
    public function first_cutoff_no_absent_deduction_taxable_equals_basic(): void
    {
        // When no absent deduction, taxable should equal full cutoffBasic
        $result = PhilippineDeductions::computeFirstCutoff(30000.00, 0.0);
        $this->assertSame(15000.00, $result['taxable_income']);
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
        $result = PhilippineDeductions::computeSecondCutoff(30000.00);
        $this->assertSame(15000.00, $result['basic_salary']);
    }

    #[Test]
    #[Group('cutoff2')]
    public function second_cutoff_basic_accounts_for_fixed_first_cutoff(): void
    {
        $result = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, 12000.00);
        $this->assertSame(18000.00, $result['basic_salary']);
    }

    #[Test]
    #[Group('cutoff2')]
    public function second_cutoff_mode_has_full_gov_deductions(): void
    {
        $result = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, null, 'half_monthly', 'second_cutoff');

        $sss        = PhilippineDeductions::computeSSS(30000.00);
        $philhealth = PhilippineDeductions::computePhilHealth(30000.00);
        $pagibig    = PhilippineDeductions::computePagIbig(30000.00);

        $this->assertSame($sss['employee'],       $result['sss_ee']);
        $this->assertSame($philhealth['employee'], $result['philhealth_ee']);
        $this->assertSame($pagibig['employee'],    $result['pagibig_ee']);
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
    public function second_cutoff_absent_deduction_excluded_from_taxable_base(): void
    {
        // Absent deduction must reduce taxable income — employee is not taxed
        // on income they did not receive.
        $withAbsence    = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, null, 'half_monthly', 'second_cutoff', 1500.00);
        $withoutAbsence = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, null, 'half_monthly', 'second_cutoff', 0.0);

        $this->assertLessThan($withoutAbsence['taxable_income'], $withAbsence['taxable_income']);
        $this->assertLessThan($withoutAbsence['withholding_tax'], $withAbsence['withholding_tax']);
    }

    #[Test]
    #[Group('cutoff2')]
    public function second_cutoff_positive_reconciliation_reduces_net(): void
    {
        $without = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, null, 'half_monthly', 'second_cutoff', 0.0, 0.0);
        $with    = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, null, 'half_monthly', 'second_cutoff', 0.0, 5000.00);

        $this->assertLessThan($without['net_pay'], $with['net_pay']);
        $this->assertSame(5000.00, $with['reconciliation']);
    }

    #[Test]
    #[Group('cutoff2')]
    public function second_cutoff_negative_reconciliation_increases_net(): void
    {
        $without = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, null, 'half_monthly', 'second_cutoff', 0.0, 0.0);
        $with    = PhilippineDeductions::computeSecondCutoff(30000.00, 0.0, null, 'half_monthly', 'second_cutoff', 0.0, -3000.00);

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
    //  Both cutoffs combined — integration sanity checks
    // =========================================================================

    #[Test]
    #[Group('integration')]
    public function both_cutoffs_combined_gov_ded_equals_full_month(): void
    {
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

        $this->assertSame(0.0, $first['sss_ee']);
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

        // ₱0.02 tolerance: semi-monthly method rounds each half independently.
        $this->assertEqualsWithDelta($fullMonthTax, $totalTax, 0.02);
    }

    // =========================================================================
    //  Year-End Reconciliation
    // =========================================================================

    #[Test]
    #[Group('reconciliation')]
    public function reconciliation_positive_when_underpaid(): void
    {
        $result = PhilippineDeductions::computeYearEndReconciliation(600000.00, 30000.00, 10000.00);
        $this->assertGreaterThan(0.0, $result);
    }

    #[Test]
    #[Group('reconciliation')]
    public function reconciliation_negative_when_overpaid(): void
    {
        $result = PhilippineDeductions::computeYearEndReconciliation(300000.00, 20000.00, 50000.00);
        $this->assertLessThan(0.0, $result);
    }

    #[Test]
    #[Group('reconciliation')]
    public function reconciliation_zero_when_exact(): void
    {
        $annualTaxable = 570000.00;
        $correctTax    = PhilippineDeductions::computeAnnualTax($annualTaxable);
        $result = PhilippineDeductions::computeYearEndReconciliation($annualTaxable, 0.0, $correctTax);
        $this->assertSame(0.00, $result);
    }

    #[Test]
    #[Group('reconciliation')]
    public function reconciliation_zero_tax_for_income_below_exemption(): void
    {
        $result = PhilippineDeductions::computeYearEndReconciliation(200000.00, 0.0, 0.0);
        $this->assertSame(0.00, $result);
    }

    #[Test]
    #[Group('reconciliation')]
    /**
     * Guard against double-withholding regression.
     *
     * When the caller correctly includes the December 2nd cutoff's own
     * gov deductions and regular semi-monthly tax in the annual totals
     * (as PayrollService now does), the reconciliation amount must equal
     * exactly the difference between the true annual tax and all tax
     * already paid including that cutoff's regular withholding — i.e. it
     * should be near-zero for a stable-income employee who had no bonuses
     * or income changes throughout the year.
     *
     * Scenario: ₱30,000/month basic, all 24 cutoffs processed, gov deds
     * collected correctly, regular withholding applied each cutoff.
     * Reconciliation should be ₱0.00 (or cents-level rounding only).
     */
    public function reconciliation_is_zero_when_complete_annual_figures_supplied(): void
    {
        $monthlyBasic = 30000.00;
        $sss          = PhilippineDeductions::computeSSS($monthlyBasic);
        $ph           = PhilippineDeductions::computePhilHealth($monthlyBasic);
        $pi           = PhilippineDeductions::computePagIbig($monthlyBasic);
        $monthlyGovEe = $sss['employee'] + $ph['employee'] + $pi['employee'];

        // Annual basic = 12 months × basic (24 semi-monthly cutoffs)
        $annualBasic   = $monthlyBasic * 12;
        $annualGovDeds = $monthlyGovEe * 12;

        // Annual taxable = annual basic - annual gov deds
        $annualTaxable    = max(0.0, $annualBasic - $annualGovDeds);
        $correctAnnualTax = PhilippineDeductions::computeAnnualTax($annualTaxable);

        // Simulate 24 cutoffs of regular withholding (annualised method ÷ 24)
        // For a stable-income employee each cutoff pays: correctAnnualTax / 24
        // But the annualised method computes semi-monthly on semi-monthly taxable:
        //   semi-monthly taxable = (monthlyBasic/2 - monthlyGovEe/2) = annualTaxable/24
        $semiMonthlyTaxable = $annualTaxable / 24;
        $semiMonthlyAnnual  = PhilippineDeductions::computeAnnualTax($semiMonthlyTaxable * 24);
        $semiMonthlyTax     = round($semiMonthlyAnnual / 24, 2);
        $annualTaxPaid      = round($semiMonthlyTax * 24, 2); // 24 cutoffs

        $result = PhilippineDeductions::computeYearEndReconciliation(
            $annualBasic, $annualGovDeds, $annualTaxPaid
        );

        // Should be zero (or at most ₱1.00 due to per-cutoff rounding accumulation)
        $this->assertLessThanOrEqual(1.00, abs($result),
            'Reconciliation should be near-zero when complete annual figures (all 24 cutoffs) are supplied. '
            . "Got: ₱{$result}. A large value indicates double-withholding on December 2nd cutoff."
        );
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
    public function zero_salary_has_zero_gross_and_minimum_deductions(): void
    {
        // Zero salary still attracts minimum SSS (₱250) and PhilHealth (₱250)
        $result = PhilippineDeductions::computeAll(0.0, 0.0);
        $this->assertSame(0.00,   $result['basic_salary']);
        $this->assertSame(0.00,   $result['gross_pay']);
        $this->assertSame(250.00, $result['sss_ee']);       // min SSS bracket: MSC 5,000 × 5%
        $this->assertSame(250.00, $result['philhealth_ee']); // floor 10,000 × 5% / 2
    }

    #[Test]
    #[Group('edge_cases')]
    public function negative_salary_is_treated_as_zero(): void
    {
        $result = PhilippineDeductions::computeAll(-5000.0);
        $this->assertSame(0.00,   $result['basic_salary']);
        $this->assertSame(0.00,   $result['gross_pay']);
        $this->assertSame(250.00, $result['sss_ee']); // minimum SSS still applies
    }

    #[Test]
    #[Group('edge_cases')]
    public function first_cutoff_absent_deduction_cannot_make_gross_negative(): void
    {
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
        $this->assertSame(1750.00, $result['employee']);
    }

    #[Test]
    #[Group('edge_cases')]
    public function minimum_wage_earner_pays_zero_income_tax(): void
    {
        // Monthly ~18,000 → annual ~216,000 → below ₱250,000 exemption
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
    //  Real-world salary scenarios — cross-checked against 2026 BIR/SSS/HDMF tables
    // =========================================================================

    #[Test]
    #[Group('scenarios')]
    public function scenario_minimum_wage_metro_manila(): void
    {
        // Metro Manila daily minimum wage ~645/day × 22 days ≈ ₱14,190/month
        $salary = 14190.00;
        $result = PhilippineDeductions::computeAll($salary);

        // SSS: [13,750.01–14,249.99] → MSC 14,000 → EE = 14,000 × 5% = 700.00
        $this->assertSame(700.00, $result['sss_ee']);

        // PhilHealth: 14,190 × 5% = 709.50 → EE = 354.75
        $this->assertSame(354.75, $result['philhealth_ee']);

        // Pag-IBIG: MFS = min(14,190, 10,000) = 10,000 → 10,000 × 2% = 200.00 (Circular 460)
        $this->assertSame(200.00, $result['pagibig_ee']);

        // Income tax: annual taxable below ₱250,000 → 0
        $this->assertSame(0.00, $result['withholding_tax']);
    }

    #[Test]
    #[Group('scenarios')]
    public function scenario_mid_level_employee_30000(): void
    {
        $salary = 30000.00;
        $result = PhilippineDeductions::computeAll($salary);

        // SSS: [29,750.01–30,249.99] → MSC 30,000 → EE = 30,000 × 5% = 1,500.00
        $this->assertSame(1500.00, $result['sss_ee']);

        // PhilHealth: 30,000 × 5% = 1,500 → EE = 750.00
        $this->assertSame(750.00, $result['philhealth_ee']);

        // Pag-IBIG: MFS = min(30,000, 10,000) = 10,000 → 10,000 × 2% = 200.00 (Circular 460)
        $this->assertSame(200.00, $result['pagibig_ee']);

        $this->assertGreaterThan(0.0, $result['net_pay']);
        $this->assertLessThan($result['gross_pay'], $result['net_pay']);
    }

    #[Test]
    #[Group('scenarios')]
    public function scenario_senior_employee_80000(): void
    {
        $salary = 80000.00;
        $result = PhilippineDeductions::computeAll($salary);

        // SSS: above ceiling → MSC 35,000 → EE = 35,000 × 5% = 1,750.00
        $this->assertSame(1750.00, $result['sss_ee']);

        // PhilHealth: 80,000 × 5% = 4,000 → EE = 2,000.00
        $this->assertSame(2000.00, $result['philhealth_ee']);

        // Pag-IBIG: MFS capped at 10,000 → 10,000 × 2% = 200.00 (Circular 460)
        $this->assertSame(200.00, $result['pagibig_ee']);

        $this->assertGreaterThan(0.0, $result['withholding_tax']);
    }
}