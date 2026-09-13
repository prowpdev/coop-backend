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
     * POST /api/loans
     */
    public function store(): never
    {
        $input = $this->getRequestBody();

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
