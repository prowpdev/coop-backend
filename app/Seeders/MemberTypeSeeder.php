<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class MemberTypeSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $memberTypes = [
            [
                'mt_associate',
                'Associate Member',
                'ASSOCIATE',
                'Non-voting member enjoying savings deposit and credit facilities',
                0,
                5000.00,
                500.00,
                1,
                json_encode([
                    'Valid Gov ID',
                    '2x2 ID Photo',
                    'Proof of Billing',
                ]),
                1,
            ],
            [
                'mt_lab',
                'Laboratory / Youth Member',
                'LAB_YOUTH',
                'Minor/Student savings depositor preparing for future cooperative participation',
                0,
                500.00,
                200.00,
                0,
                json_encode([
                    'Birth Certificate',
                    'Parents Consent Form',
                ]),
                1,
            ],
            [
                'mt_regular',
                'Regular Member',
                'REGULAR',
                'Full-fledged member with voting rights and dividend participation',
                1,
                10000.00,
                1000.00,
                1,
                json_encode([
                    'Valid Gov ID',
                    '2x2 ID Photo',
                    'Proof of Billing',
                    'PMES Certificate',
                ]),
                1,
            ],
        ];

        $sql = "
            INSERT INTO member_types (
                id,
                name,
                code,
                description,
                voting_rights,
                min_share_capital,
                savings_requirement,
                loan_eligibility,
                required_documents,
                active
            )
            VALUES (
                :id,
                :name,
                :code,
                :description,
                :voting_rights,
                :min_share_capital,
                :savings_requirement,
                :loan_eligibility,
                :required_documents,
                :active
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                code = VALUES(code),
                description = VALUES(description),
                voting_rights = VALUES(voting_rights),
                min_share_capital = VALUES(min_share_capital),
                savings_requirement = VALUES(savings_requirement),
                loan_eligibility = VALUES(loan_eligibility),
                required_documents = VALUES(required_documents),
                active = VALUES(active)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($memberTypes as $memberType) {
            $stmt->execute([
                ':id'                => $memberType[0],
                ':name'              => $memberType[1],
                ':code'              => $memberType[2],
                ':description'       => $memberType[3],
                ':voting_rights'     => $memberType[4],
                ':min_share_capital' => $memberType[5],
                ':savings_requirement' => $memberType[6],
                ':loan_eligibility'  => $memberType[7],
                ':required_documents' => $memberType[8],
                ':active'            => $memberType[9],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM member_types
            ORDER BY name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}