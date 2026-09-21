<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class TransactionTypeSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $transactionTypes = [
            [
                'tx_loan_pmt',
                'LOAN_PAYMENT',
                'Loan Amortization Collection',
                'Loans',
                0,
                'num_or',
                1,
            ],
            [
                'tx_loan_rel',
                'LOAN_RELEASE',
                'Loan Disbursement Voucher',
                'Loans',
                1,
                'num_cd',
                1,
            ],
            [
                'tx_open_bal',
                'OPENING_BALANCE',
                'Opening Balance Journal Entry',
                'Accounting',
                1,
                'num_jv',
                1,
            ],
            [
                'tx_sav_dep',
                'SAVINGS_DEPOSIT',
                'Savings Deposit Slip',
                'Savings',
                0,
                'num_or',
                1,
            ],
            [
                'tx_sav_with',
                'SAVINGS_WITHDRAWAL',
                'Savings Cash Withdrawal Voucher',
                'Savings',
                1,
                'num_cd',
                1,
            ],
            [
                'tx_sc_pay',
                'SHARE_CAPITAL_PAYMENT',
                'Capital Build-Up OR',
                'ShareCapital',
                0,
                'num_or',
                1,
            ],
        ];

        $sql = "
            INSERT INTO transaction_types (
                id,
                code,
                name,
                module,
                requires_approval,
                numbering_format_id,
                active
            )
            VALUES (
                :id,
                :code,
                :name,
                :module,
                :requires_approval,
                :numbering_format_id,
                :active
            )
            ON DUPLICATE KEY UPDATE
                code = VALUES(code),
                name = VALUES(name),
                module = VALUES(module),
                requires_approval = VALUES(requires_approval),
                numbering_format_id = VALUES(numbering_format_id),
                active = VALUES(active)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($transactionTypes as $transactionType) {
            $stmt->execute([
                ':id'                  => $transactionType[0],
                ':code'                => $transactionType[1],
                ':name'                => $transactionType[2],
                ':module'              => $transactionType[3],
                ':requires_approval'  => $transactionType[4],
                ':numbering_format_id' => $transactionType[5],
                ':active'              => $transactionType[6],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM transaction_types
            ORDER BY module ASC, code ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}