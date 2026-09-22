<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\LoanRepository;
use PDO;

class LoanController extends BaseController
{
    private LoanRepository $loans;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->loans = new LoanRepository($db);
    }

    /**
     * GET /api/loans
     */
    public function index(): never
    {
        $branchId = $this->getQuery('branchId');
        $status   = $this->getQuery('status');
        $memberId = $this->getQuery('memberId');

        $result = $this->loans->all($branchId, $status, $memberId);
        $this->json([
            'success' => true,
            'data'    => $result,
            'total'   => count($result)
        ]);
    }

    /**
     * GET /api/loans/:id
     */
    public function show(string $id): never
    {
        $loan = $this->loans->find($id);
        if (!$loan) {
            $this->error('Loan record not found', 404);
        }

        $this->success($loan);
    }

    /**
     * GET /api/loans/:id/schedule
     */
    public function schedule(string $id): never
    {
        $schedule = $this->loans->getSchedule($id);
        $this->success($schedule);
    }

    /**
     * POST /api/loans/calculate-schedule
     */
    public function calculateSchedule(): never
    {
        $input = $this->getRequestBody();
        $principal = (float)($input['principal_amount'] ?? $input['principal'] ?? 0);
        $rate = (float)($input['annual_interest_rate'] ?? $input['interest_rate'] ?? 0);
        $term = (int)($input['term_months'] ?? 12);
        $method = $input['interest_calculation_method'] ?? 'Diminishing Balance';
        $startDate = $input['disbursement_date'] ?? date('Y-m-d');
        $frequency = $input['payment_frequency'] ?? 'Monthly';

        if ($principal <= 0 || $term <= 0) {
            $this->error('Valid principal amount and term months are required.', 422);
        }

        $schedule = \App\Services\AmortizationService::generateSchedule(
            $principal,
            $rate,
            $term,
            $method,
            $startDate,
            $frequency
        );

        $totalInterest = array_sum(array_column($schedule, 'interest'));
        $totalPayment = array_sum(array_column($schedule, 'total_installment'));

        $this->json([
            'success'  => true,
            'schedule' => $schedule,
            'summary'  => [
                'principal'     => $principal,
                'total_interest'=> round($totalInterest, 2),
                'total_payment' => round($totalPayment, 2),
                'installments'  => count($schedule)
            ]
        ]);
    }
    /**
     * Update Loan Status
     * PUT /api/loans/:id
     */
    public function updateLoanStatus(string $id):array
    {
        $status = $this->getRequestBody();
        $result = $this->loans->updateStatus($id, $status);
        // return ['status'=>$this->getRequestBody()];
        return $this->success($result,'success');
        
    }
    /**
     * POST /api/loans/originate
     * POST /api/loans/apply
     */
    public function originate(): never
    {
        $this->store();
    }

    public function apply(): never
    {
        $input = $this->getRequestBody();
        $input['disbursed_from_cash_account_id'] = $input['disbursed_from_cash_account_id']
            ?? $input['cash_account_id']
            ?? null;
        $input['status'] = $input['status'] ?? 'Submitted';
        $this->store($input);
    }

    /**
     * POST /api/loans/:id/repay
     * POST /api/loans/repay
     */
    public function repay(?string $id = null): never
    {
        $input = $this->getRequestBody();
        if ($id) {
            $input['loan_id'] = $id;
        }

        if (empty($input['loan_id'])) {
            $this->error('Loan ID is required for repayment.', 422);
        }

        if (empty($input['amount_paid']) && !empty($input['amount'])) {
            $input['amount_paid'] = $input['amount'];
        }

        if (empty($input['amount_paid']) || (float)$input['amount_paid'] <= 0) {
            $this->error('A valid positive repayment amount is required.', 422);
        }

        try {
            $receipt = $this->loans->recordPayment($input);
            $this->success($receipt, 'Loan payment recorded and allocated successfully.');
        } catch (\Exception $e) {
            $this->error('Loan payment processing failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/loans
     */
    
    public function store(?array $requestInput = null): never
    {
        $input = $requestInput ?? $this->getRequestBody();

        if (empty($input['branch_id'])) {
            $this->error('Branch is required.', 422);
        }
        if (empty($input['disbursed_from_cash_account_id']) && empty($input['cash_account_id'])) {
            $this->error('Disbursement cash account is required.', 422);
        }
        $input['disbursed_from_cash_account_id'] = $input['disbursed_from_cash_account_id']
            ?? $input['cash_account_id'];

        if (empty($input['first_due_date'])) {
            $input['first_due_date'] = (new \DateTime($input['disbursement_date'] ?? 'now'))
                ->modify('+1 month')->format('Y-m-d');
        }

        if (empty($input['maturity_date'])) {
            $input['maturity_date'] = (new \DateTime($input['first_due_date']))
                ->modify('+' . max(0, (int)($input['term_months'] ?? 12) - 1) . ' months')
                ->format('Y-m-d');
        }

        if (empty($input['payment_frequency'])) {
            $input['payment_frequency'] = 'Monthly';
        }

        if (!isset($input['net_disbursed'])) {
            $principal = (float)($input['principal_amount'] ?? 0);
            $processingFee = (float)($input['processing_fee'] ?? 0);
            $serviceFee = (float)($input['service_fee'] ?? 0);
            $input['net_disbursed'] = max(0, $principal - $processingFee - $serviceFee);
        }

        $this->persistLoan($input);
    }

    private function persistLoan(array $input): never
    {
        if (empty($input['member_id']) || empty($input['loan_product_id']) || empty($input['principal_amount'])) {
            $this->error('Member, Loan Product, and Principal Amount are required.', 422);
        }

        try {
            $loan = $this->loans->createLoan($input);
            $this->success($loan, 'Loan disbursed and amortization schedule initialized successfully.', 201);
        } catch (\Exception $e) {
            $this->error('Loan disbursement failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/loans/payments
     */
    public function payment(): never
    {
        $input = $this->getRequestBody();

        if (empty($input['loan_id']) || empty($input['amount_paid']) || (float)$input['amount_paid'] <= 0) {
            $this->error('Loan ID and a positive payment amount are required.', 422);
        }

        try {
            $receipt = $this->loans->recordPayment($input);
            $this->success($receipt, 'Loan payment recorded and allocated successfully.');
        } catch (\Exception $e) {
            $this->error('Loan payment processing failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/loans/:id
     */
    public function destroy(string $id): never
    {
        $deleted = $this->loans->delete($id);
        if (!$deleted) {
            $this->error('Failed to delete loan.', 400);
        }

        $this->success(['id' => $id], 'Loan deleted successfully.');
    }
}
