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
            $this->success($entry, 'Journal entry posted successfully.', 200);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/accounting/manual-journal
     */
    public function manualJournal(): never
    {
        $this->storeJournal();
    }

    /**
     * PUT /api/config/chart-of-accounts/:id
     */
    public function updateAccount(string $id): never
    {
        $input = $this->getRequestBody();
        $input['id'] = $id;

        try {
            $account = $this->accounting->saveAccount($input);
            $this->success($account, 'Chart of accounts record updated successfully.');
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/config/chart-of-accounts/:id
     */
    public function deleteAccount(string $id): never
    {
        try {
            $this->accounting->deleteAccount($id);
            $this->success(null, 'Account deleted from chart of accounts.');
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/config/accounting-mappings
     */
    public function mappings(): never
    {
        $mappings = $this->accounting->getAccountingMappings();
        $this->success($mappings);
    }

    /**
     * POST /api/config/accounting-mappings
     */
    public function storeMapping(): never
    {
        $input = $this->getRequestBody();
        if (empty($input['name']) || empty($input['transaction_type']) || empty($input['debit_account_id']) || empty($input['credit_account_id'])) {
            $this->error('Name, transaction type, debit and credit accounts are required.', 422);
        }

        $created = $this->accounting->saveAccountingMapping($input);
        $this->success($created, 'Accounting mapping created successfully.', 201);
    }

    /**
     * PUT /api/config/accounting-mappings/:id
     */
    public function updateMapping(string $id): never
    {
        $input = $this->getRequestBody();
        $updated = $this->accounting->updateAccountingMapping($id, $input);
        $this->success($updated, 'Accounting mapping updated successfully.');
    }

    /**
     * DELETE /api/config/accounting-mappings/:id
     */
    public function deleteMapping(string $id): never
    {
        $res = $this->accounting->deleteAccountingMapping($id);
        if ($res) {
            $this->success(['id' => $id], 'Accounting mapping deleted successfully.');
        } else {
            $this->error('Accounting mapping not found or could not be deleted.', 404);
        }
    }

    /**
     * POST /api/config/accounting-mappings/reset
     */
    public function resetMappings(): never
    {
        $defaults = $this->accounting->resetAccountingMappings();
        $this->success($defaults, 'Accounting mappings reset to CDA standard defaults.');
    }

    /**
     * POST /api/config/accounting-periods/close
     */
    public function closePeriod(): never
    {
        $input = $this->getRequestBody();
        $periodId = $input['period_id'] ?? $input['id'] ?? '';
        $closedBy = $input['closed_by'] ?? 'System Administrator';

        if (!$periodId) {
            $this->error('Accounting period ID is required.', 422);
        }

        $res = $this->accounting->closePeriod($periodId, $closedBy);
        if ($res) {
            $this->success(['period_id' => $periodId], 'Accounting period closed successfully.');
        } else {
            $this->error('Failed to close accounting period.', 400);
        }
    }

    /**
     * POST /api/config/accounting-periods/reopen
     */
    public function reopenPeriod(): never
    {
        $input = $this->getRequestBody();
        $periodId = $input['period_id'] ?? $input['id'] ?? '';

        if (!$periodId) {
            $this->error('Accounting period ID is required.', 422);
        }

        $res = $this->accounting->reopenPeriod($periodId);
        if ($res) {
            $this->success(['period_id' => $periodId], 'Accounting period reopened successfully.');
        } else {
            $this->error('Failed to reopen accounting period.', 400);
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
