<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AccountingRepository;
use PDO;

class AccountingController extends BaseController
{
    private AccountingRepository $accounting;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->accounting = new AccountingRepository($db);
    }

    /**
     * GET /api/accounting/chart
     */
    public function chart(): never
    {
        $accounts = $this->accounting->getChartOfAccounts();
        $this->json([
            'success' => true,
            'data'    => $accounts,
            'total'   => count($accounts)
        ]);
    }

    /**
     * POST /api/accounting/chart
     */
    public function saveAccount(): never
    {
        $input = $this->getRequestBody();

        if (empty($input['account_code']) || empty($input['name']) || empty($input['category']) || empty($input['normal_balance'])) {
            $this->error('Account code, name, category, and normal balance are required.', 422);
        }

        try {
            $account = $this->accounting->saveAccount($input);
            $this->success($account, 'Chart of accounts record saved successfully.');
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/accounting/journals
     */
    public function journals(): never
    {
        $branchId  = $this->getQuery('branchId');
        $startDate = $this->getQuery('startDate');
        $endDate   = $this->getQuery('endDate');

        $entries = $this->accounting->getJournalEntries($branchId, $startDate, $endDate);
        $this->json([
            'success' => true,
            'data'    => $entries,
            'total'   => count($entries)
        ]);
    }

    /**
     * GET /api/accounting/journals/:id
     */
    public function showJournal(string $id): never
    {
        $entry = $this->accounting->findJournalEntry($id);
        if (!$entry) {
            $this->error('Journal entry not found.', 404);
        }

        $this->success($entry);
    }

    /**
     * POST /api/accounting/journals
     */
    public function storeJournal(): never
    {
        $input = $this->getRequestBody();

        if (empty($input['lines']) || !is_array($input['lines'])) {
            $this->error('Journal voucher lines are required.', 422);
        }

        try {
            $entry = $this->accounting->createJournalEntry($input);
            $this->success($entry, 'Journal entry posted successfully.', 201);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/accounting/journals/:id/reverse
     */
    public function reverseJournal(string $id): never
    {
        $input = $this->getRequestBody();
        $reason = $input['reason'] ?? 'User requested reversal';

        try {
            $reversal = $this->accounting->reverseJournalEntry($id, $reason);
            $this->success($reversal, 'Journal entry successfully reversed.');
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 400);
        }
    }
}
