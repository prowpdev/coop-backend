<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class UserSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $users = [
            [
                'user_admin',
                'admin',
                '$2y$10$e8PZ7YJ1s7vWc6M79iA2/eqiB4uJtY76yW55N5Q1nLqK9hX2HjB2.',
                'System Administrator',
                'admin@mayapcare.coop',
                'role_admin',
                'branch_tar',
                1,
                null,
                '2026-09-12 08:07:46',
            ],
            [
                'user_admin1',
                'superadmin',
                '$2y$10$CKXeclgN2CJz3ZrZ.lLMbe7xr.sYENkYvYDsiZy21.qw5YYoXOWtK',
                'System Administrator',
                'jhomignacio08@gmail.com',
                'role_admin',
                'branch_tar',
                1,
                null,
                '2026-09-13 08:35:53',
            ],
            [
                'usr_688248a9e500',
                'testuser01',
                '$2y$10$8fwpwsBWxNdd718coaEaXOO/HFdcNrFd/vb.10zgr/frkKdLoIe0e',
                'Test User',
                'testuser01@example.com',
                'role_loan_officer',
                'branch_tar',
                1,
                null,
                '2026-09-13 09:02:09',
            ],
        ];

        $sql = "
            INSERT INTO users (
                id,
                username,
                password_hash,
                full_name,
                email,
                role_id,
                branch_id,
                active,
                last_login,
                created_at
            )
            VALUES (
                :id,
                :username,
                :password_hash,
                :full_name,
                :email,
                :role_id,
                :branch_id,
                :active,
                :last_login,
                :created_at
            )
            ON DUPLICATE KEY UPDATE
                username = VALUES(username),
                password_hash = VALUES(password_hash),
                full_name = VALUES(full_name),
                email = VALUES(email),
                role_id = VALUES(role_id),
                branch_id = VALUES(branch_id),
                active = VALUES(active),
                last_login = VALUES(last_login)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($users as $user) {
            $stmt->execute([
                ':id'            => $user[0],
                ':username'      => $user[1],
                ':password_hash' => $user[2],
                ':full_name'     => $user[3],
                ':email'         => $user[4],
                ':role_id'       => $user[5],
                ':branch_id'     => $user[6],
                ':active'        => $user[7],
                ':last_login'    => $user[8],
                ':created_at'    => $user[9],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                id,
                username,
                full_name,
                email,
                role_id,
                branch_id,
                active,
                last_login,
                created_at
            FROM users
            ORDER BY full_name ASC, username ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}