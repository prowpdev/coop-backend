<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\MemberController;
use App\Controllers\LoanController;
use App\Controllers\SavingsController;
use App\Controllers\ShareCapitalController;
use App\Controllers\AccountingController;
use App\Controllers\ConfigController;
use App\Controllers\CashController;
use App\Controllers\ReportController;
use App\Controllers\UserController;

/** @var Router $router */

// Authentication & Users
$router->post('/api/auth/login', [UserController::class, 'login']);
$router->post('/api/auth/register', [UserController::class, 'register']);
$router->get('/api/users', [UserController::class, 'index']);
$router->get('/api/user-roles', [UserController::class, 'roles']);
$router->get('/api/users/:id', [UserController::class, 'show']);
$router->delete('/api/users/:id', [UserController::class, 'destroy']);

// System & Home
$router->get('/', [HomeController::class, 'index']);
$router->get('/api', [HomeController::class, 'index']);
$router->get('/test', [HomeController::class, 'test']);
$router->get('/api/test', [HomeController::class, 'test']);

// Configuration Center
$router->get('/api/config/all', [ConfigController::class, 'all']);
$router->get('/api/branches', [ConfigController::class, 'branches']);
$router->get('/api/config/branches', [ConfigController::class, 'branches']);
$router->post('/api/branches', [ConfigController::class, 'storeBranch']);
$router->post('/api/config/branches', [ConfigController::class, 'storeBranch']);
$router->get('/api/loan-products', [ConfigController::class, 'loanProducts']);
$router->get('/api/config/loan-products', [ConfigController::class, 'loanProducts']);
$router->get('/api/savings-products', [ConfigController::class, 'savingsProducts']);
$router->get('/api/config/savings-products', [ConfigController::class, 'savingsProducts']);
$router->get('/api/feature-toggles', [ConfigController::class, 'featureToggles']);
$router->post('/api/feature-toggles', [ConfigController::class, 'updateToggle']);
$router->get('/api/system-settings', [ConfigController::class, 'systemSettings']);

// Members Management
$router->get('/api/members', [MemberController::class, 'index']);
$router->post('/api/members', [MemberController::class, 'store']);
$router->get('/api/members/:id', [MemberController::class, 'show']);
$router->put('/api/members/:id', [MemberController::class, 'update']);
$router->delete('/api/members/:id', [MemberController::class, 'destroy']);
$router->get('/api/members/:id/report', [MemberController::class, 'report']);

// Loans Management
$router->get('/api/loans', [LoanController::class, 'index']);
$router->post('/api/loans', [LoanController::class, 'store']);
$router->get('/api/loans/:id', [LoanController::class, 'show']);
$router->get('/api/loans/:id/schedule', [LoanController::class, 'schedule']);
$router->post('/api/loans/payments', [LoanController::class, 'payment']);
$router->delete('/api/loans/:id', [LoanController::class, 'destroy']);

// Savings Accounts
$router->get('/api/savings/accounts', [SavingsController::class, 'index']);
$router->post('/api/savings/accounts', [SavingsController::class, 'store']);
$router->get('/api/savings/accounts/:id', [SavingsController::class, 'show']);
$router->post('/api/savings/transactions', [SavingsController::class, 'transaction']);
$router->delete('/api/savings/accounts/:id', [SavingsController::class, 'destroy']);

// Share Capital (CBU)
$router->get('/api/share-capital/accounts', [ShareCapitalController::class, 'index']);
$router->post('/api/share-capital/accounts', [ShareCapitalController::class, 'store']);
$router->get('/api/share-capital/accounts/:id', [ShareCapitalController::class, 'show']);
$router->post('/api/share-capital/payments', [ShareCapitalController::class, 'payment']);
$router->delete('/api/share-capital/accounts/:id', [ShareCapitalController::class, 'destroy']);

// Accounting & General Ledger
$router->get('/api/accounting/chart', [AccountingController::class, 'chart']);
$router->get('/api/config/chart-of-accounts', [AccountingController::class, 'chart']);
$router->post('/api/accounting/chart', [AccountingController::class, 'saveAccount']);
$router->get('/api/accounting/journals', [AccountingController::class, 'journals']);
$router->post('/api/accounting/journals', [AccountingController::class, 'storeJournal']);
$router->get('/api/accounting/journals/:id', [AccountingController::class, 'showJournal']);
$router->post('/api/accounting/journals/:id/reverse', [AccountingController::class, 'reverseJournal']);

// Cash Accounts
$router->get('/api/cash-accounts', [CashController::class, 'index']);
$router->get('/api/cash-accounts/:id', [CashController::class, 'show']);
$router->post('/api/cash-accounts/transfer', [CashController::class, 'transfer']);

// Reports & Dashboard
$router->get('/api/reports/trial-balance', [ReportController::class, 'trialBalance']);
$router->get('/api/reports/financial-statements', [ReportController::class, 'financialStatements']);
$router->get('/api/dashboard/stats', [ReportController::class, 'dashboardStats']);
