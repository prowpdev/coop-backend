<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class CustomFieldSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $fields = [
            [
                'cf_mem_01',
                'Member',
                'occupation',
                'Primary Occupation / Enterprise',
                'Select',
                json_encode([
                    'Farmer / Fisherfolk',
                    'Self-Employed / Entrepreneur',
                    'Government Employee',
                    'Private Sector Employee',
                    'Healthcare Professional',
                    'OFW / Remittance Dependent',
                    'Retired',
                ]),
                1,
                1,
                1,
            ],
            [
                'cf_mem_02',
                'Member',
                'barangay',
                'Barangay / Village Residence',
                'Text',
                null,
                1,
                1,
                2,
            ],
            [
                'cf_mem_03',
                'Member',
                'monthly_income',
                'Estimated Monthly Household Income (PHP)',
                'Number',
                null,
                1,
                1,
                3,
            ],
            [
                'cf_mem_04',
                'Member',
                'tin_number',
                'Tax Identification Number (TIN)',
                'Text',
                null,
                0,
                1,
                4,
            ],
        ];

        $sql = "
            INSERT INTO custom_fields (
                id,
                entity_type,
                field_key,
                label,
                field_type,
                options,
                is_required,
                active,
                display_order
            )
            VALUES (
                :id,
                :entity_type,
                :field_key,
                :label,
                :field_type,
                :options,
                :is_required,
                :active,
                :display_order
            )
            ON DUPLICATE KEY UPDATE
                entity_type = VALUES(entity_type),
                field_key = VALUES(field_key),
                label = VALUES(label),
                field_type = VALUES(field_type),
                options = VALUES(options),
                is_required = VALUES(is_required),
                active = VALUES(active),
                display_order = VALUES(display_order)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($fields as $field) {
            $stmt->execute([
                ':id'           => $field[0],
                ':entity_type'  => $field[1],
                ':field_key'    => $field[2],
                ':label'        => $field[3],
                ':field_type'   => $field[4],
                ':options'      => $field[5],
                ':is_required'  => $field[6],
                ':active'       => $field[7],
                ':display_order' => $field[8],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM custom_fields
            ORDER BY entity_type ASC, display_order ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}