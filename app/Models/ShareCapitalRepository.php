<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ShareCapitalRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Get all share capital (CBU) accounts
     */
    public function all(?string $memberId = null): array
    {
        $sql = "
            SELECT sc.*,
                   CONCAT(m.first_name, ' ', m.last_name) AS member_name,
                   m.member_no,
                   b.name AS branch_name
            FROM share_capital_accounts sc
            JOIN members m ON sc.member_id = m.id
            JOIN branches b ON m.branch_id = b.id
            WHERE 1=1
        ";
        $params = [];

        if ($memberId) {
            $sql .= " AND sc.member_id = :member_id";
            $params['member_id'] = $memberId;
        }

        $sql .= " ORDER BY sc.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find single share capital account
     */
    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT sc.*,
                   CONCAT(m.first_name, ' ', m.last_name) AS member_name,
                   m.member_no,
                   b.name AS branch_name
            FROM share_capital_accounts sc
            JOIN members m ON sc.member_id = m.id
            JOIN branches b ON m.branch_id = b.id
            WHERE sc.id = ? OR sc.account_number = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Open a new Share Capital / CBU subscription account
     *
     * If an initial paid-up amount is provided:
     *
     *   Dr Cash / Bank
     *   Cr Members' Share Capital
     *
     * Also:
     *   - Creates share_capital_transactions record
     *   - Updates cash_accounts.current_balance
     *   - Creates journal_entries
     *   - Creates journal_lines
     *   - Creates configuration_audit_trails record
     */
    public function createAccount(array $data): array
    {
        $id = $data['id']
            ?? ('sc_' . bin2hex(random_bytes(6)));

        $accNo = $data['account_number']
            ?? (
                'SC-' .
                date('Y') .
                '-' .
                str_pad(
                    (string) mt_rand(1, 99999),
                    5,
                    '0',
                    STR_PAD_LEFT
                )
            );

        $memberId = $data['member_id'] ?? null;

        if (empty($memberId)) {
            throw new \InvalidArgumentException(
                'member_id is required.'
            );
        }

        $parValue = (float) (
            $data['par_value'] ?? 100
        );

        $subscribedShares = (int) (
            $data['subscribed_shares'] ?? 50
        );

        $subscribedAmount = (float) (
            $data['subscribed_amount']
            ?? ($subscribedShares * $parValue)
        );

        $paidUpShares = (int) (
            $data['paid_up_shares'] ?? 0
        );

        $paidUpAmount = (float) (
            $data['paid_up_amount']
            ?? ($paidUpShares * $parValue)
        );

        $paidUpAmount = round($paidUpAmount, 2);

        if ($subscribedShares < 0) {
            throw new \InvalidArgumentException(
                'Subscribed shares cannot be negative.'
            );
        }

        if ($paidUpShares < 0) {
            throw new \InvalidArgumentException(
                'Paid-up shares cannot be negative.'
            );
        }

        if ($paidUpShares > $subscribedShares) {
            throw new \InvalidArgumentException(
                'Paid-up shares cannot exceed subscribed shares.'
            );
        }

        if ($paidUpAmount < 0) {
            throw new \InvalidArgumentException(
                'Paid-up amount cannot be negative.'
            );
        }

        if (
            $paidUpAmount > 0 &&
            empty($data['cash_account_id'])
        ) {
            throw new \InvalidArgumentException(
                'cash_account_id is required when paid-up amount is greater than zero.'
            );
        }

        /*
        * ------------------------------------------------------------
        * 1. Verify member exists
        * ------------------------------------------------------------
        */

        $memberStmt = $this->db->prepare("
            SELECT
                id,
                branch_id,
                first_name,
                last_name
            FROM members
            WHERE id = ?
            LIMIT 1
        ");

        $memberStmt->execute([
            $memberId
        ]);

        $member = $memberStmt->fetch(PDO::FETCH_ASSOC);

        if (!$member) {
            throw new \RuntimeException(
                "Member '{$memberId}' does not exist."
            );
        }

        /*
        * ------------------------------------------------------------
        * 2. Resolve branch
        * ------------------------------------------------------------
        */

        $branchId = $data['branch_id']
            ?? ($member['branch_id'] ?? null);

        if (empty($branchId)) {
            $branchId = 'branch_tar';
        }

        /*
        * ------------------------------------------------------------
        * 3. Duplicate check
        * ------------------------------------------------------------
        */

        $checkStmt = $this->db->prepare("
            SELECT
                id,
                account_number,
                status
            FROM share_capital_accounts
            WHERE member_id = ?
            LIMIT 1
        ");

        $checkStmt->execute([
            $memberId
        ]);

        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            throw new \RuntimeException(
                "Member already has a Share Capital account ({$existing['account_number']}). " .
                "Duplicate accounts are prohibited."
            );
        }

        /*
        * ------------------------------------------------------------
        * IDs / values used by the transaction
        * ------------------------------------------------------------
        */

        $transactionId = null;
        $journalId = null;
        $voucherNo = null;
        $auditId = null;

        $cashAccountId = $data['cash_account_id'] ?? null;

        $cashGLAccountId = null;
        $shareCapitalGLAccountId = null;

        $oldCashBalance = null;
        $newCashBalance = null;

        $referenceNumber = $data['reference_number']
            ?? (
                'SC-OR-' .
                date('Ymd') .
                '-' .
                strtoupper(
                    bin2hex(random_bytes(2))
                )
            );

        $paymentDate = $data['payment_date']
            ?? date('Y-m-d');

        $performedBy = $data['performed_by']
            ?? 'System';

        /*
        * ------------------------------------------------------------
        * BEGIN TRANSACTION
        * ------------------------------------------------------------
        */

        $this->db->beginTransaction();

        try {

            /*
            * --------------------------------------------------------
            * 4. Create Share Capital Account
            * --------------------------------------------------------
            */

            $sql = "
                INSERT INTO share_capital_accounts (
                    id,
                    account_number,
                    member_id,
                    branch_id,
                    subscribed_shares,
                    subscribed_amount,
                    paid_up_shares,
                    paid_up_amount,
                    status
                )
                VALUES (
                    :id,
                    :account_number,
                    :member_id,
                    :branch_id,
                    :subscribed_shares,
                    :subscribed_amount,
                    :paid_up_shares,
                    :paid_up_amount,
                    :status
                )
            ";

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                ':id' =>
                    $id,

                ':account_number' =>
                    $accNo,

                ':member_id' =>
                    $memberId,

                ':branch_id' =>
                    $branchId,

                ':subscribed_shares' =>
                    $subscribedShares,

                ':subscribed_amount' =>
                    $subscribedAmount,

                ':paid_up_shares' =>
                    $paidUpShares,

                ':paid_up_amount' =>
                    $paidUpAmount,

                ':status' =>
                    $data['status'] ?? 'Active',
            ]);

            /*
            * --------------------------------------------------------
            * 5. Process Initial Payment
            * --------------------------------------------------------
            */

            if ($paidUpAmount > 0) {

                /*
                * ----------------------------------------------------
                * 5A. Lock Cash Account
                * ----------------------------------------------------
                */

                $cashStmt = $this->db->prepare("
                    SELECT
                        id,
                        name,
                        account_number,
                        bank_name,
                        branch_id,
                        gl_account_id,
                        opening_balance,
                        current_balance,
                        currency,
                        active
                    FROM cash_accounts
                    WHERE id = ?
                    FOR UPDATE
                ");

                $cashStmt->execute([
                    $cashAccountId
                ]);

                $cash = $cashStmt->fetch(PDO::FETCH_ASSOC);

                if (!$cash) {
                    throw new \RuntimeException(
                        "Cash account '{$cashAccountId}' not found."
                    );
                }

                if ((int) $cash['active'] !== 1) {
                    throw new \RuntimeException(
                        "Cash account '{$cashAccountId}' is inactive."
                    );
                }

                /*
                * Optional branch validation
                *
                * If your cash account belongs to a different branch,
                * stop the transaction instead of posting to the wrong
                * branch.
                */

                if (
                    !empty($cash['branch_id']) &&
                    !empty($branchId) &&
                    $cash['branch_id'] !== $branchId
                ) {
                    throw new \RuntimeException(
                        "Cash account '{$cashAccountId}' belongs to branch " .
                        "'{$cash['branch_id']}', not '{$branchId}'."
                    );
                }

                /*
                * ----------------------------------------------------
                * 5B. Resolve Cash GL Account
                * ----------------------------------------------------
                *
                * cash_accounts.id:
                *
                *     cash_01
                *
                * journal_lines.account_id must NOT use cash_01.
                *
                * It must use:
                *
                *     cash_accounts.gl_account_id
                *
                * Example:
                *
                *     cash_01 -> acc_1110
                */

                $cashGLAccountId =
                    $cash['gl_account_id'] ?? null;

                if (empty($cashGLAccountId)) {
                    throw new \RuntimeException(
                        "Cash account '{$cashAccountId}' has no GL account configured."
                    );
                }

                $cashGLStmt = $this->db->prepare("
                    SELECT
                        id,
                        account_code,
                        name,
                        category,
                        normal_balance,
                        is_active
                    FROM chart_of_accounts
                    WHERE id = ?
                    LIMIT 1
                ");

                $cashGLStmt->execute([
                    $cashGLAccountId
                ]);

                $cashGL = $cashGLStmt->fetch(PDO::FETCH_ASSOC);

                if (!$cashGL) {
                    throw new \RuntimeException(
                        "Cash GL account '{$cashGLAccountId}' does not exist."
                    );
                }

                if ((int) $cashGL['is_active'] !== 1) {
                    throw new \RuntimeException(
                        "Cash GL account '{$cashGLAccountId}' is inactive."
                    );
                }

                /*
                * ----------------------------------------------------
                * 5C. Get Share Capital GL Account
                * ----------------------------------------------------
                */

                $settingsStmt = $this->db->query("
                    SELECT
                        accounting_account_id
                    FROM share_capital_settings
                    ORDER BY id ASC
                    LIMIT 1
                ");

                $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

                $shareCapitalGLAccountId =
                    $data['gl_share_capital_account_id']
                    ?? ($settings['accounting_account_id'] ?? null);

                if (empty($shareCapitalGLAccountId)) {
                    throw new \RuntimeException(
                        'Share capital GL account is not configured.'
                    );
                }

                $shareGLStmt = $this->db->prepare("
                    SELECT
                        id,
                        account_code,
                        name,
                        category,
                        normal_balance,
                        is_active
                    FROM chart_of_accounts
                    WHERE id = ?
                    LIMIT 1
                ");

                $shareGLStmt->execute([
                    $shareCapitalGLAccountId
                ]);

                $shareGL = $shareGLStmt->fetch(PDO::FETCH_ASSOC);

                if (!$shareGL) {
                    throw new \RuntimeException(
                        "Share capital GL account '{$shareCapitalGLAccountId}' does not exist."
                    );
                }

                if ((int) $shareGL['is_active'] !== 1) {
                    throw new \RuntimeException(
                        "Share capital GL account '{$shareCapitalGLAccountId}' is inactive."
                    );
                }

                /*
                * ----------------------------------------------------
                * 5D. Read Current Cash Balance
                * ----------------------------------------------------
                */

                $oldCashBalance = (float) (
                    $cash['current_balance'] ?? 0
                );

                $newCashBalance = round(
                    $oldCashBalance + $paidUpAmount,
                    2
                );

                /*
                * ----------------------------------------------------
                * 5E. Update Cash Account
                * ----------------------------------------------------
                */

                $cashUpdateStmt = $this->db->prepare("
                    UPDATE cash_accounts
                    SET current_balance = ?
                    WHERE id = ?
                ");

                $cashUpdateStmt->execute([
                    $newCashBalance,
                    $cashAccountId
                ]);

                /*
                * ----------------------------------------------------
                * 5F. Create Share Capital Transaction
                * ----------------------------------------------------
                */

                $transactionId =
                    'sctx_' .
                    bin2hex(random_bytes(6));

                $txStmt = $this->db->prepare("
                    INSERT INTO share_capital_transactions (
                        id,
                        receipt_no,
                        share_account_id,
                        member_id,
                        type,
                        shares,
                        amount,
                        transaction_date,
                        cash_account_id
                    )
                    VALUES (
                        :id,
                        :receipt_no,
                        :share_account_id,
                        :member_id,
                        'PAYMENT',
                        :shares,
                        :amount,
                        :transaction_date,
                        :cash_account_id
                    )
                ");

                $txStmt->execute([
                    ':id' =>
                        $transactionId,

                    ':receipt_no' =>
                        $referenceNumber,

                    ':share_account_id' =>
                        $id,

                    ':member_id' =>
                        $memberId,

                    ':shares' =>
                        $paidUpShares,

                    ':amount' =>
                        $paidUpAmount,

                    ':transaction_date' =>
                        $paymentDate,

                    ':cash_account_id' =>
                        $cashAccountId,
                ]);

                /*
                * ----------------------------------------------------
                * 5G. Create Journal Entry
                * ----------------------------------------------------
                */

                $journalId =
                    'je_' .
                    bin2hex(random_bytes(6));

                $voucherNo =
                    'JV-SC-' .
                    date('Ymd') .
                    '-' .
                    strtoupper(
                        bin2hex(random_bytes(3))
                    );

                /*
                * Example:
                *
                * payment_date = 2026-09-23
                *
                * period_id = period_2026_09
                */

                $periodId =
                    'period_' .
                    date(
                        'Y_m',
                        strtotime($paymentDate)
                    );

                $journalStmt = $this->db->prepare("
                    INSERT INTO journal_entries (
                        id,
                        voucher_number,
                        branch_id,
                        posting_date,
                        reference_type,
                        description,
                        total_debit,
                        total_credit,
                        period_id,
                        status,
                        created_by
                    )
                    VALUES (
                        :id,
                        :voucher_number,
                        :branch_id,
                        :posting_date,
                        :reference_type,
                        :description,
                        :total_debit,
                        :total_credit,
                        :period_id,
                        'Posted',
                        :created_by
                    )
                ");

                $journalStmt->execute([
                    ':id' =>
                        $journalId,

                    ':voucher_number' =>
                        $voucherNo,

                    ':branch_id' =>
                        $branchId,

                    ':posting_date' =>
                        $paymentDate,

                    ':reference_type' =>
                        'Share Capital Payment',

                    ':description' =>
                        'Initial share capital payment - ' .
                        $accNo .
                        ' - Receipt ' .
                        $referenceNumber,

                    ':total_debit' =>
                        $paidUpAmount,

                    ':total_credit' =>
                        $paidUpAmount,

                    ':period_id' =>
                        $periodId,

                    ':created_by' =>
                        $performedBy,
                ]);

                /*
                * ----------------------------------------------------
                * 5H. Create Journal Lines
                * ----------------------------------------------------
                *
                * DR Cash
                * CR Members' Share Capital
                */

                $lineStmt = $this->db->prepare("
                    INSERT INTO journal_lines (
                        id,
                        journal_entry_id,
                        account_id,
                        debit,
                        credit,
                        subsidiary_type,
                        subsidiary_id
                    )
                    VALUES (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");

                /*
                * DEBIT
                *
                * Cash / Bank
                */

                $lineStmt->execute([
                    'jl_' . bin2hex(random_bytes(6)),
                    $journalId,
                    $cashGLAccountId,
                    $paidUpAmount,
                    0,
                    'Member',
                    $memberId,
                ]);

                /*
                * CREDIT
                *
                * Members' Share Capital
                */

                $lineStmt->execute([
                    'jl_' . bin2hex(random_bytes(6)),
                    $journalId,
                    $shareCapitalGLAccountId,
                    0,
                    $paidUpAmount,
                    'Member',
                    $memberId,
                ]);
            }

            /*
            * --------------------------------------------------------
            * 6. Audit Trail
            * --------------------------------------------------------
            */

            $auditId = $this->recordAuditTrail(
                'Share Capital Account Created',
                'None',
                [
                    'account_id' =>
                        $id,

                    'account_number' =>
                        $accNo,

                    'member_id' =>
                        $memberId,

                    'branch_id' =>
                        $branchId,

                    'subscribed_shares' =>
                        $subscribedShares,

                    'subscribed_amount' =>
                        $subscribedAmount,

                    'paid_up_shares' =>
                        $paidUpShares,

                    'paid_up_amount' =>
                        $paidUpAmount,

                    'cash_account_id' =>
                        $cashAccountId,

                    'cash_gl_account_id' =>
                        $cashGLAccountId,

                    'share_capital_gl_account_id' =>
                        $shareCapitalGLAccountId,

                    'transaction_id' =>
                        $transactionId,

                    'journal_entry_id' =>
                        $journalId,

                    'voucher_number' =>
                        $voucherNo,

                    'old_cash_balance' =>
                        $oldCashBalance,

                    'new_cash_balance' =>
                        $newCashBalance,
                ],
                $performedBy,
                'New Share Capital / CBU account created.'
            );

            /*
            * --------------------------------------------------------
            * 7. COMMIT EVERYTHING
            * --------------------------------------------------------
            */

            $this->db->commit();

            /*
            * --------------------------------------------------------
            * 8. Return Created Account + Accounting Information
            * --------------------------------------------------------
            */

            $account = $this->find($id) ?? [];

            return [
                'success' => true,

                'account' =>
                    $account,

                'account_id' =>
                    $id,

                'account_number' =>
                    $accNo,

                'member_id' =>
                    $memberId,

                'branch_id' =>
                    $branchId,

                'subscribed_shares' =>
                    $subscribedShares,

                'subscribed_amount' =>
                    $subscribedAmount,

                'paid_up_shares' =>
                    $paidUpShares,

                'paid_up_amount' =>
                    $paidUpAmount,

                'cash_account_id' =>
                    $cashAccountId,

                'cash_gl_account_id' =>
                    $cashGLAccountId,

                'old_cash_balance' =>
                    $oldCashBalance,

                'new_cash_balance' =>
                    $newCashBalance,

                'transaction_id' =>
                    $transactionId,

                'journal_entry_id' =>
                    $journalId,

                'voucher_number' =>
                    $voucherNo,

                'audit_id' =>
                    $auditId,
            ];

        } catch (\Throwable $e) {

            /*
            * IMPORTANT:
            *
            * This rolls back:
            *
            * - share_capital_accounts
            * - share_capital_transactions
            * - cash_accounts
            * - journal_entries
            * - journal_lines
            * - configuration_audit_trails
            *
            * if any one operation fails.
            */

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }
    /**
     * Record payment toward share capital subscription
     * and automatically post the transaction to the General Ledger.
     *
     * Accounting:
     *
     *   Dr Cash / Bank
     *   Cr Members' Share Capital
     *
     * Everything is committed in ONE database transaction.
     */
   public function recordPayment(array $data): array
{
    $accountId = $data['account_id'] ?? null;

    $amount = (float) ($data['amount'] ?? 0);

    $parValue = (float) ($data['par_value'] ?? 100);

    $shares = isset($data['shares'])
        ? (int) $data['shares']
        : (int) floor($amount / $parValue);

    $date = $data['payment_date'] ?? date('Y-m-d');

    $ref = $data['reference_number']
        ?? (
            'SC-OR-' .
            date('Ymd') .
            '-' .
            mt_rand(1000, 9999)
        );

    /*
     * IMPORTANT:
     *
     * This is the ID from cash_accounts.
     *
     * Example:
     *
     * cash_01
     */
    $cashAccountId = $data['cash_account_id'] ?? null;

    $createdBy =
        $data['performed_by']
        ?? $data['created_by']
        ?? 'System';


    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if (!$accountId) {
        throw new \InvalidArgumentException(
            'account_id is required.'
        );
    }

    if (!$cashAccountId) {
        throw new \InvalidArgumentException(
            'cash_account_id is required for share capital payment.'
        );
    }

    if ($amount <= 0) {
        throw new \InvalidArgumentException(
            'Payment amount must be greater than zero.'
        );
    }

    if ($shares <= 0) {
        throw new \InvalidArgumentException(
            'Number of shares must be greater than zero.'
        );
    }

    if ($parValue <= 0) {
        throw new \InvalidArgumentException(
            'Par value must be greater than zero.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Start ONE Database Transaction
    |--------------------------------------------------------------------------
    */

    $this->db->beginTransaction();

    try {

        /*
        |--------------------------------------------------------------------------
        | 1. Get Share Capital Account
        |--------------------------------------------------------------------------
        */

        $stmt = $this->db->prepare("
            SELECT *
            FROM share_capital_accounts
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            $accountId
        ]);

        $acc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$acc) {
            throw new \RuntimeException(
                "Share capital account '{$accountId}' was not found."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 2. Get Member and Branch
        |--------------------------------------------------------------------------
        */

        $memberId = $acc['member_id'] ?? null;
        $branchId = $acc['branch_id'] ?? null;

        if (!$memberId) {
            throw new \RuntimeException(
                'Share capital account has no member_id.'
            );
        }

        if (!$branchId) {
            throw new \RuntimeException(
                'Share capital account has no branch_id.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 3. Validate Payment Against Subscription
        |--------------------------------------------------------------------------
        */

        $subscribedShares =
            (int) ($acc['subscribed_shares'] ?? 0);

        $paidUpShares =
            (int) ($acc['paid_up_shares'] ?? 0);

        $remainingShares =
            $subscribedShares - $paidUpShares;

        if ($remainingShares < 0) {
            throw new \RuntimeException(
                "Share capital account has invalid balances. " .
                "Paid-up shares ({$paidUpShares}) exceed " .
                "subscribed shares ({$subscribedShares})."
            );
        }

        if ($shares > $remainingShares) {
            throw new \RuntimeException(
                "Payment exceeds remaining subscribed shares. " .
                "Remaining shares: {$remainingShares}, " .
                "attempted: {$shares}."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 4. Calculate New Paid-Up Balance
        |--------------------------------------------------------------------------
        */

        $oldPaidShares =
            (int) ($acc['paid_up_shares'] ?? 0);

        $oldPaidAmount =
            (float) ($acc['paid_up_amount'] ?? 0);

        $newPaidShares =
            $oldPaidShares + $shares;

        $newPaidAmount =
            $oldPaidAmount + $amount;


        /*
        |--------------------------------------------------------------------------
        | 5. Update Share Capital Account
        |--------------------------------------------------------------------------
        */

        $updStmt = $this->db->prepare("
            UPDATE share_capital_accounts
            SET
                paid_up_shares = ?,
                paid_up_amount = ?
            WHERE id = ?
        ");

        $updStmt->execute([
            $newPaidShares,
            $newPaidAmount,
            $accountId
        ]);

        if ($updStmt->rowCount() !== 1) {
            throw new \RuntimeException(
                "Unable to update share capital account '{$accountId}'."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 6. Get Cash Account + Its GL Account
        |--------------------------------------------------------------------------
        |
        | cash_accounts.id
        |     = cash_01
        |
        | cash_accounts.gl_account_id
        |     = acc_1110
        |
        | journal_lines.account_id
        |     MUST = acc_1110
        |
        */

        $cashStmt = $this->db->prepare("
            SELECT
                ca.id AS cash_account_id,
                ca.gl_account_id,
                ca.opening_balance,
                ca.current_balance,
                ca.currency,
                ca.branch_id AS cash_branch_id,

                coa.id AS coa_id,
                coa.account_code,
                coa.name,
                coa.category,
                coa.normal_balance,
                coa.is_active

            FROM cash_accounts ca

            INNER JOIN chart_of_accounts coa
                ON coa.id = ca.gl_account_id

            WHERE ca.id = ?
              AND coa.is_active = 1

            LIMIT 1

            FOR UPDATE
        ");

        $cashStmt->execute([
            $cashAccountId
        ]);

        $cashAccount =
            $cashStmt->fetch(PDO::FETCH_ASSOC);

        if (!$cashAccount) {
            throw new \RuntimeException(
                "Cash account '{$cashAccountId}' was not found " .
                "or its GL account is inactive."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 7. Get Cash GL Account ID
        |--------------------------------------------------------------------------
        */

        $cashGLAccountId =
            $cashAccount['coa_id'] ?? null;

        if (!$cashGLAccountId) {
            throw new \RuntimeException(
                "Cash account '{$cashAccountId}' " .
                "does not have a valid GL account."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 8. Get Current Cash Balance
        |--------------------------------------------------------------------------
        */

        $oldCashBalance =
            (float) ($cashAccount['current_balance'] ?? 0);

        $newCashBalance =
            $oldCashBalance + $amount;


        /*
        |--------------------------------------------------------------------------
        | 9. Validate Cash GL Account
        |--------------------------------------------------------------------------
        */

        if (
            isset($cashAccount['category']) &&
            strcasecmp(
                $cashAccount['category'],
                'Asset'
            ) !== 0
        ) {
            throw new \RuntimeException(
                "Cash GL account '{$cashGLAccountId}' " .
                "is not categorized as an Asset."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 10. Update Cash Account Current Balance
        |--------------------------------------------------------------------------
        */

        $cashBalanceStmt = $this->db->prepare("
            UPDATE cash_accounts
            SET
                current_balance =
                    COALESCE(current_balance, 0) + ?
            WHERE id = ?
        ");

        $cashBalanceStmt->execute([
            $amount,
            $cashAccountId
        ]);

        if ($cashBalanceStmt->rowCount() !== 1) {
            throw new \RuntimeException(
                "Unable to update cash account balance " .
                "for '{$cashAccountId}'."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 11. Get Share Capital GL Account From Settings
        |--------------------------------------------------------------------------
        */

        $settingsStmt = $this->db->query("
            SELECT
                id,
                accounting_account_id,
                par_value_per_share
            FROM share_capital_settings
            ORDER BY id ASC
            LIMIT 1
        ");

        $settings =
            $settingsStmt->fetch(PDO::FETCH_ASSOC);

        $shareCapitalAccountId =
            $data['gl_share_capital_account_id']
            ?? ($settings['accounting_account_id'] ?? null);

        if (!$shareCapitalAccountId) {
            throw new \RuntimeException(
                'Share capital GL account is not configured.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 12. Validate Share Capital GL Account
        |--------------------------------------------------------------------------
        */

        $shareStmt = $this->db->prepare("
            SELECT
                id,
                account_code,
                name,
                category,
                normal_balance,
                is_active
            FROM chart_of_accounts
            WHERE id = ?
              AND is_active = 1
            LIMIT 1
        ");

        $shareStmt->execute([
            $shareCapitalAccountId
        ]);

        $shareGLAccount =
            $shareStmt->fetch(PDO::FETCH_ASSOC);

        if (!$shareGLAccount) {
            throw new \RuntimeException(
                "Members' Share Capital GL account " .
                "not found or inactive: {$shareCapitalAccountId}"
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 13. Validate Share Capital GL Category
        |--------------------------------------------------------------------------
        */

        if (
            isset($shareGLAccount['category']) &&
            strcasecmp(
                $shareGLAccount['category'],
                'Equity'
            ) !== 0
        ) {
            throw new \RuntimeException(
                "Share Capital GL account " .
                "'{$shareCapitalAccountId}' " .
                "is not categorized as Equity."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 14. Create Share Capital Transaction
        |--------------------------------------------------------------------------
        |
        | cash_account_id remains:
        |
        |     cash_01
        |
        | because this column references cash_accounts.
        |
        */

        $txId =
            'sctx_' . bin2hex(random_bytes(6));

        $txStmt = $this->db->prepare("
            INSERT INTO share_capital_transactions (
                id,
                receipt_no,
                share_account_id,
                member_id,
                type,
                shares,
                amount,
                transaction_date,
                cash_account_id
            ) VALUES (
                ?, ?, ?, ?, 'PAYMENT', ?, ?, ?, ?
            )
        ");

        $txStmt->execute([
            $txId,
            $ref,
            $accountId,
            $memberId,
            $shares,
            $amount,
            $date,
            $cashAccountId
        ]);


        /*
        |--------------------------------------------------------------------------
        | 15. Create Journal Entry
        |--------------------------------------------------------------------------
        |
        | Accounting:
        |
        |     Dr Cash / Bank
        |     Cr Members' Share Capital
        |
        */

        $journalId =
            'je_' . bin2hex(random_bytes(6));

        $voucherNo =
            'JV-SC-' .
            date('Ymd') .
            '-' .
            strtoupper(
                bin2hex(random_bytes(3))
            );

        $periodId =
            'period_' .
            date('Y_m', strtotime($date));

        $accountNumber =
            $acc['account_number']
            ?? $accountId;

        $description =
            'Share capital payment - ' .
            $accountNumber .
            ' - Receipt ' .
            $ref;


        /*
        |--------------------------------------------------------------------------
        | 16. Insert Journal Entry
        |--------------------------------------------------------------------------
        */

        $journalStmt = $this->db->prepare("
            INSERT INTO journal_entries (
                id,
                voucher_number,
                branch_id,
                posting_date,
                reference_type,
                description,
                total_debit,
                total_credit,
                period_id,
                status,
                created_by
            ) VALUES (
                :id,
                :voucher_number,
                :branch_id,
                :posting_date,
                :reference_type,
                :description,
                :total_debit,
                :total_credit,
                :period_id,
                'Posted',
                :created_by
            )
        ");

        $journalStmt->execute([
            'id' =>
                $journalId,

            'voucher_number' =>
                $voucherNo,

            'branch_id' =>
                $branchId,

            'posting_date' =>
                $date,

            'reference_type' =>
                'Share Capital Payment',

            'description' =>
                $description,

            'total_debit' =>
                $amount,

            'total_credit' =>
                $amount,

            'period_id' =>
                $periodId,

            'created_by' =>
                $createdBy
        ]);


        /*
        |--------------------------------------------------------------------------
        | 17. Create Journal Lines
        |--------------------------------------------------------------------------
        */

        $lineStmt = $this->db->prepare("
            INSERT INTO journal_lines (
                id,
                journal_entry_id,
                account_id,
                debit,
                credit,
                subsidiary_type,
                subsidiary_id
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?
            )
        ");


        /*
        |--------------------------------------------------------------------------
        | 17A. DEBIT - Cash / Bank
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | Do NOT use:
        |
        |     $cashAccountId
        |
        | because that is:
        |
        |     cash_01
        |
        | Use:
        |
        |     $cashGLAccountId
        |
        | Example:
        |
        |     cash_01
        |         ↓
        |     acc_1110
        |
        */

        $cashLineId =
            'jl_' . bin2hex(random_bytes(6));

        $lineStmt->execute([
            $cashLineId,
            $journalId,

            // chart_of_accounts.id
            $cashGLAccountId,

            $amount,
            0,

            'Member',
            $memberId
        ]);


        /*
        |--------------------------------------------------------------------------
        | 17B. CREDIT - Members' Share Capital
        |--------------------------------------------------------------------------
        */

        $shareLineId =
            'jl_' . bin2hex(random_bytes(6));

        $lineStmt->execute([
            $shareLineId,
            $journalId,

            // chart_of_accounts.id
            $shareCapitalAccountId,

            0,
            $amount,

            'Member',
            $memberId
        ]);


        /*
        |--------------------------------------------------------------------------
        | 18. Record Audit Trail
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | This happens BEFORE commit().
        |
        | Therefore the audit record is part of the
        | same database transaction.
        |
        */

        $auditId =
            $this->recordAuditTrail(
                'Share Capital Payment',

                /*
                |--------------------------------------------------------------------------
                | OLD VALUE
                |--------------------------------------------------------------------------
                */

                [
                    'transaction_id' =>
                        null,

                    'member_id' =>
                        $memberId,

                    'share_account_id' =>
                        $accountId,

                    'paid_up_shares' =>
                        $oldPaidShares,

                    'paid_up_amount' =>
                        $oldPaidAmount,

                    'cash_account_id' =>
                        $cashAccountId,

                    'cash_balance' =>
                        $oldCashBalance
                ],

                /*
                |--------------------------------------------------------------------------
                | NEW VALUE
                |--------------------------------------------------------------------------
                */

                [
                    'transaction_id' =>
                        $txId,

                    'member_id' =>
                        $memberId,

                    'share_account_id' =>
                        $accountId,

                    'paid_up_shares' =>
                        $newPaidShares,

                    'paid_up_amount' =>
                        $newPaidAmount,

                    'cash_account_id' =>
                        $cashAccountId,

                    'cash_balance' =>
                        $newCashBalance,

                    'journal_entry_id' =>
                        $journalId,

                    'voucher_number' =>
                        $voucherNo,

                    'receipt_number' =>
                        $ref
                ],

                /*
                |--------------------------------------------------------------------------
                | Changed By
                |--------------------------------------------------------------------------
                */

                $createdBy,

                /*
                |--------------------------------------------------------------------------
                | Reason
                |--------------------------------------------------------------------------
                */

                sprintf(
                    'Automated share capital payment. ' .
                    'Member: %s; Shares: %d; Amount: ₱%.2f; ' .
                    'Cash Account: %s; Cash GL: %s; ' .
                    'Share Capital GL: %s; Receipt: %s; Voucher: %s.',
                    $memberId,
                    $shares,
                    $amount,
                    $cashAccountId,
                    $cashGLAccountId,
                    $shareCapitalAccountId,
                    $ref,
                    $voucherNo
                )
            );


        /*
        |--------------------------------------------------------------------------
        | 19. Commit Everything
        |--------------------------------------------------------------------------
        */

        $this->db->commit();


        /*
        |--------------------------------------------------------------------------
        | 20. Return Result
        |--------------------------------------------------------------------------
        */

        return [

            'success' =>
                true,

            'transaction_id' =>
                $txId,

            'audit_id' =>
                $auditId,

            'account_id' =>
                $accountId,

            'account_number' =>
                $accountNumber,

            'member_id' =>
                $memberId,

            'branch_id' =>
                $branchId,

            'shares_added' =>
                $shares,

            'amount_paid' =>
                $amount,

            'old_paid_up_shares' =>
                $oldPaidShares,

            'new_paid_up_shares' =>
                $newPaidShares,

            'old_paid_up_amount' =>
                $oldPaidAmount,

            'total_paid_up' =>
                $newPaidAmount,

            'reference_no' =>
                $ref,

            'journal_entry_id' =>
                $journalId,

            'voucher_number' =>
                $voucherNo,

            /*
             * Operational cash account.
             */
            'cash_account_id' =>
                $cashAccountId,

            /*
             * GL account used by journal_lines.
             */
            'cash_gl_account_id' =>
                $cashGLAccountId,

            'cash_account_name' =>
                $cashAccount['name'],

            'old_cash_balance' =>
                $oldCashBalance,

            'new_cash_balance' =>
                $newCashBalance,

            'share_capital_gl_account_id' =>
                $shareCapitalAccountId,

            'share_capital_gl_account_name' =>
                $shareGLAccount['name'],

            'gl_posted' =>
                true
        ];

    } catch (\Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | Rollback EVERYTHING
        |--------------------------------------------------------------------------
        */

        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        throw $e;
    }
}
        /**
     * Get transactions for account
     */
    public function getTransactions(string $accountId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM share_capital_transactions
            WHERE share_account_id = ?
            ORDER BY transaction_date DESC, created_at DESC
        ");
        $stmt->execute([$accountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

  /**
 * Update existing Share Capital account
 *
 * If paid-up amount increases, the difference is treated as
 * an additional payment:
 *
 *   Dr Cash / Bank
 *   Cr Members' Share Capital
 *
 * Also creates:
 *   - share_capital_transactions
 *   - cash_accounts balance update
 *   - journal_entries
 *   - journal_lines
 *   - configuration_audit_trails
 */
public function updateAccount(string $id, array $data): array
{
    if (empty($id)) {
        throw new \InvalidArgumentException(
            'Share capital account ID is required.'
        );
    }

    $this->db->beginTransaction();

    try {

        /*
         * ---------------------------------------------------------
         * 1. Get and lock existing account
         * ---------------------------------------------------------
         */

        $existingStmt = $this->db->prepare("
            SELECT *
            FROM share_capital_accounts
            WHERE id = ?
            FOR UPDATE
        ");

        $existingStmt->execute([$id]);

        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            throw new \RuntimeException(
                "Share capital account '{$id}' not found."
            );
        }

        $memberId = $existing['member_id'];

        /*
         * ---------------------------------------------------------
         * 2. Existing values
         * ---------------------------------------------------------
         */

        $oldSubscribedShares = (int) (
            $existing['subscribed_shares'] ?? 0
        );

        $oldSubscribedAmount = (float) (
            $existing['subscribed_amount'] ?? 0
        );

        $oldPaidUpShares = (int) (
            $existing['paid_up_shares'] ?? 0
        );

        $oldPaidUpAmount = (float) (
            $existing['paid_up_amount'] ?? 0
        );

        /*
         * ---------------------------------------------------------
         * 3. New values
         * ---------------------------------------------------------
         */

        $parValue = (float) (
            $data['par_value']
            ?? $existing['par_value']
            ?? 100
        );

        $subscribedShares = isset($data['subscribed_shares'])
            ? (int) $data['subscribed_shares']
            : $oldSubscribedShares;

        $subscribedAmount = isset($data['subscribed_amount'])
            ? (float) $data['subscribed_amount']
            : ($subscribedShares * $parValue);

        $paidUpShares = isset($data['paid_up_shares'])
            ? (int) $data['paid_up_shares']
            : $oldPaidUpShares;

        $paidUpAmount = isset($data['paid_up_amount'])
            ? (float) $data['paid_up_amount']
            : ($paidUpShares * $parValue);

        $paidUpAmount = round(
            $paidUpAmount,
            2
        );

        /*
         * ---------------------------------------------------------
         * 4. Validate shares
         * ---------------------------------------------------------
         */

        if ($subscribedShares < 0) {
            throw new \InvalidArgumentException(
                'Subscribed shares cannot be negative.'
            );
        }

        if ($paidUpShares < 0) {
            throw new \InvalidArgumentException(
                'Paid-up shares cannot be negative.'
            );
        }

        if ($paidUpShares > $subscribedShares) {
            throw new \InvalidArgumentException(
                "Paid-up shares ({$paidUpShares}) cannot exceed " .
                "subscribed shares ({$subscribedShares})."
            );
        }

        /*
         * Do not allow updateAccount() to reduce paid-up shares
         * or paid-up amount.
         *
         * A withdrawal/refund should be handled by a separate
         * transaction type.
         */

        if ($paidUpShares < $oldPaidUpShares) {
            throw new \InvalidArgumentException(
                "Paid-up shares cannot be reduced from " .
                "{$oldPaidUpShares} to {$paidUpShares}."
            );
        }

        if ($paidUpAmount < $oldPaidUpAmount) {
            throw new \InvalidArgumentException(
                "Paid-up amount cannot be reduced from ₱" .
                number_format($oldPaidUpAmount, 2) .
                " to ₱" .
                number_format($paidUpAmount, 2) .
                "."
            );
        }

        /*
         * ---------------------------------------------------------
         * 5. Calculate additional payment
         * ---------------------------------------------------------
         */

        $additionalShares =
            $paidUpShares - $oldPaidUpShares;

        $additionalAmount = round(
            $paidUpAmount - $oldPaidUpAmount,
            2
        );

        /*
         * ---------------------------------------------------------
         * 6. Other account values
         * ---------------------------------------------------------
         */

        $status = $data['status']
            ?? ($existing['status'] ?? 'Active');

        $branchId = $data['branch_id']
            ?? ($existing['branch_id'] ?? null);

        $accountNumber = !empty($data['account_number'])
            ? $data['account_number']
            : $existing['account_number'];

        $performedBy = $data['performed_by']
            ?? 'System';

        $cashAccountId = $data['cash_account_id']
            ?? null;

        /*
         * ---------------------------------------------------------
         * 7. Payment values
         * ---------------------------------------------------------
         */

        $transactionId = null;
        $journalId = null;
        $voucherNo = null;
        $auditId = null;

        $cashGLAccountId = null;
        $shareCapitalGLAccountId = null;

        $oldCashBalance = null;
        $newCashBalance = null;

        $referenceNumber = $data['reference_number']
            ?? (
                'SC-OR-' .
                date('Ymd') .
                '-' .
                strtoupper(
                    bin2hex(random_bytes(2))
                )
            );

        $paymentDate = $data['payment_date']
            ?? date('Y-m-d');

        /*
         * ---------------------------------------------------------
         * 8. Process additional payment
         * ---------------------------------------------------------
         */

        if ($additionalAmount > 0) {

            if (empty($cashAccountId)) {
                throw new \InvalidArgumentException(
                    'cash_account_id is required when paid-up amount increases.'
                );
            }

            /*
             * -----------------------------------------------------
             * 8A. Lock cash account
             * -----------------------------------------------------
             */

            $cashStmt = $this->db->prepare("
                SELECT
                    id,
                    name,
                    account_number,
                    bank_name,
                    branch_id,
                    gl_account_id,
                    opening_balance,
                    current_balance,
                    currency,
                    active
                FROM cash_accounts
                WHERE id = ?
                FOR UPDATE
            ");

            $cashStmt->execute([
                $cashAccountId
            ]);

            $cash = $cashStmt->fetch(PDO::FETCH_ASSOC);

            if (!$cash) {
                throw new \RuntimeException(
                    "Cash account '{$cashAccountId}' not found."
                );
            }

            if ((int) $cash['active'] !== 1) {
                throw new \RuntimeException(
                    "Cash account '{$cashAccountId}' is inactive."
                );
            }

            /*
             * -----------------------------------------------------
             * 8B. Optional branch validation
             * -----------------------------------------------------
             */

            if (
                !empty($cash['branch_id']) &&
                !empty($branchId) &&
                $cash['branch_id'] !== $branchId
            ) {
                throw new \RuntimeException(
                    "Cash account '{$cashAccountId}' belongs to " .
                    "branch '{$cash['branch_id']}', while this " .
                    "share capital account belongs to branch '{$branchId}'."
                );
            }

            /*
             * -----------------------------------------------------
             * 8C. Resolve Cash GL
             * -----------------------------------------------------
             *
             * cash_accounts.id:
             *
             *     cash_01
             *
             * journal_lines.account_id:
             *
             *     acc_1110
             *
             * Therefore use gl_account_id here.
             */

            $cashGLAccountId =
                $cash['gl_account_id'] ?? null;

            if (empty($cashGLAccountId)) {
                throw new \RuntimeException(
                    "Cash account '{$cashAccountId}' has no GL account configured."
                );
            }

            $cashGLStmt = $this->db->prepare("
                SELECT
                    id,
                    account_code,
                    name,
                    category,
                    normal_balance,
                    is_active
                FROM chart_of_accounts
                WHERE id = ?
                LIMIT 1
            ");

            $cashGLStmt->execute([
                $cashGLAccountId
            ]);

            $cashGL = $cashGLStmt->fetch(PDO::FETCH_ASSOC);

            if (!$cashGL) {
                throw new \RuntimeException(
                    "Cash GL account '{$cashGLAccountId}' does not exist."
                );
            }

            if ((int) $cashGL['is_active'] !== 1) {
                throw new \RuntimeException(
                    "Cash GL account '{$cashGLAccountId}' is inactive."
                );
            }

            /*
             * -----------------------------------------------------
             * 8D. Resolve Share Capital GL
             * -----------------------------------------------------
             */

            $settingsStmt = $this->db->query("
                SELECT
                    accounting_account_id
                FROM share_capital_settings
                ORDER BY id ASC
                LIMIT 1
            ");

            $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

            $shareCapitalGLAccountId =
                $data['gl_share_capital_account_id']
                ?? ($settings['accounting_account_id'] ?? null);

            if (empty($shareCapitalGLAccountId)) {
                throw new \RuntimeException(
                    'Share capital GL account is not configured.'
                );
            }

            $shareGLStmt = $this->db->prepare("
                SELECT
                    id,
                    account_code,
                    name,
                    category,
                    normal_balance,
                    is_active
                FROM chart_of_accounts
                WHERE id = ?
                LIMIT 1
            ");

            $shareGLStmt->execute([
                $shareCapitalGLAccountId
            ]);

            $shareGL = $shareGLStmt->fetch(PDO::FETCH_ASSOC);

            if (!$shareGL) {
                throw new \RuntimeException(
                    "Share capital GL account '{$shareCapitalGLAccountId}' does not exist."
                );
            }

            if ((int) $shareGL['is_active'] !== 1) {
                throw new \RuntimeException(
                    "Share capital GL account '{$shareCapitalGLAccountId}' is inactive."
                );
            }

            /*
             * -----------------------------------------------------
             * 8E. Calculate cash balance
             * -----------------------------------------------------
             */

            $oldCashBalance = (float) (
                $cash['current_balance'] ?? 0
            );

            $newCashBalance = round(
                $oldCashBalance + $additionalAmount,
                2
            );

            /*
             * -----------------------------------------------------
             * 8F. Update cash account
             * -----------------------------------------------------
             */

            $cashUpdateStmt = $this->db->prepare("
                UPDATE cash_accounts
                SET current_balance = ?
                WHERE id = ?
            ");

            $cashUpdateStmt->execute([
                $newCashBalance,
                $cashAccountId
            ]);

            /*
             * -----------------------------------------------------
             * 8G. Create Share Capital Transaction
             * -----------------------------------------------------
             *
             * IMPORTANT:
             *
             * Only the additional amount is recorded.
             *
             * Example:
             *
             * Old paid-up = ₱1,000
             * New paid-up = ₱2,500
             *
             * Transaction = ₱1,500
             */

            $transactionId =
                'sctx_' .
                bin2hex(random_bytes(6));

            $txStmt = $this->db->prepare("
                INSERT INTO share_capital_transactions (
                    id,
                    receipt_no,
                    share_account_id,
                    member_id,
                    type,
                    shares,
                    amount,
                    transaction_date,
                    cash_account_id
                )
                VALUES (
                    :id,
                    :receipt_no,
                    :share_account_id,
                    :member_id,
                    'PAYMENT',
                    :shares,
                    :amount,
                    :transaction_date,
                    :cash_account_id
                )
            ");

            $txStmt->execute([
                ':id' =>
                    $transactionId,

                ':receipt_no' =>
                    $referenceNumber,

                ':share_account_id' =>
                    $id,

                ':member_id' =>
                    $memberId,

                ':shares' =>
                    $additionalShares,

                ':amount' =>
                    $additionalAmount,

                ':transaction_date' =>
                    $paymentDate,

                ':cash_account_id' =>
                    $cashAccountId,
            ]);

            /*
             * -----------------------------------------------------
             * 8H. Create Journal Entry
             * -----------------------------------------------------
             */

            $journalId =
                'je_' .
                bin2hex(random_bytes(6));

            $voucherNo =
                'JV-SC-' .
                date('Ymd') .
                '-' .
                strtoupper(
                    bin2hex(random_bytes(3))
                );

            $periodId =
                'period_' .
                date(
                    'Y_m',
                    strtotime($paymentDate)
                );

            $journalStmt = $this->db->prepare("
                INSERT INTO journal_entries (
                    id,
                    voucher_number,
                    branch_id,
                    posting_date,
                    reference_type,
                    description,
                    total_debit,
                    total_credit,
                    period_id,
                    status,
                    created_by
                )
                VALUES (
                    :id,
                    :voucher_number,
                    :branch_id,
                    :posting_date,
                    :reference_type,
                    :description,
                    :total_debit,
                    :total_credit,
                    :period_id,
                    'Posted',
                    :created_by
                )
            ");

            $journalStmt->execute([
                ':id' =>
                    $journalId,

                ':voucher_number' =>
                    $voucherNo,

                ':branch_id' =>
                    $branchId,

                ':posting_date' =>
                    $paymentDate,

                ':reference_type' =>
                    'Share Capital Payment',

                ':description' =>
                    'Additional share capital payment - ' .
                    $accountNumber .
                    ' - Receipt ' .
                    $referenceNumber,

                ':total_debit' =>
                    $additionalAmount,

                ':total_credit' =>
                    $additionalAmount,

                ':period_id' =>
                    $periodId,

                ':created_by' =>
                    $performedBy,
            ]);

            /*
             * -----------------------------------------------------
             * 8I. Create Journal Lines
             * -----------------------------------------------------
             *
             * Dr Cash
             * Cr Members' Share Capital
             */

            $lineStmt = $this->db->prepare("
                INSERT INTO journal_lines (
                    id,
                    journal_entry_id,
                    account_id,
                    debit,
                    credit,
                    subsidiary_type,
                    subsidiary_id
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            /*
             * DEBIT CASH
             */

            $lineStmt->execute([
                'jl_' . bin2hex(random_bytes(6)),
                $journalId,
                $cashGLAccountId,
                $additionalAmount,
                0,
                'Member',
                $memberId,
            ]);

            /*
             * CREDIT SHARE CAPITAL
             */

            $lineStmt->execute([
                'jl_' . bin2hex(random_bytes(6)),
                $journalId,
                $shareCapitalGLAccountId,
                0,
                $additionalAmount,
                'Member',
                $memberId,
            ]);
        }

        /*
         * ---------------------------------------------------------
         * 9. Update Share Capital Account
         * ---------------------------------------------------------
         */

        $updateStmt = $this->db->prepare("
            UPDATE share_capital_accounts
            SET
                account_number = :account_number,
                branch_id = :branch_id,
                subscribed_shares = :subscribed_shares,
                subscribed_amount = :subscribed_amount,
                paid_up_shares = :paid_up_shares,
                paid_up_amount = :paid_up_amount,
                status = :status
            WHERE id = :id
        ");

        $updateStmt->execute([
            ':id' =>
                $id,

            ':account_number' =>
                $accountNumber,

            ':branch_id' =>
                $branchId,

            ':subscribed_shares' =>
                $subscribedShares,

            ':subscribed_amount' =>
                $subscribedAmount,

            ':paid_up_shares' =>
                $paidUpShares,

            ':paid_up_amount' =>
                $paidUpAmount,

            ':status' =>
                $status,
        ]);

        /*
         * ---------------------------------------------------------
         * 10. Audit Trail
         * ---------------------------------------------------------
         */

        $auditId = $this->recordAuditTrail(
            'Share Capital Account Updated',

            [
                'account_id' =>
                    $id,

                'account_number' =>
                    $existing['account_number'],

                'member_id' =>
                    $memberId,

                'subscribed_shares' =>
                    $oldSubscribedShares,

                'subscribed_amount' =>
                    $oldSubscribedAmount,

                'paid_up_shares' =>
                    $oldPaidUpShares,

                'paid_up_amount' =>
                    $oldPaidUpAmount,
            ],

            [
                'account_id' =>
                    $id,

                'account_number' =>
                    $accountNumber,

                'member_id' =>
                    $memberId,

                'subscribed_shares' =>
                    $subscribedShares,

                'subscribed_amount' =>
                    $subscribedAmount,

                'paid_up_shares' =>
                    $paidUpShares,

                'paid_up_amount' =>
                    $paidUpAmount,

                'additional_shares' =>
                    $additionalShares,

                'additional_amount' =>
                    $additionalAmount,

                'cash_account_id' =>
                    $cashAccountId,

                'cash_gl_account_id' =>
                    $cashGLAccountId,

                'share_capital_gl_account_id' =>
                    $shareCapitalGLAccountId,

                'old_cash_balance' =>
                    $oldCashBalance,

                'new_cash_balance' =>
                    $newCashBalance,

                'transaction_id' =>
                    $transactionId,

                'journal_entry_id' =>
                    $journalId,

                'voucher_number' =>
                    $voucherNo,
            ],

            $performedBy,

            $additionalAmount > 0
                ? 'Share capital account updated and additional payment recorded.'
                : 'Share capital account updated without additional payment.'
        );

        /*
         * ---------------------------------------------------------
         * 11. Commit
         * ---------------------------------------------------------
         */

        $this->db->commit();

        /*
         * ---------------------------------------------------------
         * 12. Return result
         * ---------------------------------------------------------
         */

        return [
            'success' =>
                true,

            'account' =>
                $this->find($id) ?? [],

            'account_id' =>
                $id,

            'member_id' =>
                $memberId,

            'old_paid_up_shares' =>
                $oldPaidUpShares,

            'new_paid_up_shares' =>
                $paidUpShares,

            'old_paid_up_amount' =>
                $oldPaidUpAmount,

            'new_paid_up_amount' =>
                $paidUpAmount,

            'additional_shares' =>
                $additionalShares,

            'additional_amount' =>
                $additionalAmount,

            'cash_account_id' =>
                $cashAccountId,

            'cash_gl_account_id' =>
                $cashGLAccountId,

            'old_cash_balance' =>
                $oldCashBalance,

            'new_cash_balance' =>
                $newCashBalance,

            'transaction_id' =>
                $transactionId,

            'journal_entry_id' =>
                $journalId,

            'voucher_number' =>
                $voucherNo,

            'audit_id' =>
                $auditId,
        ];

    } catch (\Throwable $e) {

        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        throw $e;
    }
}

    /**
     * Delete account
     */
    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM share_capital_accounts WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Get share capital settings
     */
    public function getSettings(): array
    {
        $stmt = $this->db->query("SELECT scs.*, coa.account_code, coa.name AS gl_account_name FROM share_capital_settings scs LEFT JOIN chart_of_accounts coa ON scs.accounting_account_id = coa.id");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($results)) {
            // Return baseline default
            return [[
                'id' => 'sc_setting_01',
                'cooperative_id' => 'coop_01',
                'par_value_per_share' => 100.00,
                'min_subscription_shares' => 100,
                'min_paid_up_shares' => 25,
                'max_share_holding_percentage' => 10.00,
                'transfer_fee' => 100.00,
                'withdrawal_rule' => 'Subject to Board approval and 30-day prior written notice',
                'accounting_account_id' => 'acc_3110'
            ]];
        }
        return $results;
    }

    /**
     * Create share capital setting
     */
    public function createSetting(array $data): array
    {
        $id = $data['id'] ?? ('sc_setting_' . substr(uniqid(), -6));
        $stmt = $this->db->prepare("
            INSERT INTO share_capital_settings (id, cooperative_id, par_value_per_share, min_subscription_shares, min_paid_up_shares, max_share_holding_percentage, transfer_fee, withdrawal_rule, accounting_account_id)
            VALUES (:id, :cooperative_id, :par_value_per_share, :min_subscription_shares, :min_paid_up_shares, :max_share_holding_percentage, :transfer_fee, :withdrawal_rule, :accounting_account_id)
        ");
        $stmt->execute([
            'id' => $id,
            'cooperative_id' => $data['cooperative_id'] ?? 'coop_01',
            'par_value_per_share' => (float)($data['par_value_per_share'] ?? 100.0),
            'min_subscription_shares' => (int)($data['min_subscription_shares'] ?? 100),
            'min_paid_up_shares' => (int)($data['min_paid_up_shares'] ?? 25),
            'max_share_holding_percentage' => (float)($data['max_share_holding_percentage'] ?? 10.0),
            'transfer_fee' => (float)($data['transfer_fee'] ?? 100.0),
            'withdrawal_rule' => $data['withdrawal_rule'] ?? 'Subject to Board approval and 30-day prior written notice',
            'accounting_account_id' => $data['accounting_account_id'] ?? 'acc_3110'
        ]);

        $fetch = $this->db->prepare("SELECT * FROM share_capital_settings WHERE id = ?");
        $fetch->execute([$id]);
        return $fetch->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Update share capital setting
     */
    public function updateSetting(string $id, array $data): array
    {
        $stmt = $this->db->prepare("
            UPDATE share_capital_settings
            SET par_value_per_share = :par_value_per_share,
                min_subscription_shares = :min_subscription_shares,
                min_paid_up_shares = :min_paid_up_shares,
                max_share_holding_percentage = :max_share_holding_percentage,
                transfer_fee = :transfer_fee,
                withdrawal_rule = :withdrawal_rule,
                accounting_account_id = :accounting_account_id
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $id,
            'par_value_per_share' => (float)($data['par_value_per_share'] ?? 100.0),
            'min_subscription_shares' => (int)($data['min_subscription_shares'] ?? 100),
            'min_paid_up_shares' => (int)($data['min_paid_up_shares'] ?? 25),
            'max_share_holding_percentage' => (float)($data['max_share_holding_percentage'] ?? 10.0),
            'transfer_fee' => (float)($data['transfer_fee'] ?? 100.0),
            'withdrawal_rule' => $data['withdrawal_rule'] ?? 'Subject to Board approval and 30-day prior written notice',
            'accounting_account_id' => $data['accounting_account_id'] ?? 'acc_3110'
        ]);

        $fetch = $this->db->prepare("SELECT * FROM share_capital_settings WHERE id = ?");
        $fetch->execute([$id]);
        return $fetch->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function recordAuditTrail(
    string $setting,
    mixed $oldValue = 'None',
    mixed $newValue = 'None',
    string $changedBy = 'System',
    string $reason = 'System event'
): string {
    $id = 'audit_' . bin2hex(random_bytes(8));

    $now = date('c');

    $stmt = $this->db->prepare("
        INSERT INTO configuration_audit_trails (
            id,
            setting,
            old_value,
            new_value,
            changed_by,
            created_at,
            reason
        ) VALUES (
            :id,
            :setting,
            :old_value,
            :new_value,
            :changed_by,
            :created_at,
            :reason
        )
    ");

    $stmt->execute([
        ':id' =>
            $id,

        ':setting' =>
            $setting,

        ':old_value' =>
            $this->auditValue($oldValue),

        ':new_value' =>
            $this->auditValue($newValue),

        ':changed_by' =>
            $changedBy,

        ':created_at' =>
            $now,

        ':reason' =>
            $reason
    ]);

    return $id;
}


private function auditValue(mixed $value): string
{
    if ($value === null) {
        return 'None';
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if (is_array($value) || is_object($value)) {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?: 'None';
    }

    return (string) $value;
}
}
