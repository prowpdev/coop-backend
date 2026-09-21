<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class NumberingFormatSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $formats = [
            [
                'num_cbu',
                'ShareCapital',
                'CBU',
                1,
                1,
                4,
                1,
                '{BRANCH}-CBU-{YEAR}-{NUMBER}',
            ],
            [
                'num_cd',
                'Disbursements',
                'CD',
                1,
                1,
                6,
                1,
                '{BRANCH}-CD-{YEAR}-{NUMBER}',
            ],
            [
                'num_jv',
                'Journal',
                'JV',
                1,
                1,
                6,
                1,
                '{BRANCH}-JV-{YEAR}-{NUMBER}',
            ],
            [
                'num_loan',
                'Loans',
                'LN',
                1,
                1,
                5,
                1,
                '{BRANCH}-LN-{YEAR}-{NUMBER}',
            ],
            [
                'num_mem',
                'Members',
                'MEM',
                0,
                1,
                5,
                1,
                'MEM-{YEAR}-{NUMBER}',
            ],
            [
                'num_or',
                'Receipts',
                'OR',
                1,
                1,
                6,
                1,
                '{BRANCH}-OR-{YEAR}-{NUMBER}',
            ],
            [
                'num_sa',
                'Savings',
                'SA',
                1,
                1,
                4,
                1,
                '{BRANCH}-SA-{YEAR}-{NUMBER}',
            ],
        ];

        $sql = "
            INSERT INTO numbering_formats (
                id,
                module,
                prefix,
                branch_specific,
                include_year,
                padding,
                next_number,
                pattern,
                created_at
            )
            VALUES (
                :id,
                :module,
                :prefix,
                :branch_specific,
                :include_year,
                :padding,
                :next_number,
                :pattern,
                :created_at
            )
            ON DUPLICATE KEY UPDATE
                module = VALUES(module),
                prefix = VALUES(prefix),
                branch_specific = VALUES(branch_specific),
                include_year = VALUES(include_year),
                padding = VALUES(padding),
                pattern = VALUES(pattern)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($formats as $format) {
            $stmt->execute([
                ':id'             => $format[0],
                ':module'         => $format[1],
                ':prefix'         => $format[2],
                ':branch_specific' => $format[3],
                ':include_year'   => $format[4],
                ':padding'        => $format[5],
                ':next_number'    => $format[6],
                ':pattern'        => $format[7],
                ':created_at'     => '2026-09-12 08:07:46',
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM numbering_formats
            ORDER BY module ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}