<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class FeatureToggleSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $features = [
            [
                'feat_appr',
                'feature_approval_workflows',
                'Tiered Approval Workflows',
                'Multi-step role-based authorization for loans and capital adjustments',
                'Security',
                1,
                '2026-09-12 08:07:46',
            ],
            [
                'feat_cf',
                'feature_custom_fields',
                'Custom Member Fields',
                'Enable dynamic custom fields for member profiles without code changes',
                'Members',
                1,
                '2026-09-12 08:07:46',
            ],
            [
                'feat_notif',
                'feature_notification_rules',
                'Automated SMS / Push Triggers',
                'Trigger alerts on loan approvals, overdue payments, and scheduled dues',
                'Communications',
                1,
                '2026-09-12 08:07:46',
            ],
            [
                'feat_sub',
                'feature_subsidiary_ledger',
                'Subsidiary Ledger Tracking',
                'Granular accounting subsidiary breakdown by member, loan, and cash drawers',
                'Accounting',
                1,
                '2026-09-12 08:07:46',
            ],
        ];

        $sql = "
            INSERT INTO feature_toggles (
                id,
                feature_key,
                name,
                description,
                category,
                enabled,
                updated_at
            )
            VALUES (
                :id,
                :feature_key,
                :name,
                :description,
                :category,
                :enabled,
                :updated_at
            )
            ON DUPLICATE KEY UPDATE
                feature_key = VALUES(feature_key),
                name = VALUES(name),
                description = VALUES(description),
                category = VALUES(category),
                enabled = VALUES(enabled),
                updated_at = VALUES(updated_at)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($features as $feature) {
            $stmt->execute([
                ':id'          => $feature[0],
                ':feature_key' => $feature[1],
                ':name'        => $feature[2],
                ':description' => $feature[3],
                ':category'    => $feature[4],
                ':enabled'     => $feature[5],
                ':updated_at'  => $feature[6],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM feature_toggles
            ORDER BY category ASC, name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}