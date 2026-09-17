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
     * Compute Financial Statements dynamically
    * using Chart of Accounts and Financial Statement Mappings.
    *
    * Generates:
    * - Statement of Financial Position
    * - Statement of Operations
    */
    public function getFinancialStatements(
        ?string $asOfDate = null,
        ?string $branchId = null
    ): array {
        $asOfDate = $asOfDate ?? date('Y-m-d');

        /*
        * ---------------------------------------------------------
        * 1. Get Chart of Accounts
        * ---------------------------------------------------------
        */
        $coaStmt = $this->db->prepare("
            SELECT
                id,
                account_code,
                name,
                category,
                normal_balance
            FROM chart_of_accounts
            ORDER BY account_code ASC
        ");

        $coaStmt->execute();

        $accounts = $coaStmt->fetchAll(PDO::FETCH_ASSOC);

        /*
        * ---------------------------------------------------------
        * 2. Get posted GL balances
        * ---------------------------------------------------------
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

        if (
            $branchId !== null &&
            $branchId !== '' &&
            $branchId !== 'all'
        ) {
            $sql .= " AND je.branch_id = :branch_id";
            $params['branch_id'] = $branchId;
        }

        $sql .= "
            GROUP BY jl.account_id
        ";

        $balanceStmt = $this->db->prepare($sql);
        $balanceStmt->execute($params);

        $journalBalances = $balanceStmt->fetchAll(PDO::FETCH_ASSOC);

        /*
        * Create account balance lookup.
        */
        $balanceMap = [];

        foreach ($journalBalances as $row) {
            $accountId = (string) $row['account_id'];

            $balanceMap[$accountId] = [
                'debit'  => (float) $row['total_debit'],
                'credit' => (float) $row['total_credit']
            ];
        }

        /*
        * ---------------------------------------------------------
        * 3. Calculate balance for every account
        * ---------------------------------------------------------
        *
        * This follows the same logic as:
        *
        * const net =
        *   acc.normal_balance === 'Debit'
        *     ? debit - credit
        *     : credit - debit;
        */
        $accountBalances = [];

        foreach ($accounts as $account) {
            $accountId = (string) $account['id'];

            $debit = $balanceMap[$accountId]['debit'] ?? 0.0;
            $credit = $balanceMap[$accountId]['credit'] ?? 0.0;

            $normalBalance = $account['normal_balance'] ?? 'Debit';

            $net = $normalBalance === 'Debit'
                ? ($debit - $credit)
                : ($credit - $debit);

            $accountBalances[] = [
                'id'             => $accountId,
                'code'           => $account['account_code'] ?? '',
                'account_code'   => $account['account_code'] ?? '',
                'name'           => $account['name'] ?? '',
                'type'           => $account['category'] ?? 'Asset',
                'category'       => $account['category'] ?? 'Asset',
                'normal_balance' => $normalBalance,

                // Same as TypeScript balance
                'balance'        => round($net, 2),

                // Optional GL information
                'gross_debit'    => round($debit, 2),
                'gross_credit'   => round($credit, 2)
            ];
        }

        /*
        * ---------------------------------------------------------
        * 4. Get Financial Statement Mappings
        * ---------------------------------------------------------
        *
        * Example mapping categories:
        *
        * Current Assets
        * Non-current Assets
        * Current Liabilities
        * Long-term Liabilities
        * Equity
        * Income
        * Expenses
        */
        $mappingStmt = $this->db->prepare("
            SELECT *
            FROM financial_statement_mappings
            ORDER BY id ASC
        ");

        $mappingStmt->execute();

        $mappings = $mappingStmt->fetchAll(PDO::FETCH_ASSOC);

        /*
        * ---------------------------------------------------------
        * 5. Group accounts according to mappings
        * ---------------------------------------------------------
        *
        * IMPORTANT:
        *
        * Your TypeScript implementation does:
        *
        * acc.category === m.category
        *
        * Therefore the Chart of Accounts `category` must contain
        * values matching financial_statement_mappings.category.
        */
        $categories = [];

        foreach ($mappings as $mapping) {
            $mappingCategory = $mapping['category'] ?? '';

            $matchedAccounts = [];

            foreach ($accountBalances as $account) {
                if ($account['category'] === $mappingCategory) {
                    $matchedAccounts[] = $account;
                }
            }

            $total = 0.0;

            foreach ($matchedAccounts as $account) {
                $total += (float) $account['balance'];
            }

            $categories[] = [
                'category' => $mappingCategory,
                'accounts' => $matchedAccounts,
                'total'    => round($total, 2)
            ];
        }

        /*
        * ---------------------------------------------------------
        * Helper to get category total
        * ---------------------------------------------------------
        */
        $getCategoryTotal = function (string $category) use ($categories): float {
            foreach ($categories as $item) {
                if ($item['category'] === $category) {
                    return (float) $item['total'];
                }
            }

            return 0.0;
        };

        /*
        * ---------------------------------------------------------
        * 6. Statement of Financial Position
        * ---------------------------------------------------------
        */

        $currentAssets = $getCategoryTotal('Current Assets');

        $nonCurrentAssets = $getCategoryTotal('Non-current Assets');

        $totalAssets = round(
            $currentAssets + $nonCurrentAssets,
            2
        );

        $currentLiabilities = $getCategoryTotal(
            'Current Liabilities'
        );

        $longTermLiabilities = $getCategoryTotal(
            'Long-term Liabilities'
        );

        $totalLiabilities = round(
            $currentLiabilities + $longTermLiabilities,
            2
        );

        $totalEquity = $getCategoryTotal('Equity');

        $totalLiabilitiesAndEquity = round(
            $totalLiabilities + $totalEquity,
            2
        );

        /*
        * ---------------------------------------------------------
        * 7. Statement of Operations
        * ---------------------------------------------------------
        */

        $income = $getCategoryTotal('Income');

        $expenses = $getCategoryTotal('Expenses');

        $netSurplus = round(
            $income - $expenses,
            2
        );

        /*
        * ---------------------------------------------------------
        * 8. Return same structure as TypeScript
        * ---------------------------------------------------------
        */
        return [
            'statement_of_financial_position' => [
                'categories' => $categories,

                'total_assets' => $totalAssets,

                'total_liabilities' => $totalLiabilities,

                'total_equity' => $totalEquity,

                'total_liabilities_and_equity' =>
                    $totalLiabilitiesAndEquity
            ],

            'statement_of_operations' => [
                'total_income' => $income,

                'total_expenses' => $expenses,

                'net_surplus' => $netSurplus
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
