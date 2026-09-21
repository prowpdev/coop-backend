<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class SavingsRepository
{
    public function __construct(private PDO $db)
    {
    }

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
    public function recordTransaction(array $data): array
    {
        $accountId = $data['savings_account_id'];
        $memberId = $data['member_id'];
        $type      = $data['transaction_type']; // 'Deposit' or 'Withdrawal'
        $amount    = (float)$data['amount'];
        $date      = $data['transaction_date'] ?? date('Y-m-d');
        $ref       = $data['reference_number'] ?? ('TX-' . date('Ymd') . '-' . mt_rand(1000, 9999));

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Transaction amount must be positive.');
        }

        $this->db->beginTransaction();

        try {
            // Lock and fetch current balance
            $stmt = $this->db->prepare("SELECT balance FROM savings_accounts WHERE id = ? FOR UPDATE");
            $stmt->execute([$accountId]);
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$acc) {
                throw new \RuntimeException('Savings account not found.');
            }

            $currentBal = (float)$acc['balance'];

            if ($type === 'Withdrawal' && $currentBal < $amount) {
                throw new \RuntimeException('Insufficient savings balance for withdrawal.');
            }

            $newBal = ($type === 'Deposit') ? ($currentBal + $amount) : ($currentBal - $amount);

            // Update account balance
            $updStmt = $this->db->prepare("UPDATE savings_accounts SET balance = ? WHERE id = ?");
            $updStmt->execute([$newBal, $accountId]);

            // Insert transaction line safely
            $txId = 'stx_' . bin2hex(random_bytes(6));
            $this->recordSavingsTransaction(
                $txId,
                $accountId,
                $acc['member_id'] ?? $memberId,
                $type,
                $amount,
                $newBal,
                $ref,
                $date,
                $data['notes'] ?? "$type transaction"
            );

            $this->db->commit();

            return [
                'transaction_id'  => $txId,
                'account_number'  => $accountId,
                'type'            => $type,
                'amount'          => $amount,
                'running_balance' => $newBal,
                'reference_no'    => $ref
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
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
}
