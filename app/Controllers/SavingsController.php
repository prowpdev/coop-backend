<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\SavingsRepository;
use PDO;

class SavingsController extends BaseController
{
    private SavingsRepository $savings;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->savings = new SavingsRepository($db);
    }

    /**
     * GET /api/savings/accounts
     */
    public function index(): never
    {
        $branchId = $this->getQuery('branchId');
        $memberId = $this->getQuery('memberId');

        $result = $this->savings->all($branchId, $memberId);
        $this->json([
            'success' => true,
            'data'    => $result,
            'total'   => count($result)
        ]);
    }

    /**
     * GET /api/savings/accounts/:id
     */
    public function show(string $id): never
    {
        $account = $this->savings->find($id);
        if (!$account) {
            $this->error('Savings account not found.', 404);
        }

        $transactions = $this->savings->getTransactions($account['id']);
        $account['transactions'] = $transactions;

        $this->success($account);
    }

    /**
     * POST /api/savings/accounts
     */
    public function store(): never
    {
        $input = $this->getRequestBody();

        if (empty($input['member_id']) || empty($input['savings_product_id']) || empty($input['branch_id'])) {
            $this->error('Member, savings product, and branch are required.', 422);
        }

        try {
            $account = $this->savings->createAccount($input);
            $this->success($account, 'Savings account opened successfully.', 201);
        } catch (\Exception $e) {
            $this->error('Failed to open savings account: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/savings/transactions
     */
    public function transaction(): never
    {
        $input = $this->getRequestBody();

        if (empty($input['savings_account_id']) || empty($input['amount']) || empty($input['transaction_type'])) {
            $this->error('Account ID, amount, and transaction type (Deposit/Withdrawal) are required.', 422);
        }

        try {
            $result = $this->savings->recordTransaction($input);
            $this->success($result, 'Savings transaction posted successfully.');
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/savings/accounts/:id
     */
    public function destroy(string $id): never
    {
        $deleted = $this->savings->delete($id);
        if (!$deleted) {
            $this->error('Failed to delete savings account.', 400);
        }

        $this->success(['id' => $id], 'Savings account deleted.');
    }
}
