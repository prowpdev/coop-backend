<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class ConfigurationAuditTrailSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $auditTrails = [
            [
                'audit_init_01',
                'Cooperative SQL Initialization',
                'None',
                'Clean Schema & Master Seeds Created',
                'System Administrator',
                'Deployment of SQL database for PHP MVC backend integration',
                '2026-09-12 08:07:46',
            ],
        ];

        $sql = "
            INSERT INTO configuration_audit_trails (
                id,
                setting,
                old_value,
                new_value,
                changed_by,
                reason,
                created_at
            )
            VALUES (
                :id,
                :setting,
                :old_value,
                :new_value,
                :changed_by,
                :reason,
                :created_at
            )
            ON DUPLICATE KEY UPDATE
                setting = VALUES(setting),
                old_value = VALUES(old_value),
                new_value = VALUES(new_value),
                changed_by = VALUES(changed_by),
                reason = VALUES(reason)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($auditTrails as $audit) {
            $stmt->execute([
                ':id'         => $audit[0],
                ':setting'    => $audit[1],
                ':old_value'  => $audit[2],
                ':new_value'  => $audit[3],
                ':changed_by' => $audit[4],
                ':reason'     => $audit[5],
                ':created_at' => $audit[6],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM configuration_audit_trails
            ORDER BY created_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}