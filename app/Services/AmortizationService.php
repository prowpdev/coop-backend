<?php

declare(strict_types=1);

namespace App\Services;

class AmortizationService
{
    /**
     * Generate complete loan amortization schedule
     */
    public static function generateSchedule(
        float $principal,
        float $annualRate,
        int $termMonths,
        string $method = 'Diminishing Balance',
        string $startDate = 'now',
        string $frequency = 'Monthly'
    ): array {
        $schedule = [];
        $balance = $principal;
        $periodicRate = ($annualRate / 100) / 12;

        $startDateObj = new \DateTime($startDate);

        if ($method === 'Equal Amortization' || $method === 'Annuity') {
            // Equal periodic payment (PMT)
            if ($periodicRate > 0) {
                $pmt = $principal * ($periodicRate * pow(1 + $periodicRate, $termMonths)) / (pow(1 + $periodicRate, $termMonths) - 1);
            } else {
                $pmt = $principal / $termMonths;
            }

            for ($i = 1; $i <= $termMonths; $i++) {
                $dueDate = (clone $startDateObj)->modify("+$i month")->format('Y-m-d');
                $interest = $balance * $periodicRate;
                $principalPayment = $pmt - $interest;

                if ($i === $termMonths || $principalPayment > $balance) {
                    $principalPayment = $balance;
                    $pmt = $principalPayment + $interest;
                    $balance = 0.0;
                } else {
                    $balance -= $principalPayment;
                }

                $schedule[] = [
                    'installment_no'     => $i,
                    'due_date'           => $dueDate,
                    'principal'          => round($principalPayment, 2),
                    'interest'           => round($interest, 2),
                    'total_installment'  => round($pmt, 2),
                    'principal_balance'  => max(0, round($balance, 2)),
                    'status'             => 'Unpaid'
                ];
            }
        } elseif ($method === 'Flat Rate') {
            // Flat interest calculated upfront
            $totalInterest = $principal * ($annualRate / 100) * ($termMonths / 12);
            $monthlyInterest = $totalInterest / $termMonths;
            $monthlyPrincipal = $principal / $termMonths;
            $totalMonthly = $monthlyPrincipal + $monthlyInterest;

            for ($i = 1; $i <= $termMonths; $i++) {
                $dueDate = (clone $startDateObj)->modify("+$i month")->format('Y-m-d');
                $balance -= $monthlyPrincipal;

                $schedule[] = [
                    'installment_no'     => $i,
                    'due_date'           => $dueDate,
                    'principal'          => round($monthlyPrincipal, 2),
                    'interest'           => round($monthlyInterest, 2),
                    'total_installment'  => round($totalMonthly, 2),
                    'principal_balance'  => max(0, round($balance, 2)),
                    'status'             => 'Unpaid'
                ];
            }
        } else {
            // Standard Diminishing Balance (Equal Principal Payments)
            $monthlyPrincipal = $principal / $termMonths;

            for ($i = 1; $i <= $termMonths; $i++) {
                $dueDate = (clone $startDateObj)->modify("+$i month")->format('Y-m-d');
                $interest = $balance * $periodicRate;
                $totalPayment = $monthlyPrincipal + $interest;
                $balance -= $monthlyPrincipal;

                $schedule[] = [
                    'installment_no'     => $i,
                    'due_date'           => $dueDate,
                    'principal'          => round($monthlyPrincipal, 2),
                    'interest'           => round($interest, 2),
                    'total_installment'  => round($totalPayment, 2),
                    'principal_balance'  => max(0, round($balance, 2)),
                    'status'             => 'Unpaid'
                ];
            }
        }

        return $schedule;
    }
}
