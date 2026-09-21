<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class UserRoleSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $roles = [
            [
                'role_accountant',
                'Chief / Branch Accountant',
                'General ledger management, journal vouchers, and period close',
                [
                    'manage_accounting',
                    'view_gl',
                    'post_journals',
                    'close_periods',
                    'view_reports',
                ],
                1,
            ],
            [
                'role_admin',
                'System Administrator',
                'Full unconstrained platform super-user access',
                [
                    'manage_system',
                    'manage_configurations',
                    'manage_members',
                    'manage_loans',
                    'manage_savings',
                    'manage_accounting',
                    'approve_transactions',
                    'view_reports',
                ],
                1,
            ],
            [
                'role_bod',
                'Board of Directors',
                'High-level policy review, macro-reporting, and large-loan approvals',
                [
                    'approve_tier3_loans',
                    'view_reports',
                    'view_audit_trail',
                ],
                1,
            ],
            [
                'role_loan_officer',
                'Credit & Loan Evaluation Officer',
                'Loan application review, credit investigation, and schedule creation',
                [
                    'manage_loans',
                    'view_members',
                    'create_schedules',
                ],
                1,
            ],
            [
                'role_manager',
                'General / Branch Manager',
                'Branch operations supervisor with level 2 approval rights',
                [
                    'manage_members',
                    'manage_loans',
                    'manage_savings',
                    'approve_transactions',
                    'view_reports',
                ],
                1,
            ],
            [
                'role_teller',
                'Teller / Cashier',
                'Over-the-counter payments, deposits, and cash drawer settlements',
                [
                    'receive_payments',
                    'issue_receipts',
                    'cash_inflow',
                    'cash_outflow',
                ],
                1,
            ],
        ];

        $sql = "
            INSERT INTO user_roles (
                id,
                name,
                description,
                permissions,
                active
            )
            VALUES (
                :id,
                :name,
                :description,
                :permissions,
                :active
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                permissions = VALUES(permissions),
                active = VALUES(active)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($roles as $role) {
            $stmt->execute([
                ':id'          => $role[0],
                ':name'        => $role[1],
                ':description' => $role[2],
                ':permissions' => json_encode(
                    $role[3],
                    JSON_UNESCAPED_SLASHES
                ),
                ':active'      => $role[4],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM user_roles
            ORDER BY name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}