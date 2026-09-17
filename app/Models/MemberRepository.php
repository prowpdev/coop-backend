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

        // Journal Vouchers (Manual and System JVs for this member)
        $jvStmt = $this->db->prepare("
            SELECT DISTINCT je.id, je.voucher_number, je.posting_date, je.description, je.reference_type,
                   je.total_debit, je.total_credit, je.status, je.created_by
            FROM journal_entries je
            LEFT JOIN journal_lines jl ON je.id = jl.journal_entry_id
            WHERE je.reference_id = ?
               OR jl.subsidiary_id = ?
               OR je.description LIKE ?
            ORDER BY je.posting_date DESC
        ");
        $memberName = '%' . ($member['first_name'] ?? '') . '%';
        $jvStmt->execute([$id, $id, $memberName]);
        $journalVouchers = $jvStmt->fetchAll(PDO::FETCH_ASSOC);

        $transactionStmt = $this->db->prepare("
            SELECT * FROM (
                SELECT
                    l.id AS transaction_id,
                    'LOAN_RELEASE' AS transaction_type,
                    l.disbursement_date AS transaction_date,
                    l.principal_amount AS amount,
                    l.loan_account_no AS reference_number,
                    l.loan_account_no AS account_number,
                    l.current_balance AS balance_after,
                    CONCAT('Loan disbursement - ', COALESCE(lp.name, 'Loan')) AS description,
                    'loan' AS source,
                    l.created_at
                FROM loans l
                LEFT JOIN loan_products lp ON lp.id = l.loan_product_id
                WHERE l.member_id = :loan_member_id

                UNION ALL

                SELECT
                    p.id AS transaction_id,
                    'LOAN_PAYMENT' AS transaction_type,
                    p.payment_date AS transaction_date,
                    p.total_amount AS amount,
                    p.receipt_no AS reference_number,
                    l.loan_account_no AS account_number,
                    l.current_balance AS balance_after,
                    CONCAT('Loan payment - ', l.loan_account_no) AS description,
                    'loan' AS source,
                    p.created_at
                FROM loan_payments p
                INNER JOIN loans l ON l.id = p.loan_id
                WHERE p.member_id = :payment_member_id

                UNION ALL

                SELECT
                    st.id AS transaction_id,
                    st.type AS transaction_type,
                    st.transaction_date,
                    st.amount,
                    st.transaction_no AS reference_number,
                    sa.account_number,
                    st.balance_after,
                    COALESCE(st.notes, CONCAT('Savings ', st.type)) AS description,
                    'savings' AS source,
                    st.created_at
                FROM savings_transactions st
                INNER JOIN savings_accounts sa ON sa.id = st.savings_account_id
                WHERE st.member_id = :savings_member_id

                UNION ALL

                SELECT
                    sct.id AS transaction_id,
                    sct.type AS transaction_type,
                    sct.transaction_date,
                    sct.amount,
                    sct.receipt_no AS reference_number,
                    sca.account_number,
                    NULL AS balance_after,
                    CONCAT('Share capital ', sct.type) AS description,
                    'share_capital' AS source,
                    sct.created_at
                FROM share_capital_transactions sct
                INNER JOIN share_capital_accounts sca ON sca.id = sct.share_account_id
                WHERE sct.member_id = :share_member_id

                UNION ALL

                SELECT DISTINCT
                    je.id AS transaction_id,
                    je.reference_type AS transaction_type,
                    je.posting_date AS transaction_date,
                    je.total_debit AS amount,
                    je.voucher_number AS reference_number,
                    NULL AS account_number,
                    NULL AS balance_after,
                    je.description,
                    'journal' AS source,
                    je.posting_date AS created_at
                FROM journal_entries je
                LEFT JOIN journal_lines jl ON jl.journal_entry_id = je.id
                WHERE je.reference_id = :journal_reference_id
                   OR jl.subsidiary_id = :journal_subsidiary_id
                   OR je.description LIKE :journal_member_name
            ) AS member_transactions
            ORDER BY transaction_date DESC, created_at DESC
        ");
        $transactionStmt->execute([
            'loan_member_id'        => $id,
            'payment_member_id'     => $id,
            'savings_member_id'     => $id,
            'share_member_id'       => $id,
            'journal_reference_id'  => $id,
            'journal_subsidiary_id' => $id,
            'journal_member_name'   => $memberName,
        ]);
        $transactions = $transactionStmt->fetchAll(PDO::FETCH_ASSOC);
        $summary = $this->generateMemberSummary(
            $loans,
            $savings,
            $shareCapital,
            $transactions,
            $journalVouchers
        );

        return [
            'member'            => $member,
            'summary'          => $summary,
            'loans'             => $loans,
            'savings'           => $savings,
            'share_capital'     => $shareCapital,
            'journal_vouchers'  => $journalVouchers,
            'transactions'      => $transactions
        ];
    }
    /**
 * Generate a financial summary for a member.
 *
 * @param array $loans
 * @param array $savings
 * @param array $shareCapital
 * @param array $transactions
 * @param array $journalVouchers
 * @return array
 */
private function generateMemberSummary(
    array $loans,
    array $savings,
    array $shareCapital,
    array $transactions,
    array $journalVouchers
): array {
    // Total outstanding loan balance
    $loanBalance = array_reduce(
        $loans,
        fn(float $sum, array $loan): float =>
            $sum + (float) ($loan['current_balance'] ?? 0),
        0.0
    );

    // Total savings balance
    $savingsBalance = array_reduce(
        $savings,
        fn(float $sum, array $account): float =>
            $sum + (float) ($account['balance'] ?? 0),
        0.0
    );

    // Total paid-up share capital
    $shareCapitalTotal = array_reduce(
        $shareCapital,
        fn(float $sum, array $account): float =>
            $sum + (float) ($account['paid_up_amount'] ?? 0),
        0.0
    );

    // Membership fees
    // Replace this with the actual membership fee calculation
    $membershipFees = 0.0;

    return [
        'loan_balance' => round($loanBalance, 2),

        'savings_balance' => round($savingsBalance, 2),

        'share_capital' => round($shareCapitalTotal, 2),

        'membership_fees' => round($membershipFees, 2),

        'total_transactions' => count($transactions),

        'jv_count' => count($journalVouchers),
    ];
}
}
