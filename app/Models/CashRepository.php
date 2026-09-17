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
        $stmt = $this->db->prepare("SELECT * FROM cash_accounts WHERE id = ? OR account_number = ? LIMIT 1");
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
    
    public function save(array $data): array
    {
        $id = $data['id'] ?? ('cash_' . bin2hex(random_bytes(6)));
        $name = $data['name'] ?? 'Cash Vault Account';
        $accountNo = $data['account_number'] ?? $data['account_code'] ?? ('CASH-' . mt_rand(1000, 9999));
        $bankName = $data['bank_name'] ?? 'Cash Depository';
        $branchId = $data['branch_id'] ?? 'branch_tar';
        $glId = $data['gl_account_id'] ?? 'acc_1110';
        $opening = (float)($data['opening_balance'] ?? 0);
        $current = isset($data['current_balance']) ? (float)$data['current_balance'] : $opening;
        $currency = $data['currency'] ?? 'PHP';
        $active = isset($data['active']) ? (int)$data['active'] : 1;

        $branchStmt = $this->db->prepare('SELECT id FROM branches WHERE id = ?');
        $branchStmt->execute([$branchId]);
        if (!$branchStmt->fetchColumn()) {
            throw new \InvalidArgumentException('Selected branch does not exist.');
        }

        $glStmt = $this->db->prepare('SELECT id FROM chart_of_accounts WHERE id = ?');
        $glStmt->execute([$glId]);
        if (!$glStmt->fetchColumn()) {
            throw new \InvalidArgumentException('Selected GL account does not exist.');
        }

        $sql = "
            INSERT INTO cash_accounts (id, name, account_number, bank_name, branch_id, gl_account_id, opening_balance, current_balance, currency, active)
            VALUES (:id, :name, :account_number, :bank_name, :branch_id, :gl_account_id, :opening_balance, :current_balance, :currency, :active)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                account_number = VALUES(account_number),
                bank_name = VALUES(bank_name),
                branch_id = VALUES(branch_id),
                gl_account_id = VALUES(gl_account_id),
                current_balance = VALUES(current_balance),
                active = VALUES(active)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id'              => $id,
            'name'            => $name,
            'account_number'  => $accountNo,
            'bank_name'       => $bankName,
            'branch_id'       => $branchId,
            'gl_account_id'   => $glId,
            'opening_balance' => $opening,
            'current_balance' => $current,
            'currency'        => $currency,
            'active'          => $active
        ]);

        return $this->find($id) ?? $data;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM cash_accounts WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function replenish(string $accountId, float $amount, ?string $sourceId, string $notes, string $date): array
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Replenishment amount must be greater than zero.');
        }

        $this->db->beginTransaction();

        try {
            $toStmt = $this->db->prepare("SELECT * FROM cash_accounts WHERE id = ? FOR UPDATE");
            $toStmt->execute([$accountId]);
            $target = $toStmt->fetch(PDO::FETCH_ASSOC);

            if (!$target) {
                throw new \RuntimeException('Cash account not found.');
            }

            if ($sourceId) {
                $fromStmt = $this->db->prepare("SELECT * FROM cash_accounts WHERE id = ? FOR UPDATE");
                $fromStmt->execute([$sourceId]);
                $source = $fromStmt->fetch(PDO::FETCH_ASSOC);

                if ($source && (float)$source['current_balance'] < $amount) {
                    throw new \RuntimeException('Insufficient funds in source account.');
                }

                if ($source) {
                    $newSourceBal = (float)$source['current_balance'] - $amount;
                    $this->db->prepare("UPDATE cash_accounts SET current_balance = ? WHERE id = ?")->execute([$newSourceBal, $sourceId]);
                }
            }

            $newTargetBal = (float)$target['current_balance'] + $amount;
            $this->db->prepare("UPDATE cash_accounts SET current_balance = ? WHERE id = ?")->execute([$newTargetBal, $accountId]);

            $ref = 'REP-' . date('Ymd') . '-' . mt_rand(100, 999);
            $txStmt = $this->db->prepare("
                INSERT INTO cash_transactions (id, cash_account_id, type, amount, running_balance, reference_number, transaction_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $txStmt->execute(['ctx_' . bin2hex(random_bytes(6)), $accountId, 'Replenishment', $amount, $newTargetBal, $ref, $date, $notes]);

            $this->db->commit();

            return [
                'reference'       => $ref,
                'amount'          => $amount,
                'current_balance' => $newTargetBal,
                'account'         => $this->find($accountId)
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function autoAlignGl(): int
    {
        $accounts = $this->all();
        $updated = 0;
        foreach ($accounts as $a) {
            $num = strtoupper($a['account_number'] ?? '');
            $name = strtolower($a['name'] ?? '');
            $bank = strtolower($a['bank_name'] ?? '');
            $targetGl = $a['gl_account_id'];

            if (str_contains($bank, 'land bank') || str_contains($name, 'land bank')) {
                $targetGl = 'acc_1120';
            } elseif (str_contains($bank, 'development bank') || str_contains($bank, 'dbp') || str_contains($name, 'dbp')) {
                $targetGl = 'acc_1121';
            } elseif (str_contains($bank, 'maya') || str_contains($name, 'maya') || str_contains($name, 'gcash') || str_contains($name, 'wallet')) {
                $targetGl = 'acc_1130';
            } elseif (str_contains($name, 'petty') || str_contains($bank, 'petty')) {
                $targetGl = 'acc_1111';
            } elseif (str_starts_with($num, 'COH-') || str_contains($name, 'teller') || str_contains($name, 'drawer')) {
                $targetGl = 'acc_1110';
            } elseif (str_starts_with($num, 'VLT-') || str_contains($name, 'vault') || str_contains($bank, 'vault')) {
                $targetGl = 'acc_1112';
            }

            if ($targetGl !== $a['gl_account_id']) {
                $this->db->prepare("UPDATE cash_accounts SET gl_account_id = ? WHERE id = ?")->execute([$targetGl, $a['id']]);
                $updated++;
            }
        }
        return $updated;
    }
}
