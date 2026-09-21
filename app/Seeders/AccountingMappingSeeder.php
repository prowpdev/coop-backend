<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class AccountingMappingSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $mappings = [
            [
                'map_int_inc',
                'INTEREST_INCOME_RECOGNITION',
                'Loan interest collected',
                'acc_1110',
                'acc_4110',
                1,
            ],
            [
                'map_loan_pmt',
                'LOAN_PAYMENT',
                'Loan installment repayment entry',
                'acc_1110',
                'acc_1210',
                1,
            ],
            [
                'map_loan_rel',
                'LOAN_RELEASE',
                'Standard loan release journal entry',
                'acc_1210',
                'acc_1110',
                1,
            ],
            [
                'map_pen_inc',
                'PENALTY_INCOME_RECOGNITION',
                'Late payment default penalty',
                'acc_1110',
                'acc_4130',
                1,
            ],
            [
                'map_sav_dep',
                'SAVINGS_DEPOSIT',
                'Member savings cash deposit',
                'acc_1110',
                'acc_2110',
                1,
            ],
            [
                'map_sav_with',
                'SAVINGS_WITHDRAWAL',
                'Member savings cash withdrawal',
                'acc_2110',
                'acc_1110',
                1,
            ],
            [
                'map_sc_sub',
                'SHARE_SUBSCRIPTION_PAYMENT',
                'Share capital contribution',
                'acc_1110',
                'acc_3110',
                1,
            ],
        ];

        $sql = "
            INSERT INTO accounting_mappings (
                id,
                event_type,
                description,
                debit_account_id,
                credit_account_id,
                is_system
            )
            VALUES (
                :id,
                :event_type,
                :description,
                :debit_account_id,
                :credit_account_id,
                :is_system
            )
            ON DUPLICATE KEY UPDATE
                event_type = VALUES(event_type),
                description = VALUES(description),
                debit_account_id = VALUES(debit_account_id),
                credit_account_id = VALUES(credit_account_id),
                is_system = VALUES(is_system)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($mappings as $mapping) {
            $stmt->execute([
                ':id'                => $mapping[0],
                ':event_type'        => $mapping[1],
                ':description'       => $mapping[2],
                ':debit_account_id'  => $mapping[3],
                ':credit_account_id' => $mapping[4],
                ':is_system'         => $mapping[5],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                am.*,
                debit.account_code AS debit_account_code,
                debit.name AS debit_account_name,
                credit.account_code AS credit_account_code,
                credit.name AS credit_account_name
            FROM accounting_mappings am
            LEFT JOIN chart_of_accounts debit
                ON debit.id = am.debit_account_id
            LEFT JOIN chart_of_accounts credit
                ON credit.id = am.credit_account_id
            ORDER BY am.event_type ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}