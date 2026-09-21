<?php

namespace App\Seeders;

use PDO;

class ApprovalRuleSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $rules = [
            [
                'id' => 'rule_loan_tier1',
                'workflow_id' => 'wf_loan_standard',
                'level_name' => 'Tier 1 - Express Micro Loan',
                'minimum_amount' => 0,
                'maximum_amount' => 50000,
                'required_role' => 'Loan Officer',
                'required_approvals' => 1,
                'order' => 1,
                'active' => true,
            ],
            [
                'id' => 'rule_loan_tier2',
                'workflow_id' => 'wf_loan_standard',
                'level_name' => 'Tier 2 - Branch Manager Approval',
                'minimum_amount' => 50000.01,
                'maximum_amount' => 150000,
                'required_role' => 'Branch Manager',
                'required_approvals' => 1,
                'order' => 2,
                'active' => true,
            ],
            [
                'id' => 'rule_loan_tier3',
                'workflow_id' => 'wf_loan_standard',
                'level_name' => 'Tier 3 - Credit Committee & Board',
                'minimum_amount' => 150000.01,
                'maximum_amount' => 10000000,
                'required_role' => 'Board of Directors',
                'required_approvals' => 2,
                'order' => 3,
                'active' => true,
            ],
        ];

        $sql = "
            INSERT INTO `approval_rules` (
                `id`,
                `workflow_id`,
                `level_name`,
                `minimum_amount`,
                `maximum_amount`,
                `required_role`,
                `required_approvals`,
                `order`,
                `active`
            ) VALUES (
                :id,
                :workflow_id,
                :level_name,
                :minimum_amount,
                :maximum_amount,
                :required_role,
                :required_approvals,
                :order,
                :active
            )
            ON DUPLICATE KEY UPDATE
                `workflow_id` = VALUES(`workflow_id`),
                `level_name` = VALUES(`level_name`),
                `minimum_amount` = VALUES(`minimum_amount`),
                `maximum_amount` = VALUES(`maximum_amount`),
                `required_role` = VALUES(`required_role`),
                `required_approvals` = VALUES(`required_approvals`),
                `order` = VALUES(`order`),
                `active` = VALUES(`active`)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($rules as $rule) {
            $stmt->execute([
                ':id' => $rule['id'],
                ':workflow_id' => $rule['workflow_id'],
                ':level_name' => $rule['level_name'],
                ':minimum_amount' => $rule['minimum_amount'],
                ':maximum_amount' => $rule['maximum_amount'],
                ':required_role' => $rule['required_role'],
                ':required_approvals' => $rule['required_approvals'],
                ':order' => $rule['order'],
                ':active' => $rule['active'] ? 1 : 0,
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                `id`,
                `workflow_id`,
                `level_name`,
                `minimum_amount`,
                `maximum_amount`,
                `required_role`,
                `required_approvals`,
                `order`,
                `active`
            FROM `approval_rules`
            ORDER BY
                `workflow_id` ASC,
                `order` ASC
        ");

        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rules as &$rule) {
            $rule['minimum_amount'] = (float) $rule['minimum_amount'];
            $rule['maximum_amount'] = (float) $rule['maximum_amount'];
            $rule['required_approvals'] = (int) $rule['required_approvals'];
            $rule['order'] = (int) $rule['order'];
            $rule['active'] = (bool) $rule['active'];
        }

        return $rules;
    }
}