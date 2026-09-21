<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class AccountingPeriodSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $periods = [
            ['period_2026_01', 'January 2026', 2026, 1, '2026-01-01', '2026-01-31'],
            ['period_2026_02', 'February 2026', 2026, 2, '2026-02-01', '2026-02-28'],
            ['period_2026_03', 'March 2026', 2026, 3, '2026-03-01', '2026-03-31'],
            ['period_2026_04', 'April 2026', 2026, 4, '2026-04-01', '2026-04-30'],
            ['period_2026_05', 'May 2026', 2026, 5, '2026-05-01', '2026-05-31'],
            ['period_2026_06', 'June 2026', 2026, 6, '2026-06-01', '2026-06-30'],
            ['period_2026_07', 'July 2026', 2026, 7, '2026-07-01', '2026-07-31'],
            ['period_2026_08', 'August 2026', 2026, 8, '2026-08-01', '2026-08-31'],
            ['period_2026_09', 'September 2026', 2026, 9, '2026-09-01', '2026-09-30'],
            ['period_2026_10', 'October 2026', 2026, 10, '2026-10-01', '2026-10-31'],
            ['period_2026_11', 'November 2026', 2026, 11, '2026-11-01', '2026-11-30'],
            ['period_2026_12', 'December 2026', 2026, 12, '2026-12-01', '2026-12-31'],
        ];

        $sql = "
            INSERT INTO accounting_periods (
                id,
                name,
                fiscal_year,
                period_number,
                start_date,
                end_date,
                status,
                closed_at,
                closed_by,
                created_at
            )
            VALUES (
                :id,
                :name,
                :fiscal_year,
                :period_number,
                :start_date,
                :end_date,
                :status,
                :closed_at,
                :closed_by,
                :created_at
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                fiscal_year = VALUES(fiscal_year),
                period_number = VALUES(period_number),
                start_date = VALUES(start_date),
                end_date = VALUES(end_date),
                status = VALUES(status),
                closed_at = VALUES(closed_at),
                closed_by = VALUES(closed_by)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($periods as $period) {
            $stmt->execute([
                ':id'            => $period[0],
                ':name'          => $period[1],
                ':fiscal_year'   => $period[2],
                ':period_number' => $period[3],
                ':start_date'    => $period[4],
                ':end_date'      => $period[5],
                ':status'        => 'Open',
                ':closed_at'     => null,
                ':closed_by'     => null,
                ':created_at'    => '2026-09-12 08:07:46',
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM accounting_periods
            ORDER BY fiscal_year ASC, period_number ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}