<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ShareCapitalRepository;
use PDO;

class ShareCapitalController extends BaseController
{
    private ShareCapitalRepository $shareCapital;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->shareCapital = new ShareCapitalRepository($db);
    }

    /**
     * GET /api/share-capital/accounts
     */
    public function index(): never
    {
        $memberId = $this->getQuery('memberId');
        $result = $this->shareCapital->all($memberId);

        $this->json([
            'success' => true,
            'data'    => $result,
            'total'   => count($result)
        ]);
    }

    /**
     * GET /api/share-capital/accounts/:id
     */
    public function show(string $id): never
    {
        $account = $this->shareCapital->find($id);
        if (!$account) {
            $this->error('Share capital account not found.', 404);
        }

        $account['transactions'] = $this->shareCapital->getTransactions($account['id']);
        $this->success($account);
    }

    /**
     * POST /api/share-capital/accounts
     */
    public function store(): never
    {
        $input = $this->getRequestBody();

        if (empty($input['member_id'])) {
            $this->error('Member ID is required.', 422);
        }

        try {
            $account = $this->shareCapital->createAccount($input);
            $this->success($account, 'Share capital account created successfully.', 201);
        } catch (\Exception $e) {
            $this->error('Failed to create share capital account: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/share-capital/payments
     */
    public function payment(): never
    {
        $input = $this->getRequestBody();

        if (empty($input['share_capital_account_id']) || empty($input['amount']) || (float)$input['amount'] <= 0) {
            $this->error('Valid Share capital account ID and payment amount are required.', 422);
        }

        try {
            $result = $this->shareCapital->recordPayment($input);
            $this->success($result, 'Share capital payment posted successfully.');
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/share-capital/accounts/:id
     */
    public function destroy(string $id): never
    {
        $deleted = $this->shareCapital->delete($id);
        if (!$deleted) {
            $this->error('Failed to delete share capital account.', 400);
        }

        $this->success(['id' => $id], 'Share capital account deleted.');
    }
}
