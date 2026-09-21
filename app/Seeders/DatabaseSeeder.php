<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;
use App\Seeders\ChartOfAccountSeeder;
use App\Seeders\AccountingMappingSeeder;
use App\Seeders\AccountingPeriodSeeder;
use App\Seeders\CashAccountSeeder;
use App\Seeders\CustomFieldSeeder;
use App\Seeders\PenaltyRuleSeeder;
use App\Seeders\PaymentFrequencySeeder;
use App\Seeders\NumberingFormatSeeder;
use App\Seeders\SavingsProductSeeder;
use App\Seeders\MemberSeeder;
use App\Seeders\LoanProductSeeder;
use App\Seeders\PaymentAllocationRuleSeeder;
use App\Seeders\FeeSeeder;
use App\Seeders\ApprovalWorkflowSeeder;
use App\Seeders\ApprovalRuleSeeder;
use App\Seeders\DatabaseResetter;
use App\Seeders\MemberTypeSeeder;
use App\Seeders\DocumentRequirementSeeder;
use App\Seeders\FeatureToggleSeeder;
use App\Seeders\BranchSeeder;
use App\Seeders\ConfigurationAuditTrailSeeder;
use App\Seeders\CooperativeSeeder;
use App\Seeders\SystemSettingSeeder;
use App\Seeders\TransactionTypeSeeder;
use App\Seeders\UserRoleSeeder;
use App\Seeders\UserSeeder;
use App\Seeders\ShareCapitalSettingSeeder;




class DatabaseSeeder
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): array
    {
        $results = [];
        // Base/reference data first
        $results['branches'] =(new BranchSeeder($this->db))->run();

        $results['chart_of_accounts'] = (new ChartOfAccountSeeder($this->db))->run();
    
        $results['accounting_mappings'] = (new AccountingMappingSeeder($this->db))->run();

        $results['accounting_periods'] =(new AccountingPeriodSeeder($this->db))->run();

        $results['cash_accounts'] =(new CashAccountSeeder($this->db))->run();

        $results['custom_fields'] =(new CustomFieldSeeder($this->db))->run();

        $results['penalty_rules'] =(new PenaltyRuleSeeder($this->db))->run();

        $results['payment_frequencies'] =(new PaymentFrequencySeeder($this->db))->run();

        $results['numbering_formats'] =(new NumberingFormatSeeder($this->db))->run();

        $results['savings_products'] =(new SavingsProductSeeder($this->db))->run();
        
        $results['member_types'] =(new MemberTypeSeeder($this->db))->run();
      
        $results['members'] =(new MemberSeeder($this->db))->run();

        $results['loan_products'] =(new LoanProductSeeder($this->db))->run();

        $results['payment_allocation_rules'] =(new PaymentAllocationRuleSeeder($this->db))->run();

        $results['fees'] =(new FeeSeeder($this->db))->run();

        $results['approval_workflows'] =(new ApprovalWorkflowSeeder($this->db))->run();
        
        $results['approval_rules'] =(new ApprovalRuleSeeder($this->db))->run();

        $results['document_requirements'] =(new DocumentRequirementSeeder($this->db))->run();

        $results['feature_toggles'] =(new FeatureToggleSeeder($this->db))->run();

        $results['configuration_audit_trails'] =(new ConfigurationAuditTrailSeeder($this->db))->run();
        
        $results['cooperatives'] =(new CooperativeSeeder($this->db))->run();
        
        $results['system_settings'] =(new SystemSettingSeeder($this->db))->run();
        
        $results['transaction_types '] =(new TransactionTypeSeeder($this->db))->run();
        
        $results['user_roles '] =(new UserRoleSeeder($this->db))->run();
        
        $results['users '] =(new UserSeeder($this->db))->run();
        
        $results['share_capital_settings'] =(new ShareCapitalSettingSeeder($this->db))->run();

        return $results;
    }
    /**
    * Reset the development database
    */
    public function resetDb(): array
    {
        $resetter = new DatabaseResetter($this->db);
        $resetResult = $resetter->reset();

        return [
            'reset' => $resetResult,
        ];
    }
}