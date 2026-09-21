<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class SavingsRepository
{
    public function __construct(private PDO $db) {}

    /**
     * Get savings accounts with member and product info
     */
    private ?array $savingsTxColumns = null;

    private function getSavingsTxColumns(): array
    {
        if ($this->savingsTxColumns !== null) {
            return $this->savingsTxColumns;
        }

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM savings_transactions");
            $cols = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cols[strtolower($row['Field'])] = true;
            }
            $this->savingsTxColumns = $cols;
            return $cols;
        } catch (\Throwable $e) {
            return [
                'id' => true,
                'transaction_no' => true,
                'savings_account_id' => true,
                'member_id' => true,
                'type' => true,
                'amount' => true,
                'balance_after' => true,
                'transaction_date' => true,
                'notes' => true,
            ];
        }
    }

    private function recordSavingsTransaction(
        string $txId,
        string $accountId,
        ?string $memberId,
        string $type,
        float $amount,
        float $balanceAfter,
        string $ref,
        string $date,
        string $notes,
        ?string $cashAccountId = null
    ): void {
        $cols = $this->getSavingsTxColumns();
        $data = [];

        if (isset($cols['id'])) {
            $data['id'] = $txId;
        }
        if (isset($cols['savings_account_id'])) {
            $data['savings_account_id'] = $accountId;
        }
        if (isset($cols['member_id']) && $memberId) {
            $data['member_id'] = $memberId;
        }
        if (isset($cols['type'])) {
            $upper = strtoupper($type);
            $mapped = 'DEPOSIT';
            if (str_contains($upper, 'WITHDRAW')) $mapped = 'WITHDRAWAL';
            elseif (str_contains($upper, 'INTEREST')) $mapped = 'INTEREST_POSTING';
            elseif (str_contains($upper, 'FEE')) $mapped = 'FEE_DEDUCTION';
            $data['type'] = $mapped;
        }
        if (isset($cols['transaction_type'])) {
            $data['transaction_type'] = $type;
        }
        if (isset($cols['amount'])) {
            $data['amount'] = $amount;
        }
        if (isset($cols['balance_after'])) {
            $data['balance_after'] = $balanceAfter;
        }
        if (isset($cols['running_balance'])) {
            $data['running_balance'] = $balanceAfter;
        }
        if (isset($cols['transaction_no'])) {
            $data['transaction_no'] = $ref;
        }
        if (isset($cols['reference_number'])) {
            $data['reference_number'] = $ref;
        }
        if (isset($cols['transaction_date'])) {
            $data['transaction_date'] = $date;
        }
        if (isset($cols['notes'])) {
            $data['notes'] = $notes;
        }
        if (isset($cols['cash_account_id']) && $cashAccountId) {
            $data['cash_account_id'] = $cashAccountId;
        }

        if (empty($data)) return;

        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        $sql = "INSERT INTO savings_transactions (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
    }

    public function all(?string $branchId = null, ?string $memberId = null): array
    {
        $sql = "
            SELECT sa.*,
                   CONCAT(m.first_name, ' ', m.last_name) AS member_name,
                   m.member_no,
                   sp.name AS product_name,
                   sp.code AS product_code,
                   b.name AS branch_name
            FROM savings_accounts sa
                 LEFT JOIN members m ON sa.member_id = m.id
                 LEFT JOIN savings_products sp ON sa.savings_product_id = sp.id
                 LEFT JOIN branches b ON sa.branch_id = b.id
            WHERE 1=1
        ";
        $params = [];

        if ($branchId && $branchId !== 'all') {
            $sql .= " AND sa.branch_id = :branch_id";
            $params['branch_id'] = $branchId;
        }

        if ($memberId) {
            $sql .= " AND sa.member_id = :member_id";
            $params['member_id'] = $memberId;
        }

        $sql .= " ORDER BY sa.opened_date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find single savings account
     */
    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT sa.*,
                   CONCAT(m.first_name, ' ', m.last_name) AS member_name,
                   m.member_no,
                   sp.name AS product_name,
                   b.name AS branch_name
            FROM savings_accounts sa
                 LEFT JOIN members m ON sa.member_id = m.id
                 LEFT JOIN savings_products sp ON sa.savings_product_id = sp.id
                 LEFT JOIN branches b ON sa.branch_id = b.id
            WHERE sa.id = ? OR sa.account_number = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Open a new savings account
     */
    public function createAccount(array $data): array
    {
        $id = $data['id'] ?? ('sa_' . bin2hex(random_bytes(6)));
        $accNo = $data['account_number'] ?? ('SA-' . date('Y') . '-' . str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT));
        $initialDeposit = (float)($data['initial_deposit'] ?? 0);

        $this->db->beginTransaction();

        try {
            $sql = "INSERT INTO savings_accounts (
                id, account_number, member_id, savings_product_id, branch_id, balance, opened_date, status
            ) VALUES (
                :id, :account_number, :member_id, :savings_product_id, :branch_id, :balance, :opened_date, 'Active'
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id'                 => $id,
                'account_number'     => $accNo,
                'member_id'          => $data['member_id'],
                'savings_product_id' => $data['savings_product_id'],
                'branch_id'          => $data['branch_id'],
                'balance'            => $initialDeposit,
                'opened_date'        => $data['opened_date'] ?? date('Y-m-d')
            ]);

            if ($initialDeposit > 0) {
                $ref = 'DEP-' . date('Ymd') . '-' . mt_rand(100, 999);
                $this->recordSavingsTransaction(
                    'stx_' . bin2hex(random_bytes(6)),
                    $id,
                    $data['member_id'] ?? null,
                    'Deposit',
                    $initialDeposit,
                    $initialDeposit,
                    $ref,
                    $data['opened_date'] ?? date('Y-m-d'),
                    'Initial Opening Deposit'
                );
            }

            $this->db->commit();
            return $this->find($id) ?? [];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get transaction passbook history
     */
    public function getTransactions(string $accountId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM savings_transactions
            WHERE savings_account_id = ?
            ORDER BY transaction_date DESC, created_at DESC
        ");
        $stmt->execute([$accountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Record deposit or withdrawal
     */
    /**
     * Record savings deposit or withdrawal.
     *
     * Deposit:
     *   Member Savings +amount
     *   Cash/Bank +amount
     *
     * Withdrawal:
     *   Member Savings -amount
     *   Cash/Bank -amount
     */
    public function recordTransaction(array $data): array
    {
        $accountId = $data['savings_account_id'] ?? null;
        $memberId  = $data['member_id'] ?? null;
        $cashAccountId = $data['cash_account_id'] ?? null;

        // Normalize transaction type
        $type = strtoupper(
            trim((string)($data['transaction_type'] ?? $data['type'] ?? ''))
        );

        // Support both "DEPOSIT" and "Deposit"
        if ($type === 'DEPOSIT') {
            $type = 'DEPOSIT';
        } elseif (
            $type === 'WITHDRAWAL' ||
            $type === 'WITHDRAW' ||
            $type === 'WITHDRAWING'
        ) {
            $type = 'WITHDRAWAL';
        } else {
            throw new \InvalidArgumentException(
                'Transaction type must be DEPOSIT or WITHDRAWAL.'
            );
        }

        $amount = (float)($data['amount'] ?? 0);

        $date = $data['transaction_date']
            ?? date('Y-m-d');

        $ref = $data['reference_number']
            ?? $data['transaction_no']
            ?? ('TX-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))));

        $notes = $data['notes']
            ?? ($type === 'DEPOSIT'
                ? 'Savings deposit'
                : 'Savings withdrawal');

        $createdBy = $data['created_by'] ?? null;

        /*
     * Basic validation
     */
        if (!$accountId) {
            throw new \InvalidArgumentException(
                'Savings account ID is required.'
            );
        }

        if (!$cashAccountId) {
            throw new \InvalidArgumentException(
                'Cash account ID is required.'
            );
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException(
                'Transaction amount must be greater than zero.'
            );
        }

        /*
     * Start database transaction.
     *
     * Savings balance and cash balance must always
     * succeed or fail together.
     */
        $this->db->beginTransaction();

        try {

            /*
         * 1. Lock the savings account.
         *
         * FOR UPDATE prevents two simultaneous transactions
         * from modifying the same savings balance incorrectly.
         */
            $stmt = $this->db->prepare("
            SELECT
                id,
                member_id,
                balance
            FROM savings_accounts
            WHERE id = ?
            FOR UPDATE
        ");

            $stmt->execute([
                $accountId
            ]);

            $account = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$account) {
                throw new \RuntimeException(
                    "Savings account '{$accountId}' not found."
                );
            }

            /*
         * Use the member_id from the savings account when available.
         *
         * This prevents accidentally recording a transaction
         * against a different member.
         */
            $actualMemberId = $account['member_id'] ?? $memberId;

            /*
         * 2. Get current savings balance.
         */
            $currentBalance = (float)$account['balance'];

            /*
         * 3. Validate withdrawal.
         */
            if ($type === 'WITHDRAWAL' && $currentBalance < $amount) {
                throw new \RuntimeException(
                    sprintf(
                        'Insufficient savings balance. Available: %.2f, Requested: %.2f',
                        $currentBalance,
                        $amount
                    )
                );
            }

            /*
         * 4. Calculate new savings balance.
         */
            if ($type === 'DEPOSIT') {
                $newBalance = $currentBalance + $amount;
            } else {
                $newBalance = $currentBalance - $amount;
            }

            /*
         * Avoid floating point noise.
         */
            $newBalance = round($newBalance, 2);

            /*
         * 5. Update savings account balance.
         */
            $stmt = $this->db->prepare("
            UPDATE savings_accounts
            SET balance = ?
            WHERE id = ?
        ");

            $stmt->execute([
                $newBalance,
                $accountId
            ]);

            /*
         * 6. Generate savings transaction ID.
         */
            $txId = 'stx_' . bin2hex(random_bytes(6));

            /*
         * 7. Record the member savings transaction.
         *
         * This writes to savings_transactions.
         */
            $this->recordSavingsTransaction(
                $txId,
                $accountId,
                $actualMemberId,
                $type,
                $amount,
                $newBalance,
                $ref,
                $date,
                $notes,
                $cashAccountId
            );

            /*
         * 8. Update the physical cash/bank account.
         *
         * Deposit:
         *   Cash IN
         *
         * Withdrawal:
         *   Cash OUT
         */
            $cashType = ($type === 'DEPOSIT')
                ? 'INFLOW'
                : 'OUTFLOW';

            $this->saveTransactionToCashAccount(
                cashAccountId: $cashAccountId,
                type: $cashType,
                amount: $amount,
                transactionNo: $ref,
                referenceNumber: $ref,
                referenceType: 'savings_transaction',
                referenceId: $txId,
                description: $type === 'DEPOSIT'
                    ? 'Savings deposit'
                    : 'Savings withdrawal',
                notes: $notes,
                transactionDate: $date,
                createdBy: $createdBy
            );

            /*
         * 9. Everything succeeded.
         */
            $this->db->commit();

            /*
         * 10. Return useful transaction information.
         */
            return [
                'success' => true,

                'transaction_id' => $txId,

                'savings_account_id' => $accountId,

                'member_id' => $actualMemberId,

                'cash_account_id' => $cashAccountId,

                'type' => $type,

                'amount' => $amount,

                'previous_balance' => $currentBalance,

                'running_balance' => $newBalance,

                'reference_number' => $ref,

                'transaction_date' => $date
            ];
        } catch (\Throwable $e) {

            /*
         * If anything fails, undo:
         *
         * - savings balance update
         * - savings transaction
         * - cash transaction
         * - cash balance update
         */
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }
    /**
     * Delete account
     */
    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM savings_accounts WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Update cash/bank subsidiary ledger.
     *
     * For an Asset account:
     *
     * Deposit    → Debit  → increase
     * Withdrawal → Credit → decrease
     */
    private function saveTransactionToCashAccount(
        string $cashAccountId,
        string $type,
        float $amount,
        string $transactionNo,
        ?string $referenceNumber = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $description = null,
        ?string $notes = null,
        ?string $transactionDate = null,
        ?string $createdBy = null
    ): void {
        $type = strtoupper(trim($type));

        if (!in_array($type, ['INFLOW', 'OUTFLOW'], true)) {
            throw new \InvalidArgumentException(
                'Cash transaction type must be INFLOW or OUTFLOW.'
            );
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException(
                'Cash transaction amount must be greater than zero.'
            );
        }

        $transactionDate ??= date('Y-m-d');

        // Lock cash account
        $stmt = $this->db->prepare("
        SELECT id, current_balance
        FROM cash_accounts
        WHERE id = ?
        FOR UPDATE
    ");

        $stmt->execute([$cashAccountId]);

        $cashAccount = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cashAccount) {
            throw new \RuntimeException(
                "Cash account '{$cashAccountId}' not found."
            );
        }

        $balanceBefore = (float) $cashAccount['current_balance'];

        if ($type === 'INFLOW') {
            $balanceAfter = $balanceBefore + $amount;
        } else {
            $balanceAfter = $balanceBefore - $amount;

            if ($balanceAfter < 0) {
                throw new \RuntimeException(
                    'Insufficient cash balance.'
                );
            }
        }

        $transactionId = 'ctx_' . bin2hex(random_bytes(6));

        // Record movement history
        $stmt = $this->db->prepare("
        INSERT INTO cash_transactions (
            id,
            transaction_no,
            cash_account_id,
            type,
            amount,
            balance_before,
            balance_after,
            running_balance,
            reference_number,
            reference_type,
            reference_id,
            description,
            notes,
            transaction_date,
            created_by
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

        $stmt->execute([
            $transactionId,
            $transactionNo,
            $cashAccountId,
            $type,
            $amount,
            $balanceBefore,
            $balanceAfter,
            $balanceAfter,
            $referenceNumber,
            $referenceType,
            $referenceId,
            $description,
            $notes,
            $transactionDate,
            $createdBy
        ]);

        // Update current balance
        $stmt = $this->db->prepare("
        UPDATE cash_accounts
        SET current_balance = ?
        WHERE id = ?
    ");

        $stmt->execute([
            $balanceAfter,
            $cashAccountId
        ]);
    }
}
