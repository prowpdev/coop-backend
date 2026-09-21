<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class CashAccountSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $cashAccounts = [
            [
                'cash_01',
                'Cash on Hand - Teller 1',
                'COH-TAR-01',
                'Cash Vault Drawer',
                'branch_tar',
                'acc_1110',
                250000.00,
                250000.00,
                'PHP',
                1,
            ],
            [
                'cash_02',
                'Main Vault Reserve',
                'VLT-TAR-00',
                'Master Vault Safety Depository',
                'branch_tar',
                'acc_1110',
                0.00,
                0.00,
                'PHP',
                1,
            ],
            [
                'cash_03',
                'Land Bank of the Philippines - Operating Checking',
                'LBP-0912-3341-99',
                'Land Bank of the Philippines',
                'branch_tar',
                'acc_1120',
                0.00,
                0.00,
                'PHP',
                1,
            ],
            [
                'cash_04',
                'Development Bank of the Philippines - High Yield',
                'DBP-4401-2990-11',
                'Development Bank of the Philippines',
                'branch_tar',
                'acc_1121',
                0.00,
                0.00,
                'PHP',
                1,
            ],
            [
                'cash_05',
                'Urdaneta Branch Teller Cash',
                'COH-URD-01',
                'Cash Drawer Urdaneta',
                'branch_urd',
                'acc_1110',
                0.00,
                0.00,
                'PHP',
                1,
            ],
            [
                'cash_06',
                'San Fernando Branch Teller Cash',
                'COH-SFE-01',
                'Cash Drawer San Fernando',
                'branch_sfe',
                'acc_1110',
                0.00,
                0.00,
                'PHP',
                1,
            ],
        ];

        $sql = "
            INSERT INTO cash_accounts (
                id,
                name,
                account_number,
                bank_name,
                branch_id,
                gl_account_id,
                opening_balance,
                current_balance,
                currency,
                active
            )
            VALUES (
                :id,
                :name,
                :account_number,
                :bank_name,
                :branch_id,
                :gl_account_id,
                :opening_balance,
                :current_balance,
                :currency,
                :active
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                account_number = VALUES(account_number),
                bank_name = VALUES(bank_name),
                branch_id = VALUES(branch_id),
                gl_account_id = VALUES(gl_account_id),
                opening_balance = VALUES(opening_balance),
                currency = VALUES(currency),
                active = VALUES(active)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($cashAccounts as $account) {
            $stmt->execute([
                ':id'              => $account[0],
                ':name'            => $account[1],
                ':account_number'  => $account[2],
                ':bank_name'       => $account[3],
                ':branch_id'       => $account[4],
                ':gl_account_id'   => $account[5],
                ':opening_balance' => $account[6],
                ':current_balance' => $account[7],
                ':currency'        => $account[8],
                ':active'          => $account[9],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM cash_accounts
            ORDER BY account_number ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}