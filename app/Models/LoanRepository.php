<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AmortizationService;
use PDO;

class LoanRepository
{
    public function __construct(private PDO $db)
    {
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
    public function createLoan(array $data): array
    {
        $this->db->beginTransaction();

        try {
            $id = $data['id'] ?? ('ln_' . bin2hex(random_bytes(6)));
            $accountNo = $data['loan_account_no'] ?? ('LN-' . date('Y') . '-' . str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT));

            $principal = (float)$data['principal_amount'];
            $rate      = (float)$data['annual_interest_rate'];
            $term      = (int)$data['term_months'];
            $method    = $data['interest_calculation_method'] ?? 'Diminishing Balance';
            $startDate = $data['disbursement_date'] ?? date('Y-m-d');

            // Insert loan record
            $sql = "INSERT INTO loans (
                id, loan_account_no, member_id, loan_product_id, product_version, branch_id,
                principal_amount, annual_interest_rate, term_months, disbursement_date,
                current_balance, status
            ) VALUES (
                :id, :loan_account_no, :member_id, :loan_product_id, :product_version, :branch_id,
                :principal_amount, :annual_interest_rate, :term_months, :disbursement_date,
                :current_balance, :status
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id'                   => $id,
                'loan_account_no'      => $accountNo,
                'member_id'            => $data['member_id'],
                'loan_product_id'      => $data['loan_product_id'],
                'product_version'      => $data['product_version'] ?? 1,
                'branch_id'            => $data['branch_id'],
                'principal_amount'     => $principal,
                'annual_interest_rate' => $rate,
                'term_months'          => $term,
                'disbursement_date'    => $startDate,
                'current_balance'      => $principal,
                'status'               => 'Active',
            ]);

            // Generate Amortization Schedule
            $schedule = AmortizationService::generateSchedule(
                $principal,
                $rate,
                $term,
                $method,
                $startDate
            );

            $schedStmt = $this->db->prepare("
                INSERT INTO loan_amortization_schedules (
                    id, loan_id, installment_no, due_date, principal, interest,
                    total_installment, principal_balance, paid_principal, paid_interest, status
                ) VALUES (
                    :id, :loan_id, :installment_no, :due_date, :principal, :interest,
                    :total_installment, :principal_balance, 0, 0, 'Unpaid'
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
                    'principal_balance' => $row['principal_balance'],
                ]);
            }

            $this->db->commit();
            return $this->find($id) ?? [];
        } catch (\Exception $e) {
            $this->db->rollBack();
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
            $refNo     = $data['or_number'] ?? ('OR-' . date('Ymd') . '-' . mt_rand(1000, 9999));

            // Insert payment receipt
            $stmt = $this->db->prepare("
                INSERT INTO loan_payments (
                    id, loan_id, payment_date, amount_paid, principal_portion, interest_portion,
                    penalty_portion, fees_portion, reference_number, notes
                ) VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?)
            ");

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

            $stmt->execute([
                $paymentId,
                $loanId,
                $payDate,
                $amount,
                $totalPrincipalPaid,
                $totalInterestPaid,
                $refNo,
                $data['notes'] ?? 'Installment payment'
            ]);

            // Update loan current balance
            $updLoan = $this->db->prepare("
                UPDATE loans
                SET current_balance = GREATEST(0, current_balance - ?),
                    status = CASE WHEN (current_balance - ?) <= 0.01 THEN 'Fully Paid' ELSE status END
                WHERE id = ?
            ");
            $updLoan->execute([$totalPrincipalPaid, $totalPrincipalPaid, $loanId]);

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
}
