<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class CashRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function all(?string $branchId = null): array
    {
        $sql = "
            SELECT ca.*, b.name AS branch_name, coa.name AS gl_account_name
            FROM cash_accounts ca
            LEFT JOIN branches b ON ca.branch_id = b.id
            LEFT JOIN chart_of_accounts coa ON ca.gl_account_id = coa.id
            WHERE 1=1
        ";
        $params = [];

        if ($branchId && $branchId !== 'all') {
            $sql .= " AND ca.branch_id = :branch_id";
            $params['branch_id'] = $branchId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM cash_accounts WHERE id = ? OR account_code = ? LIMIT 1");
        $stmt->execute([$id, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function transfer(string $fromId, string $toId, float $amount, string $date, string $notes): array
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be positive.');
        }

        $this->db->beginTransaction();

        try {
            // Deduct from source
            $fromStmt = $this->db->prepare("SELECT current_balance FROM cash_accounts WHERE id = ? FOR UPDATE");
            $fromStmt->execute([$fromId]);
            $from = $fromStmt->fetch(PDO::FETCH_ASSOC);

            if (!$from) {
                throw new \RuntimeException('Source cash account not found.');
            }

            if ((float)$from['current_balance'] < $amount) {
                throw new \RuntimeException('Insufficient cash drawer balance.');
            }

            $newFromBal = (float)$from['current_balance'] - $amount;
            $this->db->prepare("UPDATE cash_accounts SET current_balance = ? WHERE id = ?")->execute([$newFromBal, $fromId]);

            // Add to target
            $toStmt = $this->db->prepare("SELECT current_balance FROM cash_accounts WHERE id = ? FOR UPDATE");
            $toStmt->execute([$toId]);
            $to = $toStmt->fetch(PDO::FETCH_ASSOC);

            if (!$to) {
                throw new \RuntimeException('Destination cash account not found.');
            }

            $newToBal = (float)$to['current_balance'] + $amount;
            $this->db->prepare("UPDATE cash_accounts SET current_balance = ? WHERE id = ?")->execute([$newToBal, $toId]);

            // Log transactions
            $ref = 'TXFR-' . date('Ymd') . '-' . mt_rand(100, 999);

            $insTx = $this->db->prepare("
                INSERT INTO cash_transactions (id, cash_account_id, type, amount, running_balance, reference_number, transaction_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $insTx->execute(['ctx_' . bin2hex(random_bytes(6)), $fromId, 'Transfer Out', $amount, $newFromBal, $ref, $date, $notes]);
            $insTx->execute(['ctx_' . bin2hex(random_bytes(6)), $toId, 'Transfer In', $amount, $newToBal, $ref, $date, $notes]);

            $this->db->commit();

            return [
                'reference'       => $ref,
                'amount'          => $amount,
                'from_balance'    => $newFromBal,
                'to_balance'      => $newToBal,
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
