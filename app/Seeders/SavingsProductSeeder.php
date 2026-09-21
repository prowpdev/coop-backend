<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class SavingsProductSeeder
{
    public function __construct(private PDO $db) {}

    public function run(): array
    {
        $products = [
            [
                'sp_regular',
                'SAV-REG',
                'Regular Savings Deposit',
                1000.00,
                2.00,
                'Average Daily Balance',
                500.00,
                500.00,
                'acc_2110',
                'acc_5110',
                1,
            ],
            [
                'sp_time',
                'SAV-TIME',
                'High-Yield Time Deposit (1 Year)',
                20000.00,
                5.50,
                'Simple Interest',
                20000.00,
                20000.00,
                'acc_2120',
                'acc_5110',
                1,
            ],
        ];

        $sql = "
            INSERT INTO savings_products (
                id,
                code,
                name,
                min_balance_to_earn_interest,
                annual_interest_rate,
                interest_calculation_method,
                min_opening_deposit,
                maintaining_balance,
                gl_liability_account_id,
                gl_interest_expense_account_id,
                active
            )
            VALUES (
                :id,
                :code,
                :name,
                :min_balance_to_earn_interest,
                :annual_interest_rate,
                :interest_calculation_method,
                :min_opening_deposit,
                :maintaining_balance,
                :gl_liability_account_id,
                :gl_interest_expense_account_id,
                :active
            )
            ON DUPLICATE KEY UPDATE
                code = VALUES(code),
                name = VALUES(name),
                min_balance_to_earn_interest = VALUES(min_balance_to_earn_interest),
                annual_interest_rate = VALUES(annual_interest_rate),
                interest_calculation_method = VALUES(interest_calculation_method),
                min_opening_deposit = VALUES(min_opening_deposit),
                maintaining_balance = VALUES(maintaining_balance),
                gl_liability_account_id = VALUES(gl_liability_account_id),
                gl_interest_expense_account_id = VALUES(gl_interest_expense_account_id),
                active = VALUES(active)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($products as $product) {
            $stmt->execute([
                ':id'                            => $product[0],
                ':code'                          => $product[1],
                ':name'                          => $product[2],
                ':min_balance_to_earn_interest'  => $product[3],
                ':annual_interest_rate'          => $product[4],
                ':interest_calculation_method'   => $product[5],
                ':min_opening_deposit'           => $product[6],
                ':maintaining_balance'           => $product[7],
                ':gl_liability_account_id'       => $product[8],
                ':gl_interest_expense_account_id' => $product[9],
                ':active'                        => $product[10],
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                sp.*,
                liability.account_code AS liability_account_code,
                liability.name AS liability_account_name,
                expense.account_code AS interest_expense_account_code,
                expense.name AS interest_expense_account_name
            FROM savings_products sp
            LEFT JOIN chart_of_accounts liability
                ON liability.id = sp.gl_liability_account_id
            LEFT JOIN chart_of_accounts expense
                ON expense.id = sp.gl_interest_expense_account_id
            ORDER BY sp.name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}