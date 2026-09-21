<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ConfigRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Fetch complete configuration bundle matching frontend initialization
     */
    public function getAllConfig(): array
    {
        return [
            'cooperatives'                  => $this->fetchAll('cooperatives'),
            'branches'                      => $this->fetchAll('branches'),
            'system_settings'               => $this->fetchAll('system_settings'),
            'feature_toggles'               => $this->fetchAll('feature_toggles'),
            'chart_of_accounts'             => $this->fetchAll('chart_of_accounts'),
            'accounting_mappings'           => $this->fetchAll('accounting_mappings'),
            'accounting_periods'            => $this->fetchAll('accounting_periods'),
            'numbering_formats'             => $this->fetchAll('numbering_formats'),
            'approval_workflows'            => $this->fetchAll('approval_workflows'),
            'approval_rules'                => $this->fetchAll('approval_rules'),
            'custom_fields'                 => $this->fetchAll('custom_fields'),
            'member_types'                  => $this->fetchAll('member_types'),
            'loan_products'                 => $this->fetchAll('loan_products'),
            'loan_product_versions'         => $this->fetchAll('loan_product_versions'),
            'savings_products'              => $this->fetchAll('savings_products'),
            'share_capital_settings'        => $this->fetchAll('share_capital_settings'),
            'cash_accounts'                 => $this->fetchAll('cash_accounts'),
            'fees'                          => $this->fetchAll('fees'),
            'penalty_rules'                 => $this->fetchAll('penalty_rules'),
            'payment_allocation_rules'      => $this->fetchAll('payment_allocation_rules'),
            'payment_frequencies'           => $this->fetchAll('payment_frequencies'),
            'document_requirements'         => $this->fetchAll('document_requirements'),
            'transaction_types'             => $this->fetchAll('transaction_types'),
            'user_roles'                    => $this->fetchAll('user_roles'),
            'users'                         => $this->fetchAll('users'),
            'configuration_audit_trails'    => $this->fetchAll('configuration_audit_trails')
        ];
    }

    private function fetchAll(string $table): array
    {
        try {
            $stmt = $this->db->query("SELECT * FROM `{$table}`");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    public function getBranches(): array
    {
        return $this->fetchAll('branches');
    }

    public function saveBranch(array $data): array
    {
        $id = $data['id'] ?? ('br_' . bin2hex(random_bytes(4)));
        $sql = "
            INSERT INTO branches (id, code, name, address, contact_number, manager_name, is_main_branch, active)
            VALUES (:id, :code, :name, :address, :contact_number, :manager_name, :is_main_branch, :active)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                address = VALUES(address),
                contact_number = VALUES(contact_number),
                manager_name = VALUES(manager_name),
                active = VALUES(active)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id'             => $id,
            'code'           => $data['code'],
            'name'           => $data['name'],
            'address'        => $data['address'] ?? '',
            'contact_number' => $data['contact_number'] ?? null,
            'manager_name'   => $data['manager_name'] ?? null,
            'is_main_branch' => !empty($data['is_main_branch']) ? 1 : 0,
            'active'         => isset($data['active']) ? (int)$data['active'] : 1
        ]);

        $res = $this->db->prepare("SELECT * FROM branches WHERE id = ?");
        $res->execute([$id]);
        return $res->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    
    public function getFee(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM fees WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return array_merge($row, [
            'fixed_amount' => (float)($row['amount'] ?? 0),
            'percentage' => (float)($row['percentage'] ?? ($row['calculation_type'] === 'Percentage' ? $row['amount'] : 0)),
            'rate' => (float)($row['percentage'] ?? ($row['calculation_type'] === 'Percentage' ? $row['amount'] : 0)),
            'applicable_module' => $row['applies_to'] ?? 'Loans',
            'accounting_account_id' => $row['gl_account_id'] ?? null,
            'active' => (bool)($row['active'] ?? true)
        ]);
    }

    public function deleteFee(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM fees WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getLoanProducts(): array
    {
        return $this->fetchAll('loan_products');
    }

    public function getSavingsProducts(): array
    {
        return $this->fetchAll('savings_products');
    }

    public function getSystemSettings(): array
    {
        return $this->fetchAll('system_settings');
    }

    public function getFeatureToggles(): array
    {
        return $this->fetchAll('feature_toggles');
    }

    public function updateFeatureToggle(string $featureKey, bool $enabled): bool
    {
        $stmt = $this->db->prepare("UPDATE feature_toggles SET enabled = ? WHERE feature_key = ?");
        return $stmt->execute([$enabled ? 1 : 0, $featureKey]);
    }

  

    public function deleteLoanProduct(string $id): bool
    {
        return $this->db->prepare("DELETE FROM loan_products WHERE id = ?")->execute([$id]);
    }
    //
    public function saveSavingsProduct(array $data): array
    {
        $id = $data['id'] ?? ('sp_' . bin2hex(random_bytes(4)));
        $sql = "
            INSERT INTO savings_products (
                id, code, name, min_balance_to_earn_interest, annual_interest_rate,
                interest_calculation_method, min_opening_deposit, maintaining_balance,
                gl_liability_account_id, gl_interest_expense_account_id, active
            ) VALUES (
                :id, :code, :name, :min_balance_to_earn_interest, :annual_interest_rate,
                :interest_calculation_method, :min_opening_deposit, :maintaining_balance,
                :gl_liability_account_id, :gl_interest_expense_account_id, :active
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                min_balance_to_earn_interest = VALUES(min_balance_to_earn_interest),
                annual_interest_rate = VALUES(annual_interest_rate),
                interest_calculation_method = VALUES(interest_calculation_method),
                min_opening_deposit = VALUES(min_opening_deposit),
                maintaining_balance = VALUES(maintaining_balance),
                gl_liability_account_id = VALUES(gl_liability_account_id),
                gl_interest_expense_account_id = VALUES(gl_interest_expense_account_id),
                active = VALUES(active)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id'                       => $id,
            'code'                     => $data['code'] ?? ('SP-' . mt_rand(100, 999)),
            'name'                     => $data['name'],
            'min_balance_to_earn_interest' => (float)($data['min_balance_to_earn_interest'] ?? 1000),
            'annual_interest_rate'         => (float)($data['annual_interest_rate'] ?? 2),
            'interest_calculation_method' => $data['interest_calculation_method'] ?? 'Average Daily Balance',
            'min_opening_deposit'          => (float)($data['min_opening_deposit'] ?? 500),
            'maintaining_balance'          => (float)($data['maintaining_balance'] ?? 500),
            'gl_liability_account_id'      => $data['gl_liability_account_id'] ?? '',
            'gl_interest_expense_account_id' => $data['gl_interest_expense_account_id'] ?? '',
            'active'                       => isset($data['active']) ? (int)(bool)$data['active'] : 1
        ]);
        $saved = $this->db->prepare('SELECT * FROM savings_products WHERE id = ?');
        $saved->execute([$id]);
        return $saved->fetch(PDO::FETCH_ASSOC) ?: array_merge(['id' => $id], $data);
    }


    public function deleteSavingsProduct(string $id): bool
    {
        return $this->db->prepare("DELETE FROM savings_products WHERE id = ?")->execute([$id]);
    }

    public function getFees(): array
    {
        return $this->fetchAll('fees');
    }

public function saveFee(array $data): array
{
    $id = $data['id'] ?? ('fee_' . bin2hex(random_bytes(4)));

    $code = !empty($data['code'])
        ? $data['code']
        : ('FEE-' . mt_rand(100, 999));

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
        )
        VALUES (
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

    $stmt->execute([
        'id' => $id,
        'code' => $code,
        'name' => $data['name'],
        'calculation_type' => $data['calculation_type'] ?? 'Fixed',
        'amount' => (float) (
            $data['amount']
            ?? $data['fixed_amount']
            ?? 0
        ),
        'applies_to' => $data['applicable_module'] ?? 'loan',
        'gl_account_id' => $data['gl_account_id'] ?? null,
        'percentage' => (float) ($data['percentage'] ?? 0),
        'active' => isset($data['active'])
            ? (int) $data['active']
            : 1,
    ]);

    return array_merge([
        'id' => $id,
        'code' => $code,
    ], $data);
}

    public function updateSystemSettings(array $data): bool
    {
        foreach ($data as $key => $val) {
            $stmt = $this->db->prepare("
                INSERT INTO system_settings (id, `key`, `value`)
                VALUES (:id, :key, :value)
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
            ");
            $stmt->execute([
                'id'    => 'set_' . preg_replace('/[^a-zA-Z0-9_]/', '', $key),
                'key'   => $key,
                'value' => is_string($val) ? $val : json_encode($val)
            ]);
        }
        return true;
    }

    public function getApprovalRules(): array
    {
        return $this->fetchAll('approval_rules');
    }

    public function saveApprovalRule(array $data): array
    {
        $id = $data['id'] ?? ('ar_' . bin2hex(random_bytes(4)));
        $sql = "
            INSERT INTO approval_rules (id, workflow_id, min_amount, max_amount, required_role, step_order)
            VALUES (:id, :workflow_id, :min_amount, :max_amount, :required_role, :step_order)
            ON DUPLICATE KEY UPDATE
                min_amount = VALUES(min_amount),
                max_amount = VALUES(max_amount),
                required_role = VALUES(required_role),
                step_order = VALUES(step_order)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id'            => $id,
            'workflow_id'   => $data['workflow_id'] ?? 'wf_loan_origination',
            'min_amount'    => (float)($data['min_amount'] ?? 0),
            'max_amount'    => (float)($data['max_amount'] ?? 1000000),
            'required_role' => $data['required_role'] ?? 'Loan Officer',
            'step_order'    => (int)($data['step_order'] ?? 1)
        ]);
        return array_merge(['id' => $id], $data);
    }

    public function getApprovalWorkflows(): array
    {
        return $this->fetchAll('approval_workflows');
    }

    public function getCustomFields(): array
    {
        return $this->fetchAll('custom_fields');
    }

    public function saveCustomField(array $data): array
    {
        $id = $data['id'] ?? ('cf_' . bin2hex(random_bytes(4)));
        $sql = "
            INSERT INTO custom_fields (id, entity_type, field_name, field_label, field_type, is_required)
            VALUES (:id, :entity_type, :field_name, :field_label, :field_type, :is_required)
            ON DUPLICATE KEY UPDATE
                field_label = VALUES(field_label),
                field_type = VALUES(field_type),
                is_required = VALUES(is_required)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id'          => $id,
            'entity_type' => $data['entity_type'] ?? 'member',
            'field_name'  => $data['field_name'] ?? ('custom_' . mt_rand(100, 999)),
            'field_label' => $data['field_label'] ?? 'Custom Field',
            'field_type'  => $data['field_type'] ?? 'text',
            'is_required' => !empty($data['is_required']) ? 1 : 0
        ]);
        return array_merge(['id' => $id], $data);
    }

    public function updateNumberingFormat(string $id, array $data): array
    {
        $stmt = $this->db->prepare("
            UPDATE numbering_formats
            SET prefix = :prefix, next_sequence = :next_sequence, padding = :padding
            WHERE id = :id OR entity_type = :id
        ");
        $stmt->execute([
            'id'            => $id,
            'prefix'        => $data['prefix'] ?? '',
            'next_sequence' => (int)($data['next_sequence'] ?? 1),
            'padding'       => (int)($data['padding'] ?? 5)
        ]);
        return array_merge(['id' => $id], $data);
    }
    /**
     * PUT /api/config/payment-allocation-rules/:id
     */
    public function updatePaymentAllocationRule(string $id, array $data): array
    {
        // The actual priority list is inside "data"
        $priorityOrder = $data['priorities'] ?? [];

        if (!is_array($priorityOrder)) {
            throw new \InvalidArgumentException(
                'priority_order data must be an array.'
            );
        }
        // Normalize / validate the priority items
        $priorityOrder = array_values(
            array_map(
                static function (array $item, int $index): array {
                    return [
                        'label' => (string) ($item['label'] ?? ''),
                        'priority' => (int) ($item['priority'] ?? ($index + 1)),
                        'component' => (string) ($item['component'] ?? ''),
                    ];
                },
                $priorityOrder,
                array_keys($priorityOrder)
            )
        );
        usort(
        $priorityOrder,
        static function (array $a, array $b): int {
            return $a['priority'] <=> $b['priority'];
        }
        );
        $stmt = $this->db->prepare("
            UPDATE payment_allocation_rules
            SET
                priority_order = :priority_order,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $id,
            ':priority_order' => json_encode(
                $priorityOrder
            ),
        ]);
    
        if ($stmt->rowCount() === 0) {
            // Check whether the ID actually exists
            $check = $this->db->prepare("
                SELECT id
                FROM payment_allocation_rules
                WHERE id = :id
                LIMIT 1
            ");

            $check->execute([':id' => $id]);

            if (!$check->fetch(PDO::FETCH_ASSOC)) {
                throw new \RuntimeException(
                    "Payment allocation rule '{$id}' not found."
                );
            }
        }

        return [
            'id' => $id,
            'priority_order' => $priorityOrder,
        ];
    }
    /**
     * Fetch payment allocation rules for a specific allocation type
     */
    public function getAllocationRules(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM payment_allocation_rules ORDER BY priority_order ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
        public function saveLoanProduct(array $data): array
    {
        $id = $data['id'] ?? ('lp_' . bin2hex(random_bytes(4)));
        $sql = "
            INSERT INTO loan_products (id, code, name, description, version, min_amount, max_amount, min_term_months, max_term_months, annual_interest_rate, interest_calculation_method, payment_frequency, grace_period_days, penalty_rate_percentage, gl_receivable_account_id, gl_interest_income_account_id, active)
            VALUES (:id, :code, :name, :description, :version, :min_amount, :max_amount, :min_term_months, :max_term_months, :annual_interest_rate, :interest_calculation_method, :payment_frequency, :grace_period_days, :penalty_rate_percentage, :gl_receivable_account_id, :gl_interest_income_account_id, :active)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                version = version + 1,
                min_amount = VALUES(min_amount),
                max_amount = VALUES(max_amount),
                min_term_months = VALUES(min_term_months),
                max_term_months = VALUES(max_term_months),
                annual_interest_rate = VALUES(annual_interest_rate),
                interest_calculation_method = VALUES(interest_calculation_method),
                payment_frequency = VALUES(payment_frequency),
                grace_period_days = VALUES(grace_period_days),
                penalty_rate_percentage = VALUES(penalty_rate_percentage),
                gl_receivable_account_id = VALUES(gl_receivable_account_id),
                gl_interest_income_account_id = VALUES(gl_interest_income_account_id),
                active = VALUES(active)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id'                          => $id,
            'code'                        => $data['code'] ?? ('LP-' . mt_rand(100, 999)),
            'name'                        => $data['name'],
            'description'                 => $data['description'] ?? '',
            'version'                     => (int)($data['version'] ?? 1),
            'min_amount'                  => (float)($data['min_amount'] ?? 5000),
            'max_amount'                  => (float)($data['max_amount'] ?? 500000),
            'min_term_months'             => (int)($data['min_term_months'] ?? 1),
            'max_term_months'             => (int)($data['max_term_months'] ?? $data['default_term_months'] ?? 60),
            'annual_interest_rate'        => (float)($data['annual_interest_rate'] ?? 6),
            'interest_calculation_method' => $data['interest_calculation_method'] ?? 'Diminishing Balance',
            'payment_frequency'           => $data['payment_frequency'] ?? 'Monthly',
            'grace_period_days'           => (int)($data['grace_period_days'] ?? 0),
            'penalty_rate_percentage'     => (float)($data['penalty_rate_percentage'] ?? 2),
            'gl_receivable_account_id'    => $data['gl_receivable_account_id'] ?? $data['debit_account_id'] ?? '',
            'gl_interest_income_account_id' => $data['gl_interest_income_account_id'] ?? '',
            'active'                      => isset($data['active']) ? (int)(bool)$data['active'] : 1
        ]);
        return $this->getLoanProduct($id);
    }
        public function getLoanProduct(string $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM loan_products WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
