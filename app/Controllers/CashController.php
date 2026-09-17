<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CashRepository;
use PDO;

class CashController extends BaseController
{
    private CashRepository $cash;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->cash = new CashRepository($db);
    }

    public function index(): never
    {
        $branchId = $this->getQuery('branchId') ?? $this->getQuery('branch_id');
        $accounts = $this->cash->all($branchId);

        $totalVaults = 0;
        $totalDrawers = 0;
        $totalBanks = 0;
        $totalLiquidity = 0;

        foreach ($accounts as $a) {
            $bal = (float)($a['current_balance'] ?? 0);
            $totalLiquidity += $bal;
            $num = $a['account_number'] ?? '';
            $name = strtolower($a['name'] ?? '');
            $gl = $a['gl_account_id'] ?? '';

            if (str_starts_with($num, 'VLT-') || str_contains($name, 'vault')) {
                $totalVaults += $bal;
            } elseif (str_starts_with($num, 'COH-') || str_contains($name, 'teller')) {
                $totalDrawers += $bal;
            } elseif ($gl === 'acc_1120' || $gl === 'acc_1121' || str_contains($name, 'bank')) {
                $totalBanks += $bal;
            }
        }

        $this->json([
            'success' => true,
            'data'    => $accounts,
            'stats'   => [
                'total_vaults'    => $totalVaults,
                'total_drawers'   => $totalDrawers,
                'total_banks'     => $totalBanks,
                'total_liquidity' => $totalLiquidity,
                'total_accounts'  => count($accounts)
            ]
        ]);
    }

    public function show(string $id): never
    {
        $acc = $this->cash->find($id);
        if (!$acc) {
            $this->error('Cash account not found.', 404);
        }
        $this->success($acc);
    }

    public function store(): never
    {
        $input = $this->getRequestBody();
        if (empty($input['name']) || empty($input['account_number'])) {
            $this->error('Account Name and Account Number are required.', 422);
        }

        try {
            $saved = $this->cash->save($input);
            $this->success($saved, 'Cash account created successfully.');
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    public function update(string $id): never
    {
        $input = $this->getRequestBody();
        $input['id'] = $id;

        try {
            $saved = $this->cash->save($input);
            $this->success($saved, 'Cash account updated successfully.');
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    public function destroy(string $id): never
    {
        try {
            $this->cash->delete($id);
            $this->success(null, 'Cash account removed successfully.');
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    public function replenish(): never
    {
        $input = $this->getRequestBody();
        $accountId = $input['account_id'] ?? $input['to_account_id'] ?? null;
        $amount = (float)($input['amount'] ?? 0);
        $sourceId = $input['source_account_id'] ?? $input['from_account_id'] ?? null;
        $notes = $input['notes'] ?? 'Cash replenishment';
        $date = $input['transaction_date'] ?? date('Y-m-d');

        if (!$accountId || $amount <= 0) {
            $this->error('Target Account and valid positive Amount are required.', 422);
        }

        try {
            $res = $this->cash->replenish($accountId, $amount, $sourceId, $notes, $date);
            $this->success($res, 'Cash account replenished successfully.');
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 400);
        }
    }

    public function transfer(): never
    {
        $input = $this->getRequestBody();

        if (empty($input['from_account_id']) || empty($input['to_account_id']) || empty($input['amount'])) {
            $this->error('From Account, To Account, and Amount are required.', 422);
        }

        try {
            $res = $this->cash->transfer(
                $input['from_account_id'],
                $input['to_account_id'],
                (float)$input['amount'],
                $input['transaction_date'] ?? date('Y-m-d'),
                $input['notes'] ?? 'Cash drawer transfer'
            );
            $this->success($res, 'Cash transfer executed successfully.');
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 400);
        }
    }
}
