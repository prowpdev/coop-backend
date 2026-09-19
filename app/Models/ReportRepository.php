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
/**
 * Compute Real-time CDA-compliant Trial Balance
 * from posted General Ledger journal lines.
 */
public function getTrialBalance(
    ?string $asOfDate = null,
    ?string $branchId = null
): array {
    $asOfDate = $asOfDate ?? date('Y-m-d');

    /**
     * Get all Chart of Accounts.
     */
    $coaSql = "
        SELECT
            id,
            account_code,
            name,
            category,
            normal_balance
        FROM chart_of_accounts
        ORDER BY account_code ASC
    ";

    $coaStmt = $this->db->prepare($coaSql);
    $coaStmt->execute();

    $accounts = $coaStmt->fetchAll(PDO::FETCH_ASSOC);

    /**
     * Get journal lines belonging to:
     * - Posted journal entries only
     * - Posting date <= selected date
     * - Optional branch
     */
    $sql = "
        SELECT
            jl.account_id,
            COALESCE(SUM(jl.debit), 0) AS total_debit,
            COALESCE(SUM(jl.credit), 0) AS total_credit
        FROM journal_lines jl
        INNER JOIN journal_entries je
            ON je.id = jl.journal_entry_id
        WHERE je.status = 'Posted'
          AND je.posting_date <= :as_of_date
    ";

    $params = [
        'as_of_date' => $asOfDate
    ];

    if ($branchId !== null && $branchId !== '' && $branchId !== 'all') {
        $sql .= " AND je.branch_id = :branch_id";
        $params['branch_id'] = $branchId;
    }

    $sql .= "
        GROUP BY jl.account_id
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    $journalBalances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /**
     * Create quick lookup:
     *
     * account_id => debit / credit
     */
    $balanceMap = [];

    foreach ($journalBalances as $row) {
        $accountId = (string) $row['account_id'];

        $balanceMap[$accountId] = [
            'debit' => (float) $row['total_debit'],
            'credit' => (float) $row['total_credit']
        ];
    }

    /**
     * Build Trial Balance.
     */
    $balances = [];

    foreach ($accounts as $account) {
        $accountId = (string) $account['id'];

        $totalDebit = $balanceMap[$accountId]['debit'] ?? 0.0;
        $totalCredit = $balanceMap[$accountId]['credit'] ?? 0.0;

        /**
         * Ignore accounts with no GL activity.
         *
         * This matches:
         *
         * if (totalDebit === 0 && totalCredit === 0) continue;
         */
        if (
            abs($totalDebit) < 0.000001 &&
            abs($totalCredit) < 0.000001
        ) {
            continue;
        }

        $normalBalance = $account['normal_balance'] ?? 'Debit';

        $netDebit = 0.0;
        $netCredit = 0.0;

        /**
         * Calculate the net balance according to
         * the account's normal balance.
         */
        if ($normalBalance === 'Debit') {
            $net = $totalDebit - $totalCredit;

            if ($net >= 0) {
                $netDebit = $net;
            } else {
                $netCredit = abs($net);
            }
        } else {
            $net = $totalCredit - $totalDebit;

            if ($net >= 0) {
                $netCredit = $net;
            } else {
                $netDebit = abs($net);
            }
        }

        $balances[] = [
            'id' => $accountId,

            'code' => $account['account_code'] ?? '',

            'name' => $account['name'] ?? '',

            'type' => $account['category'] ?? 'Asset',

            'category' => $account['category'] ?? 'Asset',

            'normal_balance' => $normalBalance,

            // Net Trial Balance amount
            'debit' => round($netDebit, 2),

            'credit' => round($netCredit, 2),

            // Original GL movement
            'gross_debit' => round($totalDebit, 2),

            'gross_credit' => round($totalCredit, 2),
        ];
    }

    /**
     * Sort by account code.
     */
    usort(
        $balances,
        function (array $a, array $b): int {
            return strnatcasecmp(
                (string) $a['code'],
                (string) $b['code']
            );
        }
    );

    /**
     * Calculate Trial Balance totals.
     *
     * IMPORTANT:
     * These use NET debit/credit values, not gross values.
     */
    $totalDebit = 0.0;
    $totalCredit = 0.0;

    foreach ($balances as $balance) {
        $totalDebit += (float) $balance['debit'];
        $totalCredit += (float) $balance['credit'];
    }

    $totalDebit = round($totalDebit, 2);
    $totalCredit = round($totalCredit, 2);

    $variance = round(
        $totalDebit - $totalCredit,
        2
    );

    $isBalanced = abs($variance) < 0.01;

    return [
        'balances' => $balances,

        // Backwards compatibility
        'accounts' => $balances,

        'total_debit' => $totalDebit,

        'total_credit' => $totalCredit,

        'variance' => $variance,

        'difference' => $variance,

        'is_balanced' => $isBalanced
    ];
}
    /**
     * Format the trial balance data for reporting purposes
     */
    private function formatTrialBalanceResponse(array $data): array
    {
        $totalDebit = (float) ($data['total_debit'] ?? 0);
        $totalCredit = (float) ($data['total_credit'] ?? 0);

        $variance = array_key_exists('variance', $data)
            ? (float) $data['variance']
            : round($totalDebit - $totalCredit, 2);

        $difference = array_key_exists('difference', $data)
            ? (float) $data['difference']
            : round($totalDebit - $totalCredit, 2);

        return [
            'as_of_date' => $data['as_of_date'] ?? null,

            // Frontend expects balances
            'balances' => $data['accounts'] ?? [],

            // Keep accounts for backward compatibility
            'accounts' => $data['accounts'] ?? [],

            'total_debit' => round($totalDebit, 2),

            'total_credit' => round($totalCredit, 2),

            'variance' => round($variance, 2),

            'difference' => round($difference, 2),

            'is_balanced' => $data['is_balanced']
                ?? (abs($totalDebit - $totalCredit) < 0.01),
        ];
    }

    /**
     * Compute Financial Statements
     * Compatible with TypeScript getFinancialReport()
     */
public function getFinancialStatements(
    ?string $asOfDate = null,
    ?string $branchId = null
): array {
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
        $bal = (float) $acc['net_balance'];

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

    $totalLiabilitiesAndEquity =
        $totalLiabilities + $totalEquity + $netSurplus;

    return [
        'as_of_date' => $asOfDate ?? date('Y-m-d'),

        // Matches TypeScript: res.data.statement_of_financial_position
        'statement_of_financial_position' => [
            'categories' => [
                [
                    'category' => 'Asset',
                    'accounts' => $assets
                ],
                [
                    'category' => 'Liability',
                    'accounts' => $liabilities
                ],
                [
                    'category' => 'Equity',
                    'accounts' => $equity
                ]
            ],

            'total_assets' => round($totalAssets, 2),

            'total_liabilities' => round($totalLiabilities, 2),

            'total_equity' => round($totalEquity, 2),

            'net_surplus' => round($netSurplus, 2),

            'total_liabilities_and_equity' =>
                round($totalLiabilitiesAndEquity, 2),

            'balanced' => abs(
                $totalAssets - $totalLiabilitiesAndEquity
            ) < 0.01
        ],

        // Matches TypeScript: res.data.statement_of_operations
        'statement_of_operations' => [
            'categories' => [
                [
                    'category' => 'Income',
                    'accounts' => $revenue
                ],
                [
                    'category' => 'Expenses',
                    'accounts' => $expense
                ]
            ],

            'revenue' => $revenue,

            'total_income' => round($totalRevenue, 2),

            'expenses' => $expense,

            'total_expenses' => round($totalExpense, 2),

            'net_surplus' => round($netSurplus, 2)
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
