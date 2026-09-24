<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AmortizationService;
use PDO;
use App\Core\AuditLogger;

class LoanRepository
{
    private AuditLogger $audit;
    public function __construct(private PDO $db)
    {
        $this->audit = new AuditLogger($this->db);
    }

    /**
     * Fetch all loans with member and product information
     */
    public function all(?string $branchId = null, ?string $status = null, ?string $memberId = null): array
    {
        $sql = "
            SELECT l.*,
                   CONCAT(m.first_name, ' ', m.last_name) AS member_name,
                   m.member_no,
                   lp.name AS product_name,
                   lp.code AS product_code,
                   b.name AS branch_name
            FROM loans l
            JOIN members m ON l.member_id = m.id
            JOIN loan_products lp ON l.loan_product_id = lp.id
            JOIN branches b ON l.branch_id = b.id
            WHERE 1=1
        ";
        $params = [];

        if ($branchId && $branchId !== 'all') {
            $sql .= " AND l.branch_id = :branch_id";
            $params['branch_id'] = $branchId;
        }

        if ($status && $status !== 'all') {
            $sql .= " AND l.status = :status";
            $params['status'] = $status;
        }

        if ($memberId) {
            $sql .= " AND l.member_id = :member_id";
            $params['member_id'] = $memberId;
        }

        $sql .= " ORDER BY l.disbursement_date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find single loan by ID or Account Number
     */
    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT l.*,
                   CONCAT(m.first_name, ' ', m.last_name) AS member_name,
                   m.member_no,
                   m.phone AS member_phone,
                   lp.name AS product_name,
                   lp.interest_calculation_method,
                   b.name AS branch_name
            FROM loans l
            JOIN members m ON l.member_id = m.id
            JOIN loan_products lp ON l.loan_product_id = lp.id
            JOIN branches b ON l.branch_id = b.id
            WHERE l.id = ? OR l.loan_account_no = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $id]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);

        return $loan ?: null;
    }

    /**
     * Get amortization schedule installments for a loan
     */
    public function getSchedule(string $loanId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM loan_amortization_schedules
            WHERE loan_id = ?
            ORDER BY installment_no ASC
        ");
        $stmt->execute([$loanId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Disburse / Create a new loan with full amortization schedule
     */
    /**
     * Create loan and record its loan application
     */
    public function createLoan(array $data): array
    {
        $this->db->beginTransaction();

        try {
            $id = $data['id'] ?? ('ln_' . bin2hex(random_bytes(6)));
            $accountNo = $data['loan_account_no'] ?? (
                'LN-' . date('Y') . '-' .
                str_pad((string) mt_rand(1, 99999), 5, '0', STR_PAD_LEFT)
            );

            /*
            * Generate application ID / number
            */
            $applicationId = $data['application_id']
                ?? ('la_' . bin2hex(random_bytes(6)));

            $applicationNo = $data['application_no']
                ?? (
                    'LA-' . date('Y') . '-' .
                    str_pad((string) mt_rand(1, 99999), 5, '0', STR_PAD_LEFT)
                );

            /*
            * Get loan product
            */
            $productStmt = $this->db->prepare(
                'SELECT * FROM loan_products WHERE id = ?'
            );

            $productStmt->execute([
                $data['loan_product_id']
            ]);

            $product = $productStmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new \InvalidArgumentException(
                    'Loan product not found.'
                );
            }

            /*
            * Loan values
            */
            $principal = (float) $data['principal_amount'];

            $rate = (float) (
                $data['annual_interest_rate']
                ?? $product['annual_interest_rate']
            );

            $term = (int) $data['term_months'];

            $method = $data['interest_calculation_method']
                ?? $product['interest_calculation_method'];

            $startDate = $data['disbursement_date']
                ?? date('Y-m-d');

            $frequency = $data['payment_frequency']
                ?? $product['payment_frequency'];

            $firstDueDate = $data['first_due_date']
                ?? (
                    new \DateTime($startDate)
                )->modify('+1 month')->format('Y-m-d');

            $maturityDate = $data['maturity_date']
                ?? (
                    new \DateTime($firstDueDate)
                )->modify(
                    '+' . max(0, $term - 1) . ' months'
                )->format('Y-m-d');

            $processingFee = (float) (
                $data['processing_fee'] ?? 0
            );

            $serviceFee = (float) (
                $data['service_fee'] ?? 0
            );

            $netDisbursed = isset($data['net_disbursed'])
                ? (float) $data['net_disbursed']
                : max(
                    0,
                    $principal - $processingFee - $serviceFee
                );

            $cashAccountId =
                $data['disbursed_from_cash_account_id']
                ?? $data['cash_account_id']
                ?? null;

            if (!$cashAccountId) {
                throw new \InvalidArgumentException(
                    'Disbursement cash account is required.'
                );
            }

            /*
            * ============================================================
            * 1. CREATE LOAN APPLICATION RECORD
            * ============================================================
            *
            * Since createLoan() represents an approved/released loan,
            * we record the application as Released.
            */
            $applicationStmt = $this->db->prepare("
                INSERT INTO loan_applications (
                    id,
                    application_no,
                    member_id,
                    loan_product_id,
                    branch_id,
                    applied_amount,
                    term_months,
                    purpose,
                    status,
                    submitted_date,
                    reviewed_by,
                    reviewed_date,
                    approved_amount,
                    remarks
                ) VALUES (
                    :id,
                    :application_no,
                    :member_id,
                    :loan_product_id,
                    :branch_id,
                    :applied_amount,
                    :term_months,
                    :purpose,
                    :status,
                    :submitted_date,
                    :reviewed_by,
                    :reviewed_date,
                    :approved_amount,
                    :remarks
                )
            ");

            $applicationStmt->execute([
                'id'              => $applicationId,
                'application_no'  => $applicationNo,
                'member_id'       => $data['member_id'],
                'loan_product_id' => $data['loan_product_id'],
                'branch_id'       => $data['branch_id'],
                'applied_amount'  => $data['applied_amount'] ?? $principal,
                'term_months'     => $term,
                'purpose'         => $data['purpose'] ?? null,
                'status'          => $data['application_status'] ?? 'Released',
                'submitted_date'  => $data['submitted_date']
                    ?? $startDate,
                'reviewed_by'     => $data['reviewed_by']
                    ?? $data['approved_by']
                    ?? $data['performed_by']
                    ?? null,
                'reviewed_date'   => $data['reviewed_date']
                    ?? $data['approved_date']
                    ?? $startDate,
                'approved_amount' => $data['approved_amount']
                    ?? $principal,
                'remarks'         => $data['application_remarks']
                    ?? null
            ]);
            
            /*
            * ============================================================
            *  RECORD AUDIT
            * ============================================================
            */
            $this->audit->recordAuditTrail(
                'Loan Application',
                'None',
                [
                    'id' => $applicationId,
                    'application_no' => $applicationNo,
                    'member_id' => $data['member_id'],
                    'loan_product_id' => $data['loan_product_id'],
                    'branch_id' => $data['branch_id'],
                    'applied_amount' => $data['applied_amount'] ?? $principal,
                    'term_months' => $term,
                    'status' => $data['application_status'] ?? 'Released',
                ],
                $data['performed_by'] ?? $data['approved_by'] ?? 'System',
                'Created loan application'
            );
            /*
            * ============================================================
            * 2. INSERT LOAN RECORD
            * ============================================================
            */
            $sql = "INSERT INTO loans (
                id,
                loan_account_no,
                member_id,
                loan_product_id,
                product_version,
                branch_id,
                principal_amount,
                annual_interest_rate,
                interest_calculation_method,
                term_months,
                payment_frequency,
                disbursement_date,
                first_due_date,
                maturity_date,
                processing_fee,
                service_fee,
                net_disbursed,
                disbursed_from_cash_account_id,
                status,
                current_balance,
                total_principal_paid,
                total_interest_paid,
                total_penalty_paid,
                total_fees_paid,
                approved_by,
                approved_date
            ) VALUES (
                :id,
                :loan_account_no,
                :member_id,
                :loan_product_id,
                :product_version,
                :branch_id,
                :principal_amount,
                :annual_interest_rate,
                :interest_calculation_method,
                :term_months,
                :payment_frequency,
                :disbursement_date,
                :first_due_date,
                :maturity_date,
                :processing_fee,
                :service_fee,
                :net_disbursed,
                :disbursed_from_cash_account_id,
                :status,
                :current_balance,
                0,
                0,
                0,
                0,
                :approved_by,
                :approved_date
            )";

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                'id'                   => $id,
                'loan_account_no'      => $accountNo,
                'member_id'            => $data['member_id'],
                'loan_product_id'      => $data['loan_product_id'],
                'product_version'      => $data['product_version']
                    ?? ($product['version'] ?? 1),
                'branch_id'            => $data['branch_id'],
                'principal_amount'     => $principal,
                'annual_interest_rate' => $rate,
                'interest_calculation_method' => $method,
                'term_months'          => $term,
                'payment_frequency'    => $frequency,
                'disbursement_date'    => $startDate,
                'first_due_date'       => $firstDueDate,
                'maturity_date'        => $maturityDate,
                'processing_fee'       => $processingFee,
                'service_fee'          => $serviceFee,
                'net_disbursed'        => $netDisbursed,
                'disbursed_from_cash_account_id' => $cashAccountId,
                'current_balance'      => $principal,
                'status'               => $data['status'] ?? 'Active',
                'approved_by'          => $data['approved_by']
                    ?? $data['performed_by']
                    ?? null,
                'approved_date'        => $data['approved_date']
                    ?? date('Y-m-d')
            ]);

            $this->audit->recordAuditTrail(
                'Loan',
                'None',
                [
                    'id' => $id,
                    'loan_account_no' => $accountNo,
                    'member_id' => $data['member_id'],
                    'loan_product_id' => $data['loan_product_id'],
                    'branch_id' => $data['branch_id'],
                    'principal_amount' => $principal,
                    'annual_interest_rate' => $rate,
                    'interest_calculation_method' => $method,
                    'term_months' => $term,
                    'payment_frequency' => $frequency,
                    'disbursement_date' => $startDate,
                    'net_disbursed' => $netDisbursed,
                    'status' => $data['status'] ?? 'Active',
                ],
                $data['performed_by'] ?? $data['approved_by'] ?? 'System',
                'Created and released loan'
            );
            /*
            * ============================================================
            * 3. GENERATE AMORTIZATION SCHEDULE
            * ============================================================
            */
            $schedule = AmortizationService::generateSchedule(
                $principal,
                $rate,
                $term,
                $method,
                $startDate,
                $frequency
            );

            $schedStmt = $this->db->prepare("
                INSERT INTO loan_amortization_schedules (
                    id,
                    loan_id,
                    installment_no,
                    due_date,
                    principal,
                    interest,
                    total_installment,
                    principal_balance,
                    paid_principal,
                    paid_interest,
                    status
                ) VALUES (
                    :id,
                    :loan_id,
                    :installment_no,
                    :due_date,
                    :principal,
                    :interest,
                    :total_installment,
                    :principal_balance,
                    0,
                    0,
                    'Unpaid'
                )
            ");

            foreach ($schedule as $row) {
                $schedStmt->execute([
                    'id'                => 'las_' . bin2hex(random_bytes(6)),
                    'loan_id'           => $id,
                    'installment_no'    => $row['installment_no'],
                    'due_date'          => $row['due_date'],
                    'principal'         => $row['principal'],
                    'interest'          => $row['interest'],
                    'total_installment' => $row['total_installment'],
                    'principal_balance' => $row['principal_balance']
                ]);
            }

            /*
            * ============================================================
            * 4. COMMIT
            * ============================================================
            */
            $this->db->commit();

            /*
            * Return created loan
            */
            return $this->find($id) ?? [];

        } catch (\Throwable $e) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    // Loan Applications
    /**
     * Create a new loan application
     */
    public function createLoanApplication(array $data): array
    {
        $this->db->beginTransaction();

        try {
            /*
            * Generate application ID and application number
            */
            $id = $data['id'] ?? ('la_' . bin2hex(random_bytes(6)));

            $applicationNo = $data['application_no']
                ?? ('LA-' . date('Y') . '-' . str_pad(
                    (string) mt_rand(1, 99999),
                    5,
                    '0',
                    STR_PAD_LEFT
                ));

            /*
            * Validate required fields
            */
            if (empty($data['member_id'])) {
                throw new \InvalidArgumentException('Member is required.');
            }

            if (empty($data['loan_product_id'])) {
                throw new \InvalidArgumentException('Loan product is required.');
            }

            if (empty($data['branch_id'])) {
                throw new \InvalidArgumentException('Branch is required.');
            }

            if (!isset($data['applied_amount'])) {
                throw new \InvalidArgumentException('Applied amount is required.');
            }

            if (!isset($data['term_months'])) {
                throw new \InvalidArgumentException('Term is required.');
            }

            /*
            * Validate member
            */
            $memberStmt = $this->db->prepare("
                SELECT id
                FROM members
                WHERE id = ?
                LIMIT 1
            ");

            $memberStmt->execute([
                $data['member_id']
            ]);

            $member = $memberStmt->fetch(PDO::FETCH_ASSOC);

            if (!$member) {
                throw new \InvalidArgumentException('Member not found.');
            }

            /*
            * Validate loan product
            */
            $productStmt = $this->db->prepare("
                SELECT *
                FROM loan_products
                WHERE id = ?
                LIMIT 1
            ");

            $productStmt->execute([
                $data['loan_product_id']
            ]);

            $product = $productStmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new \InvalidArgumentException('Loan product not found.');
            }

            /*
            * Validate branch
            */
            $branchStmt = $this->db->prepare("
                SELECT id
                FROM branches
                WHERE id = ?
                LIMIT 1
            ");

            $branchStmt->execute([
                $data['branch_id']
            ]);

            $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);

            if (!$branch) {
                throw new \InvalidArgumentException('Branch not found.');
            }

            /*
            * Application values
            */
            $appliedAmount = (float) $data['applied_amount'];
            $termMonths    = (int) $data['term_months'];

            $purpose = $data['purpose'] ?? null;

            $status = $data['status'] ?? 'Draft';

            /*
            * Validate amount
            */
            if ($appliedAmount <= 0) {
                throw new \InvalidArgumentException(
                    'Applied amount must be greater than zero.'
                );
            }

            /*
            * Validate term
            *
            * If the loan product contains min/max term settings,
            * enforce them here.
            */
            $minTerm = isset($product['min_term_months'])
                ? (int) $product['min_term_months']
                : null;

            $maxTerm = isset($product['max_term_months'])
                ? (int) $product['max_term_months']
                : null;

            if ($minTerm !== null && $termMonths < $minTerm) {
                throw new \InvalidArgumentException(
                    "Loan term cannot be less than {$minTerm} months."
                );
            }

            if ($maxTerm !== null && $termMonths > $maxTerm) {
                throw new \InvalidArgumentException(
                    "Loan term cannot exceed {$maxTerm} months."
                );
            }

            if ($termMonths <= 0) {
                throw new \InvalidArgumentException(
                    'Loan term must be greater than zero.'
                );
            }

            /*
            * Validate loan amount against product limits
            */
            $minAmount = isset($product['min_amount'])
                ? (float) $product['min_amount']
                : null;

            $maxAmount = isset($product['max_amount'])
                ? (float) $product['max_amount']
                : null;

            if ($minAmount !== null && $appliedAmount < $minAmount) {
                throw new \InvalidArgumentException(
                    'Applied amount is below the minimum loan amount of ' .
                    number_format($minAmount, 2)
                );
            }

            if ($maxAmount !== null && $appliedAmount > $maxAmount) {
                throw new \InvalidArgumentException(
                    'Applied amount exceeds the maximum loan amount of ' .
                    number_format($maxAmount, 2)
                );
            }

            /*
            * Submitted date
            *
            * Draft applications can have NULL submitted_date.
            * Once submitted, use today's date unless explicitly supplied.
            */
            $submittedDate = null;

            if ($status !== 'Draft') {
                $submittedDate = $data['submitted_date']
                    ?? date('Y-m-d');
            } elseif (!empty($data['submitted_date'])) {
                $submittedDate = $data['submitted_date'];
            }

            /*
            * Review information
            */
            $reviewedBy = $data['reviewed_by'] ?? null;

            $reviewedDate = $data['reviewed_date'] ?? null;

            /*
            * Approved amount
            *
            * Normally NULL until the application is approved.
            */
            $approvedAmount = null;

            if (isset($data['approved_amount'])) {
                $approvedAmount = (float) $data['approved_amount'];

                if ($approvedAmount <= 0) {
                    throw new \InvalidArgumentException(
                        'Approved amount must be greater than zero.'
                    );
                }

                /*
                * Approved amount should normally not exceed
                * the amount originally applied for.
                */
                if ($approvedAmount > $appliedAmount) {
                    throw new \InvalidArgumentException(
                        'Approved amount cannot exceed the applied amount.'
                    );
                }
            }

            /*
            * Remarks
            */
            $remarks = $data['remarks'] ?? null;

            /*
            * Insert application
            */
            $sql = "
                INSERT INTO loan_applications (
                    id,
                    application_no,
                    member_id,
                    loan_product_id,
                    branch_id,
                    applied_amount,
                    term_months,
                    purpose,
                    status,
                    submitted_date,
                    reviewed_by,
                    reviewed_date,
                    approved_amount,
                    remarks
                ) VALUES (
                    :id,
                    :application_no,
                    :member_id,
                    :loan_product_id,
                    :branch_id,
                    :applied_amount,
                    :term_months,
                    :purpose,
                    :status,
                    :submitted_date,
                    :reviewed_by,
                    :reviewed_date,
                    :approved_amount,
                    :remarks
                )
            ";

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                'id'              => $id,
                'application_no'  => $applicationNo,
                'member_id'       => $data['member_id'],
                'loan_product_id' => $data['loan_product_id'],
                'branch_id'       => $data['branch_id'],
                'applied_amount'  => $appliedAmount,
                'term_months'     => $termMonths,
                'purpose'         => $purpose,
                'status'          => $status,
                'submitted_date'  => $submittedDate,
                'reviewed_by'     => $reviewedBy,
                'reviewed_date'   => $reviewedDate,
                'approved_amount' => $approvedAmount,
                'remarks'         => $remarks
            ]);

            /*
            * Commit
            */
            $this->db->commit();

            /*
            * Return the created application
            */
            $resultStmt = $this->db->prepare("
                SELECT
                    la.*,
                    CONCAT(
                        COALESCE(m.first_name, ''),
                        ' ',
                        COALESCE(m.last_name, '')
                    ) AS member_name,
                    lp.name AS product_name,
                    b.name AS branch_name
                FROM loan_applications la
                LEFT JOIN members m
                    ON m.id = la.member_id
                LEFT JOIN loan_products lp
                    ON lp.id = la.loan_product_id
                LEFT JOIN branches b
                    ON b.id = la.branch_id
                WHERE la.id = ?
                LIMIT 1
            ");

            $resultStmt->execute([$id]);

            return $resultStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        } catch (\Throwable $e) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }


    /**
     * Record loan repayment and allocate against unpaid schedule installments
     */
    public function recordPayment(array $data): array
    {
        $this->db->beginTransaction();

        try {
            $paymentId = $data['id'] ?? ('lp_' . bin2hex(random_bytes(6)));
            $loanId    = $data['loan_id'];
            $amount    = (float)$data['amount_paid'];
            $payDate   = $data['payment_date'] ?? date('Y-m-d');
            $refNo     = $data['or_number'] ?? $data['receipt_no'] ?? ('OR-' . date('Ymd') . '-' . mt_rand(1000, 9999));

            $loan = $this->find($loanId);
            if (!$loan) {
                throw new \InvalidArgumentException('Loan not found.');
            }

            $cashAccountId = $data['cash_account_id'] ?? $loan['disbursed_from_cash_account_id'];
            $receivedBy = $data['received_by'] ?? $data['performed_by'] ?? 'system';

            // Fetch unpaid schedules
            $schedStmt = $this->db->prepare("
                SELECT * FROM loan_amortization_schedules
                WHERE loan_id = ? AND status != 'Paid'
                ORDER BY installment_no ASC
            ");
            $schedStmt->execute([$loanId]);
            $unpaidSchedules = $schedStmt->fetchAll(PDO::FETCH_ASSOC);

            $remainingCash = $amount;
            $totalPrincipalPaid = 0.0;
            $totalInterestPaid  = 0.0;

            $updateSched = $this->db->prepare("
                UPDATE loan_amortization_schedules
                SET paid_principal = paid_principal + :p,
                    paid_interest  = paid_interest + :i,
                    status         = :status
                WHERE id = :id
            ");

            foreach ($unpaidSchedules as $sched) {
                if ($remainingCash <= 0) break;

                $dueInterest  = (float)$sched['interest'] - (float)$sched['paid_interest'];
                $duePrincipal = (float)$sched['principal'] - (float)$sched['paid_principal'];

                $payInterest  = min($remainingCash, max(0, $dueInterest));
                $remainingCash -= $payInterest;
                $totalInterestPaid += $payInterest;

                $payPrincipal = min($remainingCash, max(0, $duePrincipal));
                $remainingCash -= $payPrincipal;
                $totalPrincipalPaid += $payPrincipal;

                $newPaidI = (float)$sched['paid_interest'] + $payInterest;
                $newPaidP = (float)$sched['paid_principal'] + $payPrincipal;

                $isPaid = ($newPaidI >= (float)$sched['interest'] - 0.01) && ($newPaidP >= (float)$sched['principal'] - 0.01);

                $updateSched->execute([
                    'p'      => $payPrincipal,
                    'i'      => $payInterest,
                    'status' => $isPaid ? 'Paid' : 'Partially Paid',
                    'id'     => $sched['id']
                ]);
            }

            $stmt = $this->db->prepare("
                INSERT INTO loan_payments (
                    id, receipt_no, loan_id, member_id, payment_date, total_amount,
                    cash_account_id, received_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $paymentId,
                $refNo,
                $loanId,
                $loan['member_id'],
                $payDate,
                $amount,
                $cashAccountId,
                $receivedBy
            ]);

            // Update loan current balance
            $updLoan = $this->db->prepare("
                UPDATE loans
                SET current_balance = GREATEST(0, current_balance - ?),
                    total_principal_paid = total_principal_paid + ?,
                    total_interest_paid = total_interest_paid + ?,
                    status = CASE WHEN (current_balance - ?) <= 0.01 THEN 'Fully Paid' ELSE status END
                WHERE id = ?
            ");
            $updLoan->execute([
                $totalPrincipalPaid,
                $totalPrincipalPaid,
                $totalInterestPaid,
                $totalPrincipalPaid,
                $loanId
            ]);

            $this->db->commit();

            return [
                'payment_id'      => $paymentId,
                'reference_no'    => $refNo,
                'amount_paid'     => $amount,
                'principal_paid'  => $totalPrincipalPaid,
                'interest_paid'   => $totalInterestPaid,
                'updated_loan'    => $this->find($loanId)
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Delete loan and its schedules
     */
    public function delete(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM loans WHERE id = ?');
        return $stmt->execute([$id]);
    }
    /**
     * Update an existing loan.
     */
    public function update(string $loanId, array $data): array
    {
        $this->db->beginTransaction();

        try {
            // Check if loan exists
            $stmt = $this->db->prepare("
                SELECT *
                FROM loans
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([
                ':id' => $loanId
            ]);

            $loan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$loan) {
                throw new \RuntimeException('Loan not found.');
            }

            /*
            * Only allow fields that should be updated.
            */
            $allowedFields = [
                'status',
                'loan_amount',
                'term_months',
                'interest_rate',
                'interest_method',
                'purpose',
                'notes',
                'approved_amount',
                'approved_by',
                'approved_at',
                'rejected_reason',
                'released_at',
                'released_by',
            ];

            $updates = [];
            $params = [
                ':id' => $loanId
            ];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "`{$field}` = :{$field}";
                    $params[":{$field}"] = $data[$field];
                }
            }

            if (empty($updates)) {
                throw new \RuntimeException('No fields provided for update.');
            }

            // Always update timestamp if your table has updated_at
            $updates[] = "updated_at = NOW()";

            $sql = "
                UPDATE loans
                SET " . implode(", ", $updates) . "
                WHERE id = :id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            /*
            * Get updated loan
            */
            $stmt = $this->db->prepare("
                SELECT *
                FROM loans
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([
                ':id' => $loanId
            ]);

            $updatedLoan = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Loan updated successfully.',
                'data' => $updatedLoan
            ];

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Update Loan Status
     */
    public function updateStatus(string $id, array $status): array
    {   
        $this->db->beginTransaction();

        $stmt = $this->db->prepare('UPDATE `loans` SET `status`= ? WHERE `id`= ?');
        $stmt->execute([$status['status'],$id]);

        $this->db->commit();
        return $this->find($id) ?? [];
    
    }

    /**
     * NEW METHODS
     */
    /**
     * Create a loan application only.
     *
     * Does not create a loan.
     * Does not disburse cash.
     * Does not create amortization.
     */
    public function createApplication(array $data): array
    {
        $this->db->beginTransaction();

        try {
            if (empty($data['member_id'])) {
                throw new \InvalidArgumentException(
                    'Member is required.'
                );
            }

            if (empty($data['loan_product_id'])) {
                throw new \InvalidArgumentException(
                    'Loan product is required.'
                );
            }

            if (empty($data['branch_id'])) {
                throw new \InvalidArgumentException(
                    'Branch is required.'
                );
            }

            $principal = (float) (
                $data['principal_amount'] ?? 0
            );

            if ($principal <= 0) {
                throw new \InvalidArgumentException(
                    'Principal amount must be greater than zero.'
                );
            }

            /*
            * ------------------------------------------------------------
            * Get loan product
            * ------------------------------------------------------------
            */

            $productStmt = $this->db->prepare("
                SELECT *
                FROM loan_products
                WHERE id = :id
                LIMIT 1
            ");

            $productStmt->execute([
                'id' => $data['loan_product_id']
            ]);
           
            $product = $productStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$product) {
                throw new \InvalidArgumentException(
                    'Loan product not found.'
                );
            }

            /*
            * ------------------------------------------------------------
            * Validate loan amount
            * ------------------------------------------------------------
            */

            $minAmount = (float) (
                $product['min_amount'] ?? 0
            );

            $maxAmount = (float) (
                $product['max_amount'] ?? 0
            );

            if (
                $minAmount > 0 &&
                $principal < $minAmount
            ) {
                throw new \InvalidArgumentException(
                    "Loan amount cannot be less than {$minAmount}."
                );
            }

            if (
                $maxAmount > 0 &&
                $principal > $maxAmount
            ) {
                throw new \InvalidArgumentException(
                    "Loan amount cannot exceed {$maxAmount}."
                );
            }

            /*
            * ------------------------------------------------------------
            * Term
            * ------------------------------------------------------------
            */

            $term = (int) (
                $data['term_months']
                ?? $product['min_term_months']
                ?? 12
            );

            $minTerm = (int) (
                $product['min_term_months'] ?? 0
            );

            $maxTerm = (int) (
                $product['max_term_months'] ?? 0
            );

            if (
                $minTerm > 0 &&
                $term < $minTerm
            ) {
                throw new \InvalidArgumentException(
                    "Loan term cannot be less than {$minTerm} months."
                );
            }

            if (
                $maxTerm > 0 &&
                $term > $maxTerm
            ) {
                throw new \InvalidArgumentException(
                    "Loan term cannot exceed {$maxTerm} months."
                );
            }

            /*
            * ------------------------------------------------------------
            * IDs
            * ------------------------------------------------------------
            */

            $applicationId = $data['application_id']
                ?? 'la_' . bin2hex(random_bytes(6));

            $applicationNo = $data['application_no']
                ?? (
                    'LA-' .
                    date('Y') .
                    '-' .
                    str_pad(
                        (string) mt_rand(1, 99999),
                        5,
                        '0',
                        STR_PAD_LEFT
                    )
                );

            $submittedDate = $data['submitted_date']
                ?? date('Y-m-d');

            /*
            * ------------------------------------------------------------
            * Insert application
            * ------------------------------------------------------------
            */

            $stmt = $this->db->prepare("
                INSERT INTO loan_applications (
                    id,
                    application_no,
                    member_id,
                    loan_product_id,
                    branch_id,
                    applied_amount,
                    term_months,
                    purpose,
                    status,
                    submitted_date,
                    reviewed_by,
                    reviewed_date,
                    approved_amount,
                    remarks
                ) VALUES (
                    :id,
                    :application_no,
                    :member_id,
                    :loan_product_id,
                    :branch_id,
                    :applied_amount,
                    :term_months,
                    :purpose,
                    :status,
                    :submitted_date,
                    :reviewed_by,
                    :reviewed_date,
                    :approved_amount,
                    :remarks
                )
            ");
         
            $stmt->execute([
                'id' => $applicationId,

                'application_no' => $applicationNo,

                'member_id' => $data['member_id'],

                'loan_product_id' =>
                    $data['loan_product_id'],

                'branch_id' =>
                    $data['branch_id'],

                'applied_amount' =>
                    $principal,

                'term_months' =>
                    $term,

                'purpose' =>
                    $data['purpose'] ?? null,

                'status' =>
                    $data['status'] ?? 'Pending',

                'submitted_date' =>
                    $submittedDate,

                'reviewed_by' =>
                    null,

                'reviewed_date' =>
                    null,

                'approved_amount' =>
                    null,

                'remarks' =>
                    $data['remarks'] ?? null
            ]);
            $this->audit->recordAuditTrail(
                'Loan Application',
                'None',
                [
                    'id' => $applicationId,
                    'application_no' => $applicationNo,
                    'member_id' => $data['member_id'],
                    'loan_product_id' => $data['loan_product_id'],
                    'branch_id' => $data['branch_id'],
                    'applied_amount' => $principal,
                    'term_months' => $term,
                    'purpose' => $data['purpose'] ?? null,
                    'status' => $data['status'] ?? 'Pending',
                    'submitted_date' => $submittedDate,
                ],
                $data['performed_by'] ?? 'System',
                'Created loan application'
            );
            $this->db->commit();
            

            return $this->findApplication($applicationId) ?? [];

        } catch (\Throwable $e) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }
    /**
     * Find loan application.
     */
    public function findApplication(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                la.*,

                lp.code AS loan_product_code,
                lp.name AS loan_product_name,

                b.name AS branch_name

            FROM loan_applications la

            LEFT JOIN loan_products lp
                ON la.loan_product_id = lp.id

            LEFT JOIN branches b
                ON la.branch_id = b.id

            WHERE la.id = :id

            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Get all loan applications with optional filtering.
     */
    public function allApplications(?string $branchId = null, ?string $status = null, ?string $memberId = null): array
    {
        $sql = "
            SELECT
                la.*,
                CONCAT(COALESCE(m.first_name, ''), ' ', COALESCE(m.last_name, '')) AS member_name,
                m.member_no,
                lp.code AS loan_product_code,
                lp.name AS loan_product_name,
                lp.name AS product_name,
                b.name AS branch_name
            FROM loan_applications la
            LEFT JOIN members m ON la.member_id = m.id
            LEFT JOIN loan_products lp ON la.loan_product_id = lp.id
            LEFT JOIN branches b ON la.branch_id = b.id
            WHERE 1=1
        ";
        
        $params = [];
        if ($branchId && $branchId !== 'all') {
            $sql .= " AND la.branch_id = :branch_id";
            $params['branch_id'] = $branchId;
        }
        if ($status && $status !== 'all') {
            $sql .= " AND LOWER(la.status) = LOWER(:status)";
            $params['status'] = $status;
        }
        if ($memberId) {
            $sql .= " AND la.member_id = :member_id";
            $params['member_id'] = $memberId;
        }
        $sql .= " ORDER BY la.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Reject a loan application.
     */
    public function rejectApplication(array $data): array
    {
        $applicationId = $data['application_id'] ?? null;
        if (!$applicationId) {
            throw new \InvalidArgumentException('Loan application ID is required.');
        }

        $app = $this->findApplication($applicationId);
        if (!$app) {
            throw new \RuntimeException('Loan application not found.');
        }

        $reviewer = $data['reviewed_by'] ?? $data['performed_by'] ?? 'Credit Committee';
        $today = date('Y-m-d');
        $remarks = $data['remarks'] ?? $app['remarks'] ?? null;

        $stmt = $this->db->prepare("
            UPDATE loan_applications
            SET status = 'Rejected',
                reviewed_by = :reviewed_by,
                reviewed_date = :reviewed_date,
                remarks = :remarks
            WHERE id = :id
        ");
        $stmt->execute([
            'reviewed_by' => $reviewer,
            'reviewed_date' => $today,
            'remarks' => $remarks,
            'id' => $applicationId
        ]);

        return $this->findApplication($applicationId);
    }

    /**
     * Approve a loan application.
     *
     * Does not create the loan.
     * Does not disburse cash.
     */
    public function approveApplication(array $data): array
    {
        $this->db->beginTransaction();

        try {
            $applicationId = $data['application_id']
                ?? null;

            if (!$applicationId) {
                throw new \InvalidArgumentException(
                    'Loan application is required.'
                );
            }

            /*
            * Get application
            */
            $stmt = $this->db->prepare("
                SELECT *
                FROM loan_applications
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([
                'id' => $applicationId
            ]);

            $application = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$application) {
                throw new \InvalidArgumentException(
                    'Loan application not found.'
                );
            }

            /*
            * Check current status
            */
            if ($application['status'] !== 'Pending') {
                throw new \InvalidArgumentException(
                    'Only pending applications can be approved.'
                );
            }

            /*
            * Approved amount
            */
            $approvedAmount = (float) (
                $data['approved_amount']
                ?? $application['applied_amount']
            );

            if ($approvedAmount <= 0) {
                throw new \InvalidArgumentException(
                    'Approved amount must be greater than zero.'
                );
            }

            /*
            * Reviewer
            */
            $approvedBy =
                $data['approved_by']
                ?? $data['performed_by']
                ?? null;

            if (!$approvedBy) {
                throw new \InvalidArgumentException(
                    'Approving user is required.'
                );
            }

            /*
            * Update
            */
            $update = $this->db->prepare("
                UPDATE loan_applications
                SET
                    status = 'Approved',
                    approved_amount = :approved_amount,
                    reviewed_by = :reviewed_by,
                    reviewed_date = :reviewed_date,
                    remarks = :remarks
                WHERE id = :id
            ");

            $update->execute([
                'approved_amount' =>
                    $approvedAmount,

                'reviewed_by' =>
                    $approvedBy,

                'reviewed_date' =>
                    $data['reviewed_date']
                    ?? date('Y-m-d'),

                'remarks' =>
                    $data['remarks']
                    ?? $application['remarks'],

                'id' =>
                    $applicationId
            ]);
            $this->audit->recordAuditTrail(
            'Loan Status',
            'Pending',
            [
                'application_id' => $applicationId,
                'approved_amount' => $approvedAmount,
                'status' => 'Approved',
                'approved_by' => $approvedBy ?? 'System',
                'remarks' =>  $data['remarks'] ?? $application['remarks'] ,
            ],
            $approvedBy ?? 'System',
            'Approved loan application'
        );
            $this->db->commit();

            return $this->findApplication(
                $applicationId
            ) ?? [];

        } catch (\Throwable $e) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }
    /**
     * Disburse an approved loan application.
     *
     * Creates:
     * - loans record
     * - amortization schedule
     *
     * Updates:
     * - loan application status → Released
     *
     * Later this transaction should also include:
     * - cash transaction
     * - GL journal voucher
     */
    public function disburseLoan(array $data): array
    {
        $this->db->beginTransaction();

        try {

            /*
            * ============================================================
            * 1. APPLICATION
            * ============================================================
            */

            $applicationId =
                $data['application_id'] ?? null;

            if (!$applicationId) {
                throw new \InvalidArgumentException(
                    'Loan application is required.'
                );
            }

            $applicationStmt = $this->db->prepare("
                SELECT *
                FROM loan_applications
                WHERE id = :id
                LIMIT 1
                FOR UPDATE
            ");

            $applicationStmt->execute([
                'id' => $applicationId
            ]);

            $application =
                $applicationStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$application) {
                throw new \InvalidArgumentException(
                    'Loan application not found.'
                );
            }

            /*
            * ============================================================
            * 2. APPLICATION MUST BE APPROVED
            * ============================================================
            */

            if ($application['status'] !== 'Approved') {
                throw new \InvalidArgumentException(
                    'Only approved loan applications can be disbursed.'
                );
            }

            /*
            * ============================================================
            * 3. PREVENT DOUBLE DISBURSEMENT
            * ============================================================
            */

            $existingStmt = $this->db->prepare("
                SELECT
                    id,
                    loan_account_no
                FROM loans
                WHERE application_id = :application_id
                LIMIT 1
            ");

            $existingStmt->execute([
                'application_id' =>
                    $applicationId
            ]);

            $existingLoan =
                $existingStmt->fetch(\PDO::FETCH_ASSOC);

            if ($existingLoan) {
                throw new \InvalidArgumentException(
                    'This loan application has already been disbursed.'
                );
            }

            /*
            * ============================================================
            * 4. CASH ACCOUNT
            * ============================================================
            */

            $cashAccountId =
                $data['disbursed_from_cash_account_id']
                ?? $data['cash_account_id']
                ?? null;

            if (!$cashAccountId) {
                throw new \InvalidArgumentException(
                    'Disbursement cash account is required.'
                );
            }

            /*
            * ============================================================
            * 5. PRODUCT
            * ============================================================
            */

            $productStmt = $this->db->prepare("
                SELECT *
                FROM loan_products
                WHERE id = :id
                LIMIT 1
            ");

            $productStmt->execute([
                'id' =>
                    $application['loan_product_id']
            ]);

            $product =
                $productStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$product) {
                throw new \InvalidArgumentException(
                    'Loan product not found.'
                );
            }

            /*
            * ============================================================
            * 6. LOAN VALUES
            * ============================================================
            */

            $principal = (float) (
                $application['approved_amount']
                ?? $application['applied_amount']
            );

            if ($principal <= 0) {
                throw new \InvalidArgumentException(
                    'Approved loan amount must be greater than zero.'
                );
            }

            $rate = (float) (
                $data['annual_interest_rate']
                ?? $product['annual_interest_rate']
            );

            $term = (int) (
                $application['term_months']
            );

            $method =
                $data['interest_calculation_method']
                ?? $product['interest_calculation_method'];

            $frequency =
                $data['payment_frequency']
                ?? $product['payment_frequency']
                ?? 'Monthly';

            $startDate =
                $data['disbursement_date']
                ?? date('Y-m-d');

            $firstDueDate =
                $data['first_due_date']
                ?? (
                    new \DateTime($startDate)
                )
                    ->modify('+1 month')
                    ->format('Y-m-d');

            $maturityDate =
                $data['maturity_date']
                ?? (
                    new \DateTime($firstDueDate)
                )
                    ->modify(
                        '+' .
                        max(0, $term - 1) .
                        ' months'
                    )
                    ->format('Y-m-d');

            $processingFee = (float) (
                $data['processing_fee'] ?? 0
            );

            $serviceFee = (float) (
                $data['service_fee'] ?? 0
            );

            $netDisbursed = isset(
                $data['net_disbursed']
            )
                ? (float) $data['net_disbursed']
                : max(
                    0,
                    $principal
                    - $processingFee
                    - $serviceFee
                );

            /*
            * ============================================================
            * 7. GENERATE LOAN ID
            * ============================================================
            */

            $loanId =
                $data['id']
                ?? 'ln_' . bin2hex(random_bytes(6));

            $accountNo =
                $data['loan_account_no']
                ?? (
                    'LN-' .
                    date('Y') .
                    '-' .
                    str_pad(
                        (string) mt_rand(1, 99999),
                        5,
                        '0',
                        STR_PAD_LEFT
                    )
                );

            /*
            * ============================================================
            * 8. CREATE LOAN
            * ============================================================
            */

            $sql = "
                INSERT INTO loans (
                    id,
                    loan_account_no,
                    application_id,
                    member_id,
                    loan_product_id,
                    product_version,
                    branch_id,
                    principal_amount,
                    annual_interest_rate,
                    interest_calculation_method,
                    term_months,
                    payment_frequency,
                    disbursement_date,
                    first_due_date,
                    maturity_date,
                    processing_fee,
                    service_fee,
                    net_disbursed,
                    disbursed_from_cash_account_id,
                    status,
                    current_balance,
                    total_principal_paid,
                    total_interest_paid,
                    total_penalty_paid,
                    total_fees_paid,
                    approved_by,
                    approved_date
                )
                VALUES (
                    :id,
                    :loan_account_no,
                    :application_id,
                    :member_id,
                    :loan_product_id,
                    :product_version,
                    :branch_id,
                    :principal_amount,
                    :annual_interest_rate,
                    :interest_calculation_method,
                    :term_months,
                    :payment_frequency,
                    :disbursement_date,
                    :first_due_date,
                    :maturity_date,
                    :processing_fee,
                    :service_fee,
                    :net_disbursed,
                    :disbursed_from_cash_account_id,
                    :status,
                    :current_balance,
                    0,
                    0,
                    0,
                    0,
                    :approved_by,
                    :approved_date
                )
            ";

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                'id' =>
                    $loanId,

                'loan_account_no' =>
                    $accountNo,

                'application_id' =>
                    $applicationId,

                'member_id' =>
                    $application['member_id'],

                'loan_product_id' =>
                    $application['loan_product_id'],

                'product_version' =>
                    $data['product_version']
                    ?? ($product['version'] ?? 1),

                'branch_id' =>
                    $application['branch_id'],

                'principal_amount' =>
                    $principal,

                'annual_interest_rate' =>
                    $rate,

                'interest_calculation_method' =>
                    $method,

                'term_months' =>
                    $term,

                'payment_frequency' =>
                    $frequency,

                'disbursement_date' =>
                    $startDate,

                'first_due_date' =>
                    $firstDueDate,

                'maturity_date' =>
                    $maturityDate,

                'processing_fee' =>
                    $processingFee,

                'service_fee' =>
                    $serviceFee,

                'net_disbursed' =>
                    $netDisbursed,

                'disbursed_from_cash_account_id' =>
                    $cashAccountId,

                'status' =>
                    'Active',

                'current_balance' =>
                    $principal,

                'approved_by' =>
                    $data['approved_by']
                    ?? $data['performed_by']
                    ?? null,

                'approved_date' =>
                    $data['approved_date']
                    ?? $startDate
            ]);

            /*
            * ============================================================
            * 9. AMORTIZATION
            * ============================================================
            */

            $schedule =
                \App\Services\AmortizationService::generateSchedule(
                    $principal,
                    $rate,
                    $term,
                    $method,
                    $startDate,
                    $frequency
                );

            $schedStmt = $this->db->prepare("
                INSERT INTO loan_amortization_schedules (
                    id,
                    loan_id,
                    installment_no,
                    due_date,
                    principal,
                    interest,
                    total_installment,
                    principal_balance,
                    paid_principal,
                    paid_interest,
                    status
                )
                VALUES (
                    :id,
                    :loan_id,
                    :installment_no,
                    :due_date,
                    :principal,
                    :interest,
                    :total_installment,
                    :principal_balance,
                    0,
                    0,
                    'Unpaid'
                )
            ");

            foreach ($schedule as $row) {

                $schedStmt->execute([
                    'id' =>
                        'las_' .
                        bin2hex(random_bytes(6)),

                    'loan_id' =>
                        $loanId,

                    'installment_no' =>
                        $row['installment_no'],

                    'due_date' =>
                        $row['due_date'],

                    'principal' =>
                        $row['principal'],

                    'interest' =>
                        $row['interest'],

                    'total_installment' =>
                        $row['total_installment'],

                    'principal_balance' =>
                        $row['principal_balance']
                ]);
            }

            /*
            * ============================================================
            * 10. MARK APPLICATION AS RELEASED
            * ============================================================
            */

            $updateApplication =
                $this->db->prepare("
                    UPDATE loan_applications
                    SET
                        status = 'Released'
                    WHERE id = :id
                ");

            $updateApplication->execute([
                'id' =>
                    $applicationId
            ]);

            /*
            * ============================================================
            * 11. AUDIT — LOAN DISBURSEMENT
            * ============================================================
            */

            $this->audit->recordAuditTrail(
                'Loan',
                'None',
                [
                    'id' => $loanId,
                    'loan_account_no' => $accountNo,
                    'application_id' => $applicationId,
                    'member_id' => $application['member_id'],
                    'loan_product_id' => $application['loan_product_id'],
                    'branch_id' => $application['branch_id'],
                    'principal_amount' => $principal,
                    'annual_interest_rate' => $rate,
                    'interest_calculation_method' => $method,
                    'term_months' => $term,
                    'payment_frequency' => $frequency,
                    'disbursement_date' => $startDate,
                    'first_due_date' => $firstDueDate,
                    'maturity_date' => $maturityDate,
                    'processing_fee' => $processingFee,
                    'service_fee' => $serviceFee,
                    'net_disbursed' => $netDisbursed,
                    'cash_account_id' => $cashAccountId,
                    'status' => 'Active',
                    'current_balance' => $principal,
                ],
                $data['performed_by']
                    ?? $data['approved_by']
                    ?? 'System',
                'Disbursed loan'
            );

            /*
            * ============================================================
            * 12. AUDIT — APPLICATION RELEASED
            * ============================================================
            */

            $this->audit->recordAuditTrail(
                'Loan Application',
                [
                    'id' => $application['id'],
                    'application_no' => $application['application_no'],
                    'status' => $application['status'],
                    'approved_amount' => $application['approved_amount'],
                ],
                [
                    'id' => $application['id'],
                    'application_no' => $application['application_no'],
                    'status' => 'Released',
                    'approved_amount' => $application['approved_amount'],
                ],
                $data['performed_by']
                    ?? $data['approved_by']
                    ?? 'System',
                'Marked loan application as released after disbursement'
            );



            /*
            * ============================================================
            * 11. COMMIT
            * ============================================================
            */

            $this->db->commit();

            return $this->find($loanId) ?? [];

        } catch (\Throwable $e) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }
}
