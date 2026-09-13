<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class MemberRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Get all members with optional filters
     */
    public function all(?string $branchId = null, ?string $status = null, ?string $search = null): array
    {
        $sql = "SELECT m.*, b.name AS branch_name, mt.name AS member_type_name
                FROM members m
                LEFT JOIN branches b ON m.branch_id = b.id
                LEFT JOIN member_types mt ON m.member_type_id = mt.id
                WHERE 1=1";
        $params = [];

        if ($branchId && $branchId !== 'all') {
            $sql .= " AND m.branch_id = :branch_id";
            $params['branch_id'] = $branchId;
        }

        if ($status && $status !== 'all') {
            $sql .= " AND m.status = :status";
            $params['status'] = $status;
        }

        if ($search) {
            $sql .= " AND (m.first_name LIKE :search OR m.last_name LIKE :search OR m.member_no LIKE :search OR m.phone LIKE :search)";
            $params['search'] = "%$search%";
        }

        $sql .= " ORDER BY m.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($members as &$member) {
            if (isset($member['custom_field_values']) && is_string($member['custom_field_values'])) {
                $member['custom_field_values'] = json_decode($member['custom_field_values'], true) ?: [];
            }
        }

        return $members;
    }

    /**
     * Find member by ID or Member Number
     */
    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT m.*, b.name AS branch_name, mt.name AS member_type_name
            FROM members m
            LEFT JOIN branches b ON m.branch_id = b.id
            LEFT JOIN member_types mt ON m.member_type_id = mt.id
            WHERE m.id = ? OR m.member_no = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($member) {
            if (isset($member['custom_field_values']) && is_string($member['custom_field_values'])) {
                $member['custom_field_values'] = json_decode($member['custom_field_values'], true) ?: [];
            }
            return $member;
        }

        return null;
    }

    /**
     * Create a new member
     */
    public function create(array $data): array
    {
        $id = $data['id'] ?? ('mem_' . bin2hex(random_bytes(6)));
        $memberNo = $data['member_no'] ?? ('MEM-' . date('Y') . '-' . str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT));

        $sql = "INSERT INTO members (
            id, member_no, branch_id, member_type_id, first_name, last_name, middle_name,
            gender, birthdate, email, phone, address, status, joined_date, custom_field_values
        ) VALUES (
            :id, :member_no, :branch_id, :member_type_id, :first_name, :last_name, :middle_name,
            :gender, :birthdate, :email, :phone, :address, :status, :joined_date, :custom_field_values
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id'                  => $id,
            'member_no'           => $memberNo,
            'branch_id'           => $data['branch_id'],
            'member_type_id'      => $data['member_type_id'] ?? 'mt_regular',
            'first_name'          => $data['first_name'],
            'last_name'           => $data['last_name'],
            'middle_name'         => $data['middle_name'] ?? null,
            'gender'              => $data['gender'] ?? 'Male',
            'birthdate'           => $data['birthdate'] ?? date('Y-m-d', strtotime('-25 years')),
            'email'               => $data['email'] ?? null,
            'phone'               => $data['phone'] ?? '',
            'address'             => $data['address'] ?? '',
            'status'              => $data['status'] ?? 'Active',
            'joined_date'         => $data['joined_date'] ?? date('Y-m-d'),
            'custom_field_values' => isset($data['custom_field_values']) ? json_encode($data['custom_field_values']) : null,
        ]);

        return $this->find($id) ?? [];
    }

    /**
     * Update an existing member
     */
    public function update(string $id, array $data): ?array
    {
        $fields = [];
        $params = ['id' => $id];

        $allowed = [
            'first_name', 'last_name', 'middle_name', 'gender', 'birthdate',
            'email', 'phone', 'address', 'status', 'branch_id', 'member_type_id', 'joined_date'
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (array_key_exists('custom_field_values', $data)) {
            $fields[] = "custom_field_values = :custom_field_values";
            $params['custom_field_values'] = json_encode($data['custom_field_values']);
        }

        if (empty($fields)) {
            return $this->find($id);
        }

        $sql = "UPDATE members SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->find($id);
    }

    /**
     * Delete a member
     */
    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM members WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Get member comprehensive statement / portfolio
     */
    public function getMemberReport(string $id): array
    {
        $member = $this->find($id);
        if (!$member) {
            return [];
        }

        // Loans
        $loanStmt = $this->db->prepare("
            SELECT l.*, lp.name AS product_name
            FROM loans l
            LEFT JOIN loan_products lp ON l.loan_product_id = lp.id
            WHERE l.member_id = ?
            ORDER BY l.disbursement_date DESC
        ");
        $loanStmt->execute([$id]);
        $loans = $loanStmt->fetchAll(PDO::FETCH_ASSOC);

        // Savings
        $savingsStmt = $this->db->prepare("
            SELECT sa.*, sp.name AS product_name
            FROM savings_accounts sa
            LEFT JOIN savings_products sp ON sa.savings_product_id = sp.id
            WHERE sa.member_id = ?
        ");
        $savingsStmt->execute([$id]);
        $savings = $savingsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Share Capital
        $scStmt = $this->db->prepare("
            SELECT * FROM share_capital_accounts WHERE member_id = ?
        ");
        $scStmt->execute([$id]);
        $shareCapital = $scStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'member'        => $member,
            'loans'         => $loans,
            'savings'       => $savings,
            'share_capital' => $shareCapital
        ];
    }
}
