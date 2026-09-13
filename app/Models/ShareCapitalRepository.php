<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ShareCapitalRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Get all share capital (CBU) accounts
     */
    public function all(?string $memberId = null): array
    {
        $sql = "
            SELECT sc.*,
                   CONCAT(m.first_name, ' ', m.last_name) AS member_name,
                   m.member_no,
                   b.name AS branch_name
            FROM share_capital_accounts sc
            JOIN members m ON sc.member_id = m.id
            JOIN branches b ON m.branch_id = b.id
            WHERE 1=1
        ";
        $params = [];

        if ($memberId) {
            $sql .= " AND sc.member_id = :member_id";
            $params['member_id'] = $memberId;
        }

        $sql .= " ORDER BY sc.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find single share capital account
     */
    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT sc.*,
                   CONCAT(m.first_name, ' ', m.last_name) AS member_name,
                   m.member_no,
                   b.name AS branch_name
            FROM share_capital_accounts sc
            JOIN members m ON sc.member_id = m.id
            JOIN branches b ON m.branch_id = b.id
            WHERE sc.id = ? OR sc.account_number = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Open a new Share Capital / CBU subscription account
     */
    public function createAccount(array $data): array
    {
        $id = $data['id'] ?? ('sc_' . bin2hex(random_bytes(6)));
        $accNo = $data['account_number'] ?? ('SC-' . date('Y') . '-' . str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT));

        $parValue = (float)($data['par_value'] ?? 100);
        $subscribedShares = (int)($data['subscribed_shares'] ?? 50);
        $subscribedAmount = (float)($data['subscribed_amount'] ?? ($subscribedShares * $parValue));

        $paidUpShares = (int)($data['paid_up_shares'] ?? 0);
        $paidUpAmount = (float)($data['paid_up_amount'] ?? ($paidUpShares * $parValue));

        $this->db->beginTransaction();

        try {
            $sql = "INSERT INTO share_capital_accounts (
                id, account_number, member_id, subscribed_shares, subscribed_amount,
                paid_up_shares, paid_up_amount, status
            ) VALUES (
                :id, :account_number, :member_id, :subscribed_shares, :subscribed_amount,
                :paid_up_shares, :paid_up_amount, 'Active'
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id'                => $id,
                'account_number'    => $accNo,
                'member_id'         => $data['member_id'],
                'subscribed_shares' => $subscribedShares,
                'subscribed_amount' => $subscribedAmount,
                'paid_up_shares'    => $paidUpShares,
                'paid_up_amount'    => $paidUpAmount,
            ]);

            if ($paidUpAmount > 0) {
                $txStmt = $this->db->prepare("
                    INSERT INTO share_capital_transactions (
                        id, share_capital_account_id, transaction_type, shares, amount,
                        running_shares, running_amount, reference_number, transaction_date, notes
                    ) VALUES (?, ?, 'Subscription Payment', ?, ?, ?, ?, ?, ?, 'Initial CBU Payment')
                ");
                $txStmt->execute([
                    'sctx_' . bin2hex(random_bytes(6)),
                    $id,
                    $paidUpShares,
                    $paidUpAmount,
                    $paidUpShares,
                    $paidUpAmount,
                    'SC-OR-' . date('Ymd') . '-' . mt_rand(100, 999),
                    $data['payment_date'] ?? date('Y-m-d')
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
     * Record payment toward share capital subscription
     */
    public function recordPayment(array $data): array
    {
        $accountId = $data['share_capital_account_id'];
        $amount    = (float)$data['amount'];
        $parValue  = (float)($data['par_value'] ?? 100);
        $shares    = (int)($data['shares'] ?? ($amount / $parValue));
        $date      = $data['payment_date'] ?? date('Y-m-d');
        $ref       = $data['reference_number'] ?? ('SC-OR-' . date('Ymd') . '-' . mt_rand(1000, 9999));

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("SELECT * FROM share_capital_accounts WHERE id = ? FOR UPDATE");
            $stmt->execute([$accountId]);
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$acc) {
                throw new \RuntimeException('Share capital account not found.');
            }

            $newPaidShares = (int)$acc['paid_up_shares'] + $shares;
            $newPaidAmount = (float)$acc['paid_up_amount'] + $amount;

            $updStmt = $this->db->prepare("
                UPDATE share_capital_accounts
                SET paid_up_shares = ?, paid_up_amount = ?
                WHERE id = ?
            ");
            $updStmt->execute([$newPaidShares, $newPaidAmount, $accountId]);

            $txId = 'sctx_' . bin2hex(random_bytes(6));
            $txStmt = $this->db->prepare("
                INSERT INTO share_capital_transactions (
                    id, share_capital_account_id, transaction_type, shares, amount,
                    running_shares, running_amount, reference_number, transaction_date, notes
                ) VALUES (?, ?, 'Subscription Payment', ?, ?, ?, ?, ?, ?, ?)
            ");
            $txStmt->execute([
                $txId,
                $accountId,
                $shares,
                $amount,
                $newPaidShares,
                $newPaidAmount,
                $ref,
                $date,
                $data['notes'] ?? 'Share capital subscription deposit'
            ]);

            $this->db->commit();

            return [
                'transaction_id' => $txId,
                'account_number' => $acc['account_number'],
                'shares_added'   => $shares,
                'amount_paid'    => $amount,
                'total_paid_up'  => $newPaidAmount,
                'reference_no'   => $ref
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get transactions for account
     */
    public function getTransactions(string $accountId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM share_capital_transactions
            WHERE share_capital_account_id = ?
            ORDER BY transaction_date DESC, created_at DESC
        ");
        $stmt->execute([$accountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete account
     */
    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM share_capital_accounts WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
