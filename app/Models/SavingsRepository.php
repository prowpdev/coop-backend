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
            JOIN members m ON sa.member_id = m.id
            JOIN savings_products sp ON sa.savings_product_id = sp.id
            JOIN branches b ON sa.branch_id = b.id
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
            JOIN members m ON sa.member_id = m.id
            JOIN savings_products sp ON sa.savings_product_id = sp.id
            JOIN branches b ON sa.branch_id = b.id
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
                $txStmt = $this->db->prepare("
                    INSERT INTO savings_transactions (
                        id, savings_account_id, transaction_type, amount, running_balance,
                        reference_number, transaction_date, notes
                    ) VALUES (?, ?, 'Deposit', ?, ?, ?, ?, 'Initial Opening Deposit')
                ");
                $txStmt->execute([
                    'stx_' . bin2hex(random_bytes(6)),
                    $id,
                    $initialDeposit,
                    $initialDeposit,
                    'DEP-' . date('Ymd') . '-' . mt_rand(100, 999),
                    $data['opened_date'] ?? date('Y-m-d')
                ]);
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

            // Insert transaction line
            $txId = 'stx_' . bin2hex(random_bytes(6));
            $txStmt = $this->db->prepare("
                INSERT INTO savings_transactions (
                    id, savings_account_id, transaction_type, amount, running_balance,
                    reference_number, transaction_date, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $txStmt->execute([
                $txId,
                $accountId,
                $type,
                $amount,
                $newBal,
                $ref,
                $date,
                $data['notes'] ?? "$type transaction"
            ]);

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
