<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class AccountingRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Get all Chart of Accounts items
     */
    public function getChartOfAccounts(): array
    {
        $stmt = $this->db->query("
            SELECT * FROM chart_of_accounts
            ORDER BY account_code ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find single account by ID or Code
     */
    public function findAccount(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM chart_of_accounts
            WHERE id = ? OR account_code = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Create or update account in Chart of Accounts
     */
    public function saveAccount(array $data): array
    {
        $id = $data['id'] ?? ('coa_' . bin2hex(random_bytes(6)));

        $sql = "
            INSERT INTO chart_of_accounts (id, account_code, name, category, normal_balance, is_active, report_group, description)
            VALUES (:id, :account_code, :name, :category, :normal_balance, :is_active, :report_group, :description)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                category = VALUES(category),
                normal_balance = VALUES(normal_balance),
                is_active = VALUES(is_active),
                report_group = VALUES(report_group),
                description = VALUES(description)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id'             => $id,
            'account_code'   => $data['account_code'],
            'name'           => $data['name'],
            'category'       => $data['category'],
            'normal_balance' => $data['normal_balance'],
            'is_active'      => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'report_group'   => $data['report_group'] ?? 'General',
            'description'    => $data['description'] ?? null
        ]);

        return $this->findAccount($id) ?? [];
    }

    /**
     * Fetch journal entries with lines
     */
    public function getJournalEntries(?string $branchId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $sql = "
            SELECT je.*, b.name AS branch_name
            FROM journal_entries je
            LEFT JOIN branches b ON je.branch_id = b.id
            WHERE 1=1
        ";
        $params = [];

        if ($branchId && $branchId !== 'all') {
            $sql .= " AND je.branch_id = :branch_id";
            $params['branch_id'] = $branchId;
        }

        if ($startDate) {
            $sql .= " AND je.posting_date >= :start_date";
            $params['start_date'] = $startDate;
        }

        if ($endDate) {
            $sql .= " AND je.posting_date <= :end_date";
            $params['end_date'] = $endDate;
        }

        $sql .= " ORDER BY je.posting_date DESC, je.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch lines for each entry
        $lineStmt = $this->db->prepare("
            SELECT jl.*, coa.account_code, coa.name AS account_name
            FROM journal_lines jl
            JOIN chart_of_accounts coa ON jl.account_id = coa.id
            WHERE jl.journal_entry_id = ?
        ");

        foreach ($entries as &$entry) {
            $lineStmt->execute([$entry['id']]);
            $entry['lines'] = $lineStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $entries;
    }

    /**
     * Find single journal entry
     */
    public function findJournalEntry(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT je.*, b.name AS branch_name
            FROM journal_entries je
            LEFT JOIN branches b ON je.branch_id = b.id
            WHERE je.id = ? OR je.voucher_number = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) {
            return null;
        }

        $lineStmt = $this->db->prepare("
            SELECT jl.*, coa.account_code, coa.name AS account_name
            FROM journal_lines jl
            JOIN chart_of_accounts coa ON jl.account_id = coa.id
            WHERE jl.journal_entry_id = ?
        ");
        $lineStmt->execute([$entry['id']]);
        $entry['lines'] = $lineStmt->fetchAll(PDO::FETCH_ASSOC);

        return $entry;
    }

    /**
     * Create balanced double-entry Journal Voucher
     */
    public function createJournalEntry(array $data): array
    {
        $lines = $data['lines'] ?? [];
        if (count($lines) < 2) {
            throw new \InvalidArgumentException('Journal entry must contain at least two lines.');
        }

        $totalDebit  = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $totalDebit  += (float)($line['debit'] ?? 0);
            $totalCredit += (float)($line['credit'] ?? 0);
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \InvalidArgumentException(sprintf(
                'Out of balance: Total debits (%.2f) must equal total credits (%.2f).',
                $totalDebit,
                $totalCredit
            ));
        }

        $this->db->beginTransaction();

        try {
            $id = $data['id'] ?? ('je_' . bin2hex(random_bytes(6)));
            $voucherNo = $data['voucher_number'] ?? ('JV-' . date('Ymd') . '-' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT));
            $postingDate = $data['posting_date'] ?? date('Y-m-d');
            $branchId = $data['branch_id'] ?? 'br_main';

            $sql = "
                INSERT INTO journal_entries (
                    id, voucher_number, branch_id, posting_date, reference_type,
                    description, total_debit, total_credit, period_id, status
                ) VALUES (
                    :id, :voucher_number, :branch_id, :posting_date, :reference_type,
                    :description, :total_debit, :total_credit, :period_id, 'Posted'
                )
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id'             => $id,
                'voucher_number' => $voucherNo,
                'branch_id'      => $branchId,
                'posting_date'   => $postingDate,
                'reference_type' => $data['reference_type'] ?? 'Manual JV',
                'description'    => $data['description'] ?? 'Manual Journal Voucher',
                'total_debit'    => $totalDebit,
                'total_credit'   => $totalCredit,
                'period_id'      => $data['period_id'] ?? ('period_' . date('Ym')),
            ]);

            $lineSql = "
                INSERT INTO journal_lines (
                    id, journal_entry_id, account_id, debit, credit, subsidiary_type, subsidiary_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ";
            $lineStmt = $this->db->prepare($lineSql);

            foreach ($lines as $line) {
                $lineStmt->execute([
                    'jl_' . bin2hex(random_bytes(6)),
                    $id,
                    $line['account_id'],
                    (float)($line['debit'] ?? 0),
                    (float)($line['credit'] ?? 0),
                    $line['subsidiary_type'] ?? null,
                    $line['subsidiary_id'] ?? null
                ]);
            }

            $this->db->commit();
            return $this->findJournalEntry($id) ?? [];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Reverse a Journal Entry
     */
    public function reverseJournalEntry(string $id, string $reason = 'Reversal'): array
    {
        $original = $this->findJournalEntry($id);
        if (!$original) {
            throw new \RuntimeException('Original journal entry not found.');
        }

        if ($original['status'] === 'Reversed') {
            throw new \RuntimeException('Journal entry is already reversed.');
        }

        // Build inverted lines
        $reversedLines = [];
        foreach ($original['lines'] as $line) {
            $reversedLines[] = [
                'account_id'      => $line['account_id'],
                'debit'           => (float)$line['credit'],
                'credit'          => (float)$line['debit'],
                'subsidiary_type' => $line['subsidiary_type'],
                'subsidiary_id'   => $line['subsidiary_id']
            ];
        }

        $revData = [
            'voucher_number' => 'REV-' . $original['voucher_number'],
            'branch_id'      => $original['branch_id'],
            'posting_date'   => date('Y-m-d'),
            'reference_type' => 'Reversal',
            'description'    => "Reversal of {$original['voucher_number']}: $reason",
            'lines'          => $reversedLines
        ];

        $reversalEntry = $this->createJournalEntry($revData);

        // Mark original as Reversed
        $upd = $this->db->prepare("UPDATE journal_entries SET status = 'Reversed' WHERE id = ?");
        $upd->execute([$id]);

        return $reversalEntry;
    }

    public function deleteAccount(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM chart_of_accounts WHERE id = ? OR account_code = ?");
        return $stmt->execute([$id, $id]);
    }

    public function getAccountingMappings(): array
    {
        try {
            $stmt = $this->db->query("SELECT * FROM accounting_mappings");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_map(function ($row) {
                return [
                    'id'                => $row['id'] ?? '',
                    'name'              => $row['name'] ?? $row['event_type'] ?? $row['transaction_type'] ?? $row['id'],
                    'transaction_type'  => $row['transaction_type'] ?? $row['event_type'] ?? '',
                    'event_type'        => $row['event_type'] ?? $row['transaction_type'] ?? '',
                    'description'       => $row['description'] ?? '',
                    'debit_account_id'  => $row['debit_account_id'] ?? '',
                    'credit_account_id' => $row['credit_account_id'] ?? '',
                    'is_system'         => (bool)($row['is_system'] ?? true)
                ];
            }, $rows);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function saveAccountingMapping(array $data): array
    {
        $id = $data['id'] ?? ('map_' . bin2hex(random_bytes(6)));
        $name = $data['name'] ?? 'New Mapping';
        $type = strtoupper(str_replace(' ', '_', $data['transaction_type'] ?? $data['event_type'] ?? 'CUSTOM_TX'));
        $desc = $data['description'] ?? "Journal mapping for {$name}";
        $debit = $data['debit_account_id'] ?? $data['debit_account'] ?? 'acc_1110';
        $credit = $data['credit_account_id'] ?? $data['credit_account'] ?? 'acc_2110';

        try {
            $stmt = $this->db->prepare("
                INSERT INTO accounting_mappings (id, name, transaction_type, event_type, description, debit_account_id, credit_account_id, is_system)
                VALUES (:id, :name, :type, :type2, :desc, :debit, :credit, 0)
            ");
            $stmt->execute([
                'id'    => $id,
                'name'  => $name,
                'type'  => $type,
                'type2' => $type,
                'desc'  => $desc,
                'debit' => $debit,
                'credit'=> $credit
            ]);
        } catch (\Exception $e) {
            // Fallback for schemas with only event_type
            try {
                $stmt2 = $this->db->prepare("
                    INSERT INTO accounting_mappings (id, event_type, description, debit_account_id, credit_account_id, is_system)
                    VALUES (:id, :type, :desc, :debit, :credit, 0)
                ");
                $stmt2->execute([
                    'id'    => $id,
                    'type'  => $type,
                    'desc'  => $desc,
                    'debit' => $debit,
                    'credit'=> $credit
                ]);
            } catch (\Exception $e2) {
                // Return payload in memory
            }
        }

        return [
            'id'                => $id,
            'name'              => $name,
            'transaction_type'  => $type,
            'event_type'        => $type,
            'description'       => $desc,
            'debit_account_id'  => $debit,
            'credit_account_id' => $credit,
            'is_system'         => false
        ];
    }

    public function updateAccountingMapping(string $id, array $data): array
    {
        try {
            $debit = $data['debit_account_id'] ?? $data['debit_account'] ?? null;
            $credit = $data['credit_account_id'] ?? $data['credit_account'] ?? null;
            $name = $data['name'] ?? null;
            $desc = $data['description'] ?? null;

            $stmt = $this->db->prepare("
                UPDATE accounting_mappings
                SET debit_account_id = COALESCE(:debit, debit_account_id),
                    credit_account_id = COALESCE(:credit, credit_account_id)
                WHERE id = :id OR transaction_type = :id2 OR event_type = :id3
            ");
            $stmt->execute([
                'id'     => $id,
                'id2'    => $id,
                'id3'    => $id,
                'debit'  => $debit,
                'credit' => $credit
            ]);

            if ($name || $desc) {
                try {
                    $upd = $this->db->prepare("UPDATE accounting_mappings SET name = COALESCE(:name, name), description = COALESCE(:desc, description) WHERE id = :id");
                    $upd->execute(['name' => $name, 'desc' => $desc, 'id' => $id]);
                } catch (\Exception $e) {}
            }

            return array_merge(['id' => $id], $data);
        } catch (\Exception $e) {
            return array_merge(['id' => $id], $data);
        }
    }

    public function deleteAccountingMapping(string $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM accounting_mappings WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function resetAccountingMappings(): array
    {
        $defaults = [
            ['id' => 'map_loan_rel', 'name' => 'Loan Disbursement / Release', 'transaction_type' => 'LOAN_RELEASE', 'event_type' => 'LOAN_RELEASE', 'description' => 'Disbursement of approved loan principal to borrower', 'debit_account_id' => 'acc_1210', 'credit_account_id' => 'acc_1110', 'is_system' => true],
            ['id' => 'map_loan_pmt', 'name' => 'Loan Repayment (Principal)', 'transaction_type' => 'LOAN_PAYMENT', 'event_type' => 'LOAN_PAYMENT', 'description' => 'Collection of loan installment principal', 'debit_account_id' => 'acc_1110', 'credit_account_id' => 'acc_1210', 'is_system' => true],
            ['id' => 'map_int_inc', 'name' => 'Loan Interest Collection', 'transaction_type' => 'INTEREST_INCOME', 'event_type' => 'INTEREST_INCOME', 'description' => 'Interest portion of loan repayment', 'debit_account_id' => 'acc_1110', 'credit_account_id' => 'acc_4110', 'is_system' => true],
            ['id' => 'map_pen_inc', 'name' => 'Loan Penalty Collection', 'transaction_type' => 'PENALTY_INCOME', 'event_type' => 'PENALTY_INCOME', 'description' => 'Late payment fee collected', 'debit_account_id' => 'acc_1110', 'credit_account_id' => 'acc_4130', 'is_system' => true],
            ['id' => 'map_fee_inc', 'name' => 'Service & Processing Fee Collection', 'transaction_type' => 'FEE_INCOME', 'event_type' => 'FEE_INCOME', 'description' => 'Deducted or collected processing fees', 'debit_account_id' => 'acc_1110', 'credit_account_id' => 'acc_4120', 'is_system' => true],
            ['id' => 'map_sav_dep', 'name' => 'Member Savings Deposit', 'transaction_type' => 'SAVINGS_DEPOSIT', 'event_type' => 'SAVINGS_DEPOSIT', 'description' => 'Member deposits cash into savings account', 'debit_account_id' => 'acc_1110', 'credit_account_id' => 'acc_2110', 'is_system' => true],
            ['id' => 'map_sav_with', 'name' => 'Member Savings Withdrawal', 'transaction_type' => 'SAVINGS_WITHDRAWAL', 'event_type' => 'SAVINGS_WITHDRAWAL', 'description' => 'Member withdraws cash from savings account', 'debit_account_id' => 'acc_2110', 'credit_account_id' => 'acc_1110', 'is_system' => true],
            ['id' => 'map_sc_sub', 'name' => 'Share Capital Contribution (CBU)', 'transaction_type' => 'SHARE_CAPITAL_PAYMENT', 'event_type' => 'SHARE_CAPITAL_PAYMENT', 'description' => 'Member adds capital build-up', 'debit_account_id' => 'acc_1110', 'credit_account_id' => 'acc_3110', 'is_system' => true],
            ['id' => 'map_expense', 'name' => 'Operating Expense Payment', 'transaction_type' => 'EXPENSE_PAYMENT', 'event_type' => 'EXPENSE_PAYMENT', 'description' => 'Disbursement for operational expenditure', 'debit_account_id' => 'acc_5220', 'credit_account_id' => 'acc_1110', 'is_system' => true]
        ];

        try {
            $this->db->exec("DELETE FROM accounting_mappings");
            foreach ($defaults as $m) {
                $this->saveAccountingMapping($m);
            }
        } catch (\Exception $e) {}

        return $defaults;
    }

    public function closePeriod(string $periodId, string $closedBy): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE accounting_periods
                SET status = 'Closed', closed_by = :closed_by, closed_at = NOW()
                WHERE id = :id
            ");
            return $stmt->execute(['id' => $periodId, 'closed_by' => $closedBy]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function reopenPeriod(string $periodId): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE accounting_periods
                SET status = 'Open', closed_by = NULL, closed_at = NULL
                WHERE id = :id
            ");
            return $stmt->execute(['id' => $periodId]);
        } catch (\Exception $e) {
            return false;
        }
    }
}
