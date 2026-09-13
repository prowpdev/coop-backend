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
        $branchId = $this->getQuery('branchId');
        $accounts = $this->cash->all($branchId);
        $this->success($accounts);
    }

    public function show(string $id): never
    {
        $acc = $this->cash->find($id);
        if (!$acc) {
            $this->error('Cash account not found.', 404);
        }
        $this->success($acc);
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
