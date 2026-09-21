<?php

namespace App\Seeders;

use PDO;

class PaymentAllocationRuleSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $rules = [
            [
                'id' => 'par_default',
                'name' => 'Standard CDA Priority Allocation',
                'priority_order' => [
                    [
                        'priority' => 1,
                        'component' => 'Penalty',
                        'label' => 'Penalties & Late Fines',
                    ],
                    [
                        'priority' => 2,
                        'component' => 'Interest',
                        'label' => 'Accrued Interest',
                    ],
                    [
                        'priority' => 3,
                        'component' => 'Fees',
                        'label' => 'Service & Other Fees',
                    ],
                    [
                        'priority' => 4,
                        'component' => 'Principal',
                        'label' => 'Loan Principal Balance',
                    ],
                ],
                'is_default' => 1,
                'active' => 1,
            ],

            [
                'id' => 'par_borrower_favorable',
                'name' => 'Principal-First Relief Allocation',
                'priority_order' => [
                    [
                        'priority' => 1,
                        'component' => 'Interest',
                        'label' => 'Accrued Interest',
                    ],
                    [
                        'priority' => 2,
                        'component' => 'Principal',
                        'label' => 'Loan Principal Balance',
                    ],
                    [
                        'priority' => 3,
                        'component' => 'Penalty',
                        'label' => 'Penalties & Late Fines',
                    ],
                    [
                        'priority' => 4,
                        'component' => 'Fees',
                        'label' => 'Service & Other Fees',
                    ],
                ],
                'is_default' => 0,
                'active' => 1,
            ],
        ];

        $sql = "
            INSERT INTO payment_allocation_rules (
                id,
                name,
                priority_order,
                is_default,
                active
            ) VALUES (
                :id,
                :name,
                :priority_order,
                :is_default,
                :active
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                priority_order = VALUES(priority_order),
                is_default = VALUES(is_default),
                active = VALUES(active)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($rules as $rule) {
            $stmt->execute([
                ':id' => $rule['id'],
                ':name' => $rule['name'],
                ':priority_order' => json_encode(
                    $rule['priority_order'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                ':is_default' => $rule['is_default'],
                ':active' => $rule['active'],
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
                priority_order,
                is_default,
                active
            FROM payment_allocation_rules
            ORDER BY is_default DESC, name ASC
        ");

        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rules as &$rule) {
            $rule['priority_order'] = json_decode(
                $rule['priority_order'] ?? '[]',
                true
            ) ?? [];

            $rule['is_default'] = (bool) $rule['is_default'];
            $rule['active'] = (bool) $rule['active'];
        }

        return $rules;
    }
}