<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\MemberRepository;
use App\Models\LoanRepository;
use App\Models\SavingsRepository;
use App\Models\ShareCapitalRepository;
use App\Models\ConfigRepository;
use PDO;

class MemberPortalController extends BaseController
{
    private MemberRepository $members;
    private LoanRepository $loans;
    private SavingsRepository $savings;
    private ShareCapitalRepository $shareCapital;
    private ConfigRepository $config;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->members = new MemberRepository($db);
        $this->loans = new LoanRepository($db);
        $this->savings = new SavingsRepository($db);
        $this->shareCapital = new ShareCapitalRepository($db);
        $this->config = new ConfigRepository($db);
    }

    /**
     * GET /api/member-portal/:memberId/dashboard
     * Comprehensive single-member financial view
     */
    public function dashboard(string $memberId): never
    {
        $member = $this->members->find($memberId);

        if (!$member) {
            $this->error('Member not found.', 404);
        }

        $id = $member['id'];

        // 1. Share Capital (CBU) Accounts and transactions
        $shareAccounts = $this->shareCapital->all($id);
        if (empty($shareAccounts)) {
            // Auto-provision initial CBU account if none exists
            $shareAccounts[] = $this->shareCapital->createAccount([
                'member_id'         => $id,
                'branch_id'         => $member['branch_id'] ?? 'branch_tar',
                'subscribed_shares' => 100,
                'subscribed_amount' => 10000.00,
                'paid_up_shares'    => 25,
                'paid_up_amount'    => 2500.00,
                'par_value'         => 100.00
            ]);
        }

        $shareTransactions = [];
        $totalShareCapital = 0;
        foreach ($shareAccounts as &$sca) {
            $totalShareCapital += (float)($sca['paid_up_amount'] ?? 0);
            $scaTxns = $this->shareCapital->getTransactions($sca['id']);
            $sca['transactions'] = $scaTxns;
            foreach ($scaTxns as $stx) {
                $shareTransactions[] = [
                    'id'               => $stx['id'],
                    'date'             => $stx['transaction_date'] ?? $stx['created_at'],
                    'type'             => 'Share Capital: ' . ($stx['type'] ?? 'Payment'),
                    'amount'           => (float)($stx['amount'] ?? 0),
                    'reference'        => $stx['receipt_no'] ?? $stx['id'],
                    'account'          => $sca['account_number'] ?? 'CBU Account',
                    'notes'            => $stx['notes'] ?? 'Share capital contribution',
                    'transaction_type' => 'cbu'
                ];
            }
        }

        // 2. Savings Accounts and transactions
        $savingsAccounts = $this->savings->all(null, $id);
        if (empty($savingsAccounts)) {
            // Auto-provision regular savings account if none exists
            $savingsAccounts[] = $this->savings->createAccount([
                'member_id'          => $id,
                'savings_product_id' => 'sp_regular',
                'branch_id'          => $member['branch_id'] ?? 'branch_tar',
                'initial_deposit'    => 1000.00,
                'cash_account_id'    => 'cash_01',
                'notes'              => 'Opening Regular Savings Deposit'
            ]);
        }

        $savingsTransactions = [];
        $totalSavings = 0;
        foreach ($savingsAccounts as &$sa) {
            $totalSavings += (float)($sa['balance'] ?? 0);
            $saTxns = $this->savings->getTransactions($sa['id']);
            $sa['transactions'] = $saTxns;
            foreach ($saTxns as $sat) {
                $savingsTransactions[] = [
                    'id'               => $sat['id'],
                    'date'             => $sat['transaction_date'] ?? $sat['created_at'],
                    'type'             => 'Savings ' . ($sat['type'] ?? 'Transaction'),
                    'amount'           => (float)($sat['amount'] ?? 0),
                    'reference'        => $sat['transaction_no'] ?? $sat['id'],
                    'account'          => $sa['account_number'] ?? 'Savings Account',
                    'notes'            => $sat['notes'] ?? 'Deposit/Withdrawal',
                    'transaction_type' => 'savings'
                ];
            }
        }

        // 3. Member Loans and schedules
        $memberLoans = $this->loans->all(null, null, $id);
        $totalLoanOutstanding = 0;
        $loanTransactions = [];

        foreach ($memberLoans as &$loan) {
            $sched = $this->loans->getSchedule($loan['id']);
            $loan['schedule'] = $sched;

            $balance = (float)($loan['current_balance'] ?? $loan['principal_amount'] ?? 0);
            if (($loan['status'] ?? '') === 'Active' || ($loan['status'] ?? '') === 'Disbursed') {
                $totalLoanOutstanding += $balance;
            }

            // Get payment receipts for this loan
            $stmt = $this->db->prepare("SELECT * FROM loan_payments WHERE loan_id = ? ORDER BY payment_date DESC");
            $stmt->execute([$loan['id']]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $loan['payments'] = $payments;

            foreach ($payments as $pay) {
                $loanTransactions[] = [
                    'id'               => $pay['id'],
                    'date'             => $pay['payment_date'] ?? $pay['created_at'],
                    'type'             => 'Loan Repayment',
                    'amount'           => (float)($pay['amount_paid'] ?? 0),
                    'reference'        => $pay['receipt_no'] ?? $pay['id'],
                    'account'          => $loan['loan_no'] ?? $loan['id'],
                    'notes'            => 'Principal: ₱' . number_format((float)($pay['principal_paid'] ?? 0), 2) . ' | Interest: ₱' . number_format((float)($pay['interest_paid'] ?? 0), 2),
                    'transaction_type' => 'loan_repayment'
                ];
            }
        }

        // 4. Combine all transactions into single chronological ledger
        $allTransactions = array_merge($shareTransactions, $savingsTransactions, $loanTransactions);
        usort($allTransactions, function ($a, $b) {
            return strcmp($b['date'] ?? '', $a['date'] ?? '');
        });

        // 5. Build Stats & response
        $stats = [
            'total_share_capital'    => round($totalShareCapital, 2),
            'total_savings_balance'  => round($totalSavings, 2),
            'total_loan_outstanding' => round($totalLoanOutstanding, 2),
            'active_loans_count'     => count(array_filter($memberLoans, fn($l) => in_array($l['status'] ?? '', ['Active', 'Disbursed']))),
            'total_transactions'     => count($allTransactions)
        ];

        $this->json([
            'success' => true,
            'data'    => [
                'member'                  => $member,
                'share_capital_accounts'  => $shareAccounts,
                'savings_accounts'        => $savingsAccounts,
                'loans'                   => $memberLoans,
                'transactions'            => $allTransactions,
                'stats'                   => $stats
            ]
        ]);
    }

    /**
     * POST /api/member-portal/:memberId/apply-loan
     * Self-service loan application from member portal
     */
    public function applyLoan(string $memberId): never
    {
        $member = $this->members->find($memberId);
        if (!$member) {
            $this->error('Member not found.', 404);
        }

        $input = $this->getRequestBody();
        $principal = (float)($input['principal_amount'] ?? $input['principal'] ?? 0);
        $productId = $input['loan_product_id'] ?? 'lp_provident';
        $termMonths = (int)($input['term_months'] ?? 12);
        $disbursementDate = $input['disbursement_date'] ?? date('Y-m-d');

        if ($principal <= 0) {
            $this->error('Principal amount must be greater than zero.', 422);
        }

        // Get product details
        $product = $this->config->getLoanProduct($productId);
        $annualRate = (float)($product['interest_rate'] ?? 6.0);
        $calcMethod = $product['interest_calculation_method'] ?? 'Diminishing Balance';

        // Fallback cash account for disbursement
        $cashAcc = $this->db->query("SELECT id FROM cash_accounts WHERE active = 1 LIMIT 1")->fetchColumn() ?: 'cash_01';

        $loanData = [
            'member_id'                     => $member['id'],
            'branch_id'                     => $member['branch_id'] ?? 'branch_tar',
            'loan_product_id'               => $productId,
            'principal_amount'              => $principal,
            'annual_interest_rate'          => $annualRate,
            'term_months'                   => $termMonths,
            'interest_calculation_method'   => $calcMethod,
            'disbursement_date'             => $disbursementDate,
            'payment_frequency'             => 'Monthly',
            'disbursed_from_cash_account_id'=> $cashAcc,
            'notes'                         => $input['notes'] ?? 'Self-service online loan application',
            'status'                        => 'Active'
        ];

        try {
            $loan = $this->loans->createLoan($loanData);
            $this->json([
                'success' => true,
                'message' => 'Loan application approved and disbursed successfully!',
                'data'    => $loan
            ], 201);
        } catch (\Exception $e) {
            $this->error('Loan application failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/member-portal/:memberId/deposit
     * Make a deposit to member's savings account
     */
    public function depositSavings(string $memberId): never
    {
        $member = $this->members->find($memberId);
        if (!$member) {
            $this->error('Member not found.', 404);
        }

        $input = $this->getRequestBody();
        $amount = (float)($input['amount'] ?? 0);
        $accountId = $input['savings_account_id'] ?? null;

        if ($amount <= 0) {
            $this->error('Deposit amount must be greater than zero.', 422);
        }

        if (!$accountId) {
            // Find first active savings account for member
            $accounts = $this->savings->all(null, $member['id']);
            if (empty($accounts)) {
                $acc = $this->savings->createAccount([
                    'member_id'          => $member['id'],
                    'savings_product_id' => 'sp_regular',
                    'branch_id'          => $member['branch_id'] ?? 'branch_tar'
                ]);
                $accountId = $acc['id'];
            } else {
                $accountId = $accounts[0]['id'];
            }
        }

        try {
            $result = $this->savings->recordTransaction([
                'savings_account_id' => $accountId,
                'amount'             => $amount,
                'transaction_type'   => 'Deposit',
                'cash_account_id'    => $input['cash_account_id'] ?? 'cash_01',
                'notes'              => $input['notes'] ?? 'Member Portal Online Deposit',
                'transaction_date'   => date('Y-m-d')
            ]);

            $this->success($result, 'Deposit processed successfully.');
        } catch (\Exception $e) {
            $this->error('Deposit processing failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/member-portal/:memberId/pay-share-capital
     * Make a share capital contribution
     */
    public function payShareCapital(string $memberId): never
    {
        $member = $this->members->find($memberId);
        if (!$member) {
            $this->error('Member not found.', 404);
        }

        $input = $this->getRequestBody();
        $amount = (float)($input['amount'] ?? 0);

        if ($amount <= 0) {
            $this->error('Contribution amount must be greater than zero.', 422);
        }

        $shareAccounts = $this->shareCapital->all($member['id']);
        if (empty($shareAccounts)) {
            $sca = $this->shareCapital->createAccount([
                'member_id' => $member['id'],
                'branch_id' => $member['branch_id'] ?? 'branch_tar'
            ]);
            $accountId = $sca['id'];
        } else {
            $accountId = $shareAccounts[0]['id'];
        }

        try {
            $result = $this->shareCapital->recordPayment([
                'account_id'       => $accountId,
                'amount'           => $amount,
                'transaction_type' => 'Capital Build-Up Contribution',
                'cash_account_id'  => $input['cash_account_id'] ?? 'cash_01',
                'notes'            => $input['notes'] ?? 'Online Share Capital payment',
                'transaction_date' => date('Y-m-d')
            ]);

            $this->success($result, 'Share capital contribution recorded successfully.');
        } catch (\Exception $e) {
            $this->error('Payment failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/member-portal/:memberId/loan-payment
     * Make a repayment for a loan
     */
    public function makeLoanPayment(string $memberId): never
    {
        $member = $this->members->find($memberId);
        if (!$member) {
            $this->error('Member not found.', 404);
        }

        $input = $this->getRequestBody();
        $loanId = $input['loan_id'] ?? null;
        $amount = (float)($input['amount_paid'] ?? $input['amount'] ?? 0);

        if (!$loanId || $amount <= 0) {
            $this->error('Loan ID and a positive payment amount are required.', 422);
        }

        try {
            $receipt = $this->loans->recordPayment([
                'loan_id'         => $loanId,
                'amount_paid'     => $amount,
                'payment_date'    => date('Y-m-d'),
                'cash_account_id' => $input['cash_account_id'] ?? 'cash_01',
                'notes'           => $input['notes'] ?? 'Repayment via Member Portal'
            ]);

            $this->success($receipt, 'Loan payment allocated successfully.');
        } catch (\Exception $e) {
            $this->error('Loan repayment failed: ' . $e->getMessage(), 500);
        }
    }
}
