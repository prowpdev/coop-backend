<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class SystemSettingSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $settings = [
            [
                'set_cur',
                'currency',
                'PHP',
                'General',
                'Primary operating currency symbol',
                'System Administrator',
                '2026-09-12 08:07:46',
            ],
            [
                'set_fy',
                'fiscal_year_start',
                '01-01',
                'Accounting',
                'Start of fiscal accounting calendar (MM-DD)',
                'System Administrator',
                '2026-09-12 08:07:46',
            ],
            [
                'set_grace',
                'global_grace_period_days',
                '5',
                'Loan Policy',
                'Global grace period before loan delinquency penalties apply',
                'System Administrator',
                '2026-09-12 08:07:46',
            ],
            [
                'set_pen_comp',
                'penalty_compounding',
                'false',
                'Loan Policy',
                'Whether penalties compound into principal monthly',
                'System Administrator',
                '2026-09-12 08:07:46',
            ],
            [
                'set_sc_max',
                'max_share_holding_percentage',
                '10',
                'Regulatory',
                'CDA maximum percentage of total share capital any single member may own',
                'System Administrator',
                '2026-09-12 08:07:46',
            ],
        ];

        $sql = "
            INSERT INTO system_settings (
                id,
                setting_key,
                setting_value,
                setting_group,
                description,
                updated_by,
                updated_at
            )
            VALUES (
                :id,
                :setting_key,
                :setting_value,
                :setting_group,
                :description,
                :updated_by,
                :updated_at
            )
            ON DUPLICATE KEY UPDATE
                setting_key = VALUES(setting_key),
                setting_value = VALUES(setting_value),
                setting_group = VALUES(setting_group),
                description = VALUES(description),
                updated_by = VALUES(updated_by),
                updated_at = VALUES(updated_at)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($settings as $setting) {
            $stmt->execute([
                ':id'            => $setting[0],
                ':setting_key'   => $setting[1],
                ':setting_value' => $setting[2],
                ':setting_group' => $setting[3],
                ':description'   => $setting[4],
                ':updated_by'    => $setting[5],
                ':updated_at'   => $setting[6],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM system_settings
            ORDER BY setting_group ASC, setting_key ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}