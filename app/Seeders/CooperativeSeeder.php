<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class CooperativeSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $cooperatives = [
            [
                'coop_01',
                'Mayap Care Agriculture Cooperative',
                'CDA-REG-CAR-2018-09142',
                '009-881-209-000',
                'Agricultural / Multi-Purpose',
                'National Highway, San Vicente, Tarlac City, Tarlac',
                '+63 (045) 982-1144',
                'contact@mayapcare.coop',
                '01-01',
                'PHP',
                '2026-09-12 08:07:46',
                '2026-09-12 08:07:46',
            ],
        ];

        $sql = "
            INSERT INTO cooperatives (
                id,
                name,
                cda_registration_no,
                tax_identification_no,
                coop_type,
                address,
                contact_phone,
                contact_email,
                fiscal_year_start,
                base_currency,
                created_at,
                updated_at
            )
            VALUES (
                :id,
                :name,
                :cda_registration_no,
                :tax_identification_no,
                :coop_type,
                :address,
                :contact_phone,
                :contact_email,
                :fiscal_year_start,
                :base_currency,
                :created_at,
                :updated_at
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                cda_registration_no = VALUES(cda_registration_no),
                tax_identification_no = VALUES(tax_identification_no),
                coop_type = VALUES(coop_type),
                address = VALUES(address),
                contact_phone = VALUES(contact_phone),
                contact_email = VALUES(contact_email),
                fiscal_year_start = VALUES(fiscal_year_start),
                base_currency = VALUES(base_currency),
                updated_at = VALUES(updated_at)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($cooperatives as $cooperative) {
            $stmt->execute([
                ':id'                   => $cooperative[0],
                ':name'                 => $cooperative[1],
                ':cda_registration_no'  => $cooperative[2],
                ':tax_identification_no' => $cooperative[3],
                ':coop_type'            => $cooperative[4],
                ':address'              => $cooperative[5],
                ':contact_phone'        => $cooperative[6],
                ':contact_email'        => $cooperative[7],
                ':fiscal_year_start'    => $cooperative[8],
                ':base_currency'        => $cooperative[9],
                ':created_at'           => $cooperative[10],
                ':updated_at'           => $cooperative[11],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM cooperatives
            ORDER BY name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}