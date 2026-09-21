<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class MemberSeeder
{
    public function __construct(private PDO $db) {}

    public function run(): array
    {
        $members = [
            [
                'mem_000001',
                'MEM-2026-0001',
                'branch_tar',
                'mt_regular',
                'Juan',
                'Dela Cruz',
                'Santos',
                'Male',
                '1985-03-15',
                'juan.delacruz@example.com',
                '09171234567',
                'Brgy. San Vicente, Tarlac City, Tarlac',
                'Active',
                '2026-01-15',
                '{"occupation":"Farmer","civil_status":"Married","monthly_income":25000}',
                '2026-09-12 09:57:16',
                '2026-09-12 09:57:16',
            ],
            [
                'mem_000002',
                'MEM-2026-0002',
                'branch_tar',
                'mt_regular',
                'Maria',
                'Santos',
                'Garcia',
                'Female',
                '1990-07-22',
                'maria.santos@example.com',
                '09181234567',
                'Brgy. San Jose, Tarlac City, Tarlac',
                'Active',
                '2026-02-10',
                '{"occupation":"Teacher","civil_status":"Married","monthly_income":32000}',
                '2026-09-12 09:57:16',
                '2026-09-12 09:57:16',
            ],
            [
                'mem_000003',
                'MEM-2026-0003',
                'branch_urd',
                'mt_regular',
                'Pedro',
                'Reyes',
                'Cruz',
                'Male',
                '1978-11-05',
                'pedro.reyes@example.com',
                '09191234567',
                'Brgy. Nancayasan, Urdaneta City, Pangasinan',
                'Active',
                '2026-02-20',
                '{"occupation":"Business Owner","civil_status":"Married","monthly_income":45000}',
                '2026-09-12 09:57:16',
                '2026-09-12 09:57:16',
            ],
            [
                'mem_000004',
                'MEM-2026-0004',
                'branch_sfe',
                'mt_regular',
                'Ana',
                'Garcia',
                'Lopez',
                'Female',
                '1995-01-18',
                'ana.garcia@example.com',
                '09201234567',
                'Brgy. Catbangen, City of San Fernando, La Union',
                'Pending Approval',
                '2026-08-05',
                '{"occupation":"Accountant","civil_status":"Single","monthly_income":38000}',
                '2026-09-12 09:57:16',
                '2026-09-12 09:57:16',
            ],
            [
                'mem_000005',
                'MEM-2026-0005',
                'branch_tar',
                'mt_associate',
                'Roberto',
                'Mendoza',
                'Dizon',
                'Male',
                '1982-09-30',
                'roberto.mendoza@example.com',
                '09211234567',
                'Brgy. Matatalaib, Tarlac City, Tarlac',
                'Active',
                '2026-03-12',
                '{"occupation":"Carpenter","civil_status":"Married","monthly_income":28000}',
                '2026-09-12 09:57:16',
                '2026-09-12 09:57:16',
            ]
        ];

        $sql = "
            INSERT INTO members (
                id,
                member_no,
                branch_id,
                member_type_id,
                first_name,
                last_name,
                middle_name,
                gender,
                birthdate,
                email,
                phone,
                address,
                status,
                joined_date,
                custom_field_values,
                created_at,
                updated_at
            )
            VALUES (
                :id,
                :member_no,
                :branch_id,
                :member_type_id,
                :first_name,
                :last_name,
                :middle_name,
                :gender,
                :birthdate,
                :email,
                :phone,
                :address,
                :status,
                :joined_date,
                :custom_field_values,
                :created_at,
                :updated_at
            )
            ON DUPLICATE KEY UPDATE
                member_no = VALUES(member_no),
                branch_id = VALUES(branch_id),
                member_type_id = VALUES(member_type_id),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                middle_name = VALUES(middle_name),
                gender = VALUES(gender),
                birthdate = VALUES(birthdate),
                email = VALUES(email),
                phone = VALUES(phone),
                address = VALUES(address),
                status = VALUES(status),
                joined_date = VALUES(joined_date),
                custom_field_values = VALUES(custom_field_values),
                updated_at = VALUES(updated_at)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($members as $member) {
            $stmt->execute([
                ':id'                  => $member[0],
                ':member_no'           => $member[1],
                ':branch_id'           => $member[2],
                ':member_type_id'      => $member[3],
                ':first_name'          => $member[4],
                ':last_name'           => $member[5],
                ':middle_name'         => $member[6],
                ':gender'              => $member[7],
                ':birthdate'           => $member[8],
                ':email'               => $member[9],
                ':phone'               => $member[10],
                ':address'             => $member[11],
                ':status'              => $member[12],
                ':joined_date'         => $member[13],
                ':custom_field_values' => $member[14],
                ':created_at'          => $member[15],
                ':updated_at'          => $member[16],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                m.*,
                b.name AS branch_name,
                mt.name AS member_type_name
            FROM members m
            LEFT JOIN branches b
                ON b.id = m.branch_id
            LEFT JOIN member_types mt
                ON mt.id = m.member_type_id
            ORDER BY m.member_no ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}