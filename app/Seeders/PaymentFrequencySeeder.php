<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class PaymentFrequencySeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $frequencies = [
            [
                'freq_lumpsum',
                'Lump Sum',
                180,
                2,
                1,
            ],
            [
                'freq_monthly',
                'Monthly',
                30,
                12,
                1,
            ],
            [
                'freq_semimonthly',
                'Semi-monthly',
                15,
                24,
                1,
            ],
            [
                'freq_weekly',
                'Weekly',
                7,
                52,
                1,
            ],
        ];

        $sql = "
            INSERT INTO payment_frequencies (
                id,
                name,
                days_interval,
                periods_per_year,
                active
            )
            VALUES (
                :id,
                :name,
                :days_interval,
                :periods_per_year,
                :active
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                days_interval = VALUES(days_interval),
                periods_per_year = VALUES(periods_per_year),
                active = VALUES(active)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($frequencies as $frequency) {
            $stmt->execute([
                ':id'             => $frequency[0],
                ':name'           => $frequency[1],
                ':days_interval'  => $frequency[2],
                ':periods_per_year' => $frequency[3],
                ':active'         => $frequency[4],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM payment_frequencies
            ORDER BY days_interval ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}