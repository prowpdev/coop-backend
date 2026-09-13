<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ReportRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Compute Real-time CDA-compliant Trial Balance
     */
    public function getTrialBalance(?string $asOfDate = null, ?string $branchId = null): array
    {
        $asOfDate = $asOfDate ?? date('Y-m-d');

        $sql = "
            SELECT coa.id AS account_id,
                   coa.account_code,
                   coa.name AS account_name,
                   coa.category,
                   coa.normal_balance,
                   COALESCE(SUM(jl.debit), 0) AS total_debit,
                   COALESCE(SUM(jl.credit), 0) AS total_credit
            FROM chart_of_accounts coa
            LEFT JOIN journal_lines jl ON coa.id = jl.account_id
            LEFT JOIN journal_entries je ON jl.journal_entry_id = je.id
                AND je.status = 'Posted'
                AND je.posting_date <= :as_of_date
        ";

        $params = ['as_of_date' => $asOfDate];

        if ($branchId && $branchId !== 'all') {
            $sql .= " AND je.branch_id = :branch_id";
            $params['branch_id'] = $branchId;
        }

        $sql .= " GROUP BY coa.id, coa.account_code, coa.name, coa.category, coa.normal_balance ORDER BY coa.account_code ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalDebits = 0.0;
        $totalCredits = 0.0;
        $accounts = [];

        foreach ($rows as $row) {
            $debit = (float)$row['total_debit'];
            $credit = (float)$row['total_credit'];

            $netBalance = ($row['normal_balance'] === 'Debit')
                ? ($debit - $credit)
                : ($credit - $debit);

            $debitBalance = ($netBalance > 0 && $row['normal_balance'] === 'Debit') ? $netBalance : 0.0;
            $creditBalance = ($netBalance > 0 && $row['normal_balance'] === 'Credit') ? $netBalance : 0.0;

            if ($netBalance < 0) {
                if ($row['normal_balance'] === 'Debit') {
                    $creditBalance = abs($netBalance);
                } else {
                    $debitBalance = abs($netBalance);
                }
            }

            $totalDebits += $debitBalance;
            $totalCredits += $creditBalance;

            $accounts[] = [
                'account_id'     => $row['account_id'],
                'account_code'   => $row['account_code'],
                'account_name'   => $row['account_name'],
                'category'       => $row['category'],
                'normal_balance' => $row['normal_balance'],
                'debit'          => round($debitBalance, 2),
                'credit'         => round($creditBalance, 2),
                'net_balance'    => round($netBalance, 2)
            ];
        }

        return [
            'as_of_date'      => $asOfDate,
            'accounts'        => $accounts,
            'total_debit'     => round($totalDebits, 2),
            'total_credit'    => round($totalCredits, 2),
            'is_balanced'     => abs($totalDebits - $totalCredits) < 0.01,
            'variance'        => round($totalDebits - $totalCredits, 2)
        ];
    }

    /**
     * Compute Financial Statements (Balance Sheet & Income Statement)
     */
    public function getFinancialStatements(?string $asOfDate = null, ?string $branchId = null): array
    {
        $tb = $this->getTrialBalance($asOfDate, $branchId);

        $assets = [];
        $liabilities = [];
        $equity = [];
        $revenue = [];
        $expense = [];

        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;
        $totalRevenue = 0.0;
        $totalExpense = 0.0;

        foreach ($tb['accounts'] as $acc) {
            $cat = $acc['category'];
            $bal = $acc['net_balance'];

            switch ($cat) {
                case 'Asset':
                    $assets[] = $acc;
                    $totalAssets += $bal;
                    break;
                case 'Liability':
                    $liabilities[] = $acc;
                    $totalLiabilities += $bal;
                    break;
                case 'Equity':
                    $equity[] = $acc;
                    $totalEquity += $bal;
                    break;
                case 'Revenue':
                    $revenue[] = $acc;
                    $totalRevenue += $bal;
                    break;
                case 'Expense':
                    $expense[] = $acc;
                    $totalExpense += $bal;
                    break;
            }
        }

        $netSurplus = $totalRevenue - $totalExpense;

        return [
            'balance_sheet' => [
                'as_of_date'        => $asOfDate ?? date('Y-m-d'),
                'assets'            => $assets,
                'total_assets'      => round($totalAssets, 2),
                'liabilities'       => $liabilities,
                'total_liabilities' => round($totalLiabilities, 2),
                'equity'            => $equity,
                'total_equity'      => round($totalEquity, 2),
                'net_surplus'       => round($netSurplus, 2),
                'balanced'          => abs($totalAssets - ($totalLiabilities + $totalEquity + $netSurplus)) < 0.01
            ],
            'income_statement' => [
                'revenue'       => $revenue,
                'total_revenue' => round($totalRevenue, 2),
                'expenses'      => $expense,
                'total_expense' => round($totalExpense, 2),
                'net_surplus'   => round($netSurplus, 2)
            ]
        ];
    }

    /**
     * Dashboard operational & portfolio statistics
     */
    public function getDashboardStats(): array
    {
        $membersCount = (int)$this->db->query("SELECT COUNT(*) FROM members WHERE status = 'Active'")->fetchColumn();
        $totalMembers = (int)$this->db->query("SELECT COUNT(*) FROM members")->fetchColumn();

        $loansActive = (int)$this->db->query("SELECT COUNT(*) FROM loans WHERE status = 'Active'")->fetchColumn();
        $loanPortfolio = (float)$this->db->query("SELECT COALESCE(SUM(current_balance), 0) FROM loans WHERE status = 'Active'")->fetchColumn();

        $savingsTotal = (float)$this->db->query("SELECT COALESCE(SUM(balance), 0) FROM savings_accounts WHERE status = 'Active'")->fetchColumn();
        $savingsAccounts = (int)$this->db->query("SELECT COUNT(*) FROM savings_accounts WHERE status = 'Active'")->fetchColumn();

        $shareCapitalTotal = (float)$this->db->query("SELECT COALESCE(SUM(paid_up_amount), 0) FROM share_capital_accounts WHERE status = 'Active'")->fetchColumn();

        $cashVaultTotal = (float)$this->db->query("SELECT COALESCE(SUM(current_balance), 0) FROM cash_accounts")->fetchColumn();

        return [
            'active_members'        => $membersCount,
            'total_members'         => $totalMembers,
            'active_loans_count'    => $loansActive,
            'loan_portfolio_total'  => round($loanPortfolio, 2),
            'savings_total'         => round($savingsTotal, 2),
            'savings_accounts_count'=> $savingsAccounts,
            'share_capital_total'   => round($shareCapitalTotal, 2),
            'cash_liquidity_total'  => round($cashVaultTotal, 2),
            'system_status'         => 'Healthy - Real-time Connected'
        ];
    }
}
