<?php

declare(strict_types=1);

namespace App\Seeders;

use PDO;
use Throwable;
use RuntimeException;

class DatabaseResetter
{
    public function __construct(
        private PDO $db
    ) {}

    /**
     * Reset development data and related records.
     *
     * WARNING:
     * Permanently deletes data.
     * USE ONLY IN DEVELOPMENT.
     */
    public function reset(): array
    {
        /*
         * Delete child tables first, then parent tables.
         *
         * These tables should be verified against your
         * actual foreign-key schema.
         */
        $tables = [
          'savings_transactions',
          'savings_accounts',
          'journal_entries',
          'share_capital_transactions',
          'share_capital_accounts',
          'share_capital_settings',
          'cash_transactions',
          'loans',
          'loan_applications',
          ///////////////////////////////////////
          'chart_of_accounts',
          'accounting_mappings',
          'accounting_periods',
          'cash_accounts',
          'custom_fields',
          'penalty_rules',
          'payment_frequencies',
          'numbering_formats',
          'savings_products',
          'members',
          'loan_products',
          'payment_allocation_rules',
          'fees',
          'approval_workflows',
          'approval_rules',
          'member_types',
          'document_requirements',
          'feature_toggles',
          'branches',
          'configuration_audit_trails',
          'cooperatives',
          'system_settings',
          'transaction_types',
        //   'user_roles',
        //   'users',


        ];

        $deleted = [];
        $foreignKeysDisabled = false;

        try {
            $this->db->beginTransaction();

            // Disable foreign key checks for this connection.
            $this->db->exec(
                'SET FOREIGN_KEY_CHECKS = 0'
            );

            $foreignKeysDisabled = true;

            foreach ($tables as $table) {

                // All table names are hardcoded.
                $stmt = $this->db->prepare(
                    "DELETE FROM `$table`"
                );

                $stmt->execute();

                $deleted[$table] = $stmt->rowCount();
            }

            // Re-enable foreign key checks.
            $this->db->exec(
                'SET FOREIGN_KEY_CHECKS = 1'
            );

            $foreignKeysDisabled = false;

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Database reset successfully.',
                'tables_reset' => count($tables),
                'deleted' => $deleted,
            ];

        } catch (Throwable $e) {

            // Restore the session setting.
            if ($foreignKeysDisabled) {
                $this->db->exec(
                    'SET FOREIGN_KEY_CHECKS = 1'
                );
            }

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw new RuntimeException(
                'Database reset failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}