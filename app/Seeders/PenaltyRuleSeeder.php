<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class PenaltyRuleSeeder
{
    public function __construct(private PDO $db) {}

    public function run(): array
    {
     $rules = [
        [
            'pen_default',
            'Standard Default Amortization Penalty',
            5,
            2.00,
            'Overdue Principal',
            'None',
            'acc_4130',
        ],
        [
            'pen_emergency',
            'Emergency Loan Late Payment Penalty',
            3,
            2.00,
            'Overdue Principal',
            'None',
            'acc_4130',
        ],
        [
            'pen_regular',
            'Regular Loan Delinquency Penalty',
            5,
            2.00,
            'Total Overdue Installment',
            'None',
            'acc_4130',
        ],
        [
            'pen_agricultural',
            'Agricultural Loan Delinquency Penalty',
            15,
            1.00,
            'Overdue Principal',
            'None',
            'acc_4130',
        ],
        [
            'pen_restructured',
            'Restructured Loan Penalty',
            7,
            1.00,
            'Overdue Principal',
            'None',
            'acc_4130',
        ],
        [
            'pen_severe',
            'Severe Delinquency Penalty',
            30,
            3.00,
            'Overdue Principal',
            'Monthly',
            'acc_4130',
        ],
        [
            'pen_grace',
            'Extended Grace Period Penalty',
            15,
            0.00,
            'Total Overdue Installment',
            'None',
            'acc_4130',
        ],
    ];

        $sql = "
            INSERT INTO penalty_rules (
                id,
                name,
                grace_period_days,
                penalty_rate_percentage,
                calculation_base,
                compounding_frequency,
                gl_income_account_id
            )
            VALUES (
                :id,
                :name,
                :grace_period_days,
                :penalty_rate_percentage,
                :calculation_base,
                :compounding_frequency,
                :gl_income_account_id
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                grace_period_days = VALUES(grace_period_days),
                penalty_rate_percentage = VALUES(penalty_rate_percentage),
                calculation_base = VALUES(calculation_base),
                compounding_frequency = VALUES(compounding_frequency),
                gl_income_account_id = VALUES(gl_income_account_id)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($rules as $rule) {
            $stmt->execute([
                ':id'                     => $rule[0],
                ':name'                   => $rule[1],
                ':grace_period_days'      => $rule[2],
                ':penalty_rate_percentage' => $rule[3],
                ':calculation_base'       => $rule[4],
                ':compounding_frequency'  => $rule[5],
                ':gl_income_account_id'   => $rule[6],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                pr.*,
                coa.account_code AS income_account_code,
                coa.name AS income_account_name
            FROM penalty_rules pr
            LEFT JOIN chart_of_accounts coa
                ON coa.id = pr.gl_income_account_id
            ORDER BY pr.name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
