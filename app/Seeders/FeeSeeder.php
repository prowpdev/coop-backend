<?php

namespace App\Seeders;

use PDO;

class FeeSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $fees = [
            [
                'id' => 'fee_05a86a81',
                'code' => 'FEE-724',
                'name' => 'Loan Processing Fee',
                'calculation_type' => 'Percentage',
                'amount' => 0.00,
                'applies_to' => 'loan',
                'percentage' => 2.00,
                'gl_account_id' => 'acc_4110',
                'active' => 1,
            ],
            [
                'id' => 'fee_c0f73b0e',
                'code' => 'FEE-MEMB',
                'name' => 'Cooperative Membership Entrance Fee',
                'calculation_type' => 'Fixed',
                'amount' => 500.00,
                'applies_to' => 'Loans',
                'percentage' => 0.00,
                'gl_account_id' => 'acc_4140',
                'active' => 1,
            ],
        ];

        $sql = "
            INSERT INTO fees (
                id,
                code,
                name,
                calculation_type,
                amount,
                applies_to,
                percentage,
                gl_account_id,
                active
            ) VALUES (
                :id,
                :code,
                :name,
                :calculation_type,
                :amount,
                :applies_to,
                :percentage,
                :gl_account_id,
                :active
            )
            ON DUPLICATE KEY UPDATE
                code = VALUES(code),
                name = VALUES(name),
                calculation_type = VALUES(calculation_type),
                amount = VALUES(amount),
                applies_to = VALUES(applies_to),
                percentage = VALUES(percentage),
                gl_account_id = VALUES(gl_account_id),
                active = VALUES(active)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($fees as $fee) {
            $stmt->execute([
                ':id' => $fee['id'],
                ':code' => $fee['code'],
                ':name' => $fee['name'],
                ':calculation_type' => $fee['calculation_type'],
                ':amount' => $fee['amount'],
                ':applies_to' => $fee['applies_to'],
                ':percentage' => $fee['percentage'],
                ':gl_account_id' => $fee['gl_account_id'],
                ':active' => $fee['active'],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                f.*,
                coa.account_code AS gl_account_code,
                coa.name AS gl_account_name
            FROM fees f
            LEFT JOIN chart_of_accounts coa
                ON coa.id = f.gl_account_id
            ORDER BY f.name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}