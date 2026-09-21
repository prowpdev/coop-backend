<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class DocumentRequirementSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $documents = [
            [
                'doc_billing',
                'Members',
                'Proof of Billing / Residence Certificate',
                1,
                1,
            ],
            [
                'doc_income',
                'Loans',
                'Proof of Income / Income Tax Return / Crop Harvest Log',
                1,
                1,
            ],
            [
                'doc_pmes',
                'Members',
                'Pre-Membership Education Seminar (PMES) Certificate',
                1,
                1,
            ],
            [
                'doc_promissory',
                'Loans',
                'Signed Promissory Note with Co-maker Agreement',
                1,
                1,
            ],
            [
                'doc_valid_id',
                'Members',
                'Government Issued Valid ID (Driver License, UMID, Passport)',
                1,
                1,
            ],
        ];

        $sql = "
            INSERT INTO document_requirements (
                id,
                module,
                name,
                is_mandatory,
                active
            )
            VALUES (
                :id,
                :module,
                :name,
                :is_mandatory,
                :active
            )
            ON DUPLICATE KEY UPDATE
                module = VALUES(module),
                name = VALUES(name),
                is_mandatory = VALUES(is_mandatory),
                active = VALUES(active)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($documents as $document) {
            $stmt->execute([
                ':id'           => $document[0],
                ':module'       => $document[1],
                ':name'         => $document[2],
                ':is_mandatory' => $document[3],
                ':active'       => $document[4],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM document_requirements
            ORDER BY module ASC, name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}