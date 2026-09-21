<?php

namespace App\Seeders;

use PDO;

class ApprovalWorkflowSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $workflows = [
            [
                'id' => 'wf_loan_standard',
                'name' => 'Multi-Tier Loan Approval Workflow',
                'module' => 'Loans',
                'description' => 'Tiered loan approvals based on configurable principal thresholds',
                'active' => 1,
            ],
            [
                'id' => 'wf_expense_standard',
                'name' => 'Operational Expense Approval',
                'module' => 'Expenses',
                'description' => 'Approval hierarchy for cash and bank disbursements',
                'active' => 1,
            ],
            [
                'id' => 'wf_test_1789200177598',
                'name' => 'Emergency Micro-Credit Express Approval',
                'module' => 'Loans',
                'description' => 'Fast single-tier approval for calamity loans',
                'active' => 1,
            ],
            [
                'id' => 'wf_test_1789298974260',
                'name' => 'Emergency Micro-Credit Express Approval',
                'module' => 'Loans',
                'description' => 'Fast single-tier approval for calamity loans',
                'active' => 1,
            ],
        ];

        $sql = "
            INSERT INTO approval_workflows (
                id,
                name,
                module,
                description,
                active
            ) VALUES (
                :id,
                :name,
                :module,
                :description,
                :active
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                module = VALUES(module),
                description = VALUES(description),
                active = VALUES(active)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($workflows as $workflow) {
            $stmt->execute([
                ':id' => $workflow['id'],
                ':name' => $workflow['name'],
                ':module' => $workflow['module'],
                ':description' => $workflow['description'],
                ':active' => $workflow['active'],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                id,
                name,
                module,
                description,
                active
            FROM approval_workflows
            ORDER BY module ASC, name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}