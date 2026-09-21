<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class ChartOfAccountSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $accounts = [
            ['acc_1110', '1110', 'Cash on Hand - Tellers', 'Asset', 'Debit', 1, null, 'Current Assets', 'Petty cash and daily cashier vault drawers'],
            ['acc_1120', '1120', 'Cash in Bank - Land Bank of the Philippines', 'Asset', 'Debit', 1, null, 'Current Assets', 'LBP primary operating clearing depository'],
            ['acc_1121', '1121', 'Cash in Bank - Development Bank of the Philippines', 'Asset', 'Debit', 1, null, 'Current Assets', 'DBP high-yield special reserve depository'],

            ['acc_1210', '1210', 'Loans Receivable - Regular Multi-Purpose', 'Asset', 'Debit', 1, null, 'Loans and Receivables', 'Principal balance of outstanding member multi-purpose loans'],
            ['acc_1220', '1220', 'Loans Receivable - Emergency Micro-Loans', 'Asset', 'Debit', 1, null, 'Loans and Receivables', 'Emergency calamity and express medical credit lines'],
            ['acc_1230', '1230', 'Loans Receivable - Agricultural Crop Financing', 'Asset', 'Debit', 1, null, 'Loans and Receivables', 'Seasonal crop inputs, fertilizer, and agricultural financing'],
            ['acc_1290', '1290', 'Allowance for Probable Loan Losses', 'Asset', 'Credit', 1, null, 'Contra-Asset', 'Provision for PAR and non-performing loan impairments'],
            ['acc_1310', '1310', 'Interest Receivable on Loans', 'Asset', 'Debit', 1, null, 'Receivables', 'Accrued but uncollected loan installment interest'],

            ['acc_1510', '1510', 'Office & IT Equipment', 'Asset', 'Debit', 1, null, 'Property, Plant & Equipment', 'Servers, teller terminals, and office workstations'],
            ['acc_1590', '1590', 'Accumulated Depreciation - Office Equipment', 'Asset', 'Credit', 1, null, 'Contra-Asset', 'Depreciation reserve on operational equipment'],

            ['acc_2110', '2110', 'Savings Deposits - Regular', 'Liability', 'Credit', 1, null, 'Deposit Liabilities', 'Withdrawable member deposit savings balances'],
            ['acc_2120', '2120', 'Time Deposits - High Yield', 'Liability', 'Credit', 1, null, 'Deposit Liabilities', 'Fixed-term high-yield member placements'],
            ['acc_2210', '2210', 'Accounts Payable & Accrued Expenses', 'Liability', 'Credit', 1, null, 'Current Liabilities', 'Supplier payables and operational vendor balances'],
            ['acc_2220', '2220', 'Interest Payable on Deposits', 'Liability', 'Credit', 1, null, 'Current Liabilities', 'Accrued interest payable to member savings deposits'],

            ['acc_3110', '3110', 'Paid-Up Share Capital - Common (Voting)', 'Equity', 'Credit', 1, null, 'Share Capital', 'Member common share capital subscribed and paid'],
            ['acc_3120', '3120', 'Paid-Up Share Capital - Preferred (Non-Voting)', 'Equity', 'Credit', 1, null, 'Share Capital', 'Associate member preferred non-voting equity'],
            ['acc_3210', '3210', 'Statutory Reserve Fund (General)', 'Equity', 'Credit', 1, null, 'Statutory Reserves', 'Mandatory 10% statutory reserve mandated by CDA'],
            ['acc_3220', '3220', 'Coop Education & Training Fund (CETF)', 'Equity', 'Credit', 1, null, 'Statutory Reserves', 'Mandatory educational reserve (5% localized, 5% apex federation)'],
            ['acc_3230', '3230', 'Community Development Fund', 'Equity', 'Credit', 1, null, 'Statutory Reserves', 'Mandatory 3% social community outreach fund'],
            ['acc_3240', '3240', 'Optional Reserve Fund', 'Equity', 'Credit', 1, null, 'Statutory Reserves', 'Discretionary cooperative stability and building fund'],
            ['acc_3900', '3900', 'Undivided Net Surplus / Retained Earnings', 'Equity', 'Credit', 1, null, 'Equity Surplus', 'Cumulative operating surplus available for dividend allocation'],

            ['acc_4110', '4110', 'Interest Income from Loans', 'Revenue', 'Credit', 1, null, 'Operating Revenue', 'Earned interest collected on member loan disbursements'],
            ['acc_4120', '4120', 'Service & Processing Fees', 'Revenue', 'Credit', 1, null, 'Operating Revenue', 'Loan origination, filing, and notarial service fees'],
            ['acc_4130', '4130', 'Fines & Late Payment Penalties', 'Revenue', 'Credit', 1, null, 'Operating Revenue', 'Default penalty charges assessed on delinquent installments'],
            ['acc_4140', '4140', 'Membership & Admission Fees', 'Revenue', 'Credit', 1, null, 'Operating Revenue', 'Non-refundable membership application and seminar fees'],

            ['acc_5110', '5110', 'Interest Expense on Savings Deposits', 'Expense', 'Debit', 1, null, 'Financial Expenses', 'Annual dividend and monthly interest yield distributed on deposits'],
            ['acc_5210', '5210', 'Salaries, Wages & Employee Benefits', 'Expense', 'Debit', 1, null, 'Administrative Expenses', 'Staff compensation, 13th month pay, and personnel allowances'],
            ['acc_5220', '5220', 'Office Supplies, Utilities & Communication', 'Expense', 'Debit', 1, null, 'Administrative Expenses', 'Electric, water, telecommunications, and stationery expenses'],
            ['acc_5290', '5290', 'Provision for Loan Losses', 'Expense', 'Debit', 1, null, 'Credit Losses', 'Expense entry provisioning reserve for doubtful loan accounts'],
        ];

        $sql = "
        INSERT INTO chart_of_accounts
        (
            id,
            account_code,
            name,
            category,
            normal_balance,
            is_active,
            parent_account_id,
            report_group,
            description
        )
        VALUES
        (
            :id,
            :account_code,
            :name,
            :category,
            :normal_balance,
            :is_active,
            :parent_account_id,
            :report_group,
            :description
        )
        ON DUPLICATE KEY UPDATE
            account_code = VALUES(account_code),
            name = VALUES(name),
            category = VALUES(category),
            normal_balance = VALUES(normal_balance),
            is_active = VALUES(is_active),
            parent_account_id = VALUES(parent_account_id),
            report_group = VALUES(report_group),
            description = VALUES(description)
    ";
        try {
            $stmt = $this->db->prepare($sql);

            foreach ($accounts as $account) {
                $stmt->execute([
                    ':id'                => $account[0],
                    ':account_code'      => $account[1],
                    ':name'              => $account[2],
                    ':category'          => $account[3],
                    ':normal_balance'    => $account[4],
                    ':is_active'         => $account[5],
                    ':parent_account_id' => $account[6],
                    ':report_group'      => $account[7],
                    ':description'       => $account[8],
                ]);
            }
            return $this->get_cof();
        } catch (\Throwable $th) {
            // Better during development:
            throw $th;
        }
    }
    function get_cof() :array
    {
        $sql = ("SELECT * FROM `chart_of_accounts`");
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
     
    }
}