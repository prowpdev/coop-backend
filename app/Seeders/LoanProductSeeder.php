<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;

class LoanProductSeeder
{
    public function __construct(private PDO $db) {}

    public function run(): array
    {
        $products = [
            [
                'id' => 'lp_regular',
                'code' => 'LP-REG',
                'name' => 'Regular Multi-Purpose Loan',
                'description' => 'Standard term loan for regular members with 10% annual diminishing balance',
                'version' => 1,

                'min_amount' => 10000.00,
                'max_amount' => 300000.00,

                'min_term_months' => 6,
                'max_term_months' => 12,
                'default_term_months' => 12,

                'annual_interest_rate' => 10.00,
                'interest_calculation_method' => 'Diminishing Balance',
                'payment_frequency' => 'Monthly',

                'grace_period_days' => 5,

                'processing_fee_percentage' => 2.00,
                'service_fee_fixed' => 250.00,

                'penalty_rule_id' => 'pen_standard',
                'penalty_rate_percentage' => 0,

                'collateral_required' => false,
                'guarantor_required' => true,

                /*
                 * Loan accounting mappings
                 *
                 * 1210 = Loans Receivable - Current
                 * 4110 = Interest Income from Loans
                 */
                'gl_receivable_account_id' => 'acc_1210',
                'gl_interest_income_account_id' => 'acc_4110',

                /*
                 * Keep this only if debit_account_id is an existing
                 * field used by your loan workflow.
                 */
                'debit_account_id' => 'acc_1210',

                'required_documents' => [
                    'Valid Government ID',
                    'Payslip / Income Proof',
                    'Co-maker Agreement',
                ],

                'approval_workflow_id' => 'wf_loan_standard',

                'effective_from' => '2025-01-01',
                'effective_until' => null,

                'active' => true,
            ],
        ];

        $sql = "
            INSERT INTO `loan_products` (
                `id`,
                `code`,
                `name`,
                `description`,
                `version`,
                `min_amount`,
                `max_amount`,
                `min_term_months`,
                `max_term_months`,
                `annual_interest_rate`,
                `interest_calculation_method`,
                `default_term_months`,
                `payment_frequency`,
                `grace_period_days`,
                `processing_fee_percentage`,
                `service_fee_fixed`,
                `penalty_rule_id`,
                `penalty_rate_percentage`,
                `collateral_required`,
                `guarantor_required`,
                `debit_account_id`,
                `required_documents`,
                `approval_workflow_id`,
                `effective_from`,
                `effective_until`,
                `gl_receivable_account_id`,
                `gl_interest_income_account_id`,
                `active`
            )
            VALUES (
                :id,
                :code,
                :name,
                :description,
                :version,
                :min_amount,
                :max_amount,
                :min_term_months,
                :max_term_months,
                :annual_interest_rate,
                :interest_calculation_method,
                :default_term_months,
                :payment_frequency,
                :grace_period_days,
                :processing_fee_percentage,
                :service_fee_fixed,
                :penalty_rule_id,
                :penalty_rate_percentage,
                :collateral_required,
                :guarantor_required,
                :debit_account_id,
                :required_documents,
                :approval_workflow_id,
                :effective_from,
                :effective_until,
                :gl_receivable_account_id,
                :gl_interest_income_account_id,
                :active
            )
            ON DUPLICATE KEY UPDATE
                `code` = VALUES(`code`),
                `name` = VALUES(`name`),
                `description` = VALUES(`description`),
                `version` = VALUES(`version`),
                `min_amount` = VALUES(`min_amount`),
                `max_amount` = VALUES(`max_amount`),
                `min_term_months` = VALUES(`min_term_months`),
                `max_term_months` = VALUES(`max_term_months`),
                `annual_interest_rate` = VALUES(`annual_interest_rate`),
                `interest_calculation_method` = VALUES(`interest_calculation_method`),
                `default_term_months` = VALUES(`default_term_months`),
                `payment_frequency` = VALUES(`payment_frequency`),
                `grace_period_days` = VALUES(`grace_period_days`),
                `processing_fee_percentage` = VALUES(`processing_fee_percentage`),
                `service_fee_fixed` = VALUES(`service_fee_fixed`),
                `penalty_rule_id` = VALUES(`penalty_rule_id`),
                `penalty_rate_percentage` = VALUES(`penalty_rate_percentage`),
                `collateral_required` = VALUES(`collateral_required`),
                `guarantor_required` = VALUES(`guarantor_required`),
                `debit_account_id` = VALUES(`debit_account_id`),
                `required_documents` = VALUES(`required_documents`),
                `approval_workflow_id` = VALUES(`approval_workflow_id`),
                `effective_from` = VALUES(`effective_from`),
                `effective_until` = VALUES(`effective_until`),
                `gl_receivable_account_id` = VALUES(`gl_receivable_account_id`),
                `gl_interest_income_account_id` = VALUES(`gl_interest_income_account_id`),
                `active` = VALUES(`active`)
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($products as $product) {
            $stmt->execute([
                ':id' => $product['id'],
                ':code' => $product['code'],
                ':name' => $product['name'],
                ':description' => $product['description'],
                ':version' => $product['version'],

                ':min_amount' => $product['min_amount'],
                ':max_amount' => $product['max_amount'],

                ':min_term_months' => $product['min_term_months'],
                ':max_term_months' => $product['max_term_months'],

                ':annual_interest_rate' => $product['annual_interest_rate'],
                ':interest_calculation_method' => $product['interest_calculation_method'],
                ':default_term_months' => $product['default_term_months'],
                ':payment_frequency' => $product['payment_frequency'],

                ':grace_period_days' => $product['grace_period_days'],

                ':processing_fee_percentage' => $product['processing_fee_percentage'],
                ':service_fee_fixed' => $product['service_fee_fixed'],

                ':penalty_rule_id' => $product['penalty_rule_id'],
                ':penalty_rate_percentage' => $product['penalty_rate_percentage'],

                ':collateral_required' => $product['collateral_required'] ? 1 : 0,
                ':guarantor_required' => $product['guarantor_required'] ? 1 : 0,

                ':debit_account_id' => $product['debit_account_id'],

                ':required_documents' => json_encode(
                    $product['required_documents'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),

                ':approval_workflow_id' => $product['approval_workflow_id'],

                ':effective_from' => $product['effective_from'],
                ':effective_until' => $product['effective_until'],

                ':gl_receivable_account_id' => $product['gl_receivable_account_id'],
                ':gl_interest_income_account_id' => $product['gl_interest_income_account_id'],

                ':active' => $product['active'] ? 1 : 0,
            ]);
        }

        return $this->getAll();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                `id`,
                `code`,
                `name`,
                `description`,
                `version`,
                `min_amount`,
                `max_amount`,
                `min_term_months`,
                `max_term_months`,
                `annual_interest_rate`,
                `interest_calculation_method`,
                `default_term_months`,
                `payment_frequency`,
                `grace_period_days`,
                `processing_fee_percentage`,
                `service_fee_fixed`,
                `penalty_rule_id`,
                `penalty_rate_percentage`,
                `collateral_required`,
                `guarantor_required`,
                `debit_account_id`,
                `required_documents`,
                `approval_workflow_id`,
                `effective_from`,
                `effective_until`,
                `gl_receivable_account_id`,
                `gl_interest_income_account_id`,
                `active`
            FROM `loan_products`
            ORDER BY `name` ASC
        ");

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as &$product) {
            $product['version'] = (int) $product['version'];

            $product['min_amount'] = (float) $product['min_amount'];
            $product['max_amount'] = (float) $product['max_amount'];

            $product['min_term_months'] = (int) $product['min_term_months'];
            $product['max_term_months'] = (int) $product['max_term_months'];
            $product['default_term_months'] = (int) $product['default_term_months'];

            $product['annual_interest_rate'] = (float) $product['annual_interest_rate'];

            $product['grace_period_days'] = (int) $product['grace_period_days'];

            $product['processing_fee_percentage'] = (float) $product['processing_fee_percentage'];
            $product['service_fee_fixed'] = (float) $product['service_fee_fixed'];

            $product['penalty_rate_percentage'] = (float) $product['penalty_rate_percentage'];

            $product['collateral_required'] = (bool) $product['collateral_required'];
            $product['guarantor_required'] = (bool) $product['guarantor_required'];
            $product['active'] = (bool) $product['active'];

            $product['required_documents'] = json_decode(
                $product['required_documents'] ?? '[]',
                true
            ) ?? [];
        }

        unset($product);

        return $products;
    }
}