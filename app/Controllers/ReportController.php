<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ReportRepository;
use PDO;

class ReportController extends BaseController
{
    private ReportRepository $reports;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->reports = new ReportRepository($db);
    }

    /**
     * GET /api/reports/trial-balance
     */
    public function trialBalance(): never
    {
        $asOfDate = $this->getQuery('asOfDate');
        $branchId = $this->getQuery('branchId');

        $tb = $this->reports->getTrialBalance($asOfDate, $branchId);
        $this->success($tb);
    }

    /**
     * GET /api/reports/financial-statements
     */
    public function financialStatements(): never
    {
        $asOfDate = $this->getQuery('asOfDate');
        $branchId = $this->getQuery('branchId');

        $fs = $this->reports->getFinancialStatements($asOfDate, $branchId);
        $this->success($fs);
    }

    /**
     * GET /api/dashboard/stats
     */
    public function dashboardStats(): never
    {
        $stats = $this->reports->getDashboardStats();
        $this->success($stats);
    }
}
