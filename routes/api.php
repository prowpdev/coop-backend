<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\MemberPortalController;
use App\Controllers\MemberController;
use App\Controllers\LoanController;
use App\Controllers\SavingsController;
use App\Controllers\ShareCapitalController;
use App\Controllers\AccountingController;
use App\Controllers\ConfigController;
use App\Controllers\CashController;
use App\Controllers\ReportController;
use App\Controllers\UserController;
use App\Controllers\SystemController;
use App\Controllers\SeederController;

/** @var Router $router */

// =========================================================================
// 1. System Health, Diagnostics & Home
// =========================================================================
$router->get('/', [HomeController::class, 'index']);
$router->get('/api', [HomeController::class, 'index']);
$router->get('/test', [HomeController::class, 'test']);
$router->get('/api/test', [HomeController::class, 'test']);

// =========================================================================
// 2. Authentication & Session Management (Staff & Member Portals)
// =========================================================================
// Staff / Admin Authentication
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/register', [AuthController::class, 'register']);

// Member Portal Authentication
$router->post('/api/auth/member-login', [AuthController::class, 'memberLogin']);
$router->post('/api/auth/member-register', [AuthController::class, 'memberRegister']);

// Common Auth Utilities
$router->get('/api/auth/me', [AuthController::class, 'me']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->post('/api/auth/change-password', [AuthController::class, 'changePassword']);

// Staff User Accounts & Roles
$router->get('/api/users', [UserController::class, 'index']);
$router->get('/api/user-roles', [UserController::class, 'roles']);
$router->get('/api/users/:id', [UserController::class, 'show']);
$router->delete('/api/users/:id', [UserController::class, 'destroy']);

// =========================================================================
// 3. Member Self-Service Portal Endpoints
// =========================================================================
$router->get('/api/member-portal/:memberId/dashboard', [MemberPortalController::class, 'dashboard']);
$router->post('/api/member-portal/:memberId/apply-loan', [MemberPortalController::class, 'applyLoan']);
$router->post('/api/member-portal/:memberId/deposit', [MemberPortalController::class, 'depositSavings']);
$router->post('/api/member-portal/:memberId/pay-share-capital', [MemberPortalController::class, 'payShareCapital']);
$router->post('/api/member-portal/:memberId/loan-payment', [MemberPortalController::class, 'makeLoanPayment']);

// =========================================================================
// 4. System Setup, Verification & Seeder
// =========================================================================
$router->get('/api/system/setup/status', [SystemController::class, 'status']);
$router->post('/api/system/setup/complete-all', [SystemController::class, 'completeAll']);
$router->post('/api/system/setup/step', [SystemController::class, 'runStep']);
$router->post('/api/system/setup/reset-scratch', [SystemController::class, 'resetScratch']);
$router->post('/api/system/purge-operational-data', [SystemController::class, 'resetScratch']);
$router->post('/api/system/reset-seed', [SystemController::class, 'resetSeed']);
$router->post('/api/system/seed-sample-data', [SystemController::class, 'seedSampleMembers']);
$router->post('/api/system/run-verification-tests', [SystemController::class, 'runVerificationTests']);

$router->get('/api/database/seeder', [SeederController::class, 'run']);
$router->post('/api/database/seeder', [SeederController::class, 'run']);
$router->get('/api/database/seeder/reset', [SeederController::class, 'reset']);
$router->post('/api/database/seeder/reset', [SeederController::class, 'reset']);

// =========================================================================
// 5. Members Management (Staff)
// =========================================================================
$router->get('/api/members', [MemberController::class, 'index']);
$router->post('/api/members', [MemberController::class, 'store']);
$router->get('/api/members/:id', [MemberController::class, 'show']);
$router->put('/api/members/:id', [MemberController::class, 'update']);
$router->delete('/api/members/:id', [MemberController::class, 'destroy']);
$router->get('/api/members/:id/report', [MemberController::class, 'report']);

// =========================================================================
// 6. Loans & Credit Management
// =========================================================================
$router->get('/api/loans', [LoanController::class, 'index']);
$router->post('/api/loans', [LoanController::class, 'store']);
$router->get('/api/loans/:id', [LoanController::class, 'show']);
$router->get('/api/loans/:id/schedule', [LoanController::class, 'schedule']);
// $router->post('/api/loans/originate', [LoanController::class, 'originate']);
// $router->post('/api/loans/apply', [LoanController::class, 'apply']);
$router->post('/api/loans/calculate-schedule', [LoanController::class, 'calculateSchedule']);
$router->post('/api/loans/payments', [LoanController::class, 'payment']);
$router->post('/api/loans/repay', [LoanController::class, 'repay']);
$router->post('/api/loans/:id/repay', [LoanController::class, 'repay']);
$router->delete('/api/loans/:id', [LoanController::class, 'destroy']);
// 
$router->put('/api/loan/:id', [LoanController::class, 'updateLoanStatus']);
//New implementation of Loan process
$router->post('/api/loan/applications', [LoanController::class, 'applications']);
$router->post('/api/loan/apply', [LoanController::class, 'apply']);
$router->post('/api/loan/applications/approve', [LoanController::class, 'approveApplication']);
$router->post('/api/loan/applications/reject', [LoanController::class, 'rejectApplication']);
$router->post('/api/loan/originate', [LoanController::class, 'originate']);

// Audit Logs & Regulatory Compliance
$router->get('/api/audit-logs', [ConfigController::class, 'auditLogs']);
$router->post('/api/audit-logs', [ConfigController::class, 'recordAuditLog']);
$router->get('/api/configuration_audit_trails', [ConfigController::class, 'auditLogs']);

// =========================================================================
// 7. Savings Deposit Accounts & Passbooks
// =========================================================================
$router->get('/api/savings/accounts', [SavingsController::class, 'index']);
$router->post('/api/savings/accounts', [SavingsController::class, 'store']);
$router->get('/api/savings/accounts/:id', [SavingsController::class, 'show']);
$router->post('/api/savings/transactions', [SavingsController::class, 'transaction']);
$router->post('/api/savings/transact', [SavingsController::class, 'transaction']);
$router->delete('/api/savings/accounts/:id', [SavingsController::class, 'destroy']);

// =========================================================================
// 8. Share Capital (Capital Build-Up / CBU)
// =========================================================================
$router->get('/api/share-capital/accounts', [ShareCapitalController::class, 'index']);
$router->post('/api/share-capital/accounts', [ShareCapitalController::class, 'store']);
$router->get('/api/share-capital/accounts/:id', [ShareCapitalController::class, 'show']);
$router->put('/api/share-capital/accounts/:id', [ShareCapitalController::class, 'update']);
$router->delete('/api/share-capital/accounts/:id', [ShareCapitalController::class, 'destroy']);
$router->post('/api/share-capital/payments', [ShareCapitalController::class, 'payment']);
$router->post('/api/share-capital/pay', [ShareCapitalController::class, 'payment']);
$router->get('/api/share-capital/settings', [ShareCapitalController::class, 'getSettings']);
$router->post('/api/share-capital/settings', [ShareCapitalController::class, 'storeSetting']);
$router->put('/api/share-capital/settings/:id', [ShareCapitalController::class, 'updateSetting']);

// =========================================================================
// 9. Multi-Branch Cash Accounts & Vault Liquidity
// =========================================================================
$router->get('/api/cash-accounts', [CashController::class, 'index']);
$router->post('/api/cash-accounts', [CashController::class, 'store']);
$router->post('/api/config/cash-accounts', [CashController::class, 'store']);
$router->get('/api/cash-accounts/:id', [CashController::class, 'show']);
$router->put('/api/cash-accounts/:id', [CashController::class, 'update']);
$router->delete('/api/cash-accounts/:id', [CashController::class, 'destroy']);
$router->post('/api/cash-accounts/transfer', [CashController::class, 'transfer']);
$router->post('/api/cash-accounts/replenish', [CashController::class, 'replenish']);
$router->post('/api/cash-accounts/auto-align', [CashController::class, 'autoAlign']);

// =========================================================================
// 10. Accounting & General Ledger (CDA Standard)
// =========================================================================
$router->get('/api/accounting/chart', [AccountingController::class, 'chart']);
$router->post('/api/accounting/chart', [AccountingController::class, 'saveAccount']);
$router->get('/api/config/chart-of-accounts', [AccountingController::class, 'chart']);
$router->post('/api/config/chart-of-accounts', [AccountingController::class, 'saveAccount']);
$router->put('/api/config/chart-of-accounts/:id', [AccountingController::class, 'updateAccount']);
$router->delete('/api/config/chart-of-accounts/:id', [AccountingController::class, 'deleteAccount']);

$router->get('/api/accounting/journals', [AccountingController::class, 'journals']);
$router->post('/api/accounting/journals', [AccountingController::class, 'storeJournal']);
$router->post('/api/accounting/manual-journal', [AccountingController::class, 'manualJournal']);
$router->get('/api/accounting/journals/:id', [AccountingController::class, 'showJournal']);
$router->post('/api/accounting/journals/:id/reverse', [AccountingController::class, 'reverseJournal']);

$router->get('/api/config/accounting-mappings', [AccountingController::class, 'mappings']);
$router->post('/api/config/accounting-mappings', [AccountingController::class, 'storeMapping']);
$router->put('/api/config/accounting-mappings/:id', [AccountingController::class, 'updateMapping']);
$router->delete('/api/config/accounting-mappings/:id', [AccountingController::class, 'deleteMapping']);
$router->post('/api/config/accounting-mappings/reset', [AccountingController::class, 'resetMappings']);

$router->post('/api/config/accounting-periods/close', [AccountingController::class, 'closePeriod']);
$router->post('/api/config/accounting-periods/reopen', [AccountingController::class, 'reopenPeriod']);

// =========================================================================
// 11. Configuration Center
// =========================================================================
$router->get('/api/config/all', [ConfigController::class, 'all']);

// Branches
$router->get('/api/branches', [ConfigController::class, 'branches']);
$router->get('/api/config/branches', [ConfigController::class, 'branches']);
$router->post('/api/branches', [ConfigController::class, 'storeBranch']);
$router->post('/api/config/branches', [ConfigController::class, 'storeBranch']);
$router->put('/api/config/branches/:id', [ConfigController::class, 'updateBranch']);

// Loan Products
$router->get('/api/loan-products', [ConfigController::class, 'loanProducts']);
$router->get('/api/config/loan-products', [ConfigController::class, 'loanProducts']);
$router->get('/api/config/loan-products/:id', [ConfigController::class, 'loanProduct']);
$router->post('/api/config/loan-products', [ConfigController::class, 'storeLoanProduct']);
$router->put('/api/config/loan-products/:id', [ConfigController::class, 'updateLoanProduct']);
$router->delete('/api/config/loan-products/:id', [ConfigController::class, 'destroyLoanProduct']);

// Savings Products
$router->get('/api/savings-products', [ConfigController::class, 'savingsProducts']);
$router->get('/api/config/savings-products', [ConfigController::class, 'savingsProducts']);
$router->post('/api/config/savings-products', [ConfigController::class, 'storeSavingsProduct']);
$router->put('/api/config/savings-products/:id', [ConfigController::class, 'updateSavingsProduct']);
$router->delete('/api/config/savings-products/:id', [ConfigController::class, 'destroySavingsProduct']);

// Feature Toggles
$router->get('/api/feature-toggles', [ConfigController::class, 'featureToggles']);
$router->get('/api/config/feature-toggles', [ConfigController::class, 'featureToggles']);
$router->post('/api/feature-toggles', [ConfigController::class, 'updateToggle']);
$router->post('/api/config/feature-toggles/toggle', [ConfigController::class, 'updateToggle']);

// System Settings
$router->get('/api/system-settings', [ConfigController::class, 'systemSettings']);
$router->get('/api/config/system-settings', [ConfigController::class, 'systemSettings']);
$router->put('/api/config/system-settings', [ConfigController::class, 'updateSystemSettings']);

// Fees
$router->get('/api/config/fees', [ConfigController::class, 'fees']);
$router->post('/api/config/fees', [ConfigController::class, 'storeFee']);
$router->put('/api/config/fees/:id', [ConfigController::class, 'updateFee']);

// Approval Workflows & Rules
$router->get('/api/config/approval-workflows', [ConfigController::class, 'approvalWorkflows']);
$router->get('/api/config/approval-rules', [ConfigController::class, 'approvalRules']);
$router->post('/api/config/approval-rules', [ConfigController::class, 'storeApprovalRule']);
$router->put('/api/config/approval-rules/:id', [ConfigController::class, 'updateApprovalRule']);

// Custom Fields
$router->get('/api/config/custom-fields', [ConfigController::class, 'customFields']);
$router->post('/api/config/custom-fields', [ConfigController::class, 'storeCustomField']);

// Numbering Formats & Payment Allocation
$router->put('/api/config/numbering-formats/:id', [ConfigController::class, 'updateNumberingFormat']);
$router->get('/api/config/payment-allocation-rules/alloc_cda_std', [ConfigController::class, 'getAllocationRules']);
$router->put('/api/config/payment-allocation-rules/:id', [ConfigController::class, 'updatePaymentAllocationRule']);

// =========================================================================
// 12. Financial Reports & Executive Dashboard
// =========================================================================
$router->get('/api/reports/trial-balance', [ReportController::class, 'trialBalance']);
$router->get('/api/reports/financial-statements', [ReportController::class, 'financialStatements']);
$router->get('/api/dashboard/stats', [ReportController::class, 'dashboardStats']);
