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
}
